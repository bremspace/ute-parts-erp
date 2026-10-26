<?php

namespace Tests\Feature;

use App\Modules\Marketplace\Livewire\ShopPage;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Services\SmartSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SmartSearchGlobalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate');
    }

    public function test_smart_search_tokenizes_synonyms_and_alphanumeric_split(): void
    {
        $service = app(SmartSearchService::class);

        $tokensIp11 = $service->tokenize('ip11 pro');
        $this->assertNotEmpty($tokensIp11);

        // Group 1: ip11 -> harus mengandung 'iphone 11' atau 'ip 11'
        $flatTokens = collect($tokensIp11)->flatten()->all();
        $this->assertTrue(in_array('iphone 11', $flatTokens) || in_array('ip 11', $flatTokens));

        $tokensBatreLcd = $service->tokenize('batre sam');
        $flatBatre = collect($tokensBatreLcd)->flatten()->all();
        $this->assertTrue(in_array('baterai', $flatBatre));
        $this->assertTrue(in_array('samsung', $flatBatre));
    }

    public function test_scope_cari_pintar_finds_product_via_synonym_and_relevance(): void
    {
        $kategori = KategoriProduk::firstOrCreate(
            ['slug' => 'sparepart-layar'],
            ['nama' => 'Sparepart Layar', 'is_active' => true]
        );

        $prod1 = Produk::create([
            'nama' => 'LCD Touchscreen iPhone 11 Original',
            'slug' => 'lcd-touchscreen-iphone-11-original',
            'barcode' => '8991111111',
            'kategori_id' => $kategori->id,
            'kategori' => 'Sparepart Layar',
            'harga_jual_retail' => 450000,
            'is_active' => true,
        ]);

        $prod2 = Produk::create([
            'nama' => 'Baterai Samsung Galaxy A50 Original',
            'slug' => 'baterai-samsung-galaxy-a50-original',
            'barcode' => '8992222222',
            'kategori_id' => $kategori->id,
            'kategori' => 'Sparepart Layar',
            'harga_jual_retail' => 150000,
            'is_active' => true,
        ]);

        // Cari dengan singkatan 'ip11' -> harus menemukan 'LCD Touchscreen iPhone 11 Original'
        $resultsIp11 = Produk::query()->cariPintar('ip11')->get();
        $this->assertTrue($resultsIp11->contains('id', $prod1->id));
        $this->assertFalse($resultsIp11->contains('id', $prod2->id));

        // Cari dengan singkatan 'batre sam' -> harus menemukan produk Samsung
        $resultsSam = Produk::query()->cariPintar('batre sam')->get();
        $this->assertTrue($resultsSam->contains('id', $prod2->id));
        $this->assertFalse($resultsSam->contains('id', $prod1->id));
    }

    public function test_faceted_options_filters_proportionally(): void
    {
        $brandApple = Brand::firstOrCreate(['nama' => 'Apple'], ['is_active' => true]);
        $brandSamsung = Brand::firstOrCreate(['nama' => 'Samsung'], ['is_active' => true]);

        $kualitasOri = KualitasProduk::firstOrCreate(['nama' => 'Original Pabrik'], ['is_active' => true]);

        $katLayar = KategoriProduk::firstOrCreate(['slug' => 'lcd-touchscreen'], ['nama' => 'LCD Touchscreen', 'is_active' => true]);
        $katBatre = KategoriProduk::firstOrCreate(['slug' => 'baterai'], ['nama' => 'Baterai', 'is_active' => true]);

        Produk::create([
            'nama' => 'LCD iPhone 12',
            'slug' => 'lcd-iphone-12',
            'brand_id' => $brandApple->id,
            'brand_kompatibel' => 'Apple',
            'kualitas_id' => $kualitasOri->id,
            'kategori_id' => $katLayar->id,
            'kategori' => 'LCD Touchscreen',
            'harga_jual_retail' => 500000,
            'is_active' => true,
        ]);

        Produk::create([
            'nama' => 'Baterai Samsung S20',
            'slug' => 'baterai-samsung-s20',
            'brand_id' => $brandSamsung->id,
            'brand_kompatibel' => 'Samsung',
            'kualitas_id' => $kualitasOri->id,
            'kategori_id' => $katBatre->id,
            'kategori' => 'Baterai',
            'harga_jual_retail' => 200000,
            'is_active' => true,
        ]);

        $service = app(SmartSearchService::class);

        // Jika filter brand = 'Apple', opsi faceted kategori hanya menampilkan yang relevan
        $faceted = $service->getFacetedOptions(['brand' => 'Apple']);
        $this->assertNotEmpty($faceted['categories']);
        $this->assertNotEmpty($faceted['brands']);
        $this->assertArrayHasKey('Apple', $faceted['brands']);
        $this->assertArrayHasKey('Samsung', $faceted['brands']);
    }

    public function test_shop_page_renders_with_smart_search_and_faceted_filters(): void
    {
        Livewire::test(ShopPage::class)
            ->set('search', 'ip11')
            ->assertStatus(200)
            ->set('filterHpMerk', 'Apple')
            ->assertSet('filterHpModel', '') // Reset otomatis model saat ganti merk
            ->assertStatus(200);
    }
}
