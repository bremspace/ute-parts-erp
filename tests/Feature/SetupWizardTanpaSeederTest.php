<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Rbac\Livewire\SetupWizard;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SetupWizardTanpaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_membuat_role_sendiri_tanpa_seeder(): void
    {
        // Simulasi node client Docker: tidak ada RolesAndPermissionsSeeder.
        $this->assertFalse(Role::where('name', 'super-admin')->exists());

        $cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Client',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'Admin Lokal',
            'email' => 'admin@client.test',
            'password' => bcrypt('Password123!'),
            'is_active' => true,
        ]);

        session([
            'cabang_id' => $cabang->id,
            'cabang_nama' => $cabang->nama,
        ]);

        Livewire::actingAs($admin)
            ->test(SetupWizard::class)
            ->set('currentStep', 6)
            ->set('namaToko', 'Toko Client')
            ->set('namaCabang', 'Cabang Client')
            ->set('superAdminNama', 'Admin Lokal')
            ->set('superAdminEmail', 'admin@client.test')
            ->set('superAdminPassword', 'Password123!')
            ->set('superAdminPasswordConfirmation', 'Password123!')
            ->set('buatJurnalModal', false)
            ->call('selesaikanSetup')
            ->assertRedirect(route('pos'));

        $this->assertTrue(Role::where('name', 'super-admin')->exists());
        $this->assertTrue(Role::where('name', 'kasir')->exists());
        $this->assertTrue(Role::where('name', 'teknisi')->exists());
        $this->assertTrue(Permission::where('name', 'pengaturan.manage')->exists());

        $admin->refresh();
        $this->assertTrue($admin->hasRole('super-admin'));
        $this->assertTrue((bool) app(KonfigurasiService::class)->get('app_setup_completed'));
    }
}
