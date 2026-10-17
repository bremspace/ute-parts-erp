<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Notifikasi\Livewire\NotificationCenter;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Pos\Livewire\PesananOnlineIndex;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Auto-refresh halaman Pesanan Online (wire:poll) dan siklus watermark
 * lastKnownMaxId di NotificationCenter::checkNewNotifications():
 * baris yang tidak lolos scope user TETAP menaikkan watermark agar tidak
 * di-scan berulang tiap poll, tanpa mengubah perilaku dispatch ter-scope.
 */
class NotificationAutoRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CBG-AUTO',
            'nama' => 'Cabang AutoRefresh',
            'alamat' => 'Jl. Test No. 11',
            'telepon' => '0812111111',
            'is_pusat' => true,
        ]);

        $role = Role::create(['name' => 'super-admin']);
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole($role);
        $this->superAdmin->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_check_new_notifications_menangkap_baris_baru_tanpa_remount(): void
    {
        $this->actingAs($this->superAdmin);
        $component = Livewire::test(NotificationCenter::class);

        NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'transaksi',
            'judul' => 'Pesanan Online Baru AutoRefresh',
            'konten' => 'Order INV-AUTO-1 menunggu pembayaran.',
            'status' => 'terkirim',
            'user_id' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
            'action_url' => '/app/pos/online-orders',
        ]);

        // Poll hook menangkap baris baru tanpa remount: event ter-dispatch
        // DAN list render ulang menampilkan judulnya.
        $component->call('checkNewNotifications')
            ->assertDispatched('ute:notification-arrived')
            ->assertSee('Pesanan Online Baru AutoRefresh');
    }

    public function test_last_known_max_id_naik_walaupun_baris_tidak_lolos_scope(): void
    {
        $cabangLain = Cabang::create([
            'kode' => 'CBG-LAIN',
            'nama' => 'Cabang Lain',
            'alamat' => 'Jl. Lain No. 2',
            'telepon' => '0812222222',
            'is_pusat' => false,
        ]);

        $this->actingAs($this->superAdmin);
        $component = Livewire::test(NotificationCenter::class)
            ->assertSet('lastKnownMaxId', 0);

        // Baris inapp yang TIDAK lolos scope: cabang berbeda dari session
        // (scope cabang tetap berlaku untuk super-admin).
        $barisTidakLolos = NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'transaksi',
            'judul' => 'Baris Cabang Lain',
            'konten' => 'Tidak boleh terlihat di bell cabang aktif.',
            'status' => 'terkirim',
            'cabang_id' => $cabangLain->id,
        ]);

        // Watermark naik ke id baris non-scope TANPA dispatch — baris tidak
        // di-scan berulang pada poll berikutnya.
        $component->call('checkNewNotifications')
            ->assertNotDispatched('ute:notification-arrived')
            ->assertSet('lastKnownMaxId', $barisTidakLolos->id)
            ->assertDontSee('Baris Cabang Lain');

        // Siklus tetap sehat: baris scope-compliant berikutnya masih tertangkap.
        $barisLolos = NotifikasiKeluar::create([
            'tipe' => 'inapp',
            'kategori' => 'transaksi',
            'judul' => 'Baris Cabang Aktif AutoRefresh',
            'konten' => 'Harus terlihat di bell cabang aktif.',
            'status' => 'terkirim',
            'user_id' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $component->call('checkNewNotifications')
            ->assertDispatched('ute:notification-arrived')
            ->assertSet('lastKnownMaxId', $barisLolos->id)
            ->assertSee('Baris Cabang Aktif AutoRefresh');
    }

    public function test_pesanan_online_blade_memiliki_poll(): void
    {
        // Setup identik SmokeOnlineOrdersTest agar komponen render penuh
        $perm = Permission::firstOrCreate(['name' => 'pos.view']);
        $role = Role::firstOrCreate(['name' => 'super-admin-smoke-auto']);
        $role->givePermissionTo($perm);
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        AkunCOA::firstOrCreate(['kode' => '110-01'], ['nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '110-04'], ['nama' => 'Kas Laci', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '110-02'], ['nama' => 'Bank', 'tipe' => 'aset', 'kelompok' => 'bank', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '120-02'], ['nama' => 'Kliring Duitku', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '410-03'], ['nama' => 'Pendapatan Web', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::firstOrCreate(['kode' => '510-02'], ['nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '130-01'], ['nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);

        // Root element pesanan-online-index.blade.php harus memasang wire:poll
        // agar list + metric cards + badge status ikut refresh otomatis.
        $this->actingAs($user);
        Livewire::test(PesananOnlineIndex::class)
            ->assertSee('wire:poll', false);
    }
}
