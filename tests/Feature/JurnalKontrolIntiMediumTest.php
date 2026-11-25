<?php

namespace Tests\Feature;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Models\PeriodeAkuntansi;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\ValidasiBarisJurnal;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit-1 MEDIUM — kontrol jurnal inti (F-11, F-12, F-13, F-16, F-17, F-18).
 *
 * F-11: nominal negatif / mixed-sign ditolak di post() & ValidasiBarisJurnal.
 * F-12: minimal 2 baris meaningful; baris 0-0 ditolak; 1 baris net-nol ditolak;
 *       baris dua-sisi dipecah menjadi dua baris satu-sisi (normalisasi).
 * F-13: bypass periode via sumber 'tutup_buku' & cabang null/<=0 dihapus;
 *       jurnal tanpa cabang dikenai cek periode global (fail-closed).
 * F-16: guard exists() idempotensi di-scope per cabang (sama dgn unique index).
 * F-17: SQLSTATE 23000 non-unique (FK/NOT NULL) tidak dilaporkan sebagai
 *       "idempotency guard" — exception asli dilempar ulang.
 * F-18: relasi header↔lines memakai cabang_key (isolasi lintas cabang).
 */
class JurnalKontrolIntiMediumTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabangA;

    private Cabang $cabangB;

    private AkunCOA $kas;

    private AkunCOA $pendapatan;

    private AkunCOA $utang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cabangA = Cabang::create(['kode' => 'CBG-MED-A', 'nama' => 'Cabang Medium A', 'is_active' => true]);
        $this->cabangB = Cabang::create(['kode' => 'CBG-MED-B', 'nama' => 'Cabang Medium B', 'is_active' => true]);
        $this->kas = AkunCOA::create(['kode' => '110-01', 'nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit', 'is_active' => true]);
        $this->pendapatan = AkunCOA::create(['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit', 'is_active' => true]);
        $this->utang = AkunCOA::create(['kode' => '210-01', 'nama' => 'Utang Usaha', 'tipe' => 'kewajiban', 'kelompok' => 'utang_usaha', 'saldo_normal' => 'kredit', 'is_active' => true]);
    }

    private function service(): JurnalService
    {
        return app(JurnalService::class);
    }

    /** @return array<int,array<string,mixed>> */
    private function linesSeimbang(float $nilai = 100000): array
    {
        return [
            ['akun_kode' => '110-01', 'debit' => $nilai, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $nilai],
        ];
    }

    private function tutupPeriode(int $cabangId): PeriodeAkuntansi
    {
        return PeriodeAkuntansi::create([
            'cabang_id' => $cabangId,
            'tahun' => (int) now()->format('Y'),
            'bulan' => (int) now()->format('n'),
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    // ===== F-11: nominal negatif & mixed-sign =====

    public function test_post_menolak_debit_negatif_walau_jurnal_balance(): void
    {
        // D200 + D-100 = D100 vs K100 → balance, tetapi negatif lolos sebelum fix.
        $lines = [
            ['akun_kode' => '110-01', 'debit' => 200, 'kredit' => 0],
            ['akun_kode' => '110-01', 'debit' => -100, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 100],
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('tidak boleh negatif');
        $this->service()->post('JRL-MED-NEG-1', now(), 'manual', $lines, 'Uji negatif', $this->cabangA->id);
    }

    public function test_post_menolak_kredit_negatif_walau_jurnal_balance(): void
    {
        $lines = [
            ['akun_kode' => '110-01', 'debit' => 100, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 200],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => -100],
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('tidak boleh negatif');
        $this->service()->post('JRL-MED-NEG-2', now(), 'manual', $lines, 'Uji negatif', $this->cabangA->id);
    }

    public function test_post_menolak_baris_mixed_sign(): void
    {
        // Satu baris D=-100 K=50: max>0 & cek satu-sisi lama tidak menangkap negatif.
        $lines = [
            ['akun_kode' => '110-01', 'debit' => 200, 'kredit' => 0],
            ['akun_kode' => '210-01', 'debit' => -100, 'kredit' => 50],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 150],
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('tidak boleh negatif');
        $this->service()->post('JRL-MED-NEG-3', now(), 'manual', $lines, 'Uji mixed sign', $this->cabangA->id);
    }

    public function test_cek_baris_menolak_mixed_sign_dengan_pesan_negatif(): void
    {
        $galat = ValidasiBarisJurnal::cekBaris([
            ['akun_kode' => '110-01', 'debit' => -100, 'kredit' => 50],
        ]);

        $this->assertNotEmpty($galat);
        $this->assertStringContainsString('tidak boleh negatif', $galat[0]);
        $this->assertStringContainsString('110-01', $galat[0]);
    }

    public function test_cek_baris_menolak_nominal_negatif_satu_sisi(): void
    {
        $galat = ValidasiBarisJurnal::cekBaris([
            ['akun_kode' => '110-01', 'debit' => -100, 'kredit' => 0],
        ]);

        $this->assertNotEmpty($galat);
        $this->assertStringContainsString('tidak boleh negatif', $galat[0]);
    }

    // ===== F-12: minimal 2 baris meaningful =====

    public function test_post_menolak_jurnal_satu_baris_net_nol(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('minimal 2 baris');
        $this->service()->post('JRL-MED-SATU', now(), 'manual', [
            ['akun_kode' => '110-01', 'debit' => 100, 'kredit' => 100],
        ], 'Satu baris D=K', $this->cabangA->id);
    }

    public function test_post_menolak_jurnal_kurang_dari_dua_baris(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('minimal 2 baris');
        $this->service()->post('JRL-MED-KURANG', now(), 'manual', [
            ['akun_kode' => '110-01', 'debit' => 100, 'kredit' => 0],
        ], 'Satu baris saja', $this->cabangA->id);
    }

    public function test_post_membuang_baris_nol_nol_tanpa_menyimpannya(): void
    {
        // Alur otomatis (mis. payroll) mengirim baris potongan bernilai nol:
        // baris 0-0 dibuang, jurnal tetap sah selama >= 2 baris meaningful.
        $lines = $this->linesSeimbang();
        $lines[] = ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => 0];

        $created = $this->service()->post('JRL-MED-NOL', now(), 'payroll', $lines, 'Ada baris 0-0', $this->cabangA->id);

        $this->assertCount(2, $created);
        $this->assertDatabaseCount('jurnal_akuntansi', 2);
        $this->assertSame(0, JurnalAkuntansi::where('no_jurnal', 'JRL-MED-NOL')->where('debit', 0)->where('kredit', 0)->count());
    }

    public function test_post_menolak_bila_tersisa_satu_baris_meaningful(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('minimal 2 baris');
        $this->service()->post('JRL-MED-SISA1', now(), 'manual', [
            ['akun_kode' => '110-01', 'debit' => 100, 'kredit' => 0],
            ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => 0],
        ], 'Hanya satu baris meaningful', $this->cabangA->id);
    }

    public function test_post_memecah_baris_dua_sisi_menjadi_dua_baris_satu_sisi(): void
    {
        // Alur otomatis boleh mengirim baris dua-sisi; normalisasi memecahnya
        // sehingga buku besar hanya berisi baris satu-sisi (konvensi double-entry).
        $lines = [
            ['akun_kode' => '110-01', 'debit' => 100, 'kredit' => 50],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 50],
        ];

        $created = $this->service()->post('JRL-MED-PECAH', now(), 'pos', $lines, 'Dua sisi dipecah', $this->cabangA->id);

        $this->assertCount(3, $created);
        $this->assertDatabaseCount('jurnal_akuntansi', 3);
        $this->assertSame(0.0, (float) JurnalAkuntansi::where('no_jurnal', 'JRL-MED-PECAH')->where('debit', '>', 0)->where('kredit', '>', 0)->count());
        $this->assertSame(100.0, (float) JurnalAkuntansi::where('no_jurnal', 'JRL-MED-PECAH')->sum('debit'));
        $this->assertSame(100.0, (float) JurnalAkuntansi::where('no_jurnal', 'JRL-MED-PECAH')->sum('kredit'));
    }

    // ===== F-13: bypass kontrol periode =====

    public function test_post_sumber_tutup_buku_tetap_dicek_periode_closed(): void
    {
        $this->tutupPeriode($this->cabangA->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sudah ditutup');
        $this->service()->post('JRL-MED-TB', now(), 'tutup_buku', $this->linesSeimbang(), 'Penutup bypass', $this->cabangA->id);
    }

    public function test_post_tanpa_cabang_dicek_periode_global(): void
    {
        $this->tutupPeriode($this->cabangA->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sudah ditutup');
        $this->service()->post('JRL-MED-NOCBG', now(), 'manual', $this->linesSeimbang(), 'Tanpa cabang', null);
    }

    public function test_post_cabang_nol_dicek_periode_global(): void
    {
        $this->tutupPeriode($this->cabangA->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sudah ditutup');
        $this->service()->post('JRL-MED-CBG0', now(), 'manual', $this->linesSeimbang(), 'Cabang 0', 0);
    }

    public function test_post_tutup_buku_sah_sebelum_periode_ditutup_tetap_berhasil(): void
    {
        // Jalur sah TutupBukuService: penutup diposting SEBELUM periode di-set
        // closed → guard periode (tanpa bypass sumber) tetap mengizinkan.
        PeriodeAkuntansi::create([
            'cabang_id' => $this->cabangA->id,
            'tahun' => (int) now()->format('Y'),
            'bulan' => (int) now()->format('n'),
            'status' => 'open',
        ]);

        $this->service()->post('JRL-MED-TB-SAH', now(), 'tutup_buku', $this->linesSeimbang(), 'Penutup sah', $this->cabangA->id);

        $this->assertDatabaseHas('jurnal_header', ['no_jurnal' => 'JRL-MED-TB-SAH', 'sumber' => 'tutup_buku']);
    }

    public function test_post_tanpa_cabang_tanpa_periode_closed_tetap_berhasil(): void
    {
        $this->service()->post('JRL-MED-NOCBG-OPEN', now(), 'manual', $this->linesSeimbang(), 'Tanpa cabang, periode open', null);

        $this->assertDatabaseHas('jurnal_akuntansi', ['no_jurnal' => 'JRL-MED-NOCBG-OPEN']);
    }

    // ===== F-16: scope idempotensi per cabang =====

    public function test_nomor_sama_beda_cabang_tidak_false_positive_idempotency(): void
    {
        $this->service()->post('JRL-MED-SCOPE', now(), 'manual', $this->linesSeimbang(), 'Cabang A', $this->cabangA->id);
        $this->service()->post('JRL-MED-SCOPE', now(), 'manual', $this->linesSeimbang(), 'Cabang B', $this->cabangB->id);

        $this->assertDatabaseCount('jurnal_akuntansi', 4);
        $this->assertSame(2, JurnalHeader::where('no_jurnal', 'JRL-MED-SCOPE')->count());
    }

    public function test_duplikat_nomor_cabang_sama_tetap_ditolak(): void
    {
        $this->service()->post('JRL-MED-DUP', now(), 'manual', $this->linesSeimbang(), 'Pertama', $this->cabangA->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sudah pernah diposting');
        $this->service()->post('JRL-MED-DUP', now(), 'manual', $this->linesSeimbang(), 'Duplikat', $this->cabangA->id);
    }

    // ===== F-17: 23000 bukan selalu unique =====

    public function test_fk_cabang_invalid_tidak_dilaporkan_sebagai_idempotency(): void
    {
        $this->expectException(QueryException::class);
        $this->service()->post('JRL-MED-FK', now(), 'manual', $this->linesSeimbang(), 'Cabang tidak ada', 999999);
    }

    public function test_unique_idempotency_key_tetap_diterjemahkan_ke_pesan_bisnis(): void
    {
        $this->service()->post('JRL-MED-IDEM-1', now(), 'pos', $this->linesSeimbang(), 'Pertama', $this->cabangA->id, null, null, null, 'KUNCI-UJI-1');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Pembayaran dengan kunci ini sudah pernah dicatat');
        $this->service()->post('JRL-MED-IDEM-2', now(), 'pos', $this->linesSeimbang(), 'Kunci sama', $this->cabangA->id, null, null, null, 'KUNCI-UJI-1');
    }

    // ===== F-18: relasi header↔lines keyed no_jurnal + cabang_key =====

    public function test_relasi_header_lines_terisolasi_per_cabang(): void
    {
        $this->service()->post('JRL-MED-REL', now(), 'manual', $this->linesSeimbang(), 'Cabang A', $this->cabangA->id);
        $this->service()->post('JRL-MED-REL', now(), 'manual', $this->linesSeimbang(200000), 'Cabang B', $this->cabangB->id);

        $headerA = JurnalHeader::where('no_jurnal', 'JRL-MED-REL')->where('cabang_key', $this->cabangA->id)->firstOrFail();
        $headerB = JurnalHeader::where('no_jurnal', 'JRL-MED-REL')->where('cabang_key', $this->cabangB->id)->firstOrFail();

        $this->assertCount(2, $headerA->lines);
        $this->assertCount(2, $headerB->lines);
        $this->assertSame(100000.0, (float) $headerA->lines->sum('debit'));
        $this->assertSame(200000.0, (float) $headerB->lines->sum('debit'));

        foreach ($headerA->lines as $line) {
            $this->assertSame($this->cabangA->id, (int) $line->cabang_key);
        }
        foreach ($headerB->lines as $line) {
            $this->assertSame($this->cabangB->id, (int) $line->cabang_key);
        }

        // Eager-load LINTAS cabang sekaligus (kunci komposit generated column):
        // setiap header hanya menarik baris cabangnya, setiap baris hanya
        // menarik header cabangnya — tidak ada campur aduk.
        $headers = JurnalHeader::where('no_jurnal', 'JRL-MED-REL')->with('lines')->get();
        foreach ($headers as $header) {
            $this->assertCount(2, $header->lines);
            foreach ($header->lines as $line) {
                $this->assertSame((int) $header->cabang_key, (int) $line->cabang_key);
            }
        }
        $semuaBaris = JurnalAkuntansi::where('no_jurnal', 'JRL-MED-REL')->with('header')->get();
        $this->assertCount(4, $semuaBaris);
        foreach ($semuaBaris as $line) {
            $this->assertNotNull($line->header);
            $this->assertSame((int) $line->cabang_key, (int) $line->header->cabang_key);
        }
    }

    public function test_baris_tanpa_cabang_memiliki_cabang_key_nol(): void
    {
        $this->service()->post('JRL-MED-KEY0', now(), 'manual', $this->linesSeimbang(), 'Tanpa cabang', null);

        $this->assertDatabaseHas('jurnal_akuntansi', ['no_jurnal' => 'JRL-MED-KEY0', 'cabang_key' => 0]);
        $line = JurnalAkuntansi::where('no_jurnal', 'JRL-MED-KEY0')->with('header')->firstOrFail();
        $this->assertNotNull($line->header);
        $this->assertSame(0, (int) $line->header->cabang_key);
    }
}
