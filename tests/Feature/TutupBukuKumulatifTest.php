<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\TutupBukuService;
use App\Modules\Rbac\Models\Cabang;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * [C-1] Tutup buku harus memindahkan SALDO KUMULATIF akun nominal (pendapatan &
 * beban) ke Laba Ditahan — bukan hanya mutasi bulan berjalan — dan mengendalikan
 * urutan periode. Setelah tutup: akun nominal nol, laba ditahan benar, neraca
 * tetap balance. Bulan terlewat ditangani tanpa residual / transfer ganda.
 */
class TutupBukuKumulatifTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $superAdmin;

    private JurnalService $jurnalService;

    private TutupBukuService $tutupBukuService;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-15 10:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seedCoa();

        $this->cabang = Cabang::create([
            'kode' => 'CB-TBK',
            'nama' => 'Cabang Test Tutup Buku Kumulatif',
            'is_active' => true,
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin TBK',
            'email' => 'admin-tbk@test.com',
        ]);
        $this->superAdmin->assignRole('super-admin');
        $this->superAdmin->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->superAdmin, 'web');
        $this->jurnalService = app(JurnalService::class);
        $this->tutupBukuService = app(TutupBukuService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedCoa(): void
    {
        $accounts = [
            ['kode' => '110-01', 'nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
            ['kode' => '310-01', 'nama' => 'Modal Pemilik', 'tipe' => 'ekuitas', 'kelompok' => 'modal', 'saldo_normal' => 'kredit'],
            ['kode' => '310-02', 'nama' => 'Laba Ditahan', 'tipe' => 'ekuitas', 'kelompok' => 'laba_ditahan', 'saldo_normal' => 'kredit'],
            ['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
        ];

        foreach ($accounts as $a) {
            AkunCOA::create(array_merge($a, ['is_active' => true]));
        }
    }

    private function postJurnal(string $noJurnal, Carbon $tanggal, array $lines): void
    {
        $this->jurnalService->post(
            noJurnal: $noJurnal,
            tanggal: $tanggal,
            sumber: 'manual',
            lines: $lines,
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );
    }

    /** Saldo buku besar (bertanda sesuai saldo_normal) s/d tanggal tertentu. */
    private function saldoAkun(string $kode, string $sampai): float
    {
        $akun = AkunCOA::where('kode', $kode)->firstOrFail();
        $rows = JurnalAkuntansi::where('akun_coa_id', $akun->id)
            ->where('cabang_id', $this->cabang->id)
            ->whereDate('tanggal', '<=', $sampai)
            ->get();
        $saldo = $akun->saldo_normal === 'debit'
            ? (float) $rows->sum('debit') - (float) $rows->sum('kredit')
            : (float) $rows->sum('kredit') - (float) $rows->sum('debit');

        return round($saldo, 2);
    }

    private function assertNominalNol(string $sampai): void
    {
        foreach (['410-01', '510-02'] as $kode) {
            $this->assertSame(0.0, $this->saldoAkun($kode, $sampai), "Akun nominal {$kode} harus nol setelah tutup buku s/d {$sampai}.");
        }
    }

    private function assertNeracaBalance(string $sampai): void
    {
        $neraca = app(ExportLaporanService::class)->neracaSaldo($this->cabang->id, $sampai);
        $this->assertTrue($neraca['balance'], 'Neraca harus balance setelah tutup buku.');
        $this->assertSame(0.0, round((float) $neraca['selisih'], 2));
        $this->assertSame(0.0, round((float) $neraca['laba_periode_berjalan'], 2), 'Laba periode berjalan harus 0 setelah akun nominal ditutup.');
    }

    private function linesPenutup(string $noJurnal): Collection
    {
        return JurnalAkuntansi::where('no_jurnal', $noJurnal)
            ->where('cabang_id', $this->cabang->id)
            ->with('akun')
            ->get();
    }

    // =====================================================================
    // C-1: saldo kumulatif, bukan mutasi bulan
    // =====================================================================

    public function test_tutup_buku_memindahkan_saldo_kumulatif_bukan_hanya_mutasi_bulan(): void
    {
        // Oktober bergerak tapi TIDAK ditutup (bulan terlewat, tanpa baris periode).
        $this->postJurnal('JRL-KUM-01', Carbon::create(2026, 10, 10), [
            ['akun_kode' => '110-01', 'debit' => 500000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 500000],
        ]);
        $this->postJurnal('JRL-KUM-02', Carbon::create(2026, 10, 12), [
            ['akun_kode' => '510-02', 'debit' => 200000, 'kredit' => 0],
            ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => 200000],
        ]);
        // November bergerak.
        $this->postJurnal('JRL-KUM-03', Carbon::create(2026, 11, 10), [
            ['akun_kode' => '110-01', 'debit' => 300000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 300000],
        ]);
        $this->postJurnal('JRL-KUM-04', Carbon::create(2026, 11, 12), [
            ['akun_kode' => '510-02', 'debit' => 100000, 'kredit' => 0],
            ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => 100000],
        ]);

        // Tutup November langsung: wajib menyapu residual Oktober + November.
        $periode = $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 11, $this->superAdmin->id);
        $this->assertTrue($periode->isClosed());
        $this->assertNotNull($periode->no_jurnal_penutup);

        $lines = $this->linesPenutup($periode->no_jurnal_penutup);
        $this->assertEquals(800000.0, (float) $lines->firstWhere('akun.kode', '410-01')->debit);
        $this->assertEquals(300000.0, (float) $lines->firstWhere('akun.kode', '510-02')->kredit);
        $this->assertEquals(500000.0, (float) $lines->firstWhere('akun.kode', '310-02')->kredit);
        $this->assertEquals($lines->sum('debit'), $lines->sum('kredit'));

        $this->assertNominalNol('2026-11-30');
        $this->assertSame(500000.0, $this->saldoAkun('310-02', '2026-11-30'));
        $this->assertSame(500000.0, $this->saldoAkun('110-01', '2026-11-30'));
        $this->assertNeracaBalance('2026-11-30');
    }

    public function test_tutup_buku_bulan_tanpa_mutasi_tetap_menutup_saldo_residual(): void
    {
        // Hanya Oktober bergerak; November kosong. Tutup November tidak boleh
        // mengunci periode tanpa ayat penutup sementara saldo nominal ada.
        $this->postJurnal('JRL-RES-01', Carbon::create(2026, 10, 5), [
            ['akun_kode' => '110-01', 'debit' => 400000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 400000],
        ]);

        $praCek = $this->tutupBukuService->praCek($this->cabang->id, 2026, 11);
        $this->assertTrue($praCek['siap_tutup']);
        $this->assertSame(400000.0, $praCek['total_pendapatan']);
        $this->assertNotEmpty($praCek['baris_jurnal_rencana']);

        $periode = $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 11, $this->superAdmin->id);
        $this->assertNotNull($periode->no_jurnal_penutup, 'Bulan tanpa mutasi tetap butuh ayat penutup bila ada saldo kumulatif nominal.');

        $this->assertNominalNol('2026-11-30');
        $this->assertSame(400000.0, $this->saldoAkun('310-02', '2026-11-30'));
        $this->assertNeracaBalance('2026-11-30');
    }

    public function test_tutup_buku_berurutan_dua_periode_saldo_nominal_nol_laba_ditahan_benar(): void
    {
        $this->postJurnal('JRL-SEQ-01', Carbon::create(2026, 10, 10), [
            ['akun_kode' => '110-01', 'debit' => 500000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 500000],
        ]);
        $this->postJurnal('JRL-SEQ-02', Carbon::create(2026, 10, 12), [
            ['akun_kode' => '510-02', 'debit' => 200000, 'kredit' => 0],
            ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => 200000],
        ]);
        $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 10, $this->superAdmin->id);
        $this->assertNominalNol('2026-10-31');
        $this->assertSame(300000.0, $this->saldoAkun('310-02', '2026-10-31'));
        $this->assertNeracaBalance('2026-10-31');

        $this->postJurnal('JRL-SEQ-03', Carbon::create(2026, 11, 10), [
            ['akun_kode' => '110-01', 'debit' => 300000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 300000],
        ]);
        $this->postJurnal('JRL-SEQ-04', Carbon::create(2026, 11, 12), [
            ['akun_kode' => '510-02', 'debit' => 100000, 'kredit' => 0],
            ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => 100000],
        ]);

        // Penutup November hanya memindahkan saldo buku November (Oktober sudah nol).
        $praCek = $this->tutupBukuService->praCek($this->cabang->id, 2026, 11);
        $this->assertSame(300000.0, $praCek['total_pendapatan']);
        $this->assertSame(100000.0, $praCek['total_beban']);
        $this->assertSame(200000.0, $praCek['laba_rugi_bersih']);

        $periode = $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 11, $this->superAdmin->id);
        $lines = $this->linesPenutup($periode->no_jurnal_penutup);
        $this->assertEquals(300000.0, (float) $lines->firstWhere('akun.kode', '410-01')->debit);
        $this->assertEquals(100000.0, (float) $lines->firstWhere('akun.kode', '510-02')->kredit);
        $this->assertEquals(200000.0, (float) $lines->firstWhere('akun.kode', '310-02')->kredit);

        $this->assertNominalNol('2026-11-30');
        $this->assertSame(500000.0, $this->saldoAkun('310-02', '2026-11-30'));
        $this->assertNeracaBalance('2026-11-30');
    }

    public function test_tutup_buku_rugi_kumulatif_mendebit_laba_ditahan_dan_neraca_balance(): void
    {
        $this->postJurnal('JRL-LOSS-01', Carbon::create(2026, 10, 5), [
            ['akun_kode' => '510-02', 'debit' => 500000, 'kredit' => 0],
            ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => 500000],
        ]);
        $this->postJurnal('JRL-LOSS-02', Carbon::create(2026, 10, 6), [
            ['akun_kode' => '110-01', 'debit' => 100000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 100000],
        ]);

        $periode = $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 10, $this->superAdmin->id);
        $lines = $this->linesPenutup($periode->no_jurnal_penutup);
        $this->assertEquals(400000.0, (float) $lines->firstWhere('akun.kode', '310-02')->debit);
        $this->assertEquals($lines->sum('debit'), $lines->sum('kredit'));

        $this->assertNominalNol('2026-10-31');
        $this->assertSame(-400000.0, $this->saldoAkun('310-02', '2026-10-31'));
        $this->assertNeracaBalance('2026-10-31');
    }

    // =====================================================================
    // C-1: urutan periode
    // =====================================================================

    public function test_tutup_buku_menolak_bila_periode_sebelumnya_masih_terbuka(): void
    {
        // Oktober terdaftar sebagai periode open; November tidak boleh ditutup dulu.
        $this->tutupBukuService->getPeriode($this->cabang->id, 2026, 10);
        $this->postJurnal('JRL-ORD-01', Carbon::create(2026, 11, 10), [
            ['akun_kode' => '110-01', 'debit' => 100000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 100000],
        ]);

        $praCek = $this->tutupBukuService->praCek($this->cabang->id, 2026, 11);
        $this->assertFalse($praCek['siap_tutup']);
        $this->assertStringContainsString('berurutan', $praCek['pesan']);

        try {
            $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 11, $this->superAdmin->id);
            $this->fail('Tutup buku November seharusnya ditolak selama Oktober masih open.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('berurutan', $e->getMessage());
        }

        $this->assertTrue($this->tutupBukuService->getPeriode($this->cabang->id, 2026, 11)->isOpen());
        $this->assertSame(0, JurnalHeader::where('cabang_key', $this->cabang->id)->where('sumber', 'tutup_buku')->count());

        // Setelah Oktober ditutup (bulan kosong), November boleh ditutup.
        $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 10, $this->superAdmin->id);
        $periode = $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 11, $this->superAdmin->id);
        $this->assertTrue($periode->isClosed());
        $this->assertNominalNol('2026-11-30');
        $this->assertSame(100000.0, $this->saldoAkun('310-02', '2026-11-30'));
        $this->assertNeracaBalance('2026-11-30');
    }

    public function test_tutup_mundur_ditolak_bila_saldo_sudah_tersapu_penutup_berikutnya(): void
    {
        // Oktober terlewat (tanpa baris periode), November menutup menyapu keduanya.
        $this->postJurnal('JRL-MUNDUR-01', Carbon::create(2026, 10, 10), [
            ['akun_kode' => '110-01', 'debit' => 500000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 500000],
        ]);
        $this->postJurnal('JRL-MUNDUR-02', Carbon::create(2026, 10, 12), [
            ['akun_kode' => '510-02', 'debit' => 200000, 'kredit' => 0],
            ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => 200000],
        ]);
        $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 11, $this->superAdmin->id);
        $this->assertSame(300000.0, $this->saldoAkun('310-02', '2026-11-30'));

        // Menutup Oktober setelah November closed = transfer ganda ke Laba Ditahan → wajib ditolak.
        $this->tutupBukuService->getPeriode($this->cabang->id, 2026, 10);
        try {
            $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 10, $this->superAdmin->id);
            $this->fail('Tutup mundur Oktober seharusnya ditolak: saldonya sudah tersapu penutup November.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('transfer ganda', $e->getMessage());
        }

        $this->assertTrue($this->tutupBukuService->getPeriode($this->cabang->id, 2026, 10)->isOpen());
        $this->assertSame(1, JurnalHeader::where('cabang_key', $this->cabang->id)->where('sumber', 'tutup_buku')->count());
        $this->assertSame(300000.0, $this->saldoAkun('310-02', '2026-11-30'));
        $this->assertNeracaBalance('2026-11-30');
    }

    public function test_reopen_periode_lalu_tutup_ulang_tetap_benar_meski_periode_berikutnya_closed(): void
    {
        // Oktober & November ditutup berurutan.
        $this->postJurnal('JRL-RCL-01', Carbon::create(2026, 10, 10), [
            ['akun_kode' => '110-01', 'debit' => 100000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 100000],
        ]);
        $oktober = $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 10, $this->superAdmin->id);
        $noPenutupLama = $oktober->no_jurnal_penutup;

        $this->postJurnal('JRL-RCL-02', Carbon::create(2026, 11, 10), [
            ['akun_kode' => '110-01', 'debit' => 50000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 50000],
        ]);
        $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 11, $this->superAdmin->id);
        $this->assertSame(150000.0, $this->saldoAkun('310-02', '2026-11-30'));

        // Reopen Oktober (reversal penutup bertanggal 31 Okt) lalu tutup ulang:
        // saldo nominal Oktober muncul lagi dan wajib disapu ulang, tanpa
        // mendebit/kredit Laba Ditahan dua kali.
        $this->tutupBukuService->bukaBukuKembali(
            cabangId: $this->cabang->id,
            tahun: 2026,
            bulan: 10,
            userId: $this->superAdmin->id,
            alasan: 'Koreksi audit Oktober'
        );
        $this->assertSame(100000.0, $this->saldoAkun('410-01', '2026-10-31'));

        $periode = $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 10, $this->superAdmin->id);
        $this->assertTrue($periode->isClosed());
        $this->assertNotNull($periode->no_jurnal_penutup);
        $this->assertNotSame($noPenutupLama, $periode->no_jurnal_penutup);

        $this->assertNominalNol('2026-11-30');
        $this->assertSame(150000.0, $this->saldoAkun('310-02', '2026-11-30'));
        $this->assertNeracaBalance('2026-11-30');
        $this->assertTrue($this->tutupBukuService->getPeriode($this->cabang->id, 2026, 11)->isClosed());
    }
}
