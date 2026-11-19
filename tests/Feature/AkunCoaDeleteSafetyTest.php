<?php

namespace Tests\Feature;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * F-01 (CRITICAL) — Rollback migrasi tidak boleh memusnahkan baris jurnal.
 *
 * Sebelumnya: FK jurnal_akuntansi.akun_coa_id = cascadeOnDelete + down()
 * migrasi PPN/opname menghapus akun COA tanpa cek pemakaian + model AkunCOA
 * tanpa guard deleting → rollback diam-diam menghapus jejak audit jurnal.
 *
 * Defense-in-depth yang diuji:
 * 1. Guard `deleting` model AkunCOA menolak hapus akun ber-jurnal.
 * 2. FK restrictOnDelete menolak penghapusan level DB (raw query builder).
 * 3. down() migrasi terkait gagal eksplisit bila akun sudah dipakai jurnal,
 *    dan tetap reversible (hapus akun) bila belum dipakai.
 */
class AkunCoaDeleteSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Akun penyeimbang jurnal test (di luar akun yang diuji).
        if (! AkunCOA::where('kode', '110-01')->exists()) {
            AkunCOA::create([
                'kode' => '110-01', 'nama' => 'Kas', 'tipe' => 'aset',
                'kelompok' => 'kas', 'saldo_normal' => 'debit', 'is_active' => true,
            ]);
        }
    }

    /** Posting jurnal balanced 2 baris: debit ke akun target, kredit ke Kas. */
    private function postJurnalKe(string $kodeAkun): void
    {
        $akun = AkunCOA::where('kode', $kodeAkun)->firstOrFail();
        $kas = AkunCOA::where('kode', '110-01')->firstOrFail();
        $no = 'JRL-F01-'.str_pad((string) (JurnalAkuntansi::count() + 1), 4, '0', STR_PAD_LEFT);

        JurnalAkuntansi::create([
            'no_jurnal' => $no, 'tanggal' => now(), 'akun_coa_id' => $akun->id,
            'sumber' => 'manual', 'deskripsi' => 'Test F-01', 'debit' => 100, 'kredit' => 0,
        ]);
        JurnalAkuntansi::create([
            'no_jurnal' => $no, 'tanggal' => now(), 'akun_coa_id' => $kas->id,
            'sumber' => 'manual', 'deskripsi' => 'Test F-01', 'debit' => 0, 'kredit' => 100,
        ]);
    }

    private function migration(string $filename): object
    {
        return require database_path('migrations/'.$filename);
    }

    // =====================================================================
    // 1. Guard deleting model AkunCOA
    // =====================================================================

    public function test_model_guard_menolak_delete_akun_yang_memiliki_jurnal(): void
    {
        $this->postJurnalKe('110-03');
        $akun = AkunCOA::where('kode', '110-03')->firstOrFail();

        try {
            $akun->delete();
            $this->fail('delete() akun ber-jurnal seharusnya ditolak guard.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('110-03', $e->getMessage());
        }

        $this->assertTrue(AkunCOA::where('kode', '110-03')->exists());
        $this->assertSame(2, JurnalAkuntansi::count());
    }

    public function test_model_guard_mengizinkan_delete_akun_tanpa_jurnal(): void
    {
        $akun = AkunCOA::where('kode', '220-02')->firstOrFail();

        $akun->delete();

        $this->assertFalse(AkunCOA::where('kode', '220-02')->exists());
    }

    // =====================================================================
    // 2. FK restrictOnDelete — proteksi level DB (bypass Eloquent)
    // =====================================================================

    public function test_fk_restrict_menolak_raw_delete_akun_berjurnal(): void
    {
        $this->postJurnalKe('110-03');

        try {
            DB::table('akun_coa')->where('kode', '110-03')->delete();
            $this->fail('Raw delete akun ber-jurnal seharusnya ditolak FK restrict.');
        } catch (QueryException $e) {
            // diharapkan: integrity constraint violation
        }

        $this->assertTrue(AkunCOA::where('kode', '110-03')->exists());
        $this->assertSame(2, JurnalAkuntansi::count());
    }

    public function test_fk_migration_reversible_ke_cascade(): void
    {
        $this->postJurnalKe('110-03');

        // down() migration FK mengembalikan perilaku lama (cascade) — reversibility.
        $this->migration('2026_11_20_000002_restrict_jurnal_akuntansi_akun_coa_fk.php')->down();

        DB::table('akun_coa')->where('kode', '110-03')->delete();

        $this->assertFalse(AkunCOA::where('kode', '110-03')->exists());
        $this->assertSame(1, JurnalAkuntansi::count()); // baris akun target ter-cascade (perilaku lama)
    }

    // =====================================================================
    // 3. down() migrasi terkait: gagal eksplisit bila dipakai, reversible bila belum
    // =====================================================================

    public function test_down_ppn_keluaran_menolak_bila_220_02_berjurnal(): void
    {
        $this->postJurnalKe('220-02');

        try {
            $this->migration('2026_09_17_0100_add_ppn_keluaran_account.php')->down();
            $this->fail('down() seharusnya menolak menghapus akun yang sudah dipakai jurnal.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('220-02', $e->getMessage());
        }

        $this->assertTrue(AkunCOA::where('kode', '220-02')->exists());
        $this->assertSame(2, JurnalAkuntansi::count());
    }

    public function test_down_ppn_keluaran_menghapus_220_02_bila_belum_dipakai(): void
    {
        $this->migration('2026_09_17_0100_add_ppn_keluaran_account.php')->down();

        $this->assertFalse(AkunCOA::where('kode', '220-02')->exists());
    }

    public function test_down_ppn_fields_menolak_bila_110_03_berjurnal(): void
    {
        $this->postJurnalKe('110-03');

        try {
            $this->migration('2026_09_22_110000_add_ppn_fields_and_reconcile_coa.php')->down();
            $this->fail('down() seharusnya menolak menghapus akun yang sudah dipakai jurnal.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('110-03', $e->getMessage());
        }

        $this->assertTrue(AkunCOA::where('kode', '110-03')->exists());
        $this->assertSame(2, JurnalAkuntansi::count());
        // Guard di awal down(): tidak ada perubahan skema parsial saat gagal.
        $this->assertTrue(Schema::hasColumn('transaksi', 'ppn_nominal'));
        $this->assertTrue(Schema::hasColumn('transaksi', 'dpp'));
    }

    public function test_down_ppn_fields_menghapus_110_03_bila_belum_dipakai(): void
    {
        $this->migration('2026_09_22_110000_add_ppn_fields_and_reconcile_coa.php')->down();

        $this->assertFalse(AkunCOA::where('kode', '110-03')->exists());
    }

    public function test_down_wms_opname_menolak_bila_520_08_berjurnal(): void
    {
        $this->postJurnalKe('520-08');

        try {
            $this->migration('2026_09_20_000040_add_wms_rak_opname_lock.php')->down();
            $this->fail('down() seharusnya menolak menghapus akun yang sudah dipakai jurnal.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('520-08', $e->getMessage());
        }

        $this->assertTrue(AkunCOA::where('kode', '520-08')->exists());
        $this->assertSame(2, JurnalAkuntansi::count());
        // Guard di awal down(): kolom rak tidak ikut ter-drop saat gagal.
        $this->assertTrue(Schema::hasColumn('stok_opname', 'rak_id'));
    }

    public function test_down_wms_opname_menghapus_520_08_bila_belum_dipakai(): void
    {
        $this->migration('2026_09_20_000040_add_wms_rak_opname_lock.php')->down();

        $this->assertFalse(AkunCOA::where('kode', '520-08')->exists());
    }
}
