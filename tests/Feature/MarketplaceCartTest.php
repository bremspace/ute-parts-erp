<?php

namespace Tests\Feature;

use App\Modules\Marketplace\Services\CartService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceCartTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CBG-'.Str::random(4),
            'nama' => 'Cabang Test',
            'alamat' => 'Jl. Test',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'GDG-'.Str::random(4),
            'nama' => 'Gudang Utama',
            'is_active' => true,
        ]);
    }

    private function buatProduk(string $nama, float $harga, int $stok = 10): Produk
    {
        $p = Produk::create([
            'kode' => 'PRD-'.Str::random(6),
            'nama' => $nama,
            'slug' => Str::slug($nama).'-'.Str::random(4),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'harga_beli' => $harga * 0.7,
            'harga_jual_retail' => $harga,
            'is_active' => true,
        ]);

        StokItem::create([
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'produk_id' => $p->id,
            'jumlah' => $stok,
            'kondisi' => 'baik',
        ]);

        return $p;
    }

    public function test_memasukkan_produk_berbeda_tidak_menduplikasi_qty_dan_tidak_menghilangkan_item(): void
    {
        $cart = app(CartService::class);
        $cart->kosongkan();

        $p1 = $this->buatProduk('LCD iPhone 13', 1000000, 10);
        $p2 = $this->buatProduk('Baterai Samsung A52', 200000, 10);

        // Masukkan produk 1 sebanyak 1 pcs
        $cart->isi($p1->id, null, 1);
        $items = $cart->all();

        $this->assertCount(1, $items);
        $this->assertArrayHasKey('p'.$p1->id, $items);
        $this->assertEquals(1, $items['p'.$p1->id]['qty']);
        $this->assertEquals($p1->id, $items['p'.$p1->id]['product_id']);

        // Masukkan produk 2 sebanyak 2 pcs
        $cart->isi($p2->id, null, 2);
        $items = $cart->all();

        // Keduanya harus tetap ada dan tidak tertimpa
        $this->assertCount(2, $items);
        $this->assertArrayHasKey('p'.$p1->id, $items);
        $this->assertArrayHasKey('p'.$p2->id, $items);

        // Qty masing-masing harus sesuai (bukan menduplikasi/menumpuk ke produk 2)
        $this->assertEquals(1, $items['p'.$p1->id]['qty']);
        $this->assertEquals(2, $items['p'.$p2->id]['qty']);
        $this->assertEquals(3, $cart->jumlahItem());
    }

    public function test_update_qty_dan_hapus_item_berdasarkan_cart_key(): void
    {
        $cart = app(CartService::class);
        $cart->kosongkan();

        $p1 = $this->buatProduk('LCD iPhone 13', 1000000, 10);
        $p2 = $this->buatProduk('Baterai Samsung A52', 200000, 10);

        $cart->isi($p1->id, null, 1);
        $cart->isi($p2->id, null, 2);

        // Update qty p1 menjadi 4
        $cart->updateQty('p'.$p1->id, 4);
        $this->assertEquals(4, $cart->all()['p'.$p1->id]['qty']);

        // Hapus p1
        $cart->hapus('p'.$p1->id);
        $items = $cart->all();

        $this->assertCount(1, $items);
        $this->assertArrayNotHasKey('p'.$p1->id, $items);
        $this->assertArrayHasKey('p'.$p2->id, $items);
        $this->assertEquals(2, $items['p'.$p2->id]['qty']);
    }

    public function test_produk_dengan_varian_memiliki_key_unik(): void
    {
        $cart = app(CartService::class);
        $cart->kosongkan();

        $p = $this->buatProduk('Kabel Data Type-C', 50000, 20);
        $v1 = SkuVariant::create([
            'produk_id' => $p->id,
            'sku' => 'KBL-1M',
            'nama_varian' => '1 Meter',
            'harga_tambahan' => 0,
        ]);
        $v2 = SkuVariant::create([
            'produk_id' => $p->id,
            'sku' => 'KBL-2M',
            'nama_varian' => '2 Meter',
            'harga_tambahan' => 10000,
        ]);

        StokItem::create([
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'produk_id' => $p->id,
            'sku_variant_id' => $v1->id,
            'jumlah' => 10,
            'kondisi' => 'baik',
        ]);
        StokItem::create([
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'produk_id' => $p->id,
            'sku_variant_id' => $v2->id,
            'jumlah' => 10,
            'kondisi' => 'baik',
        ]);

        $cart->isi($p->id, $v1->id, 1);
        $cart->isi($p->id, $v2->id, 3);

        $items = $cart->all();
        $this->assertCount(2, $items);
        $this->assertArrayHasKey('p'.$p->id.'_v'.$v1->id, $items);
        $this->assertArrayHasKey('p'.$p->id.'_v'.$v2->id, $items);
        $this->assertEquals(1, $items['p'.$p->id.'_v'.$v1->id]['qty']);
        $this->assertEquals(3, $items['p'.$p->id.'_v'.$v2->id]['qty']);
    }
}
