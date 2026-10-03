<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
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
}
