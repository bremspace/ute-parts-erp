<?php

namespace Tests\Feature;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Services\JurnalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JurnalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed COA minimal yang dipakai service
        AkunCOA::create(['kode' => '110-01', 'nama' => 'Kas', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit']);
        AkunCOA::create(['kode' => '410-01', 'nama' => 'Pendapatan Penjualan', 'tipe' => 'pendapatan', 'kelompok' => 'pendapatan_penjualan', 'saldo_normal' => 'kredit']);
        AkunCOA::create(['kode' => '510-02', 'nama' => 'HPP', 'tipe' => 'beban', 'kelompok' => 'hpp', 'saldo_normal' => 'debit']);
        AkunCOA::create(['kode' => '130-01', 'nama' => 'Persediaan', 'tipe' => 'aset', 'kelompok' => 'persediaan', 'saldo_normal' => 'debit']);
    }

    private function service(): JurnalService
    {
        return app(JurnalService::class);
    }

    public function test_posts_balanced_journal(): void
    {
        $lines = [
            ['akun_kode' => '110-01', 'debit' => 100000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 100000],
        ];

        $this->service()->post('JRL-TEST-001', now(), 'pos', $lines, 'Penjualan retail');

        $this->assertDatabaseHas('jurnal_akuntansi', ['no_jurnal' => 'JRL-TEST-001', 'debit' => 100000]);
        $this->assertDatabaseCount('jurnal_akuntansi', 2);
    }

    public function test_rejects_unbalanced_journal(): void
    {
        $lines = [
            ['akun_kode' => '110-01', 'debit' => 100000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 90000], // tidak balance
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Jurnal tidak balance');

        $this->service()->post('JRL-TEST-002', now(), 'pos', $lines);
    }

    public function test_rejects_duplicate_no_jurnal(): void
    {
        $lines = [
            ['akun_kode' => '110-01', 'debit' => 50000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 50000],
        ];

        $this->service()->post('JRL-TEST-003', now(), 'pos', $lines);

        // Posting ulang no_jurnal yang sama gagal (idempotency guard PRD gotcha)
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sudah pernah diposting');

        $this->service()->post('JRL-TEST-003', now(), 'pos', $lines);
    }

    public function test_rejects_unknown_akun_kode(): void
    {
        $lines = [
            ['akun_kode' => '999-99', 'debit' => 50000, 'kredit' => 0],
            ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => 50000],
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Akun COA tidak ditemukan');

        $this->service()->post('JRL-TEST-004', now(), 'pos', $lines);
    }
}