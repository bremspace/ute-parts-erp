<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hr\Livewire\PayrollSlipDetail;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Services\PayrollService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * [PRD §4.3] RBAC payroll: permission `kelola-payroll` (super-admin + finance);
 * admin-toko tidak boleh akses payroll; teknisi hanya lihat slip sendiri
 * (pembatasan user_id + cabang).
 */
class PayrollPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected PayrollService $payrollService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payrollService = app(PayrollService::class);

        $this->seed(CabangSeeder::class); // cabang 1 (pusat) & 2 (selatan)
        $this->seed(AkunCoaSeeder::class);

        // Seed RBAC penuh (roles + permissions) — super-admin all, finance kelola-payroll
        $this->seed(RolesAndPermissionsSeeder::class);

        session(['cabang_id' => 1]);

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

    /** @test */
    public function test_admin_finance_akses_halaman_payroll(): void
    {
        $superAdmin = $this->buatUser('super-admin', 'sa-payroll@test.com');
        $finance = $this->buatUser('finance', 'finance-payroll@test.com');

        // Super-admin (Permission::all) & finance (kelola-payroll eksplisit) → boleh buka halaman payroll
        foreach ([$superAdmin, $finance] as $user) {
            $this->actingAs($user, 'web')
                ->withSession(['cabang_id' => 1])
                ->get('/app/hr/payroll')
                ->assertOk();
        }
    }

    /** @test */
    public function test_admin_toko_tidak_bisa_akses_payroll(): void
    {
        $adminToko = $this->buatUser('admin-toko', 'admintoko-payroll@test.com');

        // Admin-toko hanya kelola-hr + hr.lihat-sendiri (tanpa payroll.view / kelola-payroll) → 403
        $this->actingAs($adminToko, 'web')
            ->withSession(['cabang_id' => 1])
            ->get('/app/hr/payroll')
            ->assertForbidden();
    }

    /** @test */
    public function test_teknisi_hanya_lihat_slip_sendiri(): void
    {
        $teknisi = $this->buatUser('teknisi', 'teknisi-payroll@test.com');
        $karyawanLain = $this->buatUser('teknisi', 'teknisi-lain@test.com');

        // Karyawan milik teknisi (cabang 1 = cabang session) dan karyawan lain (cabang 2)
        $karyawanA = Karyawan::create([
            'user_id' => $teknisi->id,
            'nama' => 'Teknisi Sendiri',
            'jabatan' => 'teknisi',
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => 5000000,
            'status_aktif' => true,
        ]);

        Karyawan::create([
            'user_id' => $karyawanLain->id,
            'nama' => 'Teknisi Lain',
            'jabatan' => 'teknisi',
            'cabang_id' => 2,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => 4500000,
            'status_aktif' => true,
        ]);

        $this->payrollService->hitungDraft('2026-09');
        $slipSendiri = PayrollSlip::where('karyawan_id', $karyawanA->id)->firstOrFail();
        $slipLain = PayrollSlip::where('karyawan_id', '!=', $karyawanA->id)->firstOrFail();

        // Teknisi → buka slip milik sendiri (user_id + cabang cocok) → 200
        $this->actingAs($teknisi, 'web')
            ->withSession(['cabang_id' => 1]);

        Livewire::test(PayrollSlipDetail::class)
            ->call('open', $slipSendiri->id)
            ->assertSet('showDetail', true)
            ->assertSet('slipId', $slipSendiri->id);

        // Teknisi → buka slip karyawan lain (user_id beda / cabang beda) → 403
        Livewire::test(PayrollSlipDetail::class)
            ->call('open', $slipLain->id)
            ->assertStatus(403);

        // Finance (kelola-payroll) tetap boleh lihat slip siapa pun (perilaku existing dipertahankan)
        $finance = $this->buatUser('finance', 'finance-slip@test.com');

        $this->actingAs($finance, 'web')
            ->withSession(['cabang_id' => 1]);

        Livewire::test(PayrollSlipDetail::class)
            ->call('open', $slipLain->id)
            ->assertSet('showDetail', true);
    }
}
