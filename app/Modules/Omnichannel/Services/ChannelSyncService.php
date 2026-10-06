<?php

namespace App\Modules\Omnichannel\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Omnichannel\Adapters\ShopeeAdapter;
use App\Modules\Omnichannel\Contracts\ChannelAdapterInterface;
use App\Modules\Omnichannel\Jobs\PushStockToChannelJob;
use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Omnichannel\Models\ChannelOrder;
use App\Modules\Omnichannel\Models\ChannelProductMapping;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Services\StokDeductionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orkestrasi sinkronisasi channel (PRD §4.10):
 * - pushStok: gudang sumber per mapping → debounce per SKU (dipanggil dari event StokItem berubah)
 * - pullOrders: webhook/polling → ChannelOrder → Transaksi lokal + potong stok
 */
class ChannelSyncService
{
    /** @var array<string, ChannelAdapterInterface> cache adapter per platform */
    private array $adapters = [];

    public function __construct()
    {
        $this->adapters['shopee'] = new ShopeeAdapter;
        // Fase 2: tokopedia, blibli, tiktok, lazada — implement interface yang sama
    }

    public function adapterFor(string $platform): ?ChannelAdapterInterface
    {
        return $this->adapters[$platform] ?? null;
    }

    /**
     * Dispatch queue job untuk sinkronisasi stok ke channel (debounced per produk).
     */
    public function dispatchSyncStok(int $produkId, ?int $skuVariantId = null): void
    {
        $hasActiveMapping = ChannelProductMapping::where('produk_id', $produkId)
            ->where('status', 'tersinkron')
            ->whereHas('channel', fn ($q) => $q->where('status', 'terhubung')->where('is_active', true))
            ->exists();

        if (! $hasActiveMapping) {
            return;
        }

        if (app()->environment('testing')) {
            $this->syncStokSemuaChannel($produkId, $skuVariantId);

            return;
        }

        PushStockToChannelJob::dispatch($produkId, $skuVariantId);
    }

    /**
     * Push stok semua mapping yang terkait produk — serialized per SKU (anti oversell race).
     */
    public function syncStokSemuaChannel(int $produkId, ?int $skuVariantId = null): void
    {
        $query = ChannelProductMapping::with('channel')
            ->where('produk_id', $produkId)
            ->where('status', 'tersinkron');

        if ($skuVariantId !== null) {
            $query->where(function ($q) use ($skuVariantId) {
                $q->whereNull('sku_variant_id')
                    ->orWhere('sku_variant_id', $skuVariantId);
            });
        }

        $mappings = $query->get();

        foreach ($mappings as $mapping) {
            if (! $mapping->channel || $mapping->channel->status !== 'terhubung' || ! $mapping->channel_item_id) {
                continue;
            }

            $stok = 0;
            if ($mapping->gudang_id) {
                // [T-26] SOT: stok = delta kumulatif StockMutationLog (urut terjadi_at) per produk+gudang.
                $mutasiQuery = StockMutationLog::where('produk_id', $produkId)
                    ->where('gudang_id', $mapping->gudang_id);

                if ($mapping->sku_variant_id) {
                    $mutasiQuery->where('sku_variant_id', $mapping->sku_variant_id);
                }

                $mutasi = $mutasiQuery->orderBy('terjadi_at')->get();

                $stokQuery = StokItem::where('produk_id', $produkId)
                    ->where('gudang_id', $mapping->gudang_id);

                if ($mapping->sku_variant_id) {
                    $stokQuery->where('sku_variant_id', $mapping->sku_variant_id);
                }

                $stok = $mutasi->isEmpty()
                    ? (int) $stokQuery->sum('jumlah')
                    : (int) $mutasi->sum('delta');
            }

            $adapter = $this->adapterFor($mapping->channel->platform);
            if (! $adapter) {
                continue;
            }

            try {
                $items = [
                    [
                        'item_id' => $mapping->channel_item_id,
                        'model_id' => $mapping->channel_model_id ?: 0,
                        'stock' => max(0, $stok),
                    ],
                ];

                $ok = $adapter->pushStock($mapping->channel->kredensial ?? [], $items);

                $mapping->update([
                    'status' => $ok ? 'tersinkron' : 'error',
                    'error_message' => $ok ? null : 'Push stok ditolak channel',
                ]);

                $mapping->channel->update([
                    'last_sync_at' => now(),
                    'last_sync_status' => $ok ? 'sukses' : 'error',
                ]);
            } catch (\Throwable $e) {
                $mapping->update([
                    'status' => 'error',
                    'error_message' => $e->getMessage(),
                ]);
                $mapping->channel->update(['last_sync_status' => 'error']);
            }
        }
    }

