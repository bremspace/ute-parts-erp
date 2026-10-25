<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Marketplace\Models\Pengiriman;
use App\Modules\Marketplace\Services\DuitkuService;
use App\Modules\Pos\Livewire\RiwayatTransaksiIndex;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\TiketServis;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class RiwayatTransaksiGlobalTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $user;

    private Pelanggan $pelanggan;

    private function authed(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Cabang Pusat', 'kode' => 'CBG-PST', 'is_active' => true]);

        $this->user = User::create([
            'name' => 'Kasir Utama',
            'email' => 'kasir-test@uteparts.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');

        $this->pelanggan = Pelanggan::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Budi Pelanggan',
            'telepon' => '081234567890',
        ]);
    }

    public function test_riwayat_transaksi_global_menampilkan_pos_servis_dan_mutasi(): void
    {
        $this->authed();

        // 1. Buat Transaksi POS
        $trxPos = Transaksi::create([
            'no_transaksi' => 'TRX-POS-001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $this->pelanggan->id,
            'sumber' => 'pos',
            'subtotal' => 150000,
            'total_akhir' => 150000,
            'metode_bayar' => 'tunai',
            'status' => 'selesai',
        ]);

        // 2. Buat Tiket Servis Selesai & Lunas
        $tiket = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $this->pelanggan->id,
            'teknisi_id' => $this->user->id,
            'no_tiket' => 'SRV-TEST-001',
            'jenis_hp' => 'iPhone 13',
            'seri_hp' => 'Pro Max',
            'keluhan' => 'Layar Blank',
            'status' => 'selesai',
            'status_pembayaran' => 'lunas',
            'metode_pembayaran' => 'transfer',
            'tanggal_selesai' => now(),
            'tanggal_bayar' => now(),
            'estimasi_biaya' => 350000,
        ]);

        // Transaksi pelunasan servis di POS
        $trxServis = Transaksi::create([
            'no_transaksi' => 'TRX-SRV-001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $this->pelanggan->id,
            'sumber' => 'servis',
            'tiket_servis_id' => $tiket->id,
            'subtotal' => 350000,
            'total_akhir' => 350000,
            'metode_bayar' => 'transfer',
            'status' => 'selesai',
        ]);

        // 3. Buat Sesi Kas & Mutasi Kas Laci
        $sesiId = DB::table('kas_sesi')->insertGetId([
            'cabang_id' => $this->cabang->id,
            'user_id' => $this->user->id,
            'saldo_awal' => 100000,
            'status' => 'buka',
            'dibuka_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mutasiId = DB::table('kas_mutasi_laci')->insertGetId([
            'kas_sesi_id' => $sesiId,
            'cabang_id' => $this->cabang->id,
            'user_id' => $this->user->id,
            'jenis' => 'keluar',
            'nominal' => 25000,
            'akun_lawan_kode' => '520-05',
            'no_jurnal' => 'JRL-KAS-001',
            'keterangan' => 'Beli ATK Kasir',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Uji render tab Global (default)
        Livewire::test(RiwayatTransaksiIndex::class)
            ->assertSet('activeTab', 'global')
            ->assertSee('Semua Transaksi (Global)')
            ->assertSee('TRX-POS-001')
            ->assertSee('TRX-SRV-001')
            ->assertSee('JRL-KAS-001')
            ->assertSee('Budi Pelanggan')
            ->assertSee('Beli ATK Kasir')
            // Buka modal detail POS
            ->call('lihatDetailTransaksi', $trxPos->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('Detail Transaksi TRX-POS-001')
            ->call('tutupDetailModal')
            ->assertSet('showDetailModal', false)
            // Buka modal detail Servis
            ->call('lihatDetailServis', $tiket->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('Rincian Transaksi Servis SRV-TEST-001')
            ->assertSee('iPhone 13 (Pro Max)')
            ->call('tutupDetailModal')
            // Buka modal detail Mutasi Kas
            ->call('lihatDetailMutasi', $mutasiId)
            ->assertSet('showDetailModal', true)
            ->assertSee('JRL-KAS-001')
            ->assertSee('Beli ATK Kasir')
            ->call('tutupDetailModal')
            // Uji cetak ulang struk POS
            ->call('cetakUlangStruk', 'pos', $trxPos->id)
            ->assertSet('showReceiptModal', true)
            ->assertSee('Struk Transaksi Selesai')
            ->assertSee('TRX-POS-001')
            // Uji cetak ulang struk Servis
            ->call('cetakUlangStruk', 'servis', $tiket->id)
            ->assertSet('showReceiptModal', true)
            ->assertSee('SRV-TEST-001')
            // Uji filter jenis transaksi di tab global
            ->set('filterStatus', 'servis')
            ->assertSee('TRX-SRV-001')
            ->assertDontSee('TRX-POS-001')
            ->assertDontSee('JRL-KAS-001');
    }

    public function test_tab_servis_menampilkan_rekap_transaksi_selesai_penuh(): void
    {
        $this->authed();

        $tiketSelesai = TiketServis::create([
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $this->pelanggan->id,
            'teknisi_id' => $this->user->id,
            'no_tiket' => 'SRV-DONE-999',
            'jenis_hp' => 'Samsung Galaxy',
            'seri_hp' => 'S22 Ultra',
            'keluhan' => 'Ganti Baterai',
            'status' => 'diambil',
            'status_pembayaran' => 'lunas',
            'metode_pembayaran' => 'qris',
            'tanggal_selesai' => now(),
            'tanggal_bayar' => now(),
            'estimasi_biaya' => 200000,
        ]);

        Livewire::test(RiwayatTransaksiIndex::class)
            ->set('activeTab', 'servis')
            ->assertSee('Rekap Servis Selesai')
            ->assertSee('SRV-DONE-999')
            ->assertSee('Samsung Galaxy (S22 Ultra)')
            ->assertSee('LUNAS')
            ->assertSee('Diambil');
    }

    public function test_riwayat_transaksi_menampilkan_pesanan_online_web_dan_omnichannel(): void
    {
        $this->authed();

        // 1. Buat pesanan online Web Ute Store
        $orderWeb = Transaksi::create([
            'no_transaksi' => 'TRX-MKT-888',
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $this->pelanggan->id,
            'sumber' => 'marketplace',
            'subtotal' => 250000,
            'total_akhir' => 250000,
            'metode_bayar' => 'duitku_va',
            'status' => 'lunas',
            'catatan' => 'Order Web Ute Parts',
        ]);

        // Buat pengiriman untuk order web
        Pengiriman::create([
            'transaksi_id' => $orderWeb->id,
            'kurir' => 'JNE',
            'layanan' => 'REG',
            'tracking_id' => 'JNE1234567890',
            'ongkir' => 15000,
            'nama_penerima' => 'Budi Penerima',
            'alamat_tujuan' => 'Jl. Merdeka No. 10',
            'telepon_tujuan' => '081234567890',
            'status' => 'dikirim',
        ]);

        // 2. Buat pesanan online Omnichannel Shopee
        $orderShopee = Transaksi::create([
            'no_transaksi' => 'TRX-SHP-999',
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $this->pelanggan->id,
            'sumber' => 'channel:shopee',
            'subtotal' => 450000,
            'total_akhir' => 450000,
            'metode_bayar' => 'shopee_pay',
            'status' => 'selesai',
            'catatan' => 'Pesanan Shopee',
        ]);

        // Tab Global: harus menampilkan TRX-MKT-888 & TRX-SHP-999 dan kartu ringkasan
        Livewire::test(RiwayatTransaksiIndex::class)
            ->assertSet('activeTab', 'global')
            ->assertSee('TRX-MKT-888')
            ->assertSee('TRX-SHP-999')
            ->assertSee('Pesanan Online')
            // Cek kartu ringkasan menampilkan count & nominal
            ->assertSee('2 Trx')
            // Filter jenis transaksi: online
            ->set('filterStatus', 'online')
            ->assertSee('TRX-MKT-888')
            ->assertSee('TRX-SHP-999')
            // Buka tab online
            ->set('activeTab', 'online')
            ->assertSee('TRX-MKT-888')
            ->assertSee('TRX-SHP-999')
            ->assertSee('Web Ute')
            ->assertSee('Shopee')
            ->assertSee('JNE')
            // Buka detail modal pesanan online
            ->call('lihatDetailTransaksi', $orderWeb->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('Detail Transaksi TRX-MKT-888')
            ->assertSee('JNE')
            ->assertSee('JNE1234567890')
            ->assertSee('Budi Penerima')
            ->call('tutupDetailModal')
            // Cetak struk pesanan online
            ->call('cetakUlangStruk', 'online', $orderWeb->id)
            ->assertSet('showReceiptModal', true)
            ->assertSee('TRX-MKT-888');
    }

    public function test_transaksi_piutang_relasi_dan_detail_riwayat(): void
    {
        $this->authed();

        $trxPiutang = Transaksi::create([
            'no_transaksi' => 'TRX-KASBON-001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->user->id,
            'pelanggan_id' => $this->pelanggan->id,
            'sumber' => 'pos',
            'subtotal' => 200000,
            'total_akhir' => 200000,
            'metode_bayar' => 'piutang',
            'status' => 'selesai',
        ]);

        $piutang = Piutang::create([
            'no_piutang' => 'PIU-2026-0001',
            'pelanggan_id' => $this->pelanggan->id,
            'transaksi_id' => $trxPiutang->id,
            'cabang_id' => $this->cabang->id,
            'jumlah' => 200000,
            'jumlah_dibayar' => 50000,
            'jatuh_tempo' => now()->addDays(14)->toDateString(),
            'status' => 'sebagian',
            'keterangan' => 'Kasbon Bengkel',
        ]);

        // Verifikasi relasi Eloquent
        $this->assertInstanceOf(Piutang::class, $trxPiutang->piutang);
        $this->assertEquals('PIU-2026-0001', $trxPiutang->piutang->no_piutang);

        // Verifikasi di Livewire RiwayatTransaksi
        Livewire::test(RiwayatTransaksiIndex::class)
            ->set('activeTab', 'pos')
            ->assertSee('TRX-KASBON-001')
            ->assertSee('piutang')
            ->assertSee('(SEBAGIAN)')
            ->call('lihatDetailTransaksi', $trxPiutang->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('Subledger Piutang / Kasbon: PIU-2026-0001')
            ->assertSee('SEBAGIAN')
            ->assertSee('Rp 150.000'); // Sisa
    }

    public function test_online_sync_dan_pickup_handover(): void
    {
        $this->authed();

        // 1. Pesanan Online Web Ambil di Toko (Pickup)
        $orderPickup = Transaksi::create([
            'no_transaksi' => 'TRX-ONLINE-PCK-01',
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $this->pelanggan->id,
            'sumber' => 'marketplace',
            'subtotal' => 100000,
            'total_akhir' => 100000,
            'metode_bayar' => 'duitku',
            'status' => 'lunas',
            'catatan' => 'Pesanan ambil_ke_toko cabang',
        ]);

        // 2. Pesanan Online Menunggu Pembayaran
        $orderMenunggu = Transaksi::create([
            'no_transaksi' => 'TRX-ONLINE-UNPAID-01',
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $this->pelanggan->id,
            'sumber' => 'marketplace',
            'subtotal' => 75000,
            'total_akhir' => 75000,
            'metode_bayar' => 'duitku',
            'status' => 'menunggu_pembayaran',
            'catatan' => 'Kirim JNE',
        ]);

        // Mock DuitkuService
        $mockDuitku = \Mockery::mock(DuitkuService::class);
        $mockDuitku->shouldReceive('transactionStatus')
            ->with('TRX-ONLINE-UNPAID-01')
            ->andReturn([
                'status' => 'sukses',
                'status_code' => '00',
                'reference' => 'DUITKU-REF-999',
                'amount' => 75000,
                'fee' => 0,
            ]);
        $this->app->instance(DuitkuService::class, $mockDuitku);

        // Eksekusi Livewire
        Livewire::test(RiwayatTransaksiIndex::class)
            ->set('activeTab', 'online')
            ->assertSee('TRX-ONLINE-PCK-01')
            ->assertSee('TRX-ONLINE-UNPAID-01')
            ->assertSee('Ambil di Toko')
            // Filter platform
            ->set('filterPlatform', 'marketplace')
            ->assertSee('TRX-ONLINE-PCK-01')
            // Filter pengiriman: pickup
            ->set('filterMetodePengiriman', 'pickup')
            ->assertSee('TRX-ONLINE-PCK-01')
            ->assertDontSee('TRX-ONLINE-UNPAID-01')
            ->set('filterMetodePengiriman', 'kurir')
            ->assertSee('TRX-ONLINE-UNPAID-01')
            ->assertDontSee('TRX-ONLINE-PCK-01')
            ->set('filterMetodePengiriman', '')
            // Sinkronkan status online Duitku
            ->call('sinkronkanStatusOnline', $orderMenunggu->id)
            ->assertDispatched('alert');

        $this->assertEquals('lunas', $orderMenunggu->fresh()->status);

        // Serahkan pesanan pickup
        Livewire::test(RiwayatTransaksiIndex::class)
            ->call('serahkanPesananPickup', $orderPickup->id)
            ->assertDispatched('alert');

        $this->assertEquals('selesai', $orderPickup->fresh()->status);
        $this->assertStringContainsString('Diserahkan ke Konsumen di Toko', $orderPickup->fresh()->catatan);
    }
}
