<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KaryawanKomponenGaji;
use App\Modules\Hr\Models\KomisiTeknisiRule;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Services\PayrollService;
use App\Modules\Servis\Models\TiketServis;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PayrollService $payrollService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payrollService = app(PayrollService::class);

        // Seed cabang dan COA
        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        // Buat user untuk karyawan
        if (! User::where('email', 'test@uteparts.test')->exists()) {
            User::create([
                'name' => 'Test User',
                'email' => 'test@uteparts.test',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);
        }

        // Clear permission cache dan buat permission
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'kelola-hr']);
    }

    /** @test */
    public function test_hitung_draft_menghitung_gaji_dengan_benar(): void
    {
        $user = User::where('email', 'test@uteparts.test')->first();
        $karyawan = Karyawan::create([
            'user_id' => $user->id,
            'nama' => 'Test Karyawan',
            'jabatan' => 'teknisi',
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => 5000000,
            'status_aktif' => true,
        ]);

        KaryawanKomponenGaji::create([
            'karyawan_id' => $karyawan->id,
            'tipe' => 'tunjangan',
            'nama' => 'Tunjangan Transport',
            'nominal_bulanan' => 1000000,
            'is_aktif' => true,
        ]);

        KaryawanKomponenGaji::create([
            'karyawan_id' => $karyawan->id,
            'tipe' => 'potongan',
            'nama' => 'Potongan BPJS',
            'nominal_bulanan' => 500000,
            'is_aktif' => true,
        ]);

        KomisiTeknisiRule::create([
            'jabatan_target' => 'teknisi',
            'jenis' => 'per_tiket',
            'nominal' => 50000,
            'min_status_tiket' => 'selesai',
            'is_aktif' => true,
        ]);

        TiketServis::create([
            'no_tiket' => 'TS-TEST-001',
            'cabang_id' => 1,
            'teknisi_id' => $user->id,
            'jenis_hp' => 'iPhone 14',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Kerusakan layar',
            'kondisi_fisik' => ['kerusakan' => 'layar retak'],
            'foto_unit' => [],
            'status' => 'selesai',
            'sumber' => 'walk_in',
            'estimasi_biaya' => 100000,
            'tanggal_selesai' => now()->format('Y-m-d'),
        ]);

        $result = $this->payrollService->hitungDraft('2026-09');

        $this->assertEquals(1, $result['karyawan_diproses']);
        $this->assertGreaterThan(0, $result['total_gaji']);

        $slip = PayrollSlip::first();
        $this->assertNotNull($slip);
        $this->assertEquals(5000000, $slip->gaji_pokok);
        $this->assertEquals(1000000, $slip->total_tunjangan);
        $this->assertEquals(500000, $slip->total_potongan);
        $this->assertEquals(50000, $slip->total_komisi);
        // total = 5000000 + 1000000 - 500000 + 50000 = 5550000
        $this->assertEquals(5550000, $slip->total_gaji);
    }

    /** @test */
    public function test_hitung_draft_idempotent(): void
    {
        $user = User::where('email', 'test@uteparts.test')->first();
        $karyawan = Karyawan::create([
            'user_id' => $user->id,
            'nama' => 'Test Karyawan',
            'jabatan' => 'admin',
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => 4500000,
            'status_aktif' => true,
        ]);

        $this->payrollService->hitungDraft('2026-09');
        $result1 = $this->payrollService->hitungDraft('2026-09');

        $this->assertEquals($result1['total_gaji'], PayrollSlip::sum('total_gaji'));
        $this->assertEquals(1, PayrollSlip::count());
    }

    /** @test */
    public function test_periode_dibuat_idempoten(): void
    {
        $this->payrollService->hitungDraft('2026-09');
        $this->payrollService->hitungDraft('2026-09');

        $this->assertEquals(1, PayrollPeriode::where('periode', '2026-09')->count());
    }

    /** @test */
    public function test_karyawan_non_teknisi_tanpa_komisi(): void
    {
        $user = User::where('email', 'test@uteparts.test')->first();
        Karyawan::create([
            'user_id' => $user->id,
            'nama' => 'Admin Only',
            'jabatan' => 'admin',
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => 4500000,
            'status_aktif' => true,
        ]);

        $result = $this->payrollService->hitungDraft('2026-09');
        $slip = PayrollSlip::first();

        $this->assertEquals(4500000, $slip->total_gaji);
        $this->assertEquals(0, $slip->total_komisi);
    }
}
