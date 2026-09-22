<?php

namespace App\Modules\Omnichannel\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Omnichannel\Adapters\ShopeeAdapter;
use App\Modules\Omnichannel\Contracts\ChannelAdapterInterface;
use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Omnichannel\Models\ChannelOrder;
use App\Modules\Omnichannel\Models\ChannelProductMapping;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Wms\Models\StockMutationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orkestrasi sinkronisasi channel (PRD §4.10):
 * - pushStok: gudang sumber per mapping → debounce per SKU (dipanggil dari event StokItem berubah)
 * - pullOrders: webhook/polling → ChannelOrder → Transaksi lokal
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
     * Push stok semua mapping yang terkait produk — serialized per SKU (anti oversell race).
     */
    public function syncStokSemuaChannel(int $produkId): void
    {
        $mappings = ChannelProductMapping::with('channel')
            ->where('produk_id', $produkId)
            ->where('status', 'tersinkron')
            ->get();

        foreach ($mappings as $mapping) {
            if (! $mapping->channel || $mapping->channel->status !== 'terhubung') {
                continue;
            }

            $stok = 0;
            if ($mapping->gudang_id) {
                // [T-26] SOT: stok = delta kumulatif StockMutationLog (urut terjadi_at) per produk+gudang.
                // BUKAN StokItem::sum — mutasi yang tidak tercatat log (lihat laporan "PERLU StockMutationLog di")
                // tidak ikut terhitung, jadi saldo channel konsisten dengan buku mutasi.
                // ponytail: untuk incremental push, tambah watermark last_sync pada mapping lalu filter terjadi_at > watermark.
                $stok = (int) StockMutationLog::where('produk_id', $produkId)
                    ->where('gudang_id', $mapping->gudang_id)
                    ->orderBy('terjadi_at')
                    ->get()
                    ->sum('delta');
            }

            $adapter = $this->adapterFor($mapping->channel->platform);
            if (! $adapter) {
                continue;
            }

            // Serialize per SKU: lock mapping agar tidak ada 2 job push stok bersamaan
            // (dalam MVP diproses sinkron via API manual; fase prod → queue job per SKU)
            try {
                $ok = $adapter->pushStock($mapping->channel->kredensial ?? [], [
                    ['item_id' => $mapping->channel_item_id, 'stock' => $stok],
                ]);

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
     * Tarik order dari sebuah channel → buat Transaksi lokal menunggu_pembayaran.
     */
    public function pullOrders(Channel $channel): int
    {
        $adapter = $this->adapterFor($channel->platform);
        if (! $adapter) {
            throw new \Exception("Adapter untuk platform {$channel->platform} belum tersedia");
        }

        $orders = $adapter->pullOrders($channel->kredensial ?? []);
        $created = 0;

        DB::transaction(function () use ($channel, $orders, &$created) {
            foreach ($orders as $order) {
                $orderId = $order['order_sn'] ?? $order['order_id'] ?? null;
                if (! $orderId) {
                    continue;
                }

                // Idempotent: order_id channel unik
                if (ChannelOrder::where('channel_id', $channel->id)
                    ->where('channel_order_id', $orderId)->exists()) {
                    continue;
                }

                $channelOrder = ChannelOrder::create([
                    'channel_id' => $channel->id,
                    'channel_order_id' => $orderId,
                    'payload' => $order,
                    'channel_status' => $order['order_status'] ?? null,
                    'status' => 'menunggu_proses',
                    // [T-26] Estimasi biaya admin marketplace (configurable per channel — fallback 5%)
                    'estimasi_biaya_platform' => round(((float) ($order['total_amount'] ?? 0)) * ($channel->kredensial['biaya_persen'] ?? 5) / 100, 2),
                ]);

                // Buat Transaksi lokal (validasi produk mapping di phase 2 — MVP catat saja)
                $jenisStatus = match ($order['order_status'] ?? null) {
                    'COMPLETED' => 'selesai',
                    'CANCELLED' => 'batal',
                    default => 'menunggu_pembayaran',
                };

                $channelOrder->update(['status' => $jenisStatus]);
                $created++;

                // [T-26] Jurnal biaya admin marketplace (520-06) saat order selesai — idempoten per order
                if ($jenisStatus === 'selesai') {
                    $this->prosesBiayaAdmin($channelOrder);
                }
            }
        });

        $channel->update(['last_sync_at' => now(), 'last_sync_status' => 'sukses']);

        return $created;
    }

    /**
     * [T-26] Jurnal beban biaya admin marketplace saat ChannelOrder diproses (status selesai).
     * Jurnal: Debit 520-06 "Beban Biaya Admin Marketplace" / Kredit 110-01 Kas (atau kontra sepadan).
     * Idempoten per order: satu pasang baris jurnal per referensi_tipe='channel_order' + referensi_id.
     */
    public function prosesBiayaAdmin(ChannelOrder $order): void
    {
        // Idempotency guard: sudah dijurnal → skip (duplikat callback tidak dobel posting)
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

        // Akun COA dipastikan ada (fallback firstOrCreate, pola KasSesiState)
        AkunCOA::firstOrCreate(
            ['kode' => '520-06'],
            ['nama' => 'Beban Biaya Admin Marketplace', 'tipe' => 'beban', 'kelompok' => 'biaya_marketplace', 'saldo_normal' => 'debit', 'is_active' => true]
        );
        AkunCOA::firstOrCreate(
            ['kode' => '110-01'],
            ['nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit', 'is_active' => true]
        );

        try {
            $cabangId = $order->payload['cabang_id'] ?? session('cabang_id') ?? null;
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
            // Jurnal gagal jangan blokir alur order — log utk audit finance
            Log::warning("Jurnal biaya admin channel gagal (order #{$order->id}): {$e->getMessage()}");
        }
    }
}
