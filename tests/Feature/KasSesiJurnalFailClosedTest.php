<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * B-10f / P0-4 (lanjutan) — KasSesiState fail-closed.
 *
 * Sesi kas TIDAK boleh final tanpa jurnal:
 * - bukaKas  : insert sesi + jurnal dalam 1 transaksi → jurnal gagal = tidak ada sesi;
 * - tutupKas : status 'tutup' + jurnal selisih dalam 1 transaksi → jurnal gagal =
 *              status tetap 'buka' (kas tidak pernah "tertutup" tanpa jurnal).
 *
 * Failurostasis dipicu dengan mock JurnalService yang melempar (menggantikan
 * "hapus akun COA" supaya tidak bergantung pada perilaku firstOrCreate).
 */
class KasSesiJurnalFailClosedTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->kasir = User::create([
            'name' => 'Kasir B10f',
            'email' => 'kasir-b10f@test.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->kasir->cabangs()->attach(1);
        session(['cabang_id' => 1]);
        $this->actingAs($this->kasir);

        // Sesi bersama (user_id NULL) ikut terfilter → pastikan tidak ada sisa
        DB::table('kas_sesi')->delete();
    }

    /** Pasang JurnalService yang SELALU gagal (simulasi jurnal/CDO down). */
    private function jurnalSelaluGagal(): void
    {
        $mock = Mockery::mock(JurnalService::class);
        $mock->shouldReceive('generateNoJurnal')->andReturn('JRL-KAS-1-'.now()->format('Ymd').'-9999');
        $mock->shouldReceive('post')->andThrow(new \Exception('COA tidak ditemukan: 999-99'));
        $this->app->instance(JurnalService::class, $mock);
    }

    public function test_buka_kas_jurnal_gagal_tidak_membuat_sesi(): void
    {
        $this->jurnalSelaluGagal();

        $gagal = null;
        try {
            app(KasSesiState::class)->bukaKas(150000, 1, $this->kasir->id);
        } catch (\Throwable $e) {
            $gagal = $e;
        }

        $this->assertNotNull($gagal, 'Jurnal gagal harus dilempar (fail-closed)');
        $this->assertStringContainsString('jurnal buka kas', strtolower($gagal->getMessage()));
        $this->assertSame(0, DB::table('kas_sesi')->count(), 'Sesi kas tidak boleh ada tanpa jurnal');
        $this->assertSame(0, JurnalAkuntansi::count());
    }

    public function test_buka_kas_saldo_awal_nol_tidak_perlu_jurnal_dan_tetap_berhasil(): void
    {
        // Saldo awal 0 → tidak ada nilai untuk diposting, BUKAN kegagalan
        $hasil = app(KasSesiState::class)->bukaKas(0, 1, $this->kasir->id);

        $this->assertNotEmpty($hasil['id']);
        $this->assertDatabaseHas('kas_sesi', ['id' => $hasil['id'], 'status' => 'buka']);
        $this->assertSame(0, JurnalAkuntansi::count(), 'Saldo awal 0 tidak menghasilkan jurnal');
    }

    public function test_tutup_kas_jurnal_selisih_gagal_sesi_tetap_buka(): void
    {
        $svc = app(KasSesiState::class);
        $hasil = $svc->bukaKas(100000, 1, $this->kasir->id);
        $sesiId = $hasil['id'];

        // Saldo fisik != saldo sistem → selisih -10000 → jurnal penyesuaian wajib jalan
        $this->jurnalSelaluGagal();

        $gagal = null;
        try {
            $svc->tutupKas(90000);
        } catch (\Throwable $e) {
            $gagal = $e;
        }

        $this->assertNotNull($gagal, 'Jurnal selisih gagal harus dilempar (fail-closed)');
        $this->assertStringContainsString('jurnal selisih kas', strtolower($gagal->getMessage()));

        $sesi = DB::table('kas_sesi')->where('id', $sesiId)->first();
        $this->assertSame('buka', $sesi->status, 'Kas tidak boleh "tertutup" tanpa jurnal');
        $this->assertNull($sesi->ditutup_at, 'ditutup_at tidak boleh terisi');
        $this->assertNull($sesi->selisih, 'selisih tidak boleh terisi');
    }

    public function test_tutup_kas_jurnal_berhasil_sesi_final_tutup(): void
    {
        $svc = app(KasSesiState::class);
        $hasil = $svc->bukaKas(100000, 1, $this->kasir->id);
        $sesiId = $hasil['id'];

        // Saldo fisik 90000 vs sistem 100000 → selisih -10000 (butuh jurnal)
        $out = $svc->tutupKas(90000);

        $this->assertSame(-10000.0, round((float) $out['selisih'], 2));
        $this->assertDatabaseHas('kas_sesi', [
            'id' => $sesiId,
            'status' => 'tutup',
            'selisih' => -10000,
        ]);
        $this->assertNotNull(DB::table('kas_sesi')->where('id', $sesiId)->value('ditutup_at'));

        // Jurnal selisih: 520-07 (beban) debit 10000 & 110-01 (kas) kredit 10000.
        // Total = jurnal buka kas (100000) + jurnal selisih (10000).
        $jurnal = JurnalAkuntansi::with('akun')->where('sumber', 'manual')->get();
        $this->assertSame(110000.0, (float) $jurnal->sum('debit'));
        $this->assertSame(110000.0, (float) $jurnal->sum('kredit'));

        $selisihRows = $jurnal->filter(fn ($j) => str_starts_with((string) $j->deskripsi, 'Tutup kas sesi'));
        $this->assertCount(2, $selisihRows, 'Jurnal selisih = 2 baris');
        $this->assertSame(10000.0, (float) $selisihRows->firstWhere('akun.kode', '520-07')?->debit);
        $this->assertSame(10000.0, (float) $selisihRows->firstWhere('akun.kode', '110-01')?->kredit);
    }

    public function test_tutup_kas_tanpa_selisih_tidak_perlu_jurnal(): void
    {
        $svc = app(KasSesiState::class);
        $hasil = $svc->bukaKas(100000, 1, $this->kasir->id);
        $jurnalSebelum = JurnalAkuntansi::count();

        $out = $svc->tutupKas(100000);

        $this->assertSame(0.0, round((float) $out['selisih'], 2));
        $this->assertDatabaseHas('kas_sesi', ['id' => $hasil['id'], 'status' => 'tutup']);
        // Hanya jurnal buka kas (dari langkah bukaKas), tidak ada jurnal selisih baru
        $this->assertSame($jurnalSebelum, JurnalAkuntansi::count());
    }

    public function test_sesi_kas_aktif_tetap_terbaca_setelah_gagal_tutup(): void
    {
        $svc = app(KasSesiState::class);
        $svc->bukaKas(100000, 1, $this->kasir->id);
        $this->jurnalSelaluGagal();

        try {
            $svc->tutupKas(50000);
        } catch (\Throwable) {
            // diharapkan
        }

        // Sesi masih aktif → kasir bisa tutup kas ulang setelah masalah jurnal beres
        $this->assertNotNull($svc->sesiKasAktif());
        $this->assertTrue($svc->isActiveSesi());
    }

    public function test_buka_kas_gagal_tidak_menghapus_sesi_lama(): void
    {
        $svc = app(KasSesiState::class);
        $hasil = $svc->bukaKas(100000, 1, $this->kasir->id);

        $this->jurnalSelaluGagal();

        $gagal = null;
        try {
            // Sesi masih buka → guard idempotensi yang lebih dulu menolak
            $svc->bukaKas(200000, 1, $this->kasir->id);
        } catch (\Throwable $e) {
            $gagal = $e;
        }

        $this->assertNotNull($gagal);
        $this->assertSame(1, DB::table('kas_sesi')->count(), 'Tidak boleh ada sesi kedua');
        $this->assertDatabaseHas('kas_sesi', ['id' => $hasil['id'], 'status' => 'buka', 'saldo_awal' => 100000]);
    }

    public function test_kas_sesi_scoped_per_cabang(): void
    {
        $cabangLain = Cabang::create(['nama' => 'Cabang B10f', 'kode' => 'CBG-B10F', 'is_active' => true]);
        $svc = app(KasSesiState::class);

        $svc->bukaKas(75000, 1, $this->kasir->id);
        $svc->bukaKas(25000, $cabangLain->id, $this->kasir->id);

        $this->assertSame(2, DB::table('kas_sesi')->count());
        $this->assertSame(1, (int) DB::table('kas_sesi')->where('cabang_id', 1)->count());
        $this->assertSame(1, (int) DB::table('kas_sesi')->where('cabang_id', $cabangLain->id)->count());
    }
}
