<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\ImportProdukService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ExportImportUnifiedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');

        $this->cabang = Cabang::create(['nama' => 'Cabang Test', 'kode' => 'CB-EI', 'is_active' => true]);
        Gudang::create(['nama' => 'Gudang Pusat', 'kode' => 'GD-01', 'cabang_id' => $this->cabang->id, 'is_active' => true]);

        $this->user = User::create([
            'name' => 'Super Admin ExportImport',
            'email' => 'admin-ei@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user);
    }

    public function test_export_master_xlsx_multi_sheet_termasuk_referensi(): void
    {
        // Setup: existing reference data
        KategoriProduk::firstOrCreate(['nama' => 'LCD & Touchscreen'], ['is_active' => true]);
        Brand::firstOrCreate(['nama' => 'Crown'], ['is_active' => true]);
        TipeHp::firstOrCreate(['merk' => 'Samsung', 'model' => 'Galaxy A52'], ['is_active' => true]);
        SatuanUnit::firstOrCreate(['kode' => 'pcs'], ['nama' => 'Pieces', 'is_active' => true]);
        KualitasProduk::firstOrCreate(['nama' => 'Original'], ['is_active' => true]);

        $produk = Produk::create([
            'nama' => 'LCD Samsung A52 Original',
            'barcode' => 'BC-TEST-001',
            'kategori' => 'LCD & Touchscreen',
            'brand_kompatibel' => 'Samsung',
            'model_kompatibel' => 'Galaxy A52',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 500000,
            'harga_jual_retail' => 900000,
            'is_active' => true,
        ]);

        $test = Livewire::test(ProdukTab::class)
            ->call('exportMaster', 'xlsx');

        $test->assertFileDownloaded();

        $files = collect(Storage::disk('local')->allFiles('exports'))
            ->filter(fn ($f) => str_contains($f, 'master-produk'));

        $this->assertNotEmpty($files);
        $fullPath = Storage::disk('local')->path($files->last());

        // Read all sheets
        $reader = new class implements ToArray
        {
            public function array(array $array): void {}
        };
        $sheets = Excel::toArray($reader, $fullPath);
        $this->assertCount(4, $sheets, 'Harus ada 4 sheet: Data Produk, Referensi Sistem, Petunjuk, Tambah Tipe Baru');

        // Sheet 1: Data Produk
        $sheet1 = $sheets[0];
        $this->assertNotEmpty($sheet1);
        $headings1 = $sheet1[0];
        $wajib = app(ImportProdukService::class)->templateColumns();
        foreach ($wajib as $kolom) {
            $this->assertContains($kolom, $headings1, "Kolom '$kolom' tidak ada di Sheet 1 Data Produk");
        }

        // Sheet 2: Referensi Sistem
        $sheet2 = $sheets[1];
        $this->assertNotEmpty($sheet2);
        $headings2 = $sheet2[0];
        $this->assertSame(['Merk HP', 'Kategori Produk', 'Brand Sparepart', 'Satuan', 'Kualitas', 'Supplier', 'Kategori Utama', 'Sub Kategori', 'Merk HP', 'Tipe HP', 'Brand Part', 'Kualitas Brand', 'Gudang', 'Rak'], $headings2);
        // Check existing data appears
        $flat2 = collect($sheet2)->flatten()->filter()->all();
        $this->assertContains('Samsung', $flat2);
        $this->assertContains('LCD & Touchscreen', $flat2);
        $this->assertContains('Crown', $flat2);
        $this->assertContains('pcs', $flat2);
        $this->assertContains('Original', $flat2);

        // Sheet 3: Petunjuk
        $sheet3 = $sheets[2];
        $this->assertNotEmpty($sheet3);
        $flat3 = collect($sheet3)->flatten()->filter()->implode(' ');
        $this->assertStringContainsString('PETUNJUK IMPORT MASTER PRODUK', $flat3);

        // Sheet 4: Tambah Tipe Baru
        $sheet4 = $sheets[3];
        $this->assertNotEmpty($sheet4);
        $headings4 = $sheet4[0];
        $this->assertSame(['merk_hp_baru', 'model_hp_baru', 'keterangan'], $headings4);
    }

    public function test_import_preview_menampilkan_ringkasan_referensi_baru(): void
    {
        // Setup existing reference data
        KategoriProduk::firstOrCreate(['nama' => 'LCD & Touchscreen'], ['is_active' => true]);
        Brand::firstOrCreate(['nama' => 'Crown'], ['is_active' => true]);
        TipeHp::firstOrCreate(['merk' => 'Samsung', 'model' => 'Galaxy A52'], ['is_active' => true]);
        SatuanUnit::firstOrCreate(['kode' => 'pcs'], ['nama' => 'Pieces', 'is_active' => true]);
        KualitasProduk::firstOrCreate(['nama' => 'Original'], ['is_active' => true]);

        // Create a test file with mix of existing and new references
        $importService = app(ImportProdukService::class);

        // We'll test the buildReferensiSummary method directly
        $refValues = [
            'kategori' => ['LCD & Touchscreen' => true, 'Kategori Baru' => true],
            'brand' => ['Crown' => true, 'Brand Baru' => true],
            'merk_hp' => ['Samsung' => true, 'Merk Baru' => true],
            'tipe_hp' => [
                ['merk' => 'Samsung', 'model' => 'Galaxy A52'],
                ['merk' => 'Xiaomi', 'model' => 'Redmi Note 10'],
            ],
            'kualitas' => ['Original' => true, 'Kualitas Baru' => true],
            'satuan' => ['pcs' => true, 'box' => true],
        ];

        $summary = $importService->buildReferensiSummary($refValues);

        $this->assertSame(['LCD & Touchscreen'], $summary['kategori']['existing']);
        $this->assertSame(['Kategori Baru'], $summary['kategori']['new']);
        $this->assertSame(['Crown'], $summary['brand']['existing']);
        $this->assertSame(['Brand Baru'], $summary['brand']['new']);
        $this->assertSame(['Samsung'], $summary['merk_hp']['existing']);
        $this->assertSame(['Merk Baru'], $summary['merk_hp']['new']);
        $this->assertSame(['Samsung:Galaxy A52'], $summary['tipe_hp']['existing']);
        $this->assertSame(['Xiaomi:Redmi Note 10'], $summary['tipe_hp']['new']);
        $this->assertSame(['Original'], $summary['kualitas']['existing']);
        $this->assertSame(['Kualitas Baru'], $summary['kualitas']['new']);
        $this->assertSame(['pcs', 'box'], $summary['satuan']['existing']);
        $this->assertSame([], $summary['satuan']['new']);
    }

    public function test_roundtrip_export_import(): void
    {
        // Setup reference data
        $kat = KategoriProduk::firstOrCreate(['nama' => 'LCD & Touchscreen'], ['is_active' => true]);
        $brand = Brand::firstOrCreate(['nama' => 'Crown'], ['is_active' => true]);
        $tipe = TipeHp::firstOrCreate(['merk' => 'Samsung', 'model' => 'Galaxy A52'], ['is_active' => true]);
        SatuanUnit::firstOrCreate(['kode' => 'pcs'], ['nama' => 'Pieces', 'is_active' => true]);
        KualitasProduk::firstOrCreate(['nama' => 'Original'], ['is_active' => true]);
        Supplier::firstOrCreate(['nama' => 'Supplier Test'], ['is_active' => true]);

        $produk = Produk::create([
            'nama' => 'LCD Samsung A52 Original',
            'kode' => 'LCD-A52-ORI',
            'barcode' => 'BC-TEST-001',
            'kategori_id' => $kat->id,
            'brand_id' => $brand->id,
            'kategori' => 'LCD & Touchscreen',
            'brand_kompatibel' => 'Samsung',
            'model_kompatibel' => 'Galaxy A52',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 500000,
            'harga_jual_retail' => 900000,
            'is_active' => true,
        ]);

        $variant = SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'LCD-A52-ORI',
            'nama_varian' => 'Default',
            'barcode' => 'BC-TEST-001',
            'harga_beli' => 500000,
            'harga_jual_retail' => 900000,
            'is_active' => true,
        ]);

        // Export
        $test = Livewire::test(ProdukTab::class)
            ->call('exportMaster', 'xlsx');

        $test->assertFileDownloaded();

        $files = collect(Storage::disk('local')->allFiles('exports'))
            ->filter(fn ($f) => str_contains($f, 'master-produk'));
        $fullPath = Storage::disk('local')->path($files->last());

        // Read exported file structure
        $reader = new class implements ToArray
        {
            public function array(array $array): void {}
        };
        $sheets = Excel::toArray($reader, $fullPath);

        // Should have 4 sheets
        $this->assertCount(4, $sheets);

        // Sheet 1: Data Produk - should contain our product and headings identical to templateColumns
        $sheet1 = $sheets[0];
        $headings1 = $sheet1[0];
        $expectedColumns = app(ImportProdukService::class)->templateColumns();
        $this->assertSame($expectedColumns, $headings1, 'Header Sheet 1 hasil ekspor harus identik dengan templateColumns()');

        // Sheet 2: Referensi Sistem - should have our reference data
        $sheet2 = $sheets[1];
        $headings2 = $sheet2[0];
        $this->assertSame(['Merk HP', 'Kategori Produk', 'Brand Sparepart', 'Satuan', 'Kualitas', 'Supplier', 'Kategori Utama', 'Sub Kategori', 'Merk HP', 'Tipe HP', 'Brand Part', 'Kualitas Brand', 'Gudang', 'Rak'], $headings2);

        // Verify exported references include our test data
        $flat2 = collect($sheet2)->flatten()->filter()->all();
        $this->assertContains('Samsung', $flat2);
        $this->assertContains('LCD & Touchscreen', $flat2);
        $this->assertContains('Crown', $flat2);
        $this->assertContains('pcs', $flat2);
        $this->assertContains('Original', $flat2);

        // Sheet 3: Petunjuk
        $sheet3 = $sheets[2];
        $this->assertNotEmpty($sheet3);

        // Sheet 4: Tambah Tipe Baru
        $sheet4 = $sheets[3];
        $headings4 = $sheet4[0];
        $this->assertSame(['merk_hp_baru', 'model_hp_baru', 'keterangan'], $headings4);

        // Verifikasi round-trip: file hasil export xlsx dipratinjau kembali
        $importService = app(ImportProdukService::class);
        $preview = $importService->preview($fullPath);

        $this->assertGreaterThan(0, $preview['total_baris']);
        $this->assertNotEmpty($preview['duplikat_rows']);
        $this->assertSame(1, $preview['total_duplikat_rows']);

        // Verifikasi detectDuplikatRow mendeteksinya sebagai duplikat eksisting database
        $duplikatItem = $preview['duplikat_rows'][0];
        $this->assertSame('LCD-A52-ORI', $duplikatItem['sku']);
        $this->assertSame('database', $duplikatItem['lokasi']);
        $this->assertSame($produk->id, $duplikatItem['target_produk_id']);

        // Uji resolusi timpa_update saat commit
        $log = ImportLog::create([
            'tipe' => 'produk_excel',
            'nama_file' => 'test_roundtrip.xlsx',
            'status' => 'proses',
            'user_id' => $this->user->id,
        ]);

        $resolusi = [
            2 => ['aksi' => 'timpa_update'],
        ];

        $commitResult = $importService->commit($fullPath, $log->id, $this->user->id, [], $resolusi);

        $this->assertSame(1, $commitResult['sukses'], 'Commit timpa_update harus berhasil 100% memperbarui data');
        $this->assertSame(0, $commitResult['gagal']);
    }
}
