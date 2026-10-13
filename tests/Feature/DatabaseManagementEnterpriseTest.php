<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Livewire\DatabaseManagement;
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

    public function test_health_check_command_berjalan_sukses(): void
    {
        $this->artisan('ute:db-health-check')
            ->assertSuccessful();
    }
}
