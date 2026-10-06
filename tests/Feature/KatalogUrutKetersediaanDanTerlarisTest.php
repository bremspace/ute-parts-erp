<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Marketplace\Livewire\ShopPage;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KatalogUrutKetersediaanDanTerlarisTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CBG-TEST-01',
            'nama' => 'Cabang Test Urutan',
            'alamat' => 'Jl. Test No. 1',
            'telepon' => '08123456789',
            'is_pusat' => true,
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'GDG-TEST-01',
            'nama' => 'Gudang Kasir',
            'is_active' => true,
        ]);

        $this->kasir = User::factory()->create();
        $this->kasir->assignRole('kasir');
        $this->kasir->cabangs()->attach($this->cabang->id, ['is_default' => true]);
    }

    public function test_scope_urut_ketersediaan_dan_terlaris(): void
    {
        // Buat 4 produk:
        // P1: Stok 10, Terjual 100 (Harus #1 - ready & paling laris)
        // P2: Stok 50, Terjual 20  (Harus #2 - ready & laris kedua)
        // P3: Stok 5,  Terjual 0   (Harus #3 - ready tapi belum terjual)
        // P4: Stok 0,  Terjual 500 (Harus #4 - stok habis, jatuh ke bawah meskipun pernah paling laris)
        $p1 = Produk::create(['nama' => 'Produk A Ready Top Seller', 'slug' => 'p-a', 'is_active' => true, 'harga_beli' => 1000, 'harga_jual_retail' => 2000]);
        $p2 = Produk::create(['nama' => 'Produk B Ready Medium Seller', 'slug' => 'p-b', 'is_active' => true, 'harga_beli' => 1000, 'harga_jual_retail' => 2000]);
        $p3 = Produk::create(['nama' => 'Produk C Ready Unsold', 'slug' => 'p-c', 'is_active' => true, 'harga_beli' => 1000, 'harga_jual_retail' => 2000]);
        $p4 = Produk::create(['nama' => 'Produk D Empty Stock High Seller', 'slug' => 'p-d', 'is_active' => true, 'harga_beli' => 1000, 'harga_jual_retail' => 2000]);

        $sv1 = SkuVariant::create(['produk_id' => $p1->id, 'nama_varian' => 'Standar', 'sku' => 'SKU-P1', 'is_active' => true]);
        $sv2 = SkuVariant::create(['produk_id' => $p2->id, 'nama_varian' => 'Standar', 'sku' => 'SKU-P2', 'is_active' => true]);
        $sv3 = SkuVariant::create(['produk_id' => $p3->id, 'nama_varian' => 'Standar', 'sku' => 'SKU-P3', 'is_active' => true]);
        $sv4 = SkuVariant::create(['produk_id' => $p4->id, 'nama_varian' => 'Standar', 'sku' => 'SKU-P4', 'is_active' => true]);

        // Atur Stok
        StokItem::create(['produk_id' => $p1->id, 'sku_variant_id' => $sv1->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 10]);
        StokItem::create(['produk_id' => $p2->id, 'sku_variant_id' => $sv2->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 50]);
        StokItem::create(['produk_id' => $p3->id, 'sku_variant_id' => $sv3->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 5]);
        StokItem::create(['produk_id' => $p4->id, 'sku_variant_id' => $sv4->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 0]);

        // Atur Riwayat Penjualan
        $trx = Transaksi::create([
            'no_transaksi' => 'TRX-TEST-001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->kasir->id,
            'total_akhir' => 100000,
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 100000,
            'kembalian' => 0,
        ]);

        TransaksiItem::create(['transaksi_id' => $trx->id, 'produk_id' => $p1->id, 'sku_variant_id' => $sv1->id, 'jumlah' => 100, 'harga_satuan' => 2000, 'subtotal' => 200000, 'hpp' => 1000]);
        TransaksiItem::create(['transaksi_id' => $trx->id, 'produk_id' => $p2->id, 'sku_variant_id' => $sv2->id, 'jumlah' => 20, 'harga_satuan' => 2000, 'subtotal' => 40000, 'hpp' => 1000]);
        TransaksiItem::create(['transaksi_id' => $trx->id, 'produk_id' => $p4->id, 'sku_variant_id' => $sv4->id, 'jumlah' => 500, 'harga_satuan' => 2000, 'subtotal' => 1000000, 'hpp' => 1000]);

        // Eksekusi query dengan scope
        $ordered = Produk::query()
            ->urutKetersediaanDanTerlaris($this->gudang->id, $this->cabang->id)
            ->get();

        $orderedIds = $ordered->pluck('id')->all();

        // Verifikasi urutan: P1 (#1), P2 (#2), P3 (#3), P4 (#4)
        $this->assertEquals([$p1->id, $p2->id, $p3->id, $p4->id], $orderedIds);
    }

    public function test_katalog_pos_kasir_mengurutkan_ketersediaan_dan_terlaris(): void
    {
        $this->actingAs($this->kasir);
        session(['cabang_id' => $this->cabang->id]);

        $pReadyFast = Produk::create(['nama' => 'LCD Fast Moving Ready', 'slug' => 'lcd-fast', 'is_active' => true, 'harga_beli' => 50000, 'harga_jual_retail' => 100000]);
        $pReadySlow = Produk::create(['nama' => 'Baterai Slow Moving Ready', 'slug' => 'bat-slow', 'is_active' => true, 'harga_beli' => 30000, 'harga_jual_retail' => 60000]);
        $pEmptyFast = Produk::create(['nama' => 'Kamera Habis Populer', 'slug' => 'cam-empty', 'is_active' => true, 'harga_beli' => 80000, 'harga_jual_retail' => 150000]);

        $sv1 = SkuVariant::create(['produk_id' => $pReadyFast->id, 'nama_varian' => 'Standar', 'sku' => 'SKU-RF', 'is_active' => true]);
        $sv2 = SkuVariant::create(['produk_id' => $pReadySlow->id, 'nama_varian' => 'Standar', 'sku' => 'SKU-RS', 'is_active' => true]);
        $sv3 = SkuVariant::create(['produk_id' => $pEmptyFast->id, 'nama_varian' => 'Standar', 'sku' => 'SKU-EF', 'is_active' => true]);

        StokItem::create(['produk_id' => $pReadyFast->id, 'sku_variant_id' => $sv1->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 15]);
        StokItem::create(['produk_id' => $pReadySlow->id, 'sku_variant_id' => $sv2->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 20]);
        StokItem::create(['produk_id' => $pEmptyFast->id, 'sku_variant_id' => $sv3->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 0]);

        $trx = Transaksi::create([
            'no_transaksi' => 'TRX-POS-001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->kasir->id,
            'total_akhir' => 500000,
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 500000,
            'kembalian' => 0,
        ]);

        TransaksiItem::create(['transaksi_id' => $trx->id, 'produk_id' => $pReadyFast->id, 'sku_variant_id' => $sv1->id, 'jumlah' => 45, 'harga_satuan' => 100000, 'subtotal' => 4500000, 'hpp' => 50000]);
        TransaksiItem::create(['transaksi_id' => $trx->id, 'produk_id' => $pReadySlow->id, 'sku_variant_id' => $sv2->id, 'jumlah' => 2, 'harga_satuan' => 60000, 'subtotal' => 120000, 'hpp' => 30000]);
        TransaksiItem::create(['transaksi_id' => $trx->id, 'produk_id' => $pEmptyFast->id, 'sku_variant_id' => $sv3->id, 'jumlah' => 99, 'harga_satuan' => 150000, 'subtotal' => 14850000, 'hpp' => 80000]);

        $component = Livewire::test(PosKasir::class)
            ->set('selectedGudangId', $this->gudang->id);

        $posProducts = collect($component->viewData('products'))->pluck('id')->all();

        // $pReadyFast (ready, terjual 45) harus #1
        // $pReadySlow (ready, terjual 2) harus #2
        // $pEmptyFast (habis, terjual 99) harus paling akhir
        $this->assertEquals([$pReadyFast->id, $pReadySlow->id, $pEmptyFast->id], $posProducts);
    }

    public function test_katalog_toko_online_mengurutkan_ketersediaan_dan_terlaris(): void
    {
        $pReadyPopular = Produk::create(['nama' => 'LCD Online Ready Terlaris', 'slug' => 'lcd-online-top', 'is_active' => true, 'harga_beli' => 50000, 'harga_jual_retail' => 100000]);
        $pReadyReguler = Produk::create(['nama' => 'Baterai Online Ready Reguler', 'slug' => 'bat-online-reg', 'is_active' => true, 'harga_beli' => 30000, 'harga_jual_retail' => 60000]);
        $pEmptyOldStar = Produk::create(['nama' => 'Flexible Online Habis', 'slug' => 'flex-online-empty', 'is_active' => true, 'harga_beli' => 10000, 'harga_jual_retail' => 25000]);

        $sv1 = SkuVariant::create(['produk_id' => $pReadyPopular->id, 'nama_varian' => 'Standar', 'sku' => 'SKU-OP', 'is_active' => true]);
        $sv2 = SkuVariant::create(['produk_id' => $pReadyReguler->id, 'nama_varian' => 'Standar', 'sku' => 'SKU-OR', 'is_active' => true]);
        $sv3 = SkuVariant::create(['produk_id' => $pEmptyOldStar->id, 'nama_varian' => 'Standar', 'sku' => 'SKU-OE', 'is_active' => true]);

        StokItem::create(['produk_id' => $pReadyPopular->id, 'sku_variant_id' => $sv1->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 8]);
        StokItem::create(['produk_id' => $pReadyReguler->id, 'sku_variant_id' => $sv2->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 12]);
        StokItem::create(['produk_id' => $pEmptyOldStar->id, 'sku_variant_id' => $sv3->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 0]);

        $trx = Transaksi::create([
            'no_transaksi' => 'MP-ONLINE-001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => null,
            'total_akhir' => 100000,
            'metode_bayar' => 'transfer',
            'jumlah_bayar' => 100000,
            'kembalian' => 0,
        ]);

        TransaksiItem::create(['transaksi_id' => $trx->id, 'produk_id' => $pReadyPopular->id, 'sku_variant_id' => $sv1->id, 'jumlah' => 30, 'harga_satuan' => 100000, 'subtotal' => 3000000, 'hpp' => 50000]);
        TransaksiItem::create(['transaksi_id' => $trx->id, 'produk_id' => $pReadyReguler->id, 'sku_variant_id' => $sv2->id, 'jumlah' => 1, 'harga_satuan' => 60000, 'subtotal' => 60000, 'hpp' => 30000]);
        TransaksiItem::create(['transaksi_id' => $trx->id, 'produk_id' => $pEmptyOldStar->id, 'sku_variant_id' => $sv3->id, 'jumlah' => 100, 'harga_satuan' => 25000, 'subtotal' => 2500000, 'hpp' => 10000]);

        $shop = new ShopPage;
        $shopProducts = $shop->getProductsProperty();
        $shopProductIds = collect($shopProducts->items())->pluck('id')->all();

        $this->assertEquals([$pReadyPopular->id, $pReadyReguler->id, $pEmptyOldStar->id], $shopProductIds);
    }
}
