<?php

namespace App\Modules\Wms\Services;

use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ProdukSatuanKonversi;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\TipeHp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelReader;
use Maatwebsite\Excel\Facades\Excel;
use XMLReader;
use ZipArchive;

/**
 * [T-43] Import master produk dari Excel/CSV (Request #16).
 * - Dry-run preview dulu (per-baris error), commit via queue job ImportProdukExcelJob.
 * - Import = INISIALISASI master produk (bukan stok masuk harian — stok masuk harian tetap
 *   wajib lewat PO Supplier / T-40). Stok awal import dicatat StockMutationLog sumber
 *   'import:excel' + jurnal Debit 130-01 / Kredit 310-01 (modal stok awal), balance.
 * - Idempoten: SKU yang sudah ada di DB → baris error (tidak dobel insert).
 * - Brand & kualitas dibuat otomatis bila nama baru (natural-key firstOrCreate);
 *   satuan WAJIB sudah terdaftar di satuan_unit (referensi terkontrol).
 *
 * [B-10f/P1-5] Aturan import:
 * - SATU file = SATU cabang. File yang menunjuk gudang dari >1 cabang ditolak
 *   (pesan Indonesia) di preview() DAN commit() sebelum ada mutasi apa pun.
 * - Jurnal agregat stok awal diposting DI DALAM transaksi chunk yang sama
 *   dengan mutasi stok chunk tersebut → tidak mungkin jurnal tanpa mutasi atau
 *   sebaliknya. Chunk 100 baris (bukan 1 transaksi raksasa) demi RAM 1GB.
 */
class ImportProdukService
{
    public const MAKS_BARIS = 50000;

    public const THRESHOLD_PENCOCOKAN = 99;

    protected array $brandCache = [];

    protected array $kualitasCache = [];

    protected array $kategoriCache = [];

    protected array $tipeHpCache = [];

    protected array $supplierCache = [];

    protected array $rakCache = [];

    protected array $satuanCache = [];

    protected array $gudangCache = [];

    protected array $defaultGudangCache = [];

    protected array $cabangCache = [];

    protected ?array $existingProductCache = null;

    /**
     * Normalisasi nilai SKU dari Excel/CSV.
     * Mempertahankan awalan angka nol (cth: "00123"), membersihkan tanda petik atau formula Excel.
     */
    public function normalizeSku(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }
        $str = trim((string) $raw);
        if ($str === '') {
            return '';
        }
        // Tangani escape formula Excel seperti ="00123" atau '00123
        if (preg_match('/^=\s*["\'](.*)["\']\s*$/s', $str, $m)) {
            $str = trim($m[1]);
        }
        $str = ltrim($str, "'");

        // Tangani notasi ilmiah dari sel numerik format General (cth 1.23E+04)
        if (preg_match('/^[0-9]+(\.[0-9]+)?[eE]\+[0-9]+$/', $str) && is_numeric($str)) {
            return sprintf('%.0f', (float) $str);
        }

