<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Jobs\ExportLaporanJob;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class LaporanKonsolidasiTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabangA;

    private Cabang $cabangB;

    private User $finance;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seedCoa();

        $this->cabangA = Cabang::create(['kode' => 'CBG-A', 'nama' => 'Cabang Jakarta', 'is_active' => true]);
        $this->cabangB = Cabang::create(['kode' => 'CBG-B', 'nama' => 'Cabang Surabaya', 'is_active' => true]);

        $this->finance = User::create([
            'name' => 'Finance Staff',
            'email' => 'finance-test@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->finance->assignRole('finance');
        $this->finance->cabangs()->attach([$this->cabangA->id, $this->cabangB->id]);

        $this->kasir = User::create([
            'name' => 'Kasir Toko',
            'email' => 'kasir-test@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->kasir->assignRole('kasir');
        $this->kasir->cabangs()->attach([$this->cabangA->id]);
    }

    private function seedCoa(): void
    {
        $coas = [
            '110-01' => ['Kas', 'aset', 'kas', 'debit'],
            '120-01' => ['Piutang Usaha', 'aset', 'piutang_usaha', 'debit'],
            '130-01' => ['Persediaan Barang Dagang', 'aset', 'persediaan', 'debit'],
            '210-01' => ['Utang Usaha', 'kewajiban', 'utang_usaha', 'kredit'],
            '310-01' => ['Modal Disetor', 'ekuitas', 'modal', 'kredit'],
            '410-01' => ['Pendapatan Penjualan', 'pendapatan', 'pendapatan_operasional', 'kredit'],
            '510-01' => ['Beban Operasional', 'beban', 'beban_operasional', 'debit'],
        ];

        foreach ($coas as $kode => [$nama, $tipe, $kelompok, $saldo]) {
            AkunCOA::create([
                'kode' => $kode,
                'nama' => $nama,
                'tipe' => $tipe,
                'kelompok' => $kelompok,
                'saldo_normal' => $saldo,
                'is_active' => true,
            ]);
        }
    }

    public function test_user_tanpa_izin_laporan_konsolidasi_ditolak_via_api(): void
    {
        session(['cabang_id' => $this->cabangA->id]);

        // Kasir tidak memiliki permission laporan.konsolidasi
        $this->actingAs($this->kasir, 'sanctum');

        $respLabaRugi = $this->getJson('/api/akunting/laporan/laba-rugi?konsolidasi=1');
        $respLabaRugi->assertStatus(403);

        $respNeraca = $this->getJson('/api/akunting/laporan/neraca?konsolidasi=1');
        $respNeraca->assertStatus(403);

        $respArusKas = $this->getJson('/api/akunting/laporan/arus-kas?konsolidasi=1');
        $respArusKas->assertStatus(403);

        $respExport = $this->postJson('/api/akunting/export', [
            'jenis' => 'laba_rugi',
            'konsolidasi' => true,
        ]);
        $respExport->assertStatus(403);
    }

    public function test_finance_bisa_mengakses_laporan_konsolidasi_via_api(): void
    {
        session(['cabang_id' => $this->cabangA->id]);
        $this->actingAs($this->finance, 'sanctum');

        $resp = $this->getJson('/api/akunting/laporan/laba-rugi?konsolidasi=1');
        $resp->assertOk()
            ->assertJsonPath('data.cakupan', 'konsolidasi')
            ->assertJsonPath('data.cabang_id', null);

        $respNeraca = $this->getJson('/api/akunting/laporan/neraca?konsolidasi=1');
        $respNeraca->assertOk()
            ->assertJsonPath('data.cakupan', 'konsolidasi')
            ->assertJsonPath('data.cabang_id', null);

        $respArusKas = $this->getJson('/api/akunting/laporan/arus-kas?konsolidasi=1');
        $respArusKas->assertOk()
            ->assertJsonPath('data.cakupan', 'konsolidasi')
            ->assertJsonPath('data.cabang_id', null);
    }

    public function test_laba_rugi_konsolidasi_menggabungkan_pendapatan_seluruh_cabang(): void
    {
        $akunKas = AkunCOA::where('kode', '110-01')->first();
        $akunPendapatan = AkunCOA::where('kode', '410-01')->first();

        // Transaksi Cabang A: Rp 1.000.000
        JurnalAkuntansi::create([
            'no_jurnal' => 'JRN-A-01',
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Penjualan Cabang A',
            'akun_coa_id' => $akunKas->id,
            'debit' => 1000000,
            'kredit' => 0,
            'cabang_id' => $this->cabangA->id,
            'sumber' => 'pos',
        ]);
        JurnalAkuntansi::create([
            'no_jurnal' => 'JRN-A-01',
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Penjualan Cabang A',
            'akun_coa_id' => $akunPendapatan->id,
            'debit' => 0,
            'kredit' => 1000000,
            'cabang_id' => $this->cabangA->id,
            'sumber' => 'pos',
        ]);

        // Transaksi Cabang B: Rp 2.500.000
        JurnalAkuntansi::create([
            'no_jurnal' => 'JRN-B-01',
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Penjualan Cabang B',
            'akun_coa_id' => $akunKas->id,
            'debit' => 2500000,
            'kredit' => 0,
            'cabang_id' => $this->cabangB->id,
            'sumber' => 'pos',
        ]);
        JurnalAkuntansi::create([
            'no_jurnal' => 'JRN-B-01',
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Penjualan Cabang B',
            'akun_coa_id' => $akunPendapatan->id,
            'debit' => 0,
            'kredit' => 2500000,
            'cabang_id' => $this->cabangB->id,
            'sumber' => 'pos',
        ]);

        session(['cabang_id' => $this->cabangA->id]);
        $this->actingAs($this->finance, 'sanctum');

        // Scope Cabang A saja: Pendapatan harus 1.000.000
        $respCabangA = $this->getJson('/api/akunting/laporan/laba-rugi');
        $respCabangA->assertOk();
        $this->assertEquals(1000000.0, (float) $respCabangA->json('data.total_pendapatan'));

        // Scope Konsolidasi: Pendapatan harus 1.000.000 + 2.500.000 = 3.500.000
        $respKonsolidasi = $this->getJson('/api/akunting/laporan/laba-rugi?konsolidasi=1');
        $respKonsolidasi->assertOk();
        $this->assertEquals(3500000.0, (float) $respKonsolidasi->json('data.total_pendapatan'));
        $this->assertEquals(3500000.0, (float) $respKonsolidasi->json('data.laba_bersih'));
    }

    public function test_neraca_konsolidasi_menggabungkan_saldo_seluruh_cabang_dan_balance(): void
    {
        $akunKas = AkunCOA::where('kode', '110-01')->first();
        $akunModal = AkunCOA::where('kode', '310-01')->first();

        // Cabang A: Modal disetor Rp 5.000.000
        JurnalAkuntansi::create([
            'no_jurnal' => 'JRN-A-MODAL',
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Setoran Modal Cabang A',
            'akun_coa_id' => $akunKas->id,
            'debit' => 5000000,
            'kredit' => 0,
            'cabang_id' => $this->cabangA->id,
            'sumber' => 'manual',
        ]);
        JurnalAkuntansi::create([
            'no_jurnal' => 'JRN-A-MODAL',
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Setoran Modal Cabang A',
            'akun_coa_id' => $akunModal->id,
            'debit' => 0,
            'kredit' => 5000000,
            'cabang_id' => $this->cabangA->id,
            'sumber' => 'manual',
        ]);

        // Cabang B: Modal disetor Rp 10.000.000
        JurnalAkuntansi::create([
            'no_jurnal' => 'JRN-B-MODAL',
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Setoran Modal Cabang B',
            'akun_coa_id' => $akunKas->id,
            'debit' => 10000000,
            'kredit' => 0,
            'cabang_id' => $this->cabangB->id,
            'sumber' => 'manual',
        ]);
        JurnalAkuntansi::create([
            'no_jurnal' => 'JRN-B-MODAL',
            'tanggal' => now()->toDateString(),
            'deskripsi' => 'Setoran Modal Cabang B',
            'akun_coa_id' => $akunModal->id,
            'debit' => 0,
            'kredit' => 10000000,
            'cabang_id' => $this->cabangB->id,
            'sumber' => 'manual',
        ]);

        session(['cabang_id' => $this->cabangA->id]);
        $this->actingAs($this->finance, 'sanctum');

        $resp = $this->getJson('/api/akunting/laporan/neraca?konsolidasi=1');
        $resp->assertOk();
        $this->assertTrue($resp->json('data.balance'));
        $this->assertEquals(15000000.0, (float) $resp->json('data.total_aset'));
        $this->assertEquals(15000000.0, (float) $resp->json('data.total_ekuitas_bersama_laba'));
    }

    public function test_livewire_akunting_dashboard_bisa_toggle_konsolidasi_oleh_finance(): void
    {
        session(['cabang_id' => $this->cabangA->id]);

        Livewire::actingAs($this->finance)
            ->test(AkuntingDashboard::class)
            ->assertSet('cakupanLaporan', 'cabang')
            ->call('setCakupanLaporan', 'konsolidasi')
            ->assertSet('cakupanLaporan', 'konsolidasi')
            ->call('setCakupanLaporan', 'cabang')
            ->assertSet('cakupanLaporan', 'cabang');
    }

    public function test_livewire_akunting_dashboard_menolak_toggle_konsolidasi_oleh_non_finance(): void
    {
        session(['cabang_id' => $this->cabangA->id]);

        // Kasir mencoba setCakupanLaporan 'konsolidasi' → ditolak 403
        $this->actingAs($this->kasir);

        Livewire::actingAs($this->kasir)
            ->test(AkuntingDashboard::class)
            ->call('setCakupanLaporan', 'konsolidasi')
            ->assertStatus(403);
    }

    public function test_export_konsolidasi_mendispatch_job_dengan_cabang_id_null(): void
    {
        Queue::fake();

        session(['cabang_id' => $this->cabangA->id]);
        $this->actingAs($this->finance, 'sanctum');

        $resp = $this->postJson('/api/akunting/export', [
            'jenis' => 'laba_rugi',
            'konsolidasi' => true,
        ]);

        $resp->assertOk();

        Queue::assertPushed(ExportLaporanJob::class, function ($job) {
            return $job->jenis === 'laba_rugi' && $job->cabangId === null;
        });
    }
}
