<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\HistoriPengadaanTab;
use App\Modules\Wms\Livewire\ReturnPembelianTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\NomorSeri;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\ReturnPembelianItem;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\ProcurementService;
use App\Modules\Wms\Services\ReturnPembelianService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiklusTigaFaseReturDanHistoriPengadaanTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Supplier $supplier;

    protected Produk $produk;

    protected PurchaseOrder $po;

    protected PurchaseOrderItem $poItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CBG-TEST',
            'nama' => 'Cabang Test',
            'alamat' => 'Jl. Test',
            'telepon' => '08123456789',
            'is_pusat' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'GDG-TEST',
            'nama' => 'Gudang Utama',
            'is_aktif' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole('super-admin');
        $this->actingAs($this->user);
        session(['cabang_id' => $this->cabang->id]);

        $this->supplier = Supplier::create([
            'nama' => 'PT Mitra Sparepart Jaya',
            'kontak' => 'Budi',
            'telepon' => '08111222333',
            'is_active' => true,
            'kebijakan_retur' => 'faktur_asli',
            'saldo_deposit' => 0,
        ]);

        $this->produk = Produk::create([
            'nama' => 'LCD Touchscreen OLED iPhone 13',
            'sku' => 'LCD-IP13-OLED',
            'barcode' => '8991234567890',
            'harga_beli' => 750000,
            'harga_jual_retail' => 1200000,
            'is_active' => true,
        ]);

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 10,
        ]);

        $this->po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-001',
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'user_id' => $this->user->id,
            'status' => 'diterima',
            'total' => 3750000,
            'total_dibayar' => 3750000,
            'tanggal' => now(),
        ]);

        $this->poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $this->po->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 5,
            'harga_beli' => 750000,
            'subtotal' => 3750000,
        ]);
    }

    public function test_siklus_tiga_fase_draft_ke_kirim_resi_ke_penerimaan_unit_pengganti_sn_baru(): void
    {
        $service = app(ReturnPembelianService::class);

        // FASE 1: Buat Draft Retur Pembelian
        $retur = $service->buatRetur(
            $this->po,
            [
                [
                    'purchase_order_item_id' => $this->poItem->id,
                    'jumlah' => 2,
                    'sn' => [],
                ],
            ],
            'Layar flicker setelah unboxing',
            'saldo',
            $this->cabang->id,
            $this->gudang->id,
            $this->user->id
        );

        $this->assertEquals('draft', $retur->status);
        $this->assertEquals(1500000, (float) $retur->jumlah);

        // Stok sebelum kirim masih 10 (belum dipotong karena masih draft)
        $stokAwal = StokItem::where('gudang_id', $this->gudang->id)
            ->where('produk_id', $this->produk->id)
            ->value('jumlah');
        $this->assertEquals(10, $stokAwal);

        // FASE 2: Konfirmasi Kirim Fisik ke Supplier (In-Transit dengan Resi)
        $returDikirim = $service->kirimKeSupplier($retur, [
            'ekspedisi' => 'JNE Express',
            'no_resi' => 'JNE-RMA-889900',
            'penerima_vendor' => 'Tim RMA Pak Hendra',
            'catatan' => 'Unit dikirim lengkap dengan box asli',
        ], $this->user->id);

        $this->assertEquals('dikirim', $returDikirim->status);
        $this->assertEquals('JNE Express', $returDikirim->ekspedisi);
        $this->assertEquals('JNE-RMA-889900', $returDikirim->no_resi);

        // Stok toko berkurang 2 unit (10 -> 8) karena barang fisik telah keluar
        $stokSetelahKirim = StokItem::where('gudang_id', $this->gudang->id)
            ->where('produk_id', $this->produk->id)
            ->value('jumlah');
        $this->assertEquals(8, $stokSetelahKirim);

        // FASE 3: Penerimaan Unit Pengganti Baru dari Vendor (Tukar Unit / Replacement + SN Baru)
        $returSelesai = $service->terimaUnitPengganti($returDikirim, [
            'sn' => [
                $this->produk->id => ['SN-OLED-NEW-001', 'SN-OLED-NEW-002'],
            ],
            'catatan' => '2 Unit pengganti baru diterima kondisi mulus lolos QC',
            'gudang_id' => $this->gudang->id,
        ], $this->user->id);

        $this->assertEquals('selesai', $returSelesai->status);
        $this->assertEquals('tukar_unit', $returSelesai->metode_pengembalian);

        // Stok fisik toko kembali bertambah 2 unit (8 -> 10)
        $stokSetelahTerima = StokItem::where('gudang_id', $this->gudang->id)
            ->where('produk_id', $this->produk->id)
            ->value('jumlah');
        $this->assertEquals(10, $stokSetelahTerima);

        // Verifikasi pendaftaran SN baru di database
        $snCount = NomorSeri::where('produk_id', $this->produk->id)
            ->whereIn('nomor_seri', ['SN-OLED-NEW-001', 'SN-OLED-NEW-002'])
            ->where('status', NomorSeri::STATUS_TERSEDIA)
            ->count();
        $this->assertEquals(2, $snCount);
    }

    public function test_integrasi_histori_retur_pembelian_pada_procurement_service_dan_livewire_pengadaan(): void
    {
        $service = app(ReturnPembelianService::class);

        // Buat dan selesaikan retur 1 unit
        $retur = $service->buatRetur(
            $this->po,
            [
                [
                    'purchase_order_item_id' => $this->poItem->id,
                    'jumlah' => 1,
                ],
            ],
            'Dead pixel',
            'saldo',
            $this->cabang->id,
            $this->gudang->id,
            $this->user->id
        );

        $service->kirimKeSupplier($retur, ['ekspedisi' => 'Sicepat', 'no_resi' => 'SC-12345'], $this->user->id);
        $service->selesaikanKompensasiFinansial($retur, 750000, 'saldo', 'Kredit saldo disetujui', $this->user->id);

        // Uji ProcurementService getHistoriPembelian mencakup retur
        $procurement = app(ProcurementService::class);
        $histori = $procurement->getHistoriPembelian($this->produk->id);

        $this->assertNotEmpty($histori['items']);
        $this->assertNotEmpty($histori['retur_items']);
        $this->assertEquals(5, $histori['statistik']['total_qty']); // Qty PO
        $this->assertEquals(1, $histori['statistik']['total_retur_qty']); // Qty Retur
        $this->assertEquals(4, $histori['statistik']['net_pengadaan_qty']); // 5 - 1 = 4
        $this->assertEquals(750000, $histori['statistik']['total_retur_nilai']);
        $this->assertEquals(3000000, $histori['statistik']['net_pengadaan_nilai']); // 3.750.000 - 750.000 = 3.000.000

        // Uji Tampilan Livewire Histori Pengadaan Tab memuat KPI Net Pengadaan & pencarian bebas
        Livewire::test(HistoriPengadaanTab::class)
            ->assertSee('Histori Pengadaan Barang')
            ->assertSee('Retur ke Supplier')
            ->assertSee('Net Pengadaan Bersih')
            ->set('search', 'H')
            ->assertOk();
    }

    public function test_livewire_return_pembelian_tab_aksi_tiga_fase_dan_riwayat_aktivitas(): void
    {
        $retur = ReturnPembelian::create([
            'no_return' => 'RET-CBG-TEST-2610-099',
            'purchase_order_id' => $this->po->id,
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'user_id' => $this->user->id,
            'tanggal' => now(),
            'jumlah' => 750000,
            'status' => 'draft',
            'metode_pengembalian' => 'tukar_unit',
        ]);

        ReturnPembelianItem::create([
            'return_pembelian_id' => $retur->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_beli' => 750000,
            'subtotal' => 750000,
        ]);

        // Uji Livewire component bisa buka riwayat aktivitas tanpa error
        Livewire::test(ReturnPembelianTab::class)
            ->call('bukaRiwayat', 'retur_pembelian', $retur->id)
            ->assertSet('showRiwayatModal', true)
            ->assertSet('riwayatId', $retur->id)
            ->call('tutupRiwayat')
            ->assertSet('showRiwayatModal', false)
            ->call('bukaDetail', $retur->id)
            ->assertSet('detailReturId', $retur->id)
            ->set('kirimEkspedisi', 'J&T Cargo')
            ->set('kirimNoResi', 'JT-990011')
            ->call('prosesKirimKeSupplier', $retur->id)
            ->assertDispatched('alert');

        $retur->refresh();
        $this->assertEquals('dikirim', $retur->status);
        $this->assertEquals('J&T Cargo', $retur->ekspedisi);

        // Dari status dikirim, selesaikan via hasil vendor
        Livewire::test(ReturnPembelianTab::class)
            ->call('bukaDetail', $retur->id)
            ->set('hasilTipe', 'tukar_unit')
            ->set('hasilReplacementSn', [$this->produk->id => 'SN-EXCHANGE-888'])
            ->call('prosesHasilVendor', $retur->id)
            ->assertDispatched('alert');

        $retur->refresh();
        $this->assertEquals('selesai', $retur->status);
        $this->assertEquals('tukar_unit', $retur->metode_pengembalian);
    }
}
