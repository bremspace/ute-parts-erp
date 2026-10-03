<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Pos\Services\KasSesiState;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MutasiKasLaciTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->kasir = User::create([
            'name' => 'Kasir Toko',
            'email' => 'kasir-mutasi@test.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->kasir->cabangs()->attach(1);

        session(['cabang_id' => 1]);
        $this->actingAs($this->kasir);
    }

    public function test_mutasi_keluar_laci_jurnal_beban_debit_kas_laci_kredit(): void
    {
        $svc = app(KasSesiState::class);
        $sesi = $svc->bukaKas(200000, 1, $this->kasir->id, 'manual', '110-01');

        // Kasir beli perlengkapan toko (lakban) Rp 25.000 pakai uang laci
        $res = $svc->catatMutasiLaci(
            jenis: 'keluar',
            nominal: 25000,
            akunLawanKode: '140-01',
            keterangan: 'Beli lakban & kantong plastik'
        );

        $this->assertDatabaseHas('kas_mutasi_laci', [
            'id' => $res['id'],
            'kas_sesi_id' => $sesi['id'],
            'jenis' => 'keluar',
            'nominal' => 25000,
            'akun_lawan_kode' => '140-01',
        ]);

        // Jurnal: D 140-01 (Perlengkapan) 25k / C 110-04 (Kas Laci) 25k
        $lines = JurnalAkuntansi::with('akun')->where('no_jurnal', $res['no_jurnal'])->get();
        $this->assertSame(25000.0, (float) $lines->firstWhere('akun.kode', '140-01')?->debit);
        $this->assertSame(25000.0, (float) $lines->firstWhere('akun.kode', '110-04')?->kredit);

        // Tutup kas: saldo sistem awal 200k - keluar 25k = 175k
        $tutup = $svc->tutupKas(175000);
        $this->assertSame(175000.0, (float) $tutup['saldo_sistem']);
        $this->assertSame(0.0, (float) $tutup['selisih']);
        $this->assertSame('tutup', $tutup['status']);
    }

    public function test_mutasi_masuk_laci_jurnal_kas_laci_debit_pendapatan_kredit(): void
    {
        $svc = app(KasSesiState::class);
        $sesi = $svc->bukaKas(200000, 1, $this->kasir->id, 'manual', '110-01');

        // Terima pendapatan lain-lain (jual kardus bekas) Rp 50.000 masuk laci
        $res = $svc->catatMutasiLaci(
            jenis: 'masuk',
            nominal: 50000,
            akunLawanKode: '430-01',
            keterangan: 'Penjualan kardus packing bekas'
        );

        $this->assertDatabaseHas('kas_mutasi_laci', [
            'id' => $res['id'],
            'kas_sesi_id' => $sesi['id'],
            'jenis' => 'masuk',
            'nominal' => 50000,
            'akun_lawan_kode' => '430-01',
        ]);

        // Jurnal: D 110-04 (Kas Laci) 50k / C 430-01 (Pendapatan Lain-lain) 50k
        $lines = JurnalAkuntansi::with('akun')->where('no_jurnal', $res['no_jurnal'])->get();
        $this->assertSame(50000.0, (float) $lines->firstWhere('akun.kode', '110-04')?->debit);
        $this->assertSame(50000.0, (float) $lines->firstWhere('akun.kode', '430-01')?->kredit);

        // Tutup kas: saldo sistem awal 200k + masuk 50k = 250k
        $tutup = $svc->tutupKas(250000);
        $this->assertSame(250000.0, (float) $tutup['saldo_sistem']);
        $this->assertSame(0.0, (float) $tutup['selisih']);
        $this->assertSame('tutup', $tutup['status']);
    }
}
