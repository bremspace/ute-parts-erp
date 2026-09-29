<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WmsLivewireUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cabang $cabang;

    private Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CB01',
            'nama' => 'Cabang Utama',
            'is_pusat' => true,
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'GDG-01',
            'nama' => 'Gudang Utama',
            'is_default' => true,
            'is_active' => true,
        ]);

        Rak::create([
            'gudang_id' => $this->gudang->id,
            'kode' => 'RAK-01',
            'nama' => 'Rak A1',
            'is_active' => true,
        ]);

        SatuanUnit::firstOrCreate(['kode' => 'pcs'], ['nama' => 'Pieces', 'is_active' => true]);
        KategoriProduk::firstOrCreate(['nama' => 'Umum']);

        $role = Role::create(['name' => 'super-admin']);
        Permission::findOrCreate('wms.view');
        Permission::findOrCreate('wms.create');
        Permission::findOrCreate('wms.update');

        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
        $this->admin->givePermissionTo(['wms.view', 'wms.create', 'wms.update']);

        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_livewire_import_file_upload_and_preview(): void
    {
        $this->actingAs($this->admin);

        Storage::fake('local');

        $csvContent = implode("\n", [
            'sku;nama;satuan;harga_beli;harga_jual',
            'TES-UPLOAD-01;Produk Test Upload 1;pcs;10000;15000',
            'TES-UPLOAD-02;Produk Test Upload 2;buah;20000;25000',
        ]);
        $uploaded = UploadedFile::fake()->createWithContent('import_test.csv', $csvContent);

        Livewire::test(ProdukTab::class)
            ->call('openImportModal')
            ->set('importFile', $uploaded)
            ->call('previewImport')
            ->assertSet('importStep', 'preview')
            ->assertSet('importPreview.valid', 2)
            ->assertSet('importPreview.invalid', 0)
            ->call('commitImport')
            ->assertSet('importStep', 'selesai')
            ->assertSet('activeImportLog.status', 'selesai')
            ->assertSet('activeImportLog.sukses', 2)
            ->assertSet('activeImportLog.gagal', 0)
            ->call('tutupImportModal')
            ->assertSet('showImportModal', false);
    }

    public function test_livewire_import_queued_for_large_batches(): void
    {
        $this->actingAs($this->admin);

        Storage::fake('local');

        $rows = ['sku;nama;satuan;harga_beli;harga_jual'];
        for ($i = 1; $i <= 105; $i++) {
            $rows[] = "BATCH-{$i};Produk Batch {$i};pcs;10000;15000";
        }
        $csvContent = implode("\n", $rows);
        $uploaded = UploadedFile::fake()->createWithContent('import_large.csv', $csvContent);

        Livewire::test(ProdukTab::class)
            ->call('openImportModal')
            ->set('importFile', $uploaded)
            ->call('previewImport')
            ->assertSet('importStep', 'preview')
            ->assertSet('importPreview.valid', 105)
            ->call('commitImport')
            ->assertSet('importStep', 'selesai')
            ->assertSet('activeImportLog.status', 'selesai')
            ->assertSet('activeImportLog.sukses', 105);
    }

    public function test_livewire_product_photo_upload(): void
    {
        $this->actingAs($this->admin);

        Storage::fake('public');

        $photo1 = UploadedFile::fake()->image('produk1.jpg', 600, 600);
        $photo2 = UploadedFile::fake()->image('produk2.png', 400, 400);

        Livewire::test(ProdukTab::class)
            ->call('openProdukModal')
            ->set('produkForm.nama', 'Produk Dengan Foto Upload')
            ->set('produkForm.sku', 'FOTO-001')
            ->set('produkForm.satuan_kode', 'pcs')
            ->set('produkForm.kondisi', 'baru')
            ->set('produkForm.harga_beli', 50000)
            ->set('produkForm.harga_jual_retail', 75000)
            ->set('fotoUploads', [$photo1, $photo2])
            ->call('simpanProduk');

        $produk = Produk::where('nama', 'Produk Dengan Foto Upload')->first();
        $this->assertNotNull($produk);
        $this->assertNotNull($produk->foto_utama);
        $this->assertIsArray($produk->foto);
        $this->assertCount(2, $produk->foto);
    }

    public function test_wms_controller_import_preview_api(): void
    {
        $this->actingAs($this->admin);

        $csvContent = implode("\n", [
            'sku;nama;satuan;harga_beli;harga_jual',
            'API-01;Produk API 1;pcs;10000;15000',
        ]);
        $file = UploadedFile::fake()->createWithContent('api_test.csv', $csvContent);

        $response = $this->postJson('/api/wms/produk/import/preview', [
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valid', 1);
    }

    public function test_marketplace_import_preview_and_commit_api(): void
    {
        $this->actingAs($this->admin);

        $csvContent = implode("\n", [
            'nama,harga_jual,kondisi,kategori',
            'Produk Marketplace Test,50000,baru,Umum',
        ]);
        $file = UploadedFile::fake()->createWithContent('marketplace_test.csv', $csvContent);

        $previewResponse = $this->postJson('/api/import/produk/preview', [
            'file' => $file,
        ]);

        $previewResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valid', 1);

        $commitResponse = $this->postJson('/api/import/produk/commit', [
            'file' => $file,
            'confirmed' => true,
        ]);

        $commitResponse->assertOk()
            ->assertJsonPath('success', true);
    }
}
