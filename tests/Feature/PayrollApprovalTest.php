<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Services\PayrollService;
use App\Modules\Workflow\Models\ApprovalRule;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PayrollApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected PayrollService $payrollService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payrollService = app(PayrollService::class);
        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        if (! User::where('email', 'test@uteparts.test')->exists()) {
            User::create([
                'name' => 'Test User',
                'email' => 'test@uteparts.test',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);
        }

        // Set session cabang_id
        session(['cabang_id' => 1]);

        // Buat ApprovalRule untuk payroll secara langsung (tidak perlu seeder)
        ApprovalRule::updateOrCreate(
            ['entity_type' => 'payroll', 'level' => 1],
            [
                'cabang_id' => null,
                'min_amount' => 10000000,
                'max_amount' => null,
                'approver_role' => 'finance',
                'is_aktif' => true,
            ]
        );

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'kelola-hr']);
    }

    /** @test */
    public function test_ajukan_approval_memerlukan_approval_bila_melebihi_threshold(): void
    {
        $user = User::where('email', 'test@uteparts.test')->first();
        Karyawan::create([
            'user_id' => $user->id,
            'nama' => 'High Earner',
            'jabatan' => 'admin',
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => 15000000,
            'status_aktif' => true,
        ]);

        $result = $this->payrollService->ajukanApproval('2026-09', $user->id);

        $this->assertTrue($result['needs_approval']);
        $this->assertNotNull($result['approval_request_id']);
        $this->assertEquals(15000000, $result['total_gaji']);
    }

    /** @test */
    public function test_ajukan_approval_tidak_memerlukan_approval_bila_dibawah_threshold(): void
    {
        $user = User::where('email', 'test@uteparts.test')->first();
        Karyawan::create([
            'user_id' => $user->id,
            'nama' => 'Normal Earner',
            'jabatan' => 'admin',
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => 5000000,
            'status_aktif' => true,
        ]);

        $result = $this->payrollService->ajukanApproval('2026-09', $user->id);

        $this->assertFalse($result['needs_approval']);
        $this->assertNull($result['approval_request_id']);
        $this->assertEquals(5000000, $result['total_gaji']);
    }
}
