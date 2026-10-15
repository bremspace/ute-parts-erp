<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\GrnService;
use App\Modules\Wms\Services\ProdukService;
use App\Modules\Wms\Services\PurchaseOrderService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiSatuanDanKonversiTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Gudang $gudang;

    private Supplier $supplier;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Cabang Pusat', 'kode' => 'CB-01', 'is_active' => true]);
        $this->gudang = Gudang::create(['nama' => 'Gudang Utama', 'kode' => 'GD-01', 'cabang_id' => $this->cabang->id, 'is_active' => true]);
        $this->supplier = Supplier::create(['nama' => 'Supplier Multi', 'telepon' => '0812345678', 'termin_hari' => 30]);

        $this->user = User::create([
            'name' => 'Admin Gudang',
            'email' => 'admingudang@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_multi_satuan_master_data_dan_auto_konversi_bertingkat(): void
    {
        $produk = app(ProdukService::class)->buatProduk(
            nama: 'Baut LCD iPhone 13',
            kategori: 'Sparepart',
            brand: 'Apple',
            model: 'iPhone 13',
            kondisi: 'baru',
            hargaBeli: 500,
            hargaJual: 1000,
            satuanKode: 'pcs',
            multiSatuan: [
                [
                    'satuan_kode' => 'box',
                    'satuan_asal' => 'pcs',
                    'pengali' => 10,
                    'is_satuan_beli' => false,
                ],
                [
                    'satuan_kode' => 'karton',
                    'satuan_asal' => 'box',
                    'pengali' => 10,
                    'is_satuan_beli' => true,
                ],
            ]
        );

        // 1 box = 10 pcs
        $box = $produk->satuanKonversi()->where('satuan_kode', 'box')->first();
        $this->assertNotNull($box);
        $this->assertEquals(10, (float) $box->faktor_konversi);
        $this->assertEquals('1 box = 10 pcs', $box->keterangan_konversi);

        // 1 karton = 10 box = 100 pcs (otomatis terhitung bertingkat!)
        $karton = $produk->satuanKonversi()->where('satuan_kode', 'karton')->first();
        $this->assertNotNull($karton);
        $this->assertEquals(100, (float) $karton->faktor_konversi);
        $this->assertEquals('1 karton = 10 box = 100 pcs', $karton->keterangan_konversi);

        // Test helper konversi di model Produk
        $this->assertEquals(200, $produk->konversiKeBaseUnit(2, 'karton'));
        $this->assertEquals(30, $produk->konversiKeBaseUnit(3, 'box'));
        $this->assertEquals(5, $produk->konversiKeBaseUnit(5, 'pcs'));

        $this->assertEquals(2.5, $produk->konversiDariBaseUnit(250, 'karton'));
        $this->assertEquals(15, $produk->konversiDariBaseUnit(150, 'box'));

        // Test getDaftarSatuan
        $daftar = $produk->getDaftarSatuan();
        $this->assertCount(3, $daftar);
        $this->assertEquals('pcs', $daftar[0]['satuan_kode']);
        $this->assertTrue($daftar[0]['is_dasar']);
        $this->assertEquals('box', $daftar[1]['satuan_kode']);
        $this->assertEquals('karton', $daftar[2]['satuan_kode']);
        $this->assertEquals(100, $daftar[2]['faktor_konversi']);
    }

    public function test_po_dan_terima_barang_satuan_besar_konversi_ke_stok(): void
    {
        $produk = app(ProdukService::class)->buatProduk(
            nama: 'Kabel Flex iPhone 12',
            kategori: 'Sparepart',
            brand: 'Apple',
            model: 'iPhone 12',
            kondisi: 'baru',
            hargaBeli: 5000,
            hargaJual: 10000,
            satuanKode: 'pcs',
            multiSatuan: [
                [
                    'satuan_kode' => 'box',
                    'satuan_asal' => 'pcs',
                    'pengali' => 10,
                ],
                [
                    'satuan_kode' => 'karton',
                    'satuan_asal' => 'box',
                    'pengali' => 10, // 1 karton = 100 pcs
                ],
            ]
        );

        // Buat PO untuk 2 karton @ Rp 500.000 / karton
        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-001',
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'draft',
            'metode_bayar' => 'kredit',
            'total' => 1000000,
            'total_dibayar' => 0,
        ]);

        $item = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $produk->id,
            'satuan' => 'karton',
            'konversi' => 100,
            'harga_beli' => 500000,
            'jumlah' => 2,
            'jumlah_dasar' => 200,
            'subtotal' => 1000000,
        ]);

        $this->assertEquals('karton', $item->satuan);
        $this->assertEquals(100, (float) $item->konversi);
        $this->assertEquals(200, (float) $item->jumlah_dasar);

        // Terima barang langsung lewat PurchaseOrderService
        app(PurchaseOrderService::class)->terimaBarang($po, $this->user->id);

        // Stok di gudang harus bertambah 200 pcs (bukan 2)!
        $stok = StokItem::where('produk_id', $produk->id)->where('gudang_id', $this->gudang->id)->first();
        $this->assertNotNull($stok);
        $this->assertEquals(200, (int) $stok->jumlah);

        // Moving average harga beli dasar harus tersesuaikan: 1.000.000 / 200 = 5.000 / pcs
        $produk->refresh();
        $this->assertEquals(5000, (float) $produk->harga_beli);
    }

    public function test_grn_penerimaan_po_satuan_besar_masuk_stok_dasar_dan_jurnal(): void
    {
        $produk = app(ProdukService::class)->buatProduk(
            nama: 'Baterai Samsung S21',
            kategori: 'Sparepart',
            brand: 'Samsung',
            model: 'S21',
            kondisi: 'baru',
            hargaBeli: 20000,
            hargaJual: 35000,
            satuanKode: 'pcs',
            multiSatuan: [
                [
                    'satuan_kode' => 'dus',
                    'satuan_asal' => 'pcs',
                    'pengali' => 50,
                ],
            ]
        );

        // PO status dikirim
        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-GRN-01',
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'dikirim',
            'metode_bayar' => 'kredit',
            'total' => 1000000,
            'total_dibayar' => 0,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $produk->id,
            'satuan' => 'dus',
            'konversi' => 50,
            'harga_beli' => 1000000,
            'jumlah' => 1,
            'jumlah_dasar' => 50,
            'subtotal' => 1000000,
        ]);

        // Input GRN: terima 1 dus
        $kunci = GrnService::kunciItem($produk->id, null);
        $grn = app(GrnService::class)->inputGudang(
            $po,
            [$kunci => 1],
            $this->user->id,
            [],
            [$kunci => 1000000]
        );

        // GRN auto finalize karena lengkap
        $this->assertEquals('terima', $grn->fresh()->status);

        // Stok di gudang harus bertambah 50 pcs (1 dus * 50 konversi)
        $stok = StokItem::where('produk_id', $produk->id)->where('gudang_id', $this->gudang->id)->first();
        $this->assertNotNull($stok);
        $this->assertEquals(50, (int) $stok->jumlah);
    }
}
