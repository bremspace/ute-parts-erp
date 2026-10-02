<?php

namespace App\Modules\Wms\Services;

use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SidImportMap;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use XMLReader;
use ZipArchive;

class ImportSidRetailService
{
    protected array $brandCache = [];

    protected array $kategoriCache = [];

    protected array $supplierCache = [];

    protected array $satuanCache = [];

    protected array $rakCache = [];

    /**
     * Resolusi path file fleksibel (lokal, storage app/private, atau storage app).
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
     * Stream rows dari file XLSX / CSV tanpa load seluruh file ke memori.
     * Mengembalikan Generator per baris [key => val].
     */
    public function streamRows(string $filePath): \Generator
    {
        $resolved = $this->resolveFilePath($filePath);
        $ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));

        if (in_array($ext, ['csv', 'txt'], true)) {
            yield from $this->streamCsv($resolved);

            return;
        }

        yield from $this->streamXlsx($resolved);
    }

    /**
     * Streaming CSV menggunakan fgetcsv
     */
    protected function streamCsv(string $filePath): \Generator
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new \RuntimeException("Gagal membuka file CSV: {$filePath}");
        }

        $headers = null;
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if ($headers === null) {
                $headers = array_map(fn ($h) => strtoupper(trim((string) $h)), $row);
                // Strip UTF-8 BOM pada header pertama jika ada
                if (isset($headers[0])) {
                    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
                }

                continue;
            }

            if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $mapped = [];
            foreach ($headers as $idx => $key) {
                if ($key !== '') {
                    $mapped[$key] = isset($row[$idx]) ? trim((string) $row[$idx]) : '';
                }
            }
            yield $mapped;
        }

        fclose($handle);
    }

    /**
     * Streaming XML dari zip archive XLSX (sharedStrings + sheet1.xml).
     * Sangat hemat memori untuk file ratusan ribu baris (cocok 1GB RAM).
     */
    protected function streamXlsx(string $filePath): \Generator
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException("Gagal membuka file XLSX sebagai ZipArchive: {$filePath}");
        }

        $sharedStrings = $this->readSharedStrings($zip);

        // Cari sheet1.xml
        $sheetXmlContent = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXmlContent === false) {
            // Coba alternatif bila nama sheet bukan sheet1
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $stat['name'])) {
                    $sheetXmlContent = $zip->getFromIndex($i);
                    break;
                }
            }
        }

        $zip->close();

        if (! $sheetXmlContent) {
            throw new \RuntimeException("Sheet data tidak ditemukan di dalam XLSX: {$filePath}");
        }

        $reader = new XMLReader;
        $reader->XML($sheetXmlContent);

        $headers = [];
        $colMap = []; // letter => header name

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'row') {
                $rowXml = $reader->readOuterXml();
                $cells = $this->parseRowCells($rowXml, $sharedStrings);

                if (empty($headers)) {
                    // Row pertama sebagai headers
                    foreach ($cells as $colLetter => $val) {
                        $key = strtoupper(trim((string) $val));
                        $headers[$colLetter] = $key;
                    }

                    continue;
                }

                if (empty($cells)) {
                    continue;
                }

                $mapped = [];
                $hasVal = false;
                foreach ($headers as $colLetter => $headerName) {
                    if ($headerName === '') {
                        continue;
                    }
                    $val = $cells[$colLetter] ?? '';
                    if ($val !== '') {
                        $hasVal = true;
                    }
                    $mapped[$headerName] = $val;
                }

                if ($hasVal) {
                    yield $mapped;
                }
            }
        }

        $reader->close();
    }

    /**
     * Baca sharedStrings.xml secara streaming ke array
     */
    protected function readSharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');
        if ($content === false) {
            return [];
        }

        $strings = [];
        $reader = new XMLReader;
        $reader->XML($content);

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'si') {
                $outer = $reader->readOuterXml();
                $xml = simplexml_load_string($outer);
                if ($xml !== false) {
                    if (isset($xml->t)) {
                        $strings[] = (string) $xml->t;
                    } elseif (isset($xml->r)) {
                        $text = '';
                        foreach ($xml->r as $r) {
                            $text .= (string) $r->t;
                        }
                        $strings[] = $text;
                    } else {
                        $strings[] = '';
                    }
                } else {
                    $strings[] = '';
                }
            }
        }

        $reader->close();

        return $strings;
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
     * Preview import SID Retail (dry-run).
     */
    public function preview(string $filePath, int $cabangId, ?int $gudangTokoId = null, ?int $gudangPusatId = null): array
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $valid = 0;
        $invalid = 0;
        $totalBaris = 0;
        $totalStok = 0;
        $totalNilaiStok = 0.0;
        $items = [];
        $skuSeen = [];

        foreach ($this->streamRows($filePath) as $i => $row) {
            $nomorBaris = $i + 2; // +1 header
            $totalBaris++;

            $kode = trim((string) ($row['KODE_BARANG'] ?? ''));
            $nama = trim((string) ($row['NAMA'] ?? ''));
            $barcode = trim((string) ($row['KODE_BARCODE'] ?? ''));
            $satuan = trim((string) ($row['SATUAN_1'] ?? 'PCS'));
            $hpp = (float) str_replace(',', '', (string) ($row['HPP'] ?? 0));
            $hargaToko1 = (float) str_replace(',', '', (string) ($row['HARGA_TOKO_1'] ?? 0));
            $hargaPartai1 = (float) str_replace(',', '', (string) ($row['HARGA_PARTAI_1'] ?? 0));
            $hargaToko2 = (float) str_replace(',', '', (string) ($row['HARGA_TOKO_2'] ?? 0));
            $stokToko = (int) ($row['TOKO'] ?? 0);
            $stokGudang = (int) ($row['GUDANG'] ?? 0);

            $errors = [];
            if ($kode === '') {
                $errors[] = 'KODE_BARANG wajib diisi';
            }
            if ($nama === '') {
                $errors[] = 'NAMA barang wajib diisi';
            }
            if (isset($skuSeen[$kode])) {
                $errors[] = "Duplikat KODE_BARANG '{$kode}' di dalam file (baris {$skuSeen[$kode]})";
            } else {
                $skuSeen[$kode] = $nomorBaris;
            }

            if ($errors) {
                $invalid++;
            } else {
                $valid++;
                $barisStok = max(0, $stokToko) + max(0, $stokGudang);
                $totalStok += $barisStok;
                $totalNilaiStok += round($barisStok * $hpp, 2);
            }

            if (count($items) < 100) {
                $items[] = [
                    'baris' => $nomorBaris,
                    'sku' => $kode,
                    'kode_barang' => $kode,
                    'barcode' => $barcode,
                    'nama' => $nama,
                    'kategori' => $row['KATEGORI'] ?? '',
                    'sub_kategori' => $row['SUB_KATEGORI'] ?? '',
                    'satuan' => $satuan,
                    'hpp' => $hpp,
                    'harga_retail' => $hargaToko1,
                    'harga_reseller' => $hargaPartai1,
                    'harga_agen' => $hargaToko2,
                    'stok_toko' => $stokToko,
                    'stok_gudang' => $stokGudang,
                    'lokasi' => $row['LOKASI'] ?? '',
                    'valid' => empty($errors),
                    'errors' => $errors,
                    'warnings' => [],
                ];
            }
        }

        $labelStatus = $invalid === 0
            ? 'Diterima Sempurna (Format SID Retail Valid)'
            : ($valid > 0 ? 'Valid Sebagian (Ada Baris Error)' : 'Format Tidak Valid');

        return [
            'total_baris' => $totalBaris,
            'valid' => $valid,
            'invalid' => $invalid,
            'total_peringatan' => 0,
            'total_stok' => $totalStok,
            'total_nilai_stok' => round($totalNilaiStok, 2),
            'status_preview' => $invalid === 0 ? 'sempurna' : 'sebagian',
            'label_status' => $labelStatus,
            'cabang_id' => $cabangId,
            'gudang_toko_id' => $gudangTokoId,
            'gudang_pusat_id' => $gudangPusatId,
            'sampel' => array_slice($items, 0, 5),
            'items' => $items,
        ];
    }

    /**
     * Commit data import master SID Retail ke database secara streaming dan chunk 100 baris.
     */
    public function commit(
        string $filePath,
        int $importLogId,
        int $cabangId,
        ?int $gudangTokoId = null,
        ?int $gudangPusatId = null,
        ?int $userId = null
    ): array {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $sukses = 0;
        $gagal = 0;
        $totalBaris = 0;
        $detail = [];
        $totalJurnalNilai = 0.0;

        $chunk = [];
        $chunkIndex = 0;

        foreach ($this->streamRows($filePath) as $row) {
            $totalBaris++;
            $chunk[] = [
                'nomor_baris' => $totalBaris + 1,
                'data' => $row,
            ];

            if (count($chunk) >= 100) {
                $res = $this->prosesChunk($chunk, $chunkIndex, $importLogId, $cabangId, $gudangTokoId, $gudangPusatId, $userId);
                $sukses += $res['sukses'];
                $gagal += $res['gagal'];
                $totalJurnalNilai += $res['nilai_chunk'];
                $detail = array_merge($detail, $res['detail']);
                $chunk = [];
                $chunkIndex++;
            }
        }

        if (! empty($chunk)) {
            $res = $this->prosesChunk($chunk, $chunkIndex, $importLogId, $cabangId, $gudangTokoId, $gudangPusatId, $userId);
            $sukses += $res['sukses'];
            $gagal += $res['gagal'];
            $totalJurnalNilai += $res['nilai_chunk'];
            $detail = array_merge($detail, $res['detail']);
        }

        return [
            'total_baris' => $totalBaris,
            'sukses' => $sukses,
            'gagal' => $gagal,
            'jurnal_nilai' => round($totalJurnalNilai, 2),
            'detail' => $detail,
        ];
    }

    /**
     * Proses transaksi per chunk (100 baris)
     */
    protected function prosesChunk(
        array $chunk,
        int $chunkIndex,
        int $importLogId,
        int $cabangId,
        ?int $gudangTokoId,
        ?int $gudangPusatId,
        ?int $userId
    ): array {
        $sukses = 0;
        $gagal = 0;
        $nilaiChunk = 0.0;
        $detail = [];

        DB::transaction(function () use (
            $chunk,
            $chunkIndex,
            $importLogId,
            $cabangId,
            $gudangTokoId,
            $gudangPusatId,
            $userId,
            &$sukses,
            &$gagal,
            &$nilaiChunk,
            &$detail
        ) {
            foreach ($chunk as $item) {
                $nomorBaris = $item['nomor_baris'];
                $row = $item['data'];
                $kode = trim((string) ($row['KODE_BARANG'] ?? ''));

                try {
                    if ($kode === '') {
                        throw new \InvalidArgumentException('KODE_BARANG kosong');
                    }

                    $barisNilai = DB::transaction(fn () => $this->prosesSatuBaris(
                        $row,
                        $importLogId,
                        $cabangId,
                        $gudangTokoId,
                        $gudangPusatId,
                        $userId
                    ));

                    $sukses++;
                    $nilaiChunk += $barisNilai;
                    $detail[] = [
                        'baris' => $nomorBaris,
                        'kode' => $kode,
                        'status' => 'ok',
                    ];
                } catch (\Throwable $e) {
                    $gagal++;
                    $detail[] = [
                        'baris' => $nomorBaris,
                        'kode' => $kode,
                        'status' => 'gagal',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            if ($nilaiChunk > 0) {
                $this->postJurnalStokAwal($importLogId, $chunkIndex, $cabangId, $nilaiChunk, $userId);
            }
        });

        return [
            'sukses' => $sukses,
            'gagal' => $gagal,
            'nilai_chunk' => $nilaiChunk,
            'detail' => $detail,
        ];
    }

    /**
     * Memproses satu baris data SID Retail
     */
    protected function prosesSatuBaris(
        array $row,
        int $importLogId,
        int $cabangId,
        ?int $gudangTokoId,
        ?int $gudangPusatId,
        ?int $userId
    ): float {
        $kode = trim((string) ($row['KODE_BARANG'] ?? ''));
        $barcode = $this->normalizeBarcode($row['KODE_BARCODE'] ?? null);
        $nama = trim((string) ($row['NAMA'] ?? ''));
        if ($nama === '') {
            $nama = "Barang {$kode}";
        }

        $kategoriNama = trim((string) ($row['KATEGORI'] ?? ''));
        $subKategoriNama = trim((string) ($row['SUB_KATEGORI'] ?? ''));
        $supplierNama = trim((string) ($row['SUPPLIER'] ?? ''));
        $satuanClean = strtolower(trim((string) ($row['SATUAN_1'] ?? 'pcs'))) ?: 'pcs';
        $lokasiNama = trim((string) ($row['LOKASI'] ?? ''));

        $hpp = (float) str_replace(',', '', (string) ($row['HPP'] ?? 0));
        $hargaToko1 = (float) str_replace(',', '', (string) ($row['HARGA_TOKO_1'] ?? 0));
        $hargaPartai1 = (float) str_replace(',', '', (string) ($row['HARGA_PARTAI_1'] ?? 0));
        $hargaToko2 = (float) str_replace(',', '', (string) ($row['HARGA_TOKO_2'] ?? 0));

        $stokToko = max(0, (int) ($row['TOKO'] ?? 0));
        $stokGudang = max(0, (int) ($row['GUDANG'] ?? 0));

        $stokMin = isset($row['STOK_MIN']) && is_numeric($row['STOK_MIN']) ? (int) $row['STOK_MIN'] : 0;
        $stokMax = isset($row['STOK_MAX']) && is_numeric($row['STOK_MAX']) ? (int) $row['STOK_MAX'] : 0;

        // 1. Brand (dari KATEGORI)
        $brandId = null;
        if ($kategoriNama !== '') {
            if (! isset($this->brandCache[$kategoriNama])) {
                $b = Brand::firstOrCreate(
                    ['nama' => $kategoriNama],
                    ['is_active' => true]
                );
                $this->brandCache[$kategoriNama] = $b->id;
            }
            $brandId = $this->brandCache[$kategoriNama];
        }

        // 2. KategoriProduk (dari SUB_KATEGORI)
        $kategoriId = null;
        if ($subKategoriNama !== '') {
            if (! isset($this->kategoriCache[$subKategoriNama])) {
                $kp = KategoriProduk::firstOrCreate(
                    ['nama' => $subKategoriNama],
                    ['is_active' => true]
                );
                $this->kategoriCache[$subKategoriNama] = $kp->id;
            }
            $kategoriId = $this->kategoriCache[$subKategoriNama];
        }

        // 3. Supplier
        if ($supplierNama !== '') {
            if (! isset($this->supplierCache[$supplierNama])) {
                $sup = Supplier::firstOrCreate(
                    ['nama' => $supplierNama],
                    ['is_active' => true]
                );
                $this->supplierCache[$supplierNama] = $sup->id;
            }
        }

        // 4. SatuanUnit
        if (! isset($this->satuanCache[$satuanClean])) {
            $su = SatuanUnit::firstOrCreate(
                ['kode' => $satuanClean],
                ['nama' => ucfirst($satuanClean), 'is_active' => true]
            );
            $this->satuanCache[$satuanClean] = $su->kode;
        }

        // 5. Cek SidImportMap atau eksistensi SkuVariant / Produk
        $mappedVariantId = SidImportMap::getId($kode, 'barang', 'sku_variant');
        $variant = null;
        if ($mappedVariantId) {
            $variant = SkuVariant::find($mappedVariantId);
        }

        if (! $variant) {
            $variant = SkuVariant::where('sku', $kode)->first();
        }

        $produk = null;
        if ($variant) {
            $produk = $variant->produk;
        } else {
            $mappedProdukId = SidImportMap::getId($kode, 'barang', 'produk');
            if ($mappedProdukId) {
                $produk = Produk::find($mappedProdukId);
            }
        }

        // 6. Buat / update Produk
        $produkData = [
            'nama' => $nama,
            'barcode' => $barcode ?: null,
            'brand_id' => $brandId,
            'kategori_id' => $kategoriId,
            'satuan' => $satuanClean,
            'harga_beli' => $hpp,
            'harga_jual_retail' => $hargaToko1,
            'min_stock' => $stokMin,
            'reorder_point' => $stokMax,
            'is_active' => true,
        ];

        if ($produk) {
            $produk->update($produkData);
        } else {
            $produk = Produk::create($produkData);
        }

        // 7. Buat / update SkuVariant
        $variantData = [
            'produk_id' => $produk->id,
            'sku' => $kode,
            'barcode' => $barcode ?: null,
            'nama_varian' => 'Standar',
            'satuan_kode' => $satuanClean,
            'harga_beli' => $hpp,
            'harga_jual_retail' => $hargaToko1,
            'is_active' => true,
        ];

        if ($variant) {
            $variant->update($variantData);
        } else {
            $variant = SkuVariant::create($variantData);
        }

        // 8. Catat SidImportMap
        SidImportMap::setId($kode, 'barang', 'sku_variant', $variant->id);
        SidImportMap::setId($kode, 'barang', 'produk', $produk->id);

        // 9. HargaTier: retail, reseller, agen
        // Retail
        HargaTier::updateOrCreate(
            ['produk_id' => $produk->id, 'tier_membership_id' => null, 'tipe_konsumen' => 'retail'],
            [
                'sku_variant_id' => $variant->id,
                'is_reseller' => false,
                'harga' => $hargaToko1,
                'nominal_tetap' => $hargaToko1,
                'persen_diskon' => null,
            ]
        );

        // Reseller (HARGA_PARTAI_1)
        if ($hargaPartai1 > 0) {
            HargaTier::updateOrCreate(
                ['produk_id' => $produk->id, 'tier_membership_id' => null, 'tipe_konsumen' => 'reseller'],
                [
                    'sku_variant_id' => $variant->id,
                    'is_reseller' => true,
                    'harga' => $hargaPartai1,
                    'nominal_tetap' => $hargaPartai1,
                    'persen_diskon' => null,
                ]
            );
        }

        // Agen (HARGA_TOKO_2)
        if ($hargaToko2 > 0) {
            HargaTier::updateOrCreate(
                ['produk_id' => $produk->id, 'tier_membership_id' => null, 'tipe_konsumen' => 'agen'],
                [
                    'sku_variant_id' => $variant->id,
                    'is_reseller' => false,
                    'harga' => $hargaToko2,
                    'nominal_tetap' => $hargaToko2,
                    'persen_diskon' => null,
                ]
            );
        }

        // 10. Rak jika LOKASI terisi
        $rakId = null;
        $targetGudangRak = $gudangTokoId ?? $gudangPusatId;
        if ($lokasiNama !== '' && $targetGudangRak) {
            $cacheKey = "{$targetGudangRak}|{$lokasiNama}";
            if (! isset($this->rakCache[$cacheKey])) {
                $rak = Rak::firstOrCreate(
                    ['gudang_id' => $targetGudangRak, 'nama' => $lokasiNama],
                    ['kode' => Str::slug($lokasiNama), 'is_active' => true]
                );
                $this->rakCache[$cacheKey] = $rak->id;
            }
            $rakId = $this->rakCache[$cacheKey];
        }

        // 11. Stok Toko & Gudang
        $stokNilaiBaris = 0.0;

        if ($gudangTokoId && $stokToko > 0) {
            $stokNilaiBaris += $this->catatStok(
                $produk->id,
                $variant->id,
                $gudangTokoId,
                $stokToko,
                $hpp,
                $stokMin,
                $rakId,
                $importLogId,
                $userId,
                $nama
            );
        }

        if ($gudangPusatId && $stokGudang > 0) {
            $stokNilaiBaris += $this->catatStok(
                $produk->id,
                $variant->id,
                $gudangPusatId,
                $stokGudang,
                $hpp,
                $stokMin,
                null,
                $importLogId,
                $userId,
                $nama
            );
        }

        return $stokNilaiBaris;
    }

    /**
     * Catat stok ke StokItem, StokLog, dan StockMutationLog
     */
    protected function catatStok(
        int $produkId,
        int $variantId,
        int $gudangId,
        int $jumlah,
        float $hpp,
        int $stokMin,
        ?int $rakId,
        int $importLogId,
        ?int $userId,
        string $nama
    ): float {
        $stok = StokItem::firstOrCreate(
            ['produk_id' => $produkId, 'sku_variant_id' => $variantId, 'gudang_id' => $gudangId],
            ['jumlah' => 0, 'jumlah_minimum' => $stokMin, 'rak_id' => $rakId]
        );

        $sebelum = $stok->jumlah;
        $stok->update(['jumlah' => $sebelum + $jumlah] + ($rakId ? ['rak_id' => $rakId] : []));

        StokLog::create([
            'gudang_id' => $gudangId,
            'produk_id' => $produkId,
            'sku_variant_id' => $variantId,
            'user_id' => $userId,
            'jenis' => 'import',
            'referensi_tipe' => ImportLog::class,
            'referensi_id' => $importLogId,
            'jumlah_sebelum' => $sebelum,
            'perubahan' => $jumlah,
            'jumlah_setelah' => $sebelum + $jumlah,
            'catatan' => "Stok awal import SID Retail {$nama}",
        ]);

        StockMutationLog::create([
            'produk_id' => $produkId,
            'sku_variant_id' => $variantId,
            'gudang_id' => $gudangId,
            'user_id' => $userId,
            'delta' => $jumlah,
            'sumber' => 'import:sid_retail',
            'referensi_tipe' => ImportLog::class,
            'referensi_id' => $importLogId,
            'terjadi_at' => now(),
        ]);

        return round($hpp * $jumlah, 2);
    }

    /**
     * Post Jurnal Akuntansi agregat per chunk
     * No Jurnal deterministik: JRL-SID-Ymd-ILxxxx-Cn
     */
    protected function postJurnalStokAwal(int $importLogId, int $chunkIndex, int $cabangId, float $totalNilai, ?int $userId): void
    {
        $padLogId = str_pad((string) $importLogId, 4, '0', STR_PAD_LEFT);
        $noJurnal = 'JRL-SID-'.now()->format('Ymd').'-IL'.$padLogId.'-C'.($chunkIndex + 1);

        if (JurnalAkuntansi::where('no_jurnal', $noJurnal)->exists()) {
            Log::warning("Jurnal stok awal SID sudah ada: {$noJurnal}");

            return;
        }

        app(JurnalService::class)->post(
            $noJurnal,
            now(),
            'import',
            [
                ['akun_kode' => '130-01', 'debit' => $totalNilai, 'kredit' => 0],
                ['akun_kode' => '310-01', 'debit' => 0, 'kredit' => $totalNilai],
            ],
            "Jurnal stok awal import SID Retail (import_log #{$importLogId}, chunk #{$chunkIndex})",
            $cabangId,
            $userId,
            ImportLog::class,
            $importLogId
        );
    }

    /**
     * Normalisasi nomor barcode
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
}
