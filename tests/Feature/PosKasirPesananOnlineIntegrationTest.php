<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Livewire\PosKasir;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Siklus pesanan online pickup SELESAI di layar Kasir POS (tanpa buka menu
 * /app/pos/online-orders):
 *  (a) bayar di toko (menunggu_pembayaran) → selesai + stok sekali + jurnal + struk;
 *  (b) order sudah LUNAS online → lookup kasir menemukannya → Serahkan Barang
 *      → selesai TANPA potong stok ulang & TANPA jurnal kas baru + struk bukti.
 */
class PosKasirPesananOnlineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    private Gudang $gudang;

    private Produk $produk;

    private Pelanggan $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::first();

        $this->user = User::create([
            'name' => 'Kasir Pickup Online',
            'email' => 'kasir-pickup-online@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama',
            'kode' => 'GDG-POP-01',
            'is_aktif' => true,
        ]);

        $kat = KategoriProduk::create(['nama' => 'Sparepart Pickup Online', 'kode' => 'PKO']);
        $this->produk = Produk::create([
            'kode' => 'PRD-POP-01',
            'nama' => 'Kampas Rem Pickup Online',
            'sku' => 'SKU-POP-01',
            'kategori_id' => $kat->id,
            'harga_beli' => 100000,
            'harga_jual_retail' => 150000,
            'stok_minimum' => 5,
        ]);
        StokItem::create([
            'gudang_id' => $this->gudang->id,
            'produk_id' => $this->produk->id,
            'jumlah' => 20,
        ]);

        $this->pelanggan = Pelanggan::create([
            'nama' => 'Budi Online',
            'email' => 'budi-online@example.com',
            'telepon' => '081299887700',
            'tipe_konsumen' => 'retail',
        ]);
    }

    /** Order marketplace "ambil_ke_toko" (default menunggu_pembayaran) + 1 item. */
    private function buatOrderPickup(string $status = 'menunggu_pembayaran', int $jumlah = 3): Transaksi
    {
        $harga = (float) $this->produk->harga_jual_retail;
        $subtotal = $harga * $jumlah;

        $transaksi = Transaksi::create([
            'no_transaksi' => 'MP-KSR-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $this->pelanggan->id,
            'sumber' => 'marketplace',
            'subtotal' => $subtotal,
            'total_akhir' => $subtotal,
            'metode_bayar' => $status === 'lunas' ? 'duitku' : 'menunggu',
            'status' => $status,
            'catatan' => 'Order marketplace [ambil: ambil_ke_toko] test kasir',
        ]);

        TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $this->produk->id,
            'jumlah' => $jumlah,
            'harga_satuan' => $harga,
            'subtotal' => $subtotal,
            'hpp' => (float) $this->produk->harga_beli,
        ]);

        if ($status === 'lunas') {
            // Sudah dibayar online (gateway): paid_at terisi + stok sudah terpotong
            // sekali saat konfirmasi lunas (perilaku konfirmasiLunas).
            $transaksi->update([
                'paid_at' => now()->subHour(),
                'jumlah_bayar' => $subtotal,
                'kembalian' => 0,
            ]);
            StokItem::where('produk_id', $this->produk->id)->update(['jumlah' => 20 - $jumlah]);
        }

        return $transaksi;
    }

    private function stokProduk(): int
    {
        return (int) StokItem::where('produk_id', $this->produk->id)->first()->jumlah;
    }

    public function test_bayar_online_pickup_tunai_dari_layar_kasir(): void
    {
        // 1. Sesi kas aktif (wajib utk tunai)
        app(KasSesiState::class)->bukaKas(
            saldoAwal: 500000,
            cabangId: $this->cabang->id,
            userId: $this->user->id,
            sumber: 'manual',
            akunSumberKode: '110-01'
        );

        $transaksi = $this->buatOrderPickup('menunggu_pembayaran', 3);
        $total = (float) $transaksi->total_akhir;

        // 2. Layar kasir: lookup → pilih → bayar tunai (nominal format ribuan)
        Livewire::test(PosKasir::class)
            ->call('bukaPesananOnlineModal')
            ->assertSee($transaksi->no_transaksi) // lookup menemukan order
            ->assertViewHas('jumlahPesananOnline', 1) // badge
            ->set('pesananOnlineTerpilih', $transaksi->id)
            ->assertSee('Terima Pembayaran') // tombol bayar (belum lunas) tampil
            ->set('metodeBayarOnline', 'tunai')
            ->set('nominalBayarOnline', number_format($total + 50000, 0, ',', '.'))
            ->call('bayarOnlineOrder')
            ->assertSet('showPesananOnlineModal', false)
            ->assertSet('showReceiptModal', true) // struk bukti serah terima
            ->assertSee('Struk Transaksi Selesai')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? null) === 'success');

        // 3. Status transaksi: selesai + kasir_id + paid_at
        $transaksi->refresh();
        $this->assertSame('selesai', $transaksi->status);
        $this->assertSame('tunai', $transaksi->metode_bayar);
        $this->assertSame($this->user->id, (int) $transaksi->kasir_id, 'kasir_id wajib terisi (kunci drawer)');
        $this->assertNotNull($transaksi->paid_at, 'paid_at wajib terisi');
        $this->assertEqualsWithDelta($total + 50000, (float) $transaksi->jumlah_bayar, 0.01);
        $this->assertEqualsWithDelta(50000, (float) $transaksi->kembalian, 0.01);

        // 4. Stok berkurang TEPAT SATU KALI: 20 - 3 = 17
        $this->assertSame(17, $this->stokProduk());

        // 5. Jurnal 110-04 (debit) / 410-03 (kredit) terposting & seimbang
        $baris = JurnalAkuntansi::with('akun')
            ->where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $transaksi->id)
            ->get();
        $this->assertNotEmpty($baris, 'Jurnal pickup kasir harus terposting dengan referensi transaksi');

        $jurnal = JurnalAkuntansi::with('akun')->where('no_jurnal', $baris->first()->no_jurnal)->get();
        $this->assertEqualsWithDelta((float) $jurnal->sum('debit'), (float) $jurnal->sum('kredit'), 0.01);

        $debitLaci = $jurnal->filter(fn ($l) => $l->akun->kode === '110-04' && (float) $l->debit > 0);
        $this->assertCount(1, $debitLaci, 'Debit 110-04 (Kas Laci) tepat satu baris');
        $this->assertEqualsWithDelta($total, (float) $debitLaci->first()->debit, 0.01);

        $kreditPendapatan = $jurnal->filter(fn ($l) => $l->akun->kode === '410-03');
        $this->assertCount(1, $kreditPendapatan);
        $this->assertEqualsWithDelta($total, (float) $kreditPendapatan->first()->kredit, 0.01);

        // 6. Notifikasi konfirmasi pembayaran ke konsumen
        $this->assertDatabaseHas('notifikasi_keluar', [
            'pelanggan_id' => $this->pelanggan->id,
            'tipe' => 'inapp',
            'judul' => 'Pembayaran Terkonfirmasi',
        ]);
    }

    public function test_serahkan_online_pickup_sudah_lunas_dari_layar_kasir(): void
    {
        $transaksi = $this->buatOrderPickup('lunas', 2);
        $total = (float) $transaksi->total_akhir;

        // 1. Lookup kasir MENEMUKAN order lunas (menunggu serah) + tampil ringkasan lunas
        Livewire::test(PosKasir::class)
            ->call('bukaPesananOnlineModal')
            ->assertViewHas('jumlahPesananOnline', 1)
            ->assertSee($transaksi->no_transaksi)
            ->set('pesananOnlineTerpilih', $transaksi->id)
            // Sudah lunas: input metode/nominal disembunyikan, aksi = serahkan barang
            ->assertSee('Sudah Lunas')
            ->assertSee('Serahkan Barang')
            ->assertDontSee('Terima Pembayaran')
            ->call('serahkanPesananOnline')
            ->assertSet('showPesananOnlineModal', false)
            ->assertSet('showReceiptModal', true) // struk bukti serah terima
            ->assertSee('Struk Transaksi Selesai')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? null) === 'success');

        // 2. Status → selesai, tanpa kasir_id (tak ada uang masuk laci)
        $transaksi->refresh();
        $this->assertSame('selesai', $transaksi->status);
        $this->assertNull($transaksi->kasir_id, 'Handover tidak boleh mengisi kasir_id (tidak ada uang laci)');
        $this->assertStringContainsString('Diserahkan ke Konsumen di Toko', (string) $transaksi->catatan);

        // 3. Stok TIDAK dipotong lagi (masih 20 - 2 = 18) & TIDAK ada jurnal kas baru
        $this->assertSame(18, $this->stokProduk());
        $this->assertSame(0, JurnalAkuntansi::where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $transaksi->id)->count(), 'Handover tidak membuat jurnal baru');

        // 4. Notifikasi serah-terima ke konsumen tetap terkirim (inapp)
        $this->assertDatabaseHas('notifikasi_keluar', [
            'pelanggan_id' => $this->pelanggan->id,
            'tipe' => 'inapp',
            'judul' => 'Pesanan Telah Diambil di Toko',
        ]);
    }

    public function test_serahkan_online_pickup_ditolak_untuk_order_belum_lunas(): void
    {
        // Anti-tamper: id order tidak lolos guard cabang/status lunas/pickup
        $transaksi = $this->buatOrderPickup('menunggu_pembayaran', 1);

        Livewire::test(PosKasir::class)
            ->call('bukaPesananOnlineModal')
            ->set('pesananOnlineTerpilih', $transaksi->id)
            ->call('serahkanPesananOnline')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? null) === 'error')
            ->assertSet('showReceiptModal', false);

        $transaksi->refresh();
        $this->assertSame('menunggu_pembayaran', $transaksi->status, 'Order belum lunas tidak boleh bisa diserahkan');
        $this->assertNull($transaksi->kasir_id);
        $this->assertSame(20, $this->stokProduk());
        $this->assertSame(0, JurnalAkuntansi::where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $transaksi->id)->count());
    }
}
