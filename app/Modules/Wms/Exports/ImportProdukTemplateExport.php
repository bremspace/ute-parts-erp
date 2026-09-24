<?php

namespace App\Modules\Wms\Exports;

use App\Modules\Wms\Services\ImportProdukService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * [T-43] Template Excel import master produk.
 * Sheet 1: header + 2 contoh baris. Sheet 2: petunjuk pengisian.
 */
class ImportProdukTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new class implements FromCollection, WithHeadings, WithTitle
            {
                public function title(): string
                {
                    return 'Template Produk';
                }

                public function headings(): array
                {
                    return app(ImportProdukService::class)->templateColumns();
                }

                public function collection()
                {
                    return collect(app(ImportProdukService::class)->contohBaris());
                }
            },
            new class implements FromCollection, WithTitle
            {
                public function title(): string
                {
                    return 'Petunjuk';
                }

                public function collection()
                {
                    return collect([
                        ['Petunjuk Import Master Produk (Ute Parts)'],
                        [''],
                        ['1. Jangan ubah baris header (baris 1) — nama kolom wajib sama persis.'],
                        ['2. Kolom wajib: sku, nama, satuan, harga_beli, harga_jual.'],
                        ['3. SKU & barcode harus unik (dalam file & database) — baris duplikat akan ditolak.'],
                        ['4. Satuan harus terdaftar di referensi satuan_unit (pcs, box, unit, set, pasang, dst).'],
                        ['5. Brand & kualitas dibuat otomatis bila nama baru.'],
                        ['6. tipe_hp: teks "Merk Model" (pisah beberapa dengan ; atau ,) ATAU JSON [{"merk":"Apple","model":"iPhone 13"}].'],
                        ['7. kompatibilitas_hp: JSON string [{"merk":"...","model":"..."}].'],
                        ['8. gudang_id & rak_id wajib diisi bila stok_awal > 0.'],
                        ['9. stok_awal = inisialisasi master (modal stok awal, jurnal 130-01/310-01).'],
                        ['   Stok masuk harian TETAP wajib lewat PO Supplier.'],
                        ['10. foto_url opsional (URL gambar produk).'],
                        ['11. Gunakan "Preview" dulu — laporan error per baris ditampilkan sebelum commit.'],
                        ['12. Maksimal 3000 baris per file.'],
                    ]);
                }
            },
        ];
    }
}
