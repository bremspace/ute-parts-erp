<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Livewire\DatabaseManagement;
use App\Modules\Rbac\Services\AuditService;
use App\Modules\Rbac\Services\DatabaseManagementService;
use Database\Seeders\RoleUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseManagementEnterpriseTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $owner;

    protected User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        // Siapkan seeder role & user
        $this->seed(RoleUserSeeder::class);

        $this->superAdmin = User::where('email', 'superadmin@uteparts.id')->first()
            ?? User::factory()->create(['email' => 'superadmin@uteparts.id', 'password' => Hash::make('Password123!')]);
        if (! $this->superAdmin->hasRole('super-admin')) {
            $this->superAdmin->assignRole('super-admin');
        }

        $this->owner = User::where('email', 'owner@uteparts.id')->first()
            ?? User::factory()->create(['email' => 'owner@uteparts.id', 'password' => Hash::make('Password123!')]);
        if (! $this->owner->hasRole('owner')) {
            $this->owner->assignRole('owner');
        }

        $this->kasir = User::where('email', 'kasir@uteparts.id')->first()
            ?? User::factory()->create(['email' => 'kasir@uteparts.id', 'password' => Hash::make('Password123!')]);
        if (! $this->kasir->hasRole('kasir')) {
            $this->kasir->assignRole('kasir');
        }
    }

    public function test_kasir_dilarang_mengakses_database_management(): void
    {
        $this->actingAs($this->kasir);

        Livewire::test(DatabaseManagement::class)
            ->assertForbidden();
    }

    public function test_superadmin_bisa_mengakses_dan_membuat_backup_lokal(): void
    {
        $this->actingAs($this->superAdmin);

        $component = Livewire::test(DatabaseManagement::class)
            ->assertStatus(200)
            ->call('buatBackup')
            ->assertDispatched('alert');

        $service = app(DatabaseManagementService::class);
        $backups = $service->listBackups();

        $this->assertNotEmpty($backups);
        $this->assertFileExists($backups[0]['path']);

        // Bersihkan file test backup
        $service->deleteBackup($backups[0]['filename']);
    }

    public function test_safe_reset_mempertahankan_akun_dan_mereset_password(): void
    {
        $this->actingAs($this->superAdmin);

        // Ubah password user menjadi acak
        $this->kasir->update(['password' => Hash::make('PasswordBaru123!')]);

        $component = Livewire::test(DatabaseManagement::class)
            ->set('confirmResetText', 'SAYA SETUJU RESET DATA')
            ->set('confirmPassword', 'Password123!')
            ->call('eksekusiReset')
            ->assertDispatched('alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['type'] ?? '') === 'success';
            });

        // Verifikasi akun kasir tetap ada dan password kembali ke Password123!
        $kasirRefreshed = User::where('email', 'kasir@uteparts.id')->first();
        $this->assertNotNull($kasirRefreshed);
        $this->assertTrue(Hash::check('Password123!', $kasirRefreshed->password));

        // Bersihkan backup yang dibuat saat reset
        $service = app(DatabaseManagementService::class);
        $backups = $service->listBackups();
        foreach ($backups as $b) {
            $service->deleteBackup($b['filename']);
        }
    }

    public function test_transaksi_only_reset_with_new_confirmation_phrase(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(DatabaseManagement::class)
            ->set('resetMode', 'transaksi_only')
            ->set('confirmResetText', 'RESET TRANSAKSI')
            ->set('confirmPassword', 'Password123!')
            ->call('eksekusiReset')
            ->assertDispatched('alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['type'] ?? '') === 'success' && str_contains($payload['message'], 'Reset Data Transaksional');
            });

        // Bersihkan file test backup
        $service = app(DatabaseManagementService::class);
        foreach ($service->listBackups() as $b) {
            $service->deleteBackup($b['filename']);
        }
    }

    public function test_master_and_transaksi_reset(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(DatabaseManagement::class)
            ->set('resetMode', 'master_and_transaksi')
            ->set('confirmResetText', 'RESET MASTER DAN TRANSAKSI')
            ->set('confirmPassword', 'Password123!')
            ->call('eksekusiReset')
            ->assertDispatched('alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['type'] ?? '') === 'success' && str_contains($payload['message'], 'Reset Transaksi + Katalog');
            });

        $service = app(DatabaseManagementService::class);
        foreach ($service->listBackups() as $b) {
            $service->deleteBackup($b['filename']);
        }
    }

    public function test_factory_reset_restores_default_seeders(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(DatabaseManagement::class)
            ->set('resetMode', 'factory_reset')
            ->set('confirmResetText', 'RESET PABRIK')
            ->set('confirmPassword', 'Password123!')
            ->call('eksekusiReset')
            ->assertDispatched('alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['type'] ?? '') === 'success' && str_contains($payload['message'], 'Factory Reset');
            });

        // Verifikasi akun super admin dan owner dipulihkan oleh seeder
        $this->assertDatabaseHas('users', ['email' => 'superadmin@uteparts.id']);
        $this->assertDatabaseHas('users', ['email' => 'owner@uteparts.id']);

        // Verifikasi tier membership bawaan sistem tetap ada dan kembali ke setting default
        $this->assertDatabaseHas('tier_memberships', ['kode' => 'silver', 'diskon_persen' => 3]);
        $this->assertDatabaseHas('tier_memberships', ['kode' => 'gold', 'diskon_persen' => 5]);
        $this->assertDatabaseHas('tier_memberships', ['kode' => 'platinum', 'diskon_persen' => 10]);

        $service = app(DatabaseManagementService::class);
        foreach ($service->listBackups() as $b) {
            $service->deleteBackup($b['filename']);
        }
    }

    public function test_full_wipe_maintains_anti_lockout_superadmin(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(DatabaseManagement::class)
            ->set('resetMode', 'full_wipe')
            ->set('confirmResetText', 'FULL WIPE DATABASE')
            ->set('confirmPassword', 'Password123!')
            ->call('eksekusiReset')
            ->assertDispatched('alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['type'] ?? '') === 'success' && str_contains($payload['message'], 'Full Wipe');
            });

        // Anti-lockout: Akun super admin tetap ada dan memiliki role super-admin
        $admin = User::where('email', 'superadmin@uteparts.id')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('super-admin'));

        $service = app(DatabaseManagementService::class);
        foreach ($service->listBackups() as $b) {
            $service->deleteBackup($b['filename']);
        }
    }

    public function test_audit_service_and_reset_graceful_when_session_cabang_id_invalid(): void
    {
        $this->actingAs($this->superAdmin);

        // Pasang session cabang_id yang tidak ada di database (simulasi cabang telah dihapus / invalid)
        session(['cabang_id' => 999999]);

        // 1. AuditService harus mencatat log tanpa memicu Integrity constraint violation
        $auditService = app(AuditService::class);
        $log = $auditService->catat('TestEntity', 'create', 1, 'Uji coba audit saat cabang_id basi');
        $this->assertNull($log->cabang_id);

        // 2. Eksekusi reset data harus tetap berjalan lancar tanpa error 1452
        Livewire::test(DatabaseManagement::class)
            ->set('resetMode', 'transaksi_only')
            ->set('confirmResetText', 'RESET TRANSAKSI')
            ->set('confirmPassword', 'Password123!')
            ->call('eksekusiReset')
            ->assertDispatched('alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['type'] ?? '') === 'success';
            });

        $service = app(DatabaseManagementService::class);
        foreach ($service->listBackups() as $b) {
            $service->deleteBackup($b['filename']);
        }
    }

    public function test_health_check_command_berjalan_sukses(): void
    {
        $this->artisan('ute:db-health-check')
            ->assertSuccessful();
    }
}
