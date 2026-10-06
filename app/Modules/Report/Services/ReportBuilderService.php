<?php

namespace App\Modules\Report\Services;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokTransfer;
use App\Modules\Wms\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * [F2-4] Report Builder Service.
 * Model whitelist — rejects any non-whitelisted source model.
 * All queries scoped to cabang_id (session).
 */
class ReportBuilderService
{
    /**
     * Whitelist of allowed source models for report builder.
     * Keys: model class name (short), Values: display label.
     */
    public const MODEL_WHITELIST = [
        'Transaksi' => 'Transaksi (Penjualan)',
        'JurnalAkuntansi' => 'Jurnal Akuntansi',
        'StokItem' => 'Stok Item',
        'PurchaseOrder' => 'Purchase Order',
        'Piutang' => 'Piutang',
        'Utang' => 'Utang',
        'TiketServis' => 'Tiket Servis',
        'Produk' => 'Produk',
        'TransaksiItem' => 'Transaksi Item',
        'StokLog' => 'Stok Log',
        'PurchaseOrderItem' => 'Purchase Order Item',
        'TiketServisItem' => 'Tiket Servis Item',
    ];

    /**
     * Map model short name to fully qualified class.
     */
    public const MODEL_MAP = [
        'Transaksi' => Transaksi::class,
        'TransaksiItem' => TransaksiItem::class,
        'JurnalAkuntansi' => JurnalAkuntansi::class,
        'StokItem' => StokItem::class,
        'StokLog' => StokLog::class,
        'PurchaseOrder' => PurchaseOrder::class,
        'PurchaseOrderItem' => PurchaseOrderItem::class,
        'Piutang' => Piutang::class,
        'Utang' => Utang::class,
        'TiketServis' => TiketServis::class,
        'TiketServisItem' => TiketServisItem::class,
        'Produk' => Produk::class,
    ];

    /**
     * [P0-3] Peta scope cabang EKSPLISIT — menggantikan heuristik getFillable().
     *
     * type 'column' : kolom cabang_id ada langsung di tabel model.
     * type 'relation': cabang diturunkan lewat relasi (path boleh bertitik);
     *                  callback where('cabang_id') diterapkan ke relasi paling dalam.
     *
     * Setiap model di MODEL_MAP WAJIB punya entri — model tanpa entri
     * FAIL CLOSED (tidak ada baris yang keluar, lihat applyCabangScope()).
     */
    public const CABANG_SCOPE = [
        'Transaksi' => ['type' => 'column', 'path' => 'cabang_id'],
        'JurnalAkuntansi' => ['type' => 'column', 'path' => 'cabang_id'],
        'Piutang' => ['type' => 'column', 'path' => 'cabang_id'],
        'Utang' => ['type' => 'column', 'path' => 'cabang_id'],
        'TiketServis' => ['type' => 'column', 'path' => 'cabang_id'],
        'StokItem' => ['type' => 'relation', 'path' => 'gudang'],                    // StokItem → gudang.cabang_id
        'StokLog' => ['type' => 'relation', 'path' => 'gudang'],                      // StokLog → gudang.cabang_id
        'TransaksiItem' => ['type' => 'relation', 'path' => 'transaksi'],             // → transaksi.cabang_id
        'PurchaseOrder' => ['type' => 'relation', 'path' => 'gudangTujuan'],          // → gudang_tujuan.cabang_id
        'PurchaseOrderItem' => ['type' => 'relation', 'path' => 'purchaseOrder.gudangTujuan'],
        'TiketServisItem' => ['type' => 'relation', 'path' => 'tiketServis'],         // tiket_servis.cabang_id
        // Produk adalah master global tanpa cabang_id — tampil hanya bila punya
        // stok di gudang milik cabang aktif (fail closed untuk produk tanpa stok cabang).
        'Produk' => ['type' => 'relation', 'path' => 'stokItems.gudang'],
    ];

    /**
     * [P0-5] Operator filter yang diizinkan (whitelist ketat).
     */
    public const FILTER_OPERATORS = ['=', '!=', '>', '<', '>=', '<=', 'like'];

    /**
     * Kolom-kolom bisnis terkurasi per model untuk tampilan dan ekspor laporan yang bersih dan relevan.
     */
    public const CURATED_COLUMNS = [
        'Transaksi' => [
            'no_transaksi', 'cabang_id', 'kasir_id', 'pelanggan_id', 'sumber',
            'subtotal', 'diskon_nominal', 'total_akhir', 'metode_bayar',
            'jumlah_bayar', 'kembalian', 'status', 'catatan', 'created_at',
        ],
        'TransaksiItem' => [
            'transaksi_id', 'produk_id', 'sku_variant_id', 'qty',
            'harga_satuan', 'diskon_nominal', 'subtotal', 'hpp',
        ],
        'Piutang' => [
            'no_piutang', 'pelanggan_id', 'transaksi_id', 'cabang_id',
            'jumlah', 'jumlah_dibayar', 'sisa', 'jatuh_tempo', 'status',
            'keterangan', 'created_at',
        ],
        'Utang' => [
            'no_utang', 'kreditor_nama', 'referensi_tipe', 'referensi_id',
            'cabang_id', 'jumlah', 'jumlah_dibayar', 'sisa', 'jatuh_tempo',
            'status', 'keterangan', 'created_at',
        ],
        'PurchaseOrder' => [
            'no_po', 'supplier_id', 'gudang_tujuan_id', 'total',
            'total_dibayar', 'sisa', 'status', 'metode_bayar',
            'jatuh_tempo', 'catatan', 'created_at',
        ],
        'PurchaseOrderItem' => [
            'purchase_order_id', 'produk_id', 'sku_variant_id',
            'qty_dipesan', 'qty_diterima', 'harga_beli', 'subtotal',
        ],
        'TiketServis' => [
            'no_tiket', 'cabang_id', 'pelanggan_id', 'nama_pelanggan',
            'telepon_pelanggan', 'tipe_hp', 'seri_hp', 'keluhan',
            'kondisi_fisik', 'teknisi_id', 'jenis_servis_id', 'status',
            'estimasi_biaya', 'biaya_akhir', 'status_pembayaran',
            'metode_pembayaran', 'tanggal_terima', 'tanggal_selesai', 'tanggal_diambil', 'created_at',
        ],
        'TiketServisItem' => [
            'tiket_servis_id', 'tipe', 'nama_item', 'qty',
            'harga_satuan', 'subtotal', 'created_at',
        ],
        'StokItem' => [
            'produk_id', 'sku_variant_id', 'barcode', 'gudang_id',
            'rak_id', 'jumlah', 'jumlah_minimum',
        ],
        'StokLog' => [
            'created_at', 'produk_id', 'sku_variant_id', 'gudang_id',
            'referensi_tipe', 'referensi_id', 'jumlah', 'jumlah_sebelum',
            'jumlah_setelah', 'user_id', 'keterangan',
        ],
        'Produk' => [
            'kode', 'barcode', 'nama', 'kategori_id', 'brand_id',
            'kualitas_id', 'satuan', 'harga_beli', 'harga_jual_retail',
            'is_active',
        ],
        'JurnalAkuntansi' => [
            'no_jurnal', 'tanggal', 'cabang_id', 'akun_coa_id',
            'deskripsi', 'debit', 'kredit', 'sumber', 'user_id',
        ],
    ];

