<?php

namespace App\Modules\Wms\Services;

use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\TipeHp;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportMasterProdukService
{
    /**
     * Ekspor master katalog produk ke format XLSX atau CSV.
     * Struktur kolom identik 100% dengan template import (ImportProdukService::templateColumns).
     */
    public function export(string $format = 'xlsx', ?int $cabangId = null, array $filter = []): BinaryFileResponse
    {
        $fmt = $format === 'csv' ? 'csv' : 'xlsx';
        $userId = auth()->id() ?? 0;
        $slug = 'master-produk';
        $filename = $userId.'_'.$slug.'_'.now()->format('Ymd-His').'.'.$fmt;
        $path = 'exports/'.$filename;

        $rows = $this->buildMasterRows($cabangId, $filter);

        if ($fmt === 'csv') {
            $out = fopen('php://memory', 'r+');
            if (! empty($rows)) {
                fputcsv($out, array_keys($rows[0]), ',', '"', '');
                foreach ($rows as $row) {
                    $sanitized = array_map(function ($v) {
                        if (is_array($v) || is_object($v)) {
                            return json_encode($v, JSON_UNESCAPED_UNICODE);
                        }

                        return (string) $v;
                    }, array_values($row));
                    fputcsv($out, $sanitized, ',', '"', '');
                }
            }
            rewind($out);
            $csv = stream_get_contents($out);
            fclose($out);
            Storage::disk('local')->put($path, $csv);
        } else {
            $referensiRows = $this->buatReferensiSistem($cabangId);
            $petunjukRows = $this->buatPetunjukImport();
            $tambahTipeRows = $this->buatContohTambahTipeBaru();

            Excel::store(new class($rows, $referensiRows, $petunjukRows, $tambahTipeRows) implements Export, WithMultipleSheets
            {
                public function __construct(
                    private array $dataRows,
                    private array $referensiRows,
                    private array $petunjukRows,
                    private array $tambahTipeRows
                ) {}

                public function sheets(): array
                {
                    return [
                        // Sheet 1: Data Produk
                        new class($this->dataRows) implements FromArray, WithEvents, WithHeadings, WithTitle
                        {
                            public function __construct(private array $rows) {}

                            public function title(): string
                            {
                                return 'Data Produk';
                            }

                            public function headings(): array
                            {
                                return ! empty($this->rows)
                                    ? array_keys($this->rows[0])
                                    : app(ImportProdukService::class)->templateColumns();
                            }

                            public function array(): array
                            {
                                return array_map(fn ($r) => array_values((array) $r), $this->rows);
                            }

                            public function registerEvents(): array
                            {
                                return [
                                    AfterSheet::class => function (AfterSheet $event) {
                                        $sheet = $event->sheet->getDelegate();

                                        // Dropdown Satuan (Kolom G) -> Referensi Sheet 2 (kolom D)
                                        $valSatuan = $sheet->getCell('G2')->getDataValidation();
                                        $valSatuan->setType(DataValidation::TYPE_LIST);
                                        $valSatuan->setErrorStyle(DataValidation::STYLE_INFORMATION);
                                        $valSatuan->setAllowBlank(true);
                                        $valSatuan->setShowDropDown(true);
                                        $valSatuan->setFormula1("'Referensi Sistem'!\$D\$2:\$D\$100");
                                        $valSatuan->setSqref('G2:G5000');

                                        // Dropdown Kategori (Kolom I) -> Referensi Sheet 2 (kolom B)
                                        $valKat = $sheet->getCell('I2')->getDataValidation();
                                        $valKat->setType(DataValidation::TYPE_LIST);
                                        $valKat->setErrorStyle(DataValidation::STYLE_INFORMATION);
                                        $valKat->setAllowBlank(true);
                                        $valKat->setShowDropDown(true);
                                        $valKat->setFormula1("'Referensi Sistem'!\$B\$2:\$B\$100");
                                        $valKat->setSqref('I2:I5000');

                                        // Dropdown Sub Kategori (Kolom J) -> Referensi Sheet 2 (kolom H)
                                        $valSubKat = $sheet->getCell('J2')->getDataValidation();
                                        $valSubKat->setType(DataValidation::TYPE_LIST);
                                        $valSubKat->setErrorStyle(DataValidation::STYLE_INFORMATION);
                                        $valSubKat->setAllowBlank(true);
                                        $valSubKat->setShowDropDown(true);
                                        $valSubKat->setFormula1("'Referensi Sistem'!\$H\$2:\$H\$100");
                                        $valSubKat->setSqref('J2:J5000');

                                        // Dropdown Brand (Kolom K) -> Referensi Sheet 2 (kolom C)
                                        $valBrand = $sheet->getCell('K2')->getDataValidation();
                                        $valBrand->setType(DataValidation::TYPE_LIST);
                                        $valBrand->setErrorStyle(DataValidation::STYLE_INFORMATION);
                                        $valBrand->setAllowBlank(true);
                                        $valBrand->setShowDropDown(true);
                                        $valBrand->setFormula1("'Referensi Sistem'!\$C\$2:\$C\$100");
                                        $valBrand->setSqref('K2:K5000');

                                        // Dropdown Kualitas (Kolom L) -> Referensi Sheet 2 (kolom E)
                                        $valKualitas = $sheet->getCell('L2')->getDataValidation();
                                        $valKualitas->setType(DataValidation::TYPE_LIST);
                                        $valKualitas->setErrorStyle(DataValidation::STYLE_INFORMATION);
                                        $valKualitas->setAllowBlank(true);
                                        $valKualitas->setShowDropDown(true);
                                        $valKualitas->setFormula1("'Referensi Sistem'!\$E\$2:\$E\$50");
                                        $valKualitas->setSqref('L2:L5000');

                                        // Dropdown Kondisi (Kolom M)
                                        $valKondisi = $sheet->getCell('M2')->getDataValidation();
                                        $valKondisi->setType(DataValidation::TYPE_LIST);
                                        $valKondisi->setErrorStyle(DataValidation::STYLE_INFORMATION);
                                        $valKondisi->setAllowBlank(true);
                                        $valKondisi->setShowDropDown(true);
                                        $valKondisi->setFormula1('"baru,oem,bekas,refurbished"');
                                        $valKondisi->setSqref('M2:M5000');

                                        // Dropdown Supplier (Kolom N) -> Referensi Sheet 2 (kolom F)
                                        $valSupplier = $sheet->getCell('N2')->getDataValidation();
                                        $valSupplier->setType(DataValidation::TYPE_LIST);
                                        $valSupplier->setErrorStyle(DataValidation::STYLE_INFORMATION);
                                        $valSupplier->setAllowBlank(true);
                                        $valSupplier->setShowDropDown(true);
                                        $valSupplier->setFormula1("'Referensi Sistem'!\$F\$2:\$F\$100");
                                        $valSupplier->setSqref('N2:N5000');

                                        // Dropdown Merk HP (Kolom O) -> Referensi Sheet 2 (kolom A)
                                        $valMerkHp = $sheet->getCell('O2')->getDataValidation();
                                        $valMerkHp->setType(DataValidation::TYPE_LIST);
                                        $valMerkHp->setErrorStyle(DataValidation::STYLE_INFORMATION);
                                        $valMerkHp->setAllowBlank(true);
                                        $valMerkHp->setShowDropDown(true);
                                        $valMerkHp->setFormula1("'Referensi Sistem'!\$A\$2:\$A\$100");
                                        $valMerkHp->setSqref('O2:O5000');

                                        // Dropdown SN (Kolom W)
                                        $valSn = $sheet->getCell('W2')->getDataValidation();
                                        $valSn->setType(DataValidation::TYPE_LIST);
                                        $valSn->setErrorStyle(DataValidation::STYLE_INFORMATION);
                                        $valSn->setAllowBlank(true);
                                        $valSn->setShowDropDown(true);
                                        $valSn->setFormula1('"ya,tidak"');
                                        $valSn->setSqref('W2:W5000');
                                    },
                                ];
                            }
                        },

                        // Sheet 2: Referensi Sistem
                        new class($this->referensiRows) implements FromArray, WithHeadings, WithTitle
                        {
                            public function __construct(private array $rows) {}

                            public function title(): string
                            {
                                return 'Referensi Sistem';
                            }

                            public function headings(): array
                            {
                                return ['Merk HP', 'Kategori Produk', 'Brand Sparepart', 'Satuan', 'Kualitas', 'Supplier', 'Kategori Utama', 'Sub Kategori', 'Merk HP', 'Tipe HP', 'Brand Part', 'Kualitas Brand', 'Gudang', 'Rak'];
                            }

                            public function array(): array
                            {
                                return $this->rows;
                            }
                        },

                        // Sheet 3: Petunjuk
                        new class($this->petunjukRows) implements FromArray, WithTitle
                        {
                            public function __construct(private array $rows) {}

                            public function title(): string
                            {
                                return 'Petunjuk';
                            }

                            public function array(): array
                            {
                                return $this->rows;
                            }
                        },

                        // Sheet 4: Tambah Tipe Baru
                        new class($this->tambahTipeRows) implements FromArray, WithHeadings, WithTitle
                        {
                            public function __construct(private array $rows) {}

                            public function title(): string
                            {
                                return 'Tambah Tipe Baru';
                            }

                            public function headings(): array
                            {
                                return ['merk_hp_baru', 'model_hp_baru', 'keterangan'];
                            }

                            public function array(): array
                            {
                                return $this->rows;
                            }
                        },
                    ];
                }
            }, $path, 'local');
        }

        $fullPath = Storage::disk('local')->path($path);

        return response()->download($fullPath, 'master-produk-'.now()->format('Ymd-His').'.'.$fmt);
    }

    /**
     * Membangun baris data produk yang sesuai dengan template kolom.
     */
    public function buildMasterRows(?int $cabangId = null, array $filter = []): array
    {
        $query = Produk::with([
            'brand',
            'kualitas',
            'supplier',
            'kategoriRelasi.parent',
            'hargaTier',
            'skuVariants',
            'barcodes',
            'tipeHps',
            'satuanKonversi',
            'stokItems',
            'produkKompatibel',
            'stokItems.gudang',
            'stokItems.rak',
        ])
            ->withSum([
                'stokItems as total_stok' => fn ($q) => $q->when($cabangId, fn ($sub) => $sub->whereHas('gudang', fn ($g) => $g->where('cabang_id', $cabangId))),
            ], 'jumlah')
            ->when(! empty($filter['search']), fn ($q) => $q->cariPintar($filter['search'])->urutkanRelevansi($filter['search']))
            ->when(! empty($filter['kategori_ids']), fn ($q) => $q->whereIn('kategori_id', $filter['kategori_ids']))
            ->when(! empty($filter['brand_id']), fn ($q) => $q->where('brand_id', $filter['brand_id']))
            ->when(! empty($filter['brand_kompatibel']) && ! empty($filter['model_kompatibel']), fn ($q) => $q->where(fn ($sub) => $sub->where('brand_kompatibel', $filter['brand_kompatibel'])->where('model_kompatibel', $filter['model_kompatibel'])->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.merk', $filter['brand_kompatibel'])->where('tipe_hp.model', $filter['model_kompatibel']))))
            ->when(! empty($filter['brand_kompatibel']) && empty($filter['model_kompatibel']), fn ($q) => $q->where(fn ($sub) => $sub->where('brand_kompatibel', $filter['brand_kompatibel'])->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.merk', $filter['brand_kompatibel']))))
            ->when(empty($filter['brand_kompatibel']) && ! empty($filter['model_kompatibel']), fn ($q) => $q->where(fn ($sub) => $sub->where('model_kompatibel', $filter['model_kompatibel'])->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.model', $filter['model_kompatibel']))))
            ->orderBy('nama')
            ->get();

        $rows = [];
        foreach ($query as $p) {
            $merkHp = '';
            $tipeHp = '';
            $daftarKompat = collect();
            if ($p->tipeHps && $p->tipeHps->isNotEmpty()) {
                foreach ($p->tipeHps as $th) {
                    if (! empty($th->model)) {
                        $daftarKompat->push(['merk' => $th->merk ?: '', 'model' => $th->model]);
                    }
                }
            }
            if (is_array($p->kompatibilitas_hp)) {
                foreach ($p->kompatibilitas_hp as $kh) {
                    if (is_array($kh) && ! empty($kh['model'])) {
                        $daftarKompat->push(['merk' => $kh['merk'] ?? '', 'model' => $kh['model']]);
                    }
                }
            }
            if ($daftarKompat->isEmpty() && (! empty($p->brand_kompatibel) || ! empty($p->model_kompatibel))) {
                $subModels = explode(',', (string) $p->model_kompatibel);
                foreach ($subModels as $sm) {
                    $sm = trim($sm);
                    if ($sm !== '') {
                        $daftarKompat->push(['merk' => $p->brand_kompatibel ?? '', 'model' => $sm]);
                    }
                }
            }

            $daftarKompatUnique = $daftarKompat->filter(fn ($item) => ! empty($item['model']))->unique(fn ($item) => strtolower(($item['merk'] ?? '').'|'.($item['model'] ?? '')))->values();

            $merkHp = $p->brand_kompatibel ?: ($daftarKompatUnique->pluck('merk')->filter()->first() ?? '');
            $tipeHp = $daftarKompatUnique->pluck('model')->filter()->implode(', ');
            $kompatibilitasHp = $daftarKompatUnique->map(fn ($th) => trim(($th['merk'] ? $th['merk'].':' : '').$th['model']))->filter()->implode(';');
            $stokAwal = (int) ($p->total_stok ?? 0);

            $gudangNama = '';
            $rakNama = '';
            if ($p->stokItems->isNotEmpty()) {
                $stokItem = $cabangId
                    ? $p->stokItems->first(fn ($s) => (int) ($s->gudang?->cabang_id ?? 0) === (int) $cabangId)
                    : $p->stokItems->first();
                if ($stokItem) {
                    $gudangNama = $stokItem->gudang?->nama ?? ($stokItem->gudang_id ? (string) $stokItem->gudang_id : '');
                    $rakNama = $stokItem->rak?->nama ?? ($stokItem->rak_id ? (string) $stokItem->rak_id : '');
                }
            }
            if ($gudangNama === '') {
                $gudangNama = Gudang::when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))->where('is_active', true)->value('nama')
                    ?? 'Gudang Utama';
            }

            $produkKompatibelSku = $p->produkKompatibel->pluck('kode')->filter()->implode(',');
            $fotoUrl = $p->foto ?? $p->gambar ?? '';

            $kategoriImport = '';
            $subKategoriImport = '';
            if ($p->kategoriRelasi) {
                if ($p->kategoriRelasi->parent_id) {
                    $kategoriImport = $p->kategoriRelasi->parent?->nama ?? '';
                    $subKategoriImport = $p->kategoriRelasi->nama ?? '';
                } else {
                    $kategoriImport = $p->kategoriRelasi->nama ?? '';
                    $subKategoriImport = '';
                }
            } else {
                $kategoriImport = $p->kategori ?? '';
                $subKategoriImport = '';
            }

            $konversiSatuan = $p->satuanKonversi->map(function ($sk) {
                $str = $sk->satuan_kode.':'.((float) $sk->pengali + 0);
                if ($sk->harga_jual !== null) {
                    $str .= ':'.((float) $sk->harga_jual + 0);
                    if ($sk->barcode !== null && $sk->barcode !== '') {
                        $str .= ':'.$sk->barcode;
                    }
                } elseif ($sk->barcode !== null && $sk->barcode !== '') {
                    $str .= '::'.$sk->barcode;
                }

                return $str;
            })->implode(';');

            $brandImport = $p->brand?->nama ?? $p->brand_kompatibel ?? '';

            $hargaReseller = $p->hargaTier->firstWhere('tipe_konsumen', 'reseller')?->harga
                ?? $p->hargaTier->firstWhere('is_reseller', true)?->harga
                ?? $p->harga_jual_retail;

            $hargaAgen = $p->hargaTier->firstWhere('tipe_konsumen', 'agen')?->harga
                ?? $p->harga_jual_retail;

            $allBcList = $p->barcodes && $p->barcodes->isNotEmpty()
                ? $p->barcodes->sortBy('urutan')->pluck('barcode')->all()
                : $p->all_barcodes;

            $b1 = $allBcList[0] ?? ($p->barcode ?: '');
            $b2 = $allBcList[1] ?? '';
            $b3 = $allBcList[2] ?? '';
            $multiBc = count($allBcList) > 3 ? implode(', ', array_slice($allBcList, 3)) : '';

            $rows[] = [
                'sku' => $p->kode ?: ($p->skuVariants->first()?->sku ?? ''),
                'nama' => $p->nama,
                'barcode_1' => $b1,
                'barcode_2' => $b2,
                'barcode_3' => $b3,
                'multi_barcode' => $multiBc,
                'satuan' => $p->satuan ?: 'pcs',
                'konversi_satuan' => $konversiSatuan,
                'kategori' => $kategoriImport,
                'sub_kategori' => $subKategoriImport,
                'brand' => $brandImport,
                'kualitas' => $p->kualitas?->nama ?? '',
                'kondisi' => $p->kondisi ?: 'baru',
                'supplier' => $p->supplier?->nama ?? '',
                'merk_hp' => $merkHp,
                'tipe_hp' => $tipeHp,
                'harga_beli' => (float) $p->harga_beli,
                'harga_jual' => (float) $p->harga_jual_retail,
                'harga_reseller' => (float) $hargaReseller,
                'harga_agen' => (float) $hargaAgen,
                'min_stock' => (int) $p->min_stock,
                'reorder_point' => (int) $p->reorder_point,
                'sn' => $p->sn ? 'ya' : 'tidak',
                'stok_awal' => $stokAwal,
                'gudang' => $gudangNama,
                'rak' => $rakNama,
                'produk_kompatibel_sku' => $produkKompatibelSku ?: '',
                'kompatibilitas_hp' => $kompatibilitasHp ?: '',
                'deskripsi' => $p->deskripsi ?: '',
                'foto_url' => $fotoUrl,
            ];
        }

        return $rows;
    }

    public function buatReferensiSistem(?int $cabangId = null): array
    {
        $cabangId = $cabangId ?? session('cabang_id') ?? auth()->user()?->cabang_id;
        $merks = TipeHp::where('is_active', true)->distinct()->orderBy('merk')->pluck('merk')->filter()->values()->all();
        if (empty($merks)) {
            $merks = ['Apple', 'Samsung', 'Xiaomi', 'Oppo', 'Vivo', 'Realme', 'Infinix', 'Tecno', 'Asus', 'Universal'];
        }

        $kategoriUtama = KategoriProduk::where('is_active', true)
            ->where(fn ($query) => $query->whereNull('parent_id')->orWhere('parent_id', 0))
            ->orderBy('nama')->pluck('nama')->all();
        $kategoris = KategoriProduk::where('is_active', true)->orderBy('nama')->pluck('nama')->all();
        if (empty($kategoris)) {
            $kategoris = ['LCD & Touchscreen', 'Baterai', 'Fleksibel & Board', 'Kamera', 'IC & Chipset', 'Housing & Backdoor'];
            $kategoriUtama = $kategoris;
        }

        $subKategoris = KategoriProduk::where('is_active', true)
            ->whereNotNull('parent_id')->where('parent_id', '>', 0)
            ->orderBy('nama')->pluck('nama')->all();
        if (empty($subKategoris)) {
            $subKategoris = ['Fullset Assembly', 'Glass Only', 'Baterai Tanam', 'Fleksibel Charger', 'Fleksibel On Off'];
        }

        $brands = Brand::where('is_active', true)->orderBy('nama')->pluck('nama')->all();
        if (empty($brands)) {
            $brands = ['Crown', 'OG', 'V-Gen', 'Hi-Power', 'Life', 'OEM'];
        }

        $satuans = SatuanUnit::where('is_active', true)->orderBy('kode')->pluck('kode')->all();
        if (empty($satuans)) {
            $satuans = ['pcs', 'set', 'pack', 'box', 'roll', 'strip', 'unit', 'meter'];
        }

        $kualitasList = KualitasProduk::where('is_active', true)->orderBy('nama')->pluck('nama')->all();
        if (empty($kualitasList)) {
            $kualitasList = ['Original', 'Grade A', 'Grade B', 'OEM', 'Refurbished', 'Universal'];
        }

        $suppliers = Supplier::where('is_active', true)->orderBy('nama')->pluck('nama')->all();
        if (empty($suppliers)) {
            $suppliers = ['Supplier Utama', 'PT Sumber Sparepart', 'CV Mega Jaya', 'Global Parts Ltd'];
        }

        $gudangList = Gudang::when($cabangId, fn ($q, $c) => $q->where('cabang_id', $c))
            ->where('is_active', true)
            ->orderBy('nama')
            ->pluck('nama')
            ->unique()
            ->values()
            ->all();
        if (empty($gudangList)) {
            $gudangList = Gudang::where('is_active', true)->orderBy('nama')->pluck('nama')->unique()->values()->all();
            if (empty($gudangList)) {
                $gudangList = ['Gudang Utama', 'Gudang Display', 'Gudang Transit'];
            }
        }

        $rakList = Rak::when($cabangId, fn ($q, $c) => $q->whereHas('gudang', fn ($g) => $g->where('cabang_id', $c)))
            ->orderBy('nama')
            ->pluck('nama')
            ->unique()
            ->values()
            ->all();
        if (empty($rakList)) {
            $rakList = Rak::orderBy('nama')->pluck('nama')->unique()->values()->all();
            if (empty($rakList)) {
                $rakList = ['Rak A1', 'Rak A2', 'Rak B1', 'Rak B2', 'Rak C1', 'Rak Display'];
            }
        }

        $tipeHpPairs = TipeHp::where('is_active', true)->orderBy('merk')->orderBy('model')->take(100)->get(['merk', 'model']);

        $max = max(
            count($merks), count($kategoris), count($brands), count($satuans),
            count($kualitasList), count($suppliers), count($kategoriUtama), count($subKategoris),
            $tipeHpPairs->count(), count($gudangList), count($rakList)
        );

        $referensiRows = [];
        for ($i = 0; $i < $max; $i++) {
            $pair = $tipeHpPairs->get($i);
            $referensiRows[] = [
                $merks[$i] ?? '',
                $kategoris[$i] ?? '',
                $brands[$i] ?? '',
                $satuans[$i] ?? '',
                $kualitasList[$i] ?? '',
                $suppliers[$i] ?? '',
                $kategoriUtama[$i] ?? '',
                $subKategoris[$i] ?? '',
                $pair ? $pair->merk : '',
                $pair ? $pair->model : '',
                $brands[$i] ?? '',
                $kualitasList[$i] ?? '',
                $gudangList[$i] ?? '',
                $rakList[$i] ?? '',
            ];
        }

        return $referensiRows;
    }

    public function buatPetunjukImport(): array
    {
        return [
            ['PETUNJUK IMPORT MASTER PRODUK ERP'],
            [''],
            ['1. Kolom Wajib Diisi: sku, nama, harga_jual'],
            ['2. Kolom Opsional: barcode_1 (utama), barcode_2, barcode_3, multi_barcode (alternatif/tambahan dipisah koma), satuan, konversi_satuan, kategori, sub_kategori, brand, kualitas, kondisi, supplier, merk_hp, tipe_hp, harga_beli, harga_reseller, harga_agen, min_stock, reorder_point, sn, stok_awal, gudang, rak, produk_kompatibel_sku, kompatibilitas_hp, deskripsi, foto_url, cabang'],
            ['3. Kolom satuan: gunakan kode satuan dari Sheet Referensi Sistem (cth: pcs, set, pack, box)'],
            ['4. Kolom konversi_satuan: format satuan:pengali:harga_jual dipisah titik koma (cth: box:10:120000;pack:5:62000)'],
            ['5. Kolom kondisi: hanya menerima: baru, oem, bekas, refurbished (default: baru)'],
            ['6. Kolom sn: ya / tidak (default: tidak)'],
            ['7. Kolom gudang & rak: isi nama gudang dan rak sesuai cabang aktif (lihat Sheet Referensi Sistem). Jika dikosongkan, otomatis dialokasikan ke Gudang Utama dan Rak default cabang aktif.'],
            ['8. Kolom cabang (opsional): jika diisi, sistem memvalidasi data untuk cabang tersebut. Jika tidak diisi, otomatis menggunakan cabang aktif saat sesi impor.'],
            ['9. Multi Barcode: barcode_1 adalah barcode/QR utama produk. barcode_2, barcode_3, dst. serta multi_barcode adalah barcode alternatif/supplier. Seluruh barcode otomatis diindeks agar pemindaian langsung merujuk ke SKU produk.'],
            ['10. Round-trip: File hasil ekspor master produk memiliki struktur yang identik dan dapat langsung diimpor kembali ke sistem.'],
        ];
    }

    public function buatContohTambahTipeBaru(): array
    {
        return [
            ['Samsung', 'Galaxy A55 5G', 'Tipe baru rilis 2024'],
            ['Xiaomi', 'Redmi Note 13 Pro+', 'Varian 5G Dimensity'],
        ];
    }
}
