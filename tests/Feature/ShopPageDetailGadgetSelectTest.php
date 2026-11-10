<?php

namespace Tests\Feature;

use App\Modules\Marketplace\Livewire\ShopPage;
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

class ShopPageDetailGadgetSelectTest extends TestCase
{
    use RefreshDatabase;

    public function test_klik_model_gadget_terverifikasi_memilih_varian_dan_masuk_catatan_cart(): void
    {
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

        $produk = Produk::create([
            'kode' => 'PRD-LCD-01',
            'nama' => 'LCD Touchscreen OLED',
            'slug' => 'lcd-touchscreen-oled',
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'harga_beli' => 200000,
            'harga_jual_retail' => 450000,
            'is_active' => true,
        ]);

        $variant = SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'LCD-REDMI-NOTE10',
            'nama_varian' => 'Xiaomi Redmi Note 10',
            'harga_tambahan' => 0,
        ]);

        $tipeHp = TipeHp::create([
            'merk' => 'Xiaomi',
            'model' => 'Redmi Note 10',
        ]);
        $produk->tipeHps()->attach($tipeHp->id);

        StokItem::create([
            'cabang_id' => $cabang->id,
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'sku_variant_id' => $variant->id,
            'jumlah' => 10,
            'kondisi' => 'baik',
        ]);

        // Test Livewire component
        Livewire::test(ShopPage::class, ['slug' => $produk->slug])
            ->assertSee('Xiaomi Redmi Note 10')
            ->call('selectGadgetModel', 'Xiaomi Redmi Note 10')
            ->assertSet('selectedGadgetModel', 'Xiaomi Redmi Note 10')
            ->assertSet('selectedVariantId', $variant->id)
            ->call('addToCart');

        $cart = app(CartService::class)->all();
        $this->assertNotEmpty($cart);

        $cartItem = collect($cart)->first();
        $this->assertEquals('Model: Xiaomi Redmi Note 10', $cartItem['catatan']);
        $this->assertEquals($variant->id, $cartItem['variant_id']);
    }

    public function test_deep_linking_tipe_via_query_string_otomatis_memilih_model_gadget(): void
    {
        $produk = Produk::create([
            'kode' => 'PRD-BAT-01',
            'nama' => 'Baterai Original',
            'slug' => 'baterai-original',
            'kategori' => 'Baterai',
            'kondisi' => 'baru',
            'harga_beli' => 100000,
            'harga_jual_retail' => 200000,
            'is_active' => true,
        ]);

        $variant = SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'BAT-IP11',
            'nama_varian' => 'iPhone 11',
            'harga_tambahan' => 0,
        ]);

        $tipeHp = TipeHp::create([
            'merk' => 'Apple',
            'model' => 'iPhone 11',
        ]);
        $produk->tipeHps()->attach($tipeHp->id);

        Livewire::withQueryParams(['tipe' => 'Apple iPhone 11'])
            ->test(ShopPage::class, ['slug' => $produk->slug])
            ->assertSet('selectedGadgetModel', 'Apple iPhone 11')
            ->assertSet('selectedVariantId', $variant->id);
    }

    public function test_permalink_route_shop_tipe_dan_kategori_model_berhasil_diakses(): void
    {
        $kategori = KategoriProduk::firstOrCreate(
            ['slug' => 'lcd-touchscreen'],
            ['nama' => 'LCD & Touchscreen', 'is_active' => true]
        );

        $tipeHp = TipeHp::firstOrCreate(
            ['model' => 'Galaxy A54'],
            ['merk' => 'Samsung', 'is_active' => true]
        );

        $produk = Produk::create([
            'nama' => 'LCD Samsung Galaxy A54 Original',
            'slug' => 'lcd-samsung-galaxy-a54-ori',
            'kategori_id' => $kategori->id,
            'kategori' => 'LCD & Touchscreen',
            'harga_beli' => 150000,
            'harga_jual_retail' => 250000,
            'is_active' => true,
        ]);
        $produk->tipeHps()->attach($tipeHp->id);

        // 1. Uji route permalink /shop/tipe/{hpModel}
        $responseTipe = $this->get(route('shop.tipe', ['hpModel' => 'galaxy-a54']));
        $responseTipe->assertOk();
        $responseTipe->assertSee('Galaxy A54');

        // 2. Uji route permalink /shop/kategori/{kategori}/{hpModel}
        $responseKatModel = $this->get(route('shop.kategori.model', [
            'kategori' => 'lcd-touchscreen',
            'hpModel' => 'galaxy-a54',
        ]));
        $responseKatModel->assertOk();
        $responseKatModel->assertSee('LCD &amp; Touchscreen', false);
        $responseKatModel->assertSee('Galaxy A54');
    }

    public function test_katalog_add_to_cart_berhasil_bila_ada_stok(): void
    {
        $cabang = Cabang::create(['kode' => 'CB-TEST-2', 'nama' => 'Cabang Test 2', 'is_active' => true]);
        $gudang = Gudang::create(['kode' => 'GD-TEST-2', 'nama' => 'Gudang Test 2', 'cabang_id' => $cabang->id, 'is_active' => true]);

        $produk = Produk::create([
            'kode' => 'PRD-TEST-CART',
            'nama' => 'Kabel Data Fast Charging',
            'slug' => 'kabel-data-fast-charging',
            'kategori' => 'Aksesoris',
            'harga_beli' => 15000,
            'harga_jual_retail' => 35000,
            'is_active' => true,
        ]);

        $variant = SkuVariant::create([
            'produk_id' => $produk->id,
            'sku' => 'KBL-FC-01',
            'nama_varian' => 'Black 1m',
            'harga_tambahan' => 0,
        ]);

        StokItem::create([
            'cabang_id' => $cabang->id,
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'sku_variant_id' => $variant->id,
            'jumlah' => 5,
            'kondisi' => 'baik',
        ]);

        Livewire::test(ShopPage::class)
            ->call('addToCart', $produk->id)
            ->assertDispatched('alert', function ($event, $params) use ($produk) {
                $payload = $params[0] ?? $params;

                return ($payload['type'] ?? '') === 'success'
                    && str_contains($payload['message'] ?? '', $produk->nama);
            });

        $cart = app(CartService::class)->all();
        $this->assertNotEmpty($cart);
    }

    public function test_add_to_cart_gagal_bila_stok_kosong(): void
    {
        $produk = Produk::create([
            'kode' => 'PRD-HABIS-01',
            'nama' => 'Charger Super Langka',
            'slug' => 'charger-super-langka',
            'kategori' => 'Aksesoris',
            'harga_beli' => 50000,
            'harga_jual_retail' => 100000,
            'is_active' => true,
        ]);

        Livewire::test(ShopPage::class)
            ->call('addToCart', $produk->id)
            ->assertDispatched('alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['type'] ?? '') === 'error'
                    && str_contains($payload['message'] ?? '', 'habis stok');
            });
    }
}