    /**
     * Kolom internal, sensitif, atau teknis yang dilarang muncul dalam ekspor.
     */
    public const SENSITIVE_EXPORT_COLUMNS = [
        'id', 'token_approval', 'kunci_terenkripsi', 'kunci', 'tipe_kunci',
        'foto_unit', 'sid_detail', 'is_migrasi_sid', 'kas_id', 'kode_lama',
        'split_detail', 'pajak_detail', 'payment_reference', 'payment_url',
        'paid_at', 'sales', 'dpp', 'pajak_nominal', 'ppn_nominal', 'slug',
        'meta_title', 'meta_description', 'abc_class', 'reorder_point',
        'min_stock', 'max_stock', 'is_ondemand', 'harga_fleksibel', 'gambar',
        'foto', 'barcode_alt', 'golongan', 'subgolongan', 'satuan_beli',
        'isi_satuan', 'harga_lain', 'diskon', 'stok_maksimum', 'stok_warning',
        'expired_at', 'wajib_serial', 'poin', 'komisi_sales', 'kena_pajak',
        'nilai_ppn', 'sn', 'kompatibilitas_hp', 'password', 'remember_token',
    ];

    /**
     * Label ramah pengguna untuk kolom-kolom database umum.
     */
    public const COLUMN_LABELS = [
        'cabang_id' => 'Cabang',
        'user_id' => 'Petugas / Kasir',
        'kasir_id' => 'Kasir',
        'teknisi_id' => 'Teknisi',
        'penerima_id' => 'Penerima',
        'user_pengirim_id' => 'User Pengirim',
        'user_penerima_id' => 'User Penerima',
        'pelanggan_id' => 'Pelanggan',
        'gudang_id' => 'Gudang',
        'gudang_asal_id' => 'Gudang Asal',
        'gudang_tujuan_id' => 'Gudang Tujuan',
        'supplier_id' => 'Supplier',
        'produk_id' => 'Produk',
        'akun_id' => 'Akun COA',
        'akun_coa_id' => 'Akun COA',
        'referensi_tipe' => 'Tipe Dokumen',
        'referensi_id' => 'No Referensi',
        'sku_variant_id' => 'Varian / SKU',
        'jenis_servis_id' => 'Jenis Servis',
        'nama_item' => 'Nama Item',
        'qty' => 'Qty',
        'hpp' => 'HPP (Rp)',
        'harga' => 'Harga (Rp)',
        'rak_id' => 'Lokasi Rak',
        'kategori_id' => 'Kategori',
        'brand_id' => 'Brand',
        'kualitas_id' => 'Kualitas',
        'transaksi_id' => 'No Transaksi',
        'purchase_order_id' => 'No PO',
        'tiket_servis_id' => 'No Tiket Servis',
        'tier_membership_id' => 'Tier Pelanggan',
        'tier_id' => 'Tier Pelanggan',
        'no_transaksi' => 'No Transaksi',
        'no_po' => 'No Purchase Order',
        'no_tiket' => 'No Tiket Servis',
        'no_piutang' => 'No Piutang',
        'no_utang' => 'No Utang',
        'no_jurnal' => 'No Jurnal',
        'total_akhir' => 'Total Akhir (Rp)',
        'total_kotor' => 'Total Kotor (Rp)',
        'total_diskon' => 'Diskon (Rp)',
        'total_pajak' => 'Pajak (Rp)',
        'harga_beli' => 'Harga Beli (Rp)',
        'harga_jual' => 'Harga Jual (Rp)',
        'harga_jual_retail' => 'Harga Retail (Rp)',
        'harga_satuan' => 'Harga Satuan (Rp)',
        'subtotal' => 'Subtotal (Rp)',
        'nominal' => 'Nominal (Rp)',
        'sisa' => 'Sisa Tagihan (Rp)',
        'debit' => 'Debit (Rp)',
        'kredit' => 'Kredit (Rp)',
        'estimasi_biaya' => 'Estimasi Biaya (Rp)',
        'biaya_akhir' => 'Biaya Akhir (Rp)',
        'jumlah' => 'Jumlah (Qty)',
        'jumlah_minimum' => 'Batas Min Qty',
        'metode_bayar' => 'Metode Pembayaran',
        'metode_pembayaran' => 'Metode Pembayaran',
        'status' => 'Status',
        'catatan' => 'Catatan',
        'catatan_admin' => 'Catatan Admin',
        'keterangan' => 'Keterangan',
        'tipe' => 'Tipe',
        'tanggal' => 'Tanggal',
        'tanggal_terima' => 'Tanggal Terima',
        'tanggal_selesai' => 'Tanggal Selesai',
        'tanggal_diambil' => 'Tanggal Diambil',
        'jatuh_tempo' => 'Jatuh Tempo',
        'garansi' => 'Garansi',
        'imei' => 'IMEI',
        'tipe_device' => 'Tipe Perangkat',
        'jenis_hp' => 'Tipe HP',
        'seri_hp' => 'Seri HP',
        'tipe_kunci' => 'Tipe Kunci',
        'kunci_terenkripsi' => 'Kunci',
        'alasan_estimasi' => 'Alasan Estimasi',
        'token_approval' => 'Token Approval',
        'keluhan' => 'Keluhan',
        'kondisi_fisik' => 'Kondisi Fisik',
        'kelengkapan' => 'Kelengkapan',
        'nama' => 'Nama',
        'nama_pelanggan' => 'Nama Pelanggan',
        'telepon_pelanggan' => 'Telepon Pelanggan',
        'kode' => 'Kode',
        'sku' => 'SKU',
        'barcode' => 'Barcode',
        'satuan' => 'Satuan',
        'alamat' => 'Alamat',
        'telepon' => 'No Telepon',
        'no_hp' => 'No HP / WhatsApp',
        'email' => 'Email',
        'jumlah_bayar' => 'Jumlah Bayar (Rp)',
        'kembalian' => 'Kembalian (Rp)',
        'diskon_nominal' => 'Diskon (Rp)',
        'diskon_persen' => 'Diskon (%)',
        'jumlah_dibayar' => 'Jumlah Dibayar (Rp)',
        'kreditor_nama' => 'Nama Kreditor',
        'jumlah_sebelum' => 'Jumlah Sebelum',
        'jumlah_setelah' => 'Jumlah Setelah',
        'sumber' => 'Sumber',
        'deskripsi' => 'Deskripsi',
        'is_active' => 'Status Aktif',
        'shared' => 'Dibagikan Cabang',
        'created_at' => 'Waktu Dibuat',
        'updated_at' => 'Waktu Diperbarui',
        'total' => 'Total (Rp)',
        'total_dibayar' => 'Total Dibayar (Rp)',
    ];

