<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Services\PayrollService;
use App\Modules\Workflow\Models\ApprovalRequest;
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

    /** @test */
    public function test_finalisasi_tanpa_approval_ditolak(): void
    {
        $user = User::where('email', 'test@uteparts.test')->first();
        Karyawan::create([
            'user_id' => $user->id,
            'nama' => 'Gaji Besar',
            'jabatan' => 'admin',
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => 15000000,
            'status_aktif' => true,
        ]);

        $this->payrollService->hitungDraft('2026-09');

        try {
            $this->payrollService->finalisasiDisetujui('2026-09');
            $this->fail('Seharusnya DomainException saat belum ada approval F1-1');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('belum disetujui via F1-1', $e->getMessage());
        }

        // Periode tetap draft; belum ada jurnal — finalisasi gagal total
        $this->assertEquals(PayrollPeriode::STATUS_DRAFT, PayrollPeriode::where('periode', '2026-09')->value('status'));
        $this->assertSame(0, JurnalAkuntansi::where('no_jurnal', 'JRL-PR-2026-09')->count());
    }

    /** @test */
    public function test_finalisasi_setelah_approval_disetujui(): void
    {
        $user = User::where('email', 'test@uteparts.test')->first();
        Karyawan::create([
            'user_id' => $user->id,
            'nama' => 'Gaji Besar',
            'jabatan' => 'admin',
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => 15000000,
            'status_aktif' => true,
        ]);

        $this->payrollService->hitungDraft('2026-09');
        $periodeId = (int) PayrollPeriode::where('periode', '2026-09')->value('id');

        // Approval F1-1 sudah disetujui utk periode ini
        ApprovalRequest::create([
            'approval_rule_id' => ApprovalRule::where('entity_type', 'payroll')->first()->id,
            'entity_type' => 'payroll',
            'entity_id' => $periodeId,
            'cabang_id' => 1,
            'payload_json' => ['amount' => 15000000],
            'status' => 'disetujui',
            'requested_by' => $user->id,
            'approver_role' => 'finance',
        ]);

        $hasil = $this->payrollService->finalisasiDisetujui('2026-09');

        // Periode selesai (enum DB: draft/diproses/selesai/dibayar) + jurnal payroll
        // terposting + slip status approved
        $this->assertEquals('JRL-PR-2026-09', $hasil['no_jurnal']);
        $this->assertEquals(PayrollPeriode::STATUS_SELESAI, PayrollPeriode::where('periode', '2026-09')->value('status'));
        $this->assertGreaterThan(0, JurnalAkuntansi::where('no_jurnal', 'JRL-PR-2026-09')->count());
        $this->assertEquals('approved', PayrollSlip::where('payroll_periode_id', $periodeId)->first()->status);
    }
}
