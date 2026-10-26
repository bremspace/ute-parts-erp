<?php

namespace Tests\Feature;

use App\Modules\Marketplace\Livewire\ShopPage;
use App\Modules\Marketplace\Services\CartService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
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
}