    /**
     * Resolusi label kolom kontekstual sesuai model agar nominal rupiah & judul tidak ambigu.
     */
    public static function getColumnLabel(string $field, ?string $model = null): string
    {
        if ($model === 'Piutang') {
            if ($field === 'jumlah') {
                return 'Nominal Piutang (Rp)';
            }
            if ($field === 'sisa') {
                return 'Sisa Tagihan (Rp)';
            }
        }

        if ($model === 'Utang') {
            if ($field === 'jumlah') {
                return 'Nominal Utang (Rp)';
            }
            if ($field === 'sisa') {
                return 'Sisa Utang (Rp)';
            }
            if ($field === 'kreditor_nama') {
                return 'Kreditor / Supplier';
            }
        }

        if ($model === 'PurchaseOrder') {
            if ($field === 'total') {
                return 'Total PO (Rp)';
            }
            if ($field === 'total_dibayar') {
                return 'Total Dibayar (Rp)';
            }
            if ($field === 'sisa') {
                return 'Sisa Tagihan (Rp)';
            }
        }

        if ($field === 'total') {
            return 'Total (Rp)';
        }
        if ($field === 'total_dibayar') {
            return 'Total Dibayar (Rp)';
        }

        return self::COLUMN_LABELS[$field] ?? ucwords(str_replace('_', ' ', $field));
    }

    /**
     * Get columns available for a whitelisted model.
     * Returns array of [field => label].
     */
    public function getAvailableColumns(string $modelShortName): array
    {
        $this->validateModel($modelShortName);
        $modelClass = self::MODEL_MAP[$modelShortName];
        $model = new $modelClass;
        $fillable = $model->getFillable() ?? [];

        return collect($fillable)
            ->filter(fn ($f) => ! in_array($f, ['created_at', 'updated_at', 'deleted_at']))
            ->mapWithKeys(fn ($f) => [
                $f => self::COLUMN_LABELS[$f] ?? str_replace('_', ' ', ucwords(str_replace('_', ' ', $f))),
            ])
            ->toArray();
    }

    /**
     * Validate that a model name is in the whitelist.
     *
     * @throws \InvalidArgumentException
     */
    public function validateModel(string $modelShortName): void
    {
        if (! isset(self::MODEL_MAP[$modelShortName])) {
            throw new \InvalidArgumentException(
                "Model '{$modelShortName}' tidak ada di whitelist laporan. Model yang diizinkan: ".implode(', ', array_keys(self::MODEL_MAP))
            );
        }
    }

    /**
     * Build a scoped query for the given model with filters, columns, and grouping.
     *
     * Kontrak filter (dua format didukung):
     * - Baris: [['field' => 'status', 'op' => '=', 'value' => 'selesai'], ...]
     *   — field wajib lolos whitelist getAvailableColumns(), op wajib dari FILTER_OPERATORS.
     * - Legacy assoc: ['status' => 'selesai'] — field juga divalidasi ke whitelist.
     * Baris kosong / field di luar whitelist diabaikan (anti injeksi kolom, fail closed).
     */
    public function buildQuery(
        string $modelShortName,
        array $columns = ['*'],
        ?array $filters = null,
        ?array $groupBy = null,
        ?int $cabangId = null
    ): Builder {
        $this->validateModel($modelShortName);
        $modelClass = self::MODEL_MAP[$modelShortName];
        $cabangId = $cabangId ?: session('cabang_id');
        $allowedColumns = array_keys($this->getAvailableColumns($modelShortName));

        // [P1-10] Group-by hanya boleh memakai kolom ter-whitelist.
        $groupByFields = array_values(array_intersect(
            array_filter(is_array($groupBy) ? $groupBy : [], 'is_string'),
            $allowedColumns
        ));

        if ($groupByFields !== []) {
            // [P1-10] MySQL 8 ONLY_FULL_GROUP_BY: saat ada GROUP BY, select hanya
            // boleh berisi kolom tergrup + kolom agregat → kolom tergrup + COUNT(*).
            $query = $modelClass::query()
                ->select($groupByFields)
                ->selectRaw('count(*) as jumlah');
        } else {
            $select = array_values(array_intersect(array_filter($columns, 'is_string'), $allowedColumns));
            $query = $modelClass::query()->select($select !== [] ? $select : ['*']);
        }

        // [P0-3] Scope cabang eksplisit — fail closed bila scope tak dikenal / cabang tidak aktif.
        $this->applyCabangScope($query, $modelShortName, $cabangId);

        $this->applyFilters($query, $modelShortName, $filters);

        foreach ($groupByFields as $field) {
            $query->groupBy($field);
        }

        return $query;
    }

