<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Services\PayrollService;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-10a / P0-4 — Fail-closed: status bisnis TIDAK boleh final bila jurnal gagal.
 *
 * moduli Kas sesi (KasSesiState) sengaja TIDAK disentuh pada lane ini
 * (file ditandai lane lain) → dicatat di laporan, bukan diuji di sini.
 */
class JurnalFailClosedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::create([
            'name' => 'Operator B10a',
            'email' => 'operator-b10a@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach(1);
        session(['cabang_id' => 1]);
        $this->actingAs($this->user, 'web');
    }

    private function buatTiketSiapSelesai(): TiketServis
    {
        return TiketServis::create([
            'no_tiket' => 'SRV-B10A-0001',
            'cabang_id' => 1,
            'nama_pelanggan' => 'Pelanggan B10A',
            'jenis_hp' => 'iPhone 13',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Layar mati',
            'kondisi_fisik' => ['layar' => 'pecah'],
            'foto_unit' => [],
            'status' => 'qc',
            'sumber' => 'walk_in',
            'estimasi_biaya' => 150000,
        ]);
    }

    // =============================================================
    // Servis
    // =============================================================

    public function test_servis_jurnal_gagal_tidak_menghasilkan_status_selesai(): void
    {
        $tiket = $this->buatTiketSiapSelesai();

        // Hapus akun Kas → jurnal onSelesai pasti gagal
        AkunCOA::where('kode', '110-01')->delete();

        $gagal = false;
        try {
            app(ServisService::class)->updateStatus($tiket, 'selesai', $this->user, 'Selesai');
        } catch (\Throwable $e) {
            $gagal = true;
        }

        $this->assertTrue($gagal, 'Jurnal gagal harus dilemmas sebagai exception (fail-closed)');

        $tiket->refresh();
        $this->assertSame('qc', $tiket->status, 'Status tidak boleh final bila jurnal gagal');
        $this->assertNull($tiket->tanggal_selesai, 'tanggal_selesai tidak boleh terisi');
        $this->assertSame(0, JurnalAkuntansi::count(), 'Tidak boleh ada jurnal separuh');
    }

    public function test_servis_jurnal_berhasil_tetap_membuat_status_selesai(): void
    {
        $tiket = $this->buatTiketSiapSelesai();

        app(ServisService::class)->updateStatus($tiket, 'selesai', $this->user, 'Selesai');

        $this->assertSame('selesai', $tiket->fresh()->status);
        $this->assertSame(2, JurnalAkuntansi::where('sumber', 'servis')->count(), 'Jurnal servis 2 baris (jasa saja)');

        $kas = AkunCOA::where('kode', '110-01')->first();
        $pendapatan = AkunCOA::where('kode', '420-01')->first();
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $kas->id, 'debit' => 150000, 'kredit' => 0,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $pendapatan->id, 'debit' => 0, 'kredit' => 150000,
        ]);
    }

    // =============================================================
    // Payroll
    // =============================================================

    private function buatKaryawan(float $gaji = 5000000): Karyawan
    {
        return Karyawan::create([
            'user_id' => $this->user->id,
            'nama' => 'Karyawan B10A',
            'jabatan' => 'admin',
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => $gaji,
            'status_aktif' => true,
        ]);
    }

    public function test_payroll_finalisasi_jurnal_gagal_periode_tetap_draft(): void
    {
        $this->buatKaryawan(5000000);
        $service = app(PayrollService::class);
        $service->hitungDraft('2026-10');

        // Hapus akun Beban Gaji → jurnal payroll pasti gagal
        AkunCOA::where('kode', '520-01')->delete();

        $gagal = false;
        try {
            $service->finalisasiDisetujui('2026-10');
        } catch (\Throwable) {
            $gagal = true;
        }

        $this->assertTrue($gagal, 'Jurnal payroll gagal harus dilempar');
        $this->assertSame(
            PayrollPeriode::STATUS_DRAFT,
            PayrollPeriode::where('periode', '2026-10')->value('status'),
            'Periode tidak boleh final bila jurnal gagal'
        );
        $this->assertSame(0, JurnalAkuntansi::where('no_jurnal', 'JRL-PR-2026-10')->count());
        $this->assertSame(0, PayrollSlip::where('status', 'approved')->count());
    }

    public function test_payroll_bayar_jurnal_gagal_slip_tetap_approved(): void
    {
        $this->buatKaryawan(5000000);
        $service = app(PayrollService::class);
        $service->hitungDraft('2026-11');
        $service->approvePayroll('2026-11');

        $this->assertSame(1, PayrollSlip::where('status', 'approved')->count());

        // Hapus akun Kas → jurnal pembayaran payroll pasti gagal
        AkunCOA::where('kode', '110-01')->delete();

        $gagal = false;
        try {
            $service->bayarPayroll('2026-11');
        } catch (\Throwable) {
            $gagal = true;
        }

        $this->assertTrue($gagal, 'Jurnal pembayaran payroll gagal harus dilempar');
        $this->assertSame(1, PayrollSlip::where('status', 'approved')->count(), 'Slip tidak boleh jadi dibayar');
        $this->assertNotSame('dibayar', PayrollPeriode::where('periode', '2026-11')->value('status'));
        $this->assertSame(0, JurnalAkuntansi::where('no_jurnal', 'JRL-PR-BAYAR-2026-11')->count());
    }

    public function test_payroll_finalisasi_berhasil_periode_selesai(): void
    {
        $this->buatKaryawan(5000000);
        $service = app(PayrollService::class);
        $service->hitungDraft('2026-12');
        $service->finalisasiDisetujui('2026-12');

        $this->assertSame(PayrollPeriode::STATUS_SELESAI, PayrollPeriode::where('periode', '2026-12')->value('status'));
        $this->assertGreaterThan(0, JurnalAkuntansi::where('no_jurnal', 'JRL-PR-2026-12')->count());
    }
}
