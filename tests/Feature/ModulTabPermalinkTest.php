<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Livewire\CrmDashboard;
use App\Modules\Hr\Livewire\HrAbsensiKpiPage;
use App\Modules\Hr\Livewire\PayrollPage;
use App\Modules\Marketplace\Livewire\CustomerAccount;
use App\Modules\Marketplace\Livewire\WebKonsumenSettings;
use App\Modules\Omnichannel\Livewire\OmnichannelCommandCenter;
use App\Modules\Pos\Livewire\RiwayatTransaksiIndex;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Livewire\ResellerDashboard;
use App\Modules\Wms\Livewire\ImportExportPage;
use App\Modules\Wms\Livewire\WmsDashboard;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ModulTabPermalinkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CB-TEST-MODUL',
            'nama' => 'Cabang Test Modul',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'name' => 'Super Administrator',
            'email' => 'superadmin.modul@test.com',
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);
    }

    public function test_wms_tab_permalink_resolution(): void
    {
        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->user)
            ->test(WmsDashboard::class, ['tab' => 'opname'])
            ->assertSet('activeTab', 'opname')
            ->call('pindahTab', 'po')
            ->assertSet('activeTab', 'po')
            ->assertDispatched('tab-changed', tab: 'po', url: url('/app/wms/po'));
    }

    public function test_crm_tab_permalink_resolution(): void
    {
        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->user)
            ->test(CrmDashboard::class, ['tab' => 'tier'])
            ->assertSet('activeTab', 'tier')
            ->call('switchTab', 'broadcast')
            ->assertSet('activeTab', 'broadcast')
            ->assertDispatched('tab-changed', tab: 'broadcast', url: url('/app/crm/broadcast'));
    }

    public function test_reseller_tab_permalink_resolution(): void
    {
        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->user)
            ->test(ResellerDashboard::class, ['tab' => 'komisi'])
            ->assertSet('activeTab', 'komisi')
            ->call('switchTab', 'skema')
            ->assertSet('activeTab', 'skema')
            ->assertDispatched('tab-changed', tab: 'skema', url: url('/app/reseller/skema'));
    }

    public function test_omnichannel_tab_permalink_resolution(): void
    {
        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->user)
            ->test(OmnichannelCommandCenter::class, ['tab' => 'orders'])
            ->assertSet('activeTab', 'orders')
            ->call('switchTab', 'returns')
            ->assertSet('activeTab', 'returns')
            ->assertDispatched('tab-changed', tab: 'returns', url: url('/app/omnichannel/returns'));
    }

    public function test_settings_rbac_tab_permalink_resolution(): void
    {
        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->user)
            ->test(SettingsRbac::class, ['tab' => 'role'])
            ->assertSet('activeTab', 'role')
            ->call('switchTab', 'loyalitas')
            ->assertSet('activeTab', 'loyalitas')
            ->assertDispatched('tab-changed', tab: 'loyalitas', url: url('/app/pengaturan/loyalitas'));
    }

    public function test_pos_riwayat_tab_permalink_resolution(): void
    {
        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->user)
            ->test(RiwayatTransaksiIndex::class, ['tab' => 'online'])
            ->assertSet('activeTab', 'online')
            ->call('switchTab', 'servis')
            ->assertSet('activeTab', 'servis')
            ->assertDispatched('tab-changed', tab: 'servis', url: url('/app/pos/riwayat/servis'));
    }

    public function test_hr_absensi_tab_permalink_resolution(): void
    {
        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->user)
            ->test(HrAbsensiKpiPage::class, ['tab' => 'kpi'])
            ->assertSet('tab', 'kpi')
            ->call('pilihTab', 'roster')
            ->assertSet('tab', 'roster')
            ->assertDispatched('tab-changed', tab: 'roster', url: url('/app/hr/absensi/roster'));
    }

    public function test_hr_payroll_tab_permalink_resolution(): void
    {
        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->user)
            ->test(PayrollPage::class, ['tab' => 'karyawan'])
            ->assertSet('activeTab', 'karyawan')
            ->call('setTab', 'payroll')
            ->assertSet('activeTab', 'payroll')
            ->assertDispatched('tab-changed', tab: 'payroll', url: url('/app/hr/payroll/payroll'));
    }

    public function test_wms_import_export_tab_permalink_resolution(): void
    {
        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->user)
            ->test(ImportExportPage::class, ['tab' => 'foto'])
            ->assertSet('activeTab', 'foto')
            ->call('setTab', 'export')
            ->assertSet('activeTab', 'export')
            ->assertDispatched('tab-changed', tab: 'export', url: url('/app/wms/import-export/export'));
    }

    public function test_web_konsumen_settings_tab_permalink_resolution(): void
    {
        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);

        Livewire::actingAs($this->user)
            ->test(WebKonsumenSettings::class, ['tab' => 'banner'])
            ->assertSet('activeTab', 'banner')
            ->call('setTab', 'kontak')
            ->assertSet('activeTab', 'kontak')
            ->assertDispatched('tab-changed', tab: 'kontak', url: url('/app/pengaturan/web-konsumen/kontak'));
    }

    public function test_customer_account_tab_permalink_resolution(): void
    {
        Livewire::test(CustomerAccount::class, ['tab' => 'membership'])
            ->assertSet('activeTab', 'membership')
            ->call('switchTab', 'profil')
            ->assertSet('activeTab', 'profil')
            ->assertDispatched('tab-changed', tab: 'profil', url: url('/account/profil'));
    }
}
