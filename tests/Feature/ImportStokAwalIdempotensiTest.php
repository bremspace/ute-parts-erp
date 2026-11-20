<?php

namespace Tests\Feature;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Services\ImportProdukService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit-1 F-16 (import stok awal) — nomor JRL-IMP harus deterministik penuh:
 *  - komponen tanggal diambil dari import_log.created_at (stabil), bukan
 *    now() yang berubah bila retry melewati tengah malam → jurnal dobel;
 *  - guard exists() di-scope per cabang sehingga import_log/chunk sama yang
 *    diproses untuk dua cabang tidak false-positive (unique index per-cabang).
 */
class ImportStokAwalIdempotensiTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabangA;

    private Cabang $cabangB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cabangA = Cabang::create(['kode' => 'CBG-IMP-A', 'nama' => 'Cabang Import A', 'is_active' => true]);
        $this->cabangB = Cabang::create(['kode' => 'CBG-IMP-B', 'nama' => 'Cabang Import B', 'is_active' => true]);
        AkunCOA::create(['kode' => '130-01', 'nama' => 'Persediaan Barang Dagang', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit', 'is_active' => true]);
        AkunCOA::create(['kode' => '310-01', 'nama' => 'Modal', 'tipe' => 'ekuitas', 'kelompok' => 'modal', 'saldo_normal' => 'kredit', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function buatLog(): ImportLog
    {
        return ImportLog::create([
            'tipe' => 'produk_excel', 'nama_file' => 'uji-f16.csv',
            'total_baris' => 0, 'sukses' => 0, 'gagal' => 0, 'status' => 'proses',
        ]);
    }

    private function noJurnalChunk(ImportProdukService $svc, int $logId, int $chunk): string
    {
        $m = new \ReflectionMethod($svc, 'noJurnalChunk');

        return (string) $m->invoke($svc, $logId, $chunk);
    }

    private function postJurnalStokAwal(ImportProdukService $svc, int $logId, ?int $cabangId, float $nilai, string $noJurnal): void
    {
        $m = new \ReflectionMethod($svc, 'postJurnalStokAwal');
        $m->invoke($svc, $logId, $cabangId, $nilai, null, $noJurnal);
    }

    public function test_no_jurnal_stabil_melewati_tengah_malam(): void
    {
        Carbon::setTestNow('2026-11-10 23:50:00');
        $log = $this->buatLog();
        $svc = app(ImportProdukService::class);

        $sebelum = $this->noJurnalChunk($svc, $log->id, 0);

        // Retry job terjadi setelah tengah malam → nomor TIDAK boleh berubah.
        Carbon::setTestNow('2026-11-11 00:10:00');
        $sesudah = $this->noJurnalChunk($svc, $log->id, 0);

        $this->assertSame($sebelum, $sesudah, 'Nomor import harus deterministik penuh (tanggal import_log, bukan now())');
        $this->assertStringContainsString('20261110', $sebelum);
    }

    public function test_no_jurnal_memakai_tanggal_import_log(): void
    {
        Carbon::setTestNow('2026-11-11 09:00:00');
        $log = $this->buatLog();
        // Log dibuat kemarin (mis. job tertunda/antrian panjang).
        DB::table('import_log')->where('id', $log->id)->update(['created_at' => '2026-11-01 10:00:00']);

        $no = $this->noJurnalChunk(app(ImportProdukService::class), $log->id, 0);

        $this->assertStringContainsString('20261101', $no);
        $this->assertStringNotContainsString('20261111', $no);
    }

    public function test_retry_chunk_tidak_membuat_jurnal_kedua(): void
    {
        $log = $this->buatLog();
        $svc = app(ImportProdukService::class);
        $no = $this->noJurnalChunk($svc, $log->id, 0);

        $this->postJurnalStokAwal($svc, $log->id, $this->cabangA->id, 100000, $no);
        $this->postJurnalStokAwal($svc, $log->id, $this->cabangA->id, 100000, $no);

        $this->assertDatabaseCount('jurnal_akuntansi', 2);
        $this->assertSame(1, JurnalHeader::where('no_jurnal', $no)->count());
    }

    public function test_log_sama_dua_cabang_menghasilkan_jurnal_terpisah(): void
    {
        $log = $this->buatLog();
        $svc = app(ImportProdukService::class);
        $no = $this->noJurnalChunk($svc, $log->id, 0);

        // import_log/chunk sama diproses untuk dua cabang (gudang berbeda):
        // masing-masing cabang berhak atas jurnal stok awalnya sendiri.
        $this->postJurnalStokAwal($svc, $log->id, $this->cabangA->id, 100000, $no);
        $this->postJurnalStokAwal($svc, $log->id, $this->cabangB->id, 250000, $no);

        $this->assertDatabaseCount('jurnal_akuntansi', 4);
        $this->assertSame(2, JurnalHeader::where('no_jurnal', $no)->count());
        $this->assertSame(100000.0, (float) JurnalAkuntansi::where('no_jurnal', $no)->where('cabang_id', $this->cabangA->id)->sum('debit'));
        $this->assertSame(250000.0, (float) JurnalAkuntansi::where('no_jurnal', $no)->where('cabang_id', $this->cabangB->id)->sum('debit'));
    }
}
