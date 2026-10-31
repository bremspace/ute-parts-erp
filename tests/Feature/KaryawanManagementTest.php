<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hr\Livewire\KaryawanPage;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KaryawanRiwayat;
use App\Modules\Hr\Services\UserHrSyncService;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KaryawanManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'teknisi', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'karyawan', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-toko', 'guard_name' => 'web']);
    }

    public function test_rekrut_karyawan_dan_auto_create_user_login(): void
    {
        $cabang = Cabang::create(['nama' => 'Cabang Pusat', 'kode' => 'CP-01', 'is_active' => true]);
        $service = app(UserHrSyncService::class);

        $karyawan = Karyawan::create([
            'nik' => 'EMP-001',
            'nama' => 'Ahmad Teknisi',
            'telepon' => '0812345678',
            'email' => 'ahmad@uteparts.id',
            'jabatan' => 'teknisi',
            'cabang_id' => $cabang->id,
            'tgl_masuk' => now()->toDateString(),
            'gaji_pokok' => 4500000,
            'status_aktif' => true,
        ]);

        $user = $service->createUserFromKaryawan($karyawan, 'ahmad@uteparts.id', 'password123', 'teknisi', $cabang->id);

        $this->assertNotNull($user->id);
        $this->assertEquals('Ahmad Teknisi', $user->name);
        $this->assertTrue($user->hasRole('teknisi'));
        $this->assertEquals($user->id, $karyawan->fresh()->user_id);
        $this->assertTrue($karyawan->fresh()->status_aktif);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_pemutusan_karyawan_otomatis_kunci_user_login_dan_revoke(): void
    {
        $cabang = Cabang::create(['nama' => 'Cabang Pusat', 'kode' => 'CP-01', 'is_active' => true]);
        $service = app(UserHrSyncService::class);

        $karyawan = Karyawan::create([
            'nik' => 'EMP-002',
            'nama' => 'Budi Kasir',
            'jabatan' => 'kasir',
            'cabang_id' => $cabang->id,
            'tgl_masuk' => now()->subMonths(6)->toDateString(),
            'status_aktif' => true,
        ]);

        $user = $service->createUserFromKaryawan($karyawan, 'budi@uteparts.id', 'password123', 'karyawan', $cabang->id);
        $this->assertTrue($user->fresh()->is_active);

        // Eksekusi pemutusan karyawan (offboarding / resign)
        $service->terminateKaryawan($karyawan, 'resign', now()->toDateString(), 'Pengunduran diri atas kemauan sendiri');

        $karyawanSegar = $karyawan->fresh();
        $userSegar = $user->fresh();

        $this->assertFalse($karyawanSegar->status_aktif);
        $this->assertEquals('resign', $karyawanSegar->alasan_keluar);
        $this->assertNotNull($karyawanSegar->tgl_keluar);

        // Pastikan user login aplikasi otomatis dinonaktifkan
        $this->assertFalse($userSegar->is_active);
    }

    public function test_rehire_karyawan_mengaktifkan_kembali_status_dan_user(): void
    {
        $cabang = Cabang::create(['nama' => 'Cabang Pusat', 'kode' => 'CP-01', 'is_active' => true]);
        $service = app(UserHrSyncService::class);

        $karyawan = Karyawan::create([
            'nik' => 'EMP-003',
            'nama' => 'Citra Admin',
            'jabatan' => 'admin',
            'cabang_id' => $cabang->id,
            'tgl_masuk' => now()->subYear()->toDateString(),
            'status_aktif' => false,
            'tgl_keluar' => now()->subMonth()->toDateString(),
            'alasan_keluar' => 'habis_kontrak',
        ]);

        $user = User::create([
            'name' => 'Citra Admin',
            'email' => 'citra@uteparts.id',
            'password' => bcrypt('password123'),
            'is_active' => false,
        ]);
        $karyawan->update(['user_id' => $user->id]);

        // Re-hire
        $service->rehireKaryawan($karyawan, now()->toDateString());

        $this->assertTrue($karyawan->fresh()->status_aktif);
        $this->assertNull($karyawan->fresh()->tgl_keluar);
        $this->assertNull($karyawan->fresh()->alasan_keluar);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_riwayat_tercatat_saat_terminasi_dan_rehire(): void
    {
        $cabang = Cabang::create(['nama' => 'Cabang Pusat', 'kode' => 'CP-01', 'is_active' => true]);
        $service = app(UserHrSyncService::class);

        $karyawan = Karyawan::create([
            'nik' => 'EMP-004',
            'nama' => 'Dedi Teknisi',
            'jabatan' => 'teknisi',
            'cabang_id' => $cabang->id,
            'tgl_masuk' => now()->subYear()->toDateString(),
            'gaji_pokok' => 5000000,
            'status_aktif' => true,
        ]);

        $service->terminateKaryawan($karyawan, 'phk', now()->toDateString(), 'Efisiensi operasional');

        $this->assertDatabaseHas('karyawan_riwayat', [
            'karyawan_id' => $karyawan->id,
            'tipe_event' => 'terminasi',
            'jabatan_lama' => 'teknisi',
        ]);

        $service->rehireKaryawan($karyawan, now()->toDateString());

        $this->assertDatabaseHas('karyawan_riwayat', [
            'karyawan_id' => $karyawan->id,
            'tipe_event' => 'rehire',
            'jabatan_baru' => 'teknisi',
        ]);
    }

    public function test_livewire_karyawan_page_history_actions(): void
    {
        $cabang = Cabang::create(['nama' => 'Cabang Pusat', 'kode' => 'CP-01', 'is_active' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin-toko');

        $karyawan = Karyawan::create([
            'nik' => 'EMP-005',
            'nama' => 'Eva Staff',
            'jabatan' => 'staff',
            'cabang_id' => $cabang->id,
            'tgl_masuk' => now()->toDateString(),
            'gaji_pokok' => 4000000,
            'status_aktif' => true,
        ]);

        Livewire::actingAs($user)
            ->test(KaryawanPage::class)
            ->call('openHistoryModal', $karyawan->id)
            ->assertSet('showHistoryModal', true)
            ->call('showCreateHistory')
            ->set('historyForm.tipe_event', 'promosi')
            ->set('historyForm.tanggal_efektif', now()->toDateString())
            ->set('historyForm.judul', 'Promosi Supervisor')
            ->set('historyForm.jabatan_baru', 'manager')
            ->set('historyForm.gaji_baru', 6500000)
            ->set('historyForm.dokumen_referensi', 'SK-PROMOSI-01')
            ->call('saveHistory')
            ->assertDispatched('alert');

        $this->assertDatabaseHas('karyawan_riwayat', [
            'karyawan_id' => $karyawan->id,
            'tipe_event' => 'promosi',
            'judul' => 'Promosi Supervisor',
            'dokumen_referensi' => 'SK-PROMOSI-01',
        ]);

        $riwayat = KaryawanRiwayat::where('karyawan_id', $karyawan->id)->first();

        // Test Edit
        Livewire::actingAs($user)
            ->test(KaryawanPage::class)
            ->call('openHistoryModal', $karyawan->id)
            ->call('editHistory', $riwayat->id)
            ->set('historyForm.judul', 'Promosi Manager Operasional')
            ->call('saveHistory');

        $this->assertEquals('Promosi Manager Operasional', $riwayat->fresh()->judul);

        // Test Delete
        Livewire::actingAs($user)
            ->test(KaryawanPage::class)
            ->call('openHistoryModal', $karyawan->id)
            ->call('deleteHistory', $riwayat->id);

        $this->assertDatabaseMissing('karyawan_riwayat', ['id' => $riwayat->id]);
    }
}
