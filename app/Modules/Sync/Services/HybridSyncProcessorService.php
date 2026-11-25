<?php

namespace App\Modules\Sync\Services;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Sync\Models\HybridSyncInbox;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ProdukBarcode;
use App\Modules\Wms\Models\ProdukSatuanKonversi;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\StokDeductionService;
use App\Support\NomorTeleponIndonesia;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HybridSyncProcessorService
{
    /**
     * Cache keberadaan kolom `sync_uuid` per tabel agar lookup aman saat pre-migrasi.
     *
     * @var array<string, bool>
     */
    private static array $syncUuidColumnCache = [];

    /**
     * Proses payload event sync masuk secara idempotent.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, data: array<string, mixed>|null, message: string}
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

        // Idempotency Check: hanya skip jika sebelumnya BENAR-BENAR sukses ('processed').
        // Jika status sebelumnya 'failed' atau stale 'processing', izinkan retry bersih.
        $existing = HybridSyncInbox::where('sync_uuid', $syncUuid)->first();
        if ($existing && $existing->status === 'processed') {
            return [
                'success' => true,
                'data' => ['sync_uuid' => $syncUuid, 'status' => $existing->status],
                'message' => 'Sudah diproses sebelumnya (idempotent skip).',
            ];
        }

        $payloadHash = hash('sha256', (string) json_encode($payload));

        if ($existing) {
            $existing->update([
                'source_node' => $sourceNode,
                'event_type' => $eventType,
                'payload_hash' => $payloadHash,
                'status' => 'processing',
                'error_message' => null,
                'processed_at' => now(),
            ]);
            $inbox = $existing;
        } else {
            $inbox = HybridSyncInbox::create([
                'sync_uuid' => $syncUuid,
                'source_node' => $sourceNode,
                'event_type' => $eventType,
                'payload_hash' => $payloadHash,
                'status' => 'processing',
                'processed_at' => now(),
            ]);
        }

        $nodeRole = config('sync.node_role');

        return SyncExecutionGuard::runWithoutSync(function () use ($eventType, $payload, $syncUuid, $inbox, $nodeRole) {
            try {
                DB::beginTransaction();

                if ($nodeRole === 'cloud_storefront') {
                    $this->processForCloud($eventType, $payload);
                } elseif ($nodeRole === 'primary_store') {
                    $this->processForPrimaryStore($eventType, $payload);
                } else {
                    $this->processGeneric($eventType, $payload);
                }

                $inbox->update([
                    'status' => 'processed',
                    'error_message' => null,
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
     * Inbound handlers untuk cloud_storefront (VPS menerima dari Toko Lokal / Backoffice).
     */
    protected function processForCloud(string $eventType, array $payload): void
    {
        match ($eventType) {
            'transaksi.pos' => $this->upsertSyncedPosTransaction($payload, false),
            'transaksi.void' => $this->handleTransaksiVoid($payload),
            'stok.mutasi' => $this->handleStokMutasi($payload),
            'servis.update' => $this->handleServisUpdate($payload),
            'jurnal.upsert' => $this->handleJurnalUpsert($payload),
            'payroll.upsert' => $this->handlePayrollUpsert($payload),
            default => $this->processGeneric($eventType, $payload),
        };
    }

    /**
     * Inbound handlers untuk primary_store (PC Toko menerima dari Cloud / Order Online).
     */
    protected function processForPrimaryStore(string $eventType, array $payload): void
    {
        match ($eventType) {
            'order.online_paid' => $this->handleOrderOnlinePaid($payload),
            'transaksi.pos_cloud' => $this->upsertSyncedPosTransaction($payload, true),
            'transaksi.void' => $this->handleTransaksiVoid($payload),
            'servis.update' => $this->handleServisUpdate($payload),
            'jurnal.upsert' => $this->handleJurnalUpsert($payload),
            'payroll.upsert' => $this->handlePayrollUpsert($payload),
            default => $this->processGeneric($eventType, $payload),
        };
    }

    protected function processGeneric(string $eventType, array $payload): void
    {
        match ($eventType) {
            'produk.upsert' => $this->handleProdukUpsert($payload),
            'pelanggan.upsert' => $this->handlePelangganUpsert($payload),
            'kategori_produk.upsert' => $this->handleKategoriProdukUpsert($payload),
            'brand.upsert' => $this->handleBrandUpsert($payload),
            'kualitas_produk.upsert' => $this->handleKualitasProdukUpsert($payload),
            'satuan_unit.upsert' => $this->handleSatuanUnitUpsert($payload),
            'tipe_hp.upsert' => $this->handleTipeHpUpsert($payload),
            'sku_variant.upsert' => $this->handleSkuVariantUpsert($payload),
            'harga_tier.upsert' => $this->handleHargaTierUpsert($payload),
            'produk_satuan_konversi.upsert' => $this->handleProdukSatuanKonversiUpsert($payload),
            'produk_barcode.sync' => $this->handleProdukBarcodeSync($payload),
            default => Log::notice("[HybridSync] Abaikan event tidak dikenal: {$eventType}"),
        };
    }

    /**
     * Cek LWW timestamp: true jika timestamp masuk lebih baru daripada existing.
     */
    protected function isIncomingNewer(?string $incoming, mixed $existing): bool
    {
        if (empty($incoming) || empty($existing)) {
            return true;
        }

        try {
            return Carbon::parse($incoming)->gte(Carbon::parse($existing));
        } catch (\Throwable) {
            return true;
        }
    }

    protected function tableHasSyncUuid(string $modelClass): bool
    {
        $table = (new $modelClass)->getTable();
        if (! array_key_exists($table, self::$syncUuidColumnCache)) {
            self::$syncUuidColumnCache[$table] = Schema::hasTable($table) && Schema::hasColumn($table, 'sync_uuid');
        }

        return self::$syncUuidColumnCache[$table];
    }

    /**
     * Resolusi target record berbasis sync_uuid -> natural keys -> ID non-kolisi.
     * Mencegah record offline node berbeda saling menimpa akibat bentrok auto-increment ID.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>|callable>  $naturalMatchers
     * @return TModel|null
     */
    protected function resolveTargetModel(
        string $modelClass,
        array $payload,
        array $naturalMatchers = [],
        bool $withTrashed = false
    ): ?Model {
        $baseQuery = fn () => $withTrashed && method_exists($modelClass, 'withTrashed')
            ? $modelClass::withTrashed()
            : $modelClass::query();

        $hasSyncUuid = $this->tableHasSyncUuid($modelClass);
        $incomingUuid = ! empty($payload['sync_uuid']) ? (string) $payload['sync_uuid'] : null;

        if ($hasSyncUuid && $incomingUuid !== null) {
            $found = $baseQuery()->where('sync_uuid', $incomingUuid)->first();
            if ($found) {
                return $found;
            }
        }

        foreach ($naturalMatchers as $matcher) {
            $query = $baseQuery();
            $matched = false;

            if (is_callable($matcher)) {
                $matched = (bool) $matcher($query);
            } elseif ($matcher !== []) {
                $matched = true;
                foreach ($matcher as $col => $val) {
                    if ($val === null || $val === '') {
                        $matched = false;
                        break;
                    }
                    $query->where($col, $val);
                }
            }

            if ($matched) {
                $found = $query->first();
                if ($found) {
                    return $found;
                }
            }
        }

        if (! empty($payload['id'])) {
            $byId = $baseQuery()->find($payload['id']);
            if ($byId) {
                $existingSyncUuid = $byId->getAttribute('sync_uuid');
                if ($hasSyncUuid && $incomingUuid !== null && ! empty($existingSyncUuid) && $existingSyncUuid !== $incomingUuid) {
                    return null;
                }

                if ($hasSyncUuid && $incomingUuid !== null) {
                    return null;
                }

                return $byId;
            }
        }

        return null;
    }

    /**
     * Resolusi foreign key (produk, kategori, brand, dll.) lintas node.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>>  $naturalMatchers
     */
    protected function resolveForeignId(
        string $modelClass,
        array $payload,
        string $idKey,
        ?string $uuidKey = null,
        array $naturalMatchers = [],
        bool $withTrashed = false
    ): ?int {
        $baseQuery = fn () => $withTrashed && method_exists($modelClass, 'withTrashed')
            ? $modelClass::withTrashed()
            : $modelClass::query();

        $hasSyncUuid = $this->tableHasSyncUuid($modelClass);
        if ($hasSyncUuid && $uuidKey !== null && ! empty($payload[$uuidKey])) {
            $id = $baseQuery()->where('sync_uuid', (string) $payload[$uuidKey])->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        foreach ($naturalMatchers as $matcher) {
            if ($matcher === []) {
                continue;
            }

            $query = $baseQuery();
            $valid = true;
            foreach ($matcher as $col => $val) {
                if ($val === null || $val === '') {
                    $valid = false;
                    break;
                }
                $query->where($col, $val);
            }

            if ($valid) {
                $id = $query->value('id');
                if ($id) {
                    return (int) $id;
                }
            }
        }

        if (! empty($payload[$idKey])) {
            $id = $baseQuery()->where('id', (int) $payload[$idKey])->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    protected function resolveProdukId(array $payload): ?int
    {
        return $this->resolveForeignId(
            Produk::class,
            $payload,
            'produk_id',
            'produk_sync_uuid',
            [
                ['slug' => $payload['produk_slug'] ?? null],
                ['barcode' => $payload['produk_barcode'] ?? null],
            ],
            withTrashed: true
        );
    }

    protected function resolveSkuVariantId(array $payload, ?int $produkId = null): ?int
    {
        if (! empty($payload['sku_variant_sync_uuid']) && $this->tableHasSyncUuid(SkuVariant::class)) {
            $id = SkuVariant::where('sync_uuid', (string) $payload['sku_variant_sync_uuid'])->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        if (! empty($payload['sku_variant_sku'])) {
            $query = SkuVariant::where('sku', (string) $payload['sku_variant_sku']);
            if ($produkId) {
                $query->where('produk_id', $produkId);
            }
            $id = $query->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        if (! empty($payload['sku_variant_id'])) {
            $query = SkuVariant::where('id', (int) $payload['sku_variant_id']);
            if ($produkId) {
                $query->where('produk_id', $produkId);
            }
            $id = $query->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    protected function resolvePelangganId(array $payload): ?int
    {
        return $this->resolveForeignId(
            Pelanggan::class,
            $payload,
            'pelanggan_id',
            'pelanggan_sync_uuid',
            [
                function ($query) use ($payload) {
                    $kandidat = NomorTeleponIndonesia::kandidat($payload['pelanggan_telepon'] ?? null);
                    if ($kandidat === []) {
                        return false;
                    }
                    $query->whereIn('telepon', $kandidat);

                    return true;
                },
            ]
        );
    }

    protected function resolveGudangId(array $payload, ?int $cabangId = null): ?int
    {
        if (! empty($payload['gudang_kode'])) {
            $id = Gudang::where('kode', (string) $payload['gudang_kode'])->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        if (! empty($payload['gudang_id'])) {
            $id = Gudang::where('id', (int) $payload['gudang_id'])->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        if ($cabangId) {
            $id = Gudang::where('cabang_id', $cabangId)
                ->orderByDesc('is_default')
                ->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    protected function ensureDefaultCoaActive(string $kode): void
    {
        $defaults = [
            '110-02' => ['Bank / Transfer', 'aset', 'kas', 'debit'],
            '110-04' => ['Kas Kasir / Toko', 'aset', 'kas', 'debit'],
            '120-01' => ['Piutang Dagang', 'aset', 'piutang', 'debit'],
            '130-01' => ['Persediaan Barang Dagang', 'aset', 'persediaan', 'debit'],
            '220-01' => ['PPN Keluaran', 'kewajiban', 'pajak', 'kredit'],
            '410-01' => ['Pendapatan Penjualan POS', 'pendapatan', 'pendapatan_penjualan', 'kredit'],
            '510-02' => ['HPP Penjualan', 'beban', 'hpp', 'debit'],
        ];

        if (! isset($defaults[$kode])) {
            return;
        }

        [$nama, $tipe, $kelompok, $saldo] = $defaults[$kode];

        AkunCOA::firstOrCreate(
            ['kode' => $kode],
            [
                'nama' => $nama,
                'tipe' => $tipe,
                'kelompok' => $kelompok,
                'saldo_normal' => $saldo,
                'is_active' => true,
            ]
        );
    }

    protected function handleProdukUpsert(array $payload): void
    {
        if (! class_exists(Produk::class)) {
            return;
        }

        $produk = $this->resolveTargetModel(
            Produk::class,
            $payload,
            [
                ['slug' => $payload['slug'] ?? null],
                ['barcode' => $payload['barcode'] ?? null],
            ],
            withTrashed: true
        );

        if (! empty($payload['force_deleted'])) {
            $produk?->forceDelete();

            return;
        }

        if ($produk && ! $this->isIncomingNewer($payload['updated_at'] ?? null, $produk->updated_at)) {
            return;
        }

        $kategoriId = $this->resolveForeignId(
            KategoriProduk::class,
            $payload,
            'kategori_id',
            'kategori_sync_uuid',
            [['slug' => $payload['kategori_slug'] ?? null]]
        );
        $brandId = $this->resolveForeignId(
            Brand::class,
            $payload,
            'brand_id',
            'brand_sync_uuid',
            [['nama' => $payload['brand_nama'] ?? null]]
        );
        $kualitasId = $this->resolveForeignId(
            KualitasProduk::class,
            $payload,
            'kualitas_id',
            'kualitas_sync_uuid',
            [['nama' => $payload['kualitas_nama'] ?? null]]
        );

        if (! $produk) {
            $produk = new Produk;
            if (! empty($payload['id']) && empty($payload['sync_uuid']) && ! Produk::withTrashed()->where('id', $payload['id'])->exists()) {
                $produk->id = (int) $payload['id'];
            }
        }

        $nama = (string) ($payload['nama'] ?? $produk->nama ?? 'Produk Sync');
        $slug = (string) ($payload['slug'] ?? $produk->slug ?? Str::slug($nama));
        if ($slug === '') {
            $slug = 'produk-'.Str::random(8);
        }

        $attributes = [
            'nama' => $nama,
            'slug' => $slug,
            'barcode' => $payload['barcode'] ?? $produk->barcode,
            'barcode_alt' => $payload['barcode_alt'] ?? $produk->getAttribute('barcode_alt'),
            'deskripsi' => $payload['deskripsi'] ?? $produk->deskripsi,
            'kategori' => $payload['kategori'] ?? $produk->kategori ?? 'Umum',
            'kategori_id' => $kategoriId ?? $produk->kategori_id,
            'brand_kompatibel' => $payload['brand_kompatibel'] ?? $produk->brand_kompatibel,
            'model_kompatibel' => $payload['model_kompatibel'] ?? $produk->model_kompatibel,
            'kondisi' => $payload['kondisi'] ?? $produk->kondisi ?? 'baru',
            'satuan' => $payload['satuan'] ?? $produk->satuan ?? 'pcs',
            'harga_beli' => $payload['harga_beli'] ?? $produk->harga_beli ?? 0,
            'metode_harga_beli' => $payload['metode_harga_beli'] ?? $produk->metode_harga_beli ?? 'average',
            'harga_jual_retail' => $payload['harga_jual_retail'] ?? $produk->harga_jual_retail ?? 0,
            'margin_persen' => $payload['margin_persen'] ?? $produk->margin_persen,
            'gambar' => $payload['gambar'] ?? $produk->gambar,
            'foto' => $payload['foto'] ?? $produk->foto,
            'meta_title' => $payload['meta_title'] ?? $produk->meta_title,
            'meta_description' => $payload['meta_description'] ?? $produk->meta_description,
            'kompatibilitas_hp' => $payload['kompatibilitas_hp'] ?? $produk->kompatibilitas_hp,
            'is_active' => (bool) ($payload['is_active'] ?? $produk->is_active ?? true),
            'brand_id' => $brandId ?? $produk->brand_id,
            'kualitas_id' => $kualitasId ?? $produk->kualitas_id,
            'supplier_id' => $payload['supplier_id'] ?? $produk->supplier_id,
            'harga_fleksibel' => (bool) ($payload['harga_fleksibel'] ?? $produk->harga_fleksibel ?? false),
            'sn' => (bool) ($payload['sn'] ?? $produk->sn ?? false),
            'abc_class' => $payload['abc_class'] ?? $produk->abc_class ?? 'B',
            'reorder_point' => $payload['reorder_point'] ?? $produk->reorder_point,
            'min_stock' => $payload['min_stock'] ?? $produk->min_stock,
            'max_stock' => $payload['max_stock'] ?? $produk->max_stock,
            'is_ondemand' => (bool) ($payload['is_ondemand'] ?? $produk->is_ondemand ?? false),
            'kena_pajak' => (bool) ($payload['kena_pajak'] ?? $produk->kena_pajak ?? false),
        ];

        $produk->fill($attributes);

        if ($this->tableHasSyncUuid(Produk::class) && ! empty($payload['sync_uuid'])) {
            $produk->setAttribute('sync_uuid', (string) $payload['sync_uuid']);
        }

        if (array_key_exists('deleted_at', $payload)) {
            $produk->deleted_at = ! empty($payload['deleted_at']) ? Carbon::parse((string) $payload['deleted_at']) : null;
        }

        $produk->save();
    }

    protected function handlePelangganUpsert(array $payload): void
    {
        if (! class_exists(Pelanggan::class)) {
            return;
        }

        $pelanggan = $this->resolveTargetModel(
            Pelanggan::class,
            $payload,
            [
                function ($query) use ($payload) {
                    $kandidat = NomorTeleponIndonesia::kandidat($payload['telepon'] ?? null);
                    if ($kandidat === []) {
                        return false;
                    }
                    $query->whereIn('telepon', $kandidat);

                    return true;
                },
                ['username' => $payload['username'] ?? null],
                ['email' => $payload['email'] ?? null],
                ['kode_agen' => $payload['kode_agen'] ?? null],
            ]
        );

        if ($pelanggan && ! $this->isIncomingNewer($payload['updated_at'] ?? null, $pelanggan->updated_at)) {
            return;
        }

        $tierId = $this->resolveForeignId(
            TierMembership::class,
            $payload,
            'tier_membership_id',
            null,
            [['kode' => $payload['tier_membership_kode'] ?? null]]
        );

        if (! $pelanggan) {
            $pelanggan = new Pelanggan;
            if (! empty($payload['id']) && empty($payload['sync_uuid']) && ! Pelanggan::where('id', $payload['id'])->exists()) {
                $pelanggan->id = (int) $payload['id'];
            }
        }

        $pelanggan->fill([
            'nama' => $payload['nama'] ?? $pelanggan->nama ?? 'Pelanggan',
            'username' => $payload['username'] ?? $pelanggan->username,
            'telepon' => ! empty($payload['telepon'])
                ? NomorTeleponIndonesia::standar($payload['telepon'])
                : $pelanggan->telepon,
            'email' => $payload['email'] ?? $pelanggan->email,
            'avatar' => $payload['avatar'] ?? $pelanggan->avatar,
            'alamat' => $payload['alamat'] ?? $pelanggan->alamat,
            'tanggal_lahir' => $payload['tanggal_lahir'] ?? $pelanggan->tanggal_lahir,
            'tier_membership_id' => $tierId ?? $pelanggan->tier_membership_id,
            'is_reseller' => $payload['is_reseller'] ?? $pelanggan->is_reseller ?? false,
            'kode_agen' => $payload['kode_agen'] ?? $pelanggan->kode_agen,
            'referral_kode' => $payload['referral_kode'] ?? $pelanggan->referral_kode,
            'tipe_konsumen' => $payload['tipe_konsumen'] ?? $pelanggan->tipe_konsumen ?? 'retail',
            'mode_reward_mitra' => $payload['mode_reward_mitra'] ?? $pelanggan->mode_reward_mitra,
            'total_belanja_12bulan' => $payload['total_belanja_12bulan'] ?? $pelanggan->total_belanja_12bulan ?? 0,
            'poin_loyalty' => $payload['poin_loyalty'] ?? $pelanggan->poin_loyalty ?? 0,
            'saldo_piutang' => $payload['saldo_piutang'] ?? $pelanggan->saldo_piutang ?? 0,
            'max_piutang' => $payload['max_piutang'] ?? $pelanggan->max_piutang,
            'saldo_deposit' => $payload['saldo_deposit'] ?? $pelanggan->saldo_deposit ?? 0,
            'status' => $payload['status'] ?? $pelanggan->status ?? 'aktif',
            'catatan_approval' => $payload['catatan_approval'] ?? $pelanggan->catatan_approval,
            'approved_at' => ! empty($payload['approved_at']) ? Carbon::parse((string) $payload['approved_at']) : $pelanggan->approved_at,
            'approved_by' => $payload['approved_by'] ?? $pelanggan->approved_by,
        ]);

        if ($this->tableHasSyncUuid(Pelanggan::class) && ! empty($payload['sync_uuid'])) {
            $pelanggan->setAttribute('sync_uuid', (string) $payload['sync_uuid']);
        }

        $pelanggan->save();
    }

    protected function handleKategoriProdukUpsert(array $payload): void
    {
        if (! class_exists(KategoriProduk::class)) {
            return;
        }

        $kategori = $this->resolveTargetModel(
            KategoriProduk::class,
            $payload,
            [
                ['slug' => $payload['slug'] ?? null],
                ['nama' => $payload['nama'] ?? null],
            ]
        );

        if ($kategori && ! $this->isIncomingNewer($payload['updated_at'] ?? null, $kategori->updated_at)) {
            return;
        }

        $parentId = $this->resolveForeignId(
            KategoriProduk::class,
            $payload,
            'parent_id',
            'parent_sync_uuid',
            [['slug' => $payload['parent_slug'] ?? null]]
        );

        if (! $kategori) {
            $kategori = new KategoriProduk;
            if (! empty($payload['id']) && empty($payload['sync_uuid']) && ! KategoriProduk::where('id', $payload['id'])->exists()) {
                $kategori->id = (int) $payload['id'];
            }
        }

        $kategori->fill([
            'parent_id' => $parentId,
            'nama' => $payload['nama'] ?? $kategori->nama ?? 'Kategori',
            'slug' => $payload['slug'] ?? $kategori->slug ?? Str::slug((string) ($payload['nama'] ?? 'kategori')),
            'icon' => $payload['icon'] ?? $kategori->icon,
            'urutan' => $payload['urutan'] ?? $kategori->urutan ?? 0,
            'is_active' => $payload['is_active'] ?? $kategori->is_active ?? true,
            'is_default' => $payload['is_default'] ?? $kategori->is_default ?? false,
        ]);

        if ($this->tableHasSyncUuid(KategoriProduk::class) && ! empty($payload['sync_uuid'])) {
            $kategori->setAttribute('sync_uuid', (string) $payload['sync_uuid']);
        }

        $kategori->save();
    }

    protected function handleBrandUpsert(array $payload): void
    {
        if (! class_exists(Brand::class)) {
            return;
        }

        $brand = $this->resolveTargetModel(Brand::class, $payload, [
            ['nama' => $payload['nama'] ?? null],
        ]);

        if ($brand && ! $this->isIncomingNewer($payload['updated_at'] ?? null, $brand->updated_at)) {
            return;
        }

        if (! $brand) {
            $brand = new Brand;
            if (! empty($payload['id']) && empty($payload['sync_uuid']) && ! Brand::where('id', $payload['id'])->exists()) {
                $brand->id = (int) $payload['id'];
            }
        }

        $brand->fill([
            'nama' => $payload['nama'] ?? $brand->nama,
            'keterangan' => $payload['keterangan'] ?? $brand->keterangan,
            'is_active' => $payload['is_active'] ?? $brand->is_active ?? true,
            'is_default' => $payload['is_default'] ?? $brand->is_default ?? false,
        ]);

        if ($this->tableHasSyncUuid(Brand::class) && ! empty($payload['sync_uuid'])) {
            $brand->setAttribute('sync_uuid', (string) $payload['sync_uuid']);
        }

        $brand->save();
    }

    protected function handleKualitasProdukUpsert(array $payload): void
    {
        if (! class_exists(KualitasProduk::class)) {
            return;
        }

        $kualitas = $this->resolveTargetModel(KualitasProduk::class, $payload, [
            ['nama' => $payload['nama'] ?? null],
        ]);

        if ($kualitas && ! $this->isIncomingNewer($payload['updated_at'] ?? null, $kualitas->updated_at)) {
            return;
        }

        $brandId = $this->resolveForeignId(
            Brand::class,
            $payload,
            'brand_id',
            'brand_sync_uuid',
            [['nama' => $payload['brand_nama'] ?? null]]
        );

        if (! $kualitas) {
            $kualitas = new KualitasProduk;
            if (! empty($payload['id']) && empty($payload['sync_uuid']) && ! KualitasProduk::where('id', $payload['id'])->exists()) {
                $kualitas->id = (int) $payload['id'];
            }
        }

        $kualitas->fill([
            'nama' => $payload['nama'] ?? $kualitas->nama,
            'keterangan' => $payload['keterangan'] ?? $kualitas->keterangan,
            'brand_id' => $brandId,
            'is_active' => $payload['is_active'] ?? $kualitas->is_active ?? true,
            'is_default' => $payload['is_default'] ?? $kualitas->is_default ?? false,
        ]);

        if ($this->tableHasSyncUuid(KualitasProduk::class) && ! empty($payload['sync_uuid'])) {
            $kualitas->setAttribute('sync_uuid', (string) $payload['sync_uuid']);
        }

        $kualitas->save();
    }

    protected function handleSatuanUnitUpsert(array $payload): void
    {
        if (! class_exists(SatuanUnit::class)) {
            return;
        }

        $satuan = $this->resolveTargetModel(SatuanUnit::class, $payload, [
            ['kode' => $payload['kode'] ?? null],
            ['nama' => $payload['nama'] ?? null],
        ]);

        if ($satuan && ! $this->isIncomingNewer($payload['updated_at'] ?? null, $satuan->updated_at)) {
            return;
        }

        if (! $satuan) {
            $satuan = new SatuanUnit;
            if (! empty($payload['id']) && empty($payload['sync_uuid']) && ! SatuanUnit::where('id', $payload['id'])->exists()) {
                $satuan->id = (int) $payload['id'];
            }
        }

        $satuan->fill([
            'kode' => $payload['kode'] ?? $satuan->kode,
            'nama' => $payload['nama'] ?? $satuan->nama,
            'is_active' => $payload['is_active'] ?? $satuan->is_active ?? true,
            'is_default' => $payload['is_default'] ?? $satuan->is_default ?? false,
        ]);

        if ($this->tableHasSyncUuid(SatuanUnit::class) && ! empty($payload['sync_uuid'])) {
            $satuan->setAttribute('sync_uuid', (string) $payload['sync_uuid']);
        }

        $satuan->save();
    }

    protected function handleTipeHpUpsert(array $payload): void
    {
        if (! class_exists(TipeHp::class)) {
            return;
        }

        $tipeHp = $this->resolveTargetModel(TipeHp::class, $payload, [
            [
                'merk' => $payload['merk'] ?? null,
                'model' => $payload['model'] ?? null,
            ],
        ]);

        if ($tipeHp && ! $this->isIncomingNewer($payload['updated_at'] ?? null, $tipeHp->updated_at)) {
            return;
        }

        if (! $tipeHp) {
            $tipeHp = new TipeHp;
            if (! empty($payload['id']) && empty($payload['sync_uuid']) && ! TipeHp::where('id', $payload['id'])->exists()) {
                $tipeHp->id = (int) $payload['id'];
            }
        }

        $tipeHp->fill([
            'merk' => $payload['merk'] ?? $tipeHp->merk,
            'model' => $payload['model'] ?? $tipeHp->model,
            'kode_produk' => $payload['kode_produk'] ?? $tipeHp->kode_produk,
            'nama' => $payload['nama'] ?? $tipeHp->nama,
            'is_active' => $payload['is_active'] ?? $tipeHp->is_active ?? true,
            'is_default' => $payload['is_default'] ?? $tipeHp->is_default ?? false,
        ]);

        if ($this->tableHasSyncUuid(TipeHp::class) && ! empty($payload['sync_uuid'])) {
            $tipeHp->setAttribute('sync_uuid', (string) $payload['sync_uuid']);
        }

        $tipeHp->save();
    }

    protected function handleSkuVariantUpsert(array $payload): void
    {
        if (! class_exists(SkuVariant::class)) {
            return;
        }

        $produkId = $this->resolveProdukId($payload);
        $variant = $this->resolveTargetModel(SkuVariant::class, $payload, [
            ['sku' => $payload['sku'] ?? null],
            ['barcode' => $payload['barcode'] ?? null],
        ]);

        if ($variant && ! $this->isIncomingNewer($payload['updated_at'] ?? null, $variant->updated_at)) {
            return;
        }

        $resolvedProdukId = $produkId ?? $variant?->produk_id;
        if (! $resolvedProdukId) {
            throw new \RuntimeException('Produk induk SKU Variant tidak ditemukan di node penerima.');
        }

        if (! $variant) {
            $variant = new SkuVariant;
            if (! empty($payload['id']) && empty($payload['sync_uuid']) && ! SkuVariant::where('id', $payload['id'])->exists()) {
                $variant->id = (int) $payload['id'];
            }
        }

        $variant->fill([
            'produk_id' => $resolvedProdukId,
            'sku' => $payload['sku'] ?? $variant->sku,
            'barcode' => $payload['barcode'] ?? $variant->barcode,
            'nama_varian' => $payload['nama_varian'] ?? $variant->nama_varian,
            'satuan_kode' => $payload['satuan_kode'] ?? $variant->satuan_kode,
            'atribut' => $payload['atribut'] ?? $variant->atribut,
            'harga_beli' => $payload['harga_beli'] ?? $variant->harga_beli,
            'harga_jual_retail' => $payload['harga_jual_retail'] ?? $variant->harga_jual_retail,
            'is_active' => $payload['is_active'] ?? $variant->is_active ?? true,
        ]);

        if ($this->tableHasSyncUuid(SkuVariant::class) && ! empty($payload['sync_uuid'])) {
            $variant->setAttribute('sync_uuid', (string) $payload['sync_uuid']);
        }

        $variant->save();
    }

    protected function handleHargaTierUpsert(array $payload): void
    {
        if (! class_exists(HargaTier::class)) {
            return;
        }

        $produkId = $this->resolveProdukId($payload);
        $skuVariantId = $this->resolveSkuVariantId($payload, $produkId);
        $tierId = $this->resolveForeignId(
            TierMembership::class,
            $payload,
            'tier_membership_id',
            null,
            [['kode' => $payload['tier_membership_kode'] ?? null]]
        );
        $tipeKonsumen = $payload['tipe_konsumen'] ?? null;
        $isReseller = (bool) ($payload['is_reseller'] ?? false);

        $tier = $this->resolveTargetModel(HargaTier::class, $payload, [
            function ($query) use ($produkId, $skuVariantId, $tierId, $tipeKonsumen, $isReseller): bool {
                if (! $produkId) {
                    return false;
                }
                $query->where('produk_id', $produkId)
                    ->where('is_reseller', $isReseller);

                $skuVariantId !== null
                    ? $query->where('sku_variant_id', $skuVariantId)
                    : $query->whereNull('sku_variant_id');

                $tierId !== null
                    ? $query->where('tier_membership_id', $tierId)
                    : $query->whereNull('tier_membership_id');

                $tipeKonsumen !== null
                    ? $query->where('tipe_konsumen', $tipeKonsumen)
                    : $query->whereNull('tipe_konsumen');

                return true;
            },
        ]);

        if (! empty($payload['deleted'])) {
            $tier?->delete();

            return;
        }

        $resolvedProdukId = $produkId ?? $tier?->produk_id;
        if (! $resolvedProdukId) {
            throw new \RuntimeException('Produk induk HargaTier tidak ditemukan.');
        }

        if (! $tier) {
            $tier = new HargaTier;
        }

        $tier->fill([
            'produk_id' => $resolvedProdukId,
            'sku_variant_id' => $skuVariantId,
            'tier_membership_id' => $tierId,
            'is_reseller' => $isReseller,
            'tipe_konsumen' => $tipeKonsumen,
            'harga' => (float) ($payload['harga'] ?? $payload['nominal_tetap'] ?? 0),
            'persen_diskon' => $payload['persen_diskon'] ?? null,
            'nominal_tetap' => $payload['nominal_tetap'] ?? null,
        ]);

        if ($this->tableHasSyncUuid(HargaTier::class) && ! empty($payload['sync_uuid'])) {
            $tier->setAttribute('sync_uuid', (string) $payload['sync_uuid']);
        }

        $tier->save();
    }

    protected function handleProdukSatuanKonversiUpsert(array $payload): void
    {
        if (! class_exists(ProdukSatuanKonversi::class)) {
            return;
        }

        $produkId = $this->resolveProdukId($payload);
        $satuanKode = $payload['satuan_kode'] ?? null;

        $konversi = $this->resolveTargetModel(ProdukSatuanKonversi::class, $payload, [
            $produkId && $satuanKode ? ['produk_id' => $produkId, 'satuan_kode' => $satuanKode] : [],
        ]);

        if (! empty($payload['deleted'])) {
            $konversi?->delete();

            return;
        }

        $resolvedProdukId = $produkId ?? $konversi?->produk_id;
        if (! $resolvedProdukId || empty($satuanKode)) {
            throw new \RuntimeException('Produk atau satuan_kode untuk ProdukSatuanKonversi tidak lengkap.');
        }

        if (! $konversi) {
            $konversi = new ProdukSatuanKonversi;
        }

        $konversi->fill([
            'produk_id' => $resolvedProdukId,
            'satuan_kode' => $satuanKode,
            'satuan_asal' => $payload['satuan_asal'] ?? 'pcs',
            'pengali' => $payload['pengali'] ?? 1,
            'faktor_konversi' => $payload['faktor_konversi'] ?? 1,
            'harga_beli' => $payload['harga_beli'] ?? null,
            'harga_jual' => $payload['harga_jual'] ?? null,
            'barcode' => $payload['barcode'] ?? null,
            'is_satuan_beli' => (bool) ($payload['is_satuan_beli'] ?? false),
            'is_satuan_jual' => (bool) ($payload['is_satuan_jual'] ?? false),
            'urutan' => (int) ($payload['urutan'] ?? 1),
        ]);

        if ($this->tableHasSyncUuid(ProdukSatuanKonversi::class) && ! empty($payload['sync_uuid'])) {
            $konversi->setAttribute('sync_uuid', (string) $payload['sync_uuid']);
        }

        $konversi->save();
    }

    protected function handleProdukBarcodeSync(array $payload): void
    {
        if (! class_exists(ProdukBarcode::class)) {
            return;
        }

        $produkId = $this->resolveProdukId($payload);
        $barcode = trim((string) ($payload['barcode'] ?? ''));

        if (! $produkId || $barcode === '') {
            if (! empty($payload['deleted'])) {
                return;
            }
            throw new \RuntimeException('Produk induk atau barcode untuk ProdukBarcode tidak lengkap.');
        }

        if (! empty($payload['deleted'])) {
            ProdukBarcode::where('produk_id', $produkId)
                ->where('barcode', $barcode)
                ->delete();

            return;
        }

        ProdukBarcode::updateOrCreate(
            [
                'produk_id' => $produkId,
                'barcode' => $barcode,
            ],
            [
                'sku' => $payload['sku'] ?? null,
                'label' => $payload['label'] ?? 'barcode_1',
                'urutan' => (int) ($payload['urutan'] ?? 1),
                'is_utama' => (bool) ($payload['is_utama'] ?? false),
            ]
        );
    }

    protected function handleStokMutasi(array $payload): void
    {
        if (! class_exists(StokItem::class)) {
            Log::warning('[HybridSync] Model StokItem tidak tersedia.');

            return;
        }

        $produkId = $this->resolveProdukId($payload);

        // Kompatibilitas mundur payload legacy stok.mutasi berbasis produk_id angka tanpa sync_uuid/slug.
        if (! $produkId && ! empty($payload['produk_id']) && empty($payload['produk_sync_uuid']) && empty($payload['produk_slug'])) {
            $legacyProdukId = (int) $payload['produk_id'];
            DB::table('produk')->insertOrIgnore([
                'id' => $legacyProdukId,
                'nama' => 'Produk '.$legacyProdukId,
                'slug' => 'produk-'.$legacyProdukId.'-'.uniqid(),
                'harga_jual_retail' => 0,
                'harga_beli' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $produkId = $legacyProdukId;
        }

        $skuVariantId = $this->resolveSkuVariantId($payload, $produkId);
        $cabangId = isset($payload['cabang_id']) ? (int) $payload['cabang_id'] : null;
        $gudangId = $this->resolveGudangId($payload, $cabangId);
        if (! $gudangId && class_exists(Gudang::class)) {
            $gudangId = Gudang::getDefault($cabangId)?->id;
        }

        if (! $produkId || ! $gudangId) {
            throw new \RuntimeException("Produk atau Gudang untuk stok.mutasi tidak ditemukan (produk={$produkId}, gudang={$gudangId}).");
        }

        $jumlahMutasi = isset($payload['perubahan'])
            ? (float) $payload['perubahan']
            : (isset($payload['jumlah']) ? (float) $payload['jumlah'] : null);

        $targetStok = $payload['jumlah_setelah'] ?? $payload['stok_akhir'] ?? $payload['qty'] ?? null;

        $stokItem = StokItem::firstOrCreate(
            [
                'gudang_id' => $gudangId,
                'produk_id' => $produkId,
                'sku_variant_id' => $skuVariantId,
            ],
            [
                'jumlah' => 0,
            ]
        );

        $jumlahSebelum = (int) $stokItem->jumlah;

        if ($jumlahMutasi !== null && $jumlahMutasi != 0.0) {
            $stokItem->increment('jumlah', $jumlahMutasi);
            $stokItem->refresh();
        } elseif ($targetStok !== null) {
            $stokItem->jumlah = max(0, (int) round((float) $targetStok));
            $stokItem->save();
        }

        $jumlahSetelah = (int) $stokItem->jumlah;

        // Catat audit trail StokLog di node penerima (tanpa memicu observer balik karena dalam SyncExecutionGuard)
        if (class_exists(StokLog::class)) {
            $sourceRefId = isset($payload['id']) ? (int) $payload['id'] : null;
            $alreadyLogged = $sourceRefId !== null && StokLog::where('referensi_tipe', 'sync_inbox')
                ->where('referensi_id', $sourceRefId)
                ->where('gudang_id', $gudangId)
                ->where('produk_id', $produkId)
                ->exists();

            if (! $alreadyLogged) {
                $perubahanLog = $jumlahMutasi !== null
                    ? (int) round($jumlahMutasi)
                    : ($jumlahSetelah - $jumlahSebelum);

                StokLog::create([
                    'gudang_id' => $gudangId,
                    'produk_id' => $produkId,
                    'sku_variant_id' => $skuVariantId,
                    'user_id' => isset($payload['user_id']) && User::where('id', (int) $payload['user_id'])->exists()
                        ? (int) $payload['user_id']
                        : null,
                    'jenis' => $payload['jenis'] ?? ($perubahanLog >= 0 ? 'masuk' : 'keluar'),
                    'referensi_tipe' => 'sync_inbox',
                    'referensi_id' => $sourceRefId,
                    'jumlah_sebelum' => $jumlahSebelum,
                    'perubahan' => $perubahanLog,
                    'jumlah_setelah' => $jumlahSetelah,
                    'catatan' => trim(($payload['catatan'] ?? 'Mutasi stok tersinkronisasi').' [sync]'),
                ]);
            }
        }
    }

    protected function handleTransaksiPos(array $payload): void
    {
        $this->upsertSyncedPosTransaction($payload, false);
    }

    protected function handleTransaksiPosCloud(array $payload): void
    {
        $this->upsertSyncedPosTransaction($payload, true);
    }

    protected function simpanRingkasanTransaksiPos(array $payload): void
    {
        if (! Schema::hasTable('hybrid_sync_pos_transaksi')) {
            return;
        }

        $noTransaksi = $payload['no_transaksi'] ?? $payload['nomor_transaksi'] ?? ('POS-'.Str::random(8));
        $cabangId = $payload['cabang_id'] ?? 1;

        $existing = DB::table('hybrid_sync_pos_transaksi')
            ->where('no_transaksi', $noTransaksi)
            ->first();

        $record = [
            'cabang_id' => $cabangId,
            'no_transaksi' => $noTransaksi,
            'sumber' => $payload['sumber'] ?? 'pos',
            'kasir_nama' => $payload['kasir_nama'] ?? 'Kasir POS',
            'pelanggan_nama' => $payload['pelanggan_nama'] ?? 'Pelanggan Umum',
            'total' => $payload['total_akhir'] ?? $payload['total'] ?? 0,
            'metode_bayar' => $payload['metode_bayar'] ?? 'tunai',
            'status' => $payload['status'] ?? 'selesai',
            'jumlah_item' => $payload['jumlah_item'] ?? count($payload['items'] ?? []),
            'source_node' => $payload['source_node'] ?? config('sync.node_id', 'STORE-01'),
            'transaksi_created_at' => ! empty($payload['transaksi_created_at'])
                ? Carbon::parse((string) $payload['transaksi_created_at'])
                : (! empty($payload['created_at']) ? Carbon::parse((string) $payload['created_at']) : now()),
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('hybrid_sync_pos_transaksi')
                ->where('id', $existing->id)
                ->update($record);
        } else {
            $record['created_at'] = now();
            DB::table('hybrid_sync_pos_transaksi')->insert($record);
        }
    }

    /**
     * Unified POS & Cloud Failover transaction processor (rekap + items + stok + piutang + jurnal penuh).
     * Menggantikan duplikasi rekapTransaksiPosCloud() dan handleTransaksiPosCloud().
     */
    protected function upsertSyncedPosTransaction(array $payload, bool $deductPhysicalStock): void
    {
        $this->simpanRingkasanTransaksiPos($payload);

        $noTransaksi = $payload['no_transaksi'] ?? $payload['nomor_transaksi'] ?? null;
        if (empty($noTransaksi)) {
            return;
        }

        $offlineUuid = ! empty($payload['offline_uuid']) ? (string) $payload['offline_uuid'] : null;
        $status = strtolower((string) ($payload['status'] ?? 'selesai'));
        $sumber = strtolower((string) ($payload['sumber'] ?? ($deductPhysicalStock ? 'pos_cloud' : 'pos')));

        // Safety: abaikan transaksi draft/hold/batal agar tidak membentuk piutang atau jurnal premature.
        if (in_array($status, ['ditahan', 'dibatalkan', 'menunggu_pembayaran', 'gagal', 'kadaluarsa'], true)) {
            return;
        }

        $existingQuery = Transaksi::query();
        if ($offlineUuid !== null) {
            $existingQuery->where(function ($q) use ($offlineUuid, $noTransaksi): void {
                $q->where('offline_uuid', $offlineUuid)
                    ->orWhere('no_transaksi', $noTransaksi);
            });
        } else {
            $existingQuery->where('no_transaksi', $noTransaksi);
        }

        $existingTx = $existingQuery->first();

        $cabangId = isset($payload['cabang_id']) ? (int) $payload['cabang_id'] : (int) ($existingTx?->cabang_id ?? 1);
        $gudangId = $this->resolveGudangId($payload, $cabangId) ?? $existingTx?->gudang_id;
        $pelangganId = $this->resolvePelangganId($payload) ?? $existingTx?->pelanggan_id;
        $kasirId = ! empty($payload['kasir_email'])
            ? (User::where('email', (string) $payload['kasir_email'])->value('id') ?? ($payload['kasir_id'] ?? null))
            : ($payload['kasir_id'] ?? $existingTx?->kasir_id);

        if ($kasirId && ! User::where('id', $kasirId)->exists()) {
            $kasirId = null;
        }

        $tiketServisId = $this->resolveForeignId(
            TiketServis::class,
            $payload,
            'tiket_servis_id',
            null,
            [['no_tiket' => $payload['tiket_servis_no_tiket'] ?? null]]
        ) ?? $existingTx?->tiket_servis_id;

        $totalAkhir = round((float) ($payload['total_akhir'] ?? $payload['total'] ?? 0), 2);
        $ppnNominal = round((float) ($payload['ppn_nominal'] ?? $payload['pajak_nominal'] ?? 0), 2);
        $dpp = isset($payload['dpp'])
            ? round((float) $payload['dpp'], 2)
            : round(max(0.0, $totalAkhir - $ppnNominal), 2);

        $metodeBayar = strtolower((string) ($payload['metode_bayar'] ?? 'tunai'));
        $jumlahBayar = (float) ($payload['jumlah_bayar'] ?? ($metodeBayar === 'piutang' ? 0 : $totalAkhir));
        $kembalian = (float) ($payload['kembalian'] ?? max(0.0, $jumlahBayar - $totalAkhir));

        $txData = [
            'no_transaksi' => $noTransaksi,
            'offline_uuid' => $offlineUuid ?? ($existingTx?->offline_uuid ?: (string) Str::uuid()),
            'cabang_id' => $cabangId,
            'kasir_id' => $kasirId,
            'pelanggan_id' => $pelangganId,
            'gudang_id' => $gudangId,
            'tiket_servis_id' => $tiketServisId,
            'sumber' => $sumber,
            'subtotal' => (float) ($payload['subtotal'] ?? $totalAkhir),
            'diskon_persen' => (float) ($payload['diskon_persen'] ?? 0),
            'diskon_nominal' => (float) ($payload['diskon_nominal'] ?? 0),
            'diskon_poin' => (float) ($payload['diskon_poin'] ?? 0),
            'poin_ditukar' => (int) ($payload['poin_ditukar'] ?? 0),
            'diskon_partai' => (float) ($payload['diskon_partai'] ?? 0),
            'mode_reward_mitra' => $payload['mode_reward_mitra'] ?? null,
            'komisi_mitra_nominal' => (float) ($payload['komisi_mitra_nominal'] ?? 0),
            'dpp' => $dpp,
            'pajak_nominal' => $ppnNominal,
            'ppn_nominal' => $ppnNominal,
            'total_akhir' => $totalAkhir,
            'metode_bayar' => $metodeBayar,
            'jumlah_bayar' => $jumlahBayar,
            'kembalian' => $kembalian,
            'split_detail' => $payload['split_detail'] ?? null,
            'status' => $status,
            'catatan' => $payload['catatan'] ?? null,
            'durasi_garansi_hari' => (int) ($payload['durasi_garansi_hari'] ?? 0),
            'garansi_teks' => $payload['garansi_teks'] ?? null,
            'garansi_berakhir_pada' => ! empty($payload['garansi_berakhir_pada'])
                ? Carbon::parse((string) $payload['garansi_berakhir_pada'])->toDateString()
                : null,
            'catatan_garansi' => $payload['catatan_garansi'] ?? null,
            'payment_reference' => $payload['payment_reference'] ?? null,
            'paid_at' => ! empty($payload['paid_at']) ? Carbon::parse((string) $payload['paid_at']) : null,
        ];

        $isNew = ! $existingTx;
        $transaksi = $existingTx ?? new Transaksi;
        $transaksi->fill($txData);
        $transaksi->save();

        $totalHpp = 0.0;
        $needItems = $isNew || $transaksi->items()->count() === 0;
        $itemsPayload = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $stokService = app(StokDeductionService::class);

        if ($needItems) {
            foreach ($itemsPayload as $item) {
                $produkId = $this->resolveProdukId($item);
                if (! $produkId) {
                    if ($deductPhysicalStock) {
                        throw new \RuntimeException('Produk pada transaksi cloud POS tidak ditemukan di toko.');
                    }

                    continue;
                }

                $produk = Produk::withTrashed()->find($produkId);
                $skuVariantId = $this->resolveSkuVariantId($item, $produkId);
                $skuVariant = $skuVariantId ? SkuVariant::find($skuVariantId) : null;

                $jumlah = max(1, (int) ($item['jumlah'] ?? 1));
                $konversi = max(0.0001, (float) ($item['konversi'] ?? 1));
                $qtyDasar = max(1, (int) round($jumlah * $konversi));

                $itemHpp = (float) ($item['hpp'] ?? 0);
                if ($itemHpp <= 0) {
                    $itemHpp = (float) ($skuVariant?->harga_beli ?? $produk?->harga_beli ?? 0);
                }

                $hargaSatuan = (float) ($item['harga_satuan'] ?? $item['harga'] ?? $produk?->harga_jual_retail ?? 0);
                $diskonItem = (float) ($item['diskon_nominal'] ?? 0);
                $subtotalItem = (float) ($item['subtotal'] ?? (($hargaSatuan * $jumlah) - $diskonItem));

                TransaksiItem::create([
                    'transaksi_id' => $transaksi->id,
                    'produk_id' => $produkId,
                    'sku_variant_id' => $skuVariantId,
                    'jumlah' => $jumlah,
                    'satuan' => $item['satuan'] ?? ($produk?->satuan ?? 'pcs'),
                    'konversi' => $konversi,
                    'harga_satuan' => $hargaSatuan,
                    'diskon_nominal' => $diskonItem,
                    'subtotal' => $subtotalItem,
                    'harga_final' => $item['harga_final'] ?? null,
                    'hpp' => $itemHpp,
                    'catatan' => $item['catatan'] ?? null,
                ]);

                $totalHpp += ($itemHpp * $jumlah);

                // Hanya transaksi.pos_cloud yang memotong stok fisik saat ditarik ke node utama;
                // transaksi.pos dari toko sudah menyinkronkan mutasi stok via stok.mutasi.
                if ($deductPhysicalStock && $sumber !== 'servis') {
                    if (! $gudangId) {
                        throw new \RuntimeException("Gudang cabang #{$cabangId} tidak ditemukan untuk potong stok Cloud POS.");
                    }

                    $stokService->kurangi(
                        produkId: $produkId,
                        skuVariantId: $skuVariantId,
                        gudangId: $gudangId,
                        qty: $qtyDasar,
                        jenis: 'keluar',
                        referensiTipe: Transaksi::class,
                        referensiId: $transaksi->id,
                        userId: $kasirId,
                        catatan: "Cloud POS Failover {$noTransaksi}",
                        izinkanNegatif: true
                    );
                }
            }
        } else {
            $totalHpp = (float) $transaksi->items->sum(fn ($item) => (float) $item->hpp * (int) $item->jumlah);
        }

        // Hindari poin ganda: observer selalu mengirim poin_handled_at_source = true.
        if (empty($payload['poin_handled_at_source']) && $pelangganId && $sumber !== 'servis') {
            $pelanggan = Pelanggan::find($pelangganId);
            if ($pelanggan && $totalAkhir > 0) {
                $earnedPoin = (int) floor($totalAkhir / 10000);
                if ($earnedPoin > 0) {
                    $pelanggan->increment('poin_loyalty', $earnedPoin);
                }
            }
        }

        // Piutang (Kasbon) bila metode bayar piutang/kasbon
        if (in_array($metodeBayar, ['piutang', 'kasbon'], true)
            && $pelangganId
            && $totalAkhir > 0
            && class_exists(Piutang::class)
            && ! Piutang::where('referensi_tipe', Transaksi::class)->where('referensi_id', $transaksi->id)->exists()
        ) {
            $countPiutang = Piutang::where('cabang_id', $cabangId)
                ->whereDate('created_at', now()->toDateString())
                ->count() + 1;

            Piutang::create([
                'no_piutang' => sprintf('AR-%s-%04d', now()->format('Ymd'), $countPiutang),
                'pelanggan_id' => $pelangganId,
                'transaksi_id' => $transaksi->id,
                'referensi_tipe' => Transaksi::class,
                'referensi_id' => $transaksi->id,
                'cabang_id' => $cabangId,
                'jumlah' => $totalAkhir,
                'jumlah_dibayar' => 0,
                'sisa' => $totalAkhir,
                'jatuh_tempo' => now()->addDays(30)->toDateString(),
                'status' => 'belum_lunas',
                'keterangan' => "Kasbon {$noTransaksi} (Sync)",
            ]);

            Pelanggan::where('id', $pelangganId)->increment('saldo_piutang', $totalAkhir);
        }

        // Pembayaran servis sudah dijurnal oleh ServisService::bayar() (servis-bayar); jangan posting penjualan POS ganda.
        if ($sumber === 'servis' || $totalAkhir <= 0 || ! class_exists(JurnalService::class)) {
            return;
        }

        $alreadyJournaled = JurnalHeader::where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $transaksi->id)
            ->exists();

        if ($alreadyJournaled) {
            return;
        }

        $jurnalService = app(JurnalService::class);
        $noJurnal = $jurnalService->generateNoJurnal('pos', $cabangId);
        $debitLines = $this->buildPosDebitLines($metodeBayar, $totalAkhir, $payload['split_detail'] ?? null);
        foreach ($debitLines as $dLine) {
            $this->ensureDefaultCoaActive((string) $dLine['akun_kode']);
        }
        $this->ensureDefaultCoaActive('410-01');

        $kreditPenjualan = $ppnNominal > 0 ? $dpp : $totalAkhir;
        $lines = array_merge($debitLines, [
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $kreditPenjualan],
        ]);

        if ($ppnNominal > 0) {
            $this->ensureDefaultCoaActive('220-01');
            $lines = array_merge($lines, app(PajakService::class)->jurnalLines($ppnNominal, $noJurnal, $cabangId, $kasirId ?? 0));
        }

        if ($totalHpp > 0) {
            $this->ensureDefaultCoaActive('510-02');
            $this->ensureDefaultCoaActive('130-01');
            $lines[] = ['akun_kode' => '510-02', 'debit' => round($totalHpp, 2), 'kredit' => 0];
            $lines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => round($totalHpp, 2)];
        }

        $jurnalService->post(
            noJurnal: $noJurnal,
            tanggal: ! empty($payload['paid_at']) ? Carbon::parse((string) $payload['paid_at']) : now(),
            sumber: 'pos',
            lines: $lines,
            deskripsi: "Transaksi {$noTransaksi} (Sync)",
            cabangId: $cabangId,
            userId: $kasirId,
            referensiTipe: Transaksi::class,
            referensiId: $transaksi->id,
            idempotencyKey: "sync-pos-{$transaksi->id}"
        );
    }

    /**
     * @return array<int, array{akun_kode: string, debit: float, kredit: float}>
     */
    protected function buildPosDebitLines(string $metodeBayar, float $totalAkhir, mixed $splitDetail): array
    {
        if (in_array($metodeBayar, ['piutang', 'kasbon'], true)) {
            return [['akun_kode' => '120-01', 'debit' => $totalAkhir, 'kredit' => 0.0]];
        }

        if ($metodeBayar === 'split' && is_array($splitDetail) && $splitDetail !== []) {
            // Format baru dari PosKasir: {tunai: N, non_tunai: N, metode_non_tunai: 'transfer'}
            // Format lama dari PosController free-form: {channel => nominal, ...}
            if (array_key_exists('tunai', $splitDetail) || array_key_exists('non_tunai', $splitDetail)) {
                $porsiTunai = max(0.0, (float) ($splitDetail['tunai'] ?? 0));
                $porsiNonTunai = max(0.0, (float) ($splitDetail['non_tunai'] ?? 0));
                $debitLines = [];
                if ($porsiTunai > 0.0) {
                    $debitLines[] = ['akun_kode' => '110-04', 'debit' => $porsiTunai, 'kredit' => 0.0];
                }
                if ($porsiNonTunai > 0.0) {
                    $debitLines[] = ['akun_kode' => '110-02', 'debit' => $porsiNonTunai, 'kredit' => 0.0];
                }
                if ($debitLines !== []) {
                    $sisa = round($totalAkhir - $porsiTunai - $porsiNonTunai, 2);
                    if ($sisa > 0.01) {
                        $debitLines[] = ['akun_kode' => '110-04', 'debit' => $sisa, 'kredit' => 0.0];
                    }

                    return $debitLines;
                }
            }

            // Format lama: {channel => nominal, ...} — skip key non-numerik seperti 'metode_non_tunai'
            $debitLines = [];
            $teralokasi = 0.0;

            foreach ($splitDetail as $key => $val) {
                // Skip key yang bukan nominal (misalnya 'metode_non_tunai' berisi string channel)
                if (is_string($val) && ! is_numeric($val)) {
                    continue;
                }
                $nominal = is_array($val) ? (float) ($val['jumlah'] ?? $val['nominal'] ?? 0) : (float) $val;
                $channel = is_array($val) ? strtolower((string) ($val['metode'] ?? $key)) : strtolower((string) $key);
                if (! is_finite($nominal) || $nominal <= 0.0) {
                    continue;
                }

                $akun = in_array($channel, ['transfer', 'qris', 'edc', 'bank'], true) ? '110-02' : '110-04';
                $debitLines[] = ['akun_kode' => $akun, 'debit' => $nominal, 'kredit' => 0.0];
                $teralokasi += $nominal;
            }

            if ($debitLines !== []) {
                $sisa = round($totalAkhir - $teralokasi, 2);
                if ($sisa > 0.01) {
                    $debitLines[] = ['akun_kode' => '110-04', 'debit' => $sisa, 'kredit' => 0.0];
                }

                return $debitLines;
            }
        }

        if (in_array($metodeBayar, ['transfer', 'qris', 'edc', 'bank'], true)) {
            return [['akun_kode' => '110-02', 'debit' => $totalAkhir, 'kredit' => 0.0]];
        }

        return [['akun_kode' => '110-04', 'debit' => $totalAkhir, 'kredit' => 0.0]];
    }

    protected function handleTransaksiVoid(array $payload): void
    {
        $offlineUuid = ! empty($payload['offline_uuid']) ? (string) $payload['offline_uuid'] : null;
        $noTransaksi = $payload['no_transaksi'] ?? null;

        if ($offlineUuid === null && empty($noTransaksi)) {
            return;
        }

        if (Schema::hasTable('hybrid_sync_pos_transaksi') && ! empty($noTransaksi)) {
            DB::table('hybrid_sync_pos_transaksi')
                ->where('no_transaksi', $noTransaksi)
                ->update(['status' => 'void', 'updated_at' => now()]);
        }

        $query = Transaksi::query();
        if ($offlineUuid !== null) {
            $query->where('offline_uuid', $offlineUuid);
            if (! empty($noTransaksi)) {
                $query->orWhere('no_transaksi', $noTransaksi);
            }
        } else {
            $query->where('no_transaksi', $noTransaksi);
        }

        $transaksi = $query->lockForUpdate()->first();
        if (! $transaksi || strtolower((string) $transaksi->status) === 'void') {
            return;
        }

        $alasan = (string) ($payload['void_alasan'] ?? 'Void transaksi (sync)');
        $voidById = ! empty($payload['void_by_id']) && User::where('id', (int) $payload['void_by_id'])->exists()
            ? (int) $payload['void_by_id']
            : $transaksi->kasir_id;

        $transaksi->update([
            'status' => 'void',
            'void_alasan' => $alasan,
            'void_at' => ! empty($payload['void_at']) ? Carbon::parse((string) $payload['void_at']) : now(),
            'void_by_id' => $voidById,
        ]);

        // Kembalikan stok fisik hanya bila berasal dari pos_cloud / VPS failover (node toko sudah memicu stok.mutasi saat void lokal).
        $isCloudFailover = strtolower((string) $transaksi->sumber) === 'pos_cloud'
            || str_starts_with((string) $transaksi->no_transaksi, 'VPS-');

        if ($isCloudFailover && $transaksi->gudang_id && class_exists(StokDeductionService::class)) {
            $stokService = app(StokDeductionService::class);
            $transaksi->loadMissing('items');
            foreach ($transaksi->items as $item) {
                $qtyDasar = max(1, (int) round((int) $item->jumlah * max(0.0001, (float) ($item->konversi ?? 1))));
                $stokService->kembalikan(
                    produkId: (int) $item->produk_id,
                    skuVariantId: $item->sku_variant_id ? (int) $item->sku_variant_id : null,
                    gudangId: (int) $transaksi->gudang_id,
                    qty: $qtyDasar,
                    jenis: 'masuk',
                    referensiTipe: Transaksi::class,
                    referensiId: $transaksi->id,
                    userId: $voidById,
                    catatan: "Void Sync {$transaksi->no_transaksi}: {$alasan}"
                );
            }
        }

        // Batalkan Piutang terbuka terkait transaksi
        if (class_exists(Piutang::class)) {
            $piutangList = Piutang::where('referensi_tipe', Transaksi::class)
                ->where('referensi_id', $transaksi->id)
                ->where('status', '!=', 'batal')
                ->get();

            foreach ($piutangList as $piutang) {
                $sisa = (float) $piutang->sisa;
                $piutang->update([
                    'status' => 'batal',
                    'sisa' => 0,
                    'keterangan' => trim(($piutang->keterangan ?? '')." [Void Sync: {$alasan}]"),
                ]);

                if ($sisa > 0 && $piutang->pelanggan_id) {
                    $pelanggan = Pelanggan::find($piutang->pelanggan_id);
                    if ($pelanggan) {
                        $pelanggan->update([
                            'saldo_piutang' => max(0, (float) $pelanggan->saldo_piutang - $sisa),
                        ]);
                    }
                }
            }
        }

        // Reversal Jurnal terkait transaksi
        if (class_exists(JurnalService::class)) {
            $jurnalService = app(JurnalService::class);
            $headers = JurnalHeader::where('referensi_tipe', Transaksi::class)
                ->where('referensi_id', $transaksi->id)
                ->where('is_reversed', false)
                ->where('sumber', '!=', 'reversal')
                ->get();

            foreach ($headers as $header) {
                $jurnalService->reversalJurnal(
                    $header->no_jurnal,
                    (int) ($header->cabang_id ?? $transaksi->cabang_id ?? 1),
                    $voidById,
                    "Void Sync {$transaksi->no_transaksi}: {$alasan}"
                );
            }
        }
    }

    protected function handleServisUpdate(array $payload): void
    {
        if (! class_exists(TiketServis::class)) {
            return;
        }

        $tiket = $this->resolveTargetModel(TiketServis::class, $payload, [
            ['no_tiket' => $payload['no_tiket'] ?? null],
        ]);

        if ($tiket && ! $this->isIncomingNewer($payload['updated_at'] ?? null, $tiket->updated_at)) {
            return;
        }

        $pelangganId = $this->resolvePelangganId($payload);
        $jenisServisId = $this->resolveForeignId(
            JenisServis::class,
            $payload,
            'jenis_servis_id',
            null,
            [
                ['kode' => $payload['jenis_servis_kode'] ?? null],
                ['nama' => $payload['jenis_servis_nama'] ?? null],
            ]
        );
        $teknisiId = ! empty($payload['teknisi_email'])
            ? (User::where('email', (string) $payload['teknisi_email'])->value('id') ?? ($payload['teknisi_id'] ?? null))
            : ($payload['teknisi_id'] ?? null);

        if ($teknisiId && ! User::where('id', $teknisiId)->exists()) {
            $teknisiId = null;
        }

        if (! $tiket) {
            if (empty($payload['no_tiket'])) {
                return;
            }

            $tiket = new TiketServis;
            if (! empty($payload['id']) && empty($payload['sync_uuid']) && ! TiketServis::where('id', $payload['id'])->exists()) {
                $tiket->id = (int) $payload['id'];
            }
        }

        $tiket->fill([
            'no_tiket' => $payload['no_tiket'] ?? $tiket->no_tiket,
            'cabang_id' => $payload['cabang_id'] ?? $tiket->cabang_id ?? 1,
            'jenis_servis_id' => $jenisServisId ?? $tiket->jenis_servis_id,
            'pelanggan_id' => $pelangganId ?? $tiket->pelanggan_id,
            'teknisi_id' => $teknisiId ?? $tiket->teknisi_id,
            'nama_pelanggan' => $payload['nama_pelanggan'] ?? $tiket->nama_pelanggan,
            'telepon_pelanggan' => $payload['telepon_pelanggan'] ?? $tiket->telepon_pelanggan,
            'jenis_hp' => $payload['jenis_hp'] ?? $tiket->jenis_hp,
            'seri_hp' => $payload['seri_hp'] ?? $tiket->seri_hp,
            'keluhan' => $payload['keluhan'] ?? $tiket->keluhan,
            'kondisi_fisik' => $payload['kondisi_fisik'] ?? $tiket->kondisi_fisik,
            'status' => $payload['status'] ?? $tiket->status ?? 'diterima',
            'sumber' => $payload['sumber'] ?? $tiket->sumber ?? 'walkin',
            'estimasi_biaya' => $payload['estimasi_biaya'] ?? $tiket->estimasi_biaya,
            'dpp' => $payload['dpp'] ?? $tiket->dpp,
            'ppn_nominal' => (float) ($payload['ppn_nominal'] ?? $tiket->ppn_nominal ?? 0),
            'ppn_percent' => (float) ($payload['ppn_percent'] ?? $tiket->ppn_percent ?? 0),
            'alasan_estimasi' => $payload['alasan_estimasi'] ?? $tiket->alasan_estimasi,
            'batas_waktu_menit' => $payload['batas_waktu_menit'] ?? $tiket->batas_waktu_menit,
            'durasi_garansi_custom' => $payload['durasi_garansi_custom'] ?? $tiket->durasi_garansi_custom,
            'auto_cancel_overdue' => (bool) ($payload['auto_cancel_overdue'] ?? $tiket->auto_cancel_overdue ?? false),
            'token_approval' => $payload['token_approval'] ?? $tiket->token_approval,
            'tanggal_terima' => ! empty($payload['tanggal_terima']) ? Carbon::parse((string) $payload['tanggal_terima']) : $tiket->tanggal_terima,
            'tanggal_selesai' => ! empty($payload['tanggal_selesai']) ? Carbon::parse((string) $payload['tanggal_selesai']) : $tiket->tanggal_selesai,
            'tanggal_diambil' => ! empty($payload['tanggal_diambil']) ? Carbon::parse((string) $payload['tanggal_diambil']) : $tiket->tanggal_diambil,
            'catatan_admin' => $payload['catatan_admin'] ?? $payload['catatan_teknisi'] ?? $payload['catatan'] ?? $tiket->catatan_admin,
            'status_pembayaran' => $payload['status_pembayaran'] ?? $tiket->status_pembayaran ?? 'belum_bayar',
            'tanggal_bayar' => ! empty($payload['tanggal_bayar']) ? Carbon::parse((string) $payload['tanggal_bayar']) : $tiket->tanggal_bayar,
            'metode_pembayaran' => $payload['metode_pembayaran'] ?? $tiket->metode_pembayaran,
            'no_jurnal_bayar' => $payload['no_jurnal_bayar'] ?? $tiket->no_jurnal_bayar,
            'offline_pickup_uuid' => $payload['offline_pickup_uuid'] ?? $tiket->offline_pickup_uuid,
            'is_offline_pickup' => $payload['is_offline_pickup'] ?? $tiket->is_offline_pickup ?? false,
            'pickup_synced_at' => ! empty($payload['pickup_synced_at']) ? Carbon::parse((string) $payload['pickup_synced_at']) : $tiket->pickup_synced_at,
            'garansi_berlaku' => $payload['garansi_berlaku'] ?? $tiket->garansi_berlaku ?? false,
            'komplain_catatan' => $payload['komplain_catatan'] ?? $tiket->komplain_catatan,
            'komplain_gratis' => $payload['komplain_gratis'] ?? $tiket->komplain_gratis ?? false,
            'goodwill_amount' => $payload['goodwill_amount'] ?? $tiket->goodwill_amount,
            'goodwill_alasan' => $payload['goodwill_alasan'] ?? $tiket->goodwill_alasan,
        ]);

        if ($this->tableHasSyncUuid(TiketServis::class) && ! empty($payload['sync_uuid'])) {
            $tiket->setAttribute('sync_uuid', (string) $payload['sync_uuid']);
        }

        $tiket->save();
    }

    protected function handleJurnalUpsert(array $payload): void
    {
        if (! class_exists(JurnalHeader::class) || ! class_exists(JurnalService::class)) {
            return;
        }

        $noJurnal = (string) ($payload['no_jurnal'] ?? '');
        $cabangId = isset($payload['cabang_id']) ? (int) $payload['cabang_id'] : null;
        $cabangKey = (int) ($payload['cabang_key'] ?? $cabangId ?? 0);

        if ($noJurnal === '') {
            return;
        }

        $existing = $this->resolveTargetModel(JurnalHeader::class, $payload, [
            ['no_jurnal' => $noJurnal, 'cabang_key' => $cabangKey],
        ]);

        if ($existing) {
            DB::table('jurnal_header')
                ->where('id', $existing->id)
                ->update([
                    'status_approval' => $payload['status_approval'] ?? $existing->status_approval,
                    'is_reversed' => (bool) ($payload['is_reversed'] ?? $existing->is_reversed),
                    'reversed_by_no_jurnal' => $payload['reversed_by_no_jurnal'] ?? $existing->reversed_by_no_jurnal,
                    'reversal_of_no_jurnal' => $payload['reversal_of_no_jurnal'] ?? $existing->reversal_of_no_jurnal,
                    'reversed_at' => ! empty($payload['reversed_at']) ? Carbon::parse((string) $payload['reversed_at']) : $existing->reversed_at,
                    'approved_at' => ! empty($payload['approved_at']) ? Carbon::parse((string) $payload['approved_at']) : $existing->approved_at,
                    'catatan_approval' => $payload['catatan_approval'] ?? $existing->catatan_approval,
                    'sync_uuid' => $payload['sync_uuid'] ?? $existing->getAttribute('sync_uuid'),
                    'updated_at' => now(),
                ]);

            return;
        }

        $rawLines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        if ($rawLines === []) {
            throw new \RuntimeException("Payload jurnal {$noJurnal} tidak memiliki baris akuntansi.");
        }

        $lines = [];
        foreach ($rawLines as $line) {
            $kode = (string) ($line['akun_kode'] ?? '');
            if ($kode === '') {
                continue;
            }

            if (! AkunCOA::where('kode', $kode)->exists() && ! empty($line['akun_nama'])) {
                $prefix = substr($kode, 0, 1);
                [$tipe, $saldo] = match ($prefix) {
                    '1' => ['aset', 'debit'],
                    '2' => ['kewajiban', 'kredit'],
                    '3' => ['modal', 'kredit'],
                    '4' => ['pendapatan', 'kredit'],
                    default => ['beban', 'debit'],
                };
                AkunCOA::firstOrCreate(
                    ['kode' => $kode],
                    [
                        'nama' => (string) $line['akun_nama'],
                        'tipe' => $tipe,
                        'kelompok' => $tipe,
                        'saldo_normal' => $saldo,
                        'is_active' => true,
                    ]
                );
            }

            $lines[] = [
                'akun_kode' => $kode,
                'debit' => (float) ($line['debit'] ?? 0),
                'kredit' => (float) ($line['kredit'] ?? 0),
                'deskripsi' => $line['deskripsi'] ?? null,
            ];
        }

        $userId = ! empty($payload['user_email'])
            ? User::where('email', (string) $payload['user_email'])->value('id')
            : ($payload['user_id'] ?? null);

        if ($userId && ! User::where('id', $userId)->exists()) {
            $userId = null;
        }

        app(JurnalService::class)->post(
            noJurnal: $noJurnal,
            tanggal: ! empty($payload['tanggal']) ? Carbon::parse((string) $payload['tanggal']) : now(),
            sumber: (string) ($payload['sumber'] ?? 'manual'),
            lines: $lines,
            deskripsi: $payload['deskripsi'] ?? null,
            cabangId: $cabangId,
            userId: $userId,
            referensiTipe: $payload['referensi_tipe'] ?? null,
            referensiId: $payload['referensi_id'] ?? null,
            idempotencyKey: $payload['idempotency_key'] ?? ("sync-jurnal-{$cabangKey}-{$noJurnal}"),
            statusApproval: (string) ($payload['status_approval'] ?? 'disetujui')
        );

        if ($this->tableHasSyncUuid(JurnalHeader::class) && ! empty($payload['sync_uuid'])) {
            DB::table('jurnal_header')
                ->where('no_jurnal', $noJurnal)
                ->where('cabang_key', $cabangKey)
                ->update([
                    'sync_uuid' => (string) $payload['sync_uuid'],
                    'is_reversed' => (bool) ($payload['is_reversed'] ?? false),
                    'reversed_by_no_jurnal' => $payload['reversed_by_no_jurnal'] ?? null,
                    'reversal_of_no_jurnal' => $payload['reversal_of_no_jurnal'] ?? null,
                    'updated_at' => now(),
                ]);
        }
    }

    protected function handlePayrollUpsert(array $payload): void
    {
        if (! class_exists(PayrollPeriode::class)) {
            return;
        }

        $periodeStr = (string) ($payload['periode'] ?? '');
        if ($periodeStr === '') {
            return;
        }

        $cabangId = isset($payload['cabang_id']) ? (int) $payload['cabang_id'] : null;

        $periodeRecord = $this->resolveTargetModel(PayrollPeriode::class, $payload, [
            function ($query) use ($periodeStr, $cabangId): bool {
                $query->where('periode', $periodeStr);
                if ($cabangId !== null) {
                    $query->where('cabang_id', $cabangId);
                }

                return true;
            },
        ]);

        if (! $periodeRecord) {
            $periodeRecord = new PayrollPeriode;
        }

        $periodeRecord->fill([
            'cabang_id' => $cabangId,
            'periode' => $periodeStr,
            'tanggal_mulai' => $payload['tanggal_mulai'] ?? $periodeRecord->tanggal_mulai,
            'tanggal_selesai' => $payload['tanggal_selesai'] ?? $periodeRecord->tanggal_selesai,
            'status' => $payload['status'] ?? $periodeRecord->status ?? 'selesai',
            'catatan' => $payload['catatan'] ?? $periodeRecord->catatan,
        ]);

        if ($this->tableHasSyncUuid(PayrollPeriode::class) && ! empty($payload['sync_uuid'])) {
            $periodeRecord->setAttribute('sync_uuid', (string) $payload['sync_uuid']);
        }

        $periodeRecord->save();

        $slips = is_array($payload['slips'] ?? null) ? $payload['slips'] : [];
        foreach ($slips as $slipData) {
            $karyawanId = $this->resolveForeignId(
                Karyawan::class,
                $slipData,
                'karyawan_id',
                null,
                [
                    ['nik' => $slipData['karyawan_nik'] ?? null],
                    ['email' => $slipData['karyawan_email'] ?? null],
                ]
            );

            if (! $karyawanId) {
                continue;
            }

            $slip = PayrollSlip::firstOrNew([
                'payroll_periode_id' => $periodeRecord->id,
                'karyawan_id' => $karyawanId,
            ]);

            $slip->fill([
                'gaji_pokok' => (float) ($slipData['gaji_pokok'] ?? 0),
                'total_tunjangan' => (float) ($slipData['total_tunjangan'] ?? 0),
                'total_potongan' => (float) ($slipData['total_potongan'] ?? 0),
                'total_komisi' => (float) ($slipData['total_komisi'] ?? 0),
                'total_gaji' => (float) ($slipData['total_gaji'] ?? 0),
                'rincian' => $slipData['rincian'] ?? [],
            ]);
            $slip->status = $slipData['status'] ?? $slip->status ?? 'approved';
            $slip->save();
        }
    }

    /**
     * Proses pesanan online yang sudah lunas (`order.online_paid`) di store node [C4]:
     * - stok WAJIB turun sebesar qty_dasar (jumlah * konversi), fail-closed jika gudang/stok kurang;
     * - item transaksi menyimpan satuan, konversi, harga_final, dan HPP historis tervalidasi;
     * - jurnal WAJIB: Dr 120-02 total / Cr 410-03 DPP (+ Cr 220-01 PPN Keluaran,
     *   Cr 210-04 Titipan Ongkir) DAN baris HPP Dr 510-02 / Cr 130-01;
     * - semua mutasi transaksi + item + stok + jurnal berada di dalam DB::transaction process() (atomic).
     */
    protected function handleOrderOnlinePaid(array $payload): void
    {
        $nomorOrder = $payload['nomor_order'] ?? $payload['no_transaksi'] ?? ('ONLINE-'.($payload['order_id'] ?? Str::random(8)));
        $cabangId = (int) ($payload['cabang_id'] ?? 1);
        $total = (float) ($payload['total'] ?? $payload['total_akhir'] ?? 0);
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

            $produkId = $this->resolveProdukId($it) ?? (int) ($it['produk_id'] ?? 0);
            if ($produkId <= 0) {
                throw new \RuntimeException("Item pesanan online {$nomorOrder} tanpa produk_id — sinkronisasi ditolak.");
            }
            $produk = Produk::find($produkId);
            if (! $produk) {
                throw new \RuntimeException("Produk {$produkId} (pesanan {$nomorOrder}) tidak ditemukan di database lokal. Sinkronkan master produk terlebih dahulu — pesanan tidak diproses.");
            }

            $skuVariantId = $this->resolveSkuVariantId($it, $produkId);
            $skuVariant = $skuVariantId ? SkuVariant::find($skuVariantId) : null;

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
            $hppBase = (float) ($skuVariant?->harga_beli ?? $produk->harga_beli);
            $hppPerSatuan = $hppCloud > 0 ? $hppCloud : $hppBase * $konversi;

            $harga = (float) ($it['harga'] ?? $it['harga_satuan'] ?? 0);
            $diskon = (float) ($it['diskon_nominal'] ?? 0);

            $normalized[] = [
                'produk_id' => $produkId,
                'sku_variant_id' => $skuVariantId,
                'jumlah' => $jumlah,
                'satuan' => $satuan,
                'konversi' => $konversi,
                'qty_dasar' => $qtyDasar,
                'harga_satuan' => $harga,
                'diskon_nominal' => $diskon,
                'subtotal' => round($harga * $jumlah - $diskon, 2),
                'hpp' => $hppPerSatuan,
            ];
            $totalHpp += $hppPerSatuan * $jumlah;
        }
        $totalHpp = round($totalHpp, 2);

        if ($totalHpp <= 0) {
            // shortcut: jurnal HPP/Persediaan dilewati jika harga_beli belum diisi, bukan reject pesanan
            Log::warning("[HybridSync] HPP pesanan online {$nomorOrder} nol — jurnal HPP/Persediaan dilewati. Perbarui harga_beli produk lokal.");
            $totalHpp = 0.0;
        }

        // 2. Pisah DPP/PPN/ongkir dari kolom cloud (pola DuitkuPaymentProcessor).
        $ongkir = max(0.0, (float) ($payload['ongkir'] ?? 0));
        $totalBarang = max(0.0, round($total - $ongkir, 2));
        [$dpp, $ppn] = $this->pisahDppPpnPayload($payload, $totalBarang, $nomorOrder);

        $pelangganId = $this->resolvePelangganId($payload);
        $gudangId = $this->resolveGudangId($payload, $cabangId);

        // 3. Transaksi + item dengan satuan/konversi asli & hpp per satuan tervalidasi.
        $transaksi = Transaksi::create([
            'no_transaksi' => $nomorOrder,
            'cabang_id' => $cabangId,
            'pelanggan_id' => $pelangganId,
            'gudang_id' => $gudangId,
            'subtotal' => $totalBarang,
            'diskon_nominal' => (float) ($payload['diskon_nominal'] ?? 0),
            'diskon_partai' => (float) ($payload['diskon_partai'] ?? 0),
            'dpp' => $dpp,
            'pajak_nominal' => $ppn,
            'ppn_nominal' => $ppn,
            'total_akhir' => $total,
            'metode_bayar' => $payload['metode_bayar'] ?? 'transfer',
            'jumlah_bayar' => $total,
            'payment_reference' => $payload['payment_reference'] ?? null,
            'paid_at' => ! empty($payload['paid_at']) ? Carbon::parse((string) $payload['paid_at']) : now(),
            'status' => 'lunas',
            'sumber' => 'marketplace',
            'catatan' => 'Pesanan Online Lunas dari Cloud VPS',
            'offline_uuid' => $payload['offline_uuid'] ?? (string) Str::uuid(),
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
        $jurnalService = app(JurnalService::class);
        $noJurnal = $jurnalService->generateNoJurnal('pos', $cabangId);
        $lines = [
            ['akun_kode' => '120-02', 'debit' => $total, 'kredit' => 0],
            ['akun_kode' => '410-03', 'debit' => 0, 'kredit' => $dpp],
        ];
        if ($ongkir > 0) {
            $lines[] = ['akun_kode' => '210-04', 'debit' => 0, 'kredit' => $ongkir];
        }
        foreach (app(PajakService::class)->jurnalLines($ppn, $noJurnal, $cabangId, 0) as $ppnLine) {
            $lines[] = $ppnLine;
        }
        if ($totalHpp > 0.0) {
            $lines[] = ['akun_kode' => '510-02', 'debit' => $totalHpp, 'kredit' => 0];
            $lines[] = ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => $totalHpp];
        }

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
