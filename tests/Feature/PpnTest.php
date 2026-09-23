<?php

namespace Tests\Feature;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * [F1-2] Pajak Otomatis — hitung PPN/DPP, jurnal 220-01 balance,
 * override percent per-cabang, pajak nonaktif = tanpa baris PPN.
 * Catatan: method wajib prefix test_ (PHPUnit 12 tidak deteksi @test docblock).
 */
class PpnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // COA minimal utk jurnal POS + PPN
        AkunCOA::firstOrCreate(['kode' => '110-01'], ['nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '120-01'], ['nama' => 'Piutang Usaha', 'tipe' => 'aset', 'kelompok' => 'piutang', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '410-01'], ['nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::firstOrCreate(['kode' => '510-02'], ['nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '130-01'], ['nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);
        AkunCOA::firstOrCreate(['kode' => '220-01'], ['nama' => 'PPN Keluaran', 'tipe' => 'kewajiban', 'kelompok' => 'pajak', 'saldo_normal' => 'kredit']);
        AkunCOA::firstOrCreate(['kode' => '110-03'], ['nama' => 'PPN Masukan', 'tipe' => 'aset', 'kelompok' => 'pajak', 'saldo_normal' => 'debit']);
    }

    protected function createCabang(?string $kode = null): Cabang
    {
        return Cabang::create([
            'kode' => $kode ?? 'UTP'.Str::random(3),
            'nama' => 'Cabang Test',
            'alamat' => 'Jl. Test',
            'telepon' => '08123456789',
            'is_active' => true,
        ]);
    }

    // ===== HITUNG PPN / DPP =====

    public function test_pajak_disabled_returns_zero(): void
    {
        $service = app(PajakService::class);
        $result = $service->hitung(1, 1000000);
        $this->assertFalse($result['enabled']);
        $this->assertEquals(0, $result['ppn_nominal']);
        $this->assertEquals(0, $result['ppn_percent']);
        $this->assertEquals(1000000, $result['dpp']);
    }

    public function test_pajak_enabled_calculates_correctly(): void
    {
        $service = app(PajakService::class);
        $service->set(1, true, 11.0);
        $result = $service->hitung(1, 1000000);
        $this->assertTrue($result['enabled']);
        $this->assertEquals(11.0, $result['ppn_percent']);
        $this->assertEquals(110000, $result['ppn_nominal']);
        $this->assertEquals(1000000, $result['dpp']);

        // Cabang 2 tidak diaktifkan → 0
        $result2 = $service->hitung(2, 1000000);
        $this->assertFalse($result2['enabled']);
        $this->assertEquals(0, $result2['ppn_nominal']);
    }

    public function test_pajak_with_diskon(): void
    {
        $service = app(PajakService::class);
        $service->set(1, true, 11.0);
        $result = $service->hitung(1, 1000000 - 50000); // DPP 950k setelah diskon
        $this->assertEquals(104500, $result['ppn_nominal']); // 11% × 950k
    }

    public function test_pajak_enabled_key_pajak_enabled_contract(): void
    {
        // [F1-2] kontrak AC: key pajak_enabled.{cabang}
        $service = app(PajakService::class);
        $service->set(7, true, 11.0);

        $this->assertDatabaseHas('konfigurasi', ['kunci' => 'pajak_enabled.7', 'nilai' => '1']);
        $this->assertTrue($service->enabled(7));
    }

    // ===== JURNAL PPN 220-01 + BALANCE =====

    public function test_pajak_jurnal_lines_created_when_nominal_gt_zero(): void
    {
        $service = app(PajakService::class);
        $lines = $service->jurnalLines(50000, 'JRL-001', 1, 1);
        $this->assertCount(1, $lines);
        $this->assertEquals('220-01', $lines[0]['akun_kode']); // kontrak AC F1-2
        $this->assertEquals(0, $lines[0]['debit']);
        $this->assertEquals(50000, $lines[0]['kredit']);
    }

    public function test_pajak_jurnal_lines_not_created_when_nominal_zero_or_negative(): void
    {
        $service = app(PajakService::class);
        $this->assertEmpty($service->jurnalLines(0, 'JRL-001', 1, 1));
        $this->assertEmpty($service->jurnalLines(-1000, 'JRL-002', 1, 1));
    }

    public function test_jurnal_pos_with_ppn_balances_on_220_01(): void
    {
        // Pola jurnal PosKasir: debit totalAkhir = kredit DPP (410-01) + PPN (220-01)
        $cabang = $this->createCabang();
        $pajak = app(PajakService::class);
        $pajak->set($cabang->id, true, 11.0);

        $dpp = 1000000.0;
        $hitung = $pajak->hitung($cabang->id, $dpp); // ppn 110k
        $totalAkhir = $dpp + $hitung['ppn_nominal']; // 1.110.000

        $lines = [
            ['akun_kode' => '110-01', 'debit' => $totalAkhir, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $dpp],
        ];
        foreach ($pajak->jurnalLines($hitung['ppn_nominal'], 'JRL-PPN-1', $cabang->id, 1) as $pl) {
            $lines[] = $pl;
        }

        app(JurnalService::class)->post('JRL-PPN-1', now(), 'pos', $lines, 'Jurnal POS + PPN', $cabang->id, null);

        // Balance: total debit === total kredit
        $jurnal = JurnalAkuntansi::where('no_jurnal', 'JRL-PPN-1')->get();
        $this->assertCount(3, $jurnal);
        $this->assertEquals($jurnal->sum('debit'), $jurnal->sum('kredit'));

        // Baris 220-01 kredit 110.000
        $akun22001 = AkunCOA::where('kode', '220-01')->first();
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'no_jurnal' => 'JRL-PPN-1',
            'akun_coa_id' => $akun22001->id,
            'kredit' => 110000,
        ]);
    }

    public function test_pajak_off_no_ppn_jurnal_lines(): void
    {
        $cabang = $this->createCabang();
        $pajak = app(PajakService::class);
        // default: cabang tidak diaktifkan
        $this->assertFalse($pajak->enabled($cabang->id));

        $hitung = $pajak->hitung($cabang->id, 1000000);
        $this->assertEquals(0, $hitung['ppn_nominal']);
        $this->assertEmpty($pajak->jurnalLines($hitung['ppn_nominal'], 'JRL-NO-PPN', $cabang->id, 1));

        // Jurnal tanpa PPN tetap balance (pola lama: dpp == totalAkhir)
        $lines = [
            ['akun_kode' => '110-01', 'debit' => 1000000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 1000000],
        ];
        app(JurnalService::class)->post('JRL-NO-PPN', now(), 'pos', $lines, 'Tanpa PPN', $cabang->id, null);
        $jurnal = JurnalAkuntansi::where('no_jurnal', 'JRL-NO-PPN')->get();
        $this->assertCount(2, $jurnal);
        $this->assertEquals($jurnal->sum('debit'), $jurnal->sum('kredit'));
        // Tidak ada baris 220-01
        $akun22001 = AkunCOA::where('kode', '220-01')->first();
        $this->assertDatabaseMissing('jurnal_akuntansi', ['no_jurnal' => 'JRL-NO-PPN', 'akun_coa_id' => $akun22001->id]);
    }

    // ===== FIELD transaksi ppn_nominal + dpp =====

    public function test_transaksi_stores_ppn_and_dpp(): void
    {
        $cabang = $this->createCabang();
        $service = app(PajakService::class);
        $service->set($cabang->id, true, 11.0);

        $dpp = 950000.0;
        $hitung = $service->hitung($cabang->id, $dpp);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-PPN-'.Str::random(5),
            'cabang_id' => $cabang->id,
            'subtotal' => 1000000,
            'diskon_nominal' => 50000,
            'dpp' => $dpp,
            'pajak_nominal' => $hitung['ppn_nominal'],
            'ppn_nominal' => $hitung['ppn_nominal'],
            'total_akhir' => $dpp + $hitung['ppn_nominal'],
        ]);

        $this->assertDatabaseHas('transaksi', [
            'id' => $transaksi->id,
            'dpp' => 950000,
            'ppn_nominal' => 104500,
        ]);
        $this->assertEquals(950000, (float) $transaksi->dpp);
        $this->assertEquals(104500, (float) $transaksi->ppn_nominal);
        $this->assertEquals(1054500, (float) $transaksi->total_akhir);
    }

    public function test_pos_kasir_pajak_zero_when_disabled(): void
    {
        $cabang = $this->createCabang();
        $service = app(PajakService::class);
        $service->set($cabang->id, false, 11.0);

        $transaksi = Transaksi::create([
            'no_transaksi' => 'TRX-PPN-'.Str::random(5),
            'cabang_id' => $cabang->id,
            'subtotal' => 1000000,
            'diskon_nominal' => 50000,
            'dpp' => 950000,
            'pajak_nominal' => 0,
            'ppn_nominal' => 0,
            'total_akhir' => 950000,
        ]);

        $this->assertEquals(0, (float) $transaksi->pajak_nominal);
        $this->assertEquals(0, (float) $transaksi->ppn_nominal);
    }

    // ===== OVERRIDE PERCENT PER-CABANG =====

    public function test_pajak_config_per_cabang_isolation(): void
    {
        $service = app(PajakService::class);
        $service->set(1, true, 11.0);
        $service->set(2, true, 10.0);
        $service->set(3, false, 12.0);

        $this->assertTrue($service->enabled(1));
        $this->assertTrue($service->enabled(2));
        $this->assertFalse($service->enabled(3));

        $this->assertEquals(11.0, $service->getPercent(1));
        $this->assertEquals(10.0, $service->getPercent(2));
        $this->assertEquals(12.0, $service->getPercent(3));

        // Hitung memakai percent masing-masing cabang
        $this->assertEquals(110000, $service->hitung(1, 1000000)['ppn_nominal']); // 11% × 1jt = 110k
        $this->assertEquals(100000, $service->hitung(2, 1000000)['ppn_nominal']); // 10% × 1jt = 100k
        $this->assertEquals(0, $service->hitung(3, 1000000)['ppn_nominal']);       // nonaktif
    }

    public function test_default_ppn_percent_is_11(): void
    {
        $service = app(PajakService::class);
        $this->assertEquals(11.0, $service->getPercent(null));
        $this->assertEquals(11.0, $service->getPercent(999)); // cabang tanpa override → global/default 11
    }

    // ===== EXPORT REKAP =====

    public function test_pajak_export_service_rekap(): void
    {
        $service = app(ExportLaporanService::class);
        $cabang = $this->createCabang();
        collect(range(1, 3))->each(fn () => Transaksi::create([
            'no_transaksi' => 'TRX-PPN-'.Str::random(5),
            'cabang_id' => $cabang->id,
            'subtotal' => 1000000,
            'diskon_nominal' => 0,
            'dpp' => 1000000,
            'pajak_nominal' => 110000,
            'ppn_nominal' => 110000,
            'total_akhir' => 1110000,
            'status' => 'selesai',
        ]));

        $rows = $service->recapsPpnPeriode($cabang->id, now()->toDateString(), now()->toDateString());
        $this->assertIsArray($rows);
        // Header + kolom + baris data + footer keluaran/masukan/terutang
        $this->assertGreaterThanOrEqual(5, count($rows));
        // Ada baris TOTAL KELUARAN dgn total PPN 330.000
        $flat = implode('|', array_map(fn ($r) => implode(',', $r), $rows));
        $this->assertStringContainsString('TOTAL KELUARAN', $flat);
        $this->assertStringContainsString('TOTAL MASUKAN', $flat);
        $this->assertStringContainsString('PPN TERUTANG', $flat);
    }
}
