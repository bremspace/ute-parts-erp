<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * [F3-8b] Livewire full-page component WAJIB punya tepat satu root element.
 * View yang memakai pola Blade penuh (@extends + @section) membuat
 * Livewire melempar MultipleRootElementsDetectedException saat render.
 *
 * Pola benar: view tanpa @extends (satu root <div>) plus
 * ->layout('layouts.backoffice', ...) pada render() komponen.
 *
 * Test ini meng-hitting route-nya sehingga exception multiple-root akan
 * muncul sebagai test failure, bukan hanya lolos kompilasi.
 */
class HrPageSingleRootTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function buatUser(string $role, string $email): User
    {
        $user = User::create([
            'name' => 'Test '.ucfirst($role),
            'email' => $email,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Halaman absensi, shift, KPI. Dulu gagal dengan
     * MultipleRootElementsDetectedException.
     *
     * @test
     */
    public function test_halaman_absensi_shift_kpi_render_tanpa_multiple_root(): void
    {
        $user = $this->buatUser('super-admin', 'sa-absensi-root@test.com');

        $this->actingAs($user, 'web')
            ->withSession(['cabang_id' => 1])
            ->get('/app/hr/absensi')
            ->assertOk();
    }

    /**
     * Halaman rule komisi multi-aktor, pola view yang sama.
     *
     * @test
     */
    public function test_halaman_komisi_skema_render_tanpa_multiple_root(): void
    {
        $user = $this->buatUser('super-admin', 'sa-komisi-root@test.com');

        $this->actingAs($user, 'web')
            ->withSession(['cabang_id' => 1])
            ->get('/app/hr/komisi-skema')
            ->assertOk();
    }

    /**
     * User tanpa record Karyawan. HrSayaPage punya dua cabang render dan
     * keduanya wajib dapat layout yang sama.
     *
     * @test
     */
    public function test_halaman_absensi_kpi_saya_render_tanpa_karyawan(): void
    {
        // Sengaja tidak membuat record Karyawan tertaut ke user ini.
        $user = $this->buatUser('super-admin', 'sa-saya-nullroot@test.com');

        $this->actingAs($user, 'web')
            ->withSession(['cabang_id' => 1])
            ->get('/app/hr/saya')
            ->assertOk();
    }
}
