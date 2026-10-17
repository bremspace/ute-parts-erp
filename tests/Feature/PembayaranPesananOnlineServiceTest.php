<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Pos\Services\PembayaranPesananOnlineService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kontrak `PembayaranPesananOnlineService::bayarDiToko()` (API beku):
 *  (a) bayar tunai sukses → selesai + kasir_id + paid_at + stok sekali + jurnal seimbang;
 *  (b) tunai tanpa sesi kas aktif → RuntimeException;
 *  (c) status bukan menunggu_pembayaran → RuntimeException;
 *  (d) DRAWER — hitungSaldoSistem() menghitung pembayaran online TEPAT SATU KALI
 *      meski order dibuat jauh sebelum sesi kas dibuka (opsi A, COALESCE window).
 */
class PembayaranPesananOnlineServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private Cabang $cabang;

    private Gudang $gudang;

    private Produk $produk;

    private Pelanggan $pelanggan;

    private PembayaranPesananOnlineService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::first();
        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Gudang Utama',
            'kode' => 'GDG-PAY-01',
            'is_aktif' => true,
        ]);

        $kat = KategoriProduk::create(['nama' => 'Sparepart Pickup', 'kode' => 'PKP']);
        $this->produk = Produk::create([
            'kode' => 'PRD-PICK-01',
            'nama' => 'Kampas Rem Pickup',
            'sku' => 'SKU-PICK-01',
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
            'nama' => 'Budi Pickup',
            'email' => 'budi-pickup@example.com',
            'telepon' => '081299887700',
            'tipe_konsumen' => 'retail',
        ]);

        $this->kasir = User::create([
            'name' => 'Kasir Pembayaran Online',
            'email' => 'kasir-pickup@test.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->kasir->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->kasir);

        $this->service = app(PembayaranPesananOnlineService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Order marketplace "ambil_ke_toko" status menunggu_pembayaran + 1 item. */
    private function buatOrderPickup(int $jumlah = 3): Transaksi
    {
        $harga = (float) $this->produk->harga_jual_retail;
        $subtotal = $harga * $jumlah;

        $transaksi = Transaksi::create([
            'no_transaksi' => 'MP-PICKUP-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $this->pelanggan->id,
            'sumber' => 'marketplace',
            'subtotal' => $subtotal,
            'total_akhir' => $subtotal,
            'metode_bayar' => 'menunggu',
            'status' => 'menunggu_pembayaran',
            'catatan' => 'Order marketplace [ambil: ambil_ke_toko] anjuran test',
        ]);

        TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $this->produk->id,
            'jumlah' => $jumlah,
            'harga_satuan' => $harga,
            'subtotal' => $subtotal,
            'hpp' => (float) $this->produk->harga_beli,
        ]);

        return $transaksi;
    }

    private function stokProduk(): int
    {
        return (int) StokItem::where('produk_id', $this->produk->id)->first()->jumlah;
    }

    public function test_bayar_tunai_sukses(): void
    {
        $transaksi = $this->buatOrderPickup(3);
        $total = (float) $transaksi->total_akhir;

        $hasil = $this->service->bayarDiToko($transaksi, 'tunai', $total + 50000);

        $this->assertSame(['transaksi', 'kembalian'], array_keys($hasil));
        $this->assertEqualsWithDelta(50000.0, (float) $hasil['kembalian'], 0.01);

        $fresh = $transaksi->fresh();
        $this->assertSame('selesai', $fresh->status);
        $this->assertSame('tunai', $fresh->metode_bayar);
        $this->assertSame($this->kasir->id, (int) $fresh->kasir_id, 'kasir_id wajib terisi (kunci invariant drawer)');
        $this->assertNotNull($fresh->paid_at, 'paid_at wajib terisi');
        $this->assertEqualsWithDelta($total + 50000, (float) $fresh->jumlah_bayar, 0.01);
        $this->assertEqualsWithDelta(50000, (float) $fresh->kembalian, 0.01);

        // Stok terpotong SEKALI: 20 - 3 = 17
        $this->assertSame(17, $this->stokProduk());

        // Jurnal terposting dengan referensi Transaksi::class + id, seimbang,
        // dan debit 110-04 TEPAT SATU baris (tidak dobel dengan mekanisme lain)
        $baris = JurnalAkuntansi::with('akun')
            ->where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $fresh->id)
            ->get();
        $this->assertNotEmpty($baris, 'Jurnal pickup harus terposting dengan referensi transaksi');

        $noJurnal = $baris->first()->no_jurnal;
        $jurnal = JurnalAkuntansi::with('akun')->where('no_jurnal', $noJurnal)->get();

        $this->assertEqualsWithDelta(
            (float) $jurnal->sum('debit'),
            (float) $jurnal->sum('kredit'),
            0.01,
            'Jurnal harus balance (debit = kredit)'
        );

        $debitLaci = $jurnal->filter(fn ($l) => $l->akun->kode === '110-04' && (float) $l->debit > 0);
        $this->assertCount(1, $debitLaci, 'Debit 110-04 (Kas Laci) harus tepat satu baris');
        $this->assertEqualsWithDelta($total, (float) $debitLaci->first()->debit, 0.01);

        $kreditPendapatan = $jurnal->filter(fn ($l) => $l->akun->kode === '410-03');
        $this->assertCount(1, $kreditPendapatan);
        $this->assertEqualsWithDelta($total, (float) $kreditPendapatan->first()->kredit, 0.01);

        // HPP: D 510-02 / C 130-01 = hpp(100000) x 3
        $this->assertEqualsWithDelta(300000, (float) $jurnal->filter(fn ($l) => $l->akun->kode === '510-02')->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(300000, (float) $jurnal->filter(fn ($l) => $l->akun->kode === '130-01')->sum('kredit'), 0.01);

        // Notifikasi konsumen: inapp + wa (konfirmasi pembayaran)
        $this->assertDatabaseHas('notifikasi_keluar', [
            'pelanggan_id' => $this->pelanggan->id,
            'tipe' => 'inapp',
            'judul' => 'Pembayaran Terkonfirmasi',
        ]);
        $this->assertDatabaseHas('notifikasi_keluar', [
            'pelanggan_id' => $this->pelanggan->id,
            'tipe' => 'wa',
            'judul' => 'Pembayaran Diterima',
        ]);
    }

    public function test_bayar_tunai_tanpa_sesi_kas_aktif_melempar(): void
    {
        // Riwayat sesi kas ADA di cabang ini (dibuka lalu ditutup) → guard aktif,
        // tetapi TIDAK ada sesi kas yang sedang buka.
        $kasSesi = app(KasSesiState::class);
        $kasSesi->bukaKas(500000, $this->cabang->id, $this->kasir->id, 'manual', '110-01');
        $kasSesi->tutupKas(500000);

        $transaksi = $this->buatOrderPickup(1);
        $total = (float) $transaksi->total_akhir;

        $thrown = null;
        try {
            $this->service->bayarDiToko($transaksi, 'tunai', $total);
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'Tunai tanpa sesi kas aktif harus melempar RuntimeException');
        $this->assertStringContainsString('Kas belum dibuka', $thrown->getMessage());

        // Tidak ada perubahan apa pun (stok, jurnal, status, kasir, paid_at)
        $fresh = $transaksi->fresh();
        $this->assertSame('menunggu_pembayaran', $fresh->status);
        $this->assertNull($fresh->kasir_id);
        $this->assertNull($fresh->paid_at);
        $this->assertSame(20, $this->stokProduk(), 'Stok tidak boleh terpotong bila pembayaran gagal');
        $this->assertSame(0, JurnalAkuntansi::where('referensi_tipe', Transaksi::class)
            ->where('referensi_id', $transaksi->id)->count());
    }

    public function test_status_bukan_menunggu_pembayaran_melempar(): void
    {
        $transaksi = $this->buatOrderPickup(1);
        $transaksi->update(['status' => 'lunas', 'metode_bayar' => 'transfer_manual']);

        $thrown = null;
        try {
            $this->service->bayarDiToko($transaksi, 'tunai', (float) $transaksi->total_akhir);
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'Status bukan menunggu_pembayaran harus melempar RuntimeException');
        $this->assertStringContainsString('menunggu_pembayaran', $thrown->getMessage());
        $this->assertSame(20, $this->stokProduk());
    }

    public function test_bukan_pesanan_pickup_melempar(): void
    {
        // Status valid tapi alur pengiriman (bukan ambil_ke_toko)
        $transaksi = $this->buatOrderPickup(1);
        $transaksi->update(['catatan' => 'Order marketplace [ambil: pengiriman] kurir J&T']);

        $thrown = null;
        try {
            $this->service->bayarDiToko($transaksi, 'tunai', (float) $transaksi->total_akhir);
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'Pesanan non-pickup harus ditolak');
        $this->assertStringContainsString('bukan pesanan ambil di toko', $thrown->getMessage());
    }

    /**
     * INVARIANT DRAWER (opsi A): order dibuat 2 JAM sebelum sesi kas dibuka,
     * dibayar tunai saat sesi berjalan → `hitungSaldoSistem()` menyertakan
     * nominalnya TEPAT SATU KALI, tanpa baris kas_mutasi_laci (mekanisme
     * tunggal), dan TIDAK dihitung ulang pada sesi berikutnya.
     */
    public function test_drawer_hitung_saldo_sistem_menghitung_pembayaran_online_tepat_satu_kali(): void
    {
        $kasSesi = app(KasSesiState::class);

        // 1. Order dibuat jauh sebelum sesi (posisi kritis dari recon)
        $t0 = now()->subHours(2);
        Carbon::setTestNow($t0);
        $transaksi = $this->buatOrderPickup(3);
        $total = (float) $transaksi->total_akhir;

        // 2. Baru kemudian sesi kas dibuka
        $t1 = $t0->copy()->addHours(2);
        Carbon::setTestNow($t1);
        $buka = $kasSesi->bukaKas(100000, $this->cabang->id, $this->kasir->id, 'manual', '110-01');
        $sesi = DB::table('kas_sesi')->find($buka['id']);

        $this->assertTrue(
            $transaksi->fresh()->created_at->lt(Carbon::parse($sesi->dibuka_at)),
            'Order harus lebih tua dari sesi — window created_at lama TIDAK akan menghitungnya'
        );

        // 3. Bayar tunai saat sesi aktif
        $hasil = $this->service->bayarDiToko($transaksi, 'tunai', $total);
        $this->assertEqualsWithDelta(0.0, (float) $hasil['kembalian'], 0.01);

        // Mekanisme TUNGGAL: tidak ada baris kas_mutasi_laci → mustahil dobel
        $this->assertSame(0, DB::table('kas_mutasi_laci')->count());

        // TEPAT SATU KALI (rupiah): saldo = saldo_awal + total (bukan 2x, bukan nol)
        $saldo = $kasSesi->hitungSaldoSistem();
        $this->assertSame(round(100000 + $total, 2), round($saldo, 2));
        // Pembacaan idempoten
        $this->assertSame($saldo, $kasSesi->hitungSaldoSistem());

        // 4. Tutup kas dengan uang fisik persis → selisih 0 membuktikan jumlah tepat
        $tutup = $kasSesi->tutupKas(100000 + $total);
        $this->assertSame(round(100000 + $total, 2), (float) $tutup['saldo_sistem']);
        $this->assertSame(0.0, (float) $tutup['selisih']);
        $this->assertSame('tutup', $tutup['status']);

        // 5. Sesi BARU setelah pembayaran → TIDAK menghitung ulang pembayaran tsb
        Carbon::setTestNow($t1->copy()->addMinutes(5));
        $kasSesi->bukaKas(0, $this->cabang->id, $this->kasir->id, 'manual', '110-01');
        $this->assertSame(0.0, round($kasSesi->hitungSaldoSistem(), 2), 'Sesi berikutnya tidak boleh menghitung pembayaran sesi sebelumnya');
        $this->assertSame(0, DB::table('kas_mutasi_laci')->count());
    }
}
