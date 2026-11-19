<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\TutupBukuService;
use App\Modules\Rbac\Models\Cabang;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * F-03 / F-04 / F-05 (HIGH) — Kontrol inti reversal jurnal.
 *
 * F-03: cek is_reversed dulu di luar transaksi + tanpa lock header + tanpa
 *       unique reversal_of_no_jurnal → race double reversal; jurnal legacy
 *       tanpa header bisa di-reverse berulang.
 * F-04: jurnal penutup (sumber tutup_buku) bisa di-reverse via API reversal
 *       umum tanpa reopen periode resmi.
 * F-05: reopen periode membalik ayat penutup bertanggal now(), bukan akhir
 *       bulan yang direopen → bulan reopen tetap tertutup di laporan.
 */
class JurnalReversalControlsTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $superAdmin;

    private JurnalService $jurnalService;

    private TutupBukuService $tutupBukuService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seedCoa();

        $this->cabang = Cabang::create([
            'kode' => 'CB-REVCTRL',
            'nama' => 'Cabang Test Kontrol Reversal',
            'is_active' => true,
        ]);
        $this->superAdmin = User::factory()->create([
            'name' => 'Admin Kontrol Reversal',
            'email' => 'admin-revctrl@test.com',
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
            ['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit'],
            ['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit'],
            ['kode' => '310-02', 'nama' => 'Laba Ditahan', 'tipe' => 'ekuitas', 'kelompok' => 'laba_ditahan', 'saldo_normal' => 'kredit'],
        ];
        foreach ($accounts as $a) {
            AkunCOA::create(array_merge($a, ['is_active' => true]));
        }
    }

    private function postJurnalManual(string $noJurnal, $tanggal, float $nominal = 100000.0): void
    {
        $this->jurnalService->post(
            noJurnal: $noJurnal,
            tanggal: $tanggal,
            sumber: 'manual',
            lines: [
                ['akun_kode' => '110-01', 'debit' => $nominal, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $nominal],
            ],
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id
        );
    }

    // =====================================================================
    // F-03 — race double reversal: lock header + unique index + tolak legacy
    // =====================================================================

    public function test_unique_index_reversal_of_no_jurnal_per_cabang_ada_di_db(): void
    {
        $indexes = Schema::getIndexes('jurnal_header');
        $unique = collect($indexes)->first(fn ($idx) => $idx['unique']
            && $idx['columns'] === ['cabang_key', 'reversal_of_no_jurnal']);

        $this->assertNotNull(
            $unique,
            'Unique index (cabang_key, reversal_of_no_jurnal) wajib ada sebagai jaring DB anti double reversal.'
        );
    }

    public function test_reversal_kedua_ditolak_unique_db_meski_flag_is_reversed_terreset(): void
    {
        // Simulasi race F-03: request kedua membaca state basi / metadata
        // tertimpa (is_reversed kembali false). Jaring unique DB harus tetap
        // menolak reversal kedua dan TIDAK meninggalkan jurnal pembalik ganda.
        $noAsal = 'JRL-RACE-01';
        $this->postJurnalManual($noAsal, now());

        $this->jurnalService->reversalJurnal(
            noJurnalAsal: $noAsal,
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id,
            alasan: 'Reversal pertama (sah)'
        );

        // Reset flag via raw query (meniru overwrite metadata oleh request race).
        DB::table('jurnal_header')
            ->where('no_jurnal', $noAsal)
            ->update(['is_reversed' => false, 'reversed_by_no_jurnal' => null]);

        try {
            $this->jurnalService->reversalJurnal(
                noJurnalAsal: $noAsal,
                cabangId: $this->cabang->id,
                userId: $this->superAdmin->id,
                alasan: 'Reversal kedua (race, harus ditolak)'
            );
            $this->fail('Reversal kedua seharusnya ditolak unique index reversal_of_no_jurnal.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('sudah pernah dibalik', $e->getMessage());
        }

        // Hanya SATU jurnal pembalik yang persisted (transaksi kedua rollback penuh).
        $this->assertSame(1, JurnalHeader::where('reversal_of_no_jurnal', $noAsal)->count());
        $this->assertSame(2, JurnalAkuntansi::where('sumber', 'reversal')->count());

        // Saldo Kas net nol — tidak bergeser 2x nominal.
        $kas = AkunCOA::where('kode', '110-01')->firstOrFail();
        $net = (float) JurnalAkuntansi::where('akun_coa_id', $kas->id)->sum('debit')
            - (float) JurnalAkuntansi::where('akun_coa_id', $kas->id)->sum('kredit');
        $this->assertSame(0.0, round($net, 2));
    }

    public function test_reversal_jurnal_legacy_tanpa_header_ditolak(): void
    {
        // Baris jurnal legacy tanpa header (fallback pre-migrasi): sebelumnya
        // lolos semua guard is_reversed/sumber → bisa di-reverse tanpa batas.
        $kas = AkunCOA::where('kode', '110-01')->firstOrFail();
        $pendapatan = AkunCOA::where('kode', '410-01')->firstOrFail();
        foreach ([[$kas, 60000, 0], [$pendapatan, 0, 60000]] as [$akun, $d, $k]) {
            JurnalAkuntansi::create([
                'no_jurnal' => 'JRL-LEGACY-01',
                'tanggal' => now(),
                'cabang_id' => $this->cabang->id,
                'akun_coa_id' => $akun->id,
                'sumber' => 'manual',
                'deskripsi' => 'Jurnal legacy tanpa header',
                'debit' => $d,
                'kredit' => $k,
            ]);
        }
        $this->assertNull(JurnalHeader::where('no_jurnal', 'JRL-LEGACY-01')->first());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('header');

        $this->jurnalService->reversalJurnal(
            noJurnalAsal: 'JRL-LEGACY-01',
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id,
            alasan: 'Coba reverse jurnal legacy'
        );
    }

    public function test_reversal_ganda_berurutan_ditolak_dan_saldo_net_nol(): void
    {
        $noAsal = 'JRL-SEQ-01';
        $this->postJurnalManual($noAsal, now(), 50000);

        $this->jurnalService->reversalJurnal(
            noJurnalAsal: $noAsal,
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id,
            alasan: 'Reversal pertama'
        );

        try {
            $this->jurnalService->reversalJurnal(
                noJurnalAsal: $noAsal,
                cabangId: $this->cabang->id,
                userId: $this->superAdmin->id,
                alasan: 'Reversal kedua'
            );
            $this->fail('Reversal ganda berurutan seharusnya ditolak.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('sudah pernah dibalik', $e->getMessage());
        }

        $this->assertSame(1, JurnalHeader::where('reversal_of_no_jurnal', $noAsal)->count());
        $kas = AkunCOA::where('kode', '110-01')->firstOrFail();
        $net = (float) JurnalAkuntansi::where('akun_coa_id', $kas->id)->sum('debit')
            - (float) JurnalAkuntansi::where('akun_coa_id', $kas->id)->sum('kredit');
        $this->assertSame(0.0, round($net, 2));
    }

    // =====================================================================
    // F-04 — jurnal penutup tidak boleh di-reverse via reversal biasa
    // =====================================================================

    public function test_reversal_jurnal_penutup_ditolak_via_reversal_biasa(): void
    {
        Carbon::setTestNow('2026-03-15 10:00:00');
        $this->postJurnalManual('JRL-PENUTUP-ASAL', Carbon::create(2026, 1, 10));
        $periode = $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 1, $this->superAdmin->id);
        $noPenutup = $periode->no_jurnal_penutup;
        $this->assertNotNull($noPenutup);

        try {
            $this->jurnalService->reversalJurnal(
                noJurnalAsal: $noPenutup,
                cabangId: $this->cabang->id,
                userId: $this->superAdmin->id,
                alasan: 'Coba balik jurnal penutup tanpa reopen'
            );
            $this->fail('Jurnal penutup seharusnya tidak bisa di-reverse via reversal biasa.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Buka Kembali Periode', $e->getMessage());
        }

        // Periode tetap closed, tidak ada jurnal pembalik terposting.
        $this->assertTrue($periode->fresh()->isClosed());
        $this->assertSame(0, JurnalAkuntansi::where('sumber', 'reversal')->count());
        $this->assertFalse((bool) JurnalHeader::where('no_jurnal', $noPenutup)->first()->is_reversed);
    }

    public function test_api_reversal_jurnal_penutup_ditolak_422(): void
    {
        Carbon::setTestNow('2026-03-15 10:00:00');
        $this->postJurnalManual('JRL-PENUTUP-API', Carbon::create(2026, 1, 12));
        $periode = $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 1, $this->superAdmin->id);

        $response = $this->postJson("/api/akunting/jurnal/{$periode->no_jurnal_penutup}/reversal", [
            'alasan' => 'Coba balik penutup via API',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertTrue($periode->fresh()->isClosed());
        $this->assertSame(0, JurnalAkuntansi::where('sumber', 'reversal')->count());
    }

    // =====================================================================
    // F-05 — reopen periode: reversal penutup bertanggal akhir bulan reopen
    // =====================================================================

    public function test_reopen_periode_mereversal_penutup_pada_akhir_bulan_yang_dibuka(): void
    {
        // Reopen Januari dilakukan di Maret: reversal harus bertanggal 31 Jan,
        // bukan tanggal hari ini (Maret).
        Carbon::setTestNow('2026-03-15 10:00:00');
        $this->postJurnalManual('JRL-REOPEN-01', Carbon::create(2026, 1, 10));
        $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 1, $this->superAdmin->id);

        $periode = $this->tutupBukuService->bukaBukuKembali(
            cabangId: $this->cabang->id,
            tahun: 2026,
            bulan: 1,
            userId: $this->superAdmin->id,
            alasan: 'Koreksi audit periode Januari'
        );
        $this->assertTrue($periode->isOpen());

        $headerRev = JurnalHeader::where('sumber', 'reversal')->firstOrFail();
        $this->assertSame('2026-01-31', $headerRev->tanggal->format('Y-m-d'));

        $linesRev = JurnalAkuntansi::where('no_jurnal', $headerRev->no_jurnal)->get();
        $this->assertNotEmpty($linesRev);
        foreach ($linesRev as $line) {
            $this->assertSame('2026-01-31', Carbon::parse($line->tanggal)->format('Y-m-d'));
        }

        // Tidak ada mutasi reversal nyasar ke Maret (bulan berjalan).
        $this->assertSame(0, JurnalAkuntansi::where('sumber', 'reversal')
            ->whereDate('tanggal', '>=', '2026-03-01')->count());

        // Laporan Januari pulih: pendapatan Januari kembali bersaldo kredit
        // 100.000 (penutup di-nol-kan oleh reversal pada bulan yang sama).
        $pendapatan = AkunCOA::where('kode', '410-01')->firstOrFail();
        $rowsJan = JurnalAkuntansi::where('akun_coa_id', $pendapatan->id)
            ->whereDate('tanggal', '>=', '2026-01-01')
            ->whereDate('tanggal', '<=', '2026-01-31')
            ->get();
        $this->assertSame(100000.0, round((float) $rowsJan->sum('kredit') - (float) $rowsJan->sum('debit'), 2));
    }

    public function test_periode_yang_direopen_dapat_ditutup_kembali(): void
    {
        Carbon::setTestNow('2026-03-15 10:00:00');
        $this->postJurnalManual('JRL-RECLOSE-01', Carbon::create(2026, 1, 10));
        $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 1, $this->superAdmin->id);
        $this->tutupBukuService->bukaBukuKembali(
            cabangId: $this->cabang->id,
            tahun: 2026,
            bulan: 1,
            userId: $this->superAdmin->id,
            alasan: 'Koreksi lalu tutup lagi'
        );

        $periode = $this->tutupBukuService->tutupBuku($this->cabang->id, 2026, 1, $this->superAdmin->id);
        $this->assertTrue($periode->isClosed());
        $this->assertNotNull($periode->no_jurnal_penutup);

        // Akun nominal Januari kembali nol setelah tutup buku kedua.
        $pendapatan = AkunCOA::where('kode', '410-01')->firstOrFail();
        $rowsJan = JurnalAkuntansi::where('akun_coa_id', $pendapatan->id)
            ->whereDate('tanggal', '>=', '2026-01-01')
            ->whereDate('tanggal', '<=', '2026-01-31')
            ->get();
        $this->assertSame(0.0, round((float) $rowsJan->sum('kredit') - (float) $rowsJan->sum('debit'), 2));
    }

    public function test_reversal_transaksi_biasa_tetap_bertanggal_sekarang(): void
    {
        // Regresi: storno transaksi biasa tetap bertanggal now() (bulan berjalan).
        Carbon::setTestNow('2026-03-15 10:00:00');
        $this->postJurnalManual('JRL-STORNO-01', Carbon::create(2026, 3, 2));

        $hasil = $this->jurnalService->reversalJurnal(
            noJurnalAsal: 'JRL-STORNO-01',
            cabangId: $this->cabang->id,
            userId: $this->superAdmin->id,
            alasan: 'Storno biasa'
        );

        $headerRev = JurnalHeader::where('no_jurnal', $hasil['no_jurnal_reversal'])->firstOrFail();
        $this->assertSame('2026-03-15', $headerRev->tanggal->format('Y-m-d'));
    }
}
