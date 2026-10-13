<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Livewire\SettingsRbac;
use Database\Seeders\RoleUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsDatabaseTabIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $owner;

    protected User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_superadmin_dapat_melihat_dan_beralih_ke_tab_database(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(SettingsRbac::class)
            ->assertSeeHtml('💾 Basis Data & Backup')
            ->set('activeTab', 'database')
            ->assertSeeHtml('Manajemen Basis Data & Keselamatan Sistem');
    }

    public function test_owner_dapat_melihat_dan_beralih_ke_tab_database(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(SettingsRbac::class)
            ->assertSeeHtml('💾 Basis Data & Backup')
            ->set('activeTab', 'database')
            ->assertSeeHtml('Manajemen Basis Data & Keselamatan Sistem');
    }

    public function test_non_admin_owner_tidak_melihat_tab_database(): void
    {
        $this->actingAs($this->kasir);

        Livewire::test(SettingsRbac::class)
            ->assertDontSeeHtml('💾 Basis Data & Backup');
    }
}
