<?php

namespace App\Modules\Wms\Exports;

use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\ImportProdukService;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

/**
 * [T-43] Template Excel import master produk dinamis interaktif.
 * Sheet 1: Template Produk (Dropdown data validation otomatis).
 * Sheet 2: Tambah Tipe Baru (Slot fleksibel jika merk/model belum terdaftar).
 * Sheet 3: Referensi Sistem (Kamus resmi Merk HP, Kategori, Brand part, Satuan, Kualitas).
 * Sheet 4: Petunjuk Pengisian.
 *
 * [B-04] Wajib implement Export (marker interface).
 */
class ImportProdukTemplateExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            // Sheet 1: Template Produk
            new class implements FromCollection, WithEvents, WithHeadings, WithTitle
            {
                public function title(): string
                {
                    return 'Template Produk';
                }

                public function headings(): array
                {
                    return app(ImportProdukService::class)->templateColumns();
                }

                public function collection(): Enumerable
                {
                    return collect(app(ImportProdukService::class)->contohBaris());
                }

                public function registerEvents(): array
                {
                    return [
                        AfterSheet::class => function (AfterSheet $event) {
                            $sheet = $event->sheet->getDelegate();

                            // 1. Dropdown Satuan (Kolom D) -> Referensi Sheet 3
                            $valSatuan = $sheet->getCell('D2')->getDataValidation();
                            $valSatuan->setType(DataValidation::TYPE_LIST);
                            $valSatuan->setErrorStyle(DataValidation::STYLE_INFORMATION);
                            $valSatuan->setAllowBlank(true);
                            $valSatuan->setShowDropDown(true);
                            $valSatuan->setFormula1("'Referensi Sistem'!\$D\$2:\$D\$100");
                            $valSatuan->setSqref('D2:D1000');

                            // 2. Dropdown Kategori Utama (Kolom F) -> Referensi Sheet 3 (Kolom B)
                            $valKat = $sheet->getCell('F2')->getDataValidation();
                            $valKat->setType(DataValidation::TYPE_LIST);
                            $valKat->setErrorStyle(DataValidation::STYLE_INFORMATION);
                            $valKat->setAllowBlank(true);
                            $valKat->setShowDropDown(true);
                            $valKat->setFormula1("'Referensi Sistem'!\$B\$2:\$B\$100");
                            $valKat->setSqref('F2:F1000');

                            // 3. Dropdown Sub Kategori (Kolom G) -> Referensi Sheet 3 (Kolom H)
                            $valSubKat = $sheet->getCell('G2')->getDataValidation();
                            $valSubKat->setType(DataValidation::TYPE_LIST);
                            $valSubKat->setErrorStyle(DataValidation::STYLE_INFORMATION);
                            $valSubKat->setAllowBlank(true);
                            $valSubKat->setShowDropDown(true);
                            $valSubKat->setFormula1("'Referensi Sistem'!\$H\$2:\$H\$100");
                            $valSubKat->setSqref('G2:G1000');

                            // 4. Dropdown Brand Part (Kolom H) -> Referensi Sheet 3 (Kolom C)
                            $valBrand = $sheet->getCell('H2')->getDataValidation();
                            $valBrand->setType(DataValidation::TYPE_LIST);
                            $valBrand->setErrorStyle(DataValidation::STYLE_INFORMATION);
                            $valBrand->setAllowBlank(true);
                            $valBrand->setShowDropDown(true);
                            $valBrand->setFormula1("'Referensi Sistem'!\$C\$2:\$C\$100");
                            $valBrand->setSqref('H2:H1000');

                            // 5. Dropdown Kualitas (Kolom I) -> Referensi Sheet 3 (Kolom E)
                            $valKualitas = $sheet->getCell('I2')->getDataValidation();
                            $valKualitas->setType(DataValidation::TYPE_LIST);
                            $valKualitas->setErrorStyle(DataValidation::STYLE_INFORMATION);
                            $valKualitas->setAllowBlank(true);
                            $valKualitas->setShowDropDown(true);
                            $valKualitas->setFormula1("'Referensi Sistem'!\$E\$2:\$E\$50");
                            $valKualitas->setSqref('I2:I1000');

                            // 6. Dropdown Kondisi (Kolom J)
                            $valKondisi = $sheet->getCell('J2')->getDataValidation();
                            $valKondisi->setType(DataValidation::TYPE_LIST);
                            $valKondisi->setErrorStyle(DataValidation::STYLE_INFORMATION);
                            $valKondisi->setAllowBlank(true);
                            $valKondisi->setShowDropDown(true);
                            $valKondisi->setFormula1('"baru,oem,bekas,refurbished"');
                            $valKondisi->setSqref('J2:J1000');

                            // 7. Dropdown Supplier (Kolom K) -> Referensi Sheet 3 (Kolom F)
                            $valSupplier = $sheet->getCell('K2')->getDataValidation();
                            $valSupplier->setType(DataValidation::TYPE_LIST);
                            $valSupplier->setErrorStyle(DataValidation::STYLE_INFORMATION);
                            $valSupplier->setAllowBlank(true);
                            $valSupplier->setShowDropDown(true);
                            $valSupplier->setFormula1("'Referensi Sistem'!\$F\$2:\$F\$100");
                            $valSupplier->setSqref('K2:K1000');

                            // 8. Dropdown Merk HP (Kolom L) -> Referensi Sheet 3 (Kolom A)
                            $valMerkHp = $sheet->getCell('L2')->getDataValidation();
                            $valMerkHp->setType(DataValidation::TYPE_LIST);
                            $valMerkHp->setErrorStyle(DataValidation::STYLE_INFORMATION);
                            $valMerkHp->setAllowBlank(true);
                            $valMerkHp->setShowDropDown(true);
                            $valMerkHp->setFormula1("'Referensi Sistem'!\$A\$2:\$A\$100");
                            $valMerkHp->setSqref('L2:L1000');

                            // 9. Dropdown Serial Number (Kolom T)
                            $valSn = $sheet->getCell('T2')->getDataValidation();
                            $valSn->setType(DataValidation::TYPE_LIST);
                            $valSn->setErrorStyle(DataValidation::STYLE_INFORMATION);
                            $valSn->setAllowBlank(true);
                            $valSn->setShowDropDown(true);
                            $valSn->setFormula1('"ya,tidak"');
                            $valSn->setSqref('T2:T1000');

                            // 10. Dropdown Gudang Penyimpanan (Kolom V) -> Referensi Sheet 3 (Kolom M)
                            $valGudang = $sheet->getCell('V2')->getDataValidation();
                            $valGudang->setType(DataValidation::TYPE_LIST);
                            $valGudang->setErrorStyle(DataValidation::STYLE_INFORMATION);
                            $valGudang->setAllowBlank(true);
                            $valGudang->setShowDropDown(true);
                            $valGudang->setFormula1("'Referensi Sistem'!\$M\$2:\$M\$100");
                            $valGudang->setSqref('V2:V1000');

                            // 11. Dropdown Rak Penyimpanan (Kolom W) -> Referensi Sheet 3 (Kolom N)
                            $valRak = $sheet->getCell('W2')->getDataValidation();
                            $valRak->setType(DataValidation::TYPE_LIST);
                            $valRak->setErrorStyle(DataValidation::STYLE_INFORMATION);
                            $valRak->setAllowBlank(true);
                            $valRak->setShowDropDown(true);
                            $valRak->setFormula1("'Referensi Sistem'!\$N\$2:\$N\$100");
                            $valRak->setSqref('W2:W1000');
                        },
                    ];
                }
            },

            // Sheet 2: Tambah Tipe Baru (Tempat mendaftarkan Tipe HP baru secara mandiri)
            new class implements FromCollection, WithHeadings, WithTitle
            {
                public function title(): string
                {
                    return 'Tambah Tipe Baru';
                }

                public function headings(): array
                {
                    return ['merk_hp_baru', 'model_hp_baru', 'keterangan'];
                }

                public function collection(): Enumerable
                {
                    return collect([
                        ['Infinix', 'Hot 40 Pro', 'Contoh model baru yang belum ada di sistem'],
                        ['Tecno', 'Pova 6 Pro', 'Contoh model baru'],
                    ]);
                }
            },

            // Sheet 3: Referensi Sistem (Kamus resmi terkalibrasi ke database aktif)
            new class implements FromCollection, WithHeadings, WithTitle
            {
                public function title(): string
                {
                    return 'Referensi Sistem';
                }

                public function headings(): array
                {
                    return ['Merk HP', 'Kategori Produk', 'Brand Sparepart', 'Satuan', 'Kualitas', 'Supplier', 'Kategori Utama', 'Sub Kategori', 'Merk HP', 'Tipe HP', 'Brand Part', 'Kualitas Brand', 'Gudang', 'Rak'];
                }

                public function collection(): Enumerable
                {
                    $merks = TipeHp::where('is_active', true)->distinct()->orderBy('merk')->pluck('merk')->filter()->values()->all();
                    if (empty($merks)) {
                        $merks = ['Apple', 'Samsung', 'Xiaomi', 'Oppo', 'Vivo', 'Realme', 'Infinix', 'Tecno', 'Asus', 'Universal'];
                    }

                    $kategoris = KategoriProduk::where('is_active', true)->whereNull('parent_id')->orderBy('nama')->pluck('nama')->all();
                    if (empty($kategoris)) {
                        $kategoris = ['LCD & Touchscreen', 'Baterai', 'Fleksibel & Board', 'Kamera', 'IC & Chipset', 'Housing & Backdoor'];
                    }

                    $brands = Brand::where('is_active', true)->orderBy('nama')->pluck('nama')->all();
                    if (empty($brands)) {
                        $brands = ['Crown', 'OG', 'Braderpart', 'Olike', 'OEM', 'Original'];
                    }

                    $satuans = SatuanUnit::where('is_active', true)->orderBy('kode')->pluck('kode')->all();
                    if (empty($satuans)) {
                        $satuans = ['pcs', 'box', 'unit', 'set', 'roll', 'meter', 'botol'];
                    }

                    $kualitas = KualitasProduk::where('is_active', true)->orderBy('nama')->pluck('nama')->all();
                    if (empty($kualitas)) {
                        $kualitas = ['Original', 'Grade A', 'Grade B', 'OEM', 'Refurbished'];
                    }

                    $suppliers = Supplier::where('is_active', true)->orderBy('nama')->pluck('nama')->all();
                    if (empty($suppliers)) {
                        $suppliers = ['PT Supplier Jaya', 'Supplier Umum', 'Sumber Makmur'];
                    }

                    $cabangId = session('cabang_id') ?? auth()->user()?->cabang_id;
                    $gudangs = Gudang::when($cabangId, fn ($q, $c) => $q->where('cabang_id', $c))
                        ->where('is_active', true)
                        ->orderBy('nama')
                        ->pluck('nama')
                        ->unique()
                        ->values()
                        ->all();
                    if (empty($gudangs)) {
                        $gudangs = Gudang::where('is_active', true)->orderBy('nama')->pluck('nama')->unique()->values()->all();
                        if (empty($gudangs)) {
                            $gudangs = ['Gudang Utama'];
                        }
                    }

                    $raks = Rak::when($cabangId, fn ($q, $c) => $q->whereHas('gudang', fn ($g) => $g->where('cabang_id', $c)))
                        ->where('is_active', true)
                        ->orderBy('nama')
                        ->pluck('nama')
                        ->unique()
                        ->values()
                        ->all();
                    if (empty($raks)) {
                        $raks = Rak::where('is_active', true)->orderBy('nama')->pluck('nama')->unique()->values()->all();
                        if (empty($raks)) {
                            $raks = ['Rak A1', 'Rak A2', 'Rak B1'];
                        }
                    }

                    $subKategoris = KategoriProduk::with('parent')->where('is_active', true)->whereNotNull('parent_id')->orderBy('nama')->get();
                    $tipeHp = TipeHp::where('is_active', true)->orderBy('merk')->orderBy('model')->get();
                    $kualitasBrand = KualitasProduk::with('brand')->where('is_active', true)->whereNotNull('brand_id')->orderBy('nama')->get();
                    $max = max(count($merks), count($kategoris), count($brands), count($satuans), count($kualitas), count($suppliers), count($gudangs), count($raks), count($subKategoris), count($tipeHp), count($kualitasBrand));
                    $rows = [];
                    for ($i = 0; $i < $max; $i++) {
                        $rows[] = [
                            $merks[$i] ?? '',
                            $kategoris[$i] ?? '',
                            $brands[$i] ?? '',
                            $satuans[$i] ?? '',
                            $kualitas[$i] ?? '',
                            $suppliers[$i] ?? '', $kategoris[$i] ?? '', $subKategoris[$i]?->nama ?? '',
                            $tipeHp[$i]?->merk ?? '', $tipeHp[$i]?->model ?? '', $kualitasBrand[$i]?->brand?->nama ?? '', $kualitasBrand[$i]?->nama ?? '',
                            $gudangs[$i] ?? '', $raks[$i] ?? '',
                        ];
                    }

                    return collect($rows);
                }
            },

            // Sheet 4: Petunjuk Pengisian
            new class implements FromCollection, WithTitle
            {
                public function title(): string
                {
                    return 'Petunjuk';
                }

                public function collection(): Enumerable
                {
                    return collect([
                        ['PETUNJUK IMPORT MASTER PRODUK DINAMIS — UTE PARTS'],
                        [''],
                        ['1. METODE PENGISIAN TINGGAL PILIH (DROPDOWN):'],
                        ['   - Kolom satuan, kategori, brand part, merk_hp, kualitas, kondisi, dan sn memiliki DROPDOWN pilihan.'],
                        ['   - Anda cukup klik panah dropdown di masing-masing sel untuk memilih nilai resmi tanpa perlu mengetik manual.'],
                        [''],
                        ['2. KOMPATIBILITAS TIPE HP (MUDAH & FLEKSIBEL):'],
                        ['   - merk_hp: Pilih merk smartphone dari dropdown (cth: Samsung, Apple, Xiaomi).'],
                        ['   - tipe_hp: Tuliskan model handphone. Jika satu sparepart cocok untuk banyak tipe, CUKUP PISAHKAN DENGAN KOMA.'],
                        ['     Contoh: A11, A15, M11 (Sistem otomatis memasangkannya dengan merk_hp yang dipilih).'],
                        ['   - Jika sparepart multi-brand (misal IC universal), Anda bisa menuliskan merk lengkap: "Samsung A11; Xiaomi Redmi 9".'],
                        [''],
                        ['3. PRODUK SUBSTITUSI / KOMPATIBEL ANTAR PRODUK:'],
                        ['   - produk_kompatibel_sku: Masukkan SKU produk pengganti jika barang ini kosong.'],
                        ['     Contoh: Masukkan "LCD-A11-OG, LCD-A11-BRD". Sistem akan otomatis menautkannya secara 2 arah.'],
                        [''],
                        ['4. JIKA ADA MERK ATAU TIPE HP BARU YANG BELUM ADA DI SISTEM:'],
                        ['   - Jangan khawatir! Buka Sheet "Tambah Tipe Baru", tuliskan merk dan model baru tersebut di sana.'],
                        ['   - Saat di-import, sistem otomatis mendaftarkan master Tipe HP baru tersebut tanpa terjadi error!'],
                        [''],
                        ['5. PENCEGAHAN TYPO OTOMATIS:'],
                        ['   - Sistem dilengkapi Auto-Fuzzy Matcher: jika terjadi salah ketik kecil (cth: "Samsng" atau "ip 11"),'],
                        ['     sistem otomatis menormalkan ke data resmi yang tepat tanpa mengotori database master.'],
                        [''],
                        ['6. GUDANG & PENEMPATAN LOKASI RAK FISIK:'],
                        ['   - gudang: Pilih nama gudang penyimpanan (default: "Gudang Utama"). Jika dikosongkan, sistem otomatis memasukkannya ke Gudang Utama default cabang.'],
                        ['   - rak: Tuliskan kode atau nama rak (cth: "Rak A1", "Rak Z-01"). Jika rak atau satuan belum ada di sistem, sistem otomatis mendaftarkannya tanpa menggagalkan import.'],
                    ]);
                }
            },
        ];
    }
}