    /**
     * Tarik order dari sebuah channel → proses order & kurangi stok lokal.
     */
    public function pullOrders(Channel $channel, ?int $timeFrom = null, ?int $timeTo = null): int
    {
        $adapter = $this->adapterFor($channel->platform);
        if (! $adapter) {
            throw new \Exception("Adapter untuk platform {$channel->platform} belum tersedia");
        }

        $orders = $adapter->pullOrders($channel->kredensial ?? [], $timeFrom, $timeTo);
        $created = 0;

        DB::transaction(function () use ($channel, $orders, &$created) {
            $guard = $this->orderIdTerproses($channel, $orders);

            foreach ($orders as $order) {
                $orderId = $order['order_sn'] ?? $order['order_id'] ?? null;
                if (! $orderId) {
                    continue;
                }

                if (isset($guard[(string) $orderId])) {
                    continue;
                }

                $this->createChannelOrderFromPayload($channel, $order);
                $guard[(string) $orderId] = true;
                $created++;
            }
        });

        $channel->update(['last_sync_at' => now(), 'last_sync_status' => 'sukses']);

        return $created;
    }

    /**
     * [B-15d] Set order_id channel yang SUDAH pernah diproses (landmine idempotensi).
     * Satu query `whereIn` (di-chunk 500) alih-alih 1 query `exists()` per order.
     *
     * @param  array<int, array<string, mixed>>  $orders
     * @return array<string, true>
     */
    protected function orderIdTerproses(Channel $channel, array $orders): array
    {
        $ids = [];
        foreach ($orders as $order) {
            $orderId = $order['order_sn'] ?? $order['order_id'] ?? null;
            if ($orderId === null || $orderId === '') {
                continue;
            }
            $ids[(string) $orderId] = true;
        }

        if ($ids === []) {
            return [];
        }

        $sudah = [];
        foreach (array_chunk(array_keys($ids), 500) as $chunk) {
            $terdaftar = ChannelOrder::where('channel_id', $channel->id)
                ->whereIn('channel_order_id', $chunk)
                ->pluck('channel_order_id');

            foreach ($terdaftar as $s) {
                $sudah[(string) $s] = true;
            }
        }

        return $sudah;
    }

    /**
     * Buat ChannelOrder baru dari raw payload channel.
     */
    public function createChannelOrderFromPayload(Channel $channel, array $orderPayload): ChannelOrder
    {
        $orderId = (string) ($orderPayload['order_sn'] ?? $orderPayload['order_id'] ?? $orderPayload['data']['order_sn'] ?? '');
        $rawStatus = (string) ($orderPayload['order_status'] ?? $orderPayload['data']['status'] ?? $orderPayload['status'] ?? '');
        $totalAmount = (float) ($orderPayload['total_amount'] ?? $orderPayload['data']['total_amount'] ?? 0);
        $biayaPersen = (float) ($channel->kredensial['biaya_persen'] ?? 5);

        $jenisStatus = match ($rawStatus) {
            'COMPLETED' => 'selesai',
            'CANCELLED', 'IN_CANCEL' => 'batal',
            'READY_TO_SHIP', 'PROCESSED', 'SHIPPED', 'TO_CONFIRM_RECEIVE' => 'diproses',
            default => 'menunggu_pembayaran',
        };

        $channelOrder = ChannelOrder::create([
            'channel_id' => $channel->id,
            'channel_order_id' => $orderId,
            'payload' => $orderPayload,
            'channel_status' => $rawStatus ?: null,
            'status' => $jenisStatus,
            'estimasi_biaya_platform' => round($totalAmount * $biayaPersen / 100, 2),
        ]);

        if (in_array($jenisStatus, ['diproses', 'selesai'], true) && ! empty($orderPayload['item_list'])) {
            $this->potongStokDanBuatTransaksi($channel, $channelOrder);
        }

        if ($jenisStatus === 'selesai') {
            $this->prosesBiayaAdmin($channelOrder);
        }

        return $channelOrder;
    }