        return trim($str);
    }

    /**
     * Normalisasi nilai barcode dari Excel/CSV.
     * Mempertahankan awalan angka nol (cth: "08991234500001"), membersihkan tanda petik atau formula Excel,
     * serta mencegah notasi ilmiah (misal 8.99123E+12) dan float tidak sengaja dari cell format General.
     */
    public function normalizeBarcode(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }
        $str = trim((string) $raw);
        if ($str === '') {
            return '';
        }
        // Tangani escape formula Excel seperti ="08123" atau '08123
        if (preg_match('/^=\s*["\'](.*)["\']\s*$/s', $str, $m)) {
            $str = trim($m[1]);
        }
        $str = ltrim($str, "'");

        if (preg_match('/^[0-9]+(\.[0-9]+)?[eE]\+[0-9]+$/', $str) && is_numeric($str)) {
            return sprintf('%.0f', (float) $str);
        }
        if (is_float($raw) || (is_numeric($str) && str_contains($str, '.') && (float) $str == (int) (float) $str)) {
            return sprintf('%.0f', (float) $raw);
        }

        return trim($str);
    }

    /**
     * Normalisasi nama satuan ke kode baku yang terdaftar di sistem.
     * Mengakomodir variasi input pengguna: pcs, PCS, pieces, buah, dus, pack, dll.
     */
    public function normalizeSatuan(string $satuanRaw): string
    {
        $clean = strtolower(trim($satuanRaw));
        $alphanumeric = preg_replace('/[^a-z0-9]/', '', $clean);

        $aliasMap = [
            'pcs' => 'pcs',
            'pc' => 'pcs',
            'piece' => 'pcs',
            'pieces' => 'pcs',
            'buah' => 'pcs',
            'biji' => 'pcs',
            'bh' => 'pcs',
            'bsh' => 'pcs',
            'item' => 'pcs',
            'items' => 'pcs',

            'box' => 'box',
            'dus' => 'box',
            'kotak' => 'box',
            'pack' => 'pack',
            'pck' => 'pack',
            'paket' => 'paket',
            'karton' => 'karton',
            'ctn' => 'karton',

            'set' => 'set',
            'pasang' => 'set',
            'psg' => 'set',
            'st' => 'set',

            'unit' => 'unit',
            'unt' => 'unit',

            'roll' => 'roll',
            'rol' => 'roll',
            'gulung' => 'roll',

            'meter' => 'meter',
            'm' => 'meter',

            'botol' => 'botol',
            'btl' => 'botol',

            'lembar' => 'lembar',
            'lbr' => 'lembar',

            'tube' => 'tube',
            'tabung' => 'tube',
        ];

        return $aliasMap[$alphanumeric] ?? $clean;
    }

    public function normalizeImportHeading(string $heading): string
    {
        $heading = strtolower(trim($heading));
        // Strip common suffixes in parentheses like (Rp), (Pcs), (IDR)
        $heading = preg_replace('/\s*\(.*?\)\s*/', '', $heading) ?? $heading;
        $cleaned = preg_replace('/[^a-z0-9\s_-]/', '', $heading) ?? $heading;
        $normalized = preg_replace('/[\s_-]+/', '_', trim($cleaned)) ?? '';

        if (preg_match('/^barcode_(\d+)$/', $normalized)) {
            return $normalized;
        }

        return match ($normalized) {
            'sku', 'kode_sku', 'kode', 'kode_barang', 'kode_item', 'part_number', 'part_no', 'item_code' => 'sku',
            'nama', 'nama_produk', 'nama_barang', 'nama_item', 'product_name', 'item_name', 'deskripsi_barang', 'nama_sparepart' => 'nama',
            'barcode', 'bar_code', 'kode_barcode', 'upc', 'ean', 'barcode_utama' => 'barcode_1',
            'barcode_alt', 'barcodealt', 'multi_barcode', 'barcode_tambahan', 'barcode_suplier', 'barcode_supplier', 'barcode_lain', 'qr_alt' => 'multi_barcode',
            'opsi_sku', 'format_sku', 'sku_mode' => 'opsi_sku',
            'satuan', 'satuan_unit', 'unit', 'uom', 'satuan_dasar' => 'satuan',
            'multi_satuan', 'konversi_satuan', 'satuan_konversi', 'multi_uom', 'konversi' => 'konversi_satuan',
            'kategori', 'kategori_utama', 'kategori_produk', 'kategori_barang', 'category', 'main_category' => 'kategori',
            'sub_kategori', 'subkategori', 'sub_category', 'subcategory', 'sub_kategori_produk', 'sub_kategori_barang' => 'sub_kategori',
            'brand', 'brand_part', 'merk_part', 'merek_part', 'merek', 'merk', 'brand_produk', 'merk_produk' => 'brand',
            'kualitas', 'kualitas_produk', 'grade', 'kualitas_part', 'quality' => 'kualitas',
            'kondisi', 'kondisi_produk', 'condition', 'status_kondisi' => 'kondisi',
            'supplier', 'nama_supplier', 'supplier_id', 'pemasok', 'vendor', 'nama_pemasok', 'distributor' => 'supplier',
            'merk_hp', 'merek_hp', 'brand_hp', 'brand_ponsel', 'merk_ponsel', 'device_brand' => 'merk_hp',
            'tipe_hp', 'tipe_ponsel', 'model_hp', 'model_ponsel', 'tipe_model', 'model_kompatibel', 'kompatibel_tipe', 'device_model', 'compatible_models', 'kompatibel_model', 'model_kompatibilitas', 'model_kompabilitas' => 'tipe_hp',
            'kompatibilitas', 'kompatibilitas_hp', 'compatibility', 'kompabilitas', 'kompabilitas_hp', 'kompatibel_hp', 'kompabel_hp', 'tipe_kompatibel', 'kompatibilitas_tipe', 'kompabilitas_tipe', 'kompatibilitas_model', 'kompabilitas_model' => 'kompatibilitas_hp',
            'produk_kompatibel_sku', 'produk_kompatibel', 'cross_sku', 'kompatibel_sku' => 'produk_kompatibel_sku',
            'harga_beli', 'hpp', 'harga_modal', 'modal', 'buy_price', 'cost', 'cost_price', 'purchase_price' => 'harga_beli',
            'harga_jual', 'harga_retail', 'harga_umum', 'harga_normal', 'sell_price', 'selling_price', 'price', 'harga_jual_retail' => 'harga_jual',
            'harga_reseller', 'reseller', 'harga_grosir' => 'harga_reseller',
            'harga_agen', 'agen', 'harga_distributor' => 'harga_agen',
            'min_stock', 'stok_min', 'stok_minimum', 'minimum_stock' => 'min_stock',
            'reorder_point', 'titik_pesan_ulang', 'rop' => 'reorder_point',
            'sn', 'serial_number', 'is_sn', 'pakai_sn', 'lacak_sn', 'serial' => 'sn',
            'stok_awal', 'stok', 'qty', 'jumlah_stok', 'initial_stock', 'saldo_awal' => 'stok_awal',
            'gudang', 'gudang_id', 'nama_gudang', 'warehouse', 'lokasi_gudang' => 'gudang',
            'rak', 'rak_id', 'nama_rak', 'shelf', 'bin', 'lokasi_rak' => 'rak',
            'cabang', 'cabang_id', 'nama_cabang', 'branch' => 'cabang',
            'deskripsi', 'deskripsi_produk', 'keterangan', 'catatan', 'description', 'notes' => 'deskripsi',
            'foto_url', 'foto', 'gambar', 'gambar_url', 'image', 'image_url' => 'foto_url',
            default => $normalized,
        };
    }

    /**
     * Ekstraksi seluruh barcode dari baris data import.
     * Menggabungkan barcode_1 (atau barcode), barcode_2, barcode_3, dst., serta kolom multi_barcode / barcode_alt.
     * Menghasilkan array terurut unik: ['barcode_1' => '...', 'barcode_2' => '...'].
     *
     * @return array<string, string>
     */
    public function extractAllBarcodesFromRow(array $row): array
    {
        $ordered = [];

        // 1. barcode_1 atau barcode
        $b1 = $this->normalizeBarcode($row['barcode_1'] ?? $row['barcode'] ?? null);
        if ($b1 !== '') {
            $ordered[] = $b1;
        }

        // 2. Scan dynamic keys barcode_2, barcode_3, ..., barcode_N
        $numbered = [];
        foreach ($row as $k => $v) {
            if (preg_match('/^barcode_(\d+)$/', (string) $k, $m)) {
                $idx = (int) $m[1];
                if ($idx > 1) {
                    $val = $this->normalizeBarcode($v);
                    if ($val !== '') {
                        $numbered[$idx] = $val;
                    }
                }
            }
        }
        ksort($numbered);
        foreach ($numbered as $nb) {
            if (! in_array($nb, $ordered, true)) {
                $ordered[] = $nb;
            }
        }

        // 3. Scan multi_barcode / barcode_alt
        $rawAlt = $row['multi_barcode'] ?? $row['barcode_alt'] ?? null;
        if (! empty($rawAlt)) {
            $parts = is_array($rawAlt) ? $rawAlt : preg_split('/[,;\n]+/', (string) $rawAlt);
            foreach ($parts as $p) {
                $trimmed = trim((string) $p);
                $cleaned = preg_replace('/^barcode_\d+[\s:=]+/i', '', $trimmed);
                $val = $this->normalizeBarcode($cleaned);
                if ($val !== '' && ! in_array($val, $ordered, true)) {
                    $ordered[] = $val;
                }
            }
        }

        // Format mapping: barcode_1, barcode_2, barcode_3, ...
        $result = [];
        foreach ($ordered as $i => $bc) {
            $num = $i + 1;
            $result["barcode_{$num}"] = $bc;
        }

        return $result;
    }

    /**
     * Normalisasi kondisi: baru, oem, compatible.
     */
    public function normalizeKondisi(string $kondisiRaw): string
    {
        $k = strtolower(trim($kondisiRaw));
        if (in_array($k, ['baru', 'new', 'original', 'ori', 'segel', 'std'], true)) {
            return 'baru';
        }
        if (in_array($k, ['oem', 'original equipment manufacturer'], true)) {
            return 'oem';
        }
        if (in_array($k, ['compatible', 'kompatibel', 'kw', 'grade', 'aftermarket', 'substitusi'], true)) {
            return 'compatible';
        }

        return 'baru';
    }

    /**
     * Normalisasi teks kualitas/grade.
     */
    public function normalizeKualitas(string $kualitasRaw): string
    {
        $k = trim($kualitasRaw);
        if ($k === '') {
            return '';
        }
        $kl = strtolower($k);
        if (in_array($kl, ['ori', 'original', 'asli', 'genuine'], true)) {
            return 'Original';
        }
        if (in_array($kl, ['grade a', 'grade-a', 'a', 'super'], true)) {
            return 'Grade A';
        }
        if (in_array($kl, ['grade b', 'grade-b', 'b'], true)) {
            return 'Grade B';
        }
        if (in_array($kl, ['refurbish', 'refurbished', 'rekondisi'], true)) {
            return 'Refurbished';
        }
        if (in_array($kl, ['oem'], true)) {
            return 'OEM';
        }

        return ucwords($k);
    }

    /**
     * Normalisasi boolean serial number.
     */
    public function normalizeSn(mixed $snRaw): bool
    {
        $s = strtolower(trim((string) $snRaw));

        return in_array($s, ['1', 'true', 'ya', 'yes', 'y', 'wajib', 'sn'], true);
    }

    /**
     * Cache ringan produk eksisting di DB untuk deteksi kemiripan / kompatibilitas cepat.
     */
    protected function getExistingProductCache(): array
    {
        if ($this->existingProductCache === null) {
            $this->existingProductCache = Produk::with('skuVariants')
                ->select('id', 'nama', 'kategori')
                ->limit(2000)
                ->get()
                ->map(function ($p) {
                    $sku = $p->skuVariants->first()?->sku ?? "PRD-{$p->id}";

                    return [
                        'id' => $p->id,
                        'sku' => $sku,
                        'nama' => $p->nama,
                        'nama_lower' => strtolower($p->nama),
                    ];
                })
                ->all();
        }

        return $this->existingProductCache;
    }

    /**
     * Deteksi produk eksisting yang memiliki kemiripan tinggi.
     */
    protected function findSimilarExistingProduct(string $nama, string $skuCurrent, int $threshold = self::THRESHOLD_PENCOCOKAN): ?object
    {
        $cache = $this->getExistingProductCache();
        $namaLower = strtolower($nama);

        foreach ($cache as $item) {
            if (strcasecmp($item['sku'], $skuCurrent) === 0) {
                continue;
            }
            $lenDiff = abs(strlen($namaLower) - strlen($item['nama_lower']));
            if ($lenDiff > 12) {
                continue;
            }
            similar_text($namaLower, $item['nama_lower'], $pct);
            if ($pct >= $threshold) {
                return (object) array_merge($item, ['kemiripan' => round($pct)]);
            }
        }

        return null;
    }

    /**
     * Deteksi peringatan duplikasi & rekomendasi kompatibilitas cerdas.
     */
    public function checkRowWarnings(array $row, int $nomorBaris, array $konteks, int $threshold = self::THRESHOLD_PENCOCOKAN): array
    {
        $warnings = [];
        $nama = trim((string) ($row['nama'] ?? ''));
        $sku = trim((string) ($row['sku'] ?? ''));
        $tipeHp = trim((string) ($row['tipe_hp'] ?? ''));

        if ($nama === '') {
            return $warnings;
        }

        // 1. Cek potensi kemiripan dengan baris lain di dalam file yang sama (Intra-file)
        if ($konteks['skipFuzzy'] ?? false) {
            return $warnings;
        }

        if (! empty($konteks['namaDalamFile'])) {
            foreach ($konteks['namaDalamFile'] as $prev) {
                if (strcasecmp($prev['sku'], $sku) === 0) {
                    continue;
                }
                $lenDiff = abs(strlen($nama) - strlen($prev['nama']));
                if ($lenDiff > 12) {
                    continue;
                }
                similar_text(strtolower($nama), strtolower($prev['nama']), $pct);
                if ($pct >= $threshold) {
                    $pctBulat = round($pct);
                    $warnings[] = "Perhatian Kompatibilitas ({$pctBulat}% mirip dengan Baris {$prev['baris']} [{$prev['sku']}] '{$prev['nama']}'): Jika fisik sparepart identik, satukan menjadi 1 SKU dengan kolom tipe_hp multi-tipe (cth: '{$prev['tipe_hp']}; {$tipeHp}') daripada membuat master data ganda.";
                    break;
                }
            }
        }

        // 2. Cek potensi kemiripan dengan master data yang sudah ada di database
        $existing = $this->findSimilarExistingProduct($nama, $sku, $threshold);
        if ($existing) {
            $warnings[] = "Perhatian Master Ganda: Mirip ({$existing->kemiripan}%) dengan produk eksisting [{$existing->sku}] '{$existing->nama}'. Pertimbangkan menambahkan tipe HP ke produk tersebut daripada membuat master produk baru.";
        }

        return $warnings;
    }

    /**
     * Deteksi kemiripan terstruktur untuk pencocokan manual oleh pengguna.
     */
    public function detectKemiripanRow(array $row, int $nomorBaris, array $konteks, int $threshold = self::THRESHOLD_PENCOCOKAN): ?array
    {
        $nama = trim((string) ($row['nama'] ?? ''));
        $sku = trim((string) ($row['sku'] ?? ''));
        $tipeHp = trim((string) ($row['tipe_hp'] ?? ''));

        if ($nama === '' || ($konteks['skipFuzzy'] ?? false)) {
            return null;
        }

        // 1. Cek intra-file
        if (! empty($konteks['namaDalamFile'])) {
            foreach ($konteks['namaDalamFile'] as $prev) {
                if (strcasecmp($prev['sku'], $sku) === 0) {
                    continue;
                }
                $lenDiff = abs(strlen($nama) - strlen($prev['nama']));
                if ($lenDiff > 12) {
                    continue;
                }
                similar_text(strtolower($nama), strtolower($prev['nama']), $pct);
                if ($pct >= $threshold) {
                    $pctBulat = (int) round($pct);

                    return [
                        'baris' => $nomorBaris,
                        'sku' => $sku ?: 'Auto-generate',
                        'nama' => $nama,
                        'tipe_hp' => $tipeHp,
                        'tipe_kemiripan' => 'intra_file',
                        'persentase' => $pctBulat,
                        'target_id' => null,
                        'target_sku' => $prev['sku'],
                        'target_nama' => $prev['nama'],
                        'target_baris' => $prev['baris'],
                        'default_aksi' => 'gabung',
                        'keterangan' => "Mirip {$pctBulat}% dengan Baris {$prev['baris']} [{$prev['sku']}] '{$prev['nama']}' di file ini.",
                    ];
                }
            }
        }

        // 2. Cek database
        $existing = $this->findSimilarExistingProduct($nama, $sku, $threshold);
        if ($existing) {
            return [
                'baris' => $nomorBaris,
                'sku' => $sku ?: 'Auto-generate',
                'nama' => $nama,
                'tipe_hp' => $tipeHp,
                'tipe_kemiripan' => 'database',
                'persentase' => (int) $existing->kemiripan,
                'target_id' => $existing->id,
                'target_sku' => $existing->sku,
                'target_nama' => $existing->nama,
                'target_baris' => null,
                'default_aksi' => 'gabung',
                'keterangan' => "Mirip {$existing->kemiripan}% dengan master produk [{$existing->sku}] '{$existing->nama}'.",
            ];
        }

        return null;
    }

    /**
     * Resolusi supplier dari input import (ID, Nama, atau auto-create jika baru).
     */
    public function resolveSupplierId(?string $supplierRaw): ?int
    {
        $raw = trim((string) $supplierRaw);
        if ($raw === '' || in_array(strtolower($raw), ['-', '0', 'none', 'null', 'kosong'], true)) {
            return null;
        }

        if (isset($this->supplierCache[$raw])) {
            return $this->supplierCache[$raw];
        }

        if (is_numeric($raw)) {
            $supplier = Supplier::find((int) $raw);
            if ($supplier) {
                return $this->supplierCache[$raw] = $supplier->id;
            }
        }

        $supplier = Supplier::where('nama', $raw)->first();
        if ($supplier) {
            return $this->supplierCache[$raw] = $supplier->id;
        }

        $supplier = Supplier::create([
            'nama' => $raw,
            'is_active' => true,
        ]);

        return $this->supplierCache[$raw] = $supplier->id;
    }

    /**
     * Resolusi gudang dari input import (ID numerik, kode, atau nama).
     */
    public function resolveGudangId(?string $gudangRaw, ?int $cabangId = null): ?int
    {
        $raw = trim((string) $gudangRaw);
        if ($raw === '' || in_array(strtolower($raw), ['-', '0', 'none', 'null', 'kosong'], true)) {
            return null;
        }

        $cabangId = $cabangId ?? session('cabang_id') ?? auth()->user()?->cabang_id;
        $cacheKey = "id_raw|{$cabangId}|{$raw}";
        if (isset($this->gudangCache[$cacheKey])) {
            return $this->gudangCache[$cacheKey];
        }

        if (is_numeric($raw)) {
            $g = Gudang::when($cabangId, fn ($q, $c) => $q->where('cabang_id', $c))->find((int) $raw);
            if ($g) {
                return $this->gudangCache[$cacheKey] = $g->id;
            }
        }

        $g = Gudang::when($cabangId, fn ($q, $c) => $q->where('cabang_id', $c))
            ->where(function ($q) use ($raw) {
                $q->whereRaw('LOWER(kode) = ?', [strtolower($raw)])
                    ->orWhereRaw('LOWER(nama) = ?', [strtolower($raw)]);
            })
            ->first();

        if ($g) {
            return $this->gudangCache[$cacheKey] = $g->id;
        }

        // Jika menyebut "Gudang Utama", "Gudang Pusat", atau variasi nama utama
        if (strcasecmp($raw, 'Gudang Utama') === 0 || strcasecmp($raw, 'Gudang Pusat') === 0) {
            $effectiveCabangId = $cabangId ?? $this->resolveCabangDefault();
            $gDefault = Gudang::where('cabang_id', $effectiveCabangId)
                ->where('is_active', true)
                ->where(fn ($q) => $q->where('nama', 'like', '%Utama%')->orWhere('nama', 'like', '%Pusat%')->orWhere('kode', 'like', '%UTAMA%'))
                ->first()
                ?? Gudang::where('cabang_id', $effectiveCabangId)->where('is_active', true)->first();

            if ($gDefault) {
                return $this->gudangCache[$cacheKey] = $gDefault->id;
            }

            return null;
        }

        return $this->gudangCache[$cacheKey] = null;
    }

    /**
     * Resolusi lokasi rak secara fleksibel/longgar.
     * Mengembalikan null jika kosong, strip (-), atau tidak ditemukan.
     */
    public function resolveRakId(?string $rakRaw, ?int $gudangId = null): ?int
    {
        $raw = trim((string) $rakRaw);
        if ($raw === '' || in_array(strtolower($raw), ['-', '0', 'none', 'null', 'kosong'], true)) {
            return null;
        }

        if (is_numeric($raw)) {
            $query = Rak::where('id', (int) $raw);
            if ($gudangId) {
                $scoped = (clone $query)->where('gudang_id', $gudangId)->value('id');
                if ($scoped) {
                    return $scoped;
                }
            }
            $val = $query->value('id');
            if ($val) {
                return $val;
            }
        }

        $query = Rak::where(function ($q) use ($raw) {
            $q->whereRaw('LOWER(kode) = ?', [strtolower($raw)])
                ->orWhereRaw('LOWER(nama) = ?', [strtolower($raw)]);
        });

        if ($gudangId) {
            $scoped = (clone $query)->where('gudang_id', $gudangId)->value('id');
            if ($scoped) {
                return $scoped;
            }
        }

        return $query->value('id');
    }

    /**
     * Resolusi satuan dan daftarkan otomatis ke master satuan_unit bila belum ada.
     */
    public function resolveOrCreateSatuan(?string $satuanRaw): string
    {
        $raw = trim((string) $satuanRaw);
        if ($raw === '' || in_array(strtolower($raw), ['-', 'none', 'null', 'kosong'], true)) {
            $satuan = 'pcs';
        } else {
            $satuan = $this->normalizeSatuan($raw);
            if ($satuan === '') {
                $satuan = 'pcs';
            }
        }

        if (! isset($this->satuanCache[$satuan])) {
            $su = SatuanUnit::whereRaw('LOWER(kode) = ?', [$satuan])
                ->orWhereRaw('LOWER(nama) = ?', [$satuan])
                ->first();

            if (! $su) {
                $su = SatuanUnit::create([
                    'kode' => $satuan,
                    'nama' => ucfirst($satuan),
                    'is_active' => true,
                ]);
            }

            $this->satuanCache[$satuan] = $su->kode;
        }

        return $this->satuanCache[$satuan];
    }

    public function resolveOrCreateBrand(?string $brandName): int
    {
        $brandName = trim((string) ($brandName ?? ''));
        if ($brandName === '' || in_array(strtolower($brandName), ['-', 'none', 'null', 'kosong'], true)) {
            $brandName = 'OEM';
        }
        if (! isset($this->brandCache[$brandName])) {
            $this->brandCache[$brandName] = Brand::firstOrCreate(
                ['nama' => $brandName],
                ['is_active' => true]
            )->id;
        }

        return $this->brandCache[$brandName];
    }

    public function resolveOrCreateKualitas(?string $kualitasRaw, ?int $brandId = null): int
    {
        $kualitasName = $this->normalizeKualitas(trim((string) ($kualitasRaw ?? '')));
        if ($kualitasName === '') {
            $kualitasName = 'Original';
        }
        $cacheKey = "{$brandId}:{$kualitasName}";
        if (! isset($this->kualitasCache[$cacheKey])) {
            $kualitasRow = KualitasProduk::where('nama', $kualitasName)
                ->where(function ($q) use ($brandId) {
                    $q->where('brand_id', $brandId)
                        ->orWhereNull('brand_id');
                })
                ->orderByRaw('brand_id IS NOT NULL DESC')
                ->first();

            if (! $kualitasRow) {
                $kualitasRow = KualitasProduk::firstOrCreate(
                    ['nama' => $kualitasName, 'brand_id' => $brandId],
                    ['is_active' => true]
                );
            }
            $this->kualitasCache[$cacheKey] = $kualitasRow->id;
        }

        return $this->kualitasCache[$cacheKey];
    }

    public function resolveOrCreateKategori(string $kategoriUtamaInput, string $subKategoriInput = ''): array
    {
        $kategoriUtamaInput = trim($kategoriUtamaInput) ?: 'Umum';
        $subKategoriInput = trim($subKategoriInput);

        $cacheKey = $kategoriUtamaInput.'|'.$subKategoriInput;
        if (! isset($this->kategoriCache[$cacheKey])) {
            $parent = KategoriProduk::where('nama', $kategoriUtamaInput)
                ->orWhere('slug', Str::slug($kategoriUtamaInput))
                ->first();

            if (! $parent) {
                $parent = KategoriProduk::create([
                    'nama' => $kategoriUtamaInput,
                    'slug' => Str::slug($kategoriUtamaInput),
                    'parent_id' => null,
                    'is_active' => true,
                ]);
            }

            if ($subKategoriInput !== '') {
                $child = KategoriProduk::where('parent_id', $parent->id)
                    ->where(function ($q) use ($subKategoriInput) {
                        $q->where('nama', $subKategoriInput)
                            ->orWhere('slug', Str::slug($subKategoriInput));
                    })->first();

                if (! $child) {
                    $existing = KategoriProduk::where('nama', $subKategoriInput)
                        ->orWhere('slug', Str::slug($subKategoriInput))
                        ->first();

                    if ($existing && (empty($existing->parent_id) || (int) $existing->parent_id === 0)) {
                        $existing->update(['parent_id' => $parent->id]);
                        $child = $existing;
                    } else {
                        $child = KategoriProduk::create([
                            'nama' => $subKategoriInput,
                            'parent_id' => $parent->id,
                            'is_active' => true,
                        ]);
                    }
                }
                $kategoriId = $child->id;
                $kategoriSimpan = $subKategoriInput;
            } else {
                $kategoriId = $parent->id;
                $kategoriSimpan = $kategoriUtamaInput;
            }

            $this->kategoriCache[$cacheKey] = [
                'id' => $kategoriId,
                'nama' => $kategoriSimpan,
            ];
        }

        return $this->kategoriCache[$cacheKey];
    }

    /**
     * Resolusi ID cabang default dengan in-memory cache untuk performa job antrian.
     */
    public function resolveCabangDefault(): int
    {
        if (isset($this->cabangCache['default'])) {
            return $this->cabangCache['default'];
        }

        return $this->cabangCache['default'] = (int) (Cabang::value('id') ?? 1);
    }

    /**
     * Resolusi gudang dari input import (ID numerik, kode, atau nama) atau buat baru jika belum ada.
     */
    public function resolveOrCreateGudang(?string $gudangRaw, ?int $cabangId = null): int
    {
        $raw = trim((string) $gudangRaw);
        $cabangId = $cabangId ?? session('cabang_id') ?? auth()->user()?->cabang_id ?? $this->resolveCabangDefault();

        if ($raw === '' || in_array(strtolower($raw), ['-', '0', 'none', 'null', 'kosong'], true)) {
            return $this->resolveOrCreateGudangDefault($cabangId);
        }

        $cacheKey = "{$cabangId}|{$raw}";
        if (isset($this->gudangCache[$cacheKey])) {
            return $this->gudangCache[$cacheKey];
        }

        if (is_numeric($raw)) {
            $g = Gudang::find((int) $raw);
            if ($g) {
                return $this->gudangCache[$cacheKey] = $g->id;
            }
        }

        $g = Gudang::where('cabang_id', $cabangId)
            ->where(function ($q) use ($raw) {
                $q->whereRaw('LOWER(kode) = ?', [strtolower($raw)])
                    ->orWhereRaw('LOWER(nama) = ?', [strtolower($raw)]);
            })
            ->first();

        if ($g) {
            return $this->gudangCache[$cacheKey] = $g->id;
        }

        if (strcasecmp($raw, 'Gudang Utama') === 0 || strcasecmp($raw, 'Gudang Pusat') === 0) {
            return $this->gudangCache[$cacheKey] = $this->resolveOrCreateGudangDefault($cabangId);
        }

        // Buat gudang baru jika belum terdaftar
        $cabang = Cabang::find($cabangId) ?? Cabang::first();
        if (! $cabang) {
            $cabang = Cabang::create([
                'nama' => 'Cabang Utama',
                'kode' => 'CBG-01',
                'is_pusat' => true,
                'is_active' => true,
            ]);
        }

        $baseKode = Str::upper(Str::slug($raw));
        if ($baseKode === '') {
            $baseKode = 'GDG';
        }
        $kodeUnik = $baseKode;
        $counter = 1;
        while (Gudang::where('kode', $kodeUnik)->exists()) {
            $kodeUnik = "{$baseKode}-{$counter}";
            $counter++;
        }

        $newGudang = Gudang::create([
            'cabang_id' => $cabang->id,
            'nama' => $raw,
            'kode' => $kodeUnik,
            'is_active' => true,
        ]);

        return $this->gudangCache[$cacheKey] = $newGudang->id;
    }

    /**
     * Resolusi gudang default atau buat baru jika sistem belum memiliki gudang (sebagai parent rak).
     */
    public function resolveOrCreateGudangDefault(?int $cabangId = null): int
    {
        $cabangId = $cabangId ?? session('cabang_id') ?? auth()->user()?->cabang_id ?? $this->resolveCabangDefault();

        if (isset($this->defaultGudangCache[$cabangId])) {
            return $this->defaultGudangCache[$cabangId];
        }

        $gudang = Gudang::where('cabang_id', $cabangId)->where('is_active', true)
            ->where(fn ($q) => $q->where('nama', 'like', '%Utama%')->orWhere('nama', 'like', '%Pusat%')->orWhere('kode', 'like', '%UTAMA%'))
            ->first()
            ?? Gudang::where('cabang_id', $cabangId)->where('is_active', true)->first();

        if (! $gudang) {
            $cabang = Cabang::find($cabangId) ?? Cabang::first();
            if (! $cabang) {
                $cabang = Cabang::create([
                    'nama' => 'Cabang Utama',
                    'kode' => 'CBG-01',
                    'is_pusat' => true,
                    'is_active' => true,
                ]);
            }

            $baseKode = $cabang->id > 1 ? "GDG-CBG{$cabang->id}-UTAMA" : 'GDG-UTAMA';
            $kodeUnik = $baseKode;
            $counter = 1;
            while (Gudang::where('kode', $kodeUnik)->exists()) {
                $kodeUnik = "{$baseKode}-{$counter}";
                $counter++;
            }

            $gudang = Gudang::create([
                'cabang_id' => $cabang->id,
                'nama' => 'Gudang Utama',
                'kode' => $kodeUnik,
                'is_active' => true,
            ]);
        }

        return $this->defaultGudangCache[$cabangId] = $gudang->id;
    }

    /**
     * Resolusi rak default bawaan sistem untuk gudang tertentu.
     */
    public function resolveOrCreateRakDefault(int $gudangId): int
    {
        $cacheKey = "{$gudangId}|__default__";
        if (isset($this->rakCache[$cacheKey])) {
            return $this->rakCache[$cacheKey];
        }

        $rak = Rak::where('gudang_id', $gudangId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('kode', 'RAK-01')
                    ->orWhere('nama', 'Rak Utama')
                    ->orWhere('nama', 'Rak Default')
                    ->orWhere('kode', 'like', 'RAK-DEF%')
                    ->orWhere('nama', 'Default');
            })->first();

        if (! $rak) {
            $rak = Rak::where('gudang_id', $gudangId)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();
        }

        if (! $rak) {
            $kodeUnik = "RAK-DEF-G{$gudangId}";
            $counter = 1;
            while (Rak::where('kode', $kodeUnik)->exists()) {
                $kodeUnik = "RAK-DEF-G{$gudangId}-{$counter}";
                $counter++;
            }

            $rak = Rak::create([
                'gudang_id' => $gudangId,
                'nama' => 'Rak Default',
                'kode' => $kodeUnik,
                'is_active' => true,
            ]);
        }

        return $this->rakCache[$cacheKey] = $rak->id;
    }

    /**
     * Resolusi lokasi rak atau buat baru secara aman tanpa bentrok unique constraint kode.
     * Jika nilai rak dikosongkan, otomatis dimasukkan ke rak default bawaan sistem.
     */
    public function resolveOrCreateRak(?string $rakRaw, ?int $gudangId = null): int
    {
        if (! $gudangId) {
            $cabangIdSession = session('cabang_id');
            $gudangId = $this->resolveOrCreateGudangDefault($cabangIdSession);
        }

        $raw = trim((string) $rakRaw);
        if ($raw === '' || in_array(strtolower($raw), ['-', '0', 'none', 'null', 'kosong'], true)) {
            return $this->resolveOrCreateRakDefault($gudangId);
        }

        $cacheKey = "{$gudangId}|{$raw}";
        if (isset($this->rakCache[$cacheKey])) {
            return $this->rakCache[$cacheKey];
        }

        // Cari dulu apakah rak sudah ada di gudang ini (berdasarkan id, nama, atau kode)
        $existingId = $this->resolveRakId($raw, $gudangId);
        if ($existingId) {
            $this->rakCache[$cacheKey] = $existingId;

            return $existingId;
        }

        // Buat rak baru dengan kode unik aman
        $baseKode = Str::upper(Str::slug($raw));
        if ($baseKode === '') {
            $baseKode = 'RAK';
        }
        $kodeUnik = $baseKode;
        $counter = 1;
        while (Rak::where('kode', $kodeUnik)->exists()) {
            $kodeUnik = "{$baseKode}-G{$gudangId}-{$counter}";
            $counter++;
            if ($counter > 50) {
                $kodeUnik = "{$baseKode}-".strtoupper(Str::random(5));
                break;
            }
        }

        $rakBaru = Rak::create([
            'gudang_id' => $gudangId,
            'nama' => $raw,
            'kode' => $kodeUnik,
            'is_active' => true,
        ]);

        $this->rakCache[$cacheKey] = $rakBaru->id;

        return $rakBaru->id;
    }

    public function colLetterToIndex(string $col): int
    {
        $col = strtoupper(trim($col));
        $len = strlen($col);
        $idx = 0;
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($col[$i]) - ord('A') + 1);
        }

        return $idx - 1;
    }

    public function indexToColLetter(int $idx): string
    {
        $letter = '';
        while ($idx >= 0) {
            $letter = chr(($idx % 26) + ord('A')).$letter;
            $idx = intdiv($idx, 26) - 1;
        }

        return $letter;
    }

    /**
     * Daftar seluruh kolom Master Produk yang didukung oleh sistem untuk pemetaan (custom mapping).
     *
     * @return array<string, array{label: string, required: bool, description: string, group: string}>
     */
    public function getMappableSystemColumns(): array
    {
        return [
            'sku' => [
                'label' => 'Kode SKU / Part Number',
                'required' => true,
                'description' => 'Kode unik produk. Jika tidak dipetakan, sistem akan membuatkan SKU otomatis.',
                'group' => 'Identitas Produk',
            ],
            'nama' => [
                'label' => 'Nama Produk / Sparepart',
                'required' => true,
                'description' => 'Nama lengkap produk yang akan ditampilkan di katalog, POS, dan nota.',
                'group' => 'Identitas Produk',
            ],
            'harga_jual' => [
                'label' => 'Harga Jual (Retail / Eceran)',
                'required' => true,
                'description' => 'Harga jual standar kasir/POS untuk pelanggan umum.',
                'group' => 'Harga & Nilai',
            ],
            'harga_beli' => [
                'label' => 'Harga Beli (Modal / HPP)',
                'required' => false,
                'description' => 'Harga pokok modal pembelian dari supplier/distributor.',
                'group' => 'Harga & Nilai',
            ],
            'harga_reseller' => [
                'label' => 'Harga Reseller',
                'required' => false,
                'description' => 'Harga khusus untuk tier reseller/bengkel mitra.',
                'group' => 'Harga & Nilai',
            ],
            'harga_agen' => [
                'label' => 'Harga Agen / Grosir',
                'required' => false,
                'description' => 'Harga khusus untuk pelanggan tier agen/distributor partai besar.',
                'group' => 'Harga & Nilai',
            ],
            'stok_awal' => [
                'label' => 'Stok Awal (Saldo Awal)',
                'required' => false,
                'description' => 'Jumlah stok awal fisik saat inisialisasi master produk.',
                'group' => 'Stok & Lokasi',
            ],
            'satuan' => [
                'label' => 'Satuan Unit (UOM)',
                'required' => false,
                'description' => 'Satuan dasar produk (pcs, box, unit, set). Default: pcs.',
                'group' => 'Identitas Produk',
            ],
            'barcode_1' => [
                'label' => 'Barcode Utama',
                'required' => false,
                'description' => 'Nomor barcode utama yang tercetak pada kemasan produk.',
                'group' => 'Barcode',
            ],
            'barcode_2' => [
                'label' => 'Barcode 2 (Alternatif)',
                'required' => false,
                'description' => 'Barcode sekunder atau kode supplier.',
                'group' => 'Barcode',
            ],
            'barcode_3' => [
                'label' => 'Barcode 3',
                'required' => false,
                'description' => 'Barcode tambahan ketiga.',
                'group' => 'Barcode',
            ],
            'multi_barcode' => [
                'label' => 'Multi Barcode (Pisahkan Koma)',
                'required' => false,
                'description' => 'Daftar barcode alternatif dipisahkan dengan koma.',
                'group' => 'Barcode',
            ],
            'kategori' => [
                'label' => 'Kategori Produk',
                'required' => false,
                'description' => 'Kategori utama sparepart (LCD, Baterai, Flexible, dll). Default: Umum.',
                'group' => 'Kategori & Brand',
            ],
            'sub_kategori' => [
                'label' => 'Sub Kategori',
                'required' => false,
                'description' => 'Sub kategori spesifik produk.',
                'group' => 'Kategori & Brand',
            ],
            'brand' => [
                'label' => 'Brand / Merk Produk',
                'required' => false,
                'description' => 'Merk sparepart (Crown, OG, Shine Star, dll).',
                'group' => 'Kategori & Brand',
            ],
            'kualitas' => [
                'label' => 'Kualitas / Grade',
                'required' => false,
                'description' => 'Tingkat kualitas part (Original, OEM, Grade A, Incell).',
                'group' => 'Kategori & Brand',
            ],
            'kondisi' => [
                'label' => 'Kondisi Produk',
                'required' => false,
                'description' => 'Status kondisi: baru, bekas, ref, atau oem. Default: baru.',
                'group' => 'Kategori & Brand',
            ],
            'supplier' => [
                'label' => 'Supplier / Pemasok',
                'required' => false,
                'description' => 'Nama supplier asal barang.',
                'group' => 'Kategori & Brand',
            ],
            'merk_hp' => [
                'label' => 'Merk Ponsel / Smartphone',
                'required' => false,
                'description' => 'Merk device kompatibel (Apple, Samsung, Xiaomi, Oppo, dll).',
                'group' => 'Kompatibilitas Smartphone',
            ],
            'tipe_hp' => [
                'label' => 'Tipe / Model HP Kompatibel',
                'required' => false,
                'description' => 'Model smartphone (cth: iPhone 13, iPhone 16 (A3287), Galaxy A54). Mendukung varian kode produk.',
                'group' => 'Kompatibilitas Smartphone',
            ],
            'kompatibilitas_hp' => [
                'label' => 'Kompatibilitas HP Bebas',
                'required' => false,
                'description' => 'Teks bebas model atau catatan kompabilitas perangkat.',
                'group' => 'Kompatibilitas Smartphone',
            ],
            'produk_kompatibel_sku' => [
                'label' => 'Produk Kompatibel (Cross-SKU)',
                'required' => false,
                'description' => 'SKU produk lain yang kompatibel atau dapat saling menggantikan.',
                'group' => 'Kompatibilitas Smartphone',
            ],
            'min_stock' => [
                'label' => 'Stok Minimum (Alert)',
                'required' => false,
                'description' => 'Batas minimum persediaan untuk memicu notifikasi restock.',
                'group' => 'Stok & Lokasi',
            ],
            'reorder_point' => [
                'label' => 'Reorder Point (ROP)',
                'required' => false,
                'description' => 'Titik kuantitas untuk segera melakukan pemesanan ulang ke supplier.',
                'group' => 'Stok & Lokasi',
            ],
            'gudang' => [
                'label' => 'Gudang Penyimpanan',
                'required' => false,
                'description' => 'Nama atau kode gudang penyimpanan.',
                'group' => 'Stok & Lokasi',
            ],
            'rak' => [
                'label' => 'Lokasi Rak / Bin',
                'required' => false,
                'description' => 'Nama atau nomor rak/bin lokasi penyimpanan fisik.',
                'group' => 'Stok & Lokasi',
            ],
            'sn' => [
                'label' => 'Lacak Serial Number (SN)',
                'required' => false,
                'description' => 'Apakah produk memerlukan pelacakan nomor seri unik (ya / tidak).',
                'group' => 'Stok & Lokasi',
            ],
            'konversi_satuan' => [
                'label' => 'Konversi Satuan Bertingkat',
                'required' => false,
                'description' => 'Multi satuan bertingkat (contoh: box:10:120000;pack:5:62000).',
                'group' => 'Identitas Produk',
            ],
            'deskripsi' => [
                'label' => 'Deskripsi / Keterangan',
                'required' => false,
                'description' => 'Keterangan lengkap atau spesifikasi teknis barang.',
                'group' => 'Lainnya',
            ],
            'foto_url' => [
                'label' => 'URL Foto Produk',
                'required' => false,
                'description' => 'Tautan gambar atau foto produk online.',
                'group' => 'Lainnya',
            ],
        ];
    }

    /**
     * Rekomendasi otomatis kolom sistem berdasarkan header yang terbaca dari file yang di-upload.
     */
    public function suggestSystemField(string $heading): string
    {
        $clean = strtolower(trim($heading));
        $clean = preg_replace('/\s*\(.*?\)\s*/', '', $clean) ?? $clean;
        $clean = preg_replace('/[^a-z0-9\s_-]/', '', $clean) ?? $clean;
        $norm = preg_replace('/[\s_-]+/', '_', trim($clean)) ?? '';

        $normalized = $this->normalizeImportHeading($heading);
        $mappable = array_keys($this->getMappableSystemColumns());

        if (in_array($normalized, $mappable, true)) {
            return $normalized;
        }

        return match (true) {
            str_contains($norm, 'sku') || str_contains($norm, 'part_no') || str_contains($norm, 'part_num') || str_contains($norm, 'kd_brg') || str_contains($norm, 'kdbrg') || str_contains($norm, 'kd_item') || str_contains($norm, 'kditem') || str_contains($norm, 'kd_produk') || str_contains($norm, 'kdproduk') || $norm === 'kode' || $norm === 'code' || $norm === 'item_code' || $norm === 'kode_barang' || $norm === 'kode_item' || $norm === 'kode_produk' || $norm === 'part_number' => 'sku',
            str_contains($norm, 'nama') || str_contains($norm, 'nm_brg') || str_contains($norm, 'product_name') || str_contains($norm, 'item_name') || str_contains($norm, 'deskripsi_barang') || str_contains($norm, 'nama_barang') || str_contains($norm, 'nama_item') || str_contains($norm, 'nama_produk') || str_contains($norm, 'sparepart') => 'nama',
            str_contains($norm, 'hjual') || str_contains($norm, 'h_jual') || str_contains($norm, 'jual') || str_contains($norm, 'sell') || str_contains($norm, 'retail') || str_contains($norm, 'eceran') || $norm === 'harga' || $norm === 'price' => 'harga_jual',
            str_contains($norm, 'hbeli') || str_contains($norm, 'h_beli') || str_contains($norm, 'modal') || str_contains($norm, 'hpp') || str_contains($norm, 'cost') || str_contains($norm, 'beli') || str_contains($norm, 'pokok') || str_contains($norm, 'kulakan') => 'harga_beli',
            str_contains($norm, 'reseller') || str_contains($norm, 'grosir') => 'harga_reseller',
            str_contains($norm, 'agen') || str_contains($norm, 'distributor') => 'harga_agen',
            str_contains($norm, 'stok') || str_contains($norm, 'stock') || str_contains($norm, 'qty') || str_contains($norm, 'jumlah') || str_contains($norm, 'saldo_awal') || str_contains($norm, 'kuantitas') => 'stok_awal',
            str_contains($norm, 'barcode_1') || $norm === 'barcode' || $norm === 'ean' || $norm === 'upc' || str_contains($norm, 'kode_bar') => 'barcode_1',
            str_contains($norm, 'barcode_2') => 'barcode_2',
            str_contains($norm, 'barcode_3') => 'barcode_3',
            str_contains($norm, 'multi_barcode') || str_contains($norm, 'barcode_alt') => 'multi_barcode',
            str_contains($norm, 'satuan') || str_contains($norm, 'uom') || $norm === 'unit' => 'satuan',
            str_contains($norm, 'sub_kat') || str_contains($norm, 'subkat') || str_contains($norm, 'sub_cat') => 'sub_kategori',
            str_contains($norm, 'kategori') || str_contains($norm, 'category') || str_contains($norm, 'kelompok') || str_contains($norm, 'golongan') => 'kategori',
            str_contains($norm, 'brand_hp') || str_contains($norm, 'merk_hp') || str_contains($norm, 'merek_hp') => 'merk_hp',
            str_contains($norm, 'brand') || str_contains($norm, 'merk') || str_contains($norm, 'merek') => 'brand',
            str_contains($norm, 'kualitas') || str_contains($norm, 'grade') || str_contains($norm, 'quality') => 'kualitas',
            str_contains($norm, 'kondisi') || str_contains($norm, 'condition') => 'kondisi',
            str_contains($norm, 'supplier') || str_contains($norm, 'pemasok') || str_contains($norm, 'vendor') => 'supplier',
            str_contains($norm, 'tipe_hp') || str_contains($norm, 'model_hp') || str_contains($norm, 'kompatibel') || str_contains($norm, 'device_model') || $norm === 'tipe' || $norm === 'model' => 'tipe_hp',
            str_contains($norm, 'gudang') || str_contains($norm, 'warehouse') => 'gudang',
            str_contains($norm, 'rak') || str_contains($norm, 'bin') || str_contains($norm, 'shelf') => 'rak',
            str_contains($norm, 'min_st') || str_contains($norm, 'stok_min') || str_contains($norm, 'min_qty') => 'min_stock',
            str_contains($norm, 'rop') || str_contains($norm, 'reorder') => 'reorder_point',
            str_contains($norm, 'sn') || str_contains($norm, 'serial') => 'sn',
            str_contains($norm, 'deskripsi') || str_contains($norm, 'keterangan') || str_contains($norm, 'catatan') || str_contains($norm, 'notes') || str_contains($norm, 'description') => 'deskripsi',
            str_contains($norm, 'foto') || str_contains($norm, 'gambar') || str_contains($norm, 'image') => 'foto_url',
            default => '',
        };
    }

    /**
     * Memeriksa apakah file yang di-upload adalah template bawaan sistem.
     * Jika BUKAN template bawaan, maka fitur Custom Import akan aktif.
     */
    public function isTemplateBawaan(array $rawHeadings): bool
    {
        if (empty($rawHeadings)) {
            return false;
        }

        $templateColumns = $this->templateColumns();
        $normalizedHeadings = array_map(fn ($h) => $this->normalizeImportHeading((string) $h), $rawHeadings);

        $wajib = ['sku', 'nama', 'harga_jual'];
        if (count(array_intersect($normalizedHeadings, $wajib)) < 3) {
            return false;
        }

        $first3 = array_slice($normalizedHeadings, 0, 3);
        if (! isset($first3[0], $first3[1]) || $first3[0] !== 'sku' || $first3[1] !== 'nama') {
            return false;
        }

        $matchCount = count(array_intersect($normalizedHeadings, $templateColumns));
        $ratio = $matchCount / max(1, count($normalizedHeadings));

        return $matchCount >= 10 && $ratio >= 0.6;
    }

    /**
     * Menginspeksi struktur file yang di-upload (header dan sampel nilai baris pertama).
     * Digunakan oleh Livewire untuk mendeteksi apakah file template bawaan atau bukan.
     */
    public function inspectFile(string $filePath): array
    {
        $filePath = $this->resolveFilePath($filePath);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($ext, ['csv', 'txt'], true)) {
            return $this->inspectCsvFile($filePath);
        }

        return $this->inspectXlsxFile($filePath);
    }

    protected function inspectCsvFile(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \Exception('Tidak dapat membuka file CSV: '.$filePath);
        }

        $sampel = '';
        $headerLine = '';
        $nomor = 0;
        while ($nomor < 10 && ($baris = fgets($handle)) !== false) {
            if ($nomor === 0) {
                $headerLine = $baris;
            } else {
                $sampel .= $baris;
            }
            $nomor++;
        }
        if (substr_count($sampel, ';') + substr_count($sampel, ',') === 0) {
            $sampel = $headerLine;
        }
        $delimiter = substr_count($sampel, ';') >= substr_count($sampel, ',') ? ';' : ',';
        rewind($handle);

        $rawHeadings = [];
        $sampleRows = [];
        $lineIdx = 0;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false && $lineIdx < 5) {
            if ($lineIdx === 0) {
                if (isset($data[0])) {
                    $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]) ?? $data[0];
                }
                $rawHeadings = $data;
            } else {
                if (count(array_filter($data, fn ($v) => $v !== '' && $v !== null)) > 0) {
                    $sampleRows[] = $data;
                }
            }
            $lineIdx++;
        }
        fclose($handle);

        return $this->buildInspectionResult($rawHeadings, $sampleRows);
    }

    protected function inspectXlsxFile(string $filePath): array
    {
        try {
            $zip = new ZipArchive;
            if ($zip->open($filePath) === true) {
                $sharedStrings = [];
                $sharedContent = $zip->getFromName('xl/sharedStrings.xml');
                if ($sharedContent !== false) {
                    $sReader = new XMLReader;
                    $sReader->XML($sharedContent);
                    while ($sReader->read()) {
                        if ($sReader->nodeType === XMLReader::ELEMENT && $sReader->name === 'si') {
                            $outer = $sReader->readOuterXml();
                            $xml = simplexml_load_string($outer);
                            if ($xml !== false) {
                                if (isset($xml->t)) {
                                    $sharedStrings[] = (string) $xml->t;
                                } elseif (isset($xml->r)) {
                                    $text = '';
                                    foreach ($xml->r as $r) {
                                        $text .= (string) $r->t;
                                    }
                                    $sharedStrings[] = $text;
                                } else {
                                    $sharedStrings[] = '';
                                }
                            }
                        }
                    }
                    $sReader->close();
                }

                $sheetCandidates = ['xl/worksheets/sheet1.xml'];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $stat['name']) && ! in_array($stat['name'], $sheetCandidates, true)) {
                        $sheetCandidates[] = $stat['name'];
                    }
                }

                foreach ($sheetCandidates as $candidateName) {
                    $content = $zip->getFromName($candidateName);
                    if ($content === false) {
                        continue;
                    }

                    $reader = new XMLReader;
                    $reader->XML($content);
                    $rawHeadings = [];
                    $sampleRows = [];
                    $rowNum = 0;

                    while ($reader->read()) {
                        if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'row') {
                            $rowXml = $reader->readOuterXml();
                            $cells = $this->parseRowCells($rowXml, $sharedStrings);
                            if ($rowNum === 0) {
                                $rawHeadings = $cells;
                            } elseif (! empty($cells) && count($sampleRows) < 3) {
                                $sampleRows[] = $cells;
                            }
                            $rowNum++;
                            if ($rowNum >= 6) {
                                break;
                            }
                        }
                    }
                    $reader->close();

                    if (! empty($rawHeadings)) {
                        $zip->close();

                        return $this->buildInspectionResult($rawHeadings, $sampleRows);
                    }
                }
                $zip->close();
            }
        } catch (\Throwable $e) {
            Log::info('inspectXlsxFile fast parser fallback: '.$e->getMessage());
        }

        $sheets = Excel::toCollection(null, $filePath, null, ExcelReader::XLSX);
        $first = $sheets->first() ?? collect();
        $rawHeadings = [];
        $sampleRows = [];
        foreach ($first->take(4) as $idx => $row) {
            $arr = is_array($row) ? $row : (method_exists($row, 'toArray') ? $row->toArray() : (array) $row);
            if ($idx === 0) {
                $rawHeadings = $arr;
            } else {
                $sampleRows[] = $arr;
            }
        }

        return $this->buildInspectionResult($rawHeadings, $sampleRows);
    }

    protected function buildInspectionResult(array $rawHeadings, array $sampleRows): array
    {
        $headings = [];
        $colIndex = 0;
        $rawList = [];

        foreach ($rawHeadings as $key => $headingVal) {
            $rawName = trim((string) $headingVal);
            $colLetter = is_string($key) && preg_match('/^[A-Z]+$/', $key) ? $key : $this->indexToColLetter($colIndex);
            $rawList[] = $rawName;

            $samples = [];
            foreach ($sampleRows as $sr) {
                $val = $sr[$key] ?? $sr[$colIndex] ?? null;
                if ($val !== null && $val !== '') {
                    $strVal = is_scalar($val) ? trim((string) $val) : '';
                    if ($strVal !== '' && ! in_array($strVal, $samples, true)) {
                        $samples[] = Str::limit($strVal, 40);
                    }
                }
            }

            $suggested = $this->suggestSystemField($rawName);

            $headings[] = [
                'index' => $colIndex,
                'col_letter' => $colLetter,
                'key' => 'col_'.$colIndex,
                'raw_name' => $rawName !== '' ? $rawName : ('Kolom '.$colLetter),
                'sample_values' => $samples,
                'suggested_mapping' => $suggested,
            ];

            $colIndex++;
        }

        $isTemplate = $this->isTemplateBawaan($rawList);

        return [
            'is_template' => $isTemplate,
            'headings' => $headings,
            'total_columns' => count($headings),
            'sample_count' => count($sampleRows),
        ];
    }

    /**
     * Resolusi kolom file ke field master produk sistem berdasarkan mapping yang dipilih user.
     * Kolom yang tidak dipetakan / bernilai kosong / abaikan akan menghasilkan null.
     */
    public function resolveTargetField(array $columnMapping, string $colLetter, int $colIndex, string $rawHeading): ?string
    {
        $key = 'col_'.$colIndex;
        $target = $columnMapping[$key]
            ?? $columnMapping[$colLetter]
            ?? $columnMapping[$colIndex]
            ?? $columnMapping[$rawHeading]
            ?? $columnMapping[$this->normalizeImportHeading($rawHeading)]
            ?? null;

        if ($target === null) {
            return null;
        }

        $target = trim((string) $target);
        if ($target === '' || in_array(strtolower($target), ['abaikan', 'skip', 'ignore', 'none', '-'], true)) {
            return null;
        }

        return $target;
    }

    /** Kolom template (urutan = heading export). */
    public function templateColumns(): array
    {
        return [
            'sku', 'nama', 'barcode_1', 'barcode_2', 'barcode_3', 'multi_barcode', 'satuan', 'konversi_satuan', 'kategori', 'sub_kategori', 'brand', 'kualitas', 'kondisi', 'supplier',
            'merk_hp', 'tipe_hp',
            'harga_beli', 'harga_jual', 'harga_reseller', 'harga_agen',
            'min_stock', 'reorder_point', 'sn',
            'stok_awal', 'gudang', 'rak',
            'produk_kompatibel_sku', 'kompatibilitas_hp', 'deskripsi', 'foto_url',
        ];
    }

    /** Contoh baris untuk sheet template. */
    public function contohBaris(): array
    {
        return [
            [
                'sku' => '00123-IP13-ORI', 'nama' => 'LCD iPhone 13 Original',
                'barcode_1' => '08991234500001',
                'barcode_2' => 'SPL-88291',
                'barcode_3' => 'SUP-99012',
                'multi_barcode' => 'ALT-QR-1301, ALT-QR-1302',
                'satuan' => 'pcs', 'konversi_satuan' => 'box:10:12000000;pack:5:6200000', 'kategori' => 'LCD & Touchscreen', 'sub_kategori' => 'Fullset Assembly', 'brand' => 'Crown',
                'kualitas' => 'Original', 'kondisi' => 'baru', 'supplier' => 'PT Supplier Jaya',
                'merk_hp' => 'Apple', 'tipe_hp' => 'iPhone 16 (A3287)',
                'harga_beli' => 850000, 'harga_jual' => 1250000,
                'harga_reseller' => 1050000, 'harga_agen' => 1000000, 'min_stock' => 5, 'reorder_point' => 10,
                'sn' => 'tidak', 'stok_awal' => 5, 'gudang' => 'Gudang Utama', 'rak' => 'Rak A1',
                'produk_kompatibel_sku' => '',
                'kompatibilitas_hp' => '',
                'deskripsi' => 'LCD assembly original untuk iPhone 13 warna hitam', 'foto_url' => '',
            ],
            [
                'sku' => 'BAT-SA54-ODM', 'nama' => 'Baterai Samsung A54 Grade A',
                'barcode_1' => '8991234500002',
                'barcode_2' => 'BAT-SAMS-A54-ALT',
                'barcode_3' => '',
                'multi_barcode' => '',
                'satuan' => 'pcs', 'konversi_satuan' => 'box:20:3200000', 'kategori' => 'Baterai', 'sub_kategori' => 'Baterai Tanam', 'brand' => 'OG',
                'kualitas' => 'Grade A', 'kondisi' => 'oem', 'supplier' => 'Supplier Umum',
                'merk_hp' => 'Samsung', 'tipe_hp' => 'Galaxy A54, Galaxy A34',
                'harga_beli' => 95000, 'harga_jual' => 175000,
                'harga_reseller' => 135000, 'harga_agen' => 130000, 'min_stock' => 10, 'reorder_point' => 20,
                'sn' => 'tidak', 'stok_awal' => 10, 'gudang' => 'Gudang Utama', 'rak' => 'Rak A2',
                'produk_kompatibel_sku' => 'LCD-IP13-ORI',
                'kompatibilitas_hp' => '',
                'deskripsi' => 'Baterai lithium ion Grade A kapasitas 5000mAh', 'foto_url' => '',
            ],
        ];
    }

    /**
     * Cari lokasi absolut file secara fleksibel (mendukung disk local Laravel 11/12/13
     * yang default root-nya di storage/app/private, maupun storage/app).
     */
    public function resolveFilePath(string $filePath): string
    {
        if (file_exists($filePath)) {
            return $filePath;
        }

        try {
            $diskPath = Storage::disk('local')->path($filePath);
            if (file_exists($diskPath)) {
                return $diskPath;
            }
        } catch (\Throwable) {
        }

        $storagePrivate = storage_path('app/private/'.$filePath);
        if (file_exists($storagePrivate)) {
            return $storagePrivate;
        }

        $storageApp = storage_path('app/'.$filePath);
        if (file_exists($storageApp)) {
            return $storageApp;
        }

        return $filePath;
    }

    /**
     * Baca baris produk dari sheet PERTAMA (heading case-insensitive), validasi header wajib.
     * Sheet lain (mis. "Petunjuk" di template) diabaikan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseRows(string $filePath, array $columnMapping = []): array
    {
        // Enforce lightweight streaming memory limit (bounded to 64MB)
        @ini_set('memory_limit', '64M');
        @set_time_limit(120);

        $filePath = $this->resolveFilePath($filePath);

        // CSV → fgetcsv langsung (TANPA PhpSpreadsheet — krusial utk RAM 1GB / server ringan).
        // xlsx/xls → Fast streaming XMLReader dahulu, fallback ke PhpSpreadsheet jika format tidak standar.
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($ext, ['csv', 'txt'], true)) {
            return $this->parseRowsCsv($filePath, $columnMapping);
        }

        try {
            return $this->parseRowsXlsxFast($filePath, $columnMapping);
        } catch (\Throwable $fastErr) {
            Log::info('Fast XLSX parser fallback to PhpSpreadsheet: '.$fastErr->getMessage());
        }

        $sheets = Excel::toCollection(null, $filePath, null, ExcelReader::XLSX);
        $first = $sheets->first() ?? collect();

        $rawHeadings = [];
        $rowPertama = $first->first();
        if ($rowPertama) {
            $arr = is_array($rowPertama) ? $rowPertama : (method_exists($rowPertama, 'toArray') ? $rowPertama->toArray() : (array) $rowPertama);
            $rawHeadings = array_values($arr);
        }

        $hasCustomMapping = ! empty(array_filter($columnMapping, fn ($v) => $v !== '' && $v !== null && $v !== 'abaikan'));

        if (! $hasCustomMapping) {
            $headings = array_map(fn ($h) => $this->normalizeImportHeading((string) $h), $rawHeadings);
            $wajib = ['sku', 'nama', 'harga_jual'];
            $ada = array_intersect($headings, $wajib);
            if (count($ada) < count($wajib)) {
                throw new \Exception(
                    'Format kolom tidak dikenali. Baris 1 harus berisi header: sku, nama, satuan, harga_beli, harga_jual, ... (unduh template untuk contoh).'
                );
            }
        }

        $rows = [];
        foreach ($first->slice(1) as $row) {
            $normalized = [];
            $rowArr = is_array($row) ? $row : (method_exists($row, 'toArray') ? $row->toArray() : (array) $row);
            $hasVal = false;

            if ($hasCustomMapping) {
                foreach ($rowArr as $i => $value) {
                    $rawHead = (string) ($rawHeadings[$i] ?? '');
                    $colLetter = $this->indexToColLetter((int) $i);
                    $targetField = $this->resolveTargetField($columnMapping, $colLetter, (int) $i, $rawHead);
                    if ($targetField) {
                        $val = is_scalar($value) ? trim((string) $value) : $value;
                        if ($val !== '' && $val !== null) {
                            $hasVal = true;
                        }
                        $valStr = is_scalar($val) ? trim((string) $val) : $val;
                        if ($targetField === 'sku') {
                            $valStr = $this->normalizeSku($valStr);
                        } elseif (str_starts_with($targetField, 'barcode') || $targetField === 'multi_barcode') {
                            $valStr = $this->normalizeBarcode($valStr);
                        }
                        $normalized[$targetField] = $valStr;
                    }
                }
            } else {
                foreach ($headings as $i => $heading) {
                    if ($heading === '') {
                        continue;
                    }
                    $value = $rowArr[$i] ?? null;
                    if ($value !== '' && $value !== null) {
                        $hasVal = true;
                    }
                    $valStr = is_scalar($value) ? trim((string) $value) : $value;
                    if ($heading === 'sku') {
                        $valStr = $this->normalizeSku($valStr);
                    } elseif (str_starts_with($heading, 'barcode') || $heading === 'multi_barcode') {
                        $valStr = $this->normalizeBarcode($valStr);
                    }
                    $normalized[$heading] = $valStr;
                }
            }

            // Baris kosong total → skip
            if (! $hasVal) {
                continue;
            }

            if ($hasCustomMapping && empty($normalized['sku']) && ! empty($normalized['nama'])) {
                $dummy = [];
                $normalized['sku'] = $this->generateFallbackSku($normalized['nama'], $dummy);
            }

            $rows[] = $normalized;
        }

        if (count($rows) > self::MAKS_BARIS) {
            throw new \Exception('File terlalu besar: maksimal '.self::MAKS_BARIS.' baris per import (RAM 1GB).');
        }

        return $rows;
    }

    /**
     * Parsing XLSX secara streaming dengan XMLReader & ZipArchive (sangat cepat & hemat RAM 1GB).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseRowsXlsxFast(string $filePath, array $columnMapping = []): array
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException("Gagal membuka file XLSX: {$filePath}");
        }

        // 1. Baca sharedStrings.xml
        $sharedStrings = [];
        $sharedContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedContent !== false) {
            $sReader = new XMLReader;
            $sReader->XML($sharedContent);
            while ($sReader->read()) {
                if ($sReader->nodeType === XMLReader::ELEMENT && $sReader->name === 'si') {
                    $outer = $sReader->readOuterXml();
                    $xml = simplexml_load_string($outer);
                    if ($xml !== false) {
                        if (isset($xml->t)) {
                            $sharedStrings[] = (string) $xml->t;
                        } elseif (isset($xml->r)) {
                            $text = '';
                            foreach ($xml->r as $r) {
                                $text .= (string) $r->t;
                            }
                            $sharedStrings[] = $text;
                        } else {
                            $sharedStrings[] = '';
                        }
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
            $sReader->close();
        }

        // 2. Cari sheet data
        $sheetCandidates = ['xl/worksheets/sheet1.xml'];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $stat['name']) && ! in_array($stat['name'], $sheetCandidates, true)) {
                $sheetCandidates[] = $stat['name'];
            }
        }

        $hasCustomMapping = ! empty(array_filter($columnMapping, fn ($v) => $v !== '' && $v !== null && $v !== 'abaikan'));
        $wajib = ['sku', 'nama', 'harga_jual'];
        $selectedHeaders = null;
        $selectedSheetContent = null;

        foreach ($sheetCandidates as $candidateName) {
            $content = $zip->getFromName($candidateName);
            if ($content === false) {
                continue;
            }

            // Cek baris pertama untuk headers
            $testReader = new XMLReader;
            $testReader->XML($content);
            $candidateHeaders = [];
            while ($testReader->read()) {
                if ($testReader->nodeType === XMLReader::ELEMENT && $testReader->name === 'row') {
                    $rowXml = $testReader->readOuterXml();
                    $cells = $this->parseRowCells($rowXml, $sharedStrings);
                    foreach ($cells as $colLetter => $val) {
                        $candidateHeaders[$colLetter] = (string) $val;
                    }
                    break;
                }
            }
            $testReader->close();

            if ($hasCustomMapping) {
                if (! empty($candidateHeaders)) {
                    $selectedHeaders = $candidateHeaders;
                    $selectedSheetContent = $content;
                    break;
                }
            } else {
                $candidateNorm = array_map(fn ($h) => $this->normalizeImportHeading((string) $h), $candidateHeaders);
                $ada = array_intersect(array_values($candidateNorm), $wajib);
                if (count($ada) >= count($wajib)) {
                    $selectedHeaders = $candidateNorm;
                    $selectedSheetContent = $content;
                    break;
                }
            }
        }

        $zip->close();

        if (! $selectedSheetContent || ! $selectedHeaders) {
            if ($hasCustomMapping) {
                throw new \RuntimeException('Sheet data di file XLSX kosong atau tidak dapat dibaca.');
            }
            throw new \RuntimeException('Sheet data dengan header wajib (sku, nama, harga_jual) tidak ditemukan');
        }

        // Build active map
        $activeMap = [];
        if ($hasCustomMapping) {
            $colIdx = 0;
            foreach ($selectedHeaders as $colLetter => $rawVal) {
                $targetField = $this->resolveTargetField($columnMapping, $colLetter, $colIdx, (string) $rawVal);
                if ($targetField) {
                    $activeMap[$colLetter] = $targetField;
                }
                $colIdx++;
            }
        } else {
            foreach ($selectedHeaders as $colLetter => $heading) {
                if ($heading !== '') {
                    $activeMap[$colLetter] = $heading;
                }
            }
        }

        // 3. Baca baris data
        $rows = [];
        $reader = new XMLReader;
        $reader->XML($selectedSheetContent);
        $isFirstRow = true;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'row') {
                if ($isFirstRow) {
                    $isFirstRow = false;

                    continue;
                }

                $rowXml = $reader->readOuterXml();
                $cells = $this->parseRowCells($rowXml, $sharedStrings);
                if (empty($cells)) {
                    continue;
                }

                $normalized = [];
                $hasVal = false;
                foreach ($activeMap as $colLetter => $targetField) {
                    $val = $cells[$colLetter] ?? null;
                    if ($val !== null && $val !== '') {
                        $hasVal = true;
                    }
                    $valStr = is_scalar($val) ? trim((string) $val) : $val;
                    if ($targetField === 'sku') {
                        $valStr = $this->normalizeSku($valStr);
                    } elseif (str_starts_with($targetField, 'barcode') || $targetField === 'multi_barcode') {
                        $valStr = $this->normalizeBarcode($valStr);
                    }
                    $normalized[$targetField] = $valStr;
                }

                if ($hasVal) {
                    if ($hasCustomMapping && empty($normalized['sku']) && ! empty($normalized['nama'])) {
                        $dummy = [];
                        $normalized['sku'] = $this->generateFallbackSku($normalized['nama'], $dummy);
                    }
                    $rows[] = $normalized;
                }
            }
        }
        $reader->close();

        if (count($rows) > self::MAKS_BARIS) {
            throw new \Exception('File terlalu besar: maksimal '.self::MAKS_BARIS.' baris per import (RAM 1GB).');
        }

        return $rows;
    }

    /**
     * Parse row element XML menjadi array [column_letters => cell_value]
     */
    protected function parseRowCells(string $rowXml, array &$sharedStrings): array
    {
        $cells = [];
        $reader = new XMLReader;
        $reader->XML($rowXml);

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'c') {
                $ref = $reader->getAttribute('r') ?? '';
                $type = $reader->getAttribute('t') ?? '';
                preg_match('/^([A-Z]+)/', $ref, $matches);
                $col = $matches[1] ?? '';

                $cellOuter = $reader->readOuterXml();
                $val = '';
                if ($cellOuter !== '') {
                    $xml = simplexml_load_string($cellOuter);
                    if ($xml !== false) {
                        if ($type === 's') {
                            $idx = (int) $xml->v;
                            $val = $sharedStrings[$idx] ?? '';
                        } elseif ($type === 'inlineStr') {
                            $val = (string) ($xml->is->t ?? '');
                        } else {
                            $val = (string) ($xml->v ?? '');
                        }
                    }
                }

                if ($col !== '') {
                    $cells[$col] = trim($val);
                }
            }
        }
        $reader->close();

        return $cells;
    }

    /**
     * Baca CSV langsung pakai fgetcsv — TANPA PhpSpreadsheet (krusial utk RAM 1GB / server ringan).
     * Delimiter: auto-detect ';' atau ',' ; BOM UTF-8 dibersihkan; header case-insensitive.
     *
     * @return array<int, array<string, string>>
     */
    protected function parseRowsCsv(string $filePath, array $columnMapping = []): array
    {
        // Bounded to 64MB streaming limit
        @ini_set('memory_limit', '64M');
        @set_time_limit(120);

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \Exception('Tidak dapat membuka file: '.$filePath);
        }

        // Deteksi delimiter dari sampel isi (bukan header) — template & Laplikasi pakai ';'
        $sampel = '';
        $headerLine = '';
        $nomor = 0;
        while ($nomor < 25 && ($baris = fgets($handle)) !== false) {
            if ($nomor === 0) {
                $headerLine = $baris;
            }
            if ($nomor >= 2) {
                $sampel .= $baris;
            }
            $nomor++;
        }
        // [B-10f] File cuma 1-2 baris data → sampel kosong → kedua hitungan 0 dan
        // delimiter selalu jatuh ke ';' (padahal penulis file bisa pakai ',').
        // Fallback ke baris header (selalu ada & zawali nama kolom koma/titik koma).
        if (substr_count($sampel, ';') + substr_count($sampel, ',') === 0) {
            $sampel = $headerLine;
        }
        $titikKoma = substr_count($sampel, ';');
        $koma = substr_count($sampel, ',');
        $delimiter = $titikKoma >= $koma ? ';' : ',';

        rewind($handle);

        $hasCustomMapping = ! empty(array_filter($columnMapping, fn ($v) => $v !== '' && $v !== null && $v !== 'abaikan'));
        $rows = [];
        $activeMap = [];
        $isHeading = true;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($isHeading) {
                // Bersihkan BOM UTF-8 di sel pertama
                if (isset($data[0])) {
                    $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]) ?? $data[0];
                }
                $isHeading = false;

                if ($hasCustomMapping) {
                    foreach ($data as $idx => $rawVal) {
                        $colLetter = $this->indexToColLetter((int) $idx);
                        $target = $this->resolveTargetField($columnMapping, $colLetter, (int) $idx, (string) $rawVal);
                        if ($target) {
                            $activeMap[$idx] = $target;
                        }
                    }
                } else {
                    $headings = array_map(fn ($h) => $this->normalizeImportHeading((string) $h), $data);
                    $wajib = ['sku', 'nama', 'harga_jual'];
                    $ada = array_intersect($headings, $wajib);
                    if (count($ada) < count($wajib)) {
                        fclose($handle);
                        throw new \Exception(
                            'Format kolom tidak dikenali. Baris 1 harus berisi header: sku, nama, satuan, harga_beli, harga_jual, ... (unduh template untuk contoh).'
                        );
                    }
                    foreach ($headings as $idx => $h) {
                        if ($h !== '') {
                            $activeMap[$idx] = $h;
                        }
                    }
                }

                continue;
            }

            $normalized = [];
            $hasVal = false;
            foreach ($activeMap as $idx => $targetField) {
                $value = $data[$idx] ?? '';
                if ($value !== '' && $value !== null) {
                    $hasVal = true;
                }
                $valStr = is_scalar($value) ? trim((string) $value) : $value;
                if ($targetField === 'sku') {
                    $valStr = $this->normalizeSku($valStr);
                } elseif (str_starts_with($targetField, 'barcode') || $targetField === 'multi_barcode') {
                    $valStr = $this->normalizeBarcode($valStr);
                }
                $normalized[$targetField] = $valStr;
            }

            // Baris kosong total → skip
            if (! $hasVal) {
                continue;
            }

            if ($hasCustomMapping && empty($normalized['sku']) && ! empty($normalized['nama'])) {
                $dummy = [];
                $normalized['sku'] = $this->generateFallbackSku($normalized['nama'], $dummy);
            }

            $rows[] = $normalized;
        }
        fclose($handle);

        if (count($rows) > self::MAKS_BARIS) {
            throw new \Exception('File terlalu besar: maksimal '.self::MAKS_BARIS.' baris per import (RAM 1GB).');
        }

        return $rows;
    }

    /**
     * Generate SKU unik otomatis ketika kolom SKU dikosongkan pada baris import.
     */
    public function generateFallbackSku(?string $nama, array &$konteks = []): string
    {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', (string) $nama);
        $prefix = strtoupper(substr($clean, 0, 6));
        if (strlen($prefix) < 3) {
            $prefix = 'PRD';
        }

        $candidate = $prefix.'-'.strtoupper(Str::random(6));
        $counter = 0;
        while (
            isset($konteks['skuDalamFile'][$candidate])
            || SkuVariant::where('sku', $candidate)->exists()
        ) {
            $candidate = $prefix.'-'.strtoupper(Str::random(6));
            $counter++;
            if ($counter > 50) {
                $candidate = 'SKU-'.time().'-'.strtoupper(Str::random(4));
                break;
            }
        }

        $konteks['skuDalamFile'][$candidate] = true;

        return $candidate;
    }

    /**
     * Generate SKU unik baru dari SKU yang terdeteksi duplikat (misal: SKU-V2, SKU-V3, SKU-DUP1).
     */
    public function generateUniqueSkuSuffix(string $sku, array $konteks = []): string
    {
        $base = trim($sku);
        if ($base === '') {
            $dummy = $konteks;

            return $this->generateFallbackSku(null, $dummy);
        }

        // Coba suffix -V2, -V3, dst
        for ($i = 2; $i <= 99; $i++) {
            $candidate = "{$base}-V{$i}";
            $inDb = isset($konteks['existingSkuDb'])
                ? isset($konteks['existingSkuDb'][strtolower($candidate)])
                : SkuVariant::where('sku', $candidate)->exists();
            if (! isset($konteks['skuDalamFile'][$candidate]) && ! $inDb) {
                return $candidate;
            }
        }

        return "{$base}-".strtoupper(Str::random(4));
    }

    /**
     * Deteksi apakah baris memiliki SKU atau Barcode yang duplikat (dengan database atau intra-file).
     */
    public function detectDuplikatRow(array $row, int $nomorBaris, array $konteks): ?array
    {
        $sku = trim((string) ($row['sku'] ?? ''));
        $barcode = $this->normalizeBarcode($row['barcode_1'] ?? $row['barcode'] ?? null);
        $nama = trim((string) ($row['nama'] ?? ''));

        $isSkuFile = $sku !== '' && isset($konteks['skuDalamFile'][$sku]);

        // Fast in-memory lookup if preloaded in $konteks, otherwise query DB
        $skuLower = strtolower($sku);
        $variantData = null;
        if ($sku !== '') {
            if (isset($konteks['existingSkuDb'])) {
                $variantData = $konteks['existingSkuDb'][$skuLower] ?? null;
            } else {
                $v = SkuVariant::with('produk')->where('sku', $sku)->first();
                if ($v) {
                    $variantData = [
                        'sku' => $v->sku,
                        'produk_id' => $v->produk_id,
                        'produk_nama' => $v->produk?->nama ?? '',
                        'barcode' => $v->barcode,
                    ];
                }
            }
        }
        $isSkuDb = $variantData !== null;

        $isBarcodeFile = $barcode !== '' && isset($konteks['barcodeDalamFile'][$barcode]);
        $isBarcodeDb = false;
        $barcodeTargetProdukId = null;
        $barcodeTargetProdukNama = null;
        $barcodeTargetSku = null;

        if ($barcode !== '') {
            if (isset($konteks['existingBarcodeDb'])) {
                $bData = $konteks['existingBarcodeDb'][$barcode] ?? null;
                if ($bData) {
                    $isBarcodeDb = true;
                    $barcodeTargetProdukId = $bData['produk_id'];
                    $barcodeTargetProdukNama = $bData['produk_nama'];
                    $barcodeTargetSku = $bData['sku'] ?? null;
                }
            } else {
                $variantBarcode = SkuVariant::with('produk')->where('barcode', $barcode)->first();
                if ($variantBarcode) {
                    $isBarcodeDb = true;
                    $barcodeTargetProdukId = $variantBarcode->produk_id;
                    $barcodeTargetProdukNama = $variantBarcode->produk?->nama ?? '';
                    $barcodeTargetSku = $variantBarcode->sku;
                } else {
                    $produkBarcode = Produk::where('barcode', $barcode)->first();
                    if ($produkBarcode) {
                        $isBarcodeDb = true;
                        $barcodeTargetProdukId = $produkBarcode->id;
                        $barcodeTargetProdukNama = $produkBarcode->nama;
                    }
                }
            }
        }

        if (! $isSkuFile && ! $isSkuDb && ! $isBarcodeFile && ! $isBarcodeDb) {
            return null;
        }

        $tipe = ($isSkuDb || $isSkuFile) ? 'sku' : 'barcode';
        $lokasi = ($isSkuDb || $isBarcodeDb) ? 'database' : 'intra_file';

        $targetProdukId = $variantData['produk_id'] ?? $barcodeTargetProdukId ?? null;
        $targetProdukNama = $variantData['produk_nama'] ?? $barcodeTargetProdukNama ?? null;
        $targetProdukSku = $variantData['sku'] ?? $barcodeTargetSku ?? null;

        $targetBaris = null;
        if ($lokasi === 'intra_file') {
            if ($tipe === 'sku' && $sku !== '' && isset($konteks['skuBarisMap'][$sku])) {
                $targetBaris = $konteks['skuBarisMap'][$sku]['baris'];
                $targetProdukNama = $konteks['skuBarisMap'][$sku]['nama'];
            } elseif ($tipe === 'barcode' && $barcode !== '' && isset($konteks['barcodeBarisMap'][$barcode])) {
                $targetBaris = $konteks['barcodeBarisMap'][$barcode]['baris'];
                $targetProdukNama = $konteks['barcodeBarisMap'][$barcode]['nama'];
                $targetProdukSku = $konteks['barcodeBarisMap'][$barcode]['sku'];
            } elseif (! empty($konteks['namaDalamFile'])) {
                foreach ($konteks['namaDalamFile'] as $prev) {
                    if ($sku !== '' && strcasecmp($prev['sku'], $sku) === 0) {
                        $targetBaris = $prev['baris'];
                        $targetProdukNama = $prev['nama'];
                        break;
                    }
                }
            }
        }

        $skuRekomendasi = $this->generateUniqueSkuSuffix($sku, $konteks);

        $duplikatValue = $tipe === 'sku' ? $sku : $barcode;
        $ket = match ($lokasi) {
            'database' => "{$tipe} '{$duplikatValue}' sudah terdaftar di master produk database".($targetProdukNama ? " ({$targetProdukNama})" : '').'.',
            default => "{$tipe} '{$duplikatValue}' duplikat dengan baris ".($targetBaris ?? '-').' pada file import ini.',
        };

        return [
            'baris' => $nomorBaris,
            'sku' => $sku,
            'sku_rekomendasi' => $skuRekomendasi,
            'sku_baru' => $skuRekomendasi,
            'barcode' => $barcode,
            'nama' => $nama,
            'tipe' => $tipe,
            'is_sku_duplikat' => ($isSkuDb || $isSkuFile),
            'is_barcode_duplikat' => ($isBarcodeDb || $isBarcodeFile),
            'lokasi' => $lokasi,
            'target_produk_id' => $targetProdukId,
            'target_produk_nama' => $targetProdukNama,
            'target_produk_sku' => $targetProdukSku,
            'target_baris' => $targetBaris,
            'default_aksi' => $tipe === 'barcode' ? 'kosongkan_barcode' : 'ganti_sku',
            'keterangan' => $ket,
        ];
    }

    /**
     * Validasi satu baris → daftar pesan error (kosong = valid).
     */
    public function validateRow(array $row, array $konteks): array
    {
        $errors = [];
        $sku = trim((string) ($row['sku'] ?? ''));
        $barcode = $this->normalizeBarcode($row['barcode_1'] ?? $row['barcode'] ?? null);
        $nama = trim((string) ($row['nama'] ?? ''));

        if (! $sku) {
            $errors[] = 'SKU wajib diisi';
        } else {
            $skuExistsInDb = isset($konteks['existingSkuDb'])
                ? isset($konteks['existingSkuDb'][strtolower($sku)])
                : SkuVariant::where('sku', $sku)->exists();
            if (isset($konteks['skuDalamFile'][$sku]) || $skuExistsInDb) {
                $errors[] = "SKU '{$sku}' duplikat (sudah ada di file atau database)";
            }
        }
        if ($barcode) {
            $barcodeExistsInDb = isset($konteks['existingBarcodeDb'])
                ? isset($konteks['existingBarcodeDb'][$barcode])
                : (SkuVariant::where('barcode', $barcode)->exists() || Produk::where('barcode', $barcode)->exists());
            if (isset($konteks['barcodeDalamFile'][$barcode]) || $barcodeExistsInDb) {
                $errors[] = "Barcode '{$barcode}' duplikat (sudah ada di file atau database)";
            }
        }
        if (! $nama) {
            $errors[] = 'Nama produk wajib diisi';
        }

        $satuanRaw = trim((string) ($row['satuan'] ?? ''));
        if (! $satuanRaw || in_array(strtolower($satuanRaw), ['-', 'none', 'null', 'kosong'], true)) {
            $satuan = 'pcs';
        } else {
            $satuan = $this->normalizeSatuan($satuanRaw);
            if ($satuan === '') {
                $satuan = 'pcs';
            }
        }

        $hargaBeli = $row['harga_beli'] ?? '';
        $hargaJual = $row['harga_jual'] ?? '';
        if ($hargaBeli === '' || $hargaBeli === '-' || $hargaBeli === null) {
            $hargaBeli = 0;
        }
        if ($hargaJual === '' || $hargaJual === '-' || $hargaJual === null) {
            $hargaJual = 0;
        }
        if (! is_numeric($hargaBeli)) {
            $errors[] = 'Harga beli harus angka';
        } elseif ((float) $hargaBeli < 0) {
            $errors[] = 'Harga beli tidak boleh negatif';
        }
        if (! is_numeric($hargaJual)) {
            $errors[] = 'Harga jual harus angka';
        } elseif ((float) $hargaJual < 0) {
            $errors[] = 'Harga jual tidak boleh negatif';
        }

        // [MARGIN GUARD] Deteksi harga jual di bawah HPP modal saat import master produk
        if (is_numeric($hargaBeli) && is_numeric($hargaJual)) {
            $fBeli = (float) $hargaBeli;
            $fJual = (float) $hargaJual;
            if ($fBeli > 0 && $fJual > 0 && $fJual < $fBeli) {
                $marginGuard = app(MarginGuardService::class);
                $rekomendasi = $marginGuard->hitungHargaAman($fBeli, $row['kategori'] ?? null);
                $errors[] = sprintf(
                    'Harga jual (Rp %s) lebih rendah dari modal/HPP (Rp %s) — potensi rugi langsung! Rekomendasi harga aman: Rp %s.',
                    number_format($fJual, 0, ',', '.'),
                    number_format($fBeli, 0, ',', '.'),
                    number_format($rekomendasi, 0, ',', '.')
                );
            }
        }

        $stokAwalRaw = $row['stok_awal'] ?? '';
        $stokAwal = ($stokAwalRaw === '' || $stokAwalRaw === '-' || $stokAwalRaw === null) ? 0 : $stokAwalRaw;
        if (! is_numeric($stokAwal)) {
            $errors[] = 'Stok awal harus angka';
        } elseif ((float) $stokAwal < 0) {
            $errors[] = 'Stok awal tidak boleh negatif';
        }

        $gudangRaw = trim((string) ($row['gudang'] ?? $row['gudang_id'] ?? ''));
        $gudangKosong = $gudangRaw === '' || in_array(strtolower($gudangRaw), ['-', '0', 'none', 'null', 'kosong'], true);
        if ((float) $stokAwal > 0 && $gudangKosong) {
            $errors[] = 'gudang_id wajib diisi bila stok_awal > 0';
        }

        return $errors;
    }

    /**
     * Preload SKU dan Barcode dari database sekaligus untuk mencegah N-queries per baris.
     */
    public function preloadExistingDb(array $rows): array
    {
        $fileSkus = [];
        $fileBarcodes = [];
        foreach ($rows as $r) {
            $s = trim((string) ($r['sku'] ?? ''));
            if ($s !== '') {
                $fileSkus[strtolower($s)] = $s;
            }
            $b = $this->normalizeBarcode($r['barcode_1'] ?? $r['barcode'] ?? null);
            if ($b !== '') {
                $fileBarcodes[$b] = $b;
            }
        }

        $existingSkuDb = [];
        if (! empty($fileSkus)) {
            foreach (array_chunk(array_values($fileSkus), 1000) as $skuChunk) {
                $variants = SkuVariant::with('produk:id,nama')
                    ->whereIn('sku', $skuChunk)
                    ->get(['id', 'produk_id', 'sku', 'barcode']);
                foreach ($variants as $v) {
                    $existingSkuDb[strtolower($v->sku)] = [
                        'sku' => $v->sku,
                        'produk_id' => $v->produk_id,
                        'produk_nama' => $v->produk?->nama ?? '',
                        'barcode' => $v->barcode,
                    ];
                }
            }
        }

        $existingBarcodeDb = [];
        if (! empty($fileBarcodes)) {
            foreach (array_chunk(array_values($fileBarcodes), 1000) as $barcodeChunk) {
                $variants = SkuVariant::with('produk:id,nama')
                    ->whereIn('barcode', $barcodeChunk)
                    ->get(['id', 'produk_id', 'sku', 'barcode']);
                foreach ($variants as $v) {
                    $existingBarcodeDb[$v->barcode] = [
                        'sku' => $v->sku,
                        'produk_id' => $v->produk_id,
                        'produk_nama' => $v->produk?->nama ?? '',
                    ];
                }
                $produks = Produk::whereIn('barcode', $barcodeChunk)->get(['id', 'nama', 'barcode']);
                foreach ($produks as $p) {
                    if (! isset($existingBarcodeDb[$p->barcode])) {
                        $existingBarcodeDb[$p->barcode] = [
                            'sku' => null,
                            'produk_id' => $p->id,
                            'produk_nama' => $p->nama,
                        ];
                    }
                }
            }
        }

        return [$existingSkuDb, $existingBarcodeDb];
    }

    /**
     * Preview (dry-run): validasi seluruh baris, tanpa mengubah data.
     *
     * [B-10f/P1-5] Validasi lintas cabang dijalankan di preview (fail fast)
     * supaya user tahu SEBELUM commit.
     */
    public function preview(string $filePath, int $threshold = self::THRESHOLD_PENCOCOKAN, array $resolusiDuplikat = [], array $resolusiBarcode = [], array $resolusiErrorBaris = [], ?int $cabangId = null, array $columnMapping = [], ?int $defaultGudangId = null): array
    {
        $rows = $this->parseRows($filePath, $columnMapping);
        $cabangId = $cabangId ?? session('cabang_id') ?? auth()->user()?->cabang_id;
        // [B-10f] File lintas cabang ditolak (tidak ada mutasi stok/jurnal yg bisa dicocokkan)
        $this->cabangTunggalDariRows($rows, $cabangId);

        [$existingSkuDb, $existingBarcodeDb] = $this->preloadExistingDb($rows);
        $konteks = [
            'skuDalamFile' => [],
            'barcodeDalamFile' => [],
            'namaDalamFile' => [],
            'skuBarisMap' => [],
            'barcodeBarisMap' => [],
            'existingSkuDb' => $existingSkuDb,
            'existingBarcodeDb' => $existingBarcodeDb,
            'skipFuzzy' => count($rows) > 300,
        ];

        // Collect unique reference values for smart preview
        $refValues = [
            'kategori' => [],
            'sub_kategori' => [],
            'brand' => [],
            'supplier' => [],
            'merk_hp' => [],
            'tipe_hp' => [], // array of ['merk' => ..., 'model' => ...]
            'kualitas' => [],
            'satuan' => [],
            'gudang' => [],
            'rak' => [],
        ];

        $hasil = [];
        $kemiripanRows = [];
        $duplikatRows = [];
        $valid = 0;
        $invalid = 0;
        $totalPeringatan = 0;
        foreach ($rows as $i => $row) {
            $nomorBaris = $i + 2; // +1 heading
            $skuRaw = trim((string) ($row['sku'] ?? ''));
            $barcodeRaw = $this->normalizeBarcode($row['barcode_1'] ?? $row['barcode'] ?? null);

            $resErr = $resolusiErrorBaris[$nomorBaris] ?? ($skuRaw !== '' ? ($resolusiErrorBaris[$skuRaw] ?? null) : null);
            $isErrorSkipped = $resErr === 'skip';

            $resBc = $resolusiBarcode[$nomorBaris] ?? ($barcodeRaw !== '' ? ($resolusiBarcode[$barcodeRaw] ?? null) : null);
            $aksiBc = is_array($resBc) ? ($resBc['aksi'] ?? null) : $resBc;
            $barcodeBaru = is_array($resBc) ? trim((string) ($resBc['barcode_baru'] ?? '')) : '';

            $resDup = $resolusiDuplikat[$nomorBaris] ?? ($skuRaw !== '' ? ($resolusiDuplikat[$skuRaw] ?? null) : null);
            $aksiDup = is_array($resDup) ? ($resDup['aksi'] ?? null) : $resDup;
            $skuBaru = is_array($resDup) ? trim((string) ($resDup['sku_baru'] ?? '')) : '';

            $rowValidasi = $row;
            if ($defaultGudangId && empty($rowValidasi['gudang']) && (float) ($rowValidasi['stok_awal'] ?? 0) > 0) {
                $rowValidasi['gudang'] = (string) $defaultGudangId;
            }
            if ($aksiDup === 'ganti_sku' && $skuBaru !== '') {
                $rowValidasi['sku'] = $skuBaru;
            }
            if ($aksiBc === 'kosongkan_barcode' || $aksiDup === 'kosongkan_barcode') {
                $rowValidasi['barcode'] = null;
                $rowValidasi['barcode_1'] = null;
            } elseif ($aksiBc === 'ganti_barcode' && $barcodeBaru !== '') {
                $rowValidasi['barcode'] = $barcodeBaru;
                $rowValidasi['barcode_1'] = $barcodeBaru;
            }

            $errors = $this->validateRow($rowValidasi, $konteks);
            if ($aksiDup === 'timpa_update') {
                $errors = array_values(array_filter($errors, fn ($err) => ! str_contains($err, 'duplikat')));
            } elseif (in_array($aksiDup, ['ganti_sku', 'skip'], true)) {
                $errors = array_values(array_filter($errors, fn ($err) => ! str_contains($err, 'duplikat') || ! str_contains($err, 'SKU')));
            }
            if ($aksiBc === 'kosongkan_barcode' || $aksiDup === 'kosongkan_barcode') {
                $errors = array_values(array_filter($errors, fn ($err) => ! str_contains($err, 'Barcode')));
            }
            if ($isErrorSkipped) {
                $errors = [];
            }

            $warnings = $this->checkRowWarnings($row, $nomorBaris, $konteks, $threshold);
            $kemiripan = $this->detectKemiripanRow($row, $nomorBaris, $konteks, $threshold);
            $duplikat = $this->detectDuplikatRow($row, $nomorBaris, $konteks);
            if ($kemiripan) {
                $kemiripanRows[] = $kemiripan;
            }
            if ($duplikat) {
                $duplikatRows[] = $duplikat;
            }
            $this->catatKonteks($rowValidasi, $nomorBaris, $konteks);
            if ($errors) {
                $invalid++;
            } else {
                $valid++;
            }
            if (! empty($warnings)) {
                $totalPeringatan++;
            }
            $hasil[] = [
                'baris' => $nomorBaris,
                'sku' => trim((string) ($rowValidasi['sku'] ?? '')),
                'nama' => trim((string) ($row['nama'] ?? '')),
                'valid' => empty($errors),
                'errors' => $errors,
                'warnings' => $warnings,
                'is_skipped' => $isErrorSkipped,
            ];

            // Collect reference values
            $kat = trim((string) ($row['kategori'] ?? 'Umum'));
            if ($kat !== '' && $kat !== 'Umum') {
                $refValues['kategori'][$kat] = true;
            }
            $subKat = trim((string) ($row['sub_kategori'] ?? ''));
            if ($subKat !== '') {
                $refValues['sub_kategori'][$subKat] = true;
            }
            $brand = trim((string) ($row['brand'] ?? ''));
            if ($brand !== '') {
                $refValues['brand'][$brand] = true;
            }
            $supplier = trim((string) ($row['supplier'] ?? ''));
            if ($supplier !== '' && ! in_array(strtolower($supplier), ['-', '0', 'none', 'null', 'kosong'], true)) {
                $refValues['supplier'][$supplier] = true;
            }
            $merkHpNorm = $this->normalizeMerkHp($row['merk_hp'] ?? null);
            $parsedTipe = $this->parseTipeHp($row['tipe_hp'] ?? null, $merkHpNorm);
            $parsedKompat = $this->parseKompatibilitas($row['kompatibilitas_hp'] ?? null, $merkHpNorm);
            $allTipeBaris = $this->gabungDaftarTipe($parsedTipe, $parsedKompat);

            foreach ($allTipeBaris as $pair) {
                if (! empty($pair['merk'])) {
                    $refValues['merk_hp'][$pair['merk']] = true;
                    if (! empty($pair['model'])) {
                        $refValues['tipe_hp'][] = ['merk' => $pair['merk'], 'model' => $pair['model']];
                    }
                }
            }
            $kualitas = trim((string) ($row['kualitas'] ?? ''));
            if ($kualitas !== '') {
                $refValues['kualitas'][$kualitas] = true;
            }
            $satuan = $this->normalizeSatuan(trim((string) ($row['satuan'] ?? 'pcs')));
            if ($satuan !== '') {
                $refValues['satuan'][$satuan] = true;
            }
            $gudangVal = trim((string) ($row['gudang'] ?? $row['gudang_id'] ?? ''));
            if ($gudangVal !== '' && ! in_array(strtolower($gudangVal), ['-', '0', 'none', 'null', 'kosong'], true)) {
                $refValues['gudang'][$gudangVal] = true;
            }
            $rakVal = trim((string) ($row['rak'] ?? $row['rak_id'] ?? ''));
            if ($rakVal !== '' && ! in_array(strtolower($rakVal), ['-', '0', 'none', 'null', 'kosong'], true)) {
                $refValues['rak'][$rakVal] = true;
            } else {
                $refValues['rak']['Rak Default'] = true;
            }
        }

        // Build referensi_summary: check DB for each unique value
        $referensiSummary = $this->buildReferensiSummary($refValues);

        $konfirmasi = [];
        foreach (['brand', 'supplier', 'kategori', 'sub_kategori', 'kualitas', 'satuan'] as $tipe) {
            foreach ($referensiSummary[$tipe]['new'] ?? [] as $nilai) {
                $label = match ($tipe) {
                    'sub_kategori' => 'Sub Kategori',
                    'kategori' => 'Kategori Utama',
                    default => ucfirst($tipe),
                };
                $konfirmasi[] = [
                    'tipe' => $tipe,
                    'nilai' => $nilai,
                    'aksi' => 'auto_create',
                    'keterangan' => "{$label} '{$nilai}' belum ada di sistem — akan dibuat otomatis saat import.",
                ];
            }
        }
        foreach ($referensiSummary['rak']['new'] ?? [] as $nilai) {
            $konfirmasi[] = [
                'tipe' => 'rak',
                'nilai' => $nilai,
                'aksi' => 'auto_create',
                'keterangan' => "Rak '{$nilai}' belum ada di sistem — akan didaftarkan otomatis ke gudang terkait.",
            ];
        }
        if (! Gudang::exists()) {
            $konfirmasi[] = [
                'tipe' => 'gudang',
                'nilai' => 'Gudang Utama',
                'aksi' => 'auto_create',
                'keterangan' => 'Belum ada gudang di sistem — Gudang Utama default akan dibuat otomatis sebagai parent lokasi rak dan inventori.',
            ];
        }
        foreach ($referensiSummary['gudang']['new'] ?? [] as $nilai) {
            $konfirmasi[] = [
                'tipe' => 'gudang',
                'nilai' => $nilai,
                'aksi' => 'auto_create',
                'keterangan' => "Gudang '{$nilai}' belum ada di sistem — akan dibuat otomatis saat import.",
            ];
        }

        $statusPreview = $invalid === 0
            ? ($totalPeringatan > 0 ? 'perlu_perhatian' : 'sempurna')
            : ($valid > 0 ? 'sebagian' : 'gagal');

        $labelStatus = match ($statusPreview) {
            'sempurna' => 'Diterima Sempurna (100% Valid)',
            'perlu_perhatian' => 'Valid — Ada Perhatian Khusus Kompatibilitas',
            'sebagian' => 'Valid Sebagian (Ada Baris Error)',
            default => 'Format Tidak Valid (Gagal Total)',
        };

        return [
            'total_baris' => count($rows),
            'valid' => $valid,
            'invalid' => $invalid,
            'total_peringatan' => $totalPeringatan,
            'status_preview' => $statusPreview,
            'label_status' => $labelStatus,
            'sampel' => array_slice($hasil, 0, 5),
            // Rincian seluruh baris error satu per satu (tanpa dipotong agar pengguna dapat memperbaiki semua error)
            'error_rows' => array_values(array_map(fn ($h) => [
                'baris' => $h['baris'],
                'sku' => $h['sku'],
                'nama' => $h['nama'],
                'errors' => $h['errors'],
            ], array_filter($hasil, fn ($h) => ! $h['valid']))),
            'total_error_rows' => $invalid,
            'warning_rows' => array_slice(array_values(array_filter($hasil, fn ($h) => ! empty($h['warnings']))), 0, 10),
            'total_warning_rows' => $totalPeringatan,
            'kemiripan_rows' => $kemiripanRows,
            'total_kemiripan_rows' => count($kemiripanRows),
            'duplikat_rows' => $duplikatRows,
            'total_duplikat_rows' => count($duplikatRows),
            'threshold_kemiripan' => $threshold,
            'referensi_summary' => $referensiSummary,
            'konfirmasi' => array_slice($konfirmasi, 0, 20),
            'total_konfirmasi' => count($konfirmasi),
        ];
    }

    /**
     * Build referensi summary for smart import preview.
     * Returns existing/new split for each reference type.
     */
    public function buildReferensiSummary(array $refValues): array
    {
        $summary = [
            'kategori' => ['existing' => [], 'new' => []],
            'sub_kategori' => ['existing' => [], 'new' => []],
            'brand' => ['existing' => [], 'new' => []],
            'supplier' => ['existing' => [], 'new' => []],
            'merk_hp' => ['existing' => [], 'new' => []],
            'tipe_hp' => ['existing' => [], 'new' => []],
            'kualitas' => ['existing' => [], 'new' => []],
            'satuan' => ['existing' => [], 'new' => []],
            'gudang' => ['existing' => [], 'new' => []],
            'rak' => ['existing' => [], 'new' => []],
        ];

        // Kategori: check by nama or slug (parent)
        foreach (array_keys($refValues['kategori']) as $kat) {
            $exists = KategoriProduk::where('nama', $kat)->orWhere('slug', Str::slug($kat))->exists();
            $summary['kategori'][$exists ? 'existing' : 'new'][] = $kat;
        }

        // Sub Kategori: check by nama or slug
        if (! empty($refValues['sub_kategori'])) {
            foreach (array_keys($refValues['sub_kategori']) as $sub) {
                $exists = KategoriProduk::where('nama', $sub)->orWhere('slug', Str::slug($sub))->exists();
                $summary['sub_kategori'][$exists ? 'existing' : 'new'][] = $sub;
            }
        }

        // Brand: check by nama
        if (! empty($refValues['brand'])) {
            $brandList = array_keys($refValues['brand']);
            $existingBrands = Brand::whereIn('nama', $brandList)->pluck('nama')->map(fn ($v) => strtolower($v))->flip()->all();
            foreach ($brandList as $brand) {
                $exists = isset($existingBrands[strtolower($brand)]);
                $summary['brand'][$exists ? 'existing' : 'new'][] = $brand;
            }
        }

        // Supplier: check by nama
        if (! empty($refValues['supplier'])) {
            $supList = array_keys($refValues['supplier']);
            $existingSups = Supplier::whereIn('nama', $supList)->pluck('nama')->map(fn ($v) => strtolower($v))->flip()->all();
            foreach ($supList as $sup) {
                $exists = isset($existingSups[strtolower($sup)]);
                $summary['supplier'][$exists ? 'existing' : 'new'][] = $sup;
            }
        }

        // Merk HP: check TipeHp distinct merk
        foreach (array_keys($refValues['merk_hp']) as $merk) {
            $exists = TipeHp::where('merk', $merk)->where('is_active', true)->exists();
            $summary['merk_hp'][$exists ? 'existing' : 'new'][] = $merk;
        }

        // Tipe HP: check merk+model pairs
        foreach ($refValues['tipe_hp'] as $pair) {
            $exists = TipeHp::where('merk', $pair['merk'])->where('model', $pair['model'])->where('is_active', true)->exists();
            $key = $pair['merk'].':'.$pair['model'];
            $summary['tipe_hp'][$exists ? 'existing' : 'new'][] = $key;
        }

        // Kualitas: check by nama (global)
        if (! empty($refValues['kualitas'])) {
            $kualList = array_keys($refValues['kualitas']);
            $existingKuals = KualitasProduk::whereIn('nama', $kualList)->pluck('nama')->map(fn ($v) => strtolower($v))->flip()->all();
            foreach ($kualList as $kual) {
                $exists = isset($existingKuals[strtolower($kual)]);
                $summary['kualitas'][$exists ? 'existing' : 'new'][] = $kual;
            }
        }

        // Satuan: check by kode or nama
        foreach (array_keys($refValues['satuan']) as $sat) {
            $exists = SatuanUnit::whereRaw('LOWER(kode) = ?', [strtolower($sat)])
                ->orWhereRaw('LOWER(nama) = ?', [strtolower($sat)])
                ->exists();
            $summary['satuan'][$exists ? 'existing' : 'new'][] = $sat;
        }

        // Gudang: check by nama or kode
        if (! empty($refValues['gudang'])) {
            foreach (array_keys($refValues['gudang']) as $g) {
                $exists = Gudang::whereRaw('LOWER(nama) = ?', [strtolower($g)])
                    ->orWhereRaw('LOWER(kode) = ?', [strtolower($g)])
                    ->exists()
                    || (is_numeric($g) && Gudang::find((int) $g));
                $summary['gudang'][$exists ? 'existing' : 'new'][] = $g;
            }
        }

        // Rak: check by nama or kode
        if (! empty($refValues['rak'])) {
            foreach (array_keys($refValues['rak']) as $r) {
                $exists = Rak::whereRaw('LOWER(nama) = ?', [strtolower($r)])
                    ->orWhereRaw('LOWER(kode) = ?', [strtolower($r)])
                    ->exists()
                    || (is_numeric($r) && Rak::find((int) $r));
                $summary['rak'][$exists ? 'existing' : 'new'][] = $r;
            }
        }

        return $summary;
    }

    /**
     * Commit: proses seluruh baris valid dalam chunk 100 (per-chunk DB transaction),
     * catat stok awal + StockMutationLog 'import:excel' + jurnal agregat 130-01/310-01.
     *
     * [B-10f/P1-5] Perbaikan atomik & lintas cabang:
     * - (a) file yang menunjuk gudang dari >1 cabang DITOLAK (pesan Indonesia),
     *   checked sebelum mutasi apa pun → tidak ada impor setengah jadi lintas cabang;
     * - (c) jurnal agregat stok awal diposting DI DALAM transaksi chunk yang sama
     *   dengan mutasi stoknya → mustahil ada jurnal tanpa mutasi atau sebaliknya
     *   (rollback chunk mengembalikan keduanya);
     * - branches:chunk dipakai karena RAM 1GB — satu file besar tidak boleh jadi
     *   satu transaksi raksasa yang menahan lock (/tmp) lama.
     */
    public function commit(string $filePath, int $importLogId, ?int $userId = null, array $aksiKemiripan = [], array $resolusiDuplikat = [], int $threshold = self::THRESHOLD_PENCOCOKAN, array $resolusiBarcode = [], array $resolusiErrorBaris = [], ?int $cabangId = null, array $columnMapping = [], ?int $defaultGudangId = null): array
    {
        $rows = $this->parseRows($filePath, $columnMapping);
        $totalBaris = count($rows);

        ImportLog::where('id', $importLogId)->update([
            'total_baris' => $totalBaris,
            'sukses' => 0,
            'gagal' => 0,
            'status' => 'proses',
        ]);

        // [B-10f] Validasi lintas cabang (fail fast, SEBELUM ada mutasi apa pun)
        $detectedCabangId = $this->cabangTunggalDariRows($rows, $cabangId);
        $cabangId = $cabangId ?? $detectedCabangId ?? session('cabang_id') ?? auth()->user()?->cabang_id ?? $this->resolveCabangDefault();
        [$existingSkuDb, $existingBarcodeDb] = $this->preloadExistingDb($rows);
        $konteks = [
            'skuDalamFile' => [],
            'barcodeDalamFile' => [],
            'namaDalamFile' => [],
            'skuBarisMap' => [],
            'barcodeBarisMap' => [],
            'existingSkuDb' => $existingSkuDb,
            'existingBarcodeDb' => $existingBarcodeDb,
            'skipFuzzy' => count($rows) > 300,
        ];

        $sukses = 0;
        $gagal = 0;
        $totalPeringatan = 0;
        $detail = [];
        $totalStokNilai = 0.0;
        $importedSkuToId = [];

        $chunkSize = 50;
        foreach (array_chunk($rows, $chunkSize) as $indexChunk => $chunk) {
            $lastItemChunk = ['sku' => null, 'nama' => null, 'baris' => null];

            DB::transaction(function () use ($chunk, &$konteks, $importLogId, &$sukses, &$gagal, &$totalPeringatan, &$detail, &$totalStokNilai, $cabangId, $userId, $indexChunk, &$importedSkuToId, $aksiKemiripan, $resolusiDuplikat, $threshold, $chunkSize, &$lastItemChunk, $resolusiBarcode, $resolusiErrorBaris, $defaultGudangId) {
                // Akumulator NILAI per chunk: jurnal di-post dalam transaksi yang
                // SAMA dengan mutasi stok baris-baris chunk ini.
                $nilaiChunk = 0.0;

                foreach ($chunk as $i => $row) {
                    $nomorBaris = $indexChunk * $chunkSize + $i + 2;
                    if ($defaultGudangId && empty($row['gudang']) && (float) ($row['stok_awal'] ?? 0) > 0) {
                        $row['gudang'] = (string) $defaultGudangId;
                    }
                    $skuRaw = trim((string) ($row['sku'] ?? ''));
                    $namaRaw = trim((string) ($row['nama'] ?? ''));
                    $barcodeRaw = $this->normalizeBarcode($row['barcode_1'] ?? $row['barcode'] ?? null);

                    $lastItemChunk = [
                        'sku' => $skuRaw,
                        'nama' => $namaRaw,
                        'baris' => $nomorBaris,
                    ];

                    // Resolusi Error Baris (Abaikan / Skip)
                    $resErr = $resolusiErrorBaris[$nomorBaris] ?? ($skuRaw !== '' ? ($resolusiErrorBaris[$skuRaw] ?? null) : null);
                    if ($resErr === 'skip') {
                        $detail[] = [
                            'baris' => $nomorBaris,
                            'sku' => $skuRaw,
                            'nama' => $row['nama'] ?? '',
                            'status' => 'skip',
                            'catatan' => 'Dilewati oleh pengguna (baris error diabaikan)',
                        ];

                        continue;
                    }

                    // Resolusi Barcode Duplikat
                    $resBc = $resolusiBarcode[$nomorBaris] ?? ($barcodeRaw !== '' ? ($resolusiBarcode[$barcodeRaw] ?? null) : null);
                    $aksiBc = is_array($resBc) ? ($resBc['aksi'] ?? null) : $resBc;
                    $barcodeBaru = is_array($resBc) ? trim((string) ($resBc['barcode_baru'] ?? '')) : '';

                    if ($aksiBc === 'skip') {
                        $detail[] = [
                            'baris' => $nomorBaris,
                            'sku' => $skuRaw,
                            'nama' => $row['nama'] ?? '',
                            'status' => 'skip',
                            'catatan' => 'Dilewati oleh pengguna (resolusi barcode duplikat: lewati)',
                        ];

                        continue;
                    } elseif ($aksiBc === 'kosongkan_barcode') {
                        $row['barcode'] = null;
                        $row['barcode_1'] = null;
                    } elseif ($aksiBc === 'ganti_barcode' && $barcodeBaru !== '') {
                        $row['barcode'] = $barcodeBaru;
                        $row['barcode_1'] = $barcodeBaru;
                    }

                    // Resolusi Duplikat SKU / Barcode dari input pengguna
                    $resDup = $resolusiDuplikat[$nomorBaris] ?? ($skuRaw !== '' ? ($resolusiDuplikat[$skuRaw] ?? null) : null);
                    $aksiDup = is_array($resDup) ? ($resDup['aksi'] ?? null) : $resDup;
                    $skuBaru = is_array($resDup) ? trim((string) ($resDup['sku_baru'] ?? '')) : '';

                    if ($aksiDup === 'skip') {
                        $detail[] = [
                            'baris' => $nomorBaris,
                            'sku' => $skuRaw,
                            'nama' => $row['nama'] ?? '',
                            'status' => 'skip',
                            'catatan' => 'Dilewati oleh pengguna (resolusi duplikat: lewati)',
                        ];

                        continue;
                    } elseif ($aksiDup === 'kosongkan_barcode') {
                        $row['barcode'] = null;
                        $row['barcode_1'] = null;
                    }

                    if ($aksiDup === 'ganti_sku' && $skuBaru !== '') {
                        $row['sku'] = $skuBaru;
                        $skuRaw = $skuBaru;
                        $lastItemChunk['sku'] = $skuBaru;
                    }

                    // Aksi Kemiripan Produk
                    $aksi = $aksiKemiripan[$nomorBaris] ?? ($skuRaw !== '' ? ($aksiKemiripan[$skuRaw] ?? null) : null) ?? 'terpisah';

                    if ($aksi === 'skip') {
                        $detail[] = [
                            'baris' => $nomorBaris,
                            'sku' => $skuRaw,
                            'nama' => $row['nama'] ?? '',
                            'status' => 'skip',
                            'catatan' => 'Dilewati oleh pengguna (pencocokan kemiripan: lewati)',
                        ];

                        continue;
                    }

                    $errors = $this->validateRow($row, $konteks);
                    if ($aksi === 'gabung' || $aksiDup === 'timpa_update') {
                        $errors = array_values(array_filter($errors, fn ($err) => ! str_contains($err, 'duplikat')));
                    } elseif (in_array($aksiDup, ['ganti_sku', 'skip'], true)) {
                        $errors = array_values(array_filter($errors, fn ($err) => ! str_contains($err, 'duplikat') || ! str_contains($err, 'SKU')));
                    }
                    if ($aksiBc === 'kosongkan_barcode' || $aksiDup === 'kosongkan_barcode') {
                        $errors = array_values(array_filter($errors, fn ($err) => ! str_contains($err, 'Barcode')));
                    }
                    $warnings = $this->checkRowWarnings($row, $nomorBaris, $konteks, $threshold);
                    $this->catatKonteks($row, $nomorBaris, $konteks);

                    try {
                        if ($errors) {
                            throw new \Exception(implode('; ', $errors));
                        }
                        // [B-10f] Savepoint per baris (nested transaction): baris gagal
                        // tidak boleh meninggalkan produk/sku setengah tertulis di dalam
                        // chunk yang sama — nilai jurnal chunk tetap = mutasi utuh.
                        $result = DB::transaction(function () use ($row, $importLogId, $userId, $aksi, $aksiDup, &$importedSkuToId, $nomorBaris, $konteks, $threshold, $cabangId) {
                            if ($aksiDup === 'timpa_update') {
                                return $this->prosesTimpaUpdateBaris($row, $importLogId, $userId, $nomorBaris, $cabangId);
                            }

                            if ($aksi === 'gabung') {
                                return $this->prosesGabungBaris($row, $importLogId, $userId, $importedSkuToId, $nomorBaris, $konteks, $threshold, $cabangId);
                            }

                            return $this->prosesSatuBaris($row, $importLogId, $userId, $cabangId);
                        });

                        $sukses++;
                        $nilaiChunk += $result['stok_nilai'];
                        $totalStokNilai += $result['stok_nilai'];
                        if (! empty($result['produk_id']) && ! empty($result['sku'])) {
                            $importedSkuToId[$result['sku']] = $result['produk_id'];
                        }
                        if (! empty($warnings)) {
                            $totalPeringatan++;
                        }
                        $catatanStatus = null;
                        if (! empty($result['timpa'])) {
                            $catatanStatus = "Diperbarui/ditimpa ke produk eksisting [{$result['sku']}] {$result['nama']}";
                        } elseif (! empty($result['gabung'])) {
                            $catatanStatus = "Digabungkan ke master produk [ID: {$result['produk_id']}] {$result['nama']}";
                        } elseif ($aksiDup === 'ganti_sku' && $skuBaru !== '') {
                            $catatanStatus = "SKU disesuaikan menjadi {$skuBaru}";
                        }

                        $detail[] = [
                            'baris' => $nomorBaris,
                            'sku' => $result['sku'] ?? $skuRaw,
                            'nama' => $row['nama'] ?? '',
                            'status' => 'ok',
                            'catatan' => $catatanStatus,
                            'warning' => ! empty($warnings) ? implode('; ', $warnings) : null,
                        ];
                    } catch (\Throwable $e) {
                        $gagal++;
                        $detail[] = [
                            'baris' => $nomorBaris,
                            'sku' => trim((string) ($row['sku'] ?? '')),
                            'nama' => trim((string) ($row['nama'] ?? '')),
                            'status' => 'gagal',
                            'error' => $e->getMessage(),
                        ];
                    }
                }

                // Jurnal agregat stok awal chunk ini: Debit 130-01 / Kredit 310-01
                // (modal stok awal) — balance, DI DALAM transaksi yang sama dgn
                // mutasi stok chunk ini (lihat noJurnalChunk).
                if ($nilaiChunk > 0) {
                    $this->postJurnalStokAwal(
                        $importLogId,
                        $cabangId,
                        $nilaiChunk,
                        $userId,
                        $this->noJurnalChunk($importLogId, $indexChunk)
                    );
                }
            });

            // Update kemajuan real-time ke ImportLog (beserta produk yang baru selesai diproses)
            ImportLog::where('id', $importLogId)->update([
                'total_baris' => $totalBaris,
                'sukses' => $sukses,
                'gagal' => $gagal,
                'detail' => [
                    'current_sku' => $lastItemChunk['sku'] ?? null,
                    'current_nama' => Str::limit($lastItemChunk['nama'] ?? '', 70),
                    'current_baris' => $lastItemChunk['baris'] ?? ($sukses + $gagal),
                    'total_baris' => $totalBaris,
                ],
            ]);
        }

        // Pass 2: Resolusi kompatibilitas antar produk (substitusi) via SKU
        $this->resolveProdukKompatibelAntarSku($rows, $importedSkuToId);

        // Baris number di detail dipertahankan sesuai nomor baris asli spreadsheet
        foreach ($detail as $d => $item) {
            if (! isset($detail[$d]['baris'])) {
                $detail[$d]['baris'] = $d + 2;
            }
        }

        ImportLog::where('id', $importLogId)->update([
            'detail' => $detail,
        ]);

        $statusKeseluruhan = $gagal === 0 ? 'sukses_penuh' : ($sukses > 0 ? 'sukses_sebagian' : 'gagal_total');
        $labelStatus = match ($statusKeseluruhan) {
            'sukses_penuh' => ($totalPeringatan > 0 ? 'Diterima Sempurna (Perlu Perhatian Kompatibilitas)' : 'Diterima Sempurna'),
            'sukses_sebagian' => 'Diterima Sebagian',
            default => 'Gagal Total',
        };

        return [
            'total_baris' => count($rows),
            'sukses' => $sukses,
            'gagal' => $gagal,
            'total_peringatan' => $totalPeringatan,
            'status_keseluruhan' => $statusKeseluruhan,
            'label_status' => $labelStatus,
            'detail' => $detail,
            'jurnal_nilai' => round($totalStokNilai, 2),
        ];
    }

    /**
     * [B-10f/P1-5] Resolvi cabang dari gudang-gudang yang dipakai file.
     *
     * Import bukan partisi per cabang (sederhana & aman utk RAM 1GB: satu file =
     * satu cabang), jadi file yang mencampur >1 cabang DITOLAK. Jurnal stok awal
     * hanya punya satu kolom cabang_id — memaksanya ke baris pertama akan salah
     * membebankan persediaan antar cabang.
     *
     * @return int|null cabang tunggal, atau null bila file tidak menyentuh stok
     *
     * @throws \Exception pesan Indonesia bila file lintas cabang
     */
    /**
     * Resolusi ID cabang dari input raw (ID numerik, kode, atau nama).
     */
    public function resolveCabangId(?string $cabangRaw): ?int
    {
        $raw = trim((string) $cabangRaw);
        if ($raw === '' || in_array(strtolower($raw), ['-', '0', 'none', 'null', 'kosong'], true)) {
            return null;
        }

        if (isset($this->cabangCache["raw|{$raw}"])) {
            return $this->cabangCache["raw|{$raw}"];
        }

        if (is_numeric($raw)) {
            $c = Cabang::find((int) $raw);
            if ($c) {
                return $this->cabangCache["raw|{$raw}"] = $c->id;
            }
        }

        $c = Cabang::whereRaw('LOWER(nama) = ?', [strtolower($raw)])
            ->orWhereRaw('LOWER(kode) = ?', [strtolower($raw)])
            ->first();

        return $this->cabangCache["raw|{$raw}"] = $c?->id;
    }

    /**
     * Validasi fail-fast bahwa seluruh baris file yang menyentuh stok mengarah
     * ke SATU cabang yang sama.
     *
     * @return int|null cabang tunggal, atau null bila file tidak menyentuh stok
     *
     * @throws \Exception pesan Indonesia bila file lintas cabang
     */
    protected function cabangTunggalDariRows(array $rows, ?int $defaultCabangId = null): ?int
    {
        $gudangIds = [];
        $cabangIdsDariRow = [];

        foreach ($rows as $row) {
            $cRaw = trim((string) ($row['cabang'] ?? $row['nama_cabang'] ?? $row['cabang_id'] ?? ''));
            if ($cRaw !== '') {
                $cId = $this->resolveCabangId($cRaw);
                if ($cId) {
                    $cabangIdsDariRow[$cId] = true;
                }
            }

            $currentCabangId = ! empty($cabangIdsDariRow) ? array_key_last($cabangIdsDariRow) : $defaultCabangId;
            $gRaw = trim((string) ($row['gudang'] ?? $row['gudang_id'] ?? ''));
            if ($gRaw !== '' && ! in_array(strtolower($gRaw), ['-', '0', 'none', 'null', 'kosong'], true)) {
                $resolvedId = $this->resolveGudangId($gRaw, $currentCabangId);
                if ($resolvedId) {
                    $gudangIds[$resolvedId] = true;
                }
            }
        }

        if (count($cabangIdsDariRow) > 1) {
            $namaCabangs = Cabang::whereIn('id', array_keys($cabangIdsDariRow))->pluck('nama')->implode(', ');
            throw new \Exception(
                'Import lintas cabang tidak didukung: file ini menyebutkan lebih dari satu cabang ('.$namaCabangs.'). '
                .'Pisahkan file per cabang lalu import satu per satu agar jurnal stok awal ter-posting ke cabang yang benar.'
            );
        }

        if ($gudangIds === []) {
            return ! empty($cabangIdsDariRow) ? (int) array_key_first($cabangIdsDariRow) : null;
        }

        // Satu query (bukan N) — RAM 1GB.
        $gudang = Gudang::with('cabang')
            ->whereIn('id', array_keys($gudangIds))
            ->get(['id', 'cabang_id']);

        $perCabang = [];
        foreach ($gudang as $g) {
            if ($g->cabang_id) {
                $perCabang[(int) $g->cabang_id] = true;
            }
        }
        foreach (array_keys($cabangIdsDariRow) as $cId) {
            $perCabang[(int) $cId] = true;
        }

        if (count($perCabang) <= 1) {
            return $perCabang ? (int) array_key_first($perCabang) : null;
        }

        $namaCabang = collect($perCabang)
            ->map(fn ($_, $id) => optional($gudang->firstWhere('cabang_id', $id)?->cabang)->nama ?? Cabang::find($id)?->nama ?? "Cabang #{$id}")
            ->values()
            ->implode(', ');

        throw new \Exception(
            'Import lintas cabang tidak didukung: file ini menunjuk gudang dari '.count($perCabang)
            .' cabang ('.$namaCabang.'). Pisahkan file per cabang lalu import satu per satu '
            .'agar jurnal stok awal tetap ter-posting ke cabang yang benar.'
        );
    }

    /**
     * Catat sku/barcode setelah baris divalidasi — duplikat in-file hanya terdeteksi
     * pada kemunculan BERIKUTNYA (kemunculan pertama sah kecuali sudah ada di DB).
     */
    protected function catatKonteks(array $row, int $nomorBaris, array &$konteks): void
    {
        $s = trim((string) ($row['sku'] ?? ''));
        $n = trim((string) ($row['nama'] ?? ''));
        if ($s !== '') {
            $konteks['skuDalamFile'][$s] = true;
            $konteks['skuBarisMap'][$s] = ['baris' => $nomorBaris, 'nama' => $n];
        }
        $allBarcodes = $this->extractAllBarcodesFromRow($row);
        foreach ($allBarcodes as $bcVal) {
            $konteks['barcodeDalamFile'][$bcVal] = true;
            $konteks['barcodeBarisMap'][$bcVal] = ['baris' => $nomorBaris, 'nama' => $n, 'sku' => $s];
        }
        $n = trim((string) ($row['nama'] ?? ''));
        if ($n !== '') {
            $konteks['namaDalamFile'][] = [
                'baris' => $nomorBaris,
                'sku' => $s,
                'nama' => $n,
                'brand' => trim((string) ($row['brand'] ?? '')),
                'tipe_hp' => trim((string) ($row['tipe_hp'] ?? '')),
            ];
        }
    }

    /**
     * Proses penggabungan baris ke master produk yang sudah ada (merge varian, tipe HP, dan stok).
     */
    protected function prosesGabungBaris(array $row, int $importLogId, ?int $userId, array &$importedSkuToId, int $nomorBaris, array $konteks, int $threshold = self::THRESHOLD_PENCOCOKAN, ?int $cabangId = null): array
    {
        $nama = trim((string) ($row['nama'] ?? ''));
        $sku = trim((string) ($row['sku'] ?? ''));
        $barcode = $this->normalizeBarcode($row['barcode_1'] ?? $row['barcode'] ?? null);
        $hargaBeli = round((float) ($row['harga_beli'] ?? 0), 2);
        $hargaJual = round((float) ($row['harga_jual'] ?? 0), 2);
        $stokAwal = (int) ($row['stok_awal'] ?? 0);
        $satuan = $this->resolveOrCreateSatuan($row['satuan'] ?? 'pcs');

        // Cari target produk yang mirip (baik dari DB atau intra-file)
        $targetProduk = null;
        $existing = $this->findSimilarExistingProduct($nama, $sku, $threshold);
        if ($existing) {
            $targetProduk = Produk::with(['tipeHps', 'skuVariants'])->find($existing->id);
        }

        if (! $targetProduk && ! empty($konteks['namaDalamFile'])) {
            foreach ($konteks['namaDalamFile'] as $prev) {
                if (strcasecmp($prev['sku'], $sku) === 0) {
                    continue;
                }
                $lenDiff = abs(strlen($nama) - strlen($prev['nama']));
                if ($lenDiff > 12) {
                    continue;
                }
                similar_text(strtolower($nama), strtolower($prev['nama']), $pct);
                if ($pct >= $threshold) {
                    $targetProdukId = $importedSkuToId[$prev['sku']] ?? null;
                    if ($targetProdukId) {
                        $targetProduk = Produk::with(['tipeHps', 'skuVariants'])->find($targetProdukId);
                        break;
                    }
                }
            }
        }

        if (! $targetProduk) {
            return $this->prosesSatuBaris($row, $importLogId, $userId, $cabangId);
        }

        // 1. Merge Tipe HP & Kompatibilitas
        $merkHp = $this->normalizeMerkHp($row['merk_hp'] ?? null);
        $daftarTipeHp = $this->parseTipeHp($row['tipe_hp'] ?? null, $merkHp);
        $kompat = $this->parseKompatibilitas($row['kompatibilitas_hp'] ?? null, $merkHp);
        $daftarTipe = $this->gabungDaftarTipe($daftarTipeHp, $kompat);

        if ($daftarTipe) {
            $ids = [];
            foreach ($daftarTipe as $t) {
                $cacheKey = $t['merk'].'|'.$t['model'];
                if (! isset($this->tipeHpCache[$cacheKey])) {
                    $this->tipeHpCache[$cacheKey] = $this->resolveOrCreateTipeHpId($t['merk'], $t['model']);
                }
                $ids[] = $this->tipeHpCache[$cacheKey];
            }
            $targetProduk->tipeHps()->syncWithoutDetaching($ids);

            $existingKompat = is_array($targetProduk->kompatibilitas_hp) ? $targetProduk->kompatibilitas_hp : [];
            $mergedKompat = $this->gabungDaftarTipe($existingKompat, $daftarTipe);
            $allMerks = collect($mergedKompat)->pluck('merk')->filter()->unique()->values();
            $allModels = collect($mergedKompat)->pluck('model')->filter()->unique()->values();

            $updateMerge = ['kompatibilitas_hp' => $mergedKompat];
            if ($allMerks->isNotEmpty() && empty($targetProduk->brand_kompatibel)) {
                $updateMerge['brand_kompatibel'] = $allMerks->implode(', ');
            }
            if ($allModels->isNotEmpty()) {
                $updateMerge['model_kompatibel'] = $allModels->implode(', ');
            }
            $targetProduk->update($updateMerge);
        }

        // 2. Variant resolution
        $variant = null;
        if ($sku !== '') {
            $variant = SkuVariant::where('sku', $sku)->first();
            if (! $variant) {
                $variant = SkuVariant::create([
                    'produk_id' => $targetProduk->id,
                    'sku' => $sku,
                    'barcode' => $barcode ?: null,
                    'nama_varian' => trim((string) ($row['nama_varian'] ?? '')) ?: 'Varian '.$sku,
                    'satuan_kode' => $satuan,
                    'harga_beli' => $hargaBeli > 0 ? $hargaBeli : (float) $targetProduk->harga_beli,
                    'harga_jual_retail' => $hargaJual > 0 ? $hargaJual : (float) $targetProduk->harga_jual_retail,
                    'is_active' => true,
                ]);
            }
        }
        if (! $variant) {
            $variant = $targetProduk->skuVariants()->first();
        }

        // 3. Stok awal & Lokasi
        $rowCabangRaw = trim((string) ($row['cabang'] ?? $row['nama_cabang'] ?? $row['cabang_id'] ?? ''));
        $cabangId = ($rowCabangRaw !== '' ? $this->resolveCabangId($rowCabangRaw) : null)
            ?? $cabangId ?? session('cabang_id') ?? auth()->user()?->cabang_id ?? $this->resolveCabangDefault();
        $gudangRaw = trim((string) ($row['gudang'] ?? $row['gudang_id'] ?? ''));
        $gudangKosong = $gudangRaw === '' || in_array(strtolower($gudangRaw), ['-', '0', 'none', 'null', 'kosong'], true);
        $rakRaw = trim((string) ($row['rak'] ?? $row['rak_id'] ?? ''));
        $rakKosong = $rakRaw === '' || in_array(strtolower($rakRaw), ['-', '0', 'none', 'null', 'kosong'], true);

        $targetGudangId = $gudangKosong
            ? $this->resolveOrCreateGudangDefault($cabangId)
            : $this->resolveOrCreateGudang($gudangRaw, $cabangId);

        $rakId = $this->resolveOrCreateRak($rakRaw, $targetGudangId);
        $gudangId = ($stokAwal > 0 || ! $gudangKosong || ! $rakKosong) ? $targetGudangId : null;

        $stokNilai = 0.0;
        if ($gudangId && $variant) {
            $minStock = isset($row['min_stock']) && is_numeric($row['min_stock']) ? (int) $row['min_stock'] : 0;
            $stok = StokItem::firstOrCreate(
                ['produk_id' => $targetProduk->id, 'sku_variant_id' => $variant->id, 'gudang_id' => $gudangId],
                ['jumlah' => 0, 'jumlah_minimum' => $minStock, 'rak_id' => $rakId]
            );

            if ($rakId && $stok->rak_id !== $rakId) {
                $stok->update(['rak_id' => $rakId]);
            }

            if ($stokAwal > 0) {
                $sebelum = $stok->jumlah;
                $stok->update(['jumlah' => $sebelum + $stokAwal, 'rak_id' => $rakId]);

                StokLog::create([
                    'gudang_id' => $gudangId,
                    'produk_id' => $targetProduk->id,
                    'sku_variant_id' => $variant->id,
                    'user_id' => $userId,
                    'jenis' => 'import',
                    'referensi_tipe' => ImportLog::class,
                    'referensi_id' => $importLogId,
                    'jumlah_sebelum' => $sebelum,
                    'perubahan' => $stokAwal,
                    'jumlah_setelah' => $sebelum + $stokAwal,
                    'catatan' => "Stok awal import (gabung ke master: {$targetProduk->nama})",
                ]);

                StockMutationLog::create([
                    'produk_id' => $targetProduk->id,
                    'sku_variant_id' => $variant->id,
                    'gudang_id' => $gudangId,
                    'user_id' => $userId,
                    'delta' => $stokAwal,
                    'sumber' => 'import:excel',
                    'referensi_tipe' => ImportLog::class,
                    'referensi_id' => $importLogId,
                    'terjadi_at' => now(),
                ]);

                $hpp = $hargaBeli > 0 ? $hargaBeli : (float) $targetProduk->harga_beli;
                $stokNilai = round($stokAwal * $hpp, 2);
            }
        }

        return [
            'produk_id' => $targetProduk->id,
            'sku' => $variant?->sku ?? $sku,
            'nama' => $targetProduk->nama,
            'stok_nilai' => $stokNilai,
            'gabung' => true,
        ];
    }

    /**
     * Proses timpa/update produk eksisting ketika user memilih opsi timpa untuk duplikat database.
     */
    protected function prosesTimpaUpdateBaris(array $row, int $importLogId, ?int $userId, int $nomorBaris, ?int $cabangId = null): array
    {
        $sku = trim((string) ($row['sku'] ?? ''));
        $barcode = $this->normalizeBarcode($row['barcode_1'] ?? $row['barcode'] ?? null);
        $hargaBeli = round((float) ($row['harga_beli'] ?? 0), 2);
        $hargaJual = round((float) ($row['harga_jual'] ?? 0), 2);
        $stokAwal = (int) ($row['stok_awal'] ?? 0);

        $variant = null;
        if ($sku !== '') {
            $variant = SkuVariant::with('produk')->where('sku', $sku)->first();
        }
        if (! $variant && $barcode !== '') {
            $variant = SkuVariant::with('produk')->where('barcode', $barcode)->first();
        }

        if (! $variant) {
            return $this->prosesSatuBaris($row, $importLogId, $userId, $cabangId);
        }

        $produk = $variant->produk;

        // Update atribut produk jika diisi pada import baris ini
        $produkUpdate = [];

        $namaInput = trim((string) ($row['nama'] ?? ''));
        if ($namaInput !== '') {
            $produkUpdate['nama'] = $namaInput;
        }

        $kategoriUtamaInput = trim((string) ($row['kategori'] ?? $row['kategori_utama'] ?? ''));
        $subKategoriInput = trim((string) ($row['sub_kategori'] ?? ''));
        if ($kategoriUtamaInput !== '') {
            $katData = $this->resolveOrCreateKategori($kategoriUtamaInput, $subKategoriInput);
            $produkUpdate['kategori_id'] = $katData['id'];
            $produkUpdate['kategori'] = $katData['nama'];
        }

        $brandRaw = trim((string) ($row['brand'] ?? ''));
        if ($brandRaw !== '' && ! in_array(strtolower($brandRaw), ['-', 'none', 'null', 'kosong'], true)) {
            $brandId = $this->resolveOrCreateBrand($brandRaw);
            $produkUpdate['brand_id'] = $brandId;
        } else {
            $brandId = $produk->brand_id;
        }

        $kualitasRaw = trim((string) ($row['kualitas'] ?? ''));
        if ($kualitasRaw !== '') {
            $produkUpdate['kualitas_id'] = $this->resolveOrCreateKualitas($kualitasRaw, $brandId);
        }

        $supplierRaw = trim((string) ($row['supplier'] ?? ''));
        if ($supplierRaw !== '') {
            $produkUpdate['supplier_id'] = $this->resolveSupplierId($supplierRaw);
        }

        $satuanRaw = trim((string) ($row['satuan'] ?? ''));
        if ($satuanRaw !== '') {
            $satuan = $this->resolveOrCreateSatuan($satuanRaw);
            $produkUpdate['satuan'] = $satuan;
        }

        if (isset($row['kondisi']) && trim((string) $row['kondisi']) !== '') {
            $produkUpdate['kondisi'] = $this->normalizeKondisi((string) $row['kondisi']);
        }

        if (isset($row['min_stock']) && is_numeric($row['min_stock'])) {
            $produkUpdate['min_stock'] = (int) $row['min_stock'];
        }

        if (isset($row['reorder_point']) && is_numeric($row['reorder_point'])) {
            $produkUpdate['reorder_point'] = (int) $row['reorder_point'];
        }

        if (isset($row['sn']) && trim((string) $row['sn']) !== '') {
            $produkUpdate['sn'] = $this->normalizeSn($row['sn']);
        }

        $extractedBarcodes = $this->extractAllBarcodesFromRow($row);
        if (! empty($extractedBarcodes)) {
            $barcodeList = array_values($extractedBarcodes);
            $barcode = $barcodeList[0];
            $barcodeAltMap = array_slice($extractedBarcodes, 1);
            $produkUpdate['barcode'] = $barcode;
            $produkUpdate['barcode_alt'] = ! empty($barcodeAltMap) ? $barcodeAltMap : null;
        }

        if (isset($row['deskripsi']) && trim((string) $row['deskripsi']) !== '') {
            $produkUpdate['deskripsi'] = trim((string) $row['deskripsi']);
        }

        if (isset($row['foto_url']) && trim((string) $row['foto_url']) !== '') {
            $produkUpdate['gambar'] = trim((string) $row['foto_url']);
        }

        if ($hargaBeli > 0) {
            $produkUpdate['harga_beli'] = $hargaBeli;
        }
        if ($hargaJual > 0) {
            $produkUpdate['harga_jual_retail'] = $hargaJual;
        }

        $finalHpp = $hargaBeli > 0 ? $hargaBeli : (float) $produk->harga_beli;
        $finalJual = $hargaJual > 0 ? $hargaJual : (float) $produk->harga_jual_retail;
        if ($finalHpp > 0 && $finalJual > 0) {
            $produkUpdate['margin_persen'] = round((($finalJual - $finalHpp) / $finalHpp) * 100, 2);
        }

        // Sync Tipe HP jika diisi
        $merkHp = $this->normalizeMerkHp($row['merk_hp'] ?? null);
        $daftarTipeHp = $this->parseTipeHp($row['tipe_hp'] ?? null, $merkHp);
        $kompat = $this->parseKompatibilitas($row['kompatibilitas_hp'] ?? null, $merkHp);
        $daftarTipe = $this->gabungDaftarTipe($daftarTipeHp, $kompat);

        if ($daftarTipe) {
            $ids = [];
            foreach ($daftarTipe as $t) {
                $cacheKey = $t['merk'].'|'.$t['model'];
                if (! isset($this->tipeHpCache[$cacheKey])) {
                    $this->tipeHpCache[$cacheKey] = $this->resolveOrCreateTipeHpId($t['merk'], $t['model']);
                }
                $ids[] = $this->tipeHpCache[$cacheKey];
            }
            $produk->tipeHps()->syncWithoutDetaching($ids);

            $allMerks = collect($daftarTipe)->pluck('merk')->filter()->unique()->values();
            $allModels = collect($daftarTipe)->pluck('model')->filter()->unique()->values();
            if ($merkHp || $allMerks->isNotEmpty()) {
                $produkUpdate['brand_kompatibel'] = $merkHp ?: $allMerks->implode(', ');
            }
            if ($allModels->isNotEmpty()) {
                $produkUpdate['model_kompatibel'] = $allModels->implode(', ');
            }
            $produkUpdate['kompatibilitas_hp'] = $daftarTipe;
        }

        if (! empty($produkUpdate)) {
            $produk->update($produkUpdate);
        }

        // Update harga dan barcode jika ada
        $variantUpdate = [];
        if ($hargaBeli > 0) {
            $variantUpdate['harga_beli'] = $hargaBeli;
        }
        if ($hargaJual > 0) {
            $variantUpdate['harga_jual_retail'] = $hargaJual;
        }
        if ($barcode && ! $variant->barcode) {
            $variantUpdate['barcode'] = $barcode;
        }
        if (isset($satuan)) {
            $variantUpdate['satuan_kode'] = $satuan;
        }
        if (! empty($variantUpdate)) {
            $variant->update($variantUpdate);
        }

        if (! empty($extractedBarcodes)) {
            $produk->syncBarcodes(array_values($extractedBarcodes), $sku ?: $variant->sku);
        }

        // Multi satuan / konversi satuan
        if (! empty($row['konversi_satuan'])) {
            $this->simpanKonversiSatuan($produk, (string) $row['konversi_satuan'], $produk->satuan);
        }

        // Update HargaTier
        if ($hargaJual > 0) {
            HargaTier::updateOrCreate(
                ['produk_id' => $produk->id, 'sku_variant_id' => null, 'tier_membership_id' => null, 'tipe_konsumen' => 'retail'],
                ['is_reseller' => false, 'harga' => $hargaJual, 'nominal_tetap' => $hargaJual, 'persen_diskon' => null]
            );
        }
        foreach (['reseller' => 'harga_reseller', 'agen' => 'harga_agen'] as $tipe => $kolom) {
            if (isset($row[$kolom]) && is_numeric($row[$kolom])) {
                HargaTier::updateOrCreate(
                    ['produk_id' => $produk->id, 'sku_variant_id' => null, 'tier_membership_id' => null, 'tipe_konsumen' => $tipe],
                    ['is_reseller' => $tipe === 'reseller', 'harga' => round((float) $row[$kolom], 2), 'nominal_tetap' => round((float) $row[$kolom], 2), 'persen_diskon' => null]
                );
            }
        }

        // Tambah stok awal jika diisi
        $rowCabangRaw = trim((string) ($row['cabang'] ?? $row['nama_cabang'] ?? $row['cabang_id'] ?? ''));
        $cabangId = ($rowCabangRaw !== '' ? $this->resolveCabangId($rowCabangRaw) : null)
            ?? $cabangId ?? session('cabang_id') ?? auth()->user()?->cabang_id ?? $this->resolveCabangDefault();
        $gudangRaw = trim((string) ($row['gudang'] ?? $row['gudang_id'] ?? ''));
        $gudangKosong = $gudangRaw === '' || in_array(strtolower($gudangRaw), ['-', '0', 'none', 'null', 'kosong'], true);
        $rakRaw = trim((string) ($row['rak'] ?? $row['rak_id'] ?? ''));
        $rakKosong = $rakRaw === '' || in_array(strtolower($rakRaw), ['-', '0', 'none', 'null', 'kosong'], true);

        $targetGudangId = $gudangKosong
            ? $this->resolveOrCreateGudangDefault($cabangId)
            : $this->resolveOrCreateGudang($gudangRaw, $cabangId);

        $rakId = $this->resolveOrCreateRak($rakRaw, $targetGudangId);
        $gudangId = ($stokAwal > 0 || ! $gudangKosong || ! $rakKosong) ? $targetGudangId : null;

        $stokNilai = 0.0;
        if ($gudangId) {
            $stok = StokItem::firstOrCreate(
                ['produk_id' => $produk->id, 'sku_variant_id' => $variant->id, 'gudang_id' => $gudangId],
                ['jumlah' => 0, 'jumlah_minimum' => 0, 'rak_id' => $rakId]
            );

            if ($rakId && $stok->rak_id !== $rakId) {
                $stok->update(['rak_id' => $rakId]);
            }

            if ($stokAwal > 0) {
                $sebelum = $stok->jumlah;
                $stok->update(['jumlah' => $sebelum + $stokAwal, 'rak_id' => $rakId]);

                StokLog::create([
                    'gudang_id' => $gudangId,
                    'produk_id' => $produk->id,
                    'sku_variant_id' => $variant->id,
                    'user_id' => $userId,
                    'jenis' => 'import',
                    'referensi_tipe' => ImportLog::class,
                    'referensi_id' => $importLogId,
                    'jumlah_sebelum' => $sebelum,
                    'perubahan' => $stokAwal,
                    'jumlah_setelah' => $sebelum + $stokAwal,
                    'catatan' => "Stok awal import (update produk eksisting: {$produk->nama})",
                ]);

                StockMutationLog::create([
                    'produk_id' => $produk->id,
                    'sku_variant_id' => $variant->id,
                    'gudang_id' => $gudangId,
                    'user_id' => $userId,
                    'delta' => $stokAwal,
                    'sumber' => 'import:excel',
                    'referensi_tipe' => ImportLog::class,
                    'referensi_id' => $importLogId,
                    'terjadi_at' => now(),
                ]);

                $hpp = $hargaBeli > 0 ? $hargaBeli : (float) $variant->harga_beli;
                $stokNilai = round($stokAwal * $hpp, 2);
            }
        }

        return [
            'produk_id' => $produk->id,
            'sku' => $variant->sku,
            'nama' => $produk->nama,
            'stok_nilai' => $stokNilai,
            'timpa' => true,
        ];
    }

    /**
     * Proses satu baris valid: produk + sku_variant + harga_tier + tipe_hp + stok awal.
     */
    protected function prosesSatuBaris(array $row, int $importLogId, ?int $userId, ?int $cabangId = null): array
    {
        $nama = trim((string) ($row['nama'] ?? ''));
        $extractedBarcodes = $this->extractAllBarcodesFromRow($row);
        $barcodeList = array_values($extractedBarcodes);
        $barcode = $extractedBarcodes['barcode_1'] ?? null;
        $barcodeAltList = array_slice($extractedBarcodes, 1);
        $opsiSku = strtolower(trim((string) ($row['opsi_sku'] ?? '')));
        $sku = trim((string) ($row['sku'] ?? ''));

        if ($opsiSku === 'barcode' && ! empty($barcode)) {
            $sku = $barcode;
        } elseif ($sku === '') {
            if ($opsiSku === 'otomatis') {
                $dummyKonteks = [];
                $sku = $this->generateFallbackSku($nama, $dummyKonteks);
            } elseif (! empty($barcode)) {
                $sku = $barcode;
            } else {
                $dummyKonteks = [];
                $sku = $this->generateFallbackSku($nama, $dummyKonteks);
            }
        }
        if ($nama === '') {
            $nama = 'Produk '.$sku;
        }

        $satuan = $this->resolveOrCreateSatuan($row['satuan'] ?? 'pcs');

        $kategoriUtamaInput = trim((string) ($row['kategori'] ?? $row['kategori_utama'] ?? '')) ?: 'Umum';
        $subKategoriInput = trim((string) ($row['sub_kategori'] ?? ''));
        $hargaBeli = round((float) ($row['harga_beli'] ?? 0), 2);
        $hargaJual = round((float) ($row['harga_jual'] ?? 0), 2);
        $stokAwal = (int) ($row['stok_awal'] ?? 0);

        $rowCabangRaw = trim((string) ($row['cabang'] ?? $row['nama_cabang'] ?? $row['cabang_id'] ?? ''));
        $cabangId = ($rowCabangRaw !== '' ? $this->resolveCabangId($rowCabangRaw) : null)
            ?? $cabangId ?? session('cabang_id') ?? auth()->user()?->cabang_id ?? $this->resolveCabangDefault();
        $gudangRaw = trim((string) ($row['gudang'] ?? $row['gudang_id'] ?? ''));
        $gudangKosong = $gudangRaw === '' || in_array(strtolower($gudangRaw), ['-', '0', 'none', 'null', 'kosong'], true);
        $rakRaw = trim((string) ($row['rak'] ?? $row['rak_id'] ?? ''));
        $rakKosong = $rakRaw === '' || in_array(strtolower($rakRaw), ['-', '0', 'none', 'null', 'kosong'], true);

        $targetGudangId = $gudangKosong
            ? $this->resolveOrCreateGudangDefault($cabangId)
            : $this->resolveOrCreateGudang($gudangRaw, $cabangId);

        $rakId = $this->resolveOrCreateRak($rakRaw, $targetGudangId);
        $gudangId = ($stokAwal > 0 || ! $gudangKosong || ! $rakKosong) ? $targetGudangId : null;

        $supplierRaw = trim((string) ($row['supplier'] ?? ''));
        $supplierId = $this->resolveSupplierId($supplierRaw);

        $kondisi = $this->normalizeKondisi((string) ($row['kondisi'] ?? 'baru'));
        $minStock = isset($row['min_stock']) && is_numeric($row['min_stock']) ? (int) $row['min_stock'] : 0;
        $reorderPoint = isset($row['reorder_point']) && is_numeric($row['reorder_point']) ? (int) $row['reorder_point'] : 0;
        $sn = $this->normalizeSn($row['sn'] ?? 'tidak');
        $deskripsi = trim((string) ($row['deskripsi'] ?? '')) ?: null;

        // Brand & kualitas: runtime cache to accelerate massive imports
        $brandName = trim((string) ($row['brand'] ?? ''));
        $brandId = $this->resolveOrCreateBrand($brandName);
        $kualitasId = $this->resolveOrCreateKualitas(trim((string) ($row['kualitas'] ?? '')), $brandId);

        // Kategori & sub-kategori: runtime cache & firstOrCreate
        $katData = $this->resolveOrCreateKategori($kategoriUtamaInput, $subKategoriInput);
        $kategoriId = $katData['id'];
        $kategoriSimpan = $katData['nama'];

        // Merk Smartphone & Tipe HP kompatibilitas
        $merkHp = $this->normalizeMerkHp($row['merk_hp'] ?? null);
        $daftarTipeHp = $this->parseTipeHp($row['tipe_hp'] ?? null, $merkHp);
        $kompat = $this->parseKompatibilitas($row['kompatibilitas_hp'] ?? null, $merkHp);
        $daftarTipe = $this->gabungDaftarTipe($daftarTipeHp, $kompat);

        $allMerks = collect($daftarTipe)->pluck('merk')->filter()->unique()->values();
        $allModels = collect($daftarTipe)->pluck('model')->filter()->unique()->values();

        $primaryMerk = $merkHp ?: ($allMerks->isNotEmpty() ? $allMerks->implode(', ') : ($brandName ?: null));
        $primaryModel = $allModels->isNotEmpty() ? $allModels->implode(', ') : null;

        $marginPersen = ($hargaBeli > 0 && $hargaJual > 0)
            ? round((($hargaJual - $hargaBeli) / $hargaBeli) * 100, 2)
            : null;

        $produk = Produk::create([
            'nama' => $nama,
            'slug' => (Str::limit(Str::slug($nama), 180, '') ?: 'produk').'-'.Str::lower(Str::random(4)),
            'deskripsi' => $deskripsi,
            'kategori' => $kategoriSimpan,
            'kategori_id' => $kategoriId,
            'brand_id' => $brandId,
            'kualitas_id' => $kualitasId,
            'supplier_id' => $supplierId,
            'barcode' => $barcode ?: null,
            'barcode_alt' => ! empty($barcodeAltList) ? $barcodeAltList : null,
            'brand_kompatibel' => $primaryMerk,
            'model_kompatibel' => $primaryModel,
            'kompatibilitas_hp' => ! empty($daftarTipe) ? $daftarTipe : null,
            'kondisi' => $kondisi,
            'satuan' => $satuan,
            'harga_beli' => $hargaBeli,
            'harga_jual_retail' => $hargaJual,
            'margin_persen' => $marginPersen,
            'gambar' => trim((string) ($row['foto_url'] ?? '')) ?: null,
            'is_active' => true,
            'sn' => $sn,
            'min_stock' => $minStock,
            'reorder_point' => $reorderPoint,
        ]);

        $variant = SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => $sku,
            'barcode' => $barcode ?: null,
            'nama_varian' => 'Standar',
            'satuan_kode' => $satuan,
            'harga_beli' => $hargaBeli,
            'harga_jual_retail' => $hargaJual,
            'is_active' => true,
        ]);

        if (! empty($barcodeList)) {
            $produk->syncBarcodes($barcodeList, $sku);
        }

        if ($daftarTipe) {
            $ids = [];
            foreach ($daftarTipe as $t) {
                $cacheKey = $t['merk'].'|'.$t['model'];
                if (! isset($this->tipeHpCache[$cacheKey])) {
                    $this->tipeHpCache[$cacheKey] = $this->resolveOrCreateTipeHpId($t['merk'], $t['model']);
                }
                $ids[] = $this->tipeHpCache[$cacheKey];
            }
            $produk->tipeHps()->sync($ids);
        }

        // HargaTier default: retail = harga_jual; reseller/agen bila diisi kolom.
        HargaTier::updateOrCreate(
            ['produk_id' => $produk->id, 'sku_variant_id' => null, 'tier_membership_id' => null, 'tipe_konsumen' => 'retail'],
            ['is_reseller' => false, 'harga' => $hargaJual, 'nominal_tetap' => $hargaJual, 'persen_diskon' => null]
        );
        foreach (['reseller' => 'harga_reseller', 'agen' => 'harga_agen'] as $tipe => $kolom) {
            if (is_numeric($row[$kolom] ?? null)) {
                HargaTier::updateOrCreate(
                    ['produk_id' => $produk->id, 'sku_variant_id' => null, 'tier_membership_id' => null, 'tipe_konsumen' => $tipe],
                    ['is_reseller' => $tipe === 'reseller', 'harga' => round((float) $row[$kolom], 2), 'nominal_tetap' => round((float) $row[$kolom], 2), 'persen_diskon' => null]
                );
            }
        }

        // Stok awal → stok_items + StokLog + StockMutationLog 'import:excel'
        $stokNilai = 0.0;
        $cabangId = null;
        if ($gudangId) {
            $stok = StokItem::firstOrCreate(
                ['produk_id' => $produk->id, 'sku_variant_id' => $variant->id, 'gudang_id' => $gudangId],
                ['jumlah' => 0, 'jumlah_minimum' => $minStock ?? 0, 'rak_id' => $rakId]
            );

            if ($rakId && $stok->rak_id !== $rakId) {
                $stok->update(['rak_id' => $rakId]);
            }

            if ($stokAwal > 0) {
                $sebelum = $stok->jumlah;
                $stok->update(['jumlah' => $sebelum + $stokAwal, 'rak_id' => $rakId]);

                StokLog::create([
                    'gudang_id' => $gudangId,
                    'produk_id' => $produk->id,
                    'sku_variant_id' => $variant->id,
                    'user_id' => $userId,
                    'jenis' => 'import',
                    'referensi_tipe' => ImportLog::class,
                    'referensi_id' => $importLogId,
                    'jumlah_sebelum' => $sebelum,
                    'perubahan' => $stokAwal,
                    'jumlah_setelah' => $sebelum + $stokAwal,
                    'catatan' => "Stok awal import produk {$nama}",
                ]);

                StockMutationLog::create([
                    'produk_id' => $produk->id,
                    'sku_variant_id' => $variant->id,
                    'gudang_id' => $gudangId,
                    // [B-10f] Jejak pelaku import (kolom nullable, diisi bila ada)
                    'user_id' => $userId,
                    'delta' => $stokAwal,
                    'sumber' => 'import:excel',
                    'referensi_tipe' => ImportLog::class,
                    'referensi_id' => $importLogId,
                    'terjadi_at' => now(),
                ]);

                $stokNilai = round($hargaBeli * $stokAwal, 2);
            } else {
                $stok->update(['rak_id' => $rakId]);
            }

            $cabangId = Gudang::find($gudangId)?->cabang_id;
        }

        // Multi-satuan konversi (opsional)
        $rawKonversi = trim((string) ($row['konversi_satuan'] ?? $row['multi_satuan'] ?? ''));
        if ($rawKonversi !== '' && ! in_array(strtolower($rawKonversi), ['-', 'none', 'null', '0'], true)) {
            $this->simpanKonversiSatuan($produk, $rawKonversi, $satuan);
        }

        return ['stok_nilai' => $stokNilai, 'cabang_id' => $cabangId, 'produk_id' => $produk->id, 'sku' => $sku];
    }

    /**
     * [B-10f/P1-5] no_jurnal agregat stok awal per import_log + chunk.
     *
     * Chunk PERTAMA memakai format lama (tanpa suffix) supaya laporan/assert
     * yang sudah ada tetap cocok; chunk berikutnya diberi suffix -C{n}.
     * Deterministik → idempoten (tidak dobel bila job di-retry).
     */
    protected function noJurnalChunk(int $importLogId, int $indexChunk): string
    {
        $dasar = 'JRL-IMP-'.now()->format('Ymd').'-IL'.str_pad((string) $importLogId, 4, '0', STR_PAD_LEFT);

        return $indexChunk > 0
            ? $dasar.'-C'.($indexChunk + 1)
            : $dasar;
    }

    /**
     * Jurnal stok awal agregat (balance): Debit 130-01 Persediaan / Kredit 310-01 Modal (stok awal).
     * no_jurnal deterministik per import_log (+chunk) → idempoten (tidak dobel bila job retry).
     *
     * [B-10f/P1-5] Dipanggil DI DALAM transaksi chunk yang sama dengan mutasi stoknya.
     */
    protected function postJurnalStokAwal(int $importLogId, ?int $cabangId, float $totalNilai, ?int $userId, ?string $noJurnal = null): void
    {
        $noJurnal = $noJurnal ?: $this->noJurnalChunk($importLogId, 0);
        if (JurnalAkuntansi::where('no_jurnal', $noJurnal)->exists()) {
            // [B-10f] Retry parsial: ada mutasi stok baru untuk chunk yang sama.
            // Jurnal idempoten tidak boleh dobel → tandai agar bisa direkonsiliasi.
            Log::warning('Jurnal stok awal import sudah pernah diposting — mutasi baru tidak dijurnalkan', [
                'no_jurnal' => $noJurnal,
                'import_log_id' => $importLogId,
                'nilai_baru' => $totalNilai,
            ]);

            return; // sudah diposting (idempotency)
        }

        app(JurnalService::class)->post(
            $noJurnal,
            now(),
            'import',
            [
                ['akun_kode' => '130-01', 'debit' => $totalNilai, 'kredit' => 0],
                ['akun_kode' => '310-01', 'debit' => 0, 'kredit' => $totalNilai],
            ],
            'Jurnal stok awal import produk (import_log #'.$importLogId.')',
            $cabangId,
            $userId,
            ImportLog::class,
            $importLogId
        );
    }

    /**
     * Resolusi hubungan kompatibilitas / substitusi antar produk (Pass 2) berdasarkan SKU.
     * Menautkan produk_kompatibel_ids secara timbal balik (bidirectional).
     */
    protected function resolveProdukKompatibelAntarSku(array $rows, array $importedSkuToId): void
    {
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $kompatSkuStr = trim((string) ($row['produk_kompatibel_sku'] ?? ''));
            if ($sku === '' || $kompatSkuStr === '') {
                continue;
            }

            $currentProdukId = $importedSkuToId[$sku] ?? SkuVariant::where('sku', $sku)->value('produk_id');
            if (! $currentProdukId) {
                continue;
            }

            $targetSkus = array_filter(array_map('trim', preg_split('/[;,]/', $kompatSkuStr)));
            $targetProdukIds = [];

            foreach ($targetSkus as $tSku) {
                if (strcasecmp($tSku, $sku) === 0) {
                    continue;
                }
                $targetId = $importedSkuToId[$tSku] ?? SkuVariant::where('sku', $tSku)->value('produk_id');
                if ($targetId && $targetId !== $currentProdukId) {
                    $targetProdukIds[] = $targetId;
                }
            }

            if (! empty($targetProdukIds)) {
                $produk = Produk::find($currentProdukId);
                if ($produk) {
                    $produk->produkKompatibel()->syncWithoutDetaching($targetProdukIds);
                    // Tautkan timbal balik
                    foreach ($targetProdukIds as $tId) {
                        $targetProduk = Produk::find($tId);
                        if ($targetProduk) {
                            $targetProduk->produkKompatibel()->syncWithoutDetaching([$currentProdukId]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Normalisasi dan koreksi typo nama merk smartphone (fuzzy match ke master TipeHp / kamus).
     */
    public function normalizeMerkHp(?string $merkRaw): string
    {
        $raw = trim((string) ($merkRaw ?? ''));
        if ($raw === '') {
            return '';
        }

        $lower = strtolower($raw);

        // Kamus singkatan / alias lazim
        $alias = [
            'ip' => 'Apple', 'iph' => 'Apple', 'ipon' => 'Apple', 'iphone' => 'Apple', 'aple' => 'Apple', 'appl' => 'Apple',
            'ss' => 'Samsung', 'sam' => 'Samsung', 'samsng' => 'Samsung', 'samsun' => 'Samsung', 'smasung' => 'Samsung',
            'xio' => 'Xiaomi', 'xiomi' => 'Xiaomi', 'xomi' => 'Xiaomi', 'redmi' => 'Xiaomi', 'mi' => 'Xiaomi', 'poco' => 'Xiaomi',
            'op' => 'Oppo', 'opp' => 'Oppo', 'opo' => 'Oppo',
            'vv' => 'Vivo', 'vvo' => 'Vivo',
            'inf' => 'Infinix', 'infx' => 'Infinix', 'infinx' => 'Infinix',
            'real' => 'Realme', 'rlme' => 'Realme', 'relme' => 'Realme',
            'tc' => 'Tecno', 'tekno' => 'Tecno',
            'asus' => 'Asus', 'rog' => 'Asus',
            'hw' => 'Huawei', 'huaw' => 'Huawei', 'huawai' => 'Huawei',
            'uni' => 'Universal', 'univ' => 'Universal',
        ];

        if (isset($alias[$lower])) {
            return $alias[$lower];
        }

        // Cek existing di database TipeHp (case-insensitive)
        $dbMerk = TipeHp::where('is_active', true)
            ->distinct()
            ->pluck('merk')
            ->filter()
            ->all();

        foreach ($dbMerk as $m) {
            if (strcasecmp($m, $raw) === 0) {
                return $m;
            }
        }

        // Fuzzy match Levenshtein / similar_text (>= 80% kemiripan)
        $bestMatch = null;
        $highestPct = 0;
        foreach ($dbMerk as $m) {
            similar_text($lower, strtolower($m), $pct);
            if ($pct > $highestPct && $pct >= 80) {
                $highestPct = $pct;
                $bestMatch = $m;
            }
        }

        if ($bestMatch) {
            return $bestMatch;
        }

        // Bersihkan tanda baca dan rapikan kapital
        $clean = preg_replace('/[^a-zA-Z0-9\s]/', '', $raw) ?: $raw;

        return ucwords(strtolower($clean));
    }

    /**
     * Normalisasi nama model smartphone (hilangkan spasi/strip berlebih).
     */
    public function normalizeModelHp(?string $modelRaw): string
    {
        $raw = trim((string) ($modelRaw ?? ''));
        if ($raw === '') {
            return '';
        }

        // Rapikan tanda strip yang sering salah diketik jadi model baku (A-11 -> A11, Note-10 -> Note 10)
        $clean = preg_replace('/\s*-\s*/', ' ', $raw) ?? $raw;
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;

        // Jika diawali merk (cth 'Apple iPhone 13'), biarkan modelnya rapi
        return trim($clean);
    }

    /**
     * Parse kolom tipe_hp yang mendukung format:
     * - Model saja dipisah koma (jika $defaultMerk tersedia, cth: "A11, A15, A12" atau "iPhone 13 Pro Max")
     * - Format lengkap "Merk Model" dipisah ';' atau ',' (cth: "Samsung Galaxy A11; Xiaomi Redmi 9" atau "Apple iPhone 13")
     * - JSON array legacy
     *
     * @return array<int, array{merk: string, model: string}>
     */
    /**
     * Gabungkan dan deduplikasi daftar tipe [{merk, model}].
     *
     * @param  array<int, array{merk: string, model: string}>  $list1
     * @param  array<int, array{merk: string, model: string}>  $list2
     * @return array<int, array{merk: string, model: string}>
     */
    public function gabungDaftarTipe(array $list1, array $list2): array
    {
        $unique = [];
        foreach (array_merge($list1, $list2) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $merk = trim((string) ($item['merk'] ?? ''));
            $model = trim((string) ($item['model'] ?? ''));
            $kodeProduk = trim((string) ($item['kode_produk'] ?? ''));
            if ($merk === '' || $model === '') {
                continue;
            }
            $key = strtolower($merk.'|'.$model.($kodeProduk !== '' ? '|'.$kodeProduk : ''));
            if (! isset($unique[$key])) {
                $entry = [
                    'merk' => $merk,
                    'model' => $model,
                ];
                if ($kodeProduk !== '') {
                    $entry['kode_produk'] = $kodeProduk;
                }
                $unique[$key] = $entry;
            }
        }

        return array_values($unique);
    }

    /**
     * Parse kolom tipe_hp / kompatibilitas_hp yang mendukung berbagai format:
     * - "Merk:Model;Merk:Model" (format standar ekspor sistem)
     * - "Merk: Model 1, Model 2; Merk 2: Model 3"
     * - "Merk Model; Merk Model" (cth: "Samsung Galaxy A11; Xiaomi Redmi 9")
     * - "Model 1, Model 2" dengan $defaultMerk (cth: "iPhone 13, iPhone 13 Pro")
     * - JSON array string [{"merk":"...", "model":"..."}]
     *
     * @return array<int, array{merk: string, model: string}>
     */
    public function parseTipeHp(mixed $value, ?string $defaultMerk = null): array
    {
        $value = trim((string) ($value ?? ''));
        $normDefaultMerk = $this->normalizeMerkHp($defaultMerk);

        if ($value === '') {
            if ($normDefaultMerk) {
                return [['merk' => $normDefaultMerk, 'model' => 'Universal']];
            }

            return [];
        }

        // 1. Dukungan format JSON
        if (str_starts_with($value, '[') || str_starts_with($value, '{')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                // Jika single object {...}
                if (isset($decoded['merk']) || isset($decoded['model'])) {
                    $decoded = [$decoded];
                }
                $list = [];
                foreach ($decoded as $d) {
                    if (! is_array($d)) {
                        continue;
                    }
                    $mRaw = trim((string) ($d['merk'] ?? ($d['nama'] ?? '')));
                    $merk = $this->normalizeMerkHp($mRaw ?: $normDefaultMerk) ?: 'Universal';
                    $modelRaw = trim((string) ($d['model'] ?? ''));
                    $kodeRaw = trim((string) ($d['kode_produk'] ?? ''));
                    if ($modelRaw !== '') {
                        $parsedEntry = $this->extractModelDanKodeProduk($modelRaw, $merk);
                        if ($kodeRaw !== '') {
                            $parsedEntry['kode_produk'] = $kodeRaw;
                        }
                        $list[] = $parsedEntry;
                    }
                }
                if (! empty($list)) {
                    return $this->gabungDaftarTipe($list, []);
                }
            }
        }

        $manufacturers = [
            'apple' => 'Apple',
            'samsung' => 'Samsung',
            'xiaomi' => 'Xiaomi',
            'oppo' => 'Oppo',
            'vivo' => 'Vivo',
            'realme' => 'Realme',
            'infinix' => 'Infinix',
            'tecno' => 'Tecno',
            'itel' => 'Itel',
            'asus' => 'Asus',
            'huawei' => 'Huawei',
            'honor' => 'Honor',
            'google' => 'Google',
            'sony' => 'Sony',
            'nokia' => 'Nokia',
            'motorola' => 'Motorola',
            'lenovo' => 'Lenovo',
            'oneplus' => 'OnePlus',
            'universal' => 'Universal',
        ];

        $seriesToBrand = [
            'iphone' => 'Apple',
            'ipad' => 'Apple',
            'galaxy' => 'Samsung',
            'redmi' => 'Xiaomi',
            'mi' => 'Xiaomi',
            'poco' => 'Poco',
            'pixel' => 'Google',
            'rog' => 'Asus',
        ];

        // Pisahkan segmen utama: prioritas delimiter baris baru (\n) atau titik koma (;)
        $primaryDelimiter = str_contains($value, "\n") ? "\n" : (str_contains($value, ';') ? ';' : null);
        $segments = $primaryDelimiter !== null
            ? explode($primaryDelimiter, $value)
            : [$value];

        $hasil = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            // Pola A: Ada tanda titik dua ":" pemisah Merk dan Model (cth: "Apple:iPhone 13" atau "Apple: iPhone 13, iPhone 14")
            if (str_contains($segment, ':')) {
                [$merkPart, $modelPart] = explode(':', $segment, 2);
                $merkCandidate = $this->normalizeMerkHp(trim($merkPart)) ?: ($normDefaultMerk ?: 'Universal');
                // Model part bisa berupa multi-model dipisah koma
                $subModels = explode(',', $modelPart);
                foreach ($subModels as $sub) {
                    $sub = trim($sub);
                    if ($sub !== '') {
                        $hasil[] = $this->extractModelDanKodeProduk($sub, $merkCandidate);
                    }
                }

                continue;
            }

            // Pola B: Tidak ada titik dua, periksa apakah ada koma pemisah antar item
            // Jika tidak ada primaryDelimiter titik koma, maka koma adalah pemisah antar item
            $items = ($primaryDelimiter === null && str_contains($segment, ','))
                ? explode(',', $segment)
                : [$segment];

            foreach ($items as $item) {
                $item = trim($item);
                if ($item === '') {
                    continue;
                }

                // Cek kata pertama untuk deteksi brand atau series prefix
                $words = preg_split('/\s+/', $item, 2);
                $candidateFirstWord = strtolower($words[0] ?? '');
                $candidateRest = $words[1] ?? '';

                if (isset($manufacturers[$candidateFirstWord])) {
                    $detectedMerk = $manufacturers[$candidateFirstWord];
                    $modelName = ! empty($candidateRest) ? $candidateRest : $item;
                    $hasil[] = $this->extractModelDanKodeProduk($modelName, $detectedMerk);
                } elseif (isset($seriesToBrand[$candidateFirstWord])) {
                    $detectedMerk = $normDefaultMerk ?: $seriesToBrand[$candidateFirstWord];
                    $hasil[] = $this->extractModelDanKodeProduk($item, $detectedMerk);
                } else {
                    $hasil[] = $this->extractModelDanKodeProduk($item, $normDefaultMerk ?: 'Universal');
                }
            }
        }

        return $this->gabungDaftarTipe($hasil, []);
    }

    /**
     * Parse kolom kompatibilitas_hp (mendukung JSON atau format delimiter Merk:Model).
     *
     * @return array<int, array{merk: string, model: string}>
     */
    public function parseKompatibilitas(mixed $value, ?string $defaultMerk = null): array
    {
        return $this->parseTipeHp($value, $defaultMerk);
    }

    /**
     * Simpan definisi multi-satuan ke tabel produk_satuan_konversi.
     * Format: satuan:pengali[:harga_jual[:barcode]] dipisah ';'
     * Contoh: box:10:1200000:8990001;pack:5:620000
     */
    public function simpanKonversiSatuan(Produk $produk, string $rawKonversi, string $satuanDasar): void
    {
        $items = array_filter(array_map('trim', explode(';', $rawKonversi)));
        $urutan = 1;

        foreach ($items as $item) {
            $parts = array_map('trim', explode(':', $item));
            if (count($parts) < 2) {
                continue;
            }

            $satuanKode = $this->resolveOrCreateSatuan($parts[0]);
            $pengali = (float) $parts[1];

            if ($satuanKode === '' || $pengali <= 0) {
                continue;
            }

            $hargaJual = isset($parts[2]) && is_numeric($parts[2]) ? round((float) $parts[2], 2) : null;
            $barcode = isset($parts[3]) && trim($parts[3]) !== '' ? trim($parts[3]) : null;

            ProdukSatuanKonversi::updateOrCreate(
                [
                    'produk_id' => $produk->id,
                    'satuan_kode' => $satuanKode,
                ],
                [
                    'satuan_asal' => $satuanDasar,
                    'pengali' => $pengali,
                    'faktor_konversi' => $pengali,
                    'harga_jual' => $hargaJual,
                    'barcode' => $barcode,
                    'is_satuan_beli' => true,
                    'is_satuan_jual' => true,
                    'urutan' => $urutan++,
                ]
            );
        }
    }

    protected function resolveOrCreateTipeHpId(string $merk, string $model): int
    {
        $merk = trim($merk);
        $model = trim($model);

        // 1. Cek exact match merk + model
        $th = TipeHp::whereRaw('LOWER(TRIM(merk)) = ? AND LOWER(TRIM(model)) = ?', [strtolower($merk), strtolower($model)])->first();
        if ($th) {
            return $th->id;
        }

        // 2. Cek apakah ada kode produk dalam tanda kurung, misal: "iPhone 16 (A3287)"
        $extractedKode = null;
        $baseModel = $model;
        if (preg_match('/^(.*?)\s*\(([^)]+)\)$/', $model, $matches)) {
            $baseModel = trim($matches[1]);
            $extractedKode = trim($matches[2]);

            $thKode = TipeHp::whereRaw('LOWER(TRIM(merk)) = ? AND LOWER(TRIM(model)) = ? AND LOWER(TRIM(kode_produk)) = ?', [
                strtolower($merk),
                strtolower($baseModel),
                strtolower($extractedKode),
            ])->first();

            if ($thKode) {
                return $thKode->id;
            }
        }

        // 3. Cek by nama tampilan lengkap
        $namaLengkap = TipeHp::formatNamaTampilan($merk, $baseModel, null, $extractedKode);
        $thNama = TipeHp::whereRaw('LOWER(TRIM(nama)) = ?', [strtolower($namaLengkap)])->first();
        if ($thNama) {
            return $thNama->id;
        }

        // 4. Buat record baru jika belum ada
        return TipeHp::create([
            'merk' => $merk,
            'model' => $baseModel,
            'kode_produk' => $extractedKode,
            'nama' => $namaLengkap,
            'is_active' => true,
        ])->id;
    }
}
