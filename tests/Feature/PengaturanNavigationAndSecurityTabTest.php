<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Rbac\Livewire\SettingsRbac;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PengaturanNavigationAndSecurityTabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);
    }

    public function test_sidebar_has_web_konsumen_link_and_pengaturan_renders_properly(): void
    {
        $cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Pusat',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@uteparts.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('super-admin');
        $admin->cabangs()->attach($cabang->id, ['is_default' => true]);

        app(KonfigurasiService::class)->set('app_setup_completed', true);

        $response = $this->actingAs($admin)
            ->withSession(['cabang_id' => $cabang->id, 'cabang_nama' => $cabang->nama])
            ->get(route('pengaturan'));

        $response->assertStatus(200);
        $response->assertSee('Toko &amp; Web Konsumen', false);
        $response->assertSee(route('pengaturan.web-konsumen'));
        $response->assertSee('Keamanan &amp; Sesi', false);
    }

    public function test_tab_keamanan_in_settings_rbac(): void
    {
        $cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Pusat',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin2@uteparts.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('super-admin');
        $admin->cabangs()->attach($cabang->id, ['is_default' => true]);

        app(KonfigurasiService::class)->set('app_setup_completed', true);

        session(['cabang_id' => $cabang->id, 'cabang_nama' => $cabang->nama]);

        Livewire::actingAs($admin)
            ->withQueryParams(['tab' => 'keamanan'])
            ->test(SettingsRbac::class)
            ->assertSet('activeTab', 'keamanan')
            ->assertSee('Pusat Keamanan Akun & Sesi Perangkat')
            ->assertSee('Verifikasi Dua Faktor (2FA)')
            ->assertSee('Sesi Perangkat & Browser');
    }

    public function test_uncompleted_setup_cannot_be_skipped_by_unauthorized_user(): void
    {
        $cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Pusat',
            'is_active' => true,
        ]);

        $kasir = User::create([
            'name' => 'Kasir Staff',
            'email' => 'kasir@uteparts.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $kasir->assignRole('kasir');
        $kasir->cabangs()->attach($cabang->id, ['is_default' => true]);

        app(KonfigurasiService::class)->set('app_setup_completed', false);

        $response = $this->actingAs($kasir)
            ->withSession(['cabang_id' => $cabang->id, 'cabang_nama' => $cabang->nama])
            ->get('/app/pos');

        // Unauthorized user is logged out and redirected to login with notice
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_uncompleted_setup_redirects_admin_even_from_keamanan_route(): void
    {
        $cabang = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Pusat',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin3@uteparts.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('super-admin');
        $admin->cabangs()->attach($cabang->id, ['is_default' => true]);

        app(KonfigurasiService::class)->set('app_setup_completed', false);

        $response = $this->actingAs($admin)
            ->withSession(['cabang_id' => $cabang->id, 'cabang_nama' => $cabang->nama])
            ->get(route('keamanan.sesi'));

        $response->assertRedirect(route('setup.wizard'));
    }
}
