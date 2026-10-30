<?php

namespace App\Modules\Wms\Services;

use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\TipeHp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportAtributService
{
    /**
     * Membaca dan memvalidasi file Excel/CSV atribut produk untuk menghasilkan preview.
     */
    public function preview(string $filePath, ?string $targetTipe = 'all', ?int $cabangId = null): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheetNames = $spreadsheet->getSheetNames();

        $preview = [
            'summary' => [
                'kategori' => ['total' => 0, 'baru' => 0, 'update' => 0, 'error' => 0],
                'brand' => ['total' => 0, 'baru' => 0, 'update' => 0, 'error' => 0],
                'kualitas' => ['total' => 0, 'baru' => 0, 'update' => 0, 'error' => 0],
                'satuan' => ['total' => 0, 'baru' => 0, 'update' => 0, 'error' => 0],
                'rak' => ['total' => 0, 'baru' => 0, 'update' => 0, 'error' => 0],
                'tipe_hp' => ['total' => 0, 'baru' => 0, 'update' => 0, 'error' => 0],
            ],
            'items' => [
                'kategori' => [],
                'brand' => [],
                'kualitas' => [],
                'satuan' => [],
                'rak' => [],
                'tipe_hp' => [],
            ],
            'total_baris' => 0,
            'total_valid' => 0,
            'total_error' => 0,
            'has_error' => false,
        ];

        // Jika user memilih spesifik tipe atau file hanya punya 1 sheet
        if ($targetTipe && $targetTipe !== 'all') {
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $this->sheetToArray($sheet);
            $this->parseAttributeRows($targetTipe, $rows, $preview, $cabangId);
        } else {
            // Multi-sheet mode: cocokkan nama sheet
            $handledSheet = false;
            foreach ($sheetNames as $sheetName) {
                $tipe = $this->resolveSheetType($sheetName);
                if ($tipe) {
                    $sheet = $spreadsheet->getSheetByName($sheetName);
                    $rows = $this->sheetToArray($sheet);
                    $this->parseAttributeRows($tipe, $rows, $preview, $cabangId);
                    $handledSheet = true;
                }
            }

            // Jika tidak ada sheet yang cocok dengan nama baku, coba sheet aktif dengan deteksi kolom otomatis
            if (! $handledSheet) {
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $this->sheetToArray($sheet);
                $detectedType = $this->detectTypeFromHeaders($rows[0] ?? []);
                if ($detectedType) {
                    $this->parseAttributeRows($detectedType, $rows, $preview, $cabangId);
                }
            }
        }

        foreach ($preview['summary'] as $sum) {
            $preview['total_baris'] += $sum['total'];
            $preview['total_valid'] += ($sum['baru'] + $sum['update']);
            $preview['total_error'] += $sum['error'];
        }

        $preview['has_error'] = $preview['total_error'] > 0;

        return $preview;
    }

    /**
     * Eksekusi penyimpanan data atribut ke database secara transactional.
     */
    public function execute(array $parsedItems, ?int $cabangId = null): array
    {
        $hasil = [
            'kategori' => ['sukses' => 0, 'update' => 0, 'gagal' => 0],
            'brand' => ['sukses' => 0, 'update' => 0, 'gagal' => 0],
            'kualitas' => ['sukses' => 0, 'update' => 0, 'gagal' => 0],
            'satuan' => ['sukses' => 0, 'update' => 0, 'gagal' => 0],
            'rak' => ['sukses' => 0, 'update' => 0, 'gagal' => 0],
            'tipe_hp' => ['sukses' => 0, 'update' => 0, 'gagal' => 0],
            'total_berhasil' => 0,
            'total_gagal' => 0,
        ];

        $gudangDefault = Gudang::when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))->first()
            ?? Gudang::first();

        DB::transaction(function () use (&$hasil, $parsedItems, $gudangDefault) {
            // 1. Kategori
            foreach ($parsedItems['kategori'] ?? [] as $item) {
                if (($item['status'] ?? '') === 'error') {
                    $hasil['kategori']['gagal']++;

                    continue;
                }

                $utama = trim($item['kategori_utama'] ?? '');
                $sub = trim($item['sub_kategori'] ?? '');
                $isActive = $this->parseBoolean($item['is_active'] ?? true);
                $urutan = is_numeric($item['urutan'] ?? null) ? (int) $item['urutan'] : 0;
                $slug = ! empty($item['kode_slug']) ? Str::slug($item['kode_slug']) : null;

                if (empty($utama)) {
                    $hasil['kategori']['gagal']++;

                    continue;
                }

                // Parent Kategori
                $parent = KategoriProduk::whereNull('parent_id')
                    ->where(function ($q) use ($utama) {
                        $q->whereRaw('LOWER(nama) = ?', [strtolower($utama)])
                            ->orWhere('slug', Str::slug($utama));
                    })->first();

                if (! $parent) {
                    $parentSlug = $slug && ! $sub ? $slug : $utama;
                    $parent = KategoriProduk::create([
                        'nama' => $utama,
                        'slug' => $this->unikKategoriSlug($parentSlug),
                        'urutan' => $sub ? 0 : $urutan,
                        'is_active' => true,
                    ]);
                }

                // Child / Sub-kategori jika ada
                if (! empty($sub)) {
                    $child = KategoriProduk::where('parent_id', $parent->id)
                        ->where(function ($q) use ($sub) {
                            $q->whereRaw('LOWER(nama) = ?', [strtolower($sub)])
                                ->orWhere('slug', Str::slug($sub));
                        })->first();

                    if (! $child) {
                        $childSlug = $slug ?: $this->unikKategoriSlug($sub);
                        KategoriProduk::create([
                            'parent_id' => $parent->id,
                            'nama' => $sub,
                            'slug' => $this->unikKategoriSlug($childSlug ?: $sub),
                            'urutan' => $urutan,
                            'is_active' => $isActive,
                        ]);
                        $hasil['kategori']['sukses']++;
                    } else {
                        $child->update([
                            'urutan' => $urutan ?: $child->urutan,
                            'is_active' => $isActive,
                        ]);
                        $hasil['kategori']['update']++;
                    }
                } else {
                    if ($item['status'] === 'update') {
                        $parent->update(['urutan' => $urutan, 'is_active' => $isActive]);
                        $hasil['kategori']['update']++;
                    } else {
                        $hasil['kategori']['sukses']++;
                    }
                }
            }

            // 2. Brand
            foreach ($parsedItems['brand'] ?? [] as $item) {
                if (($item['status'] ?? '') === 'error') {
                    $hasil['brand']['gagal']++;

                    continue;
                }
                $nama = trim($item['nama'] ?? '');
                $ket = trim($item['keterangan'] ?? '');
                $isActive = $this->parseBoolean($item['is_active'] ?? true);

                $brand = Brand::whereRaw('LOWER(nama) = ?', [strtolower($nama)])->first();
                if ($brand) {
                    $brand->update([
                        'keterangan' => $ket ?: $brand->keterangan,
                        'is_active' => $isActive,
                    ]);
                    $hasil['brand']['update']++;
                } else {
                    Brand::create([
                        'nama' => $nama,
                        'keterangan' => $ket ?: null,
                        'is_active' => $isActive,
                    ]);
                    $hasil['brand']['sukses']++;
                }
            }

            // 3. Kualitas
            foreach ($parsedItems['kualitas'] ?? [] as $item) {
                if (($item['status'] ?? '') === 'error') {
                    $hasil['kualitas']['gagal']++;

                    continue;
                }
                $nama = trim($item['nama'] ?? '');
                $brandName = trim($item['brand'] ?? '');
                $ket = trim($item['keterangan'] ?? '');
                $isActive = $this->parseBoolean($item['is_active'] ?? true);

                $brandId = null;
                if (! empty($brandName)) {
                    $brand = Brand::firstOrCreate(['nama' => $brandName], ['is_active' => true]);
                    $brandId = $brand->id;
                }

                $kual = KualitasProduk::whereRaw('LOWER(nama) = ?', [strtolower($nama)])
                    ->where('brand_id', $brandId)
                    ->first();

                if ($kual) {
                    $kual->update([
                        'keterangan' => $ket ?: $kual->keterangan,
                        'is_active' => $isActive,
                    ]);
                    $hasil['kualitas']['update']++;
                } else {
                    KualitasProduk::create([
                        'nama' => $nama,
                        'brand_id' => $brandId,
                        'keterangan' => $ket ?: null,
                        'is_active' => $isActive,
                    ]);
                    $hasil['kualitas']['sukses']++;
                }
            }

            // 4. Satuan
            foreach ($parsedItems['satuan'] ?? [] as $item) {
                if (($item['status'] ?? '') === 'error') {
                    $hasil['satuan']['gagal']++;

                    continue;
                }
                $kode = strtolower(trim($item['kode'] ?? ''));
                $nama = trim($item['nama'] ?? '');
                $isActive = $this->parseBoolean($item['is_active'] ?? true);

                $su = SatuanUnit::whereRaw('LOWER(kode) = ?', [$kode])->first();
                if ($su) {
                    $su->update([
                        'nama' => $nama ?: $su->nama,
                        'is_active' => $isActive,
                    ]);
                    $hasil['satuan']['update']++;
                } else {
                    SatuanUnit::create([
                        'kode' => $kode,
                        'nama' => $nama ?: strtoupper($kode),
                        'is_active' => $isActive,
                    ]);
                    $hasil['satuan']['sukses']++;
                }
            }

            // 5. Rak
            foreach ($parsedItems['rak'] ?? [] as $item) {
                if (($item['status'] ?? '') === 'error') {
                    $hasil['rak']['gagal']++;

                    continue;
                }
                $kode = trim($item['kode'] ?? '');
                $nama = trim($item['nama'] ?? '');
                $zona = trim($item['zona'] ?? '');
                $gudangRaw = trim($item['gudang'] ?? '');
                $isActive = $this->parseBoolean($item['is_active'] ?? true);

                $gudangId = $gudangDefault?->id;
                if (! empty($gudangRaw)) {
                    $g = Gudang::whereRaw('LOWER(nama) = ?', [strtolower($gudangRaw)])->first();
                    if ($g) {
                        $gudangId = $g->id;
                    }
                }

                if (! $gudangId) {
                    $hasil['rak']['gagal']++;

                    continue;
                }

                $kodeUnik = ! empty($kode) ? $kode : Str::upper(Str::slug($nama));
                $rak = Rak::where('gudang_id', $gudangId)
                    ->where(function ($q) use ($kodeUnik, $nama) {
                        $q->where('kode', $kodeUnik)
                            ->orWhere('nama', $nama);
                    })->first();

                if ($rak) {
                    $rak->update([
                        'nama' => $nama ?: $rak->nama,
                        'zona' => $zona ?: $rak->zona,
                        'is_active' => $isActive,
                    ]);
                    $hasil['rak']['update']++;
                } else {
                    Rak::create([
                        'gudang_id' => $gudangId,
                        'kode' => $kodeUnik,
                        'nama' => $nama ?: $kodeUnik,
                        'zona' => $zona ?: null,
                        'is_active' => $isActive,
                    ]);
                    $hasil['rak']['sukses']++;
                }
            }

            // 6. Tipe HP
            foreach ($parsedItems['tipe_hp'] ?? [] as $item) {
                if (($item['status'] ?? '') === 'error') {
                    $hasil['tipe_hp']['gagal']++;

                    continue;
                }
                $merk = trim($item['merk'] ?? '');
                $model = trim($item['model'] ?? '');
                $nama = trim($item['nama'] ?? '');
                $isActive = $this->parseBoolean($item['is_active'] ?? true);

                $th = TipeHp::whereRaw('LOWER(merk) = ?', [strtolower($merk)])
                    ->whereRaw('LOWER(model) = ?', [strtolower($model)])
                    ->first();

                if ($th) {
                    $th->update([
                        'nama' => $nama ?: $th->nama,
                        'is_active' => $isActive,
                    ]);
                    $hasil['tipe_hp']['update']++;
                } else {
                    TipeHp::create([
                        'merk' => $merk,
                        'model' => $model,
                        'nama' => $nama ?: "{$merk} {$model}",
                        'is_active' => $isActive,
                    ]);
                    $hasil['tipe_hp']['sukses']++;
                }
            }
        });

        foreach ($hasil as $key => $val) {
            if (is_array($val)) {
                $hasil['total_berhasil'] += ($val['sukses'] + $val['update']);
                $hasil['total_gagal'] += $val['gagal'];
            }
        }

        return $hasil;
    }

    private function resolveSheetType(string $sheetName): ?string
    {
        $clean = strtolower(trim(str_replace(['_', '-'], '', $sheetName)));

        if (str_contains($clean, 'kategori') || str_contains($clean, 'category')) {
            return 'kategori';
        }
        if (str_contains($clean, 'brand') || str_contains($clean, 'merk')) {
            return 'brand';
        }
        if (str_contains($clean, 'kualitas') || str_contains($clean, 'grade') || str_contains($clean, 'quality')) {
            return 'kualitas';
        }
        if (str_contains($clean, 'satuan') || str_contains($clean, 'unit')) {
            return 'satuan';
        }
        if (str_contains($clean, 'rak') || str_contains($clean, 'rack') || str_contains($clean, 'lokasi')) {
            return 'rak';
        }
        if (str_contains($clean, 'tipehp') || str_contains($clean, 'modelhp') || str_contains($clean, 'tipe')) {
            return 'tipe_hp';
        }

        return null;
    }

    private function detectTypeFromHeaders(array $headers): ?string
    {
        $normalized = array_map(fn ($h) => strtolower(trim((string) $h)), $headers);

        if (in_array('kategori_utama', $normalized) || in_array('sub_kategori', $normalized)) {
            return 'kategori';
        }
        if (in_array('merk', $normalized) && in_array('model', $normalized)) {
            return 'tipe_hp';
        }
        if (in_array('gudang', $normalized) && (in_array('rak', $normalized) || in_array('kode', $normalized))) {
            return 'rak';
        }
        if (in_array('kode', $normalized) && in_array('nama', $normalized)) {
            return 'satuan';
        }
        if (in_array('nama', $normalized) && in_array('keterangan', $normalized)) {
            return 'brand';
        }

        return null;
    }

    private function sheetToArray($sheet): array
    {
        $data = $sheet->toArray(null, true, true, false);
        if (empty($data)) {
            return [];
        }

        $headerRow = array_shift($data);
        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $headerRow);

        $result = [];
        foreach ($data as $row) {
            // Abaikan baris kosong total
            if (empty(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                continue;
            }

            $named = [];
            foreach ($headers as $idx => $key) {
                if ($key !== '') {
                    $named[$key] = isset($row[$idx]) ? trim((string) $row[$idx]) : '';
                }
            }
            $result[] = $named;
        }

        return $result;
    }

    private function parseAttributeRows(string $tipe, array $rows, array &$preview, ?int $cabangId = null): void
    {
        $existingBrands = Brand::pluck('id', 'nama')->mapWithKeys(fn ($id, $n) => [strtolower($n) => $id])->all();
        $existingSatuans = SatuanUnit::pluck('id', 'kode')->mapWithKeys(fn ($id, $k) => [strtolower($k) => $id])->all();
        $existingKategoriUtama = KategoriProduk::whereNull('parent_id')->pluck('id', 'nama')->mapWithKeys(fn ($id, $n) => [strtolower($n) => $id])->all();
        $existingTipeHp = TipeHp::get()->mapWithKeys(fn ($t) => [strtolower("{$t->merk}|{$t->model}") => $t->id])->all();

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // Mengingat baris 1 adalah heading
            $status = 'baru';
            $errorMsg = '';

            switch ($tipe) {
                case 'kategori':
                    $utama = $row['kategori_utama'] ?? ($row['kategori'] ?? '');
                    $sub = $row['sub_kategori'] ?? ($row['subkategori'] ?? '');
                    if (empty($utama)) {
                        $status = 'error';
                        $errorMsg = 'Kategori Utama tidak boleh kosong.';
                    } else {
                        if (! empty($sub)) {
                            $exists = KategoriProduk::whereNotNull('parent_id')
                                ->whereRaw('LOWER(nama) = ?', [strtolower($sub)])
                                ->exists();
                            $status = $exists ? 'update' : 'baru';
                        } else {
                            $status = isset($existingKategoriUtama[strtolower($utama)]) ? 'update' : 'baru';
                        }
                    }
                    $row['kategori_utama'] = $utama;
                    $row['sub_kategori'] = $sub;
                    break;

                case 'brand':
                    $nama = $row['nama'] ?? ($row['brand'] ?? ($row['merk'] ?? ''));
                    if (empty($nama)) {
                        $status = 'error';
                        $errorMsg = 'Nama Brand tidak boleh kosong.';
                    } else {
                        $status = isset($existingBrands[strtolower($nama)]) ? 'update' : 'baru';
                    }
                    $row['nama'] = $nama;
                    break;

                case 'kualitas':
                    $nama = $row['nama'] ?? ($row['kualitas'] ?? ($row['grade'] ?? ''));
                    $brand = $row['brand'] ?? '';
                    if (empty($nama)) {
                        $status = 'error';
                        $errorMsg = 'Nama Kualitas/Grade tidak boleh kosong.';
                    } else {
                        $query = KualitasProduk::whereRaw('LOWER(nama) = ?', [strtolower($nama)]);
                        if (! empty($brand)) {
                            $brandId = $existingBrands[strtolower($brand)] ?? null;
                            $query->where('brand_id', $brandId);
                        } else {
                            $query->whereNull('brand_id');
                        }
                        $status = $query->exists() ? 'update' : 'baru';
                    }
                    $row['nama'] = $nama;
                    $row['brand'] = $brand;
                    break;

                case 'satuan':
                    $kode = strtolower($row['kode'] ?? ($row['satuan'] ?? ''));
                    if (empty($kode)) {
                        $status = 'error';
                        $errorMsg = 'Kode satuan tidak boleh kosong (misal: pcs, unit, box).';
                    } else {
                        $status = isset($existingSatuans[$kode]) ? 'update' : 'baru';
                    }
                    $row['kode'] = $kode;
                    break;

                case 'rak':
                    $kode = $row['kode'] ?? '';
                    $nama = $row['nama'] ?? '';
                    if (empty($kode) && empty($nama)) {
                        $status = 'error';
                        $errorMsg = 'Kode atau Nama Rak harus diisi.';
                    } else {
                        $exists = Rak::where('kode', $kode)->orWhere('nama', $nama)->exists();
                        $status = $exists ? 'update' : 'baru';
                    }
                    $row['kode'] = $kode;
                    $row['nama'] = $nama;
                    break;

                case 'tipe_hp':
                    $merk = $row['merk'] ?? ($row['brand'] ?? '');
                    $model = $row['model'] ?? ($row['tipe'] ?? '');
                    if (empty($merk) || empty($model)) {
                        $status = 'error';
                        $errorMsg = 'Merk dan Model HP wajib diisi (misal: Apple | iPhone 11).';
                    } else {
                        $status = isset($existingTipeHp[strtolower("{$merk}|{$model}")]) ? 'update' : 'baru';
                    }
                    $row['merk'] = $merk;
                    $row['model'] = $model;
                    break;
            }

            $previewItem = array_merge($row, [
                '_baris' => $rowNum,
                'status' => $status,
                'error_msg' => $errorMsg,
            ]);

            $preview['items'][$tipe][] = $previewItem;
            $preview['summary'][$tipe]['total']++;
            if ($status === 'baru') {
                $preview['summary'][$tipe]['baru']++;
            } elseif ($status === 'update') {
                $preview['summary'][$tipe]['update']++;
            } else {
                $preview['summary'][$tipe]['error']++;
            }
        }
    }

    private function parseBoolean(mixed $val): bool
    {
        if (is_bool($val)) {
            return $val;
        }
        $str = strtolower(trim((string) $val));

        return in_array($str, ['1', 'true', 'ya', 'yes', 'aktif', 'y'], true);
    }

    /**
     * Menghasilkan slug kategori yang unik (tanpa konflik dengan slug existing).
     */
    private function unikKategoriSlug(string $basis): string
    {
        $base = Str::slug($basis) ?: 'kategori';
        $slug = $base;
        $counter = 1;
        while (KategoriProduk::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$counter);
        }

        return $slug;
    }
}
