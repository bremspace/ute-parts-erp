<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendRbacNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'CBG-TEST',
            'nama' => 'Cabang Test RBAC',
            'is_active' => true,
        ]);
    }

    public function test_kasir_hanya_melihat_menu_dan_action_yang_diizinkan(): void
    {
        $kasir = User::factory()->create();
        $kasir->cabangs()->attach($this->cabang->id);
        $kasir->assignRole('kasir');

        $response = $this->actingAs($kasir)
            ->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('dashboard'));

        $response->assertStatus(200);

        // Kasir boleh melihat link POS & Kasir
        $response->assertSee('/app/pos');
        $response->assertSee('Buka Kasir');

        // Kasir TIDAK boleh melihat menu Akunting, Omnichannel, Approvals, Tiket Servis di shortcut
        $response->assertDontSee('/app/akunting');
        $response->assertDontSee('/app/omnichannel');
        $response->assertDontSee('/app/approvals');
        $response->assertDontSee('Tiket Servis');
        $response->assertDontSee('Pusat Pengaturan');
    }

    public function test_teknisi_hanya_melihat_menu_servis_dan_tidak_melihat_pos_akunting(): void
    {
        $teknisi = User::factory()->create();
        $teknisi->cabangs()->attach($this->cabang->id);
        $teknisi->assignRole('teknisi');

        $response = $this->actingAs($teknisi)
            ->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('dashboard'));

        $response->assertStatus(200);

        // Teknisi boleh melihat Servis & shortcut Tiket Servis
        $response->assertSee('/app/servis');
        $response->assertSee('Tiket Servis');

        // Teknisi TIDAK boleh melihat Buka Kasir, Akunting, Omnichannel, Pengaturan
        $response->assertDontSee('Buka Kasir');
        $response->assertDontSee('/app/akunting');
        $response->assertDontSee('/app/omnichannel');
        $response->assertDontSee('Pusat Pengaturan');
    }

    public function test_staff_gudang_hanya_melihat_wms_dan_tidak_melihat_kasir_servis(): void
    {
        $gudang = User::factory()->create();
        $gudang->cabangs()->attach($this->cabang->id);
        $gudang->assignRole('staff-gudang');

        $response = $this->actingAs($gudang)
            ->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('dashboard'));

        $response->assertStatus(200);

        // Staff gudang boleh melihat WMS
        $response->assertSee('/app/wms');

        // Staff gudang TIDAK boleh melihat Kasir, Servis, Akunting, Omnichannel
        $response->assertDontSee('Buka Kasir');
        $response->assertDontSee('Tiket Servis');
        $response->assertDontSee('/app/akunting');
        $response->assertDontSee('/app/omnichannel');
        $response->assertDontSee('Pusat Pengaturan');
    }

    public function test_super_admin_melihat_seluruh_menu_dan_action(): void
    {
        $admin = User::factory()->create();
        $admin->cabangs()->attach($this->cabang->id);
        $admin->assignRole('super-admin');

        $response = $this->actingAs($admin)
            ->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('dashboard'));

        $response->assertStatus(200);

        // Super admin melihat semua modul utama
        $response->assertSee('Buka Kasir');
        $response->assertSee('Tiket Servis');
        $response->assertSee('/app/wms');
        $response->assertSee('/app/crm');
        $response->assertSee('/app/akunting');
        $response->assertSee('/app/omnichannel');
        $response->assertSee('/app/approvals');
        $response->assertSee('Pusat Pengaturan');
    }

    public function test_gating_action_button_wms_sesuai_permission(): void
    {
        // Kasir hanya punya wms.view (bukan wms.create / wms.transfer / wms.opname)
        $kasir = User::factory()->create();
        $kasir->cabangs()->attach($this->cabang->id);
        $kasir->assignRole('kasir');

        $responseKasir = $this->actingAs($kasir)
            ->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('wms'));

        $responseKasir->assertStatus(200);
        $responseKasir->assertDontSee('+ Tambah Produk');
        $responseKasir->assertDontSee('Import Excel');
        $responseKasir->assertDontSee('⚡ Auto ABC (WMS)');

        // Staff gudang punya wms.create
        $gudang = User::factory()->create();
        $gudang->cabangs()->attach($this->cabang->id);
        $gudang->assignRole('staff-gudang');

        $responseGudang = $this->actingAs($gudang)
            ->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('wms'));

        $responseGudang->assertStatus(200);
        $responseGudang->assertSee('+ Tambah Produk');
        $responseGudang->assertSee('Import Excel');
        $responseGudang->assertSee('⚡ Auto ABC (WMS)');
    }

    public function test_gating_action_button_crm_sesuai_permission(): void
    {
        // Staff gudang tidak punya akses CRM -> 403
        $gudang = User::factory()->create();
        $gudang->cabangs()->attach($this->cabang->id);
        $gudang->assignRole('staff-gudang');

        $this->actingAs($gudang)
            ->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('crm'))
            ->assertStatus(403);

        // Kasir tidak punya crm.view -> 403
        $kasir = User::factory()->create();
        $kasir->cabangs()->attach($this->cabang->id);
        $kasir->assignRole('kasir');

        $this->actingAs($kasir)
            ->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('crm'))
            ->assertStatus(403);

        // Marketing punya crm.view, crm.create, dan tier.manage
        $marketing = User::factory()->create();
        $marketing->cabangs()->attach($this->cabang->id);
        $marketing->assignRole('marketing');

        $responseMarketing = $this->actingAs($marketing)
            ->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('crm'));

        $responseMarketing->assertStatus(200);
        $responseMarketing->assertSee('+ Tambah Pelanggan');
        $responseMarketing->assertSee('+ Tambah Tier');
        $responseMarketing->assertSee('Rekalkulasi Tier');

        // Admin toko punya crm.view tapi TIDAK punya crm.create maupun tier.manage
        $adminToko = User::factory()->create();
        $adminToko->cabangs()->attach($this->cabang->id);
        $adminToko->assignRole('admin-toko');

        $responseAdminToko = $this->actingAs($adminToko)
            ->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('crm'));

        $responseAdminToko->assertStatus(200);
        $responseAdminToko->assertDontSee('+ Tambah Pelanggan');
        $responseAdminToko->assertDontSee('+ Tambah Tier');
        $responseAdminToko->assertDontSee('Rekalkulasi Tier');
    }

    public function test_gating_action_button_riwayat_transaksi_pos(): void
    {
        $kasir = User::factory()->create();
        $kasir->cabangs()->attach($this->cabang->id);
        $kasir->assignRole('kasir');

        $responseKasir = $this->actingAs($kasir)
            ->withSession(['active_cabang_id' => $this->cabang->id])
            ->get(route('pos.riwayat'));

        $responseKasir->assertStatus(200);
        $responseKasir->assertSee('Buka Kasir POS (F2)');
    }
}
