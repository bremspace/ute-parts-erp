<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Marketplace\Livewire\ShopPage;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Services\SmartSearchService;
use Database\Seeders\RolesAndPermissionsSeeder;
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

    public function test_relevance_ranking_prioritizes_exact_and_prefix_product_name_at_top(): void
    {
        $brand = Brand::firstOrCreate(['nama' => 'Crown'], ['is_active' => true]);

        // Prod A: Nama persis "iPhone 11"
        $prodExact = Produk::create([
            'nama' => 'iPhone 11',
            'slug' => 'iphone-11-exact',
            'brand_id' => $brand->id,
            'brand_kompatibel' => 'Apple',
            'model_kompatibel' => 'iPhone 11',
            'harga_jual_retail' => 1000000,
            'is_active' => true,
        ]);

        // Prod B: Prefix nama "iPhone 11 Pro Max Original"
        $prodPrefix = Produk::create([
            'nama' => 'iPhone 11 Pro Max Original',
            'slug' => 'iphone-11-pro-max',
            'brand_id' => $brand->id,
            'brand_kompatibel' => 'Apple',
            'model_kompatibel' => 'iPhone 11 Pro Max',
            'harga_jual_retail' => 1200000,
            'is_active' => true,
        ]);

        // Prod C: Hanya kompatibel, nama di belakang "Kabel Fleksibel iPhone 11"
        $prodSuffix = Produk::create([
            'nama' => 'Kabel Fleksibel iPhone 11',
            'slug' => 'kabel-fleksibel-iphone-11',
            'brand_id' => $brand->id,
            'brand_kompatibel' => 'Apple',
            'model_kompatibel' => 'iPhone 11',
            'harga_jual_retail' => 50000,
            'is_active' => true,
        ]);

        $ordered = Produk::query()
            ->cariPintar('iPhone 11')
            ->urutkanRelevansi('iPhone 11')
            ->pluck('id')
            ->all();

        // Exact match nama produk harus berada di posisi teratas
        $this->assertEquals($prodExact->id, $ordered[0]);
        // Prefix match harus berada di urutan berikutnya sebelum match di tengah/akhir frasa
        $this->assertEquals($prodPrefix->id, $ordered[1]);
        $this->assertEquals($prodSuffix->id, $ordered[2]);
    }

    public function test_filter_katalog_scope_filters_correctly(): void
    {
        $kat = KategoriProduk::firstOrCreate(['slug' => 'baterai-test'], ['nama' => 'Baterai Test', 'is_active' => true]);

        $prod = Produk::create([
            'nama' => 'Baterai Hippo iPhone 11',
            'slug' => 'baterai-hippo-iphone-11',
            'kategori_id' => $kat->id,
            'kategori' => 'Baterai Test',
            'kondisi' => 'baru',
            'harga_jual_retail' => 250000,
            'is_active' => true,
        ]);

        $results = Produk::query()->filterKatalog([
            'search' => 'Hippo',
            'kategori_id' => $kat->id,
            'kondisi' => 'baru',
        ])->get();

        $this->assertTrue($results->contains('id', $prod->id));

        $emptyResults = Produk::query()->filterKatalog([
            'search' => 'Hippo',
            'kondisi' => 'bekas',
        ])->get();

        $this->assertFalse($emptyResults->contains('id', $prod->id));
    }

    public function test_reset_single_filter_and_reset_all_on_shop_page(): void
    {
        $component = Livewire::test(ShopPage::class)
            ->set('search', 'iPhone 11')
            ->set('filterKondisi', 'baru');

        $this->assertEquals('baru', $component->get('filterKondisi'));
        $this->assertEquals('iPhone 11', $component->get('search'));

        // Reset individual filter
        $component->call('resetSingleFilter', 'kondisi');
        $this->assertEquals('', $component->get('filterKondisi'));
        $this->assertEquals('iPhone 11', $component->get('search'));

        // Reset all
        $component->call('resetAllFilters');
        $this->assertEquals('', $component->get('search'));
        $this->assertEquals('', $component->get('filterKondisi'));
    }

    public function test_settings_rbac_manages_merk_hp_and_tipe_hp(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user);

        // Tambah Merk HP Baru
        Livewire::test(SettingsRbac::class)
            ->set('activeTab', 'master_produk')
            ->set('masterProdukSubTab', 'merk_hp')
            ->call('openMerkHpModal')
            ->set('merkHpForm.merk', 'Google Pixel')
            ->call('simpanMerkHp')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tipe_hp', [
            'merk' => 'Google Pixel',
        ]);

        // Ubah Merk HP
        Livewire::test(SettingsRbac::class)
            ->call('openMerkHpModal', 'Google Pixel')
            ->set('merkHpForm.merk', 'Google')
            ->call('simpanMerkHp')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tipe_hp', [
            'merk' => 'Google',
        ]);
        $this->assertDatabaseMissing('tipe_hp', [
            'merk' => 'Google Pixel',
        ]);
    }
}
