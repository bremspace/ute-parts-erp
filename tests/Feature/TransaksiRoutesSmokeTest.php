<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pos\Livewire\PesananOnlineIndex;
use App\Modules\Pos\Livewire\ReturPenjualanIndex;
use App\Modules\Pos\Livewire\RiwayatTransaksiIndex;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TransaksiRoutesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CB99',
            'nama' => 'Cabang Smoke Test',
            'tipe' => 'cabang',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Super Tester',
            'email' => 'supertester@uteparts.test',
            'password' => Hash::make('password'),
            'cabang_id' => $this->cabang->id,
        ]);
        $this->user->assignRole('super-admin');

        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_halaman_pos_kasir_dapat_diakses(): void
    {
        $this->actingAs($this->user)
            ->withSession(['cabang_id' => $this->cabang->id])
            ->get('/app/pos')
            ->assertOk();
    }

    public function test_halaman_pos_riwayat_dapat_diakses(): void
    {
        $this->actingAs($this->user)
            ->withSession(['cabang_id' => $this->cabang->id])
            ->get('/app/pos/riwayat')
            ->assertOk();
    }

    public function test_halaman_pos_riwayat_semua_tab_dapat_dirender(): void
    {
        $this->actingAs($this->user);

        // Buat data dummy transaksi
        $trx = Transaksi::create([
            'no_transaksi' => 'TRX-TEST-001',
            'cabang_id' => $this->cabang->id,
            'kasir_id' => $this->user->id,
            'sumber' => 'pos',
            'subtotal' => 100000,
            'total_akhir' => 100000,
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 100000,
            'status' => 'selesai',
            'durasi_garansi_hari' => 3,
            'garansi_teks' => '3 Hari Toko',
        ]);

        Livewire::test(RiwayatTransaksiIndex::class)
            ->set('activeTab', 'global')
            ->assertSee($trx->no_transaksi)
            ->set('activeTab', 'pos')
            ->assertSee($trx->no_transaksi)
            ->set('activeTab', 'online')
            ->assertOk()
            ->set('activeTab', 'servis')
            ->assertOk()
            ->set('activeTab', 'mutasi_kas')
            ->assertOk()
            ->call('lihatDetailTransaksi', $trx->id)
            ->assertSet('showDetailModal', true)
            ->call('cetakUlangStruk', 'pos', $trx->id)
            ->assertSet('showReceiptModal', true);
    }

    public function test_halaman_pos_online_orders_dapat_diakses(): void
    {
        $this->actingAs($this->user)
            ->withSession(['cabang_id' => $this->cabang->id])
            ->get('/app/pos/online-orders')
            ->assertOk();

        $onlineOrder = Transaksi::create([
            'no_transaksi' => 'TRX-ONLINE-001',
            'cabang_id' => $this->cabang->id,
            'sumber' => 'marketplace',
            'subtotal' => 250000,
            'total_akhir' => 250000,
            'metode_bayar' => 'duitku',
            'status' => 'lunas',
            'catatan' => 'Pesanan Web (ambil_ke_toko)',
        ]);

        Livewire::test(PesananOnlineIndex::class)
            ->assertSee($onlineOrder->no_transaksi)
            ->call('bukaModalPickup', $onlineOrder->id)
            ->assertSet('showPickupModal', true)
            ->call('cetakLabel', $onlineOrder->id)
            ->assertSet('showReceiptModal', true);
    }

    public function test_halaman_pos_retur_dapat_diakses(): void
    {
        $this->actingAs($this->user)
            ->withSession(['cabang_id' => $this->cabang->id])
            ->get('/app/pos/retur')
            ->assertOk();

        Livewire::test(ReturPenjualanIndex::class)
            ->call('bukaModalTambah')
            ->assertSet('showCreateModal', true);
    }

    public function test_halaman_servis_kanban_dapat_diakses(): void
    {
        $this->actingAs($this->user)
            ->withSession(['cabang_id' => $this->cabang->id])
            ->get('/app/servis')
            ->assertOk();
    }
}
