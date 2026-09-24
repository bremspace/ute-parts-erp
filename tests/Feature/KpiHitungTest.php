<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Lead;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KpiHasil;
use App\Modules\Hr\Models\KpiMetric;
use App\Modules\Hr\Services\KpiService;
use App\Modules\Servis\Models\TiketServis;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KpiHitungTest extends TestCase
{
    use RefreshDatabase;

    protected KpiService $kpi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        $this->kpi = app(KpiService::class);
        session(['cabang_id' => 1]);
    }

    private function buatUser(string $email): User
    {
        return User::create([
            'name' => 'User '.$email,
            'email' => $email,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    private function buatKaryawan(User $user, string $jabatan): Karyawan
    {
        return Karyawan::create([
            'user_id' => $user->id,
            'nama' => 'Karyawan '.$jabatan,
            'jabatan' => $jabatan,
            'cabang_id' => 1,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => 4000000,
            'status_aktif' => true,
        ]);
    }

    private function buatMetric(string $kode, string $rumus, float $target = 0): KpiMetric
    {
        return KpiMetric::create([
            'kode' => $kode,
            'nama' => 'Metric '.$kode,
            'rumus' => $rumus,
            'target' => $target,
            'satuan' => 'unit',
            'periode' => 'bulanan',
            'is_aktif' => true,
        ]);
    }

    private function buatTiket(User $teknisiUser): void
    {
        TiketServis::create([
            'no_tiket' => 'TS-KPI-'.uniqid(),
            'cabang_id' => 1,
            'teknisi_id' => $teknisiUser->id,
            'jenis_hp' => 'iPhone 14',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Kerusakan layar',
            'kondisi_fisik' => ['kerusakan' => 'layar retak'],
            'foto_unit' => [],
            'status' => 'selesai',
            'sumber' => 'walk_in',
            'estimasi_biaya' => 100000,
            'tanggal_selesai' => now(),
        ]);
    }

    private function buatTransaksiKasir(User $kasirUser, string $no): void
    {
        DB::table('transaksi')->insert([
            'no_transaksi' => $no,
            'cabang_id' => 1,
            'kasir_id' => $kasirUser->id,
            'sumber' => 'pos',
            'subtotal' => 100000,
            'total_akhir' => 100000,
            'metode_bayar' => 'tunai',
            'jumlah_bayar' => 100000,
            'status' => 'selesai',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buatSesiKasSelisih(User $kasirUser, float $selisih): void
    {
        DB::table('kas_sesi')->insert([
            'cabang_id' => 1,
            'user_id' => $kasirUser->id,
            'saldo_awal' => 100000,
            'saldo_akhir_sistem' => 100000,
            'saldo_akhir_fisik' => 100000 + $selisih,
            'selisih' => $selisih,
            'status' => 'tutup',
            'dibuka_at' => now(),
            'ditutup_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buatLeadWon(User $marketingUser, float $nilai): void
    {
        Lead::create([
            'cabang_id' => 1,
            'sumber' => 'website',
            'stage' => 'won',
            'nama' => 'Lead KPI',
            'telepon' => '0812'.rand(1000000, 9999999),
            'nilai_estimasi' => $nilai,
            'assigned_to' => $marketingUser->id,
            'won_at' => now(),
        ]);
    }

    public function test_metric_tiket_selesai_dari_data_real(): void
    {
        $teknisi = $this->buatKaryawan($this->buatUser('teknisi@test.local'), 'teknisi');
        $this->buatTiket($teknisi->user);
        $this->buatMetric('tiket_selesai', 'tiket_selesai', 20);

        $hasil = $this->kpi->hitung('2026-09');
        $this->assertEquals(1, $hasil['hasil']);

        $row = KpiHasil::where('karyawan_id', $teknisi->id)->first();
        $this->assertNotNull($row);
        $this->assertEquals(1, (float) $row->nilai_aktual);
        // 1 / 20 * 100 = 5%
        $this->assertEquals(5, (float) $row->persen_capaian);
    }

    public function test_metric_transaksi_kasir_dan_selisih_kas_dari_data_real(): void
    {
        $kasir = $this->buatKaryawan($this->buatUser('kasir@test.local'), 'kasir');
        $this->buatTransaksiKasir($kasir->user, 'TRX-KPI-001');
        $this->buatSesiKasSelisih($kasir->user, -500);
        $this->buatMetric('transaksi_kasir', 'transaksi_kasir', 15);
        $this->buatMetric('selisih_kas', 'selisih_kas', 50000);

        $this->kpi->hitung('2026-09');

        $transaksi = KpiHasil::where('karyawan_id', $kasir->id)->whereHas('metric', fn ($q) => $q->where('rumus', 'transaksi_kasir'))->first();
        $this->assertNotNull($transaksi);
        $this->assertEquals(1, (float) $transaksi->nilai_aktual);
        // 1 / 15 * 100 = 6.67
        $this->assertEquals(6.67, round((float) $transaksi->persen_capaian, 2));

        $selisih = KpiHasil::where('karyawan_id', $kasir->id)->whereHas('metric', fn ($q) => $q->where('rumus', 'selisih_kas'))->first();
        $this->assertNotNull($selisih);
        $this->assertEquals(500, (float) $selisih->nilai_aktual);
        // 100 - (500 / 50000 * 100) = 99%
        $this->assertEquals(99, (float) $selisih->persen_capaian);
    }

    public function test_metric_lead_won_dan_penjualan_dari_data_real(): void
    {
        $marketing = $this->buatKaryawan($this->buatUser('marketing@test.local'), 'marketing');
        $this->buatLeadWon($marketing->user, 25000000);
        $this->buatMetric('lead_won', 'lead_won', 10);
        $this->buatMetric('penjualan_lead_won', 'penjualan_lead_won', 50000000);

        $this->kpi->hitung('2026-09');

        $leadWon = KpiHasil::where('karyawan_id', $marketing->id)->whereHas('metric', fn ($q) => $q->where('rumus', 'lead_won'))->first();
        $this->assertNotNull($leadWon);
        $this->assertEquals(1, (float) $leadWon->nilai_aktual);
        // 1 / 10 * 100 = 10%
        $this->assertEquals(10, (float) $leadWon->persen_capaian);

        $penjualan = KpiHasil::where('karyawan_id', $marketing->id)->whereHas('metric', fn ($q) => $q->where('rumus', 'penjualan_lead_won'))->first();
        $this->assertNotNull($penjualan);
        $this->assertEquals(25000000, (float) $penjualan->nilai_aktual);
        // 25.000.000 / 50.000.000 * 100 = 50%
        $this->assertEquals(50, (float) $penjualan->persen_capaian);
    }

    public function test_rumus_non_whitelist_ditolak_tanpa_eval(): void
    {
        $teknisi = $this->buatKaryawan($this->buatUser('teknisi2@test.local'), 'teknisi');
        $this->buatTiket($teknisi->user);
        $evil = $this->buatMetric('evil_rumus', 'eval(system("id"))');

        $hasil = $this->kpi->hitung('2026-09');
        $this->assertEquals(0, $hasil['metric_diproses']);
        $this->assertEquals(0, KpiHasil::where('kpi_metric_id', $evil->id)->count());
    }

    public function test_hitung_idempotent_per_karyawan_metric_periode(): void
    {
        $teknisi = $this->buatKaryawan($this->buatUser('teknisi3@test.local'), 'teknisi');
        $this->buatTiket($teknisi->user);
        $this->buatMetric('tiket_selesai', 'tiket_selesai', 20);

        $this->kpi->hitung('2026-09');
        $this->kpi->hitung('2026-09');

        $this->assertEquals(1, KpiHasil::where('karyawan_id', $teknisi->id)->count());
        $this->assertEquals(1, KpiHasil::where('periode', '2026-09')->count());
    }

    public function test_karyawan_jabatan_tidak_cocok_tidak_dihitung(): void
    {
        // Karyawan 'admin' — metric teknisi/kasir/marketing tidak berlaku
        $admin = $this->buatKaryawan($this->buatUser('admin@test.local'), 'admin');
        $this->buatMetric('tiket_selesai', 'tiket_selesai', 20);

        $hasil = $this->kpi->hitung('2026-09');
        $this->assertEquals(0, $hasil['hasil']);
        $this->assertEquals(0, KpiHasil::where('karyawan_id', $admin->id)->count());
    }
}
