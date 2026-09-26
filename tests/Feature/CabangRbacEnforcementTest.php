<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CabangRbacEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabangPusat;

    protected Cabang $cabangSelatan;

    protected Cabang $cabangNonAktif;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabangPusat = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Pusat',
            'is_active' => true,
        ]);

        $this->cabangSelatan = Cabang::create([
            'kode' => 'CBG-02',
            'nama' => 'Cabang Selatan',
            'is_active' => true,
        ]);

        $this->cabangNonAktif = Cabang::create([
            'kode' => 'CBG-03',
            'nama' => 'Cabang Tutup',
            'is_active' => false,
        ]);

        foreach (['super-admin', 'finance', 'marketing', 'kelola-hr', 'admin-toko', 'kasir', 'teknisi', 'staff-gudang'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
    }

    public function test_super_admin_dan_role_hq_bisa_akses_seluruh_cabang_aktif(): void
    {
        foreach (['super-admin', 'finance', 'marketing', 'kelola-hr'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->assertTrue($user->bisaAksesCabang($this->cabangPusat->id), "{$role} harus bisa akses cabang aktif 1");
            $this->assertTrue($user->bisaAksesCabang($this->cabangSelatan->id), "{$role} harus bisa akses cabang aktif 2");
            $this->assertFalse($user->bisaAksesCabang($this->cabangNonAktif->id), "{$role} tidak boleh akses cabang nonaktif");
            $this->assertEquals(2, $user->daftarCabangAkses()->count(), "{$role} harus melihat 2 cabang aktif");
        }
    }

    public function test_role_operasional_dibatasi_hanya_cabang_yang_ditugaskan(): void
    {
        $kasir = User::factory()->create();
        $kasir->assignRole('kasir');

        // Tugaskan hanya ke Cabang Pusat
        $kasir->cabangs()->attach($this->cabangPusat->id, ['is_default' => true]);

        $this->assertTrue($kasir->bisaAksesCabang($this->cabangPusat->id));
        $this->assertFalse($kasir->bisaAksesCabang($this->cabangSelatan->id), 'Kasir tidak boleh akses cabang yang tidak ditugaskan');
        $this->assertFalse($kasir->bisaAksesCabang($this->cabangNonAktif->id));

        $daftar = $kasir->daftarCabangAkses();
        $this->assertCount(1, $daftar);
        $this->assertEquals($this->cabangPusat->id, $daftar->first()->id);
    }

    public function test_cabang_default_mengutamakan_flag_is_default(): void
    {
        $adminToko = User::factory()->create();
        $adminToko->assignRole('admin-toko');

        // Ditugaskan ke dua cabang, namun default di Cabang Selatan
        $adminToko->cabangs()->attach($this->cabangPusat->id, ['is_default' => false]);
        $adminToko->cabangs()->attach($this->cabangSelatan->id, ['is_default' => true]);

        $default = $adminToko->cabangDefault();
        $this->assertNotNull($default);
        $this->assertEquals($this->cabangSelatan->id, $default->id, 'Cabang default harus sesuai pivot is_default');
    }

    public function test_user_operasional_ditolak_saat_pindah_ke_cabang_tanpa_izin(): void
    {
        $kasir = User::factory()->create();
        $kasir->assignRole('kasir');
        $kasir->cabangs()->attach($this->cabangPusat->id, ['is_default' => true]);

        // Coba switch ke Cabang Selatan via web POST /app/pilih-cabang
        $response = $this->actingAs($kasir, 'web')->post('/app/pilih-cabang', [
            'cabang_id' => $this->cabangSelatan->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_bisa_switch_ke_semua_cabang_aktif(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $response = $this->actingAs($admin, 'web')->post('/app/pilih-cabang', [
            'cabang_id' => $this->cabangSelatan->id,
        ]);

        $response->assertRedirect();
        $this->assertEquals($this->cabangSelatan->id, session('cabang_id'));
    }

    public function test_middleware_auto_mengisi_cabang_default_jika_belum_ada_di_session(): void
    {
        $kasir = User::factory()->create();
        $kasir->assignRole('kasir');
        $kasir->cabangs()->attach($this->cabangPusat->id, ['is_default' => true]);

        // Masuk tanpa session cabang_id sebelumnya
        $response = $this->actingAs($kasir, 'web')->get('/app/dashboard');

        $response->assertOk();
        $this->assertEquals($this->cabangPusat->id, session('cabang_id'));
        $this->assertEquals($this->cabangPusat->nama, session('cabang_nama'));
    }
}
