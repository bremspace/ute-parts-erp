<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\GrnService;
use App\Modules\Wms\Services\ProcurementService;
use App\Modules\Wms\Services\ProdukService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterProdukDanPoOptimasiTest extends TestCase
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

        $this->cabang = Cabang::create(['nama' => 'Cabang Test', 'kode' => 'CB-01', 'is_active' => true]);
        $this->gudang = Gudang::create(['nama' => 'Gudang Test', 'kode' => 'GD-01', 'cabang_id' => $this->cabang->id, 'is_active' => true]);
        $this->supplier = Supplier::create(['nama' => 'Supplier Prima', 'telepon' => '08129999111', 'termin_hari' => 30]);

        $this->user = User::create([
            'name' => 'Admin WMS',
            'email' => 'adminwms@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_buat_produk_dengan_margin_persen_dan_metode_harga_beli(): void
    {
        $service = app(ProdukService::class);

        $produk = $service->buatProduk(
            nama: 'Baterai iPhone 11',
            kategori: 'Sparepart',
            brand: 'Apple',
            model: 'iPhone 11',
            kondisi: 'baru',
            hargaBeli: 100000,
            hargaJual: 125000,
            satuanKode: 'pcs',
            metodeHargaBeli: 'average',
            marginPersen: 25.0
        );

        $this->assertDatabaseHas('produk', [
            'id' => $produk->id,
            'nama' => 'Baterai iPhone 11',
            'metode_harga_beli' => 'average',
            'margin_persen' => 25.00,
            'harga_beli' => 100000.00,
            'harga_jual_retail' => 125000.00,
        ]);
    }

    public function test_penyesuaian_otomatis_moving_average_cost_saat_pembelian(): void
    {
        $service = app(ProdukService::class);

        // Produk awal: harga beli 100.000, margin 20% -> harga jual 120.000, stok 10 unit
        $produk = $service->buatProduk(
            nama: 'LCD Samsung A52',
            kategori: 'LCD',
            brand: 'Samsung',
            model: 'A52',
            kondisi: 'baru',
            hargaBeli: 100000,
            hargaJual: 120000,
            gudangId: $this->gudang->id,
            stokAwal: 10,
            satuanKode: 'pcs',
            metodeHargaBeli: 'average',
            marginPersen: 20.0
        );

        // Pembelian baru: 10 unit dengan harga 150.000
        // Moving Average = ((10 * 100.000) + (10 * 150.000)) / (10 + 10) = 2.500.000 / 20 = 125.000
        // Harga jual dengan margin 20% = 125.000 * 1.20 = 150.000
        $service->tambahStokPembelian(
            $produk->id,
            null,
            $this->gudang->id,
            10,
            150000,
            'Pembelian restock PO-001',
            $this->user->id,
            postJurnal: false
        );

        $service->sesuaikanHargaBeliAverage($produk->id, 10, 150000);

        $produkFresh = $produk->fresh();
        $this->assertEquals(125000.00, (float) $produkFresh->harga_beli);
        $this->assertEquals(150000.00, (float) $produkFresh->harga_jual_retail);
    }

    public function test_tracking_histori_pembelian_supplier_dan_statistik_weighted_average(): void
    {
        $produk = Produk::create([
            'nama' => 'Flexible On Off Xiaomi',
            'harga_beli' => 20000,
            'harga_jual_retail' => 35000,
            'is_active' => true,
        ]);

        // PO 1: 5 unit @ 20.000 (total 100.000)
        $po1 = PurchaseOrder::create([
            'no_po' => 'PO-HISTORI-001',
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'diterima',
            'metode_bayar' => 'tunai',
            'total' => 100000,
            'total_dibayar' => 100000,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po1->id,
            'produk_id' => $produk->id,
            'harga_beli' => 20000,
            'jumlah' => 5,
            'subtotal' => 100000,
        ]);

        // PO 2: 15 unit @ 30.000 (total 450.000)
        $po2 = PurchaseOrder::create([
            'no_po' => 'PO-HISTORI-002',
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'status' => 'diterima',
            'metode_bayar' => 'kredit',
            'total' => 450000,
            'total_dibayar' => 0,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po2->id,
            'produk_id' => $produk->id,
            'harga_beli' => 30000,
            'jumlah' => 15,
            'subtotal' => 450000,
        ]);

        $procurement = app(ProcurementService::class);
        $histori = $procurement->getHistoriPembelian($produk->id);

        $this->assertCount(2, $histori['items']);
        $this->assertEquals(20, $histori['statistik']['total_qty']); // 5 + 15
        $this->assertEquals(30000, $histori['statistik']['harga_terakhir']);
        $this->assertEquals(20000, $histori['statistik']['harga_terendah']);
        $this->assertEquals(30000, $histori['statistik']['harga_tertinggi']);
        // Weighted Average = (100.000 + 450.000) / 20 = 550.000 / 20 = 27.500
        $this->assertEquals(27500, $histori['statistik']['harga_rata_rata']);
    }

    public function test_analisis_abc_pareto_dan_rekomendasi_rop(): void
    {
        $produkFast = Produk::create([
            'nama' => 'LCD Fast Moving',
            'harga_beli' => 200000,
            'harga_jual_retail' => 300000,
            'abc_class' => 'B',
            'is_active' => true,
        ]);

        $produkSlow = Produk::create([
            'nama' => 'Kabel Fleksibel Slow Moving',
            'harga_beli' => 10000,
            'harga_jual_retail' => 15000,
            'abc_class' => 'B',
            'is_active' => true,
        ]);

        // Simulasikan mutasi keluar
        // Produk Fast: keluar 90 unit dalam 90 hari (omzet: 90 * 300.000 = 27.000.000)
        StockMutationLog::create([
            'produk_id' => $produkFast->id,
            'gudang_id' => $this->gudang->id,
            'delta' => -90,
            'sumber' => 'pos',
            'terjadi_at' => now()->subDays(10),
        ]);

        // Produk Slow: keluar 1 unit (omzet: 1 * 15.000 = 15.000)
        StockMutationLog::create([
            'produk_id' => $produkSlow->id,
            'gudang_id' => $this->gudang->id,
            'delta' => -1,
            'sumber' => 'pos',
            'terjadi_at' => now()->subDays(20),
        ]);

        $procurement = app(ProcurementService::class);
        $analisis = $procurement->hitungAnalisisAbc($this->cabang->id, 90);

        $fastItem = collect($analisis['items'])->firstWhere('produk_id', $produkFast->id);
        $slowItem = collect($analisis['items'])->firstWhere('produk_id', $produkSlow->id);

        $this->assertEquals('A', $fastItem['rekomendasi_abc']);
        $this->assertGreaterThan(0, $fastItem['rekomendasi_rop']);

        $this->assertEquals('C', $slowItem['rekomendasi_abc']);

        // Terapkan ke database
        $count = $procurement->terapkanAnalisisAbc([$produkFast->id, $produkSlow->id], $this->cabang->id, 90);
        $this->assertEquals(2, $count);

        $this->assertEquals('A', $produkFast->fresh()->abc_class);
        $this->assertEquals('C', $produkSlow->fresh()->abc_class);
    }

    public function test_grn_input_harga_deteksi_perubahan_dan_jurnal_stok_awal_modal(): void
    {
        $service = app(ProdukService::class);

        // 1. Uji: Produk baru dibuat dengan stok awal -> otomatis tercatat sebagai Modal Pemilik di akuntansi
        $produk = $service->buatProduk(
            nama: 'Kabel Data Type-C Fast',
            kategori: 'Aksesoris',
            brand: 'Baseus',
            model: 'Type-C',
            kondisi: 'baru',
            hargaBeli: 20000,
            hargaJual: 35000,
            gudangId: $this->gudang->id,
            stokAwal: 50,
            satuanKode: 'pcs',
            userId: $this->user->id
        );

        // Verifikasi default metode_harga_beli adalah 'average'
        $this->assertEquals('average', $produk->metode_harga_beli);

        // Verifikasi jurnal modal usaha tercatat di jurnal_akuntansi (130-01 Persediaan Debit, 310-01 Modal Ekuitas Kredit)
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'sumber' => 'stok_awal',
            'referensi_tipe' => Produk::class,
            'referensi_id' => $produk->id,
            'cabang_id' => $this->cabang->id,
            'akun_coa_id' => AkunCOA::where('kode', '130-01')->value('id'),
            'debit' => 1000000.00, // 50 * 20.000
        ]);

        $this->assertDatabaseHas('jurnal_akuntansi', [
            'sumber' => 'stok_awal',
            'referensi_tipe' => Produk::class,
            'referensi_id' => $produk->id,
            'cabang_id' => $this->cabang->id,
            'akun_coa_id' => AkunCOA::where('kode', '310-01')->value('id'),
            'kredit' => 1000000.00, // Modal Usaha
        ]);

        // 2. Uji: GRN dengan input harga baru -> otomatis deteksi perubahan harga & update Moving Average
        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-002',
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'user_id' => $this->user->id,
            'status' => 'dikirim',
            'total' => 250000,
            'total_dibayar' => 0,
            'metode_bayar' => 'kredit',
            'tanggal_pesan' => now(),
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $produk->id,
            'jumlah' => 10,
            'harga_beli' => 20000,
            'subtotal' => 200000,
        ]);

        // Supplier datang dengan harga baru naik menjadi 25.000 per unit
        $grnService = app(GrnService::class);
        $kunci = GrnService::kunciItem($produk->id, null);

        $grn = $grnService->inputGudang(
            po: $po,
            itemReceived: [$kunci => 10],
            userId: $this->user->id,
            snPerProduk: [],
            itemHargaBeli: [$kunci => 25000]
        );

        $this->assertNotNull($grn);

        // Verifikasi perubahan harga tercatat di tabel riwayat_perubahan_harga
        $this->assertDatabaseHas('riwayat_perubahan_harga', [
            'produk_id' => $produk->id,
            'cabang_id' => $this->cabang->id,
            'sumber' => 'grn',
            'harga_lama' => 20000.00,
            'harga_baru' => 25000.00,
            'selisih' => 5000.00,
            'persentase_perubahan' => 25.00,
        ]);

        // Verifikasi harga modal Moving Average otomatis terupdate:
        // Stok lama = 50 unit @ 20.000 = 1.000.000
        // Stok masuk = 10 unit @ 25.000 = 250.000
        // Total baru = 1.250.000 / 60 unit = 20.833,33
        $produkFresh = $produk->fresh();
        $this->assertEquals(20833.33, (float) $produkFresh->harga_beli);
    }
}
