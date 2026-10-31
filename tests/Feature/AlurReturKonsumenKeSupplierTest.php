<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Livewire\ReturPenjualanIndex;
use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\ReturnPenjualanItem;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\AktivitasLog;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\ReturnPembelianTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AlurReturKonsumenKeSupplierTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected Gudang $gudang;

    protected Supplier $supplier;

    protected Pelanggan $pelanggan;

    protected Produk $produkRusak;

    protected Produk $produkPengganti;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Retur E2E',
            'kode' => 'CBG-E2E',
            'is_active' => true,
        ]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Retur E2E',
            'kode' => 'G-E2E',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'nama' => 'Supplier LCD Prima',
            'kode' => 'SUP-LCD',
            'is_active' => true,
        ]);

        $this->pelanggan = Pelanggan::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Budi Konsumen',
            'no_hp' => '081234567890',
            'is_active' => true,
        ]);

        $this->produkRusak = Produk::create([
            'kategori_id' => 1,
            'nama' => 'LCD iPhone 11 OEM',
            'barcode' => 'LCD-IP11-OEM',
            'harga_beli' => 200000,
            'harga_jual' => 350000,
            'is_active' => true,
        ]);

        $this->produkPengganti = Produk::create([
            'kategori_id' => 1,
            'nama' => 'LCD iPhone 11 Original',
            'barcode' => 'LCD-IP11-ORI',
            'harga_beli' => 300000,
            'harga_jual' => 500000,
            'is_active' => true,
        ]);

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produkRusak->id,
            'jumlah' => 10,
        ]);

        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produkPengganti->id,
            'jumlah' => 5,
        ]);

        // Simulasikan riwayat PO agar produkRusak terhubung dengan supplier
        $po = PurchaseOrder::create([
            'gudang_tujuan_id' => $this->gudang->id,
            'supplier_id' => $this->supplier->id,
            'no_po' => 'PO-TEST-001',
            'status' => 'diterima',
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $this->produkRusak->id,
            'jumlah' => 10,
            'harga_beli' => 200000,
            'subtotal' => 2000000,
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole('super-admin');
    }

    public function test_alur_lengkap_retur_konsumen_tukar_barang_hingga_retur_supplier(): void
    {
        $this->actingAs($this->user);
        session(['cabang_id' => $this->cabang->id]);

        // 1. Simulasikan Penjualan POS ke Konsumen
        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-E2E-001',
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $this->pelanggan->id,
            'kasir_id' => $this->user->id,
            'metode_pembayaran' => 'tunai',
            'total_kotor' => 350000,
            'total_diskon' => 0,
            'total_akhir' => 350000,
            'total_bayar' => 350000,
            'kembalian' => 0,
            'status' => 'selesai',
            'created_at' => now(),
        ]);

        $trxItem = TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $this->produkRusak->id,
            'jumlah' => 1,
            'harga_satuan' => 350000,
            'harga_final' => 350000,
            'subtotal' => 350000,
            'hpp' => 200000,
        ]);

        // 2. Alur POS: Konsumen mengajukan retur tukar barang dengan selisih bayar
        // Verifikasi komponen Livewire ReturPenjualanIndex dan property jumlahBayarSelisih
        $selisihWajibBayar = 500000 - 350000; // 150.000

        $component = Livewire::test(ReturPenjualanIndex::class)
            ->set('kwitansiInput', 'TRX-E2E-001')
            ->call('cariKwitansi')
            ->assertSet('selectedTransaksi.id', $transaksi->id)
            ->assertSet('jumlahBayarSelisih', 0)
            // Uji update live pada properti jumlahBayarSelisih
            ->set('jumlahBayarSelisih', $selisihWajibBayar)
            ->assertSet('jumlahBayarSelisih', $selisihWajibBayar);

        // Siapkan input retur: barang rusak ditukar dengan produk pengganti
        $component->set("itemInputs.{$trxItem->id}.checked", true)
            ->set("itemInputs.{$trxItem->id}.jumlah", 1)
            ->set("itemInputs.{$trxItem->id}.kondisi_barang", 'rusak')
            ->set("itemInputs.{$trxItem->id}.alasan", 'Layar bergaris cacat pabrik')
            ->set("itemInputs.{$trxItem->id}.tipe_penyelesaian", 'tukar_produk')
            ->set("itemInputs.{$trxItem->id}.metode_pengembalian", 'kas')
            ->set("itemInputs.{$trxItem->id}.tukar_items", [
                [
                    'produk_id' => $this->produkPengganti->id,
                    'nama' => $this->produkPengganti->nama,
                    'harga_satuan' => 500000,
                    'jumlah' => 1,
                    'subtotal' => 500000,
                    'stok_tersedia' => 5,
                ],
            ])
            ->set('alasan', 'Barang cacat pabrik')
            ->set('metodeBayarSelisih', 'tunai')
            ->set('jumlahBayarSelisih', $selisihWajibBayar)
            ->call('simpanRetur')
            ->assertHasNoErrors()
            ->assertDispatched('alert');

        // Verifikasi dokumen retur penjualan terbentuk di POS
        $returPenjualan = ReturnPenjualan::where('transaksi_id', $transaksi->id)->first();
        $this->assertNotNull($returPenjualan);
        $this->assertEquals(350000, (float) $returPenjualan->jumlah);
        $this->assertEquals('tukar_produk', $returPenjualan->tipe_penyelesaian);
        $this->assertEquals($selisihWajibBayar, (float) $returPenjualan->jumlah_bayar_selisih);

        // Verifikasi item retur penjualan masuk karantina dan mengaitkan supplier
        $returItem = ReturnPenjualanItem::where('return_penjualan_id', $returPenjualan->id)->first();
        $this->assertNotNull($returItem);
        $this->assertEquals('rusak', $returItem->kondisi_barang);
        $this->assertEquals('karantina', $returItem->status_karantina);
        $this->assertEquals($this->supplier->id, $returItem->supplier_id);

        // Verifikasi stok:
        // Produk rusak TIDAK bertambah ke stok aktif (tetap 10)
        $stokRusak = StokItem::where('gudang_id', $this->gudang->id)->where('produk_id', $this->produkRusak->id)->first();
        $this->assertEquals(10, (int) $stokRusak->jumlah);

        // Produk pengganti berkurang 1 (5 -> 4)
        $stokPengganti = StokItem::where('gudang_id', $this->gudang->id)->where('produk_id', $this->produkPengganti->id)->first();
        $this->assertEquals(4, (int) $stokPengganti->jumlah);

        // 3. Alur WMS: Verifikasi Pool Karantina & Eksekusi Retur ke Supplier
        $wmsTab = Livewire::test(ReturnPembelianTab::class)
            ->set('subTab', 'karantina')
            ->assertSee('LCD iPhone 11 OEM')
            ->assertSee('Supplier LCD Prima');

        // Buka modal retur pool supplier dan proses buat draft retur pembelian
        $wmsTab->call('bukaModalPoolRetur', $this->supplier->id)
            ->assertSet('selectedPoolSupplierId', $this->supplier->id)
            ->set('poolAlasan', 'Cacat pabrik dari garansi konsumen')
            ->set('poolMetodePengembalian', 'saldo')
            ->call('simpanReturPoolSupplier')
            ->assertHasNoErrors()
            ->assertDispatched('alert');

        // 4. Verifikasi dokumen draft retur pembelian terbentuk lengkap beserta estimasi nilainya
        $returPembelian = ReturnPembelian::where('supplier_id', $this->supplier->id)->latest('id')->first();
        $this->assertNotNull($returPembelian);
        $this->assertEquals('draft', $returPembelian->status);
        $this->assertEquals(200000, (float) $returPembelian->jumlah); // senilai estimasi HPP / harga beli

        // Status karantina sementara terkunci di draft_retur_supplier
        $returItem->refresh();
        $this->assertEquals('draft_retur_supplier', $returItem->status_karantina);
        $this->assertEquals($returPembelian->id, $returItem->return_pembelian_id);

        // Cetak dokumen pengantar retur (PO/GRN format)
        $responsePrint = $this->get(route('wms.retur-pembelian.print', $returPembelian->id));
        $responsePrint->assertOk();
        $responsePrint->assertSee('Surat Pengantar Retur Supplier');
        $responsePrint->assertSee($returPembelian->no_return);

        // Lakukan konfirmasi pengiriman ke supplier dengan opsi penyelesaian saldo
        $wmsTab->set('kirimOpsiPenyelesaian', 'saldo')
            ->set('kirimCatatan', 'Dikirim via kurir toko')
            ->call('konfirmasiKirimKeSupplier', $returPembelian->id)
            ->assertDispatched('alert');

        $returPembelian->refresh();
        $this->assertEquals('selesai', $returPembelian->status);

        // Verifikasi status karantina pada item retur konsumen telah menjadi sudah_diretur_supplier
        $returItem->refresh();
        $this->assertEquals('sudah_diretur_supplier', $returItem->status_karantina);
        $this->assertEquals($returPembelian->id, $returItem->return_pembelian_id);

        // 5. Verifikasi Dual-Indexing Audit Log:
        // Cek log aktivitas untuk produk rusak
        // 5. Verifikasi Dual-Indexing Audit Log:
        // Cek log aktivitas untuk produk rusak
        $logProdukRusak = AktivitasLog::untukProduk($this->produkRusak->id)->get();
        $this->assertTrue($logProdukRusak->isNotEmpty());

        $hasReturPenjualanLog = $logProdukRusak->contains(function ($log) {
            return $log->subject_type === ReturnPenjualan::class;
        });
        $this->assertTrue($hasReturPenjualanLog, 'Audit log retur penjualan harus tercatat pada produk rusak');

        $hasReturPembelianLog = $logProdukRusak->contains(function ($log) {
            return $log->subject_type === ReturnPembelian::class;
        });
        $this->assertTrue($hasReturPembelianLog, 'Audit log retur pembelian supplier harus tercatat pada produk rusak');
    }
}
