<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ImportExportPage;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BulkFotoProdukFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Cabang Pusat', 'kode' => 'CBG-01', 'is_active' => true]);
        $this->gudang = Gudang::create(['cabang_id' => $this->cabang->id, 'nama' => 'Gudang Utama', 'kode' => 'GDG-01', 'is_active' => true]);

        $this->user = User::create([
            'name' => 'Admin WMS',
            'email' => 'admin-wms@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);
        session(['cabang_id' => $this->cabang->id]);

        $this->actingAs($this->user, 'web');
        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_import_foto_tab_can_be_rendered(): void
    {
        $response = $this->get('/app/wms/import-export?tab=foto');
        $response->assertSuccessful();
        $response->assertSee('Import Foto Massal');
        $response->assertSee('Pilih Sumber Foto');
    }

    public function test_bulk_scan_and_match_image_files_via_livewire(): void
    {
        // 1. Siapkan master produk
        $p1 = Produk::create([
            'nama' => 'LCD iPhone 13 Original',
            'slug' => 'lcd-iphone-13-original-'.uniqid(),
            'kategori' => 'LCD',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 800000,
            'harga_jual_retail' => 1200000,
            'is_active' => true,
        ]);
        SkuVariant::create([
            'produk_id' => $p1->id,
            'sku' => 'LCD-IP13-ORI',
            'nama_varian' => 'Standar',
            'is_active' => true,
        ]);

        $p2 = Produk::create([
            'nama' => 'Baterai Samsung A52',
            'slug' => 'baterai-samsung-a52-'.uniqid(),
            'kategori' => 'Baterai',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 150000,
            'harga_jual_retail' => 250000,
            'is_active' => true,
        ]);
        SkuVariant::create([
            'produk_id' => $p2->id,
            'sku' => 'BAT-SAM-A52',
            'nama_varian' => 'Standar',
            'is_active' => true,
        ]);

        // 2. Unggah file tes melalui Livewire
        $file1 = UploadedFile::fake()->image('LCD-IP13-ORI.jpg', 600, 600);
        $file2 = UploadedFile::fake()->image('BAT-SAM-A52_1.png', 600, 600);
        $file3 = UploadedFile::fake()->image('UNKNOWN-SKU-999.jpg', 600, 600);

        Livewire::test(ImportExportPage::class, ['tab' => 'foto'])
            ->set('activeTab', 'foto')
            ->set('fotoSourceType', 'files')
            ->set('fotoUploads', [$file1, $file2, $file3])
            ->call('scanUploadedFoto')
            ->assertSet('fotoStep', 'preview')
            ->assertSee('LCD-IP13-ORI')
            ->assertSee('BAT-SAM-A52')
            ->assertSee('UNKNOWN-SKU-999')
            ->assertSet('fotoScanResult.total', 3)
            ->assertSet('fotoScanResult.matched_count', 2)
            ->assertSet('fotoScanResult.skipped_count', 1);
    }

    public function test_bulk_processing_chunks_and_updates_products(): void
    {
        $p = Produk::create([
            'nama' => 'Kamera Belakang iPhone 12',
            'slug' => 'kam-bel-ip12-'.uniqid(),
            'kategori' => 'Kamera',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 300000,
            'harga_jual_retail' => 500000,
            'is_active' => true,
            'gambar' => null,
            'foto' => null,
        ]);
        SkuVariant::create([
            'produk_id' => $p->id,
            'sku' => 'KAM-BEL-IP12',
            'nama_varian' => 'Standar',
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->image('KAM-BEL-IP12.jpg', 800, 800);

        $test = Livewire::test(ImportExportPage::class, ['tab' => 'foto'])
            ->set('activeTab', 'foto')
            ->set('fotoUploads', [$file])
            ->call('scanUploadedFoto')
            ->assertSet('fotoStep', 'preview')
            ->call('startFotoProcessing')
            ->assertSet('fotoStep', 'completed')
            ->assertSet('fotoSuccessCount', 1);

        // Verifikasi produk diperbarui dengan foto WebP
        $p->refresh();
        $this->assertNotNull($p->gambar);
        $this->assertStringEndsWith('.webp', $p->gambar);
        $this->assertIsArray($p->foto);
        $this->assertCount(1, $p->foto);
        $this->assertTrue($p->foto[0]['is_primary']);
    }

    public function test_download_skipped_report_excel(): void
    {
        $fileUnknown = UploadedFile::fake()->image('TIDAK-ADA-SKU.jpg', 400, 400);

        Livewire::test(ImportExportPage::class, ['tab' => 'foto'])
            ->set('activeTab', 'foto')
            ->set('fotoUploads', [$fileUnknown])
            ->call('scanUploadedFoto')
            ->assertSet('fotoScanResult.skipped_count', 1)
            ->call('downloadFotoSkippedExcel')
            ->assertFileDownloaded();
    }

    public function test_reset_foto_upload_cleans_up_state(): void
    {
        $file = UploadedFile::fake()->image('ANY-FILE.jpg', 400, 400);

        Livewire::test(ImportExportPage::class, ['tab' => 'foto'])
            ->set('activeTab', 'foto')
            ->set('fotoUploads', [$file])
            ->call('scanUploadedFoto')
            ->call('resetFotoUpload')
            ->assertSet('fotoStep', 'upload')
            ->assertSet('fotoUploads', [])
            ->assertSet('fotoScanResult', []);
    }

    public function test_system_wide_synchronization_across_modules(): void
    {
        $produk = Produk::create([
            'nama' => 'Flexible Charger iPhone 11',
            'slug' => 'flx-chg-ip11-'.uniqid(),
            'kategori' => 'Flexible',
            'kondisi' => 'baru',
            'satuan' => 'pcs',
            'harga_beli' => 50000,
            'harga_jual_retail' => 95000,
            'is_active' => true,
        ]);
        SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'FLX-CHG-IP11',
            'nama_varian' => 'Standar',
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->image('FLX-CHG-IP11.jpg', 800, 800);

        Livewire::test(ImportExportPage::class, ['tab' => 'foto'])
            ->set('activeTab', 'foto')
            ->set('fotoUploads', [$file])
            ->call('scanUploadedFoto')
            ->call('startFotoProcessing')
            ->assertSet('fotoSuccessCount', 1);

        $produk->refresh();

        // 1. Verifikasi WMS & Kasir POS Thumbnail
        $this->assertNotNull($produk->thumbnail_url);
        $this->assertStringContainsString('_thumb.webp', $produk->thumbnail_url);

        // 2. Verifikasi Marketplace & E-commerce Galeri
        $this->assertNotNull($produk->foto_utama);
        $this->assertStringEndsWith('.webp', $produk->foto_utama);
        $this->assertCount(1, $produk->galeri_foto);
        $this->assertEquals($produk->foto_utama, $produk->galeri_foto[0]['url']);
        $this->assertTrue($produk->galeri_foto[0]['is_primary']);
    }
}