    /**
     * Ingestion order masuk (baik via polling pullOrders maupun webhook).
     */
    public function prosesOrderMasuk(Channel $channel, array $orderPayload): ChannelOrder
    {
        $orderId = (string) ($orderPayload['order_sn'] ?? $orderPayload['order_id'] ?? $orderPayload['data']['order_sn'] ?? '');
        if ($orderId === '') {
            throw new \Exception('order_sn atau order_id wajib ada pada payload order channel.');
        }

        return DB::transaction(function () use ($channel, $orderId, $orderPayload) {
            $existing = ChannelOrder::where('channel_id', $channel->id)
                ->where('channel_order_id', $orderId)
                ->first();

            if (! $existing) {
                return $this->createChannelOrderFromPayload($channel, $orderPayload);
            }

            // Update status existing
            $rawStatus = (string) ($orderPayload['order_status'] ?? $orderPayload['data']['status'] ?? $orderPayload['status'] ?? $existing->channel_status);
            $totalAmount = (float) ($orderPayload['total_amount'] ?? $orderPayload['data']['total_amount'] ?? 0);
            $biayaPersen = (float) ($channel->kredensial['biaya_persen'] ?? 5);

            $jenisStatus = match ($rawStatus) {
                'COMPLETED' => 'selesai',
                'CANCELLED', 'IN_CANCEL' => 'batal',
                'READY_TO_SHIP', 'PROCESSED', 'SHIPPED', 'TO_CONFIRM_RECEIVE' => 'diproses',
                default => $existing->status,
            };

            $existing->channel_status = $rawStatus;
            $existing->payload = array_merge($existing->payload ?? [], $orderPayload);
            if ($totalAmount > 0) {
                $existing->estimasi_biaya_platform = round($totalAmount * $biayaPersen / 100, 2);
            }
            $existing->status = $jenisStatus;
            $existing->save();

            if (in_array($jenisStatus, ['diproses', 'selesai'], true) && ! $existing->transaksi_id && ! empty($existing->payload['item_list'])) {
                $this->potongStokDanBuatTransaksi($channel, $existing);
            }

            if ($jenisStatus === 'selesai') {
                $this->prosesBiayaAdmin($existing);
            }

            return $existing;
        });
    }

    /**
     * Buat Transaksi lokal dan potong stok gudang untuk order channel.
     */
    protected function potongStokDanBuatTransaksi(Channel $channel, ChannelOrder $channelOrder): void
    {
        $payload = $channelOrder->payload ?? [];
        $items = $payload['item_list'] ?? [];
        $totalAmount = (float) ($payload['total_amount'] ?? 0);

        $cabangId = $channel->kredensial['cabang_id'] ?? session('cabang_id') ?? 1;
        $noTransaksi = 'TRX-CH-'.$channel->id.'-'.$channelOrder->channel_order_id;

        $transaksi = Transaksi::firstOrCreate(
            ['no_transaksi' => $noTransaksi],
            [
                'cabang_id' => $cabangId,
                'sumber' => 'channel:'.$channel->platform,
                'subtotal' => $totalAmount,
                'total_akhir' => $totalAmount,
                'metode_bayar' => 'marketplace',
                'status' => 'selesai',
            ]
        );

        foreach ($items as $item) {
            $itemId = $item['item_id'] ?? null;
            $modelId = $item['model_id'] ?? null;
            $itemSku = $item['item_sku'] ?? $item['model_sku'] ?? null;
            $qty = (int) ($item['model_quantity_purchased'] ?? $item['quantity_purchased'] ?? 1);

            if ($qty <= 0) {
                continue;
            }

            // Cari mapping produk berdasarkan model_id atau item_id atau SKU
            $mappingQuery = ChannelProductMapping::where('channel_id', $channel->id);
            if ($modelId && $modelId !== 0 && $modelId !== '0') {
                $mappingQuery->where(function ($q) use ($modelId, $itemId) {
                    $q->where('channel_model_id', (string) $modelId)
                        ->orWhere('channel_item_id', (string) $itemId);
                });
            } elseif ($itemId) {
                $mappingQuery->where('channel_item_id', (string) $itemId);
            } elseif ($itemSku) {
                $mappingQuery->where('channel_sku', (string) $itemSku);
            }

            $mapping = $mappingQuery->first();
            if ($mapping && $mapping->gudang_id) {
                try {
                    app(StokDeductionService::class)->kurangi(
                        produkId: $mapping->produk_id,
                        skuVariantId: $mapping->sku_variant_id,
                        gudangId: $mapping->gudang_id,
                        qty: $qty,
                        jenis: 'penjualan',
                        referensiTipe: ChannelOrder::class,
                        referensiId: $channelOrder->id,
                        userId: null,
                        catatan: "Order {$channel->nama} #{$channelOrder->channel_order_id}",
                        izinkanNegatif: false
                    );
                } catch (\Throwable $e) {
                    Log::warning("Gagal potong stok order channel #{$channelOrder->channel_order_id}: {$e->getMessage()}");
                }
            }
        }

        $channelOrder->update(['transaksi_id' => $transaksi->id]);
    }

