<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Livewire\CrmDashboard;
use App\Modules\Crm\Livewire\LeadKanban;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CrmTabDanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Cabang CRM Test', 'kode' => 'CBG-CRM', 'is_active' => true]);

        $this->admin = User::create([
            'name' => 'Admin CRM Toko',
            'email' => 'admincrm@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('admin-toko');
        $this->admin->cabangs()->attach($this->cabang->id);

        $this->actingAs($this->admin, 'web');
        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_crm_dashboard_render_dan_switch_tab(): void
    {
        $tierGold = TierMembership::create([
            'nama' => 'Gold Member',
            'kode' => 'gold',
            'min_belanja_12bulan' => 5000000,
            'diskon_persen' => 5,
            'poin_multiplier' => 1.5,
            'urutan' => 2,
            'is_active' => true,
        ]);

        $pelanggan = Pelanggan::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Budi Pelanggan',
            'telepon' => '081299998888',
            'tier_membership_id' => $tierGold->id,
            'total_belanja_12bulan' => 6000000,
            'poin_loyalty' => 120,
        ]);

        // 1. Render default tab 'pelanggan'
        Livewire::test(CrmDashboard::class)
            ->assertSet('activeTab', 'pelanggan')
            ->assertSee('Budi Pelanggan')
            ->assertSee('081299998888')
            ->assertSee('Gold Member')
            ->call('switchTab', 'tier')
            ->assertSet('activeTab', 'tier')
            ->assertSee('Gold Member')
            ->assertSee('Min. Belanja 12 Bln:')
            ->assertSee('5.000.000')
            ->assertSee('1 orang'); // withCount pelanggan
    }

    public function test_lead_kanban_render_dengan_navigasi_crm(): void
    {
        Lead::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Prospek Lead Test',
            'telepon' => '089988776655',
            'stage' => 'baru',
            'sumber' => 'walkin',
        ]);

        Livewire::test(LeadKanban::class)
            ->assertSee('Prospek Lead Test')
            ->assertSee('Lead Pipeline')
            ->assertSee('Pelanggan')
            ->assertSee('Tier Loyalty');
    }
}