    /**
     * [P0-2/P0-3] Terapkan scope cabang berdasarkan peta eksplisit CABANG_SCOPE.
     *
     * Fail closed: cabang aktif null ATAU model tidak ada di peta → query tidak
     * pernah mengembalikan baris (where 1 = 0), bukan melempar data lintas cabang.
     */
    public function applyCabangScope(Builder $query, string $modelShortName, ?int $cabangId): Builder
    {
        $scope = self::CABANG_SCOPE[$modelShortName] ?? null;

        if (! $cabangId || $scope === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($scope['type'] === 'column') {
            return $query->where($scope['path'], $cabangId);
        }

        return $query->whereHas(
            $scope['path'],
            fn (Builder $q) => $q->where('cabang_id', $cabangId)
        );
    }

    /**
     * [P0-2] Verifikasi satu baris berada di scope cabang aktif.
     * Berbagi CABANG_SCOPE dengan buildQuery() — gagal = false (fail closed).
     */
    public function itemInCabang(string $modelShortName, Model $item, ?int $cabangId = null): bool
    {
        $this->validateModel($modelShortName);

        $modelClass = self::MODEL_MAP[$modelShortName];
        if (! $item instanceof $modelClass) {
            return false;
        }

        $cabangId = $cabangId ?: session('cabang_id');

        return $this->applyCabangScope($modelClass::query(), $modelShortName, $cabangId)
            ->whereKey($item->getKey())
            ->exists();
    }

    /**
     * [P0-5/P2] Terapkan filter dengan whitelist field + whitelist operator.
     */
    private function applyFilters(Builder $query, string $modelShortName, ?array $filters): void
    {
        if (! $filters) {
            return;
        }

        $allowedColumns = array_keys($this->getAvailableColumns($modelShortName));

        if ($this->filtersAreRows($filters)) {
            foreach ($filters as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $field = $row['field'] ?? null;
                $op = $row['op'] ?? '=';
                $value = $row['value'] ?? null;

                // Baris kosong / field di luar whitelist diabaikan (anti injeksi kolom).
                if (! is_string($field) || $field === '' || ! in_array($field, $allowedColumns, true)) {
                    continue;
                }
                if (! in_array($op, self::FILTER_OPERATORS, true)) {
                    $op = '=';
                }
                if ($value === null || $value === '') {
                    continue;
                }

                $query->where($field, $op, $op === 'like' ? "%{$value}%" : $value);
            }

            return;
        }

        // Format legacy [kolom => nilai] — field tetap wajib lolos whitelist.
        // [P2-5] Tanpa heuristik panjang nilai (strlen > 2 → like): operator
        // eksplisit default '=' (atau whereIn bila nilai berupa array).
        // Baris format baris memakai op dari whitelist FILTER_OPERATORS.
        foreach ($filters as $field => $value) {
            if (! is_string($field) || ! in_array($field, $allowedColumns, true)) {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, '=', $value);
            }
        }
    }

