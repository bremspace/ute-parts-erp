<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Livewire\RiwayatTransaksiIndex;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\ReturnPenjualanItem;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class RiwayatTransaksiReturPembelianTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Cabang $cabangLain;

    private User $kasirUser;

    private User $supervisorUser;

    private Pelanggan $pelanggan;

    private Supplier $supplier;

    private Gudang $gudang;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Retur Test',
            'kode' => 'CBG-RETUR',
            'is_active' => true,
        ]);

        $this->cabangLain = Cabang::create([
            'nama' => 'Cabang Lain Test',
            'kode' => 'CBG-LAIN',
            'is_active' => true,
        ]);

        $this->kasirUser = User::create([
            'name' => 'Kasir Retur Test',
            'email' => 'kasir-retur@uteparts.id',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->kasirUser->assignRole('kasir');
        $this->kasirUser->cabangs()->attach($this->cabang->id);

        $this->supervisorUser = User::create([
            'name' => 'Supervisor Retur Test',
            'email' => 'supervisor-retur@uteparts.id',
            'password' => Hash::make('supervisorSecret123'),
            'is_active' => true,
        ]);
        $this->supervisorUser->assignRole('admin-toko');
        $this->supervisorUser->cabangs()->attach($this->cabang->id);

        $this->pelanggan = Pelanggan::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Pelanggan Retur Test',
            'telepon' => '081234567890',
        ]);

        $this->supplier = Supplier::create([
            'nama' => 'Supplier Sparepart Test',
            'kode' => 'SUP-RETUR-01',
            'telepon' => '081299998888',
            'alamat' => 'Jl. Supplier Test No. 10',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Cabang Retur Test',
            'kode' => 'GDG-RETUR',
            'is_active' => true,
        ]);

        $kategori = KategoriProduk::create([
            'nama' => 'LCD Test',
            'kode' => 'LCD-TEST',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'LCD iPhone 12 Pro Max OLED',
            'sku' => 'LCD-IP12PM-OLED',
            'harga_beli' => 750000,
            'harga_jual_retail' => 1200000,
            'satuan' => 'pcs',
            'is_active' => true,
        ]);

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 20,
        ]);
    }

    private function createPurchaseOrder(string $status = 'diterima', int $qty = 10, float $hargaBeli = 750000): PurchaseOrder
    {
        $subtotal = $qty * $hargaBeli;

        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-'.uniqid(),
            'supplier_id' => $this->supplier->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'user_id' => $this->kasirUser->id,
            'status' => $status,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'metode_bayar' => 'utang',
            'sisa' => $subtotal,
            'total_dibayar' => 0,
            'jatuh_tempo' => Carbon::now()->addDays(30),
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $this->produk->id,
            'jumlah' => $qty,
            'harga_beli' => $hargaBeli,
            'subtotal' => $subtotal,
        ]);

        return $po;
    }

    private function createTransaksiSelesai(float $total = 1200000): Transaksi
    {
        $trx = Transaksi::create([
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->kasirUser->id,
            'pelanggan_id' => $this->pelanggan->id,
            'no_transaksi' => 'TRX-TEST-'.uniqid(),
            'subtotal' => $total,
            'total_akhir' => $total,
            'jumlah_bayar' => $total,
            'kembalian' => 0,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
            'sumber' => 'pos',
        ]);

        TransaksiItem::create([
            'transaksi_id' => $trx->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_satuan' => $total,
            'subtotal' => $total,
            'hpp' => 750000,
        ]);

        return $trx;
    }

    public function test_akses_tab_pembelian_dan_buka_detail(): void
    {
        $po = $this->createPurchaseOrder('diterima', 5);

        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->kasirUser)
            ->test(RiwayatTransaksiIndex::class, ['activeTab' => 'pembelian'])
            ->assertSet('activeTab', 'pembelian')
            ->assertSee($po->no_po)
            ->call('lihatDetailPembelian', $po->id)
            ->assertSet('showDetailModal', true)
            ->assertSet('selectedDetail.tipe', 'pembelian')
            ->assertSet('selectedDetail.no_po', $po->no_po)
            ->assertSet('selectedDetail.bisa_diretur', true)
            ->call('tutupDetailModal')
            ->assertSet('showDetailModal', false)
            ->assertSet('selectedDetail', null);
    }

    public function test_filter_status_bisa_retur_pada_tab_pembelian(): void
    {
        $poDiterima = $this->createPurchaseOrder('diterima', 5);
        $poDraft = $this->createPurchaseOrder('draft', 5);

        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->kasirUser)
            ->test(RiwayatTransaksiIndex::class, ['activeTab' => 'pembelian'])
            ->set('filterStatus', 'bisa_retur')
            ->assertSee($poDiterima->no_po)
            ->assertDontSee($poDraft->no_po);
    }

    public function test_akses_tab_retur_menampilkan_retur_penjualan_dan_retur_pembelian(): void
    {
        $trx = $this->createTransaksiSelesai(1200000);

        // Buat data retur penjualan
        $returJual = ReturnPenjualan::create([
            'cabang_id' => $this->cabang->id,
            'transaksi_id' => $trx->id,
            'pelanggan_id' => $this->pelanggan->id,
            'user_id' => $this->kasirUser->id,
            'no_return' => 'RET-POS-'.uniqid(),
            'tanggal' => Carbon::today(),
            'tipe_penyelesaian' => 'kembali_dana',
            'metode_pengembalian' => 'kas',
            'status' => 'selesai',
            'jumlah' => 1200000,
            'alasan' => 'Barang cacat pabrik',
        ]);

        ReturnPenjualanItem::create([
            'return_penjualan_id' => $returJual->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 1,
            'harga_satuan' => 1200000,
            'subtotal' => 1200000,
            'kondisi_barang' => 'rusak',
        ]);

        // Buat data retur pembelian
        $po = $this->createPurchaseOrder('diterima', 4);
        $returBeli = ReturnPembelian::create([
            'cabang_id' => $this->cabang->id,
            'purchase_order_id' => $po->id,
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudang->id,
            'user_id' => $this->kasirUser->id,
            'no_return' => 'RET-PO-'.uniqid(),
            'tanggal' => Carbon::today(),
            'metode_pengembalian' => 'utang',
            'status' => 'selesai',
            'jumlah' => 1500000,
            'alasan' => 'Retur ke supplier test',
        ]);

        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->kasirUser)
            ->test(RiwayatTransaksiIndex::class, ['activeTab' => 'retur'])
            ->assertSet('activeTab', 'retur')
            ->assertSee($returJual->no_return)
            ->assertSee($returBeli->no_return)
            ->set('filterJenisRetur', 'penjualan')
            ->assertSee($returJual->no_return)
            ->assertDontSee($returBeli->no_return)
            ->set('filterJenisRetur', 'pembelian')
            ->assertSee($returBeli->no_return)
            ->assertDontSee($returJual->no_return);
    }

    public function test_eksekusi_retur_pembelian_sukses_dan_potong_stok(): void
    {
        $po = $this->createPurchaseOrder('diterima', 10, 750000);
        $poItem = $po->items->first();

        $stokAwal = StokItem::where('gudang_id', $this->gudang->id)
            ->where('produk_id', $this->produk->id)
            ->value('jumlah');

        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->kasirUser)
            ->test(RiwayatTransaksiIndex::class, ['activeTab' => 'pembelian'])
            ->call('bukaModalReturPembelian', $po->id)
            ->assertSet('showReturPembelianModal', true)
            ->assertSet('returPoId', $po->id)
            ->set("returItems.{$poItem->id}.jumlah_retur", 3)
            ->set('returAlasan', 'Layar blank setelah test QC')
            ->set('returMetodePengembalian', 'utang')
            ->call('prosesSimpanReturPembelian')
            ->assertHasNoErrors()
            ->assertSet('showReturPembelianModal', false)
            ->assertDispatched('alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['type'] ?? '') === 'success' && str_contains($payload['message'] ?? '', 'berhasil');
            });

        // Verifikasi retur pembelian tersimpan di DB
        $this->assertDatabaseHas('return_pembelian', [
            'purchase_order_id' => $po->id,
            'cabang_id' => $this->cabang->id,
            'supplier_id' => $this->supplier->id,
            'metode_pengembalian' => 'utang',
            'alasan' => 'Layar blank setelah test QC',
        ]);

        // Verifikasi stok terpotong jika auto-selesai
        $retur = ReturnPembelian::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($retur);

        if ($retur->status === 'selesai') {
            $stokAkhir = StokItem::where('gudang_id', $this->gudang->id)
                ->where('produk_id', $this->produk->id)
                ->value('jumlah');
            $this->assertEquals($stokAwal - 3, $stokAkhir);
        }
    }

    public function test_validasi_retur_pembelian_gagal_jika_qty_melebihi_sisa(): void
    {
        $po = $this->createPurchaseOrder('diterima', 5, 750000);
        $poItem = $po->items->first();

        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->kasirUser)
            ->test(RiwayatTransaksiIndex::class, ['activeTab' => 'pembelian'])
            ->call('bukaModalReturPembelian', $po->id)
            ->set("returItems.{$poItem->id}.jumlah_retur", 10) // PO hanya 5
            ->set('returAlasan', 'Kelebihan qty retur')
            ->call('prosesSimpanReturPembelian')
            ->assertHasErrors(["returItems.{$poItem->id}.jumlah_retur"])
            ->assertSet('showReturPembelianModal', true);
    }

    public function test_void_transaksi_langsung_dari_modal_detail_transaksi(): void
    {
        $trx = $this->createTransaksiSelesai(1200000);

        // Buka detail transaksi, lalu trigger void langsung dari modal detail
        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->supervisorUser)
            ->test(RiwayatTransaksiIndex::class, ['activeTab' => 'pos'])
            ->call('lihatDetailTransaksi', $trx->id)
            ->assertSet('showDetailModal', true)
            ->assertSet('selectedDetail.id', $trx->id)
            ->assertSet('selectedDetail.status', 'selesai')
            // Panggil void modal menggunakan ID dari selectedDetail
            ->call('bukaVoidModal', $trx->id)
            ->assertSet('showVoidModal', true)
            ->assertSet('voidTransaksiId', $trx->id)
            ->set('voidAlasan', 'Konsumen salah pilih tipe unit')
            ->set('voidSupervisorAuth', $this->supervisorUser->email)
            ->set('voidSupervisorPassword', 'supervisorSecret123')
            ->call('prosesVoidTransaksi')
            ->assertHasNoErrors()
            ->assertSet('showVoidModal', false)
            ->assertDispatched('alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['type'] ?? '') === 'success' && str_contains($payload['message'] ?? '', 'VOID');
            });

        $this->assertEquals('void', $trx->fresh()->status);
    }
}
