<?php

namespace App\Modules\Wms\Services;

use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\TipeHp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
 */
class ImportProdukService
{
    public const MAKS_BARIS = 3000;

    /** Kolom template (urutan = heading export). */
    public function templateColumns(): array
    {
        return [
            'sku', 'nama', 'barcode', 'satuan', 'kategori', 'tipe_hp', 'brand',
            'kualitas', 'harga_beli', 'harga_jual', 'harga_reseller', 'harga_agen',
            'stok_awal', 'gudang_id', 'rak_id', 'kompatibilitas_hp', 'foto_url',
        ];
    }

    /** Contoh baris untuk sheet template. */
    public function contohBaris(): array
    {
        return [
            [
                'sku' => 'LCD-IP13-ORI', 'nama' => 'LCD iPhone 13 Original', 'barcode' => '8991234500001',
                'satuan' => 'pcs', 'kategori' => 'LCD / Layar', 'tipe_hp' => 'Apple iPhone 13', 'brand' => 'Apple',
                'kualitas' => 'Original', 'harga_beli' => 850000, 'harga_jual' => 1250000,
                'harga_reseller' => 1050000, 'harga_agen' => 1000000, 'stok_awal' => 5, 'gudang_id' => 1,
                'rak_id' => 1, 'kompatibilitas_hp' => '[{"merk":"Apple","model":"iPhone 13"}]', 'foto_url' => '',
            ],
            [
                'sku' => 'BAT-SA54-ODM', 'nama' => 'Baterai Samsung A54 Grade A', 'barcode' => '8991234500002',
                'satuan' => 'pcs', 'kategori' => 'Baterai', 'tipe_hp' => 'Samsung Galaxy A54', 'brand' => 'Samsung',
                'kualitas' => 'Grade A', 'harga_beli' => 95000, 'harga_jual' => 175000,
                'harga_reseller' => 135000, 'harga_agen' => 130000, 'stok_awal' => 10, 'gudang_id' => 1,
                'rak_id' => 2, 'kompatibilitas_hp' => '[{"merk":"Samsung","model":"Galaxy A54"}]', 'foto_url' => '',
            ],
        ];
    }

    /**
     * Baca baris produk dari sheet PERTAMA (heading case-insensitive), validasi header wajib.
     * Sheet lain (mis. "Petunjuk" di template) diabaikan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseRows(string $filePath): array
    {
        // CSV → fgetcsv langsung (TANPA PhpSpreadsheet — krusial utk RAM 1GB / server ringan).
        // xlsx/xls → PhpSpreadsheet (reader type XLSX).
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($ext, ['csv', 'txt'], true)) {
            return $this->parseRowsCsv($filePath);
        }

        $sheets = Excel::toCollection(null, $filePath, null, Excel::XLSX);
        $first = $sheets->first() ?? collect();

        $headings = [];
        $rowPertama = $first->first();
        if ($rowPertama) {
            foreach ($rowPertama->toArray() as $key => $value) {
                $headings[] = strtolower(trim((string) $key));
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
            foreach ($headings as $i => $heading) {
                if ($heading === '') {
                    continue;
                }
                $value = $row[$i] ?? $row->get($heading);
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
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \Exception('Tidak dapat membuka file: '.$filePath);
        }

        // Deteksi delimiter dari sampel isi (bukan header) — template & Laplikasi pakai ';'
        $sampel = '';
        $nomor = 0;
        while ($nomor < 25 && ($baris = fgets($handle)) !== false) {
            if ($nomor >= 2) {
                $sampel .= $baris;
            }
            $nomor++;
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
                $headings = array_map(fn ($h) => strtolower(trim((string) $h)), $data);
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
        $sku = trim((string) ($row['sku'] ?? ''));
        $barcode = trim((string) ($row['barcode'] ?? ''));
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

        $satuan = trim((string) ($row['satuan'] ?? ''));
        if (! $satuan) {
            $errors[] = 'Satuan wajib diisi';
        } elseif (! SatuanUnit::where('kode', $satuan)->where('is_active', true)->exists()) {
            $errors[] = "Satuan '{$satuan}' tidak terdaftar di satuan_unit";
        }

        $hargaBeli = $row['harga_beli'] ?? '';
        $hargaJual = $row['harga_jual'] ?? '';
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
        $stokAwal = $stokAwalRaw === '' ? 0 : $stokAwalRaw;
        if (! is_numeric($stokAwal)) {
            $errors[] = 'Stok awal harus angka';
        } elseif ((float) $stokAwal < 0) {
            $errors[] = 'Stok awal tidak boleh negatif';
        }

        $gudangId = $row['gudang_id'] ?? null;
        if ($gudangId !== null && $gudangId !== '' && ! Gudang::where('id', $gudangId)->exists()) {
            $errors[] = "Gudang id '{$gudangId}' tidak ditemukan";
        } elseif ((float) $stokAwal > 0 && ($gudangId === null || $gudangId === '')) {
            $errors[] = 'gudang_id wajib diisi bila stok_awal > 0';
        }

        $rakId = $row['rak_id'] ?? null;
        if ($rakId !== null && $rakId !== '' && ! Rak::where('id', $rakId)->exists()) {
            $errors[] = "Rak id '{$rakId}' tidak ditemukan";
        }

        return $errors;
    }

    /**
     * Preview (dry-run): validasi seluruh baris, tanpa mengubah data.
     */
    public function preview(string $filePath): array
    {
        $rows = $this->parseRows($filePath);
        $konteks = $this->konteksDuplikat($rows);

        $hasil = [];
        $valid = 0;
        $invalid = 0;
        foreach ($rows as $i => $row) {
            $errors = $this->validateRow($row, $konteks);
            if ($errors) {
                $invalid++;
            } else {
                $valid++;
            }
            $nomorBaris = $i + 2; // +1 heading
            $hasil[] = [
                'baris' => $nomorBaris,
                'sku' => trim((string) ($row['sku'] ?? '')),
                'nama' => trim((string) ($row['nama'] ?? '')),
                'valid' => empty($errors),
                'errors' => $errors,
            ];
        }

        return [
            'total_baris' => count($rows),
            'valid' => $valid,
            'invalid' => $invalid,
            'sampel' => array_slice($hasil, 0, 5),
            'error_rows' => array_values(array_filter($hasil, fn ($h) => ! $h['valid'])),
        ];
    }

