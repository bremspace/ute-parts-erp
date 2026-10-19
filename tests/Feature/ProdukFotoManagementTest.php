<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Services\ProductImageService;
use App\Modules\Wms\Services\ProdukService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProdukFotoManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cabang::firstOrCreate(
            ['kode' => 'CBG-01'],
            ['nama' => 'Cabang Pusat', 'is_pusat' => true, 'is_active' => true]
        );
    }

    protected function authedAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $cabang = Cabang::firstOrCreate(
            ['kode' => 'CBG-01'],
            ['nama' => 'Cabang Pusat', 'is_pusat' => true, 'is_active' => true]
        );
        Gudang::firstOrCreate(
            ['kode' => 'GDG-01'],
            ['cabang_id' => $cabang->id, 'nama' => 'Gudang Pusat', 'is_active' => true]
        );

        $user = User::create([
            'name' => 'Admin WMS',
            'email' => 'admin-foto-'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $user->cabangs()->attach($cabang->id);

        session([
            'cabang_id' => $cabang->id,
            'cabang_aktif_id' => $cabang->id,
        ]);

        $this->actingAs($user, 'web');

        return $user;
    }

    public function test_format_foto_url_normalizes_various_formats(): void
    {
        // 1. Storage relative path
        $this->assertEquals('/storage/produk/2026/10/test.webp', Produk::formatFotoUrl('/storage/produk/2026/10/test.webp'));
        $this->assertEquals('/storage/produk/2026/10/test.webp', Produk::formatFotoUrl('storage/produk/2026/10/test.webp'));

        // 2. Absolute URL with different host
        $this->assertEquals('/storage/produk/2026/10/test.webp', Produk::formatFotoUrl('http://66.42.48.27/storage/produk/2026/10/test.webp'));
        $this->assertEquals('/storage/produk/2026/10/test.webp', Produk::formatFotoUrl('https://staging.uteparts.id/storage/produk/2026/10/test.webp'));

        // 3. Raw filename/path
        $this->assertEquals('/storage/produk/test.webp', Produk::formatFotoUrl('produk/test.webp'));

        // 4. Null & empty string
        $this->assertNull(Produk::formatFotoUrl(null));
        $this->assertNull(Produk::formatFotoUrl('   '));
    }

    public function test_product_image_service_generates_webp_and_handles_cleanup(): void
    {
        Storage::fake('public');
        $service = app(ProductImageService::class);

        $file = UploadedFile::fake()->image('sparepart.jpg', 800, 600);
        $result = $service->prosesDanSimpan($file);

        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('thumb', $result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('thumb_path', $result);

        $this->assertStringStartsWith('/storage/produk/', $result['url']);
        $this->assertStringEndsWith('.webp', $result['url']);
        $this->assertStringEndsWith('_thumb.webp', $result['thumb']);

        // Pastikan file fisik tersimpan di disk public
        Storage::disk('public')->assertExists($result['path']);
        Storage::disk('public')->assertExists($result['thumb_path']);

        // Test penghapusan foto
        $service->hapusFoto($result['url'], $result['thumb']);
        Storage::disk('public')->assertMissing($result['path']);
        Storage::disk('public')->assertMissing($result['thumb_path']);
    }

    public function test_produk_model_accessors_with_gallery_and_primary_flag(): void
    {
        $kat = KategoriProduk::create(['nama' => 'Baterai']);
        $service = app(ProdukService::class);
        $this->authedAdmin();

        $produk = $service->buatProduk(
            nama: 'Baterai iPhone 14 Pro Max',
            kategori: 'Baterai',
            brand: 'Apple',
            model: 'iPhone 14 Pro Max',
            kondisi: 'baru',
            hargaBeli: 250000,
            hargaJual: 450000,
            sku: 'BAT-IP14PM',
            kategoriId: $kat->id,
            foto: [
                ['url' => '/storage/produk/bat1.webp', 'thumb' => '/storage/produk/bat1_thumb.webp', 'is_primary' => false],
                ['url' => '/storage/produk/bat2.webp', 'thumb' => '/storage/produk/bat2_thumb.webp', 'is_primary' => true],
            ]
        );

        $this->assertEquals('/storage/produk/bat2.webp', $produk->foto_utama);
        $this->assertEquals('/storage/produk/bat2_thumb.webp', $produk->thumbnail_url);
        $this->assertCount(2, $produk->galeri_foto);
        $this->assertTrue($produk->galeri_foto[1]['is_primary']);
    }

    public function test_produk_tab_livewire_can_manage_photos(): void
    {
        Storage::fake('public');
        $this->authedAdmin();

        $kat = KategoriProduk::create(['nama' => 'Konektor']);

        $file1 = UploadedFile::fake()->image('konektor1.png', 400, 400);
        $file2 = UploadedFile::fake()->image('konektor2.png', 400, 400);

        Livewire::test(ProdukTab::class)
            ->set('produkForm.nama', 'Flexible Charger Type C')
            ->set('produkForm.kategori_id', $kat->id)
            ->set('produkForm.harga_beli', 35000)
            ->set('produkForm.harga_jual_retail', 70000)
            ->set('produkForm.sku', 'FLX-CHG-01')
            ->set('fotoUploads', [$file1, $file2])
            ->call('simpanProduk');

        $p = Produk::where('sku', 'FLX-CHG-01')->orWhereHas('skuVariants', fn ($q) => $q->where('sku', 'FLX-CHG-01'))->first();
        $this->assertNotNull($p);
        $this->assertNotEmpty($p->foto);
        $this->assertCount(2, $p->foto);
        $this->assertNotNull($p->foto_utama);
        $this->assertNotNull($p->thumbnail_url);

        // Edit produk & ganti foto primary
        Livewire::test(ProdukTab::class)
            ->call('openEditProdukModal', $p->id)
            ->call('setFotoUtama', 1)
            ->call('simpanEditProduk');

        $p->refresh();
        $this->assertTrue($p->foto[1]['is_primary']);
        $this->assertEquals($p->foto[1]['url'], $p->foto_utama);
    }

    public function test_produk_tab_livewire_can_capture_photo_directly_from_camera(): void
    {
        Storage::fake('public');
        $this->authedAdmin();

        $kat = KategoriProduk::create(['nama' => 'Camera Sparepart']);

        $fotoKamera1 = UploadedFile::fake()->image('kamera_snap_1.jpg', 640, 480);
        $fotoKamera2 = UploadedFile::fake()->image('kamera_snap_2.jpg', 640, 480);

        // Test pada Tambah Produk via kamera langsung
        $component = Livewire::test(ProdukTab::class)
            ->call('openProdukModal')
            ->set('fotoKameraUpload', $fotoKamera1);

        // Verifikasi fotoKameraUpload otomatis pindah ke fotoUploads dan fotoKameraUpload di-reset
        $this->assertCount(1, $component->get('fotoUploads'));
        $this->assertNull($component->get('fotoKameraUpload'));

        // Ambil foto kedua via kamera
        $component->set('fotoKameraUpload', $fotoKamera2);
        $this->assertCount(2, $component->get('fotoUploads'));

        // Test hapus salah satu foto upload baru
        $component->call('hapusFotoUploadBaru', 1);
        $this->assertCount(1, $component->get('fotoUploads'));

        // Simpan produk
        $component->set('produkForm.nama', 'Kamera Modul Depan Original')
            ->set('produkForm.kategori_id', $kat->id)
            ->set('produkForm.harga_beli', 85000)
            ->set('produkForm.harga_jual_retail', 150000)
            ->set('produkForm.sku', 'CAM-MOD-01')
            ->call('simpanProduk');

        $p = Produk::where('sku', 'CAM-MOD-01')->orWhereHas('skuVariants', fn ($q) => $q->where('sku', 'CAM-MOD-01'))->first();
        $this->assertNotNull($p);
        $this->assertCount(1, $p->foto);

        // Test pada Edit Produk via kamera langsung
        $fotoKameraEdit = UploadedFile::fake()->image('kamera_snap_edit.jpg', 640, 480);
        $editComponent = Livewire::test(ProdukTab::class)
            ->call('openEditProdukModal', $p->id)
            ->set('editFotoKameraUpload', $fotoKameraEdit);

        $this->assertCount(1, $editComponent->get('editFotoUploads'));
        $this->assertNull($editComponent->get('editFotoKameraUpload'));

        $editComponent->call('simpanEditProduk');

        $p->refresh();
        $this->assertCount(2, $p->foto);
    }
}
