<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Livewire\AsetRegister;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AkuntingTabPermalinkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CB-TAB-01',
            'nama' => 'Cabang Uji Permalink',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'name' => 'Staf Finance Akunting',
            'email' => 'finance.tab@test.com',
        ]);
        $this->user->assignRole('admin-toko');
        $this->user->givePermissionTo(['akunting.view', 'akunting.create']);
        $this->user->cabangs()->attach($this->cabang->id);

        AkunCOA::create([
            'kode' => '110-01',
            'nama' => 'Kas',
            'tipe' => 'aset',
            'kelompok' => 'kas',
            'saldo_normal' => 'debit',
            'is_active' => true,
        ]);
    }

    public function test_akses_akunting_root_membuka_tab_laporan_default(): void
    {
        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);

        $response = $this->get('/app/akunting');
        $response->assertStatus(200);
        $response->assertSeeLivewire(AkuntingDashboard::class);

        Livewire::actingAs($this->user)
            ->test(AkuntingDashboard::class)
            ->assertSet('activeTab', 'laporan');
    }

    public function test_akses_permalink_tiap_tab_langsung_mengaktifkan_tab_bersangkutan(): void
    {
        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);

        $tabs = [
            'laporan',
            'jurnal',
            'buku-besar',
            'coa',
            'piutang',
            'utang',
            'matching-kas',
            'diagnosa-neraca',
            'tutup-buku',
            'neraca-saldo',
            'rekonsiliasi-bank',
        ];

        foreach ($tabs as $tab) {
            $response = $this->get("/app/akunting/{$tab}");
            $response->assertStatus(200);

            Livewire::actingAs($this->user)
                ->test(AkuntingDashboard::class, ['tab' => $tab])
                ->assertSet('activeTab', $tab);
        }
    }

    public function test_pilih_tab_mendispatch_event_tab_changed_dengan_url_permalink(): void
    {
        Livewire::actingAs($this->user)
            ->test(AkuntingDashboard::class)
            ->call('pilihTab', 'neraca-saldo')
            ->assertSet('activeTab', 'neraca-saldo')
            ->assertDispatched('tab-changed', fn ($event, $params) => $params['tab'] === 'neraca-saldo' && str_ends_with($params['url'], '/app/akunting/neraca-saldo'));
    }

    public function test_route_akunting_aset_tidak_tertimpa_oleh_tab_permalink(): void
    {
        $this->actingAs($this->user, 'web');
        session(['cabang_id' => $this->cabang->id]);

        $response = $this->get('/app/akunting/aset');
        $response->assertStatus(200);
        $response->assertSeeLivewire(AsetRegister::class);
    }
}
