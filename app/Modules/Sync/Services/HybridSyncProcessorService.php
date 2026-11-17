<?php

namespace App\Modules\Sync\Services;

use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Sync\Models\HybridSyncInbox;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Services\StokDeductionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HybridSyncProcessorService
{
    /**
     * Process incoming sync payload.
     * Guaranteed idempotent by checking hybrid_sync_inbox.
     */
    public function process(array $data): array
    {
        $syncUuid = $data['sync_uuid'] ?? null;
        $eventType = $data['event_type'] ?? null;
        $sourceNode = $data['source_node'] ?? null;
        $payload = $data['payload'] ?? null;

        if (empty($syncUuid) || empty($eventType) || empty($sourceNode) || ! is_array($payload)) {
            return [
                'success' => false,
                'data' => null,
                'message' => 'Payload tidak lengkap. Parameter sync_uuid, event_type, source_node, dan payload wajib ada.',
            ];
        }

        // Idempotency Check: jika sync_uuid sudah tercatat di inbox
        $existing = HybridSyncInbox::where('sync_uuid', $syncUuid)->first();
        if ($existing) {
            return [
                'success' => true,
                'data' => ['sync_uuid' => $syncUuid, 'status' => $existing->status],
                'message' => 'Sudah diproses sebelumnya (idempotent skip).',
            ];
        }

        $payloadHash = hash('sha256', json_encode($payload));

        $inbox = HybridSyncInbox::create([
            'sync_uuid' => $syncUuid,
            'source_node' => $sourceNode,
            'event_type' => $eventType,
            'payload_hash' => $payloadHash,
            'status' => 'processing',
            'processed_at' => now(),
        ]);

        $nodeRole = config('sync.node_role');

        try {
            DB::beginTransaction();

            if ($nodeRole === 'cloud_storefront') {
                $this->processForCloud($eventType, $payload);
            } elseif ($nodeRole === 'primary_store') {
                $this->processForPrimaryStore($eventType, $payload);
            } else {
                // Default fallback: allow handling based on event type
                $this->processGeneric($eventType, $payload);
            }

            $inbox->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'data' => ['sync_uuid' => $syncUuid],
                'message' => "Event {$eventType} berhasil disinkronisasi.",
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            $inbox->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("[HybridSync] Gagal memproses event {$eventType} ({$syncUuid}): ".$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'data' => ['sync_uuid' => $syncUuid],
                'message' => 'Gagal memproses sinkronisasi: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Inbound handlers for cloud_storefront (VPS menerima dari Toko Lokal)
     */
    protected function processForCloud(string $eventType, array $payload): void
    {
        match ($eventType) {
            'produk.upsert' => $this->handleProdukUpsert($payload),
            'stok.mutasi' => $this->handleStokMutasi($payload),
            'transaksi.pos' => $this->handleTransaksiPos($payload),
            'servis.update' => $this->handleServisUpdate($payload),
            default => Log::info("[HybridSync] Cloud menerima event tidak ditangani: {$eventType}"),
        };
    }

    /**
     * Inbound handlers for primary_store (Lokal menerima dari VPS)
     */
    protected function processForPrimaryStore(string $eventType, array $payload): void
    {
        match ($eventType) {
            'order.online_paid' => $this->handleOrderOnlinePaid($payload),
            default => $this->processGeneric($eventType, $payload),
        };
    }

    /**
     * Generic fallback handler
     */
    protected function processGeneric(string $eventType, array $payload): void
    {
        match ($eventType) {
            'produk.upsert' => $this->handleProdukUpsert($payload),
            'stok.mutasi' => $this->handleStokMutasi($payload),
            'transaksi.pos' => $this->handleTransaksiPos($payload),
            'servis.update' => $this->handleServisUpdate($payload),
            'order.online_paid' => $this->handleOrderOnlinePaid($payload),
            default => Log::info("[HybridSync] Generic event: {$eventType}"),
        };
    }

    /**
     * Handle produk.upsert: sinkron master produk & harga tier
     */
    protected function handleProdukUpsert(array $payload): void
    {
        if (! class_exists(Produk::class)) {
            Log::warning('[HybridSync] Model Produk tidak tersedia.');

            return;
        }

        $nama = $payload['nama'] ?? 'Produk Sync';
        $slug = $payload['slug'] ?? Str::slug($nama).'-'.($payload['id'] ?? uniqid());

        $attributes = [
            'nama' => $nama,
            'harga_jual_retail' => $payload['harga_jual_retail'] ?? $payload['harga_jual'] ?? $payload['harga'] ?? 0,
            'harga_beli' => $payload['harga_beli'] ?? $payload['harga_modal'] ?? 0,
            'is_active' => $payload['is_active'] ?? $payload['aktif'] ?? true,
        ];

        if (isset($payload['barcode'])) {
            $attributes['barcode'] = $payload['barcode'];
        }

        if (isset($payload['id']) && $payload['id']) {
            $existing = Produk::find($payload['id']);
            if ($existing) {
                $existing->update($attributes);
            } else {
                DB::table('produk')->insert(array_merge($attributes, [
                    'id' => $payload['id'],
                    'slug' => $slug,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        } else {
            Produk::updateOrCreate(['slug' => $slug], $attributes);
        }
    }

    /**
     * Handle stok.mutasi: sinkron saldo stok item akhir
     */
    protected function handleStokMutasi(array $payload): void
    {
        if (! class_exists(StokItem::class)) {
            Log::warning('[HybridSync] Model StokItem tidak tersedia.');

            return;
        }

        $produkId = $payload['produk_id'] ?? null;
        $cabangId = $payload['cabang_id'] ?? null;
        $stokAkhir = $payload['stok_akhir'] ?? $payload['jumlah'] ?? null;

        if ($produkId && $stokAkhir !== null) {
            // Pastikan produk ada di database agar tidak melanggar foreign key constraint
            if (! Produk::where('id', $produkId)->exists()) {
                DB::table('produk')->insert([
                    'id' => $produkId,
                    'nama' => 'Produk '.$produkId,
                    'slug' => 'produk-'.$produkId.'-'.uniqid(),
                    'harga_jual_retail' => 0,
                    'harga_beli' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Temukan atau buat gudang default untuk cabang tersebut
            $gudangId = $payload['gudang_id'] ?? null;
            if (! $gudangId && class_exists(Gudang::class)) {
                $gudang = Gudang::getDefault($cabangId);
                $gudangId = $gudang?->id;
            }

            if ($gudangId) {
                StokItem::updateOrCreate(
                    [
                        'produk_id' => $produkId,
                        'gudang_id' => $gudangId,
                    ],
                    [
                        'jumlah' => $stokAkhir,
                    ]
                );
            }
        }
    }

    /**
     * Handle transaksi.pos: rekap ringkasan POS untuk monitoring owner di VPS
     */
    protected function handleTransaksiPos(array $payload): void
    {
        Log::info('[HybridSync] Transaksi POS tersinkron ke cloud:', [
            'nomor_transaksi' => $payload['nomor_transaksi'] ?? $payload['no_transaksi'] ?? null,
            'total' => $payload['total_akhir'] ?? $payload['total'] ?? 0,
            'cabang_id' => $payload['cabang_id'] ?? null,
        ]);
    }

    /**
     * Handle servis.update: update progress tiket servis untuk pelacakan publik
     */
    protected function handleServisUpdate(array $payload): void
    {
        if (! class_exists(TiketServis::class)) {
            Log::warning('[HybridSync] Model TiketServis tidak tersedia.');

            return;
        }

        $id = $payload['id'] ?? null;
        $nomorTiket = $payload['no_tiket'] ?? $payload['nomor_tiket'] ?? null;

        $tiket = null;
        if ($id) {
            $tiket = TiketServis::find($id);
        }
        if (! $tiket && $nomorTiket) {
            $tiket = TiketServis::where('no_tiket', $nomorTiket)->first();
        }

        if ($tiket && isset($payload['status'])) {
            $tiket->status = $payload['status'];
            if (isset($payload['catatan_teknisi'])) {
                $tiket->catatan_admin = $payload['catatan_teknisi'];
            }
            $tiket->saveQuietly();
        }
    }

    /**
     * Handle order.online_paid: pesanan marketplace lunas masuk toko lokal
     * Potong stok fisik resmi via StokDeductionService dan buat jurnal akuntansi.
     */
    protected function handleOrderOnlinePaid(array $payload): void
    {
        $orderId = $payload['order_id'] ?? null;
        $nomorOrder = $payload['nomor_order'] ?? ('ONLINE-'.($orderId ?? uniqid()));
        $cabangId = $payload['cabang_id'] ?? (class_exists(Cabang::class) ? Cabang::first()?->id ?? 1 : 1);
        $total = $payload['total'] ?? 0;
        $items = $payload['items'] ?? [];

        // 1. Catat ke Transaksi lokal jika model tersedia
        $transaksi = null;
        if (class_exists(Transaksi::class)) {
            $transaksi = Transaksi::firstOrCreate(
                ['no_transaksi' => $nomorOrder],
                [
                    'cabang_id' => $cabangId,
                    'total_akhir' => $total,
                    'subtotal' => $total,
                    'status' => 'lunas', // Status 'lunas' agar langsung muncul di tab 'Siap Dikemas' pada PesananOnlineIndex
                    'sumber' => 'marketplace',
                    'metode_bayar' => 'transfer',
                    'catatan' => 'Pesanan Online Lunas dari Cloud VPS',
                ]
            );

            // Simpan items transaksi jika belum ada
            if ($transaksi->wasRecentlyCreated && ! empty($items) && class_exists(TransaksiItem::class)) {
                foreach ($items as $it) {
                    $pId = $it['produk_id'] ?? null;
                    if ($pId) {
                        // Pastikan produk ada di database lokal agar tidak melanggar foreign key constraint
                        if (class_exists(Produk::class) && ! Produk::where('id', $pId)->exists()) {
                            DB::table('produk')->insert([
                                'id' => $pId,
                                'nama' => $it['nama'] ?? 'Produk '.$pId,
                                'slug' => 'produk-'.$pId.'-'.uniqid(),
                                'harga_jual_retail' => $it['harga'] ?? 0,
                                'harga_beli' => 0,
                                'is_active' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        TransaksiItem::create([
                            'transaksi_id' => $transaksi->id,
                            'produk_id' => $pId,
                            'sku_variant_id' => $it['sku_variant_id'] ?? null,
                            'jumlah' => $it['qty'] ?? $it['jumlah'] ?? 1,
                            'satuan' => 'pcs',
                            'konversi' => 1,
                            'harga_satuan' => $it['harga'] ?? 0,
                            'subtotal' => ($it['harga'] ?? 0) * ($it['qty'] ?? 1),
                            'hpp' => $it['hpp'] ?? 0,
                        ]);
                    }
                }
            }
        }

        // 2. Potong stok fisik resmi via StokDeductionService jika ada
        if (class_exists(StokDeductionService::class) && ! empty($items)) {
            try {
                $stokService = app(StokDeductionService::class);
                foreach ($items as $item) {
                    $produkId = $item['produk_id'] ?? null;
                    $qty = (int) round(($item['qty'] ?? $item['jumlah'] ?? 0) * ($item['konversi'] ?? 1));
                    $skuVariantId = $item['sku_variant_id'] ?? null;

                    if ($produkId && $qty > 0) {
                        if (method_exists($stokService, 'kurangiDariGudangTersedia')) {
                            $stokService->kurangiDariGudangTersedia(
                                $produkId,
                                $skuVariantId,
                                $qty,
                                $cabangId,
                                'penjualan',
                                Transaksi::class,
                                $transaksi?->id ?? 0,
                                null,
                                "Pesanan Online Lunas {$nomorOrder}"
                            );
                        } elseif (method_exists($stokService, 'deductStock')) {
                            $stokService->deductStock($produkId, $cabangId, $qty, "Order Online {$nomorOrder}");
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[HybridSync] Warning potong stok online: '.$e->getMessage());
            }
        }

        // 3. Buat jurnal otomatis jika JurnalService ada
        if (class_exists(JurnalService::class) && $total > 0) {
            try {
                $jurnalService = app(JurnalService::class);
                $noJurnal = $jurnalService->generateNoJurnal('pos', $cabangId);
                $lines = [
                    ['akun_kode' => '120-02', 'debit' => (float) $total, 'kredit' => 0], // Piutang Payment Gateway
                    ['akun_kode' => '410-03', 'debit' => 0, 'kredit' => (float) $total], // Pendapatan Penjualan Online
                ];

                $jurnalService->post(
                    $noJurnal,
                    now(),
                    'pos',
                    $lines,
                    "Jurnal otomatis pesanan online {$nomorOrder}",
                    $cabangId,
                    null,
                    false
                );
            } catch (\Throwable $e) {
                Log::warning('[HybridSync] Warning catat jurnal online: '.$e->getMessage());
            }
        }
    }
}
