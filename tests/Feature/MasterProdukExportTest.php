<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\ImportProdukService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class MasterProdukExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');

        $this->cabang = Cabang::create(['nama' => 'Cabang Test', 'kode' => 'CB-TEST', 'is_active' => true]);
        Gudang::create(['nama' => 'Gudang Pusat', 'kode' => 'GD-01', 'cabang_id' => $this->cabang->id, 'is_active' => true]);

        $this->user = User::create([
            'name' => 'Super Admin Export',
            'email' => 'admin-export-master@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user);
    }

    /**
     * Buat 2 produk lengkap master relasi: 1 aktif + 1 nonaktif.
     *
     * @return array{0: Produk, 1: Produk, 2: KategoriProduk, 3: KategoriProduk}
     */
    private function buatProdukLengkapTerkait(): array
    {
        $katParent = KategoriProduk::create(['nama' => 'Sparepart HP']);
        $katAnak = KategoriProduk::create(['nama' => 'LCD', 'parent_id' => $katParent->id]);
        $katLain = KategoriProduk::create(['nama' => 'Baterai']);

        $brand = Brand::create(['nama' => 'Brand Jaya', 'is_active' => true]);
        $kualitas = KualitasProduk::create(['nama' => 'Grade Orig', 'is_active' => true]);
        $supplier = Supplier::create(['nama' => 'PT Supplier Jaya', 'is_active' => true]);

        $aktif = Produk::create([
            'nama' => 'LCD Aktif Master',
            'barcode' => 'BC-AKTIF-001',
            'kategori_id' => $katAnak->id,
            'brand_id' => $brand->id,
            'kualitas_id' => $kualitas->id,
            'supplier_id' => $supplier->id,
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 500000,
            'harga_jual_retail' => 900000,
            'is_active' => true,
        ]);

        $nonaktif = Produk::create([
            'nama' => 'Produk Nonaktif Master',
            'kategori' => 'Lainnya',
            'harga_beli' => 12000,
            'harga_jual_retail' => 20000,
            'is_active' => false,
        ]);

        // Tier reseller khusus utk produk aktif
        HargaTier::create([
            'produk_id' => $aktif->id,
            'tipe_konsumen' => 'reseller',
            'harga' => 800000,
            'nominal_tetap' => 800000,
        ]);

        return [$aktif, $nonaktif, $katAnak, $katLain];
    }

    private function fileExportMasterTerbaru(): string
    {
        $files = collect(Storage::disk('local')->allFiles('exports'))
            ->filter(fn ($f) => str_contains($f, 'master-produk'));

        $this->assertNotEmpty($files, 'File export master-produk tidak ditemukan di disk exports.');

        return $files->last();
    }

    public function test_export_master_menghasilkan_file_xlsx_dengan_semua_kolom(): void
    {
        [$aktif, $nonaktif] = $this->buatProdukLengkapTerkait();

        $test = Livewire::test(ProdukTab::class)
            ->call('exportMaster', 'xlsx');

        $test->assertFileDownloaded();

        $relativePath = $this->fileExportMasterTerbaru();
        Storage::disk('local')->assertExists($relativePath);

        $fullPath = Storage::disk('local')->path($relativePath);
        $reader = new class implements ToArray
        {
            public function array(array $array): void {}
        };
        $data = Excel::toArray($reader, $fullPath);

        $this->assertNotEmpty($data, 'Sheet xlsx kosong.');
        $headings = $data[0][0];
        $this->assertIsArray($headings);

        // Kolom sekarang mengikuti template import (ImportProdukService::templateColumns)
        $wajib = app(ImportProdukService::class)->templateColumns();
        foreach ($wajib as $kolom) {
            $this->assertContains($kolom, $headings, "Kolom '$kolom' tidak ada di heading xlsx.");
        }

        // Baris produk nonaktif harus ikut terekspor (master lengkap, tanpa filter is_active)
        $namaIdx = array_search('nama', $headings);
        $katIdx = array_search('kategori', $headings);
        $statusIdx = -1; // status tidak lagi di kolom, cek is_active via kondisi/empty
        $brandIdx = array_search('brand', $headings);

        $body = array_slice($data[0], 1);
        $nonaktifRow = collect($body)->first(fn ($row) => ($row[$namaIdx] ?? null) === 'Produk Nonaktif Master');
        $this->assertNotNull($nonaktifRow, 'Produk nonaktif tidak ikut terekspor di xlsx.');

        // Relasi master: kategori, brand, supplier
        $aktifRow = collect($body)->first(fn ($row) => ($row[$namaIdx] ?? null) === 'LCD Aktif Master');
        $this->assertNotNull($aktifRow, 'Produk aktif tidak terekspor di xlsx.');
        $this->assertSame('Brand Jaya', $aktifRow[$brandIdx]);

        $supplierIdx = array_search('supplier', $headings);
        $this->assertNotFalse($supplierIdx);
        $this->assertSame('PT Supplier Jaya', $aktifRow[$supplierIdx]);

        // Kategori & Sub Kategori untuk import: parent & child terpisah jelas
        $subKatIdx = array_search('sub_kategori', $headings);
        $this->assertNotFalse($subKatIdx);
        $this->assertSame('Sparepart HP', $aktifRow[$katIdx]);
        $this->assertSame('LCD', $aktifRow[$subKatIdx]);
    }

    public function test_export_master_csv_berisi_nilai_tier(): void
    {
        [, $nonaktif] = $this->buatProdukLengkapTerkait();

        Livewire::test(ProdukTab::class)
            ->set('filterTierHarga', 'reseller')
            ->call('exportMaster', 'csv');

        $path = $this->fileExportMasterTerbaru();
        $content = Storage::disk('local')->get($path);

        // Heading & kolom tier resolusi (kolom baru: harga_reseller, harga_agen)
        $this->assertStringContainsString('harga_reseller', $content);
        $this->assertStringContainsString('harga_agen', $content);

        // Produk nonaktif terekspor
        $this->assertStringContainsString('Produk Nonaktif Master', $content);

        // Angka harga tanpa format ribuan (raw numbers untuk import)
        $this->assertStringContainsString('12000', $content);
        $this->assertStringContainsString('20000', $content);
    }

    public function test_export_master_menghormati_filter_kategori(): void
    {
        [, $nonaktif, $katAnak, $katLain] = $this->buatProdukLengkapTerkait();

        // Produk di kategori Lainnya (di luar keturunan) harus dikecualikan
        $diLuar = Produk::create([
            'nama' => 'Produk Kategori Lainnya',
            'kategori_id' => $katLain->id,
            'harga_beli' => 5000,
            'harga_jual_retail' => 9000,
            'is_active' => false,
        ]);

        Livewire::test(ProdukTab::class)
            ->set('filterKategoriId', $katAnak->id)
            ->call('exportMaster', 'csv');

        $path = $this->fileExportMasterTerbaru();
        $content = Storage::disk('local')->get($path);

        // Produk nonaktif tanpa kategori (NULL) tidak match kategori_id → tidak di dalam file
        // Produk di kategori lain juga tidak
        $this->assertStringNotContainsString('Produk Kategori Lainnya', $content);

        // Produk aktif sesuai kategori (anak kategori dari filter) tetap ada
        $this->assertStringContainsString('LCD Aktif Master', $content);

        // Produk nonaktif kategori NULL dikeluarkan karena filter kategori aktif
        $this->assertStringNotContainsString('Produk Nonaktif Master', $content);
    }
}
