<?php

namespace App\Modules\Wms\Exports;

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
 * [T-43] Template Excel import master produk.
 * Sheet 1: header + 2 contoh baris (lengkap dengan dropdown data validation). Sheet 2: petunjuk pengisian.
 *
 * [B-04] Wajib implement Export (marker interface) — Excel::download() mengetik
 * argumen #1 sebagai Maatwebsite\Excel\Concerns\Export; WithMultipleSheets saja
 * menyebabkan TypeError → download gagal.
 */
class ImportProdukTemplateExport implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
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

                            // 1. Dropdown Satuan (Kolom D)
                            $valSatuan = $sheet->getCell('D2')->getDataValidation();
                            $valSatuan->setType(DataValidation::TYPE_LIST);
                            $valSatuan->setErrorStyle(DataValidation::STYLE_INFORMATION);
                            $valSatuan->setAllowBlank(true);
                            $valSatuan->setShowDropDown(true);
                            $valSatuan->setShowInputMessage(true);
                            $valSatuan->setPromptTitle('Pilih Satuan');
                            $valSatuan->setPrompt('Pilih satuan resmi yang terdaftar');
                            $valSatuan->setFormula1('"pcs,box,unit,set,roll,meter,botol"');
                            $valSatuan->setSqref('D2:D1000');

                            // 2. Dropdown Kualitas (Kolom H)
                            $valKualitas = $sheet->getCell('H2')->getDataValidation();
                            $valKualitas->setType(DataValidation::TYPE_LIST);
                            $valKualitas->setErrorStyle(DataValidation::STYLE_INFORMATION);
                            $valKualitas->setAllowBlank(true);
                            $valKualitas->setShowDropDown(true);
                            $valKualitas->setShowInputMessage(true);
                            $valKualitas->setPromptTitle('Pilih Kualitas');
                            $valKualitas->setPrompt('Pilih grade kualitas');
                            $valKualitas->setFormula1('"Original,Grade A,Grade B,Refurbished,OEM"');
                            $valKualitas->setSqref('H2:H1000');

                            // 3. Dropdown Kondisi (Kolom I)
                            $valKondisi = $sheet->getCell('I2')->getDataValidation();
                            $valKondisi->setType(DataValidation::TYPE_LIST);
                            $valKondisi->setErrorStyle(DataValidation::STYLE_INFORMATION);
                            $valKondisi->setAllowBlank(true);
                            $valKondisi->setShowDropDown(true);
                            $valKondisi->setShowInputMessage(true);
                            $valKondisi->setPromptTitle('Pilih Kondisi');
                            $valKondisi->setPrompt('baru, oem, atau compatible');
                            $valKondisi->setFormula1('"baru,oem,compatible"');
                            $valKondisi->setSqref('I2:I1000');

                            // 4. Dropdown Serial Number (Kolom P)
                            $valSn = $sheet->getCell('P2')->getDataValidation();
                            $valSn->setType(DataValidation::TYPE_LIST);
                            $valSn->setErrorStyle(DataValidation::STYLE_INFORMATION);
                            $valSn->setAllowBlank(true);
                            $valSn->setShowDropDown(true);
                            $valSn->setShowInputMessage(true);
                            $valSn->setPromptTitle('Wajib SN');
                            $valSn->setPrompt('Pilih ya jika wajib serial number');
                            $valSn->setFormula1('"ya,tidak"');
                            $valSn->setSqref('P2:P1000');
                        },
                    ];
                }
            },
            new class implements FromCollection, WithTitle
            {
                public function title(): string
                {
                    return 'Petunjuk';
                }

                public function collection(): Enumerable
                {
                    return collect([
                        ['PETUNJUK IMPORT MASTER PRODUK — UTE PARTS'],
                        [''],
                        ['1. FORMAT & STRUKTUR FILE:'],
                        ['   - Baris 1 adalah HEADER KOLOM — JANGAN ubah nama atau urutan kolom.'],
                        ['   - Untuk data sangat banyak (> 5.000 baris), disarankan simpan/upload dalam format CSV agar proses super cepat.'],
                        ['   - Sistem mendukung hingga 50.000 baris per file dengan pemrosesan otomatis di latar belakang (background queue).'],
                        [''],
                        ['2. KOLOM WAJIB:'],
                        ['   - sku: Kode unik produk (cth: LCD-IP13-ORI). Harus unik dan tidak boleh ada di database.'],
                        ['   - nama: Nama lengkap produk / sparepart (cth: LCD iPhone 13 Original).'],
                        ['   - satuan: Satuan unit produk, harus terdaftar di sistem (cth: pcs, box, unit, set, roll, meter, botol).'],
                        ['   - harga_beli: Harga modal / HPP (angka tanpa titik/koma ribuan, cth: 850000).'],
                        ['   - harga_jual: Harga jual retail untuk konsumen umum (cth: 1250000).'],
                        [''],
                        ['3. KOLOM OPSIONAL & HARGA TIER:'],
                        ['   - barcode: Kode barcode fisik / scan (unik jika diisi).'],
                        ['   - kategori: Kategori produk (cth: LCD & Touchscreen, Baterai, IC & Chipset, Aksesoris HP). Dibuat otomatis jika baru.'],
                        ['   - brand: Brand sparepart / merk produk (cth: Apple, Samsung, OG, Crown). Dibuat otomatis jika baru.'],
                        ['   - kualitas: Grade kualitas (cth: Original, Grade A, Grade B, Refurbished). Dibuat otomatis jika baru.'],
                        ['   - kondisi: baru / oem / compatible (default: baru jika dikosongkan).'],
                        ['   - harga_reseller: Nominal harga khusus pelanggan reseller (opsional).'],
                        ['   - harga_agen: Nominal harga khusus pelanggan agen/grosir (opsional).'],
                        [''],
                        ['4. PENGADAAN & SERIAL NUMBER:'],
                        ['   - min_stock: Batas minimum pengaman stok di gudang (pemicu stok menipis).'],
                        ['   - reorder_point: Titik ROP pengadaan otomatis untuk kelas fast moving.'],
                        ['   - sn: Isi "ya" atau "1" jika produk wajib scan serial number/SN (kartu garansi anti-fraud). Dikosongkan jika tidak.'],
                        [''],
                        ['5. STOK AWAL & GUDANG (Hanya untuk Inisialisasi Master):'],
                        ['   - stok_awal: Jumlah saldo awal saat setup produk (default 0).'],
                        ['   - gudang_id & rak_id: ID gudang dan rak penempatan (wajib diisi bila stok_awal > 0).'],
                        ['   - Catatan: Stok awal otomatis membuat mutasi saldo awal dan jurnal akuntansi 130-01 / 310-01.'],
                        ['   - Pembelian barang masuk harian tetap dilakukan melalui menu PO Supplier & GRN.'],
                        [''],
                        ['6. KOMPATIBILITAS & MEDIA:'],
                        ['   - tipe_hp: Tipe handphone yang cocok (cth: "Apple iPhone 13" atau beberapa dipisah titik-koma: "Apple iPhone 13; Apple iPhone 14").'],
                        ['   - deskripsi: Keterangan / spesifikasi lengkap sparepart.'],
                        ['   - foto_url: URL foto produk (jika ada).'],
                    ]);
                }
            },
        ];
    }
}