    /**
     * Deteksi format filter: daftar baris {field,op,value} vs legacy assoc [kolom => nilai].
     */
    private function filtersAreRows(array $filters): bool
    {
        foreach ($filters as $value) {
            if (is_array($value) && array_key_exists('field', $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the display label for a model short name.
     */
    public function getModelLabel(string $modelShortName): string
    {
        return self::MODEL_WHITELIST[$modelShortName] ?? $modelShortName;
    }

    /**
     * Get all whitelisted models as [shortName => label].
     */
    public function getWhitelist(): array
    {
        return self::MODEL_WHITELIST;
    }

    /**
     * Get drill-down target model for a given source model.
     * Returns next model in the drill-down chain or null.
     *
     * Chain: Transaksi → TransaksiItem
     *        JurnalAkuntansi → null (terminal)
     *        StokItem → StokLog
     *        PurchaseOrder → PurchaseOrderItem
     *        Piutang → Transaksi
     *        Utang → null (terminal)
     *        TiketServis → TiketServisItem
     *        Produk → StokItem
     */
    public function getDrillDownTarget(string $modelShortName): ?string
    {
        $chain = [
            'Transaksi' => 'TransaksiItem',
            'TransaksiItem' => null,
            'JurnalAkuntansi' => null,
            'StokItem' => 'StokLog',
            'StokLog' => null,
            'PurchaseOrder' => 'PurchaseOrderItem',
            'PurchaseOrderItem' => null,
            'Piutang' => 'Transaksi',
            'Utang' => null,
            'TiketServis' => 'TiketServisItem',
            'TiketServisItem' => null,
            'Produk' => 'StokItem',
        ];

        return $chain[$modelShortName] ?? null;
    }

    /**
     * Dapatkan kolom tanggal / timestamp utama untuk model yang ditentukan.
     */
    public function getDateColumnForModel(string $modelShortName): ?string
    {
        $this->validateModel($modelShortName);
        $modelClass = self::MODEL_MAP[$modelShortName];
        $modelObj = new $modelClass;
        $table = $modelObj->getTable();

        // Preferensi kolom tanggal bisnis jika ada, fallback ke created_at
        if ($modelShortName === 'JurnalAkuntansi') {
            return 'tanggal';
        }

        if ($modelShortName === 'TiketServis') {
            return 'created_at';
        }

        if ($modelObj->usesTimestamps()) {
            return $modelObj->getCreatedAtColumn() ?: 'created_at';
        }

        return 'created_at';
    }

    /**
     * Get the drill-down chain as array for UI breadcrumb.
     */
    public function getDrillChain(string $modelShortName): array
    {
        $chain = [];
        $current = $modelShortName;
        $visited = [];

        while ($current && ! in_array($current, $visited)) {
            $visited[] = $current;
            $chain[] = $current;
            $current = $this->getDrillDownTarget($current);
        }

        return $chain;
    }

    /**
     * Format baris data database menjadi format yang ramah dibaca pengguna.
     * Mengubah foreign key numerik (cabang_id, kasir_id, user_id, pelanggan_id, dll)
     * menjadi nama entitas riil secara efisien dalam 1 batch query per relasi.
     */
    public function formatRowsForDisplay(array $rows, ?string $model = null): array
    {
        if (empty($rows)) {
            return [];
        }

        // Kumpulkan seluruh distinct ID untuk query batching
        $cabangIds = [];
        $userIds = [];
        $pelangganIds = [];
        $gudangIds = [];
        $supplierIds = [];
        $produkIds = [];
        $akunIds = [];
        $rakIds = [];
        $kategoriIds = [];
        $brandIds = [];
        $kualitasIds = [];
        $tierIds = [];
        $transaksiIds = [];
        $poIds = [];
        $tiketIds = [];
        $skuVariantIds = [];
        $jenisServisIds = [];
        $refDocsByType = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (! empty($row['cabang_id']) && is_numeric($row['cabang_id'])) {
                $cabangIds[] = (int) $row['cabang_id'];
            }

            foreach (['user_id', 'kasir_id', 'teknisi_id', 'penerima_id', 'user_pengirim_id', 'user_penerima_id'] as $uk) {
                if (! empty($row[$uk]) && is_numeric($row[$uk])) {
                    $userIds[] = (int) $row[$uk];
                }
            }

            if (! empty($row['pelanggan_id']) && is_numeric($row['pelanggan_id'])) {
                $pelangganIds[] = (int) $row['pelanggan_id'];
            }

            foreach (['gudang_id', 'gudang_asal_id', 'gudang_tujuan_id'] as $gk) {
                if (! empty($row[$gk]) && is_numeric($row[$gk])) {
                    $gudangIds[] = (int) $row[$gk];
                }
            }

            if (! empty($row['supplier_id']) && is_numeric($row['supplier_id'])) {
                $supplierIds[] = (int) $row['supplier_id'];
            }

            if (! empty($row['produk_id']) && is_numeric($row['produk_id'])) {
                $produkIds[] = (int) $row['produk_id'];
            }

            foreach (['akun_id', 'akun_coa_id'] as $ak) {
                if (! empty($row[$ak]) && is_numeric($row[$ak])) {
                    $akunIds[] = (int) $row[$ak];
                }
            }

            if (! empty($row['rak_id']) && is_numeric($row['rak_id'])) {
                $rakIds[] = (int) $row['rak_id'];
            }

            if (! empty($row['kategori_id']) && is_numeric($row['kategori_id'])) {
                $kategoriIds[] = (int) $row['kategori_id'];
            }

            if (! empty($row['brand_id']) && is_numeric($row['brand_id'])) {
                $brandIds[] = (int) $row['brand_id'];
            }

            if (! empty($row['kualitas_id']) && is_numeric($row['kualitas_id'])) {
                $kualitasIds[] = (int) $row['kualitas_id'];
            }

            foreach (['tier_id', 'tier_membership_id'] as $tk) {
                if (! empty($row[$tk]) && is_numeric($row[$tk])) {
                    $tierIds[] = (int) $row[$tk];
                }
            }

            if (! empty($row['transaksi_id']) && is_numeric($row['transaksi_id'])) {
                $transaksiIds[] = (int) $row['transaksi_id'];
            }
            if (! empty($row['purchase_order_id']) && is_numeric($row['purchase_order_id'])) {
                $poIds[] = (int) $row['purchase_order_id'];
            }
            if (! empty($row['tiket_servis_id']) && is_numeric($row['tiket_servis_id'])) {
                $tiketIds[] = (int) $row['tiket_servis_id'];
            }

            if (! empty($row['sku_variant_id']) && is_numeric($row['sku_variant_id'])) {
                $skuVariantIds[] = (int) $row['sku_variant_id'];
            }

            if (! empty($row['jenis_servis_id']) && is_numeric($row['jenis_servis_id'])) {
                $jenisServisIds[] = (int) $row['jenis_servis_id'];
            }

            if (! empty($row['referensi_tipe']) && ! empty($row['referensi_id']) && is_numeric($row['referensi_id'])) {
                $refDocsByType[$row['referensi_tipe']][] = (int) $row['referensi_id'];
            }
        }

        // Batch lookups
        $cabangs = ! empty($cabangIds) ? Cabang::whereIn('id', array_unique($cabangIds))->pluck('nama', 'id')->all() : [];
        $users = ! empty($userIds) ? User::whereIn('id', array_unique($userIds))->pluck('name', 'id')->all() : [];
        $pelanggans = ! empty($pelangganIds) ? Pelanggan::whereIn('id', array_unique($pelangganIds))->get()->mapWithKeys(function ($p) {
            $label = $p->nama;
            if (! empty($p->no_hp)) {
                $label .= ' ('.$p->no_hp.')';
            }

            return [$p->id => $label];
        })->all() : [];
        $gudangs = ! empty($gudangIds) ? Gudang::whereIn('id', array_unique($gudangIds))->pluck('nama', 'id')->all() : [];
        $suppliers = ! empty($supplierIds) ? Supplier::whereIn('id', array_unique($supplierIds))->pluck('nama', 'id')->all() : [];
        $produks = ! empty($produkIds) ? Produk::whereIn('id', array_unique($produkIds))->get()->mapWithKeys(fn ($p) => [$p->id => $p]) : collect();
        $akuns = ! empty($akunIds) ? AkunCOA::whereIn('id', array_unique($akunIds))->get()->mapWithKeys(fn ($a) => [$a->id => ($a->kode ? "[{$a->kode}] " : '').$a->nama])->all() : [];
        $raks = ! empty($rakIds) ? Rak::whereIn('id', array_unique($rakIds))->pluck('nama', 'id')->all() : [];
        $kategoris = ! empty($kategoriIds) ? KategoriProduk::whereIn('id', array_unique($kategoriIds))->pluck('nama', 'id')->all() : [];
        $brands = ! empty($brandIds) ? Brand::whereIn('id', array_unique($brandIds))->pluck('nama', 'id')->all() : [];
        $kualitas = ! empty($kualitasIds) ? KualitasProduk::whereIn('id', array_unique($kualitasIds))->pluck('nama', 'id')->all() : [];
        $tiers = ! empty($tierIds) ? TierMembership::whereIn('id', array_unique($tierIds))->pluck('nama', 'id')->all() : [];
        $transaksis = ! empty($transaksiIds) ? Transaksi::whereIn('id', array_unique($transaksiIds))->pluck('no_transaksi', 'id')->all() : [];
        $pos = ! empty($poIds) ? PurchaseOrder::whereIn('id', array_unique($poIds))->pluck('no_po', 'id')->all() : [];
        $tikets = ! empty($tiketIds) ? TiketServis::whereIn('id', array_unique($tiketIds))->pluck('no_tiket', 'id')->all() : [];
        $skuVariants = ! empty($skuVariantIds) ? SkuVariant::whereIn('id', array_unique($skuVariantIds))->get()->mapWithKeys(fn ($v) => [$v->id => $v]) : collect();
        $jenisServis = ! empty($jenisServisIds) ? JenisServis::whereIn('id', array_unique($jenisServisIds))->pluck('nama', 'id')->all() : [];

        // Batch lookup nomor referensi dokumen
        $resolvedRefDocs = [];
        foreach ($refDocsByType as $tipe => $ids) {
            $uniqueIds = array_unique($ids);
            if ($tipe === 'App\Modules\Pos\Models\Transaksi' || $tipe === 'Transaksi') {
                $resolvedRefDocs[$tipe] = Transaksi::whereIn('id', $uniqueIds)->pluck('no_transaksi', 'id')->all();
            } elseif ($tipe === 'App\Modules\Servis\Models\TiketServis' || $tipe === 'TiketServis') {
                $resolvedRefDocs[$tipe] = TiketServis::whereIn('id', $uniqueIds)->pluck('no_tiket', 'id')->all();
            } elseif ($tipe === 'App\Modules\Wms\Models\PurchaseOrder' || $tipe === 'PurchaseOrder') {
                $resolvedRefDocs[$tipe] = PurchaseOrder::whereIn('id', $uniqueIds)->pluck('no_po', 'id')->all();
            } elseif ($tipe === 'App\Modules\Wms\Models\StokOpname' || $tipe === 'StokOpname') {
                $resolvedRefDocs[$tipe] = StokOpname::whereIn('id', $uniqueIds)->pluck('no_opname', 'id')->all();
            } elseif ($tipe === 'App\Modules\Wms\Models\StokTransfer' || $tipe === 'StokTransfer') {
                $resolvedRefDocs[$tipe] = StokTransfer::whereIn('id', $uniqueIds)->pluck('no_transfer', 'id')->all();
            } elseif ($tipe === 'App\Modules\Wms\Models\ReturnPembelian' || $tipe === 'ReturnPembelian') {
                $resolvedRefDocs[$tipe] = ReturnPembelian::whereIn('id', $uniqueIds)->pluck('no_return', 'id')->all();
            } elseif ($tipe === 'App\Modules\Akunting\Models\Piutang' || $tipe === 'Piutang') {
                $resolvedRefDocs[$tipe] = Piutang::whereIn('id', $uniqueIds)->pluck('no_piutang', 'id')->all();
            } elseif ($tipe === 'App\Modules\Akunting\Models\Utang' || $tipe === 'Utang') {
                $resolvedRefDocs[$tipe] = Utang::whereIn('id', $uniqueIds)->pluck('no_utang', 'id')->all();
            } elseif (class_exists($tipe)) {
                $resolvedRefDocs[$tipe] = $tipe::whereIn('id', $uniqueIds)->get()->mapWithKeys(function ($m) {
                    $nomor = $m->no_transaksi ?? $m->no_tiket ?? $m->no_po ?? $m->no_opname ?? $m->no_transfer ?? $m->no_piutang ?? $m->no_utang ?? $m->no_jurnal ?? $m->nomor ?? null;

                    return [$m->id => $nomor ?: class_basename($m)." #{$m->id}"];
                })->all();
            }
        }

        $refTipeMap = [
            'App\Modules\Pos\Models\Transaksi' => 'Transaksi POS',
            'App\Modules\Servis\Models\TiketServis' => 'Tiket Servis',
            'App\Modules\Wms\Models\PurchaseOrder' => 'Purchase Order',
            'App\Modules\Wms\Models\StokOpname' => 'Stok Opname',
            'App\Modules\Wms\Models\StokTransfer' => 'Transfer Stok',
            'App\Modules\Wms\Models\ReturnPembelian' => 'Retur Pembelian',
            'App\Modules\Akunting\Models\Piutang' => 'Piutang Pelanggan',
            'App\Modules\Akunting\Models\Utang' => 'Utang Supplier',
            'App\Modules\Akunting\Models\JurnalAkuntansi' => 'Jurnal Akuntansi',
            'Transaksi' => 'Transaksi POS',
            'TiketServis' => 'Tiket Servis',
            'PurchaseOrder' => 'Purchase Order',
            'StokOpname' => 'Stok Opname',
            'StokTransfer' => 'Transfer Stok',
            'ReturnPembelian' => 'Retur Pembelian',
            'Piutang' => 'Piutang Pelanggan',
            'Utang' => 'Utang Supplier',
            'JurnalAkuntansi' => 'Jurnal Akuntansi',
            'kas_sesi' => 'Sesi Kasir',
            'kas_mutasi_laci' => 'Mutasi Kas Laci',
            'transaksi' => 'Transaksi POS',
            'servis' => 'Tiket Servis',
            'po' => 'Purchase Order',
            'opname' => 'Stok Opname',
            'transfer' => 'Transfer Stok',
        ];

        $metodeMap = [
            'tunai' => 'Tunai',
            'cash' => 'Tunai',
            'transfer' => 'Transfer Bank',
            'transfer_bank' => 'Transfer Bank',
            'qris' => 'QRIS',
            'debit' => 'Kartu Debit',
            'kredit' => 'Kartu Kredit',
            'tempo' => 'Tempo / Piutang',
            'piutang' => 'Tempo / Piutang',
        ];

        $statusMap = [
            'selesai' => 'Selesai',
            'proses' => 'Diproses',
            'diproses' => 'Diproses',
            'menunggu_sparepart' => 'Menunggu Sparepart',
            'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
            'menunggu_approval' => 'Menunggu Approval',
            'batal' => 'Dibatalkan',
            'dibatalkan' => 'Dibatalkan',
            'pending' => 'Pending',
            'draft' => 'Draft',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
            'dikirim' => 'Dikirim',
            'diterima' => 'Diterima',
            'siap_diambil' => 'Siap Diambil',
            'bisa_diambil' => 'Siap Diambil',
            'diambil' => 'Sudah Diambil',
            'diagnosa' => 'Diagnosa',
            'dikerjakan' => 'Sedang Dikerjakan',
            'qc' => 'Quality Control (QC)',
            'lunas' => 'Lunas',
            'belum_lunas' => 'Belum Lunas',
        ];

        return array_map(function ($row) use (
            $cabangs, $users, $pelanggans, $gudangs, $suppliers,
            $produks, $akuns, $raks, $kategoris, $brands, $kualitas,
            $tiers, $transaksis, $pos, $tikets, $skuVariants, $jenisServis,
            $resolvedRefDocs, $refTipeMap, $metodeMap, $statusMap, $model
        ) {
            if (! is_array($row)) {
                return $row;
            }

            // Hitung kalkulasi sisa piutang/utang/PO bila belum terisi
            if ($model === 'Utang' || (isset($row['no_utang']) && ! isset($row['sisa']))) {
                $row['sisa'] = max(0, (float) ($row['jumlah'] ?? 0) - (float) ($row['jumlah_dibayar'] ?? 0));
            } elseif ($model === 'PurchaseOrder' || (isset($row['no_po']) && ! isset($row['sisa']))) {
                $row['sisa'] = max(0, (float) ($row['total'] ?? 0) - (float) ($row['total_dibayar'] ?? 0));
            } elseif ($model === 'Piutang' || isset($row['no_piutang'])) {
                if (! isset($row['sisa'])) {
                    $row['sisa'] = max(0, (float) ($row['jumlah'] ?? 0) - (float) ($row['jumlah_dibayar'] ?? 0));
                }
            }

            // Simpan raw values untuk lookup sekunder
            $rawProdukId = $row['produk_id'] ?? null;
            $rawSkuVariantId = $row['sku_variant_id'] ?? null;
            $rawRefTipe = $row['referensi_tipe'] ?? null;
            $rawRefId = $row['referensi_id'] ?? null;

            if (array_key_exists('cabang_id', $row)) {
                $cid = $row['cabang_id'];
                $row['cabang_id'] = $cid ? ($cabangs[$cid] ?? "Cabang #{$cid}") : '-';
            }

            foreach (['user_id' => 'Petugas', 'kasir_id' => 'Kasir', 'teknisi_id' => 'Teknisi', 'penerima_id' => 'Penerima', 'user_pengirim_id' => 'User', 'user_penerima_id' => 'User'] as $uk => $label) {
                if (array_key_exists($uk, $row)) {
                    $uid = $row[$uk];
                    $row[$uk] = $uid ? ($users[$uid] ?? "{$label} #{$uid}") : '-';
                }
            }

            if (array_key_exists('pelanggan_id', $row)) {
                $pid = $row['pelanggan_id'];
                if ($model === 'Utang' && empty($pid)) {
                    $row['pelanggan_id'] = '-';
                } else {
                    $row['pelanggan_id'] = empty($pid) ? 'Pelanggan Umum (Walk-in)' : ($pelanggans[$pid] ?? "Pelanggan #{$pid}");
                }
            }

            foreach (['gudang_id', 'gudang_asal_id', 'gudang_tujuan_id'] as $gk) {
                if (array_key_exists($gk, $row)) {
                    $gid = $row[$gk];
                    $row[$gk] = $gid ? ($gudangs[$gid] ?? "Gudang #{$gid}") : '-';
                }
            }

            if (array_key_exists('supplier_id', $row)) {
                $sid = $row['supplier_id'];
                $row['supplier_id'] = $sid ? ($suppliers[$sid] ?? "Supplier #{$sid}") : '-';
            }

            if (array_key_exists('produk_id', $row)) {
                $prid = $row['produk_id'];
                $pObj = $produks->get($prid);
                $row['produk_id'] = $pObj ? $pObj->nama : ($prid ? "Produk #{$prid}" : '-');
            }

            if (array_key_exists('sku_variant_id', $row)) {
                $svid = $row['sku_variant_id'];
                $svObj = $skuVariants->get($svid);
                if ($svObj) {
                    $varLabel = $svObj->nama_varian ?: 'Standar';
                    if (! empty($svObj->sku)) {
                        $varLabel .= ' ('.$svObj->sku.')';
                    }
                    $row['sku_variant_id'] = $varLabel;
                } else {
                    $row['sku_variant_id'] = $svid ? "Varian #{$svid}" : '-';
                }
            }

            foreach (['akun_id', 'akun_coa_id'] as $ak) {
                if (array_key_exists($ak, $row)) {
                    $aid = $row[$ak];
                    $row[$ak] = $aid ? ($akuns[$aid] ?? "Akun #{$aid}") : '-';
                }
            }

            if (array_key_exists('jenis_servis_id', $row)) {
                $jsid = $row['jenis_servis_id'];
                $row['jenis_servis_id'] = $jsid ? ($jenisServis[$jsid] ?? "Jenis Servis #{$jsid}") : '-';
            }

            if (array_key_exists('rak_id', $row)) {
                $rid = $row['rak_id'];
                $row['rak_id'] = $rid ? ($raks[$rid] ?? "Rak #{$rid}") : '-';
            }

            if (array_key_exists('kategori_id', $row)) {
                $katid = $row['kategori_id'];
                $row['kategori_id'] = $katid ? ($kategoris[$katid] ?? "Kategori #{$katid}") : '-';
            }

            if (array_key_exists('brand_id', $row)) {
                $bid = $row['brand_id'];
                $row['brand_id'] = $bid ? ($brands[$bid] ?? "Brand #{$bid}") : '-';
            }

            if (array_key_exists('kualitas_id', $row)) {
                $kid = $row['kualitas_id'];
                $row['kualitas_id'] = $kid ? ($kualitas[$kid] ?? "Kualitas #{$kid}") : '-';
            }

            foreach (['tier_id', 'tier_membership_id'] as $tk) {
                if (array_key_exists($tk, $row)) {
                    $tid = $row[$tk];
                    $row[$tk] = $tid ? ($tiers[$tid] ?? "Tier #{$tid}") : '-';
                }
            }

            if (array_key_exists('transaksi_id', $row)) {
                $trid = $row['transaksi_id'];
                $row['transaksi_id'] = $trid ? ($transaksis[$trid] ?? "TRX #{$trid}") : '-';
            }
            if (array_key_exists('purchase_order_id', $row)) {
                $poid = $row['purchase_order_id'];
                $row['purchase_order_id'] = $poid ? ($pos[$poid] ?? "PO #{$poid}") : '-';
            }
            if (array_key_exists('tiket_servis_id', $row)) {
                $tkid = $row['tiket_servis_id'];
                $row['tiket_servis_id'] = $tkid ? ($tikets[$tkid] ?? "Tiket #{$tkid}") : '-';
            }

            // Referensi Tipe & Referensi ID
            if (array_key_exists('referensi_tipe', $row)) {
                $rt = $rawRefTipe;
                if (! empty($rt)) {
                    $row['referensi_tipe'] = $refTipeMap[$rt] ?? (class_exists($rt) ? class_basename($rt) : ucwords(str_replace('_', ' ', $rt)));
                } else {
                    $row['referensi_tipe'] = '-';
                }
            }

            if (array_key_exists('referensi_id', $row)) {
                $rid = $rawRefId;
                if ($rid) {
                    $docNo = $resolvedRefDocs[$rawRefTipe][$rid] ?? null;
                    $row['referensi_id'] = $docNo ?: "Ref #{$rid}";
                } else {
                    $row['referensi_id'] = '-';
                }
            }

            // Resolusi Barcode bila kosong
            if (array_key_exists('barcode', $row)) {
                $bc = trim((string) ($row['barcode'] ?? ''));
                if ($bc === '' || $bc === '-') {
                    $fallbackBc = null;
                    if ($rawSkuVariantId && ($v = $skuVariants->get($rawSkuVariantId))) {
                        $fallbackBc = $v->barcode;
                    }
                    if (! $fallbackBc && $rawProdukId && ($p = $produks->get($rawProdukId))) {
                        $fallbackBc = $p->barcode;
                    }
                    $row['barcode'] = $fallbackBc ?: '-';
                }
            }

            // Metode Pembayaran
            foreach (['metode_bayar', 'metode_pembayaran'] as $mk) {
                if (array_key_exists($mk, $row) && is_string($row[$mk])) {
                    $low = strtolower(trim($row[$mk]));
                    $row[$mk] = $metodeMap[$low] ?? ucwords(str_replace('_', ' ', $row[$mk]));
                }
            }

            if (array_key_exists('status', $row) && is_string($row['status'])) {
                $low = strtolower(trim($row['status']));
                $row['status'] = $statusMap[$low] ?? ucwords(str_replace('_', ' ', $row['status']));
            }

            return $row;
        }, $rows);
    }

    /**
     * Format baris data untuk diekspor ke berkas Excel / CSV.
     * Menggunakan header berbahasa manusia sesuai UI/UX dan memformat tanggal, boolean,
     * serta nominal angka secara konsisten.
     */
    public function formatRowsForExport(array $rows, ?string $model = null): array
    {
        if (empty($rows)) {
            return [];
        }

        $formatted = [];
        $curated = $model && isset(self::CURATED_COLUMNS[$model]) ? self::CURATED_COLUMNS[$model] : null;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            // Urutkan dan filter kolom jika ada curated columns untuk model ini
            $orderedKeys = $curated ? array_intersect($curated, array_keys($row)) : array_keys($row);
            if ($curated && empty($orderedKeys)) {
                $orderedKeys = array_keys($row);
            }

            $exportRow = [];
            foreach ($orderedKeys as $key) {
                if (in_array($key, self::SENSITIVE_EXPORT_COLUMNS, true)) {
                    continue;
                }

                $val = $row[$key] ?? null;
                $headerLabel = self::getColumnLabel((string) $key, $model);

                if (is_bool($val)) {
                    $exportRow[$headerLabel] = $val ? 'Ya' : 'Tidak';
                } elseif (is_array($val)) {
                    $exportRow[$headerLabel] = implode(', ', array_map('strval', $val));
                } elseif (is_null($val) || $val === '' || $val === '-') {
                    $exportRow[$headerLabel] = '-';
                } elseif (is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}/', $val)) {
                    $exportRow[$headerLabel] = date('d/m/Y H:i', strtotime($val));
                } elseif (is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                    $exportRow[$headerLabel] = date('d/m/Y', strtotime($val));
                } elseif (is_numeric($val)) {
                    $keyStr = (string) $key;
                    $bukanNominal = in_array($keyStr, ['id', 'kode', 'sku', 'barcode'])
                        || str_ends_with($keyStr, '_id')
                        || str_ends_with($keyStr, '_kode')
                        || str_starts_with($keyStr, 'no_')
                        || str_ends_with($keyStr, '_persen');

                    $isNominalField = in_array($keyStr, [
                        'debit', 'kredit', 'total_akhir', 'total_kotor', 'total_diskon', 'total_pajak',
                        'harga_beli', 'harga_jual', 'harga_jual_retail', 'harga_satuan', 'subtotal',
                        'nominal', 'sisa', 'estimasi_biaya', 'biaya_akhir', 'harga', 'hpp',
                        'jumlah_bayar', 'kembalian', 'diskon_nominal', 'jumlah_dibayar',
                        'total', 'total_dibayar',
                    ]) || (in_array($model, ['Piutang', 'Utang'], true) && $keyStr === 'jumlah');

                    if (! $bukanNominal && $isNominalField) {
                        $exportRow[$headerLabel] = number_format((float) $val, 0, ',', '.');
                    } else {
                        $exportRow[$headerLabel] = $val;
                    }
                } else {
                    $exportRow[$headerLabel] = $val;
                }
            }

            $formatted[] = $exportRow;
        }

        return $formatted;
    }

    /**
     * Format satu baris data.
     */
    public function formatRowForDisplay(array $row, ?string $model = null): array
    {
        $res = $this->formatRowsForDisplay([$row], $model);

        return $res[0] ?? $row;
    }
}