    /**
     * Commit: proses seluruh baris valid dalam chunk 100 (per-chunk DB transaction),
     * catat stok awal + StockMutationLog 'import:excel' + jurnal agregat 130-01/310-01.
     */
    public function commit(string $filePath, int $importLogId, ?int $userId = null): array
    {
        $rows = $this->parseRows($filePath);
        $konteks = $this->konteksDuplikat($rows);

        $sukses = 0;
        $gagal = 0;
        $detail = [];
        $totalStokNilai = 0.0;
        $cabangId = null;

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::transaction(function () use ($chunk, $konteks, $importLogId, &$sukses, &$gagal, &$detail, &$totalStokNilai, &$cabangId, $userId) {
                foreach ($chunk as $i => $row) {
                    $errors = $this->validateRow($row, $konteks);
                    $nomorBaris = 0; // dihitung di loop luar — diisi ulang di bawah
                    try {
                        if ($errors) {
                            throw new \Exception(implode('; ', $errors));
                        }
                        $result = $this->prosesSatuBaris($row, $importLogId, $userId);
                        $sukses++;
                        $totalStokNilai += $result['stok_nilai'];
                        $cabangId = $cabangId ?: $result['cabang_id'];
                        $detail[] = ['baris' => $nomorBaris, 'sku' => $row['sku'] ?? '', 'status' => 'ok'];
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
            });
        }

        // Baris number di detail diisi pasca-transaksi (nomor baris = index + 2)
        foreach ($detail as $d => $item) {
            $detail[$d]['baris'] = $d + 2;
        }

        // Jurnal agregat stok awal: Debit 130-01 / Kredit 310-01 (modal stok awal) — balance.
        // no_jurnal deterministik per import_log → idempoten (JurnalService guard duplikat).
        if ($totalStokNilai > 0) {
            $this->postJurnalStokAwal($importLogId, $cabangId, $totalStokNilai, $userId);
        }

        return [
            'total_baris' => count($rows),
            'sukses' => $sukses,
            'gagal' => $gagal,
            'detail' => $detail,
            'jurnal_nilai' => round($totalStokNilai, 2),
        ];
    }

    protected function konteksDuplikat(array $rows): array
    {
        $sku = [];
        $barcode = [];
        foreach ($rows as $row) {
            $s = trim((string) ($row['sku'] ?? ''));
            $b = trim((string) ($row['barcode'] ?? ''));
            if ($s) {
                $sku[$s] = true;
            }
            if ($b) {
                $barcode[$b] = true;
            }
        }

        return ['skuDalamFile' => $sku, 'barcodeDalamFile' => $barcode];
    }

    /**
     * Proses satu baris valid: produk + sku_variant + harga_tier + tipe_hp + stok awal.
     */
    protected function prosesSatuBaris(array $row, int $importLogId, ?int $userId): array
    {
        $nama = trim((string) $row['nama']);
        $sku = trim((string) $row['sku']);
        $barcode = trim((string) ($row['barcode'] ?? ''));
        $satuan = trim((string) $row['satuan']);
        $kategori = trim((string) ($row['kategori'] ?? 'Umum')) ?: 'Umum';
        $hargaBeli = round((float) $row['harga_beli'], 2);
        $hargaJual = round((float) $row['harga_jual'], 2);
        $stokAwal = (int) ($row['stok_awal'] ?? 0);
        $gudangId = ($row['gudang_id'] ?? '') !== '' ? (int) $row['gudang_id'] : null;
        $rakId = ($row['rak_id'] ?? '') !== '' ? (int) $row['rak_id'] : null;

        // Brand & kualitas: firstOrCreate natural key (auto-buat bila nama baru)
        $brandId = null;
        if (trim((string) ($row['brand'] ?? '')) !== '') {
            $brandId = Brand::firstOrCreate(
                ['nama' => trim((string) $row['brand'])],
                ['is_active' => true]
            )->id;
        }
        $kualitasId = null;
        if (trim((string) ($row['kualitas'] ?? '')) !== '') {
            $kualitasId = KualitasProduk::firstOrCreate(
                ['nama' => trim((string) $row['kualitas'])],
                ['is_active' => true]
            )->id;
        }

        $produk = Produk::create([
            'nama' => $nama,
            'slug' => Str::slug($nama).'-'.Str::lower(Str::random(4)),
            'deskripsi' => null,
            'kategori' => $kategori,
            'brand_id' => $brandId,
            'kualitas_id' => $kualitasId,
            'barcode' => $barcode ?: null,
            'brand_kompatibel' => $brandId ? Brand::find($brandId)?->nama : null,
            'kondisi' => 'baru',
            'satuan' => $satuan,
            'harga_beli' => $hargaBeli,
            'harga_jual_retail' => $hargaJual,
            'gambar' => trim((string) ($row['foto_url'] ?? '')) ?: null,
            'is_active' => true,
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

        // Tipe HP / kompatibilitas
        $daftarTipe = $this->parseTipeHp($row['tipe_hp'] ?? null);
        $kompat = $this->parseKompatibilitas($row['kompatibilitas_hp'] ?? null);
        if (! $daftarTipe && $kompat) {
            $daftarTipe = $kompat; // derivasi dari JSON legacy
        }
        if ($daftarTipe) {
            $ids = [];
            foreach ($daftarTipe as $t) {
                $tipeHp = TipeHp::firstOrCreate(
                    ['merk' => $t['merk'], 'model' => $t['model']],
                    ['nama' => $t['merk'].' '.$t['model'], 'is_active' => true]
                );
                $ids[] = $tipeHp->id;
            }
            $produk->tipeHps()->sync($ids);
        }
        if ($kompat) {
            $produk->update(['kompatibilitas_hp' => $kompat]);
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
        if ($gudangId && $stokAwal > 0) {
            $stok = StokItem::firstOrCreate(
                ['produk_id' => $produk->id, 'sku_variant_id' => $variant->id, 'gudang_id' => $gudangId],
                ['jumlah' => 0, 'jumlah_minimum' => 0, 'rak_id' => $rakId]
            );
            $sebelum = $stok->jumlah;
            $stok->update(['jumlah' => $sebelum + $stokAwal] + ($rakId ? ['rak_id' => $rakId] : []));

            StokLog::create([
                'gudang_id' => $gudangId,
                'produk_id' => $produk->id,
                'sku_variant_id' => $variant->id,
                'user_id' => $userId,
                'jenis' => 'import',
                'referensi_tipe' => \App\Modules\Wms\Models\ImportLog::class,
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
                'delta' => $stokAwal,
                'sumber' => 'import:excel',
                'referensi_tipe' => \App\Modules\Wms\Models\ImportLog::class,
                'referensi_id' => $importLogId,
                'terjadi_at' => now(),
            ]);

            $stokNilai = round($hargaBeli * $stokAwal, 2);
            $cabangId = Gudang::find($gudangId)?->cabang_id;
        }

        return ['stok_nilai' => $stokNilai, 'cabang_id' => $cabangId];
    }

    /**
     * Jurnal stok awal agregat (balance): Debit 130-01 Persediaan / Kredit 310-01 Modal (stok awal).
     * no_jurnal deterministik per import_log → idempoten (tidak dobel bila job retry).
     */
    protected function postJurnalStokAwal(int $importLogId, ?int $cabangId, float $totalNilai, ?int $userId): void
    {
        $noJurnal = 'JRL-IMP-'.now()->format('Ymd').'-IL'.str_pad((string) $importLogId, 4, '0', STR_PAD_LEFT);
        if (JurnalAkuntansi::where('no_jurnal', $noJurnal)->exists()) {
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
            \App\Modules\Wms\Models\ImportLog::class,
            $importLogId
        );
    }

    /**
     * Parse kolom tipe_hp: JSON array [{merk, model}] atau teks "Merk Model" dipisah ';'.
     */
    public function parseTipeHp(mixed $value): array
    {
        $value = trim((string) ($value ?? ''));
        if (! $value) {
            return [];
        }
        if (str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_map(function ($d) {
                    $merk = trim((string) ($d['merk'] ?? ($d['nama'] ?? '')));
                    $model = trim((string) ($d['model'] ?? ''));

                    return ['merk' => $merk, 'model' => $model];
                }, $decoded);
            }
        }

        $hasil = [];
        foreach (preg_split('/[;,]/', $value) as $part) {
            $part = trim($part);
            if (! $part) {
                continue;
            }
            $words = preg_split('/\s+/', $part, 2);
            $merk = $words[0] ?? '';
            $model = $words[1] ?? '';
            if ($merk) {
                $hasil[] = ['merk' => $merk, 'model' => $model];
            }
        }

        return $hasil;
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