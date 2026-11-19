<?php

namespace Tests\Feature;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Marketplace\Services\OrderService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ProdukSatuanKonversi;
use App\Modules\Wms\Models\StokItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * [SECURITY] Regression checkout marketplace: harga HARUS dihitung server-side.
 * Client tidak boleh bisa menentukan harga (vektor manipulasi: kirim harga=0.01).
 * Juga memastikan satuan konversi (box) dihargai dari data produk, bukan input client.
 */
class CheckoutPriceManipulationTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Pelanggan $pelanggan;

    protected Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CBG-01', 'nama' => 'Cabang Pusat', 'alamat' => 'Jl. Pusat No. 1',
            'telepon' => '08123456789', 'is_pusat' => true,
        ]);
        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang Utama', 'kode' => 'GDG-01', 'is_aktif' => true,
        ]);
        session(['cabang_id' => $this->cabang->id]);

        $kat = KategoriProduk::create(['nama' => 'Sparepart Rem', 'kode' => 'REM']);
        $this->produk = Produk::create([
            'kode' => 'PRD-01', 'nama' => 'Brake Pad Brembo', 'sku' => 'SKU-BRM-01',
            'kategori_id' => $kat->id, 'harga_beli' => 100000, 'harga_jual' => 150000,
            'harga_jual_retail' => 150000, 'satuan' => 'pcs', 'stok_minimum' => 5,
        ]);
        StokItem::create(['gudang_id' => $this->gudang->id, 'produk_id' => $this->produk->id, 'jumlah' => 100]);

        $this->pelanggan = Pelanggan::create([
            'nama' => 'Budi Otomotif', 'email' => 'budi@example.com', 'telepon' => '081299887766',
            'alamat' => 'Jl. Otomotif No. 10 Jakarta', 'tipe_konsumen' => 'retail', 'password' => bcrypt('secret123'),
        ]);
    }

    public function test_checkout_ignores_tampered_client_harga(): void
    {
        $orderService = app(OrderService::class);

        // Client nakal mengirim harga sangat rendah; server WAJIB mengabaikannya.
        $transaksi = $orderService->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 2, 'harga' => 0.01]],
            $this->cabang->id,
            'ambil_ke_toko'
        );

        $item = $transaksi->items->first();
        $this->assertEquals(150000.0, (float) $item->harga_satuan, 'Harga satuan harus harga server, bukan harga client');
        $this->assertEquals(300000.0, (float) $transaksi->subtotal, 'Subtotal harus 2 x harga server');
        $this->assertNotEquals(0.02, (float) $transaksi->subtotal);
    }

    public function test_checkout_uses_server_price_without_client_harga(): void
    {
        $orderService = app(OrderService::class);

        $transaksi = $orderService->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 3]],
            $this->cabang->id,
            'ambil_ke_toko'
        );

        $this->assertEquals(150000.0, (float) $transaksi->items->first()->harga_satuan);
        $this->assertEquals(450000.0, (float) $transaksi->subtotal);
    }

    public function test_checkout_box_satuan_uses_server_box_price_not_client_harga(): void
    {
        // 1 box = 12 pcs, harga jual per box 1.620.000 -> per pcs 135.000 (server-side).
        ProdukSatuanKonversi::create([
            'produk_id' => $this->produk->id, 'satuan_kode' => 'box', 'satuan_asal' => 'pcs',
            'pengali' => 1, 'faktor_konversi' => 12, 'harga_jual' => 1620000, 'urutan' => 1,
        ]);

        $orderService = app(OrderService::class);

        // Beli 24 pcs (=2 box) dengan satuan box; client mencoba harga murah -> diabaikan.
        $transaksi = $orderService->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 24, 'satuan' => 'box', 'harga' => 1]],
            $this->cabang->id,
            'ambil_ke_toko'
        );

        $item = $transaksi->items->first();
        // [C1] Kontrak POS-native: jumlah = qty satuan (2 box), konversi = 12,
        // harga_satuan per box (1.620.000 server-side, bukan harga client).
        $this->assertEquals(2, (int) $item->jumlah);
        $this->assertEquals('box', $item->satuan);
        $this->assertEquals(12.0, (float) $item->konversi);
        $this->assertEquals(1620000.0, (float) $item->harga_satuan, 'Harga per box harus dari data produk server (1.620.000), bukan harga client');
        $this->assertEquals(3240000.0, (float) $transaksi->subtotal, 'Subtotal 2 box x 1.620.000 (= 24 pcs x 135.000)');
    }
}
