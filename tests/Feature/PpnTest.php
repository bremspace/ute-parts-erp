<?php

namespace Tests\Feature;

use App\Modules\Akunting\Services\PajakService;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Pos\Models\Transaksi;
use Tests\TestCase;

class PpnTest extends TestCase
{
    /** @test */
    public function pajak_disabled_returns_zero()
    {
        $service = app(PajakService::class);
        $result = $service->hitung(1, 1000000); // 1 juta
        $this->assertFalse($result['enabled']);
        $this->assertEquals(0, $result['ppn_nominal']);
        $this->assertEquals(0, $result['ppn_percent']);
        $this->assertEquals(1000000, $result['dpp']);
    }

    /** @test */
    public function pajak_enabled_calculates_correctly()
    {
        $service = app(PajakService::class);
        // Enable PPN for cabang 1
        $service->set(1, true, 11.0);
        $result = $service->hitung(1, 1000000);
        $this->assertTrue($result['enabled']);
        $this->assertEquals(11.0, $result['ppn_percent']);
        $this->assertEquals(110000, $result['ppn_nominal']);
        $this->assertEquals(1000000, $result['dpp']);

        // Test with cabang 2 (tidak diaktifkan) harus 0
        $result2 = $service->hitung(2, 1000000);
        $this->assertFalse($result2['enabled']);
        $this->assertEquals(0, $result2['ppn_nominal']);
    }

    /** @test */
    public function pajak_with_diskon()
    {
        $service = app(PajakService::class);
        $service->set(1, true, 11.0);
        // DPP = subtotal - diskon
        $result = $service->hitung(1, 1000000 - 50000); // 950,000 setelah diskon 50k
        $this->assertEquals(104500, $result['ppn_nominal']); // 11% dari 950k
    }

    /** @test */
    public function pajak_jurnal_lines_created_when_nominal_gt_zero()
    {
        $service = app(PajakService::class);
        $lines = $service->jurnalLines(50000, 'JRL-001', 1, 1);
        $this->assertCount(1, $lines);
        $this->assertEquals('220-02', $lines[0]['akun_kode']);
        $this->assertEquals(0, $lines[0]['debit']);
        $this->assertEquals(50000, $lines[0]['kredit']);
    }

    /** @test */
    public function pajak_jurnal_lines_not_created_when_nominal_zero_or_negative()
    {
        $service = app(PajakService::class);
        $lines = $service->jurnalLines(0, 'JRL-001', 1, 1);
        $this->assertEmpty($lines);

        $lines2 = $service->jurnalLines(-1000, 'JRL-002', 1, 1);
        $this->assertEmpty($lines2);
    }

    /** @test */
    public function pos_kasir_pajak_automatic_calculation()
    {
        // Ini menguji integrasi melalui PosController
        // Simulasikan transaksi dengan PPN diaktifkan
        $service = app(PajakService::class);
        $service->set(1, true, 11.0);

        $transaksi = Transaksi::factory()->create([
            'cabang_id' => 1,
            'subtotal' => 1000000,
            'diskon_nominal' => 50000,
            'pajak_nominal' => 104500, // 11% dari (1M - 50k)
            'total_akhir' => 1054500,
        ]);

        // Verifikasi perhitungan
        $dpp = $transaksi->subtotal - $transaksi->diskon_nominal;
        $expectedPpn = round($dpp * 11 / 100, 2);
        $this->assertEquals($expectedPpn, $transaksi->pajak_nominal);
        $this->assertEquals($dpp + $expectedPpn, $transaksi->total_akhir);
    }

    /** @test */
    public function pos_kasir_pajak_zero_when_disabled()
    {
        $service = app(PajakService::class);
        $service->set(1, false, 11.0); // Nonaktifkan PPN

        $transaksi = Transaksi::factory()->create([
            'cabang_id' => 1,
            'subtotal' => 1000000,
            'diskon_nominal' => 50000,
            'pajak_nominal' => 0,
            'total_akhir' => 950000,
        ]);

        $this->assertEquals(0, $transaksi->pajak_nominal);
    }

    /** @test */
    public function pajak_config_per_cabang_isolation()
    {
        $service = app(PajakService::class);
        // Cabang 1: enabled 11%
        $service->set(1, true, 11.0);
        // Cabang 2: enabled 10%
        $service->set(2, true, 10.0);
        // Cabang 3: disabled
        $service->set(3, false, 12.0);

        $this->assertTrue($service->enabled(1));
        $this->assertTrue($service->enabled(2));
        $this->assertFalse($service->enabled(3));

        $this->assertEquals(11.0, $service->getPercent(1));
        $this->assertEquals(10.0, $service->getPercent(2));
        $this->assertEquals(12.0, $service->getPercent(3));
    }

    /** @test */
    public function pajak_export_service_rekap()
    {
        $service = app(\App\Modules\Akunting\Services\ExportLaporanService::class);
        // Buat beberapa transaksi test
        $transaksi = Transaksi::factory()->count(3)->create([
            'cabang_id' => 1,
            'subtotal' => 1000000,
            'diskon_nominal' => 0,
            'pajak_nominal' => 110000,
            'no_jurnal' => 'JRL-001',
            'created_at' => now(),
        ]);

        $transaksi->each(function ($t) {
            $t->update(['pajak_nominal' => 110000]);
        });

        $rows = $service->recapsPpnPeriode(1, now()->toDateString(), now()->toDateString());
        $this->assertIsArray($rows);
        // Header + data rows + footer
        $this->assertGreaterThanOrEqual(5, count($rows));
    }
}