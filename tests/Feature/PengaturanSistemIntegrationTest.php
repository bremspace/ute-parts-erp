<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Livewire\SessionManagementPage;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Models\DeviceSession;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PengaturanSistemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $kasir;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::firstOrCreate(
            ['kode' => 'CBG-01'],
            ['nama' => 'Cabang Utama', 'is_active' => true]
        );

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@uteparts.id',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('super-admin');
        $this->superAdmin->cabangs()->attach($this->cabang->id, ['is_default' => true]);

        $this->kasir = User::factory()->create([
            'name' => 'Kasir Toko',
            'email' => 'kasir@uteparts.id',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $this->kasir->assignRole('kasir');
        $this->kasir->cabangs()->attach($this->cabang->id, ['is_default' => true]);
    }

    /**
     * Test akses route 4 menu Pengaturan & Sistem sesuai RBAC.
     */
    public function test_rbac_routes_pengaturan_dan_sistem(): void
    {
        // 1. Pengaturan RBAC
        $this->actingAs($this->superAdmin);
        session(['cabang_id' => $this->cabang->id]);
        $response = $this->get('/app/pengaturan');
        $response->assertStatus(200);

        // 2. Keamanan 2FA
        $response = $this->get('/app/keamanan/dua-faktor');
        $response->assertStatus(200);

        // 3. Sesi Perangkat
        $response = $this->get('/app/keamanan/sesi');
        $response->assertStatus(200);

        // 4. Audit Log
        $response = $this->get('/app/audit-log');
        $response->assertStatus(200);

        // Kasir tanpa izin kelola-sesi dan lihat-audit-log harus 403
        $this->actingAs($this->kasir);
        $this->get('/app/keamanan/sesi')->assertStatus(403);
        $this->get('/app/audit-log')->assertStatus(403);
    }

    /**
     * Test login web mencatat DeviceSession dan logout menandainya nonaktif.
     */
    public function test_login_and_logout_tracks_device_session(): void
    {
        // Login via POST /app/login
        $response = $this->post('/app/login', [
            'email' => 'superadmin@uteparts.id',
            'password' => 'password123',
        ], ['User-Agent' => 'TestBrowser/1.0']);

        $response->assertRedirect('/app/dashboard');

        $session = DeviceSession::where('user_id', $this->superAdmin->id)->latest()->first();
        $this->assertNotNull($session);
        $this->assertTrue($session->is_active);
        $this->assertStringContainsString('TestBrowser', $session->device_name);

        $deviceToken = session('device_token');
        $this->assertEquals($session->device_token, $deviceToken);

        // Logout
        $this->post('/logout');
        $session->refresh();
        $this->assertFalse($session->is_active);
    }

    /**
     * Test guardrail anti self-lockout pada SettingsRbac.
     */
    public function test_anti_self_lockout_guardrails(): void
    {
        $this->actingAs($this->superAdmin);
        session(['cabang_id' => $this->cabang->id]);

        // 1. Toggle nonaktif akun sendiri harus ditolak
        Livewire::test(SettingsRbac::class)
            ->call('toggleUserActive', $this->superAdmin->id)
            ->assertDispatched('alert');

        $this->superAdmin->refresh();
        $this->assertTrue($this->superAdmin->is_active);

        // 2. Uncheck is_active saat edit akun sendiri harus ditolak
        Livewire::test(SettingsRbac::class)
            ->call('openUserModal', $this->superAdmin->id)
            ->set('userForm.is_active', false)
            ->call('saveUser')
            ->assertDispatched('alert');

        $this->superAdmin->refresh();
        $this->assertTrue($this->superAdmin->is_active);

        // 3. Mengosongkan permission super-admin harus ditolak
        $role = Role::findByName('super-admin');
        Livewire::test(SettingsRbac::class)
            ->call('openEditRole', $role->id)
            ->set('editRolePermissions', [])
            ->call('saveEditRolePermissions')
            ->assertDispatched('alert');
    }

    /**
     * Test SessionManagementPage actions (force logout & logout all).
     */
    public function test_session_management_page_actions(): void
    {
        $this->actingAs($this->superAdmin);
        session(['cabang_id' => $this->cabang->id, 'device_token' => 'current-token-123']);

        DeviceSession::create([
            'user_id' => $this->superAdmin->id,
            'device_name' => 'Device Lain',
            'device_token' => 'other-token-456',
            'is_active' => true,
            'last_activity' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        DeviceSession::create([
            'user_id' => $this->superAdmin->id,
            'device_name' => 'Device Saat Ini',
            'device_token' => 'current-token-123',
            'is_active' => true,
            'last_activity' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        Livewire::test(SessionManagementPage::class)
            ->call('logoutDevice', 'other-token-456')
            ->assertDispatched('alert');

        $this->assertFalse(DeviceSession::where('device_token', 'other-token-456')->first()->is_active);

        // Test logout all (hanya device selain current-token-123)
        DeviceSession::where('device_token', 'other-token-456')->update(['is_active' => true]);

        Livewire::test(SessionManagementPage::class)
            ->call('logoutAll')
            ->assertDispatched('alert');

        $this->assertFalse(DeviceSession::where('device_token', 'other-token-456')->first()->is_active);
        $this->assertTrue(DeviceSession::where('device_token', 'current-token-123')->first()->is_active);
    }
}
