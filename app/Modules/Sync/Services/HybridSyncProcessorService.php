<?php

namespace App\Modules\Sync\Services;

use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Sync\Models\HybridSyncInbox;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\StokDeductionService;
use Carbon\Carbon;
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

        return SyncExecutionGuard::runWithoutSync(function () use ($eventType, $payload, $syncUuid, $inbox, $nodeRole) {
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
        });
    }

    /**
     * Inbound handlers for cloud_storefront (VPS menerima dari Toko Lokal)
     */
    protected function processForCloud(string $eventType, array $payload): void
    {
        match ($eventType) {
            'produk.upsert' => $this->handleProdukUpsert($payload),
            'sku_variant.upsert' => $this->handleSkuVariantUpsert($payload),
            'pelanggan.upsert' => $this->handlePelangganUpsert($payload),
            'kategori_produk.upsert' => $this->handleKategoriProdukUpsert($payload),
            'brand.upsert' => $this->handleBrandUpsert($payload),
            'kualitas_produk.upsert' => $this->handleKualitasProdukUpsert($payload),
            'satuan_unit.upsert' => $this->handleSatuanUnitUpsert($payload),
            'tipe_hp.upsert' => $this->handleTipeHpUpsert($payload),
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
            'transaksi.pos_cloud' => $this->handleTransaksiPosCloud($payload),
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
            'sku_variant.upsert' => $this->handleSkuVariantUpsert($payload),
            'pelanggan.upsert' => $this->handlePelangganUpsert($payload),
            'kategori_produk.upsert' => $this->handleKategoriProdukUpsert($payload),
            'brand.upsert' => $this->handleBrandUpsert($payload),
            'kualitas_produk.upsert' => $this->handleKualitasProdukUpsert($payload),
            'satuan_unit.upsert' => $this->handleSatuanUnitUpsert($payload),
            'tipe_hp.upsert' => $this->handleTipeHpUpsert($payload),
            'stok.mutasi' => $this->handleStokMutasi($payload),
            'transaksi.pos' => $this->handleTransaksiPos($payload),
            'transaksi.pos_cloud' => $this->handleTransaksiPosCloud($payload),
            'servis.update' => $this->handleServisUpdate($payload),
            'order.online_paid' => $this->handleOrderOnlinePaid($payload),
            default => Log::info("[HybridSync] Generic event: {$eventType}"),
        };
    }

    /**
     * Tentukan apakah data masuk lebih baru dari data lokal (last-write-wins).
     */
    protected function isIncomingNewer(?string $incoming, $existing): bool
    {
        if (empty($incoming)) {
            return true;
        }

        $existingTs = null;
        if (is_object($existing) && method_exists($existing, 'getAttribute')) {
            $val = $existing->updated_at ?? null;
            $existingTs = $val instanceof \DateTimeInterface ? $val->format('c') : (string) $val;
        } elseif (is_string($existing)) {
            $existingTs = $existing;
        }

        if (empty($existingTs)) {
            return true;
        }

        $in = strtotime($incoming);
        $ex = strtotime($existingTs);

        if ($in === false || $ex === false) {
            return true;
        }

        return $in > $ex;
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

        if (array_key_exists('cabang_id', $payload)) {
            $attributes['cabang_id'] = $payload['cabang_id'];
        }

        if (isset($payload['barcode'])) {
            $attributes['barcode'] = $payload['barcode'];
        }

        if (isset($payload['id']) && $payload['id']) {
            $existing = Produk::find($payload['id']);
            if ($existing) {
                if (! $this->isIncomingNewer($payload['updated_at'] ?? null, $existing)) {
                    return;
                }
                $existing->fill($attributes);
                $existing->saveQuietly();
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
     * Handle pelanggan.upsert: sinkron master pelanggan dari toko lokal ke cloud.
     */
    protected function handlePelangganUpsert(array $payload): void
    {
        if (! class_exists(Pelanggan::class)) {
            Log::warning('[HybridSync] Model Pelanggan tidak tersedia.');

            return;
        }

        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            Log::warning('[HybridSync] Pelanggan tanpa id valid - sinkronisasi dilewati.');

            return;
        }

        $attributes = [
            'nama' => $payload['nama'] ?? 'Pelanggan Sync',
            'username' => $payload['username'] ?? null,
            'telepon' => $payload['telepon'] ?? null,
            'email' => $payload['email'] ?? null,
            'alamat' => $payload['alamat'] ?? null,
            'tanggal_lahir' => $payload['tanggal_lahir'] ?? null,
            'tier_membership_id' => $payload['tier_membership_id'] ?? null,
            'is_reseller' => (bool) ($payload['is_reseller'] ?? false),
            'kode_agen' => $payload['kode_agen'] ?? null,
            'referral_kode' => $payload['referral_kode'] ?? null,
            'tipe_konsumen' => $payload['tipe_konsumen'] ?? 'retail',
            'mode_reward_mitra' => $payload['mode_reward_mitra'] ?? null,
            'poin_loyalty' => $payload['poin_loyalty'] ?? 0,
            'saldo_piutang' => $payload['saldo_piutang'] ?? 0,
            'max_piutang' => $payload['max_piutang'] ?? 0,
            'saldo_deposit' => $payload['saldo_deposit'] ?? 0,
            'status' => $payload['status'] ?? 'aktif',
            'catatan_approval' => $payload['catatan_approval'] ?? null,
            'approved_at' => $payload['approved_at'] ?? null,
            'approved_by' => $payload['approved_by'] ?? null,
        ];

        $pelanggan = Pelanggan::find($id);
        if ($pelanggan) {
            if (! $this->isIncomingNewer($payload['updated_at'] ?? null, $pelanggan)) {
                return;
            }
            $pelanggan->fill($attributes);
            $pelanggan->saveQuietly();
        } else {
            $pelanggan = new Pelanggan;
            $pelanggan->id = $id;
            $pelanggan->forceFill($attributes);
            $pelanggan->saveQuietly();
        }
    }

    /**
     * Handle kategori_produk.upsert: sinkron master kategori produk.
     */
    protected function handleKategoriProdukUpsert(array $payload): void
    {
        if (! class_exists(KategoriProduk::class)) {
            Log::warning('[HybridSync] Model KategoriProduk tidak tersedia.');

            return;
        }

        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $attributes = [
            'parent_id' => $payload['parent_id'] ?? null,
            'nama' => $payload['nama'] ?? 'Kategori Sync',
            'slug' => $payload['slug'] ?? null,
            'icon' => $payload['icon'] ?? null,
            'urutan' => $payload['urutan'] ?? 0,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'is_default' => (bool) ($payload['is_default'] ?? false),
        ];

        $model = KategoriProduk::find($id);
        if ($model) {
            if (! $this->isIncomingNewer($payload['updated_at'] ?? null, $model)) {
                return;
            }
            $model->fill($attributes);
            $model->saveQuietly();
        } else {
            $model = new KategoriProduk;
            $model->id = $id;
            $model->forceFill($attributes);
            $model->saveQuietly();
        }
    }

    /**
     * Handle brand.upsert: sinkron master brand.
     */
    protected function handleBrandUpsert(array $payload): void
    {
        if (! class_exists(Brand::class)) {
            Log::warning('[HybridSync] Model Brand tidak tersedia.');

            return;
        }

        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $attributes = [
            'nama' => $payload['nama'] ?? 'Brand Sync',
            'keterangan' => $payload['keterangan'] ?? null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'is_default' => (bool) ($payload['is_default'] ?? false),
        ];

        $model = Brand::find($id);
        if ($model) {
            if (! $this->isIncomingNewer($payload['updated_at'] ?? null, $model)) {
                return;
            }
            $model->fill($attributes);
            $model->saveQuietly();
        } else {
            $model = new Brand;
            $model->id = $id;
            $model->forceFill($attributes);
            $model->saveQuietly();
        }
    }

    /**
     * Handle kualitas_produk.upsert: sinkron master kualitas produk.
     */
    protected function handleKualitasProdukUpsert(array $payload): void
    {
        if (! class_exists(KualitasProduk::class)) {
            Log::warning('[HybridSync] Model KualitasProduk tidak tersedia.');

            return;
        }

        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $brandId = $payload['brand_id'] ?? null;
        if ($brandId !== null && ! Brand::where('id', $brandId)->exists()) {
            $brandId = null;
        }

        $attributes = [
            'nama' => $payload['nama'] ?? 'Kualitas Sync',
            'keterangan' => $payload['keterangan'] ?? null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'brand_id' => $brandId,
            'is_default' => (bool) ($payload['is_default'] ?? false),
        ];

        $model = KualitasProduk::find($id);
        if ($model) {
            if (! $this->isIncomingNewer($payload['updated_at'] ?? null, $model)) {
                return;
            }
            $model->fill($attributes);
            $model->saveQuietly();
        } else {
            $model = new KualitasProduk;
            $model->id = $id;
            $model->forceFill($attributes);
            $model->saveQuietly();
        }
    }

    /**
     * Handle satuan_unit.upsert: sinkron master satuan.
     */
    protected function handleSatuanUnitUpsert(array $payload): void
    {
        if (! class_exists(SatuanUnit::class)) {
            Log::warning('[HybridSync] Model SatuanUnit tidak tersedia.');

            return;
        }

        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $attributes = [
            'kode' => $payload['kode'] ?? ('SAT-'.$id),
            'nama' => $payload['nama'] ?? 'Satuan Sync',
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'is_default' => (bool) ($payload['is_default'] ?? false),
        ];

        $model = SatuanUnit::find($id);
        if ($model) {
            if (! $this->isIncomingNewer($payload['updated_at'] ?? null, $model)) {
                return;
            }
            $model->fill($attributes);
            $model->saveQuietly();
        } else {
            $model = new SatuanUnit;
            $model->id = $id;
            $model->forceFill($attributes);
            $model->saveQuietly();
        }
    }

    /**
     * Handle tipe_hp.upsert: sinkron master tipe HP.
     */
    protected function handleTipeHpUpsert(array $payload): void
    {
        if (! class_exists(TipeHp::class)) {
            Log::warning('[HybridSync] Model TipeHp tidak tersedia.');

            return;
        }

        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $attributes = [
            'merk' => $payload['merk'] ?? null,
            'model' => $payload['model'] ?? null,
            'kode_produk' => $payload['kode_produk'] ?? null,
            'nama' => $payload['nama'] ?? null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'is_default' => (bool) ($payload['is_default'] ?? false),
        ];

        $model = TipeHp::find($id);
        if ($model) {
            if (! $this->isIncomingNewer($payload['updated_at'] ?? null, $model)) {
                return;
            }
            $model->fill($attributes);
            $model->saveQuietly();
        } else {
            $model = new TipeHp;
            $model->id = $id;
            $model->forceFill($attributes);
            $model->saveQuietly();
        }

        if (class_exists(TipeHp::class) && method_exists(TipeHp::class, 'clearResolutionCache')) {
            TipeHp::clearResolutionCache();
        }
    }

    /**
     * Handle sku_variant.upsert: sinkron SKU varian produk.
     */
    protected function handleSkuVariantUpsert(array $payload): void
    {
        if (! class_exists(SkuVariant::class)) {
            Log::warning('[HybridSync] Model SkuVariant tidak tersedia.');

            return;
        }

        $id = (int) ($payload['id'] ?? 0);
        $produkId = (int) ($payload['produk_id'] ?? 0);
        if ($id <= 0 || $produkId <= 0) {
            return;
        }

        // Pastikan produk induk ada di cloud; jika belum, buat placeholder minimal.
        if (! Produk::where('id', $produkId)->exists()) {
            Produk::insert([
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

        $attributes = [
            'produk_id' => $produkId,
            'sku' => $payload['sku'] ?? null,
            'barcode' => $payload['barcode'] ?? null,
            'nama_varian' => $payload['nama_varian'] ?? null,
            'satuan_kode' => $payload['satuan_kode'] ?? null,
            'atribut' => $payload['atribut'] ?? null,
            'harga_beli' => $payload['harga_beli'] ?? 0,
            'harga_jual_retail' => $payload['harga_jual_retail'] ?? 0,
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ];

        $model = SkuVariant::find($id);
        if ($model) {
            if (! $this->isIncomingNewer($payload['updated_at'] ?? null, $model)) {
                return;
            }
            $model->fill($attributes);
            $model->saveQuietly();
        } else {
            $model = new SkuVariant;
            $model->id = $id;
            $model->forceFill($attributes);
            $model->saveQuietly();
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
        // Ambil delta mutasi (perubahan) jika tersedia
        $jumlahMutasi = isset($payload['perubahan']) ? (float) $payload['perubahan'] : (isset($payload['jumlah']) ? (float) $payload['jumlah'] : null);
        $stokAkhir = isset($payload['stok_akhir']) ? (float) $payload['stok_akhir'] : null;

        if ($produkId) {
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
                $stokItem = StokItem::firstOrCreate(
                    [
                        'produk_id' => $produkId,
                        'gudang_id' => $gudangId,
                    ],
                    [
                        'jumlah' => 0,
                    ]
                );

                // Delta-based mutation: jika ada nilai delta mutasi selain 0
                if ($jumlahMutasi !== null && $jumlahMutasi != 0.0) {
                    $stokItem->increment('jumlah', $jumlahMutasi);
                } elseif ($stokAkhir !== null) {
                    // Fallback ke stok_akhir hanya jika delta mutasi tidak tersedia
                    $stokItem->jumlah = $stokAkhir;
                    $stokItem->saveQuietly();
                }
            }
        }
    }

    /**
     * Handle transaksi.pos: rekap transaksi POS lengkap, simpan items, dan posting jurnal di VPS
     */
    protected function handleTransaksiPos(array $payload): void
    {
        if (! class_exists(Transaksi::class)) {
            Log::warning('[HybridSync] Model Transaksi tidak tersedia.');

            return;
        }

        $uuid = $payload['offline_uuid'] ?? null;
        $noTransaksi = $payload['no_transaksi'] ?? $payload['nomor_transaksi'] ?? null;

        if (! $noTransaksi && ! $uuid) {
            Log::warning('[HybridSync] Transaksi POS diabaikan: nomor transaksi atau UUID tidak ada.');

            return;
        }

        // 1. Idempotensi check
        $query = Transaksi::query();
        if ($uuid) {
            $query->where('offline_uuid', $uuid);
        } else {
            $query->where('no_transaksi', $noTransaksi);
        }
        $existing = $query->first();

        if ($existing) {
            Log::info("[HybridSync] Transaksi POS {$noTransaksi} ({$uuid}) sudah ada di database, lewati.");

            return;
        }

        $cabangId = (int) ($payload['cabang_id'] ?? (class_exists(Cabang::class) ? Cabang::first()?->id ?? 1 : 1));
        $totalAkhir = (float) ($payload['total_akhir'] ?? $payload['total'] ?? 0);
        $clientCreatedAt = isset($payload['created_at'])
            ? rescue(fn () => Carbon::parse($payload['created_at']), now())
            : now();

        // 2. Simpan Transaksi di Cloud
        $transaksi = Transaksi::create([
            'no_transaksi' => $noTransaksi,
            'cabang_id' => $cabangId,
            'kasir_id' => $payload['kasir_id'] ?? 1,
            'pelanggan_id' => $payload['pelanggan_id'] ?? null,
            'gudang_id' => $payload['gudang_id'] ?? null,
            'sumber' => $payload['sumber'] ?? 'pos',
            'subtotal' => (float) ($payload['subtotal'] ?? $totalAkhir),
            'diskon_nominal' => (float) ($payload['diskon_nominal'] ?? 0),
            'pajak_nominal' => (float) ($payload['pajak_nominal'] ?? 0),
            'ppn_nominal' => (float) ($payload['pajak_nominal'] ?? 0),
            'total_akhir' => $totalAkhir,
            'metode_bayar' => $payload['metode_bayar'] ?? 'tunai',
            'jumlah_bayar' => (float) ($payload['jumlah_bayar'] ?? $totalAkhir),
            'kembalian' => (float) ($payload['kembalian'] ?? 0),
            'split_detail' => $payload['split_detail'] ?? null,
            'status' => $payload['status'] ?? 'selesai',
            'catatan' => $payload['catatan'] ?? null,
            'offline_uuid' => $uuid,
            'is_offline' => true,
            'client_created_at' => $clientCreatedAt,
            'synced_at' => now(),
            'sync_status' => 'synced',
        ]);

        // 3. Simpan TransaksiItem
        $items = $payload['items'] ?? [];
        $totalHpp = 0.0;

        foreach ($items as $item) {
            $konversi = max(0.0001, (float) ($item['konversi'] ?? 1));
            $qty = (float) ($item['jumlah'] ?? 1);
            $hppPerSatuan = (float) ($item['hpp'] ?? 0);

            TransaksiItem::create([
                'transaksi_id' => $transaksi->id,
                'produk_id' => $item['produk_id'] ?? 0,
                'sku_variant_id' => $item['sku_variant_id'] ?? null,
                'jumlah' => $qty,
                'satuan' => $item['satuan'] ?? 'pcs',
                'konversi' => $konversi,
                'harga_satuan' => (float) ($item['harga_satuan'] ?? 0),
                'diskon_nominal' => (float) ($item['diskon_nominal'] ?? 0),
                'subtotal' => (float) ($item['subtotal'] ?? 0),
                'harga_final' => $qty > 0 ? round((float) ($item['subtotal'] ?? 0) / $qty, 2) : (float) ($item['harga_satuan'] ?? 0),
                'hpp' => $hppPerSatuan,
                'catatan' => $item['catatan'] ?? null,
            ]);

            $totalHpp += ($hppPerSatuan * $qty);
        }

        // 4. Update Akumulasi Belanja Pelanggan CRM
        if (! empty($payload['pelanggan_id']) && class_exists(PelangganService::class)) {
            try {
                app(PelangganService::class)->tambahBelanjaDanPoin(
                    (int) $payload['pelanggan_id'],
                    $totalAkhir,
                    $transaksi->id
                );
            } catch (\Throwable $e) {
                Log::warning("[HybridSync] Gagal update poin pelanggan {$payload['pelanggan_id']}: {$e->getMessage()}");
            }
        }

        // 5. Posting Jurnal Double-Entry di Cloud
        if (class_exists(JurnalService::class)) {
            try {
                $jurnalService = app(JurnalService::class);
                $noJurnal = $jurnalService->generateNoJurnal('pos', $cabangId);

                $barisJurnal = [
                    [
                        'akun_kode' => '110-04', // Kas Toko / Kasir
                        'nama_akun' => 'Kas',
                        'debit' => $totalAkhir,
                        'kredit' => 0.0,
                        'keterangan' => "Penerimaan Kas POS Sync {$transaksi->no_transaksi}",
                    ],
                    [
                        'akun_kode' => '410-01', // Pendapatan Penjualan
                        'nama_akun' => 'Pendapatan Penjualan',
                        'debit' => 0.0,
                        'kredit' => $totalAkhir,
                        'keterangan' => "Penjualan POS Sync {$transaksi->no_transaksi}",
                    ],
                ];

                if ($totalHpp > 0) {
                    $barisJurnal[] = [
                        'akun_kode' => '510-02',
                        'nama_akun' => 'Harga Pokok Penjualan',
                        'debit' => $totalHpp,
                        'kredit' => 0.0,
                        'keterangan' => "HPP POS Sync {$transaksi->no_transaksi}",
                    ];
                    $barisJurnal[] = [
                        'akun_kode' => '130-01',
                        'nama_akun' => 'Persediaan Barang Dagang',
                        'debit' => 0.0,
                        'kredit' => $totalHpp,
                        'keterangan' => "Pengurangan Persediaan POS Sync {$transaksi->no_transaksi}",
                    ];
                }

                $jurnalService->post(
                    noJurnal: $noJurnal,
                    tanggal: $clientCreatedAt->toDateString(),
                    sumber: 'pos',
                    lines: $barisJurnal,
                    deskripsi: "Jurnal POS Sync {$transaksi->no_transaksi}",
                    cabangId: $cabangId,
                    userId: $transaksi->kasir_id,
                    referensiTipe: Transaksi::class,
                    referensiId: $transaksi->id
                );
            } catch (\Throwable $jurnalEx) {
                Log::warning("[HybridSync] Gagal membuat jurnal POS Sync {$transaksi->no_transaksi}: {$jurnalEx->getMessage()}");
            }
        }
    }

    /**
     * Handle transaksi.pos_cloud: transaksi kasir darurat di cloud ditarik dan diserap ke toko lokal
     */
    protected function handleTransaksiPosCloud(array $payload): void
    {
        if (! class_exists(Transaksi::class)) {
            Log::warning('[HybridSync] Model Transaksi tidak tersedia.');

            return;
        }

        $uuid = $payload['offline_uuid'] ?? null;
        $noTransaksi = $payload['no_transaksi'] ?? $payload['nomor_transaksi'] ?? null;

        if (! $noTransaksi && ! $uuid) {
            Log::warning('[HybridSync] Transaksi Cloud POS diabaikan: nomor transaksi atau UUID tidak ada.');

            return;
        }

        // 1. Idempotensi check
        $query = Transaksi::query();
        if ($uuid) {
            $query->where('offline_uuid', $uuid);
        } else {
            $query->where('no_transaksi', $noTransaksi);
        }
        $existing = $query->first();

        if ($existing) {
            Log::info("[HybridSync] Transaksi Cloud POS {$noTransaksi} ({$uuid}) sudah ada di database lokal, lewati.");

            return;
        }

        $cabangId = (int) ($payload['cabang_id'] ?? (class_exists(Cabang::class) ? Cabang::first()?->id ?? 1 : 1));
        $totalAkhir = (float) ($payload['total_akhir'] ?? $payload['total'] ?? 0);
        $clientCreatedAt = isset($payload['created_at'])
            ? rescue(fn () => Carbon::parse($payload['created_at']), now())
            : now();

        // Cari atau gunakan gudang default cabang
        $gudangId = $payload['gudang_id'] ?? null;
        if (! $gudangId && class_exists(Gudang::class)) {
            $gudang = Gudang::getDefault($cabangId);
            $gudangId = $gudang?->id;
        }

        // 2. Simpan Transaksi di Toko Lokal
        $transaksi = Transaksi::create([
            'no_transaksi' => $noTransaksi,
            'cabang_id' => $cabangId,
            'kasir_id' => $payload['kasir_id'] ?? 1,
            'pelanggan_id' => $payload['pelanggan_id'] ?? null,
            'gudang_id' => $gudangId,
            'sumber' => $payload['sumber'] ?? 'pos',
            'subtotal' => (float) ($payload['subtotal'] ?? $totalAkhir),
            'diskon_nominal' => (float) ($payload['diskon_nominal'] ?? 0),
            'pajak_nominal' => (float) ($payload['pajak_nominal'] ?? 0),
            'ppn_nominal' => (float) ($payload['pajak_nominal'] ?? 0),
            'total_akhir' => $totalAkhir,
            'metode_bayar' => $payload['metode_bayar'] ?? 'tunai',
            'jumlah_bayar' => (float) ($payload['jumlah_bayar'] ?? $totalAkhir),
            'kembalian' => (float) ($payload['kembalian'] ?? 0),
            'split_detail' => $payload['split_detail'] ?? null,
            'status' => $payload['status'] ?? 'selesai',
            'catatan' => $payload['catatan'] ?? '[FAILOVER CLOUD POS]',
            'offline_uuid' => $uuid,
            'is_offline' => false,
            'client_created_at' => $clientCreatedAt,
            'synced_at' => now(),
            'sync_status' => 'synced',
        ]);

        // 3. Simpan TransaksiItem & Potong Stok Fisik Lokal
        $items = $payload['items'] ?? [];
        $totalHpp = 0.0;

        foreach ($items as $item) {
            $konversi = max(0.0001, (float) ($item['konversi'] ?? 1));
            $qty = (float) ($item['jumlah'] ?? 1);
            $qtyDasar = (int) round($qty * $konversi);
            $hppPerSatuan = (float) ($item['hpp'] ?? 0);
            $produkId = (int) ($item['produk_id'] ?? 0);

            TransaksiItem::create([
                'transaksi_id' => $transaksi->id,
                'produk_id' => $produkId,
                'sku_variant_id' => $item['sku_variant_id'] ?? null,
                'jumlah' => $qty,
                'satuan' => $item['satuan'] ?? 'pcs',
                'konversi' => $konversi,
                'harga_satuan' => (float) ($item['harga_satuan'] ?? 0),
                'diskon_nominal' => (float) ($item['diskon_nominal'] ?? 0),
                'subtotal' => (float) ($item['subtotal'] ?? 0),
                'harga_final' => $qty > 0 ? round((float) ($item['subtotal'] ?? 0) / $qty, 2) : (float) ($item['harga_satuan'] ?? 0),
                'hpp' => $hppPerSatuan,
                'catatan' => $item['catatan'] ?? null,
            ]);

            $totalHpp += ($hppPerSatuan * $qty);

            // Potong stok fisik lokal karena barang terjual di failover cloud
            if ($gudangId && $produkId > 0 && class_exists(StokItem::class)) {
                $stokItem = StokItem::firstOrCreate(
                    ['produk_id' => $produkId, 'gudang_id' => $gudangId],
                    ['jumlah' => 0]
                );
                $stokItem->decrement('jumlah', $qtyDasar);
            }
        }

        // 4. Update Akumulasi Belanja Pelanggan CRM di Lokal
        if (! empty($payload['pelanggan_id']) && class_exists(PelangganService::class)) {
            try {
                app(PelangganService::class)->tambahBelanjaDanPoin(
                    (int) $payload['pelanggan_id'],
                    $totalAkhir,
                    $transaksi->id
                );
            } catch (\Throwable $e) {
                Log::warning("[HybridSync] Gagal update poin pelanggan failover {$payload['pelanggan_id']}: {$e->getMessage()}");
            }
        }

        // 5. Posting Jurnal Double-Entry di Toko Lokal
        if (class_exists(JurnalService::class)) {
            try {
                $jurnalService = app(JurnalService::class);
                $noJurnal = $jurnalService->generateNoJurnal('pos', $cabangId);

                $barisJurnal = [
                    [
                        'akun_kode' => '110-04', // Kas Toko / Kasir
                        'nama_akun' => 'Kas',
                        'debit' => $totalAkhir,
                        'kredit' => 0.0,
                        'keterangan' => "Penerimaan Kas Cloud POS Failover {$transaksi->no_transaksi}",
                    ],
                    [
                        'akun_kode' => '410-01', // Pendapatan Penjualan
                        'nama_akun' => 'Pendapatan Penjualan',
                        'debit' => 0.0,
                        'kredit' => $totalAkhir,
                        'keterangan' => "Penjualan Cloud POS Failover {$transaksi->no_transaksi}",
                    ],
                ];

                if ($totalHpp > 0) {
                    $barisJurnal[] = [
                        'akun_kode' => '510-02',
                        'nama_akun' => 'Harga Pokok Penjualan',
                        'debit' => $totalHpp,
                        'kredit' => 0.0,
                        'keterangan' => "HPP Cloud POS Failover {$transaksi->no_transaksi}",
                    ];
                    $barisJurnal[] = [
                        'akun_kode' => '130-01',
                        'nama_akun' => 'Persediaan Barang Dagang',
                        'debit' => 0.0,
                        'kredit' => $totalHpp,
                        'keterangan' => "Pengurangan Persediaan Cloud POS Failover {$transaksi->no_transaksi}",
                    ];
                }

                $jurnalService->post(
                    noJurnal: $noJurnal,
                    tanggal: $clientCreatedAt->toDateString(),
                    sumber: 'pos',
                    lines: $barisJurnal,
                    deskripsi: "Jurnal Cloud POS Failover {$transaksi->no_transaksi}",
                    cabangId: $cabangId,
                    userId: $transaksi->kasir_id,
                    referensiTipe: Transaksi::class,
                    referensiId: $transaksi->id
                );
            } catch (\Throwable $jurnalEx) {
                Log::warning("[HybridSync] Gagal membuat jurnal Cloud POS Failover {$transaksi->no_transaksi}: {$jurnalEx->getMessage()}");
            }
        }
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
     * Handle order.online_paid: pesanan marketplace lunas masuk toko lokal.
     *
     * [C4] Kontrak satuan/HPP mengikuti POS-native (PosCheckoutService &
     * TransaksiVoidService):
     * - satuan & konversi asli payload disimpan apa adanya (default: satuan dasar
     *   produk + konversi 1) — TIDAK hardcode pcs/1;
     * - hpp per SATUAN tervalidasi: hpp cloud <= 0 -> harga_beli lokal x konversi;
     * - stok dipotong qtyDasar = round(jumlah x konversi), konsisten dgn tersimpan;
     * - jurnal WAJIB: Dr 120-02 total / Cr 410-03 DPP (+ Cr 220-01 PPN Keluaran,
     *   Cr 210-04 Titipan Ongkir) DAN baris HPP Dr 510-02 / Cr 130-01;
     * - fail-CLOSED: kegagalan stok/jurnal melempar exception -> process()
     *   me-rollback seluruh event & menandai inbox failed (selaras KasSesiState);
     * - produk tak dikenal TIDAK auto-create harga_beli=0: event ditolak dengan
     *   error jelas agar master produk disinkronkan lebih dulu.
     */
    protected function handleOrderOnlinePaid(array $payload): void
    {
        $nomorOrder = $payload['nomor_order'] ?? ('ONLINE-'.($payload['order_id'] ?? uniqid()));
        $cabangId = (int) ($payload['cabang_id'] ?? (class_exists(Cabang::class) ? Cabang::first()?->id ?? 1 : 1));
        $total = (float) ($payload['total'] ?? 0);
        $items = $payload['items'] ?? [];

        if (! is_array($items) || empty($items)) {
            throw new \RuntimeException("Pesanan online {$nomorOrder} tidak memiliki item — sinkronisasi ditolak.");
        }
        if ($total <= 0) {
            throw new \RuntimeException("Total pesanan online {$nomorOrder} tidak valid ({$total}) — sinkronisasi ditolak.");
        }

        // Idempotensi per nomor order: transaksi sudah ada berarti event ini sudah
        // pernah diproses utuh (atomik) — jangan potong stok / posting jurnal dobel.
        if (Transaksi::where('no_transaksi', $nomorOrder)->exists()) {
            Log::info("[HybridSync] Pesanan online {$nomorOrder} sudah tercatat lokal — skip (idempotent).");

            return;
        }

        // 1. Normalisasi & validasi seluruh item SEBELUM menulis apa pun (fail-closed).
        $normalized = [];
        $totalHpp = 0.0;
        foreach ($items as $it) {
            if (! is_array($it)) {
                throw new \RuntimeException("Item pesanan online {$nomorOrder} tidak valid — sinkronisasi ditolak.");
            }

            $produkId = (int) ($it['produk_id'] ?? 0);
            if ($produkId <= 0) {
                throw new \RuntimeException("Item pesanan online {$nomorOrder} tanpa produk_id — sinkronisasi ditolak.");
            }
            $produk = Produk::find($produkId);
            if (! $produk) {
                throw new \RuntimeException("Produk {$produkId} (pesanan {$nomorOrder}) tidak ditemukan di database lokal. Sinkronkan master produk terlebih dahulu — pesanan tidak diproses.");
            }

            $jumlah = (float) ($it['qty'] ?? $it['jumlah'] ?? 0);
            if ($jumlah <= 0) {
                throw new \RuntimeException("Jumlah item produk {$produkId} (pesanan {$nomorOrder}) tidak valid — sinkronisasi ditolak.");
            }
            $konversi = (float) ($it['konversi'] ?? 1);
            if ($konversi <= 0) {
                throw new \RuntimeException("Konversi satuan item produk {$produkId} (pesanan {$nomorOrder}) tidak valid — sinkronisasi ditolak.");
            }
            $satuan = (string) ($it['satuan'] ?? ($produk->satuan ?: 'pcs'));
            $qtyDasar = (int) round($jumlah * $konversi);
            if ($qtyDasar <= 0) {
                throw new \RuntimeException("Qty dasar item produk {$produkId} (pesanan {$nomorOrder}) membulat ke nol — sinkronisasi ditolak.");
            }

            // hpp per SATUAN: nilai cloud valid (>0) dipakai apa adanya; bila
            // 0/negatif/kosong fallback harga_beli master lokal x konversi.
            $hppCloud = (float) ($it['hpp'] ?? 0);
            $hppPerSatuan = $hppCloud > 0 ? $hppCloud : (float) $produk->harga_beli * $konversi;

            $harga = (float) ($it['harga'] ?? $it['harga_satuan'] ?? 0);
            $diskon = (float) ($it['diskon_nominal'] ?? 0);

            $normalized[] = [
                'produk_id' => $produkId,
                'sku_variant_id' => isset($it['sku_variant_id']) ? (int) $it['sku_variant_id'] : null,
                'jumlah' => $jumlah,
                'satuan' => $satuan,
                'konversi' => $konversi,
                'qty_dasar' => $qtyDasar,
                'harga_satuan' => $harga,
                'diskon_nominal' => $diskon,
                'subtotal' => round($harga * $jumlah - $diskon, 2),
                'hpp' => $hppPerSatuan,
            ];
            // Total HPP = hpp per satuan x jumlah (= qtyDasar x harga_beli utk fallback lokal).
            $totalHpp += $hppPerSatuan * $jumlah;
        }
        $totalHpp = round($totalHpp, 2);

        if ($totalHpp <= 0) {
            throw new \RuntimeException("HPP pesanan online {$nomorOrder} nol — perbarui harga_beli produk lokal atau kirim hpp valid dari cloud sebelum sinkronisasi.");
        }

        // 2. Pisah DPP/PPN/ongkir dari kolom cloud (pola DuitkuPaymentProcessor).
        $ongkir = max(0.0, (float) ($payload['ongkir'] ?? 0));
        $totalBarang = max(0.0, round($total - $ongkir, 2));
        [$dpp, $ppn] = $this->pisahDppPpnPayload($payload, $totalBarang, $nomorOrder);

        // 3. Transaksi + item dengan satuan/konversi asli & hpp per satuan tervalidasi.
        $transaksi = Transaksi::create([
            'no_transaksi' => $nomorOrder,
            'cabang_id' => $cabangId,
            'subtotal' => $totalBarang,
            'dpp' => $dpp,
            'pajak_nominal' => $ppn,
            'ppn_nominal' => $ppn,
            'total_akhir' => $total,
            'jumlah_bayar' => $total,
            'paid_at' => now(),
            'status' => 'lunas', // Status 'lunas' agar langsung muncul di tab 'Siap Dikemas' pada PesananOnlineIndex
            'sumber' => 'marketplace',
            'metode_bayar' => 'transfer',
            'catatan' => 'Pesanan Online Lunas dari Cloud VPS',
        ]);

        foreach ($normalized as $n) {
            TransaksiItem::create([
                'transaksi_id' => $transaksi->id,
                'produk_id' => $n['produk_id'],
                'sku_variant_id' => $n['sku_variant_id'],
                'jumlah' => $n['jumlah'],
                'satuan' => $n['satuan'],
                'konversi' => $n['konversi'],
                'harga_satuan' => $n['harga_satuan'],
                'diskon_nominal' => $n['diskon_nominal'],
                'subtotal' => $n['subtotal'],
                'hpp' => $n['hpp'],
            ]);
        }

        // 4. Potong stok fisik qtyDasar — exception TIDAK ditelan (fail-closed:
        // stok kurang / gudang tak ada -> seluruh event rollback).
        $stokService = app(StokDeductionService::class);
        foreach ($normalized as $n) {
            $stokService->kurangiDariGudangTersedia(
                $n['produk_id'],
                $n['sku_variant_id'],
                $n['qty_dasar'],
                $cabangId,
                'penjualan',
                Transaksi::class,
                $transaksi->id,
                null,
                "Pesanan Online Lunas {$nomorOrder} ({$n['jumlah']} {$n['satuan']})"
            );
        }

        // 5. Jurnal lengkap: kliring / pendapatan DPP / PPN / ongkir / HPP.
        // Gagal posting -> exception -> rollback stok & transaksi (fail-closed).
        $jurnalService = app(JurnalService::class);
        $noJurnal = $jurnalService->generateNoJurnal('pos', $cabangId);
        $lines = [
            ['akun_kode' => '120-02', 'debit' => $total, 'kredit' => 0], // Kliring Payment Gateway
            ['akun_kode' => '410-03', 'debit' => 0, 'kredit' => $dpp], // Pendapatan Online (DPP, tanpa PPN/ongkir)
        ];
        if ($ongkir > 0) {
            $lines[] = ['akun_kode' => '210-04', 'debit' => 0, 'kredit' => $ongkir]; // Titipan Ongkir
        }
        foreach (app(PajakService::class)->jurnalLines($ppn, $noJurnal, $cabangId, 0) as $ppnLine) {
            $lines[] = $ppnLine; // PPN Keluaran 220-01
        }
        // HPP wajib berpasangan dengan pendapatan: Dr 510-02 / Cr 130-01 sebesar total HPP tervalidasi.
        $lines[] = ['akun_kode' => '510-02', 'debit' => $totalHpp, 'kredit' => 0];
        $lines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $totalHpp];

        $jurnalService->post(
            $noJurnal,
            now(),
            'pos',
            $lines,
            "Jurnal otomatis pesanan online {$nomorOrder}",
            $cabangId,
            null,
            Transaksi::class,
            $transaksi->id
        );
    }

    /**
     * Pisah DPP/PPN dari kolom payload cloud — pola DuitkuPaymentProcessor::pisahDppPpn.
     * ppn_nominal/pajak_nominal/ppn eksplisit dipakai; bila kosong, PPN disimpulkan
     * dari selisih total barang vs dpp cloud. TIDAK menghitung ulang dari konfigurasi
     * pajak lokal (nilai pajak dihitung sekali saat order dibuat di cloud).
     *
     * @return array{0: float, 1: float} [dpp, ppnNominal]
     */
    private function pisahDppPpnPayload(array $payload, float $totalBarang, string $nomorOrder): array
    {
        $ppn = max(0.0, (float) ($payload['ppn_nominal'] ?? $payload['pajak_nominal'] ?? $payload['ppn'] ?? 0));

        if ($ppn <= 0) {
            $dppCloud = (float) ($payload['dpp'] ?? 0);
            if ($dppCloud > 0 && $dppCloud < $totalBarang) {
                $ppn = round($totalBarang - $dppCloud, 2);
            }
        }

        if ($totalBarang > 0 && $ppn >= $totalBarang) {
            throw new \RuntimeException("PPN pesanan online {$nomorOrder} ({$ppn}) >= total barang ({$totalBarang}) — data pajak tidak valid.");
        }

        $dpp = $ppn > 0 ? round($totalBarang - $ppn, 2) : $totalBarang;

        return [$dpp, $ppn];
    }
}