    /**
     * [T-26] Jurnal beban biaya admin marketplace saat ChannelOrder diproses (status selesai).
     * Jurnal: Debit 520-06 "Beban Biaya Admin Marketplace" / Kredit 110-01 Kas.
     */
    public function prosesBiayaAdmin(ChannelOrder $order): void
    {
        $sudahAda = JurnalAkuntansi::where('referensi_tipe', 'channel_order')
            ->where('referensi_id', $order->id)
            ->exists();
        if ($sudahAda) {
            return;
        }

        $biaya = round((float) $order->estimasi_biaya_platform, 2);
        if ($biaya <= 0) {
            return;
        }

        AkunCOA::firstOrCreate(
            ['kode' => '520-06'],
            ['nama' => 'Beban Biaya Admin Marketplace', 'tipe' => 'beban', 'kelompok' => 'biaya_marketplace', 'saldo_normal' => 'debit', 'is_active' => true]
        );
        AkunCOA::firstOrCreate(
            ['kode' => '110-01'],
            ['nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit', 'is_active' => true]
        );

        try {
            $cabangId = $order->payload['cabang_id'] ?? session('cabang_id') ?? 1;
            app(JurnalService::class)->post(
                app(JurnalService::class)->generateNoJurnal('channel', $cabangId),
                $order->updated_at ?? now(),
                'channel',
                [
                    ['akun_kode' => '520-06', 'debit' => $biaya, 'kredit' => 0],
                    ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => $biaya],
                ],
                'Biaya admin marketplace '.($order->channel?->nama ?? 'channel').' — order '.$order->channel_order_id,
                $cabangId,
                null,
                'channel_order',
                $order->id
            );
        } catch (\Throwable $e) {
            Log::warning("Jurnal biaya admin channel gagal (order #{$order->id}): {$e->getMessage()}");
        }
    }

    /**
     * Periksa dan refresh access token yang akan kedaluwarsa dalam 1 jam.
     */
    public function refreshExpiredTokens(): int
    {
        $channels = Channel::where('status', 'terhubung')
            ->where('is_active', true)
            ->get();

        $refreshed = 0;
        foreach ($channels as $channel) {
            if (! $channel->shouldRefreshToken(3600)) {
                continue;
            }

            $adapter = $this->adapterFor($channel->platform);
            if (! $adapter) {
                continue;
            }

            try {
                $newTokens = $adapter->refreshAccessToken($channel->kredensial ?? []);
                $kredensial = array_merge($channel->kredensial ?? [], $newTokens);
                $channel->update([
                    'kredensial' => $kredensial,
                    'last_sync_at' => now(),
                    'last_sync_status' => 'sukses',
                ]);
                $refreshed++;
                Log::info("Token channel {$channel->nama} berhasil di-refresh");
            } catch (\Throwable $e) {
                $channel->update(['last_sync_status' => 'error']);
                Log::error("Gagal refresh token channel {$channel->nama}: {$e->getMessage()}");
            }
        }

        return $refreshed;
    }
}
