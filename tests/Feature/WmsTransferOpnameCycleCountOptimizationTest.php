<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\CycleCountPage;
use App\Modules\Wms\Livewire\GrnTab;
use App\Modules\Wms\Livewire\OpnameTab;
use App\Modules\Wms\Livewire\ProductSearchModal;
use App\Modules\Wms\Livewire\TransferTab;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\CycleCountSchedule;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WmsTransferOpnameCycleCountOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    private Gudang $gudangAsal;

    private Gudang $gudangTujuan;

    private Brand $brandApple;

    private KategoriProduk $katLcd;

    private Produk $produk1;

    private Produk $produk2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create(['kode' => 'CBG-TEST', 'nama' => 'Cabang Test', 'is_active' => true]);
        $this->user = User::factory()->create();
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach([$this->cabang->id]);
        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);

        $this->gudangAsal = Gudang::create(['kode' => 'GDG-ASAL', 'nama' => 'Gudang Asal', 'cabang_id' => $this->cabang->id, 'is_active' => true]);
        $this->gudangTujuan = Gudang::create(['kode' => 'GDG-TUJUAN', 'nama' => 'Gudang Tujuan', 'cabang_id' => $this->cabang->id, 'is_active' => true]);

        $this->brandApple = Brand::firstOrCreate(['nama' => 'Apple'], ['is_active' => true]);
        $this->katLcd = KategoriProduk::firstOrCreate(['nama' => 'LCD Screen'], ['is_active' => true]);

        $this->produk1 = Produk::create([
            'kode' => 'PRD-IPHONE13',
            'nama' => 'LCD iPhone 13 Pro Max OLED',
            'barcode' => '88812345601',
            'sku' => 'SKU-IPH13PM',
            'brand_id' => $this->brandApple->id,
            'kategori_id' => $this->katLcd->id,
            'harga_beli' => 800000,
            'harga_jual' => 1200000,
            'is_active' => true,
        ]);

        $this->produk2 = Produk::create([
            'kode' => 'PRD-IPHONE12',
            'nama' => 'Baterai iPhone 12 Original',
            'barcode' => '88812345602',
            'sku' => 'SKU-BAT12',
            'brand_id' => $this->brandApple->id,
            'harga_beli' => 200000,
            'harga_jual' => 350000,
            'is_active' => true,
        ]);

        StokItem::create(['gudang_id' => $this->gudangAsal->id, 'produk_id' => $this->produk1->id, 'jumlah' => 50]);
        StokItem::create(['gudang_id' => $this->gudangAsal->id, 'produk_id' => $this->produk2->id, 'jumlah' => 30]);
    }

    public function test_transfer_tab_scan_barcode_dan_stepper_kuantitas(): void
    {
        Livewire::test(TransferTab::class)
            ->call('openNewTransferModal')
            ->set('transferGudangAsalId', $this->gudangAsal->id)
            ->set('transferGudangTujuanId', $this->gudangTujuan->id)
            ->call('scanBarcodeDirect', '88812345601')
            ->assertSet('transferItems.0.produk_id', $this->produk1->id)
            ->assertSet('transferItems.0.jumlah', 1)
            // Scan lagi barang yang sama -> kuantitas otomatis bertambah jadi 2
            ->call('scanBarcodeDirect', '88812345601')
            ->assertSet('transferItems.0.jumlah', 2)
            // Uji tombol stepper tambah & kurang
            ->call('tambahJumlahItem', 0)
            ->assertSet('transferItems.0.jumlah', 3)
            ->call('kurangJumlahItem', 0)
            ->assertSet('transferItems.0.jumlah', 2);
    }

    public function test_product_search_modal_scan_barcode_direct(): void
    {
        Livewire::test(ProductSearchModal::class)
            ->call('openModal', ['targetIndex' => 0, 'context' => 'transfer'])
            ->call('scanBarcodeDirect', '88812345602')
            ->assertDispatched('produk-dipilih-global', [
                'targetIndex' => 0,
                'context' => 'transfer',
                'produkId' => $this->produk2->id,
            ]);
    }

    public function test_stock_opname_variatif_by_kategori_brand_semua_dan_scan(): void
    {
        // 1. Target Kategori
        $component = Livewire::test(OpnameTab::class)
            ->set('filterGudangId', $this->gudangAsal->id)
            ->call('openNewOpnameModal')
            ->set('opnameTipeTarget', 'kategori')
            ->set('opnameKategoriId', $this->katLcd->id)
            ->call('muatItemOpname');

        $rows = $component->get('opnameRows');
        $this->assertNotEmpty($rows);
        $this->assertSame($this->produk1->id, $rows[0]['produk_id']);

        // 2. Scan barcode pada opname langsung mengupdate hitungan fisik
        $component->call('scanBarcodeDirect', '88812345601');
        $rowsAfterScan = $component->get('opnameRows');
        $this->assertSame(1, $rowsAfterScan[0]['stok_fisik']);

        // Stepper tambah hitungan fisik
        $component->call('tambahFisik', 0);
        $rowsAfterStepper = $component->get('opnameRows');
        $this->assertSame(2, $rowsAfterStepper[0]['stok_fisik']);
    }

    public function test_cycle_count_schedule_target_brand_dan_scan_task(): void
    {
        $rak = Rak::create(['kode' => 'RAK-01', 'nama' => 'Rak Utama', 'gudang_id' => $this->gudangAsal->id]);

        $component = Livewire::test(CycleCountPage::class)
            ->call('bukaFormSchedule')
            ->set('scheduleNama', 'Audit Brand Apple')
            ->set('tipeTarget', 'brand')
            ->set('targetBrandId', $this->brandApple->id)
            ->set('frekuensi', 'mingguan')
            ->set('hari', 1)
            ->set('jam', '08:00')
            ->set('sampleSize', 5)
            ->call('simpanSchedule');

        $schedule = CycleCountSchedule::where('cabang_id', $this->cabang->id)->latest()->first();
        $this->assertNotNull($schedule);
        $this->assertSame('brand', $schedule->tipe_target);
        $this->assertSame($this->brandApple->id, $schedule->target_id);
        $this->assertStringContainsString('Brand Apple', $schedule->target_label);

        // Jalankan task
        $component->call('jalankanSchedule', $schedule->id);
        $task = $schedule->tasks()->first();
        $this->assertNotNull($task);

        // Mulai count dan uji scan barcode pada item sampel
        $sample = $task->sample_items[0];
        $stokItemId = $sample['stok_item_id'];

        $component->call('mulaiCount', $task->id)
            ->call('tambahHitungan', $stokItemId)
            ->assertSet("fisikPerItem.{$stokItemId}", 1)
            ->call('kurangHitungan', $stokItemId)
            ->assertSet("fisikPerItem.{$stokItemId}", 0);
    }

    public function test_tombol_batal_cycle_count_berfungsi_dan_mereset_form(): void
    {
        Livewire::test(CycleCountPage::class)
            ->call('bukaFormSchedule')
            ->assertSet('showScheduleForm', true)
            ->set('scheduleNama', 'Draft Jadwal')
            ->call('batalSchedule')
            ->assertSet('showScheduleForm', false)
            ->assertSet('scheduleNama', '');
    }

    public function test_workflow_stock_opname_scan_tampilkan_produk_input_lanjut_dan_ajukan_approval(): void
    {
        $component = Livewire::test(OpnameTab::class)
            ->set('filterGudangId', $this->gudangAsal->id)
            ->call('openNewOpnameModal')
            ->assertSet('showOpnameModal', true)
            ->assertSet('activeItemIndex', null);

        // 1. User Scan Barcode Produk Pertama
        $component->call('scanBarcodeDirect', '88812345601')
            ->assertSet('activeItemIndex', 0)
            ->assertSet('activeItemFisik', '1')
            ->assertSee('Produk Aktif Dihitung')
            ->assertSee($this->produk1->nama);

        // 2. User update hitungan fisik (misal jadi 15) dan tekan simpan & lanjut
        $component->set('activeItemFisik', '15')
            ->call('simpanDanLanjut')
            ->assertSet('activeItemIndex', null)
            ->assertSet('opnameRows.0.stok_fisik', 15);

        // 3. User Scan Barcode Produk Kedua
        $component->call('scanBarcodeDirect', '88812345602')
            ->assertSet('activeItemIndex', 1)
            ->assertSet('activeItemFisik', '1')
            ->assertSee($this->produk2->nama)
            ->set('activeItemFisik', '28')
            ->call('simpanDanLanjut')
            ->assertSet('activeItemIndex', null)
            ->assertSet('opnameRows.1.stok_fisik', 28);

        // 4. Ajukan ke supervisor
        $component->set('opnameCatatan', 'Opname mingguan akurat')
            ->call('saveOpname')
            ->assertSet('showOpnameModal', false);

        // Verifikasi record stock opname di DB berstatus menunggu_approval
        $this->assertDatabaseHas('stok_opname', [
            'gudang_id' => $this->gudangAsal->id,
            'status' => 'menunggu_approval',
            'catatan' => 'Opname mingguan akurat',
        ]);
    }

    public function test_stock_opname_input_qty_bisa_manual_dan_melalui_tabel(): void
    {
        $component = Livewire::test(OpnameTab::class)
            ->set('filterGudangId', $this->gudangAsal->id)
            ->call('openNewOpnameModal');

        // Pilih item lewat tabel
        $component->call('pilihItemAktif', 0)
            ->assertSet('activeItemIndex', 0)
            ->assertSet('activeItemFisik', '')
            // Input manual angka 42 pada active card
            ->set('activeItemFisik', '42')
            ->assertSet('opnameRows.0.stok_fisik', 42)
            ->call('simpanDanLanjut')
            ->assertSet('activeItemIndex', null);

        // Input manual langsung di baris tabel (updateOpnameFisik)
        $component->call('updateOpnameFisik', 1, 99)
            ->assertSet('opnameRows.1.stok_fisik', 99)
            ->assertSet('opnameRows.1.selisih', 99 - 30);
    }

    public function test_workflow_grn_scan_barcode_tampilkan_produk_aktif_input_manual_dan_simpan_lanjut(): void
    {
        $supplier = Supplier::create(['nama' => 'Supplier Test WMS', 'termin_hari' => 14]);

        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $this->gudangAsal->id,
            'status' => 'dikirim',
            'metode_bayar' => 'kredit',
            'total' => 2000000,
            'total_dibayar' => 0,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $this->produk1->id,
            'harga_beli' => 100000,
            'jumlah' => 10,
            'subtotal' => 1000000,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $this->produk2->id,
            'harga_beli' => 200000,
            'jumlah' => 5,
            'subtotal' => 1000000,
        ]);

        $component = Livewire::test(GrnTab::class)
            ->call('openGrnModal', $po->id)
            ->assertSet('showGrnModal', true)
            ->assertSet('activeItemIndex', null);

        // 1. Scan barcode produk 1 (88812345601)
        $component->call('scanBarcodeDirect', '88812345601')
            ->assertSet('activeItemIndex', 0)
            ->assertSet('activeItemQty', '1')
            ->assertSee('Produk Aktif Diterima')
            ->assertSee($this->produk1->nama);

        // 2. Input manual qty jadi 8, lalu simpan & lanjut
        $component->set('activeItemQty', '8')
            ->call('simpanDanLanjutGrn')
            ->assertSet('activeItemIndex', null)
            ->assertSet('grnForm.item_qty.0.qty_received', 8);

        // 3. Scan barcode produk 2 (88812345602)
        $component->call('scanBarcodeDirect', '88812345602')
            ->assertSet('activeItemIndex', 1)
            ->assertSet('activeItemQty', '1')
            ->assertSee($this->produk2->nama)
            // Pakai tombol terima lengkap (Full)
            ->call('setActiveItemLengkap')
            ->assertSet('activeItemQty', '5')
            ->call('simpanDanLanjutGrn')
            ->assertSet('activeItemIndex', null)
            ->assertSet('grnForm.item_qty.1.qty_received', 5);
    }
}
