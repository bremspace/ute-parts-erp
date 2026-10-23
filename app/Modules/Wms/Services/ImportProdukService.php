<?php

namespace App\Modules\Wms\Services;

use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
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

    protected array $brandCache = [];

    protected array $kualitasCache = [];

    protected array $kategoriCache = [];

    protected array $tipeHpCache = [];

    protected array $supplierCache = [];

    protected ?array $existingProductCache = null;

    /**
     * Normalisasi nilai barcode dari Excel/CSV.
     * Mencegah notasi ilmiah (misal 8.99123E+12) dan float tidak sengaja dari cell format General.
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
        if (preg_match('/^[0-9]+(\.[0-9]+)?[eE]\+[0-9]+$/', $str) && is_numeric($str)) {
            return sprintf('%.0f', (float) $str);
        }
        if (is_float($raw) || (is_numeric($str) && str_contains($str, '.') && (float) $str == (int) (float) $str)) {
            return sprintf('%.0f', (float) $raw);
        }

        return $str;
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
            'pack' => 'box',
            'paket' => 'box',
            'karton' => 'box',

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

    protected function normalizeImportHeading(string $heading): string
    {
        $heading = strtolower(trim($heading));

        return match ($heading) {
            'kategori utama', 'kategori_utama' => 'kategori_utama',
            'sub kategori', 'sub_kategori' => 'sub_kategori',
            'brand part', 'brand_part' => 'brand_part',
            default => $heading,
        };
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
    protected function findSimilarExistingProduct(string $nama, string $skuCurrent): ?object
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
            if ($pct >= 85) {
                return (object) array_merge($item, ['kemiripan' => round($pct)]);
            }
        }

        return null;
    }

    /**
     * Deteksi peringatan duplikasi & rekomendasi kompatibilitas cerdas.
     */
    public function checkRowWarnings(array $row, int $nomorBaris, array $konteks): array
    {
        $warnings = [];
        $nama = trim((string) ($row['nama'] ?? ''));
        $sku = trim((string) ($row['sku'] ?? ''));
        $tipeHp = trim((string) ($row['tipe_hp'] ?? ''));

        if ($nama === '') {
            return $warnings;
        }

        // 1. Cek potensi kemiripan dengan baris lain di dalam file yang sama (Intra-file)
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
                if ($pct >= 85) {
                    $pctBulat = round($pct);
                    $warnings[] = "Perhatian Kompatibilitas ({$pctBulat}% mirip dengan Baris {$prev['baris']} [{$prev['sku']}] '{$prev['nama']}'): Jika fisik sparepart identik, satukan menjadi 1 SKU dengan kolom tipe_hp multi-tipe (cth: '{$prev['tipe_hp']}; {$tipeHp}') daripada membuat master data ganda.";
                    break;
                }
            }
        }

        // 2. Cek potensi kemiripan dengan master data yang sudah ada di database
        $existing = $this->findSimilarExistingProduct($nama, $sku);
        if ($existing) {
            $warnings[] = "Perhatian Master Ganda: Mirip ({$existing->kemiripan}%) dengan produk eksisting [{$existing->sku}] '{$existing->nama}'. Pertimbangkan menambahkan tipe HP ke produk tersebut daripada membuat master produk baru.";
        }

        // 3. Peringatan non-esensial: lokasi rak & gudang (agar fleksibel/longgar tanpa menggagalkan import)
        $rakRaw = trim((string) ($row['rak_id'] ?? ''));
        if ($rakRaw !== '' && ! in_array(strtolower($rakRaw), ['-', '0', 'none', 'null', 'kosong'], true)) {
            $gudangId = $this->resolveGudangId(trim((string) ($row['gudang_id'] ?? '')));
            if (! $this->resolveRakId($rakRaw, $gudangId)) {
                $warnings[] = "Lokasi rak '{$rakRaw}' tidak ditemukan di sistem. Produk akan tetap diimport tanpa penempatan rak (kosong).";
            }
        }

        $stokAwal = (float) ($row['stok_awal'] ?? 0);
        $gudangRaw = trim((string) ($row['gudang_id'] ?? ''));
        if ($stokAwal > 0 && ($gudangRaw === '' || in_array(strtolower($gudangRaw), ['-', '0', 'none', 'null', 'kosong'], true))) {
            $warnings[] = 'Stok awal diisi tanpa gudang tujuan; stok akan ditempatkan di gudang default aktif.';
        }

        return $warnings;
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
    public function resolveGudangId(?string $gudangRaw): ?int
    {
        $raw = trim((string) $gudangRaw);
        if ($raw === '' || in_array(strtolower($raw), ['-', '0', 'none', 'null', 'kosong'], true)) {
            return null;
        }

        if (is_numeric($raw)) {
            $g = Gudang::find((int) $raw);
            if ($g) {
                return $g->id;
            }
        }

        $g = Gudang::where('kode', $raw)->orWhere('nama', $raw)->first();

        return $g?->id;
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
            $q->where('kode', $raw)->orWhere('nama', $raw);
        });

        if ($gudangId) {
            $scoped = (clone $query)->where('gudang_id', $gudangId)->value('id');
            if ($scoped) {
                return $scoped;
            }
        }

        return $query->value('id');
    }

    /** Kolom template (urutan = heading export). */
    public function templateColumns(): array
    {
        return [
            'sku', 'nama', 'barcode', 'satuan', 'kategori', 'kategori_utama', 'sub_kategori', 'brand', 'brand_part', 'supplier', 'merk_hp', 'tipe_hp',
            'kualitas', 'kondisi', 'harga_beli', 'harga_jual', 'harga_reseller', 'harga_agen',
            'min_stock', 'reorder_point', 'sn',
            'stok_awal', 'gudang_id', 'rak_id', 'produk_kompatibel_sku', 'kompatibilitas_hp', 'deskripsi', 'foto_url',
        ];
    }

    /** Contoh baris untuk sheet template. */
    public function contohBaris(): array
    {
        return [
            [
                'sku' => 'LCD-IP13-ORI', 'nama' => 'LCD iPhone 13 Original', 'barcode' => '8991234500001',
                'satuan' => 'pcs', 'kategori' => 'LCD & Touchscreen', 'kategori_utama' => '', 'sub_kategori' => '', 'brand' => 'Crown', 'brand_part' => '', 'supplier' => 'PT Supplier Jaya',
                'merk_hp' => 'Apple', 'tipe_hp' => 'iPhone 13',
                'kualitas' => 'Original', 'kondisi' => 'baru', 'harga_beli' => 850000, 'harga_jual' => 1250000,
                'harga_reseller' => 1050000, 'harga_agen' => 1000000, 'min_stock' => 5, 'reorder_point' => 10,
                'sn' => 'tidak', 'stok_awal' => 5, 'gudang_id' => 1, 'rak_id' => 1,
                'produk_kompatibel_sku' => '',
                'kompatibilitas_hp' => '',
                'deskripsi' => 'LCD assembly original untuk iPhone 13 warna hitam', 'foto_url' => '',
            ],
            [
                'sku' => 'BAT-SA54-ODM', 'nama' => 'Baterai Samsung A54 Grade A', 'barcode' => '8991234500002',
                'satuan' => 'pcs', 'kategori' => 'Baterai', 'kategori_utama' => '', 'sub_kategori' => '', 'brand' => 'OG', 'brand_part' => '', 'supplier' => 'Supplier Umum',
                'merk_hp' => 'Samsung', 'tipe_hp' => 'Galaxy A54, Galaxy A34',
                'kualitas' => 'Grade A', 'kondisi' => 'oem', 'harga_beli' => 95000, 'harga_jual' => 175000,
                'harga_reseller' => 135000, 'harga_agen' => 130000, 'min_stock' => 10, 'reorder_point' => 20,
                'sn' => 'tidak', 'stok_awal' => 10, 'gudang_id' => 1, 'rak_id' => 2,
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
    public function parseRows(string $filePath): array
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $filePath = $this->resolveFilePath($filePath);

        // CSV → fgetcsv langsung (TANPA PhpSpreadsheet — krusial utk RAM 1GB / server ringan).
        // xlsx/xls → PhpSpreadsheet (reader type XLSX).
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($ext, ['csv', 'txt'], true)) {
            return $this->parseRowsCsv($filePath);
        }

        $sheets = Excel::toCollection(null, $filePath, null, ExcelReader::XLSX);
        $first = $sheets->first() ?? collect();

        $headings = [];
        $rowPertama = $first->first();
        if ($rowPertama) {
            $arr = is_array($rowPertama) ? $rowPertama : (method_exists($rowPertama, 'toArray') ? $rowPertama->toArray() : (array) $rowPertama);
            foreach ($arr as $value) {
                $headings[] = $this->normalizeImportHeading((string) $value);
            }
        }

        $wajib = ['sku', 'nama', 'harga_jual'];
        $ada = array_intersect($headings, $wajib);
        if (count($ada) < count($wajib)) {
            throw new \Exception(
                'Format kolom tidak dikenali. Baris 1 harus berisi header: sku, nama, satuan, harga_beli, harga_jual, ... (unduh template untuk contoh).'
            );
        }

        $rows = [];
        foreach ($first->slice(1) as $row) {
            $normalized = [];
            $rowArr = is_array($row) ? $row : (method_exists($row, 'toArray') ? $row->toArray() : (array) $row);
            foreach ($headings as $i => $heading) {
                if ($heading === '') {
                    continue;
                }
                $value = $rowArr[$i] ?? null;
                $normalized[$heading] = is_scalar($value) ? trim((string) $value) : $value;
            }
            // Baris kosong total → skip
            if (count(array_filter($normalized, fn ($v) => $v !== '' && $v !== null)) === 0) {
                continue;
            }
            $rows[] = $normalized;
        }

        if (count($rows) > self::MAKS_BARIS) {
            throw new \Exception('File terlalu besar: maksimal '.self::MAKS_BARIS.' baris per import (RAM 1GB).');
        }

        return $rows;
    }

    /**
     * Baca CSV langsung pakai fgetcsv — TANPA PhpSpreadsheet (krusial utk RAM 1GB / server ringan).
     * Delimiter: auto-detect ';' atau ',' ; BOM UTF-8 dibersihkan; header case-insensitive.
     *
     * @return array<int, array<string, string>>
     */
    protected function parseRowsCsv(string $filePath): array
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

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

        $rows = [];
        $headings = [];
        $isHeading = true;
        $gagalFormat = false;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($isHeading) {
                // Bersihkan BOM UTF-8 di sel pertama
                if (isset($data[0])) {
                    $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]) ?? $data[0];
                }
                $headings = array_map(fn ($h) => $this->normalizeImportHeading((string) $h), $data);
                $isHeading = false;

                $wajib = ['sku', 'nama', 'harga_jual'];
                $ada = array_intersect($headings, $wajib);
                if (count($ada) < count($wajib)) {
                    fclose($handle);
                    throw new \Exception(
                        'Format kolom tidak dikenali. Baris 1 harus berisi header: sku, nama, satuan, harga_beli, harga_jual, ... (unduh template untuk contoh).'
                    );
                }

                continue;
            }

            $normalized = [];
            foreach ($headings as $i => $heading) {
                if ($heading === '') {
                    continue;
                }
                $value = $data[$i] ?? '';
                $normalized[$heading] = is_scalar($value) ? trim((string) $value) : $value;
            }
            // Baris kosong total → skip
            if (count(array_filter($normalized, fn ($v) => $v !== '' && $v !== null)) === 0) {
                continue;
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
     * Validasi satu baris → daftar pesan error (kosong = valid).
     */
    public function validateRow(array $row, array $konteks): array
    {
        $errors = [];
        $this->resolveHierarchyInputs($row, $errors);
        $sku = trim((string) ($row['sku'] ?? ''));
        $barcode = $this->normalizeBarcode($row['barcode'] ?? null);
        $nama = trim((string) ($row['nama'] ?? ''));

        if (! $sku) {
            $errors[] = 'SKU wajib diisi';
        } elseif (isset($konteks['skuDalamFile'][$sku]) || SkuVariant::where('sku', $sku)->exists()) {
            $errors[] = "SKU '{$sku}' duplikat (sudah ada di file atau database)";
        }
        if ($barcode) {
            if (isset($konteks['barcodeDalamFile'][$barcode])
                || SkuVariant::where('barcode', $barcode)->exists()
                || Produk::where('barcode', $barcode)->exists()) {
                $errors[] = "Barcode '{$barcode}' duplikat (sudah ada di file atau database)";
            }
        }
        if (! $nama) {
            $errors[] = 'Nama produk wajib diisi';
        }

        $satuanRaw = trim((string) ($row['satuan'] ?? ''));
        if (! $satuanRaw || in_array(strtolower($satuanRaw), ['-', 'none', 'null'], true)) {
            $satuan = 'pcs';
        } else {
            $satuan = $this->normalizeSatuan($satuanRaw);
            if (! SatuanUnit::where('kode', $satuan)->where('is_active', true)->exists()) {
                $errors[] = "Satuan '{$satuanRaw}' tidak terdaftar di satuan_unit";
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

        $stokAwalRaw = $row['stok_awal'] ?? '';
        $stokAwal = ($stokAwalRaw === '' || $stokAwalRaw === '-' || $stokAwalRaw === null) ? 0 : $stokAwalRaw;
        if (! is_numeric($stokAwal)) {
            $errors[] = 'Stok awal harus angka';
        } elseif ((float) $stokAwal < 0) {
            $errors[] = 'Stok awal tidak boleh negatif';
        }

        $gudangRaw = trim((string) ($row['gudang_id'] ?? ''));
        $gudangKosong = $gudangRaw === '' || in_array(strtolower($gudangRaw), ['-', '0', 'none', 'null', 'kosong'], true);
        if (! $gudangKosong && ! $this->resolveGudangId($gudangRaw)) {
            $errors[] = "Gudang '{$gudangRaw}' tidak ditemukan";
        } elseif ((float) $stokAwal > 0 && $gudangKosong) {
            $errors[] = 'gudang_id wajib diisi bila stok_awal > 0';
        }

        // Rak id bersifat opsional dan fleksibel: kosong, strip (-), 0, atau tidak ditemukan tidak menggagalkan baris (warning dicatat di checkRowWarnings).

        return $errors;
    }

    protected function resolveHierarchyInputs(array &$row, array &$errors): void
    {
        $utama = trim((string) ($row['kategori_utama'] ?? ''));
        $sub = trim((string) ($row['sub_kategori'] ?? ''));
        if ($sub !== '') {
            if ($utama === '') {
                $errors[] = 'Kategori utama wajib diisi untuk sub-kategori';
            } else {
                $parent = KategoriProduk::whereRaw('LOWER(nama) = ?', [mb_strtolower($utama)])->first();
                if (! $parent) {
                    $errors[] = "Kategori utama '{$utama}' tidak ditemukan";
                } else {
                    $child = KategoriProduk::where('parent_id', $parent->id)
                        ->whereRaw('LOWER(nama) = ?', [mb_strtolower($sub)])->first();
                    $row['kategori'] = $sub;
                }
            }
        } elseif ($utama !== '') {
            $row['kategori'] = $utama;
        }
        if (! empty($row['brand_part']) && empty($row['brand'])) {
            $row['brand'] = $row['brand_part'];
        }
    }

    /**
     * Preview (dry-run): validasi seluruh baris, tanpa mengubah data.
     *
     * [B-10f/P1-5] Validasi lintas cabang dijalankan di preview (fail fast)
     * supaya user tahu SEBELUM commit.
     */
    public function preview(string $filePath): array
    {
        $rows = $this->parseRows($filePath);
        // [B-10f] File lintas cabang ditolak (tidak ada mutasi stok/jurnal yg bisa dicocokkan)
        $this->cabangTunggalDariRows($rows);
        // Konteks duplikat diakumulasi per baris SETELAH validasi (prebuild file-penuh
        // menandai baris itu sendiri → semua baris dianggap duplikat).
        $konteks = ['skuDalamFile' => [], 'barcodeDalamFile' => [], 'namaDalamFile' => []];

        // Collect unique reference values for smart preview
        $refValues = [
            'kategori' => [],
            'brand' => [],
            'supplier' => [],
            'merk_hp' => [],
            'tipe_hp' => [], // array of ['merk' => ..., 'model' => ...]
            'kualitas' => [],
            'satuan' => [],
        ];

        $hasil = [];
        $valid = 0;
        $invalid = 0;
        $totalPeringatan = 0;
        foreach ($rows as $i => $row) {
            $nomorBaris = $i + 2; // +1 heading
            $errors = $this->validateRow($row, $konteks);
            $warnings = $this->checkRowWarnings($row, $nomorBaris, $konteks);
            $this->catatKonteks($row, $nomorBaris, $konteks);
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
                'sku' => trim((string) ($row['sku'] ?? '')),
                'nama' => trim((string) ($row['nama'] ?? '')),
                'valid' => empty($errors),
                'errors' => $errors,
                'warnings' => $warnings,
            ];

            // Collect reference values
            $kat = trim((string) ($row['kategori'] ?? 'Umum'));
            if ($kat !== '' && $kat !== 'Umum') {
                $refValues['kategori'][$kat] = true;
            }
            $brand = trim((string) ($row['brand'] ?? ''));
            if ($brand !== '') {
                $refValues['brand'][$brand] = true;
            }
            $supplier = trim((string) ($row['supplier'] ?? ''));
            if ($supplier !== '' && ! in_array(strtolower($supplier), ['-', '0', 'none', 'null', 'kosong'], true)) {
                $refValues['supplier'][$supplier] = true;
            }
            $merkHp = trim((string) ($row['merk_hp'] ?? ''));
            $tipeHpRaw = trim((string) ($row['tipe_hp'] ?? ''));
            if ($merkHp !== '' && $tipeHpRaw !== '') {
                $refValues['merk_hp'][$merkHp] = true;
                // Parse tipe_hp (comma separated models)
                foreach (explode(',', $tipeHpRaw) as $model) {
                    $model = trim($model);
                    if ($model !== '') {
                        $refValues['tipe_hp'][] = ['merk' => $merkHp, 'model' => $model];
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
        }

        // Build referensi_summary: check DB for each unique value
        $referensiSummary = $this->buildReferensiSummary($refValues);

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
            'error_rows' => array_values(array_filter($hasil, fn ($h) => ! $h['valid'])),
            'warning_rows' => array_values(array_filter($hasil, fn ($h) => ! empty($h['warnings']))),
            'referensi_summary' => $referensiSummary,
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
            'brand' => ['existing' => [], 'new' => []],
            'supplier' => ['existing' => [], 'new' => []],
            'merk_hp' => ['existing' => [], 'new' => []],
            'tipe_hp' => ['existing' => [], 'new' => []],
            'kualitas' => ['existing' => [], 'new' => []],
            'satuan' => ['existing' => [], 'new' => []],
        ];

        // Kategori: check by nama or slug
        foreach (array_keys($refValues['kategori']) as $kat) {
            $exists = KategoriProduk::where('nama', $kat)->orWhere('slug', Str::slug($kat))->exists();
            $summary['kategori'][$exists ? 'existing' : 'new'][] = $kat;
        }

        // Brand: check by nama
        foreach (array_keys($refValues['brand']) as $brand) {
            $exists = Brand::where('nama', $brand)->exists();
            $summary['brand'][$exists ? 'existing' : 'new'][] = $brand;
        }

        // Supplier: check by nama
        if (! empty($refValues['supplier'])) {
            foreach (array_keys($refValues['supplier']) as $sup) {
                $exists = Supplier::where('nama', $sup)->exists();
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
        foreach (array_keys($refValues['kualitas']) as $kual) {
            $exists = KualitasProduk::where('nama', $kual)->exists();
            $summary['kualitas'][$exists ? 'existing' : 'new'][] = $kual;
        }

        // Satuan: check by kode
        foreach (array_keys($refValues['satuan']) as $sat) {
            $exists = SatuanUnit::where('kode', $sat)->exists();
            $summary['satuan'][$exists ? 'existing' : 'new'][] = $sat;
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
    public function commit(string $filePath, int $importLogId, ?int $userId = null): array
    {
        $rows = $this->parseRows($filePath);
        // [B-10f] Validasi lintas cabang (fail fast, SEBELUM ada mutasi apa pun)
        $cabangId = $this->cabangTunggalDariRows($rows);
        $konteks = ['skuDalamFile' => [], 'barcodeDalamFile' => [], 'namaDalamFile' => []];

        $sukses = 0;
        $gagal = 0;
        $totalPeringatan = 0;
        $detail = [];
        $totalStokNilai = 0.0;
        $importedSkuToId = [];

        foreach (array_chunk($rows, 100) as $indexChunk => $chunk) {
            DB::transaction(function () use ($chunk, &$konteks, $importLogId, &$sukses, &$gagal, &$totalPeringatan, &$detail, &$totalStokNilai, $cabangId, $userId, $indexChunk, &$importedSkuToId) {
                // Akumulator NILAI per chunk: jurnal di-post dalam transaksi yang
                // SAMA dengan mutasi stok baris-baris chunk ini.
                $nilaiChunk = 0.0;

                foreach ($chunk as $i => $row) {
                    $nomorBaris = $indexChunk * 100 + $i + 2;
                    $errors = $this->validateRow($row, $konteks);
                    $warnings = $this->checkRowWarnings($row, $nomorBaris, $konteks);
                    $this->catatKonteks($row, $nomorBaris, $konteks);
                    try {
                        if ($errors) {
                            throw new \Exception(implode('; ', $errors));
                        }
                        // [B-10f] Savepoint per baris (nested transaction): baris gagal
                        // tidak boleh meninggalkan produk/sku setengah tertulis di dalam
                        // chunk yang sama — nilai jurnal chunk tetap = mutasi utuh.
                        $result = DB::transaction(fn () => $this->prosesSatuBaris($row, $importLogId, $userId));
                        $sukses++;
                        $nilaiChunk += $result['stok_nilai'];
                        $totalStokNilai += $result['stok_nilai'];
                        if (! empty($result['produk_id']) && ! empty($result['sku'])) {
                            $importedSkuToId[$result['sku']] = $result['produk_id'];
                        }
                        if (! empty($warnings)) {
                            $totalPeringatan++;
                        }
                        $detail[] = [
                            'baris' => $nomorBaris,
                            'sku' => $row['sku'] ?? '',
                            'nama' => $row['nama'] ?? '',
                            'status' => 'ok',
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
        }

        // Pass 2: Resolusi kompatibilitas antar produk (substitusi) via SKU
        $this->resolveProdukKompatibelAntarSku($rows, $importedSkuToId);

        // Baris number di detail diisi pasca-transaksi (nomor baris = index + 2)
        foreach ($detail as $d => $item) {
            $detail[$d]['baris'] = $d + 2;
        }

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
    protected function cabangTunggalDariRows(array $rows): ?int
    {
        $gudangIds = [];
        foreach ($rows as $row) {
            $g = trim((string) ($row['gudang_id'] ?? ''));
            if ($g !== '') {
                $gudangIds[(int) $g] = true;
            }
        }

        if ($gudangIds === []) {
            return null;
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

        if (count($perCabang) <= 1) {
            return $perCabang ? (int) array_key_first($perCabang) : null;
        }

        $namaCabang = collect($perCabang)
            ->map(fn ($_, $id) => optional($gudang->firstWhere('cabang_id', $id)?->cabang)->nama ?? "Cabang #{$id}")
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
        if ($s !== '') {
            $konteks['skuDalamFile'][$s] = true;
        }
        $b = $this->normalizeBarcode($row['barcode'] ?? null);
        if ($b !== '') {
            $konteks['barcodeDalamFile'][$b] = true;
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
     * Proses satu baris valid: produk + sku_variant + harga_tier + tipe_hp + stok awal.
     */
    protected function prosesSatuBaris(array $row, int $importLogId, ?int $userId): array
    {
        $nama = trim((string) $row['nama']);
        $sku = trim((string) $row['sku']);
        $barcode = $this->normalizeBarcode($row['barcode'] ?? null);

        $satuanRaw = trim((string) ($row['satuan'] ?? 'pcs'));
        if (! $satuanRaw || in_array(strtolower($satuanRaw), ['-', 'none', 'null'], true)) {
            $satuan = 'pcs';
        } else {
            $satuan = $this->normalizeSatuan($satuanRaw);
        }
        if (! SatuanUnit::where('kode', $satuan)->exists()) {
            SatuanUnit::create(['kode' => $satuan, 'nama' => ucfirst($satuan), 'is_active' => true]);
        }

        $kategori = trim((string) ($row['kategori'] ?? 'Umum')) ?: 'Umum';
        $hargaBeli = round((float) ($row['harga_beli'] ?? 0), 2);
        $hargaJual = round((float) ($row['harga_jual'] ?? 0), 2);
        $stokAwal = (int) ($row['stok_awal'] ?? 0);

        $gudangRaw = trim((string) ($row['gudang_id'] ?? ''));
        $gudangId = $this->resolveGudangId($gudangRaw);
        if (! $gudangId && $stokAwal > 0) {
            $cabangIdSession = session('cabang_id');
            $gudangId = Gudang::when($cabangIdSession, fn ($q, $c) => $q->where('cabang_id', $c))
                ->where('is_active', true)
                ->value('id') ?? Gudang::where('is_active', true)->value('id');
        }

        $rakRaw = trim((string) ($row['rak_id'] ?? ''));
        $rakId = $this->resolveRakId($rakRaw, $gudangId);

        $supplierRaw = trim((string) ($row['supplier'] ?? ''));
        $supplierId = $this->resolveSupplierId($supplierRaw);

        $kondisi = $this->normalizeKondisi((string) ($row['kondisi'] ?? 'baru'));
        $minStock = isset($row['min_stock']) && is_numeric($row['min_stock']) ? (int) $row['min_stock'] : null;
        $reorderPoint = isset($row['reorder_point']) && is_numeric($row['reorder_point']) ? (int) $row['reorder_point'] : null;
        $sn = $this->normalizeSn($row['sn'] ?? null);
        $deskripsi = trim((string) ($row['deskripsi'] ?? '')) ?: null;

        // Brand & kualitas: runtime cache to accelerate massive imports
        $brandId = null;
        $brandName = trim((string) ($row['brand'] ?? ''));
        if ($brandName !== '') {
            if (! isset($this->brandCache[$brandName])) {
                $this->brandCache[$brandName] = Brand::firstOrCreate(
                    ['nama' => $brandName],
                    ['is_active' => true]
                )->id;
            }
            $brandId = $this->brandCache[$brandName];
        }

        $kualitasId = null;
        $kualitasName = $this->normalizeKualitas(trim((string) ($row['kualitas'] ?? '')));
        if ($kualitasName !== '') {
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
            $kualitasId = $this->kualitasCache[$cacheKey];
        }

        // Kategori & kategori_id: runtime cache & firstOrCreate
        $kategoriId = null;
        if (! empty($kategori) && $kategori !== 'Umum') {
            $parentName = trim((string) ($row['kategori_utama'] ?? ''));
            $cacheKey = $parentName.'|'.$kategori;
            if (! isset($this->kategoriCache[$cacheKey])) {
                $parent = $parentName !== '' ? KategoriProduk::whereRaw('LOWER(nama) = ?', [mb_strtolower($parentName)])->first() : null;
                $katRow = $parent
                    ? KategoriProduk::where('parent_id', $parent->id)->whereRaw('LOWER(nama) = ?', [mb_strtolower($kategori)])->first()
                    : KategoriProduk::where('nama', $kategori)->orWhere('slug', Str::slug($kategori))->first();
                if (! $katRow) {
                    $katRow = KategoriProduk::create([
                        'nama' => $kategori,
                        'slug' => Str::slug($kategori),
                        'parent_id' => $parent?->id,
                        'is_active' => true,
                    ]);
                }
                $this->kategoriCache[$cacheKey] = $katRow->id;
            }
            $kategoriId = $this->kategoriCache[$cacheKey];
        }

        // Merk Smartphone & Tipe HP kompatibilitas
        $merkHp = $this->normalizeMerkHp($row['merk_hp'] ?? null);
        $daftarTipe = $this->parseTipeHp($row['tipe_hp'] ?? null, $merkHp);
        $kompat = $this->parseKompatibilitas($row['kompatibilitas_hp'] ?? null);
        if (! $daftarTipe && $kompat) {
            $daftarTipe = $kompat; // derivasi dari JSON legacy
        }

        $primaryMerk = $merkHp ?: ($daftarTipe[0]['merk'] ?? ($brandName ?: null));
        $primaryModel = $daftarTipe[0]['model'] ?? null;

        $produk = Produk::create([
            'nama' => $nama,
            'slug' => (Str::limit(Str::slug($nama), 180, '') ?: 'produk').'-'.Str::lower(Str::random(4)),
            'deskripsi' => $deskripsi,
            'kategori' => $kategori,
            'kategori_id' => $kategoriId,
            'brand_id' => $brandId,
            'kualitas_id' => $kualitasId,
            'supplier_id' => $supplierId,
            'barcode' => $barcode ?: null,
            'brand_kompatibel' => $primaryMerk,
            'model_kompatibel' => $primaryModel,
            'kondisi' => $kondisi,
            'satuan' => $satuan,
            'harga_beli' => $hargaBeli,
            'harga_jual_retail' => $hargaJual,
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

        if ($daftarTipe) {
            $ids = [];
            foreach ($daftarTipe as $t) {
                $cacheKey = $t['merk'].'|'.$t['model'];
                if (! isset($this->tipeHpCache[$cacheKey])) {
                    $this->tipeHpCache[$cacheKey] = TipeHp::firstOrCreate(
                        ['merk' => $t['merk'], 'model' => $t['model']],
                        ['nama' => $t['merk'].' '.$t['model'], 'is_active' => true]
                    )->id;
                }
                $ids[] = $this->tipeHpCache[$cacheKey];
            }
            $produk->tipeHps()->sync($ids);
        }
        if ($kompat) {
            $produk->update(['kompatibilitas_hp' => $kompat]);
        } elseif ($daftarTipe) {
            $produk->update(['kompatibilitas_hp' => $daftarTipe]);
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
    public function parseTipeHp(mixed $value, ?string $defaultMerk = null): array
    {
        $value = trim((string) ($value ?? ''));
        $normDefaultMerk = $this->normalizeMerkHp($defaultMerk);

        if (! $value) {
            if ($normDefaultMerk) {
                return [['merk' => $normDefaultMerk, 'model' => 'Universal']];
            }

            return [];
        }

        if (str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_map(function ($d) use ($normDefaultMerk) {
                    $mRaw = trim((string) ($d['merk'] ?? ($d['nama'] ?? '')));
                    $merk = $this->normalizeMerkHp($mRaw ?: $normDefaultMerk);
                    $model = $this->normalizeModelHp(trim((string) ($d['model'] ?? '')));

                    return ['merk' => $merk, 'model' => $model];
                }, $decoded);
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
            'asus' => 'Asus',
            'huawei' => 'Huawei',
            'google' => 'Google',
            'sony' => 'Sony',
            'nokia' => 'Nokia',
            'motorola' => 'Motorola',
            'lenovo' => 'Lenovo',
            'oneplus' => 'OnePlus',
            'universal' => 'Universal',
        ];

        $hasil = [];
        // Jika terdapat titik koma ';', prioritaskan sebagai pemisah antar entri multi-tipe/multi-brand.
        // Jika tidak ada ';', pisahkan dengan koma ','
        $delimiter = str_contains($value, ';') ? ';' : (str_contains($value, "\n") ? "\n" : ',');
        foreach (explode($delimiter, $value) as $part) {
            $part = trim($part);
            if (! $part) {
                continue;
            }

            $words = preg_split('/\s+/', $part, 2);
            $candidateFirstWord = strtolower($words[0] ?? '');
            $candidateRest = $words[1] ?? '';

            if (isset($manufacturers[$candidateFirstWord]) && ! empty($candidateRest)) {
                $hasil[] = [
                    'merk' => $manufacturers[$candidateFirstWord],
                    'model' => $this->normalizeModelHp($candidateRest),
                ];
            } else {
                $hasil[] = [
                    'merk' => $normDefaultMerk ?: 'Universal',
                    'model' => $this->normalizeModelHp($part),
                ];
            }
        }

        // Deduplikasi hasil
        $unique = [];
        foreach ($hasil as $h) {
            if (empty($h['merk']) || empty($h['model'])) {
                continue;
            }
            $key = strtolower($h['merk'].'|'.$h['model']);
            if (! isset($unique[$key])) {
                $unique[$key] = $h;
            }
        }

        return array_values($unique);
    }

    /**
     * Parse kolom kompatibilitas_hp (JSON string) → [{merk, model}].
     */
    public function parseKompatibilitas(mixed $value): array
    {
        $value = trim((string) ($value ?? ''));
        if (! $value) {
            return [];
        }
        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($d) {
            if (! is_array($d)) {
                return null;
            }
            $merk = trim((string) ($d['merk'] ?? ''));
            $model = trim((string) ($d['model'] ?? ''));
            if (! $merk) {
                return null;
            }

            return ['merk' => $merk, 'model' => $model];
        }, $decoded)));
    }
}
