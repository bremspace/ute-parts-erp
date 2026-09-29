<?php

namespace Tests\Unit;

use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Services\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProcurementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProcurementService $service;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProcurementService::class);

        $this->cabang = Cabang::create([
            'kode' => 'CB01',
            'nama' => 'Cabang Utama',
            'alamat' => 'Jl. Merdeka No. 1',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama',
            'kode' => 'GD01',
            'is_active' => true,
        ]);
    }

    public function test_kelas_a_rop_memicu_order_optimal_ketika_stok_di_bawah_rop(): void
    {
        $produk = Produk::create([
            'nama' => 'LCD iPhone 13 OLED (Fast Moving)',
            'kategori' => 'LCD',
            'harga_beli' => 800000,
            'harga_jual_retail' => 1200000,
            'abc_class' => 'A',
            'reorder_point' => 10,
            'min_stock' => 5,
            'max_stock' => 30,
            'is_ondemand' => false,
            'is_active' => true,
        ]);

        // Set stok = 8 (<= ROP 10)
        StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 8,
            'jumlah_minimum' => 5,
        ]);

        $recs = $this->service->getProcurementRecommendations($this->cabang->id);
        $itemRec = $recs->firstWhere('produk_id', $produk->id);

        $this->assertNotNull($itemRec);
        $this->assertEquals('ORDER', $itemRec['action']);
        $this->assertEquals('ROP (Fast Moving)', $itemRec['metode']);
        // Order optimal ke max_stock: 30 - 8 = 22
        $this->assertEquals(22, $itemRec['recommended_order']);
        $this->assertEquals(22 * 800000, $itemRec['estimasi_biaya']);
    }

    public function test_kelas_a_rop_hold_ketika_stok_di_atas_rop(): void
    {
        $produk = Produk::create([
            'nama' => 'Baterai iPhone 12 (Fast Moving)',
            'kategori' => 'Baterai',
            'harga_beli' => 200000,
            'harga_jual_retail' => 350000,
            'abc_class' => 'A',
            'reorder_point' => 10,
            'min_stock' => 5,
            'max_stock' => 25,
            'is_ondemand' => false,
            'is_active' => true,
        ]);

        // Set stok = 15 (> ROP 10)
        StokItem::create([
            'produk_id' => $produk->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 15,
            'jumlah_minimum' => 5,
        ]);

        $recs = $this->service->getProcurementRecommendations($this->cabang->id);
        $itemRec = $recs->firstWhere('produk_id', $produk->id);

        $this->assertEquals('HOLD', $itemRec['action']);
        $this->assertEquals(0, $itemRec['recommended_order']);
    }

    public function test_kelas_b_dan_c_min_max_memicu_order_ketika_stok_di_bawah_min(): void
    {
        $produkB = Produk::create([
            'nama' => 'Kamera Belakang Samsung A52',
            'kategori' => 'Kamera',
            'harga_beli' => 150000,
            'harga_jual_retail' => 250000,
            'abc_class' => 'B',
            'reorder_point' => 5,
            'min_stock' => 4,
            'max_stock' => 15,
            'is_ondemand' => false,
            'is_active' => true,
        ]);

        $produkC = Produk::create([
            'nama' => 'Flex Home Button iPad 4 (Slow Moving)',
            'kategori' => 'Flexible',
            'harga_beli' => 30000,
            'harga_jual_retail' => 70000,
            'abc_class' => 'C',
            'reorder_point' => 3,
            'min_stock' => 2,
            'max_stock' => 8,
            'is_ondemand' => false,
            'is_active' => true,
        ]);

        // B: stok 3 (<= min 4), max 15 -> order = 15 - 3 = 12
        StokItem::create([
            'produk_id' => $produkB->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 3,
            'jumlah_minimum' => 4,
        ]);

        // C: stok 5 (> min 2) -> HOLD
        StokItem::create([
            'produk_id' => $produkC->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 5,
            'jumlah_minimum' => 2,
        ]);

        $recs = $this->service->getProcurementRecommendations($this->cabang->id);

        $recB = $recs->firstWhere('produk_id', $produkB->id);
        $this->assertEquals('ORDER', $recB['action']);
        $this->assertEquals('Min-Max (Kelas B)', $recB['metode']);
        $this->assertEquals(12, $recB['recommended_order']);

        $recC = $recs->firstWhere('produk_id', $produkC->id);
        $this->assertEquals('HOLD', $recC['action']);
        $this->assertEquals(0, $recC['recommended_order']);
    }

    public function test_modified_jit_abaikan_stok_otomatis_jika_tidak_ada_order_konsumen(): void
    {
        $produkJit = Produk::create([
            'nama' => 'IC CPU Snapdragon 888 (Komponen Mahal / JIT)',
            'kategori' => 'IC & Chipset',
            'harga_beli' => 1200000,
            'harga_jual_retail' => 1800000,
            'abc_class' => 'A',
            'reorder_point' => 5,
            'min_stock' => 2,
            'max_stock' => 10,
            'is_ondemand' => true, // Flag JIT / On-Demand
            'is_active' => true,
        ]);

        // Stok 0 (meskipun <= ROP 5 dan min 2, JIT mengabaikan pengecekan otomatis)
        StokItem::create([
            'produk_id' => $produkJit->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 0,
            'jumlah_minimum' => 2,
        ]);

        $recs = $this->service->getProcurementRecommendations($this->cabang->id);
        $rec = $recs->firstWhere('produk_id', $produkJit->id);

        $this->assertEquals('HOLD', $rec['action']);
        $this->assertEquals('Modified JIT', $rec['metode']);
        $this->assertEquals(0, $rec['recommended_order']);
        $this->assertStringContainsString('Tidak ada pesanan konsumen aktif', $rec['alasan']);
    }

    public function test_modified_jit_memicu_order_saat_ada_pesanan_konsumen(): void
    {
        $produkJit = Produk::create([
            'nama' => 'Motherboard ROG Phone 5 (JIT Part)',
            'kategori' => 'Motherboard',
            'harga_beli' => 2500000,
            'harga_jual_retail' => 3200000,
            'abc_class' => 'A',
            'reorder_point' => 2,
            'min_stock' => 1,
            'max_stock' => 5,
            'is_ondemand' => true,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $produkJit->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 1,
            'jumlah_minimum' => 1,
        ]);

        // Simulasikan ada order tiket servis konsumen yang butuh 3 unit part ini
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-TEST-001',
            'cabang_id' => $this->cabang->id,
            'status' => 'disetujui',
            'tipe_antrian' => 'reguler',
            'jenis_hp' => 'ROG Phone 5',
            'keluhan' => 'Mati total',
        ]);

        DB::table('servis_sparepart')->insert([
            'tiket_servis_id' => $tiket->id,
            'produk_id' => $produkJit->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 3,
            'harga_satuan' => 3200000,
            'hpp' => 2500000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recs = $this->service->getProcurementRecommendations($this->cabang->id);
        $rec = $recs->firstWhere('produk_id', $produkJit->id);

        // Kebutuhan: demand 3 unit, stok 1 unit -> order 2 unit
        $this->assertEquals('ORDER', $rec['action']);
        $this->assertEquals('Modified JIT', $rec['metode']);
        $this->assertEquals(2, $rec['recommended_order']);
        $this->assertEquals(3, $rec['pending_demand']);
    }

    public function test_get_procurement_summary(): void
    {
        Produk::create([
            'nama' => 'Part A',
            'kategori' => 'Kategori 1',
            'harga_beli' => 100000,
            'harga_jual_retail' => 150000,
            'abc_class' => 'A',
            'reorder_point' => 10,
            'max_stock' => 20,
            'is_ondemand' => false,
            'is_active' => true,
        ]);

        $summary = $this->service->getProcurementSummary($this->cabang->id);

        $this->assertArrayHasKey('total_sku', $summary);
        $this->assertArrayHasKey('total_needs_order', $summary);
        $this->assertArrayHasKey('total_estimasi_biaya', $summary);
        $this->assertArrayHasKey('breakdown', $summary);
        $this->assertArrayHasKey('A', $summary['breakdown']);
        $this->assertArrayHasKey('B', $summary['breakdown']);
        $this->assertArrayHasKey('C', $summary['breakdown']);
        $this->assertArrayHasKey('JIT', $summary['breakdown']);
    }
}
