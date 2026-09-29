<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Marketplace\Livewire\ShopPage;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Services\ProductImageService;
use App\Modules\Wms\Services\ProdukService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProdukKatalogOptimasiTest extends TestCase
{
    use RefreshDatabase;

    private function authedAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $cabang = Cabang::create(['nama' => 'Cabang Utama', 'kode' => 'CBG-01', 'is_active' => true]);
        Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'Gudang Pusat', 'kode' => 'GDG-01', 'is_active' => true]);

        $user = User::create([
            'name' => 'Admin WMS',
            'email' => 'admin-katalog@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $user->cabangs()->attach($cabang->id);
        session(['cabang_id' => $cabang->id]);

        $this->actingAs($user, 'web');

        return $user;
    }

    public function test_hierarki_kategori_dan_tree(): void
    {
        $parent = KategoriProduk::create(['nama' => 'Sparepart Baru', 'urutan' => 1]);
        $child1 = KategoriProduk::create(['parent_id' => $parent->id, 'nama' => 'LCD Screen Baru', 'urutan' => 1]);
        $child2 = KategoriProduk::create(['parent_id' => $parent->id, 'nama' => 'Baterai Baru', 'urutan' => 2]);

        $tree = KategoriProduk::getTree();
        $found = $tree->firstWhere('id', $parent->id);
        $this->assertNotNull($found);
        $this->assertEquals('Sparepart Baru', $found->nama);
        $this->assertCount(2, $found->children);
    }

    public function test_image_service_generates_webp_and_thumbnail(): void
    {
        Storage::fake('public');
        $service = app(ProductImageService::class);

        $file = UploadedFile::fake()->image('test_lcd.jpg', 600, 600);
        $result = $service->prosesDanSimpan($file);

        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('thumb', $result);
        $this->assertStringEndsWith('.webp', $result['url']);
        $this->assertStringEndsWith('.webp', $result['thumb']);

        $service->hapusFoto($result['url'], $result['thumb']);
    }

    public function test_produk_service_creates_and_updates_with_kompatibilitas(): void
    {
        $this->authedAdmin();
        $service = app(ProdukService::class);

        $kat = KategoriProduk::create(['nama' => 'LCD & Layar']);
        $brand = Brand::firstOrCreate(['nama' => 'Apple']);

        $p1 = $service->buatProduk(
            nama: 'LCD iPhone 13 Original',
            kategori: 'LCD',
            brand: 'Apple',
            model: 'iPhone 13',
            kondisi: 'baru',
            hargaBeli: 500000,
            hargaJual: 850000,
            sku: 'LCD-IP13-01',
            brandId: $brand->id,
            kategoriId: $kat->id,
            foto: [
                ['url' => '/storage/produk/p1.webp', 'thumb' => '/storage/produk/p1_thumb.webp', 'is_primary' => true],
            ]
        );

        $p2 = $service->buatProduk(
            nama: 'LCD iPhone 13 Pro',
            kategori: 'LCD',
            brand: 'Apple',
            model: 'iPhone 13 Pro',
            kondisi: 'baru',
            hargaBeli: 600000,
            hargaJual: 950000,
            sku: 'LCD-IP13PRO-01',
            brandId: $brand->id,
            kategoriId: $kat->id,
            produkKompatibelIds: [$p1->id]
        );

        $this->assertEquals('/storage/produk/p1.webp', $p1->foto_utama);
        $this->assertEquals('/storage/produk/p1_thumb.webp', $p1->thumbnail_url);
        $this->assertTrue($p2->produkKompatibel->contains($p1->id));

        // Test update
        $updated = $service->updateProduk(
            produkId: $p1->id,
            nama: 'LCD iPhone 13 Original (Updated)',
            kategori: 'LCD',
            brand: 'Apple',
            model: 'iPhone 13',
            kondisi: 'baru',
            hargaBeli: 520000,
            hargaJual: 880000,
            kategoriId: $kat->id,
            produkKompatibelIds: [$p2->id]
        );

        $this->assertEquals('LCD iPhone 13 Original (Updated)', $updated->nama);
        $this->assertTrue($updated->produkKompatibel->contains($p2->id));
    }

    public function test_produk_tab_livewire_renders_and_filters_kategori(): void
    {
        $this->authedAdmin();
        $kat = KategoriProduk::create(['nama' => 'Baterai']);
        $p = Produk::create([
            'nama' => 'Baterai iPhone 12 Pro',
            'kategori' => 'Baterai',
            'kategori_id' => $kat->id,
            'kondisi' => 'baru',
            'harga_beli' => 150000,
            'harga_jual_retail' => 300000,
            'is_active' => true,
        ]);

        Livewire::test(ProdukTab::class)
            ->assertSee('Baterai iPhone 12 Pro')
            ->assertSee('Semua Kategori')
            ->set('filterKategoriId', $kat->id)
            ->assertSee('Baterai iPhone 12 Pro');
    }

    public function test_marketplace_shop_page_renders(): void
    {
        $cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-01', 'is_active' => true]);
        $kat = KategoriProduk::create(['nama' => 'Tools & Alat']);

        Produk::create([
            'nama' => 'Obeng Presisi 24 in 1',
            'kategori' => 'Tools & Alat',
            'kategori_id' => $kat->id,
            'kondisi' => 'baru',
            'harga_beli' => 25000,
            'harga_jual_retail' => 60000,
            'is_active' => true,
        ]);

        Livewire::test(ShopPage::class)
            ->assertSee('Obeng Presisi 24 in 1')
            ->set('filterKategori', 'Tools & Alat')
            ->assertSee('Obeng Presisi 24 in 1');
    }
}
