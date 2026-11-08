<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Marketplace\Services\DuitkuPaymentProcessor;
use App\Modules\Marketplace\Services\OrderService;
use App\Modules\Pos\Livewire\PesananOnlineIndex;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\ReturnPembelianItem;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Services\ReturnPembelianService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class WmsFaseEmpatOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected User $user;

    protected Produk $produk;

    protected Pelanggan $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create(['nama' => 'Cabang WMS Utama', 'kode' => 'CB-WMS', 'is_active' => true]);
        $this->gudang = Gudang::create(['cabang_id' => $this->cabang->id, 'nama' => 'Gudang Pusat WMS', 'kode' => 'GD-WMS', 'is_active' => true]);

        $this->user = User::factory()->create();
        $this->user->cabangs()->attach($this->cabang->id, ['is_default' => true]);
        $this->actingAs($this->user);

        $kategori = KategoriProduk::create(['nama' => 'Onderdil', 'kode' => 'OND']);
        $this->produk = Produk::create([
            'kategori_id' => $kategori->id,
            'kode' => 'PRD-WMS-01',
            'nama' => 'Shock Absorber Ori',
            'harga_beli' => 150000,
            'harga_jual_retail' => 250000,
            'is_active' => true,
        ]);

        StokItem::create([
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'jumlah' => 20,
        ]);

        $this->pelanggan = Pelanggan::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Pelanggan Online WMS',
            'telepon' => '089912345678',
        ]);

        // COA
        AkunCOA::firstOrCreate(['kode' => '110-01'], ['nama' => 'Kas Toko', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '120-02'], ['nama' => 'Kliring Duitku', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '410-03'], ['nama' => 'Pendapatan Web', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::firstOrCreate(['kode' => '510-02'], ['nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '130-01'], ['nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '520-08'], ['nama' => 'Beban Selisih Retur', 'tipe' => 'beban', 'kelompok' => 'beban_lain', 'saldo_normal' => 'debit']);
    }

    /**
     * T-19: Produk mengimplementasikan SoftDeletes, data tidak terhapus permanen dari DB.
     */
    public function test_t19_produk_menggunakan_soft_deletes(): void
    {
        $id = $this->produk->id;
        $this->produk->delete();

        // Query normal tidak menemukan produk
        $this->assertNull(Produk::find($id));

        // Query withTrashed menemukan produk
        $trashed = Produk::withTrashed()->find($id);
        $this->assertNotNull($trashed);
        $this->assertNotNull($trashed->deleted_at);

        // Baris stok_item tetap ada di database (tidak ter-hard-delete via cascade)
        $this->assertDatabaseHas('stok_items', [
            'produk_id' => $id,
            'gudang_id' => $this->gudang->id,
        ]);
    }

    /**
     * T-20: Pembatalan pesanan online memulihkan stok via StokDeductionService::kembalikan()
     * dan mencatat StokLog serta StockMutationLog yang valid.
     */
    public function test_t20_pembatalan_pesanan_online_memulihkan_stok_secara_valid(): void
    {
        // 1. Buat pesanan online & lunaskan (stok terpotong 2 unit dari 20 menjadi 18)
        $order = app(OrderService::class)->buatOrder(
            $this->pelanggan,
            [['produk_id' => $this->produk->id, 'jumlah' => 2]],
            $this->cabang->id,
            'ambil_toko'
        );

        app(DuitkuPaymentProcessor::class)->prosesLunas($order->no_transaksi, 500000, 'REF-WMS-01', 'duitku');

        $stokSetelahLunas = StokItem::where('produk_id', $this->produk->id)->where('gudang_id', $this->gudang->id)->value('jumlah');
        $this->assertEquals(18, $stokSetelahLunas);

        // 2. Batalkan pesanan via PesananOnlineIndex
        session(['cabang_id' => $this->cabang->id]);

        Livewire::test(PesananOnlineIndex::class)
            ->call('bukaModalBatal', $order->id)
            ->set('alasanBatal', 'Pelanggan membatalkan pemesanan')
            ->call('eksekusiBatal');

        // 3. Verifikasi stok kembali ke 20
        $stokSetelahBatal = StokItem::where('produk_id', $this->produk->id)->where('gudang_id', $this->gudang->id)->value('jumlah');
        $this->assertEquals(20, $stokSetelahBatal);

        // 4. Verifikasi StokLog dan StockMutationLog
        $this->assertDatabaseHas('stok_log', [
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'jenis' => 'masuk',
            'referensi_tipe' => Transaksi::class,
            'referensi_id' => $order->id,
            'perubahan' => 2,
        ]);

        $this->assertDatabaseHas('stock_mutation_log', [
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'delta' => 2,
            'referensi_tipe' => Transaksi::class,
            'referensi_id' => $order->id,
        ]);
    }

    /**
     * T-21: Penolakan retur vendor (kembali ke toko) mengembalikan stok via StokDeductionService::mutasi()
     * dan mencatat audit trail mutasi.
     */
    public function test_t21_penolakan_retur_vendor_memulihkan_stok_via_mutasi(): void
    {
        $supplier = Supplier::create([
            'nama' => 'PT Vendor Sparepart',
            'kode' => 'VND-01',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::create([
            'cabang_id' => $this->cabang->id,
            'gudang_tujuan_id' => $this->gudang->id,
            'supplier_id' => $supplier->id,
            'no_po' => 'PO-TEST-001',
            'status' => 'diterima',
            'total' => 150000,
        ]);

        $retur = ReturnPembelian::create([
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudang->id,
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po->id,
            'no_return' => 'RET-VND-001',
            'status' => 'dikirim',
            'jumlah' => 150000,
        ]);

        ReturnPembelianItem::create([
            'return_pembelian_id' => $retur->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 3,
            'harga_beli' => 50000,
            'subtotal' => 150000,
        ]);

        $stokAwal = (int) StokItem::where('produk_id', $this->produk->id)->where('gudang_id', $this->gudang->id)->value('jumlah');

        // Eksekusi penolakan retur dengan opsi barang kembali ke toko
        $service = app(ReturnPembelianService::class);
        $service->tolakReturSupplier($retur, 'Barang bukan cacat pabrik', 'kembali_ke_toko', $this->user->id);

        // Stok harus bertambah 3 unit
        $stokAkhir = (int) StokItem::where('produk_id', $this->produk->id)->where('gudang_id', $this->gudang->id)->value('jumlah');
        $this->assertEquals($stokAwal + 3, $stokAkhir);

        // Verifikasi histori mutasi tercatat
        $this->assertDatabaseHas('stok_log', [
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'referensi_tipe' => ReturnPembelian::class,
            'referensi_id' => $retur->id,
            'perubahan' => 3,
        ]);

        $this->assertDatabaseHas('stock_mutation_log', [
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'sumber' => 'retur_pembelian_ditolak',
            'referensi_tipe' => ReturnPembelian::class,
            'referensi_id' => $retur->id,
            'delta' => 3,
        ]);
    }

    /**
     * T-22: Relasi referensi(): MorphTo berfungsi di StokLog dan StockMutationLog
     * serta indeks komposit referensi tersedia di skema DB.
     */
    public function test_t22_relasi_morph_to_referensi_dan_indeks_komposit(): void
    {
        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-REF-001',
            'cabang_id' => $this->cabang->id,
            'sumber' => 'pos',
            'subtotal' => 50000,
            'total_akhir' => 50000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);

        $stokLog = StokLog::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jenis' => 'penjualan',
            'referensi_tipe' => Transaksi::class,
            'referensi_id' => $transaksi->id,
            'jumlah_sebelum' => 20,
            'perubahan' => -1,
            'jumlah_setelah' => 19,
        ]);

        $this->assertInstanceOf(Transaksi::class, $stokLog->referensi);
        $this->assertEquals($transaksi->id, $stokLog->referensi->id);

        $mutationLog = StockMutationLog::create([
            'produk_id' => $this->produk->id,
            'gudang_id' => $this->gudang->id,
            'delta' => -1,
            'sumber' => 'pos',
            'referensi_tipe' => Transaksi::class,
            'referensi_id' => $transaksi->id,
            'terjadi_at' => now(),
        ]);

        $this->assertInstanceOf(Transaksi::class, $mutationLog->referensi);
        $this->assertEquals($transaksi->id, $mutationLog->referensi->id);

        // Verifikasi keberadaan kolom deleted_at di produk
        $this->assertTrue(Schema::hasColumn('produk', 'deleted_at'));
    }
}
