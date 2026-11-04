<?php

namespace Tests\Feature;

use App\Modules\Marketplace\Livewire\KnowledgeBasePage;
use App\Modules\Marketplace\Livewire\ShopPage;
use App\Modules\Marketplace\Models\WebArticle;
use App\Modules\Marketplace\Services\CartService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\TipeHp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PortalUxAndSeoOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Utama',
            'alamat' => 'Jl. Utama',
            'is_active' => true,
        ]);

        $gudang = Gudang::create([
            'cabang_id' => $cabang->id,
            'kode' => 'GDG-01',
            'nama' => 'Gudang Pusat',
            'is_active' => true,
        ]);

        $kategori = KategoriProduk::create([
            'nama' => 'LCD TS',
            'slug' => 'lcd-ts',
            'is_active' => true,
        ]);

        $tipeHp = TipeHp::firstOrCreate(
            ['merk' => 'Apple', 'model' => 'iPhone 13'],
            ['is_active' => true]
        );

        $produk = Produk::create([
            'nama' => 'LCD iPhone 13 OLED Super Retina',
            'slug' => 'lcd-iphone-13-oled-super-retina',
            'kategori' => 'LCD TS',
            'kategori_id' => $kategori->id,
            'kode' => 'PRD-IP13',
            'sku' => 'LCD-IP13-001',
            'kondisi' => 'baru',
            'kualitas' => 'OEM',
            'harga_beli' => 450000,
            'harga_jual_retail' => 650000,
            'model_kompatibel' => 'iPhone 13',
            'is_active' => true,
            'garansi_hari' => 30,
        ]);

        $produk->tipeHps()->attach($tipeHp->id);

        $variant = SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'LCD-IP13-BLK',
            'nama_varian' => 'Black / Hitam',
            'is_active' => true,
        ]);

        StokItem::create([
            'cabang_id' => $cabang->id,
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'sku_variant_id' => $variant->id,
            'jumlah' => 15,
        ]);
    }

    public function test_panduan_lab_returns_200_and_renders_knowledge_base(): void
    {
        WebArticle::create([
            'judul' => 'Panduan Ganti Baterai iPhone 13',
            'slug' => 'panduan-ganti-baterai-iphone-13',
            'kategori' => 'Baterai',
            'ringkasan' => 'Langkah-langkah penggantian baterai iPhone 13 aman dan presisi.',
            'konten_lengkap' => 'Konten lengkap panduan.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->get('/panduan');
        $response->assertStatus(200);
        $response->assertSee('Pusat Edukasi');

        Livewire::test(KnowledgeBasePage::class)
            ->assertStatus(200)
            ->call('setKategori', 'Baterai')
            ->assertSet('kategori', 'Baterai');
    }

    public function test_catalog_has_add_to_cart_and_dispatches_properly(): void
    {
        $produk = Produk::first();

        $response = $this->get('/shop');
        $response->assertStatus(200);
        $response->assertSee('+ Keranjang');
        $response->assertSee('addToCart('.$produk->id.')');

        Livewire::test(ShopPage::class)
            ->call('addToCart', $produk->id)
            ->assertDispatched('cart-updated')
            ->assertDispatched('alert');

        $cart = app(CartService::class)->all();
        $this->assertNotEmpty($cart);
        $this->assertEquals(1, collect($cart)->first()['qty']);
    }

    public function test_catalog_category_and_compatibility_routing_and_breadcrumbs(): void
    {
        $responseKategori = $this->get('/shop/kategori/lcd-ts');
        $responseKategori->assertStatus(200);
        $responseKategori->assertSee('Katalog Sparepart');
        $responseKategori->assertSee('LCD TS');

        $responseModel = $this->get('/shop/kategori/lcd-ts/iphone-13');
        $responseModel->assertStatus(200);
        $responseModel->assertSee('iPhone 13');

        $produk = Produk::first();
        $responseDetail = $this->get('/shop/'.$produk->slug);
        $responseDetail->assertStatus(200);
        $responseDetail->assertSee('aria-label="Breadcrumb"', false);
        $responseDetail->assertSee('Katalog Sparepart');
        $responseDetail->assertSee('LCD TS');
    }

    public function test_schema_org_structured_data_on_catalog_and_detail(): void
    {
        $catalogResponse = $this->get('/shop');
        $catalogHtml = $catalogResponse->getContent();

        $this->assertStringContainsString('https://schema.org', $catalogHtml);
        $this->assertStringContainsString('"@type": "WebSite"', $catalogHtml);
        $this->assertStringContainsString('"@type": "ItemList"', $catalogHtml);
        $this->assertStringContainsString('"@type": "BreadcrumbList"', $catalogHtml);

        $produk = Produk::first();
        $detailResponse = $this->get('/shop/'.$produk->slug);
        $detailHtml = $detailResponse->getContent();

        $this->assertStringContainsString('"@type": "Product"', $detailHtml);
        $this->assertStringContainsString('"@type": "BreadcrumbList"', $detailHtml);
        $this->assertStringContainsString('rel="canonical"', $detailHtml);
    }
}
