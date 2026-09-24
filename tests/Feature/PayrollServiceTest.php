<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hr\Models\AbsensiLog;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KaryawanKomponenGaji;
use App\Modules\Hr\Models\KomisiTeknisiRule;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Services\PayrollService;
use App\Modules\Reseller\Models\KomisiSkema;
use App\Modules\Reseller\Services\KomisiService;
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

    /** @test */
    public function test_komisi_teknisi_tidak_ganda_saat_engine_aktif(): void
    {
        $user = User::where('email', 'test@uteparts.test')->first();
        $karyawan = Karyawan::create([
            'user_id' => $user->id,
            'nama' => 'Teknisi Engine',
            'jabatan' => 'teknisi',
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => 5000000,
            'status_aktif' => true,
        ]);

        // Legacy rule teknisi (Rp50.000/tiket)
        KomisiTeknisiRule::create([
            'jabatan_target' => 'teknisi',
            'jenis' => 'per_tiket',
            'nominal' => 50000,
            'min_status_tiket' => 'selesai',
            'is_aktif' => true,
        ]);

        $tiket = TiketServis::create([
            'no_tiket' => 'TS-TEST-ENGINE',
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

        // Rule engine komisi multi-aktor (tiket_servis, karyawan) — Rp10.000
        KomisiSkema::create([
            'nama' => 'Teknisi tiket servis 10000',
            'aktor_tipe' => 'karyawan',
            'aktor_id' => null,
            'trigger_tipe' => 'tiket_servis',
            'kategori' => null,
            'tipe' => 'nominal',
            'nilai' => 10000,
            'min_amount' => 0,
            'cabang_id' => null,
            'is_aktif' => true,
        ]);

        // Engine mencatat komisi utk tiket ini (status pending)
        app(KomisiService::class)->hitungKomisiMultiAktor('tiket_servis', ['tiket_servis_id' => $tiket->id]);

        $this->payrollService->hitungDraft('2026-09');

        $slip = PayrollSlip::first();
        $this->assertNotNull($slip);
        // Engine menang: 10000 — bukan 60000 (legacy 50000 + engine 10000)
        $this->assertEquals(10000, $slip->total_komisi);
        $this->assertEquals(5000000 + 10000, $slip->total_gaji);
    }

    /** @test */
    public function test_potongan_absen_sesuai_threshold_dan_optin_cabang(): void
    {
        $user = User::where('email', 'test@uteparts.test')->first();
        $karyawan = Karyawan::create([
            'user_id' => $user->id,
            'nama' => 'Sering Absen',
            'jabatan' => 'admin',
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => 4500000,
            'status_aktif' => true,
        ]);

        // 5 hari absen tanpa izin dalam periode 2026-09
        foreach (['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-05', '2026-09-08'] as $tanggal) {
            AbsensiLog::create([
                'karyawan_id' => $karyawan->id,
                'tanggal' => $tanggal,
                'status' => AbsensiLog::STATUS_ABSEN,
                'catatan' => 'Tanpa keterangan',
            ]);
        }

        // Cabang 1 opt-in potongan absen
        config([
            'hr.potongan_absen.cabang_aktif' => [1],
            'hr.potongan_absen.threshold' => 3,
            'hr.potongan_absen.nominal_per_hari' => 25000,
        ]);

        $this->payrollService->hitungDraft('2026-09');
        $slip = PayrollSlip::first();
        $this->assertNotNull($slip);

        // (5 - 3) x 25000 = 50000
        $harapan = (5 - 3) * 25000;
        $this->assertEquals($harapan, $slip->total_potongan);
        // rincian tersimpan double-encoded (quirk lama) — decode seperti PayrollPage
        $rincian = json_decode($slip->rincian, true);
        $this->assertIsArray($rincian);
        $this->assertEquals($harapan, $rincian['potongan_absen']);

        // Kasus cabang TIDAK opt-in → potongan absen 0
        config(['hr.potongan_absen.cabang_aktif' => []]);
        $this->payrollService->hitungDraft('2026-09');
        $this->assertEquals(0.0, (float) $slip->fresh()->total_potongan);
        $rincianNonaktif = json_decode($slip->fresh()->rincian, true);
        $this->assertEquals(0.0, (float) $rincianNonaktif['potongan_absen']);
    }
}
