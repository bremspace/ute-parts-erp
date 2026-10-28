<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hr\Livewire\PayrollPage;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserKaryawanAndRoleCreationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::first();

        $this->superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@uteparts.test',
            'password' => bcrypt('Password123!'),
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('super-admin');
        $this->superAdmin->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_role_karyawan_seeded_and_has_permission(): void
    {
        $role = Role::findByName('karyawan', 'web');
        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermissionTo('hr.lihat-sendiri'));
    }

    public function test_user_has_one_karyawan_relationship(): void
    {
        $user = User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@uteparts.test',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $karyawan = Karyawan::create([
            'user_id' => $user->id,
            'nik' => 'EMP-0099',
            'nama' => 'Budi Santoso',
            'jabatan' => 'teknisi',
            'cabang_id' => $this->cabang->id,
            'tgl_masuk' => now()->toDateString(),
            'gaji_pokok' => 3000000,
            'status_aktif' => true,
        ]);

        $this->assertInstanceOf(Karyawan::class, $user->karyawan);
        $this->assertEquals($karyawan->id, $user->karyawan->id);
    }

    public function test_settings_rbac_can_create_user_and_auto_link_karyawan(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(SettingsRbac::class)
            ->set('userForm.name', 'Joko Staff')
            ->set('userForm.email', 'joko@uteparts.test')
            ->set('userForm.password', 'password123')
            ->set('userForm.role', 'karyawan')
            ->set('userForm.cabang_ids', [$this->cabang->id])
            ->set('userForm.cabang_default_id', $this->cabang->id)
            ->set('userForm.buat_karyawan', true)
            ->set('userForm.nik_karyawan', 'EMP-JOKO-01')
            ->call('saveUser')
            ->assertHasNoErrors();

        $user = User::where('email', 'joko@uteparts.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('karyawan'));

        $karyawan = Karyawan::where('user_id', $user->id)->first();
        $this->assertNotNull($karyawan);
        $this->assertEquals('EMP-JOKO-01', $karyawan->nik);
        $this->assertEquals('Joko Staff', $karyawan->nama);
        $this->assertEquals($this->cabang->id, $karyawan->cabang_id);
    }

    public function test_settings_rbac_user_validation_errors(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(SettingsRbac::class)
            ->set('userForm.name', '')
            ->set('userForm.email', 'bukan-email')
            ->set('userForm.password', '123')
            ->set('userForm.role', '')
            ->call('saveUser')
            ->assertHasErrors([
                'userForm.name',
                'userForm.email',
                'userForm.password',
                'userForm.role',
            ]);
    }

    public function test_settings_rbac_custom_role_lifecycle(): void
    {
        $this->actingAs($this->superAdmin);

        // 1. Buat custom role baru
        Livewire::test(SettingsRbac::class)
            ->set('roleBaruNama', 'koordinator-lapangan')
            ->set('roleBaruPermissions', ['hr.lihat-sendiri'])
            ->call('saveRoleBaru')
            ->assertHasNoErrors();

        $customRole = Role::findByName('koordinator-lapangan', 'web');
        $this->assertNotNull($customRole);
        $this->assertTrue($customRole->hasPermissionTo('hr.lihat-sendiri'));

        // 2. Coba hapus role sistem (harus ditolak)
        $karyawanRole = Role::findByName('karyawan', 'web');
        Livewire::test(SettingsRbac::class)
            ->call('hapusRole', $karyawanRole->id)
            ->assertDispatched('alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['type'] ?? '') === 'error' && str_contains($payload['message'] ?? '', 'tidak dapat dihapus');
            });
        $this->assertDatabaseHas('roles', ['name' => 'karyawan']);

        // 3. Hapus custom role yang belum dipakai (harus berhasil)
        Livewire::test(SettingsRbac::class)
            ->call('hapusRole', $customRole->id)
            ->assertDispatched('alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['type'] ?? '') === 'success' && str_contains($payload['message'] ?? '', 'berhasil dihapus');
            });
        $this->assertDatabaseMissing('roles', ['name' => 'koordinator-lapangan']);
    }

    public function test_payroll_page_can_create_karyawan_with_inline_user(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(PayrollPage::class)
            ->call('bukaTambahKaryawan')
            ->set('inputNama', 'Rina Wati')
            ->set('inputNik', 'EMP-RINA-01')
            ->set('inputJabatan', 'kasir')
            ->set('inputCabangId', $this->cabang->id)
            ->set('inputTglMasuk', now()->toDateString())
            ->set('inputKaryawanGajiPokok', '3500000')
            ->set('inputBuatUserBaru', true)
            ->set('inputEmailLogin', 'rina@uteparts.test')
            ->set('inputPasswordLogin', 'secret123')
            ->set('inputRoleLogin', 'kasir')
            ->call('simpanKaryawan')
            ->assertHasNoErrors();

        $user = User::where('email', 'rina@uteparts.test')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Rina Wati', $user->name);
        $this->assertTrue($user->hasRole('kasir'));

        $karyawan = Karyawan::where('nik', 'EMP-RINA-01')->first();
        $this->assertNotNull($karyawan);
        $this->assertEquals($user->id, $karyawan->user_id);
        $this->assertEquals('kasir', $karyawan->jabatan);
        $this->assertEquals(3500000, (float) $karyawan->gaji_pokok);
    }

    public function test_user_model_lifecycle_automatically_syncs_status_and_name_to_karyawan(): void
    {
        $user = User::create([
            'name' => 'Agus Prasetyo',
            'email' => 'agus@uteparts.test',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $karyawan = Karyawan::create([
            'user_id' => $user->id,
            'nik' => 'EMP-AGUS-01',
            'nama' => 'Agus Prasetyo',
            'jabatan' => 'teknisi',
            'cabang_id' => $this->cabang->id,
            'tgl_masuk' => now()->toDateString(),
            'gaji_pokok' => 4000000,
            'status_aktif' => true,
        ]);

        // Ubah nama dan nonaktifkan user
        $user->update([
            'name' => 'Agus Prasetyo Updated',
            'is_active' => false,
        ]);

        $karyawan->refresh();
        $this->assertEquals('Agus Prasetyo Updated', $karyawan->nama);
        $this->assertFalse((bool) $karyawan->status_aktif);

        // Hapus user
        $user->delete();
        $karyawan->refresh();
        $this->assertNull($karyawan->user_id);
        $this->assertFalse((bool) $karyawan->status_aktif);
    }

    public function test_centralized_user_hr_sync_service_and_artisan_command(): void
    {
        $user1 = User::create([
            'name' => 'Karyawan Alpha',
            'email' => 'alpha@uteparts.test',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);
        $user1->assignRole('teknisi');
        $user1->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        $user2 = User::create([
            'name' => 'Karyawan Beta',
            'email' => 'beta@uteparts.test',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);
        $user2->assignRole('kasir');
        $user2->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        $this->artisan('hr:sync-users')
            ->assertExitCode(0);

        $karyawanAlpha = Karyawan::where('user_id', $user1->id)->first();
        $this->assertNotNull($karyawanAlpha);
        $this->assertEquals('teknisi', $karyawanAlpha->jabatan);
        $this->assertEquals('Karyawan Alpha', $karyawanAlpha->nama);

        $karyawanBeta = Karyawan::where('user_id', $user2->id)->first();
        $this->assertNotNull($karyawanBeta);
        $this->assertEquals('kasir', $karyawanBeta->jabatan);
        $this->assertEquals('Karyawan Beta', $karyawanBeta->nama);
    }

    public function test_rbac_api_controller_syncs_user_to_karyawan(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum');

        $response = $this->postJson('/api/users', [
            'name' => 'Staff API Baru',
            'email' => 'staffapi@uteparts.test',
            'password' => 'Password123!',
            'role' => 'staff-gudang',
            'cabang_ids' => [$this->cabang->id],
        ]);

        $response->assertStatus(201);
        $userId = $response->json('data.id');

        $karyawan = Karyawan::where('user_id', $userId)->first();
        $this->assertNotNull($karyawan);
        $this->assertEquals('Staff API Baru', $karyawan->nama);
        $this->assertEquals('admin', $karyawan->jabatan); // staff-gudang di-resolve ke admin di HR
        $this->assertEquals($this->cabang->id, $karyawan->cabang_id);
    }

    public function test_payroll_page_updating_karyawan_syncs_back_to_user(): void
    {
        $this->actingAs($this->superAdmin);

        $user = User::create([
            'name' => 'Doni Ramadhan',
            'email' => 'doni@uteparts.test',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $karyawan = Karyawan::create([
            'user_id' => $user->id,
            'nik' => 'EMP-DONI-01',
            'nama' => 'Doni Ramadhan',
            'jabatan' => 'teknisi',
            'cabang_id' => $this->cabang->id,
            'tgl_masuk' => now()->toDateString(),
            'gaji_pokok' => 3000000,
            'status_aktif' => true,
        ]);

        Livewire::test(PayrollPage::class)
            ->call('bukaEditKaryawan', $karyawan->id)
            ->set('inputNama', 'Doni Ramadhan SE')
            ->set('inputStatusAktif', false)
            ->call('simpanKaryawan')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertEquals('Doni Ramadhan SE', $user->name);
        $this->assertFalse((bool) $user->is_active);
    }
}
