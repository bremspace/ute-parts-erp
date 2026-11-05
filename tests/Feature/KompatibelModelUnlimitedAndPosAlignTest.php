<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Marketplace\Livewire\ShopPage;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\ProdukService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class KompatibelModelUnlimitedAndPosAlignTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    private Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Cabang Test', 'kode' => 'CB-TEST', 'is_active' => true]);
        $this->gudang = Gudang::create(['nama' => 'Gudang Pusat', 'kode' => 'GD-01', 'cabang_id' => $this->cabang->id, 'is_active' => true]);

        SatuanUnit::firstOrCreate(['kode' => 'PCS'], ['nama' => 'Pieces', 'is_active' => true]);

        $this->user = User::create([
            'name' => 'Kasir Test',
            'email' => 'kasir@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
    }

    public function test_unlimited_model_kompatibel_can_be_stored_without_truncation_error(): void
    {
        // Buat 60 tipe HP dengan nama panjang (menghasilkan string > 2000 karakter)
        $tipeHpIds = [];
        for ($i = 1; $i <= 60; $i++) {
            $th = TipeHp::create([
                'merk' => 'BrandTestLongName',
                'model' => "Model Super Panjang Untuk Kompatibilitas Ponsel Nomor Seri {$i} Generasi Terbaru Pro Max Ultra",
                'nama' => "BrandTestLongName Model {$i}",
                'is_active' => true,
            ]);
            $tipeHpIds[] = $th->id;
        }

        $service = app(ProdukService::class);
        $produk = $service->buatProduk(
            nama: 'Flexible Konektor Universal Multi Device',
            kategori: 'Sparepart',
            brand: null,
            model: null,
            kondisi: 'baru',
            hargaBeli: 15000,
            hargaJual: 30000,
            satuanKode: 'PCS',
            tipeHpIds: $tipeHpIds,
        );

        $produk->refresh();

        $this->assertNotNull($produk->model_kompatibel);
        $this->assertGreaterThan(2000, strlen($produk->model_kompatibel));
        $this->assertCount(60, $produk->tipeHps);
        $this->assertCount(60, $produk->daftar_kompatibel);
    }

    public function test_pos_kasir_displays_compatible_model_with_brand_prepended(): void
    {
        $th = TipeHp::create([
            'merk' => 'Oppo',
            'model' => 'A11',
            'nama' => 'Oppo A11',
            'is_active' => true,
        ]);

        $produk = Produk::create([
            'nama' => 'LCD Touchscreen Fullset Oppo A11 Original',
            'kategori' => 'LCD Touchscreen',
            'satuan' => 'PCS',
            'harga_beli' => 120000,
            'harga_jual_retail' => 180000,
            'brand_kompatibel' => 'Oppo',
            'model_kompatibel' => 'A11',
            'is_active' => true,
        ]);

        SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'LCD-OPPO-A11-001',
            'nama_varian' => 'Default',
            'satuan_kode' => 'PCS',
            'harga_beli' => 120000,
            'is_active' => true,
        ]);

        $produk->tipeHps()->sync([$th->id]);

        $this->actingAs($this->user);
        session(['cabang_id' => $this->cabang->id]);

        Livewire::test(PosKasir::class)
            ->assertSee('Oppo A11')
            ->assertDontSee('📱 A11 ');
    }

    public function test_pos_kasir_search_query_finds_product_and_renders_formatted_model(): void
    {
        $th = TipeHp::create([
            'merk' => 'Oppo',
            'model' => 'A11',
            'nama' => 'Oppo A11',
            'is_active' => true,
        ]);

        $produk = Produk::create([
            'nama' => 'Flexible Charger Port A11',
            'kategori' => 'Flexible',
            'satuan' => 'PCS',
            'harga_beli' => 20000,
            'harga_jual_retail' => 45000,
            'brand_kompatibel' => 'Oppo',
            'model_kompatibel' => 'A11',
            'is_active' => true,
        ]);

        SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'FLX-OPP-A11',
            'nama_varian' => 'Default',
            'satuan_kode' => 'PCS',
            'harga_beli' => 20000,
            'is_active' => true,
        ]);

        $produk->tipeHps()->sync([$th->id]);

        $this->actingAs($this->user);
        session(['cabang_id' => $this->cabang->id]);

        Livewire::test(PosKasir::class)
            ->set('search', 'Oppo A11')
            ->assertSee('Flexible Charger Port A11')
            ->assertSee('Oppo A11');
    }

    public function test_produk_daftar_kompatibel_accessor_formatting(): void
    {
        // 1. Dari relasi tipeHps
        $th1 = TipeHp::create(['merk' => 'Oppo', 'model' => 'A11', 'nama' => 'Oppo A11']);
        $th2 = TipeHp::create(['merk' => 'Vivo', 'model' => 'Y12', 'nama' => 'Vivo Y12']);

        $p = Produk::create([
            'nama' => 'Baterai Universal A',
            'kategori' => 'Baterai',
            'satuan' => 'PCS',
            'harga_beli' => 50000,
            'harga_jual_retail' => 80000,
            'is_active' => true,
        ]);
        $p->tipeHps()->sync([$th1->id, $th2->id]);
        $p->load('tipeHps');

        $this->assertEquals(['Oppo A11', 'Vivo Y12'], $p->daftar_kompatibel->all());

        // 2. Dari JSON kompatibilitas_hp
        $pJson = Produk::create([
            'nama' => 'Baterai Universal B',
            'kategori' => 'Baterai',
            'satuan' => 'PCS',
            'harga_beli' => 50000,
            'harga_jual_retail' => 80000,
            'kompatibilitas_hp' => [
                ['merk' => 'Samsung', 'model' => 'Galaxy A54'],
                ['merk' => 'Xiaomi', 'model' => 'Redmi Note 12'],
            ],
            'is_active' => true,
        ]);

        $this->assertEquals(['Samsung Galaxy A54', 'Xiaomi Redmi Note 12'], $pJson->daftar_kompatibel->all());

        // 3. Dari model_kompatibel string fallback
        $pString = Produk::create([
            'nama' => 'Baterai Universal C',
            'kategori' => 'Baterai',
            'satuan' => 'PCS',
            'harga_beli' => 50000,
            'harga_jual_retail' => 80000,
            'brand_kompatibel' => 'Realme',
            'model_kompatibel' => 'C67, 11 Pro',
            'is_active' => true,
        ]);

        $this->assertEquals(['Realme C67', 'Realme 11 Pro'], $pString->daftar_kompatibel->all());
    }

    public function test_tipe_hp_format_nama_tampilan_and_resolve_label(): void
    {
        // 1. Model iPhone 11 dengan merk Apple -> Apple iPhone 11
        $this->assertEquals('Apple iPhone 11', TipeHp::formatNamaTampilan('Apple', 'iPhone 11'));

        // 2. Model 11 dengan merk Apple -> Apple iPhone 11
        $this->assertEquals('Apple iPhone 11', TipeHp::formatNamaTampilan('Apple', '11'));

        // 3. Nama lengkap atribut terisi -> Apple iPhone 11 (A2221)
        $this->assertEquals('Apple iPhone 11 (A2221)', TipeHp::formatNamaTampilan('Apple', 'iPhone 11', 'Apple iPhone 11 (A2221)'));
        $this->assertEquals('Apple iPhone 11 (A2221)', TipeHp::formatNamaTampilan('Apple', 'iPhone 11', 'iPhone 11 (A2221)'));

        // 4. Model Galaxy A52 dengan merk Samsung -> Samsung Galaxy A52
        $this->assertEquals('Samsung Galaxy A52', TipeHp::formatNamaTampilan('Samsung', 'Galaxy A52'));

        // 5. Model sudah mengandung merk -> Samsung Galaxy A52 (tidak dobel)
        $this->assertEquals('Samsung Galaxy A52', TipeHp::formatNamaTampilan('Samsung', 'Samsung Galaxy A52'));

        // 6. Generic / Universal
        $this->assertEquals('Multi-Brand Qualcomm', TipeHp::formatNamaTampilan('Universal', 'Multi-Brand Qualcomm'));

        // 7. Resolusi dari database TipeHp
        TipeHp::updateOrCreate(
            ['merk' => 'Apple', 'model' => 'iPhone 11'],
            ['nama' => 'Apple iPhone 11 (A2221)', 'is_active' => true]
        );
        Cache::forget('tipe_hp_resolution_map');

        $this->assertEquals('Apple iPhone 11 (A2221)', TipeHp::resolveLabelForModel('iPhone 11'));
        $this->assertEquals('Apple iPhone 11 (A2221)', TipeHp::resolveLabelForModel('iPhone 11', 'Apple'));
    }

    public function test_filter_dropdown_tipe_hp_includes_brand_in_pos_kasir(): void
    {
        TipeHp::updateOrCreate(
            ['merk' => 'Apple', 'model' => 'iPhone 11'],
            ['nama' => 'Apple iPhone 11 (A2221)', 'is_active' => true]
        );
        TipeHp::updateOrCreate(
            ['merk' => 'Samsung', 'model' => 'Galaxy A52'],
            ['nama' => null, 'is_active' => true]
        );
        Cache::forget('tipe_hp_resolution_map');

        $this->actingAs($this->user);
        session(['cabang_id' => $this->cabang->id]);

        Livewire::test(PosKasir::class)
            ->assertSeeHtml('Apple iPhone 11 (A2221)')
            ->assertSeeHtml('Samsung Galaxy A52');
    }

    public function test_filter_dropdown_tipe_hp_includes_brand_in_web_konsumen_shop_catalog(): void
    {
        TipeHp::updateOrCreate(
            ['merk' => 'Apple', 'model' => 'iPhone 11'],
            ['nama' => 'Apple iPhone 11 (A2221)', 'is_active' => true]
        );
        TipeHp::updateOrCreate(
            ['merk' => 'Samsung', 'model' => 'Galaxy A52'],
            ['nama' => null, 'is_active' => true]
        );
        TipeHp::clearResolutionCache();

        $p = Produk::create([
            'nama' => 'LCD Apple iPhone 11 Premium',
            'kategori' => 'LCD',
            'satuan' => 'PCS',
            'harga_beli' => 200000,
            'harga_jual_retail' => 350000,
            'brand_kompatibel' => 'Apple',
            'model_kompatibel' => 'iPhone 11',
            'is_active' => true,
        ]);

        $thApple = TipeHp::where('model', 'iPhone 11')->first();
        if ($thApple) {
            $p->tipeHps()->sync([$thApple->id]);
        }

        Livewire::test(ShopPage::class)
            ->assertSeeHtml('Apple iPhone 11 (A2221)');
    }

    public function test_pos_kasir_cart_renders_actual_sku_instead_of_internal_cart_key(): void
    {
        $produk = Produk::create([
            'nama' => 'Baterai Hippo iPhone 11',
            'kategori' => 'Baterai',
            'satuan' => 'PCS',
            'harga_beli' => 100000,
            'harga_jual_retail' => 175000,
            'barcode' => 'UTP-008487',
            'is_active' => true,
        ]);

        $variant = SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => '008487',
            'nama_varian' => 'Standar',
            'harga_jual' => 175000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $produk->id,
            'sku_variant_id' => $variant->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 10,
        ]);

        $this->actingAs($this->user);
        session(['cabang_id' => $this->cabang->id]);

        $internalKey = "{$produk->id}-0";

        Livewire::test(PosKasir::class)
            ->set('selectedGudangId', $this->gudang->id)
            ->call('addToCart', $produk->id)
            ->assertSeeHtml('008487')
            ->assertDontSeeHtml(">{$internalKey}<");
    }

    public function test_pos_kasir_toggle_method_and_settings_toggle_without_500(): void
    {
        Livewire::actingAs($this->user)
            ->test(PosKasir::class)
            ->call('toggle', 'showCustomFilterSettings')
            ->assertSet('showCustomFilterSettings', true)
            ->call('toggle', 'showCustomFilterSettings')
            ->assertSet('showCustomFilterSettings', false)
            ->call('toggleFilter', 'kategori')
            ->assertSet('visibleFilters.kategori', true)
            ->assertOk();
    }

    public function test_pos_kasir_and_shop_renders_full_category_tree(): void
    {
        $parentKat = KategoriProduk::create([
            'nama' => 'Komponen Sparepart Mesin',
            'slug' => 'komponen-sparepart-mesin',
            'parent_id' => null,
            'urutan' => 1,
            'is_active' => true,
        ]);

        $subKat = KategoriProduk::create([
            'nama' => 'Konektor Fleksibel Antena',
            'slug' => 'konektor-fleksibel-antena',
            'parent_id' => $parentKat->id,
            'urutan' => 1,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(PosKasir::class)
            ->assertSeeHtml('Komponen Sparepart Mesin')
            ->assertSeeHtml('Konektor Fleksibel Antena');

        Livewire::test(ShopPage::class)
            ->assertSeeHtml('Komponen Sparepart Mesin')
            ->assertSeeHtml('Konektor Fleksibel Antena');
    }

    public function test_smart_search_category_filter_matches_both_id_and_unlinked_name(): void
    {
        $parentKat = KategoriProduk::create([
            'nama' => 'Baterai Master',
            'slug' => 'baterai-master',
            'parent_id' => null,
            'urutan' => 1,
            'is_active' => true,
        ]);

        $p1 = Produk::create([
            'nama' => 'Baterai Model X',
            'kategori' => 'Baterai Master',
            'kategori_id' => $parentKat->id,
            'harga_jual_retail' => 50000,
            'is_active' => true,
        ]);

        $p2 = Produk::withoutEvents(function () {
            return Produk::create([
                'nama' => 'Baterai Model Y Unlinked',
                'slug' => 'baterai-model-y-unlinked',
                'kategori' => 'Baterai Master',
                'kategori_id' => null,
                'harga_jual_retail' => 60000,
                'is_active' => true,
            ]);
        });

        $query = Produk::query()->filterKatalog(['kategori_id' => $parentKat->id]);
        $results = $query->pluck('id')->all();

        $this->assertContains($p1->id, $results);
        $this->assertContains($p2->id, $results);
    }

    public function test_tipe_hp_filter_sorted_alphabetically_with_brand_as_primary_reference(): void
    {
        TipeHp::firstOrCreate(['merk' => 'Xiaomi', 'model' => 'Redmi Note 10 Pro'], ['nama' => 'Redmi Note 10 Pro', 'is_active' => true]);
        TipeHp::firstOrCreate(['merk' => 'Apple', 'model' => 'iPhone 13'], ['nama' => 'iPhone 13', 'is_active' => true]);
        TipeHp::firstOrCreate(['merk' => 'Apple', 'model' => 'iPhone 11'], ['nama' => 'iPhone 11', 'is_active' => true]);
        TipeHp::firstOrCreate(['merk' => 'Samsung', 'model' => 'Galaxy A52'], ['nama' => 'Galaxy A52', 'is_active' => true]);
        TipeHp::firstOrCreate(['merk' => 'Samsung', 'model' => 'Galaxy A11'], ['nama' => 'Galaxy A11', 'is_active' => true]);

        $rawModels = ['Redmi Note 10 Pro', 'iPhone 13', 'Galaxy A52', 'iPhone 11', 'Galaxy A11'];
        $sorted = TipeHp::sortModelsByBrandAndAlphabet($rawModels);

        $this->assertSame([
            'iPhone 11',
            'iPhone 13',
            'Galaxy A11',
            'Galaxy A52',
            'Redmi Note 10 Pro',
        ], array_values($sorted));

        $grouped = TipeHp::groupModelsByBrand($rawModels);
        $this->assertSame(['Apple', 'Samsung', 'Xiaomi'], array_keys($grouped));
        $this->assertSame(['iPhone 11', 'iPhone 13'], array_values($grouped['Apple']));
        $this->assertSame(['Galaxy A11', 'Galaxy A52'], array_values($grouped['Samsung']));
        $this->assertSame(['Redmi Note 10 Pro'], array_values($grouped['Xiaomi']));
    }

    public function test_shop_and_pos_render_optgroup_for_brand_referenced_models(): void
    {
        $thApple = TipeHp::firstOrCreate(['merk' => 'Apple', 'model' => 'iPhone 11'], ['is_active' => true]);
        $thSamsung = TipeHp::firstOrCreate(['merk' => 'Samsung', 'model' => 'Galaxy A52'], ['is_active' => true]);

        Produk::create([
            'nama' => 'LCD iPhone 11',
            'brand_kompatibel' => 'Apple',
            'model_kompatibel' => 'iPhone 11',
            'harga_jual_retail' => 100000,
            'is_active' => true,
        ]);

        Produk::create([
            'nama' => 'Baterai Galaxy A52',
            'brand_kompatibel' => 'Samsung',
            'model_kompatibel' => 'Galaxy A52',
            'harga_jual_retail' => 80000,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(PosKasir::class)
            ->call('toggleFilter', 'model_kompatibel')
            ->assertSeeHtml('optgroup label="Apple"')
            ->assertSeeHtml('optgroup label="Samsung"');

        Livewire::test(ShopPage::class)
            ->assertSeeHtml('optgroup label="Apple"')
            ->assertSeeHtml('optgroup label="Samsung"');
    }
}
