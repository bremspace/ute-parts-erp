<?php

namespace App\Modules\Marketplace\Controllers;

use App\Modules\Marketplace\Imports\ProdukImport;
use App\Modules\Wms\Models\Produk;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;

/**
 * Import data (PRD §4.11): Excel/CSV produk dengan dry-run/preview wajib sebelum commit.
 */
class ImportController extends Controller
{
    use ApiResponse;

    /**
     * Preview: validasi + ringkasan baris yang akan di-import, tanpa mengubah data.
     */
    public function previewProduk(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $path = $request->file('file')->store('import-tmp');

        // Validasi heading awal — mapping kolom sumber → field Ute Parts (SID Retail generik)
        try {
            $headings = Excel::toArray(new HeadingRowImport(), storage_path('app/' . $path));
            $kolom = collect($headings[0][0] ?? [])->toArray();
        } catch (\Throwable $e) {
            // Bukan error fatal — ToModel WithValidation akan menangkap per-row
            $kolom = [];
        }

        $import = new ProdukImport(preview: true);
        $rows = Excel::toCollection($import, storage_path('app/' . $path));

        $valid = 0;
        $invalid = 0;
        $warnings = [];

        foreach ($rows->flatten(1) as $row) {
            if ($row['nama'] ?? false) {
                $valid++;
            } else {
                $invalid++;
                $warnings[] = 'Baris tanpa kolom "nama" terdeteksi';
            }
        }

        // Cek duplikasi slug yang sudah ada
        $duplikat = $rows->flatten(1)->filter(fn ($r) => $r['nama'] ?? false)
            ->filter(fn ($r) => Produk::where('slug', Str::slug($r['nama']))->exists())
            ->count();

        $warnings[] = $duplikat > 0 ? "{$duplikat} baris nama sudah terdaftar (akan di-update via firstOrCreate)" : 'Tidak ada duplikat nama dengan produk existing';

        return $this->success([
            'kolom_terdeteksi' => $kolom,
            'total_baris' => $valid + $invalid,
            'valid' => $valid,
            'invalid' => $invalid,
            'duplikat_existing' => $duplikat,
            'warnings' => $warnings,
            'pesan' => 'Mode preview: belum ada data yang diubah. Upload ulang dengan mode commit untuk menyimpan.',
        ], 'Preview import produk selesai');
    }

    /**
     * Commit: jalankan import sungguhan setelah preview disetujui.
     */
    public function commitProduk(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'confirmed' => 'required|boolean|accepted', // wajib konfirmasi dari hasil preview
        ]);

        $path = $request->file('file')->store('import-tmp');

        try {
            $import = new ProdukImport(preview: false);
            $count = Excel::import($import, storage_path('app/' . $path));

            return $this->success(['rows_processed' => $count], 'Import produk berhasil disimpan');
        } catch (\Throwable $e) {
            return $this->error('Import gagal: ' . $e->getMessage(), 422);
        }
    }
}