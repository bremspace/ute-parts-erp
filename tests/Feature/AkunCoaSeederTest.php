<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-02 / P0-4 — COA wajib tersedia di instalasi fresh.
 *
 * Tempo lalu `composer setup` hanya `migrate --force` (TANPA seed) → database
 * baru tidak punya satu pun akun COA. Jurnal servis `onSelesai` bersifat
 * fail-closed (`JurnalService` → `ValidasiBarisJurnal::cariAktif()`), jadi
 * SETIAP tiket gagal masuk status `selesai` (regresi P0-4/P0-10).
 *
 * Yang diuji:
 *  1. `AkunCoaSeeder` idempoten — dijalankan 2x (lewat `db:seed` artisan,
 *     persis perintah yang dipakai `composer setup`) tidak error & tidak
 *     menggandakan baris.
 *  2. Kelima akun wajib servis (110-01, 130-01, 410-01, 420-01, 510-02) ada
 *     DAN `is_active = true`.
 *  3. Akun wajib yang dinonaktifkan/diubah definisinya dipulihkan oleh seeder.
 *  4. Definisi Akun NON-wajib tidak diaktifkan paksa (mempertahankan keputusan admin).
 *  5. End-to-end: hanya `AkunCoaSeeder` (+ data identitas/cabang, BUKAN seeder
 *     COA lain) → jurnal servis `selesai` tetap bisa diposting.
 */
class AkunCoaSeederTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::create([
            'name' => 'Operator T02',
            'email' => 'operator-t02@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach(1);
        session(['cabang_id' => 1]);
        $this->actingAs($this->user, 'web');
    }

    /** Perintah yang sama dengan `composer setup` (script `setup`). */
    private function seedCOA(): void
    {
        $this->artisan('db:seed', [
            '--class' => 'Database\\Seeders\\AkunCoaSeeder',
            '--force' => true,
        ])->assertSuccessful();
    }

    /** (1) Idempoten: dua kali jalan, tidak error & jumlah baris tetap. */
    public function test_seeder_bisa_dijalankan_berulang_tanpa_error(): void
    {
        $this->seedCOA();
        $jumlahAwal = AkunCOA::count();
        $this->assertGreaterThanOrEqual(count(AkunCoaSeeder::AKUN_WAJIB_SERVIS), $jumlahAwal);

        // Jalankan lagi (2x, sekaligus 3x) — harus tetap aman
        $this->seedCOA();
        $this->seedCOA();

        $this->assertSame($jumlahAwal, AkunCOA::count(), 'Seeder wajib idempoten — tidak boleh menambah/gandakan akun');
        $this->assertSame(
            1,
            AkunCOA::where('kode', '110-01')->count(),
            'Kode akun harus unik (tidak boleh dobel baris)'
        );
    }

    /** (2) Kelima akun wajib servis ada & aktif. */
    public function test_lima_akun_wajib_servis_ada_dan_aktif(): void
    {
        $this->seedCOA();

        foreach (AkunCoaSeeder::AKUN_WAJIB_SERVIS as $kode) {
            $akun = AkunCOA::where('kode', $kode)->first();

            $this->assertNotNull($akun, "Akun COA wajib servis {$kode} harus ada setelah seeding");
            $this->assertTrue($akun->is_active, "Akun COA {$kode} harus aktif (jurnal fail-closed bila nonaktif)");
            $this->assertNotEmpty($akun->nama);
            $this->assertContains($akun->tipe, ['aset', 'kewajiban', 'ekuitas', 'pendapatan', 'beban']);
        }

        $this->assertSame(
            ['110-01', '120-01', '130-01', '410-01', '420-01', '510-02'],
            AkunCoaSeeder::AKUN_WAJIB_SERVIS,
            'Daftar akun wajib servis berubah — samakan dengan jurnal onSelesai & test'
        );
    }

    /** (3) Akun wajib yang nonaktif / rusak definisinya dipulihkan seeder. */
    public function test_akun_wajib_servis_dipulihkan_oleh_seeder(): void
    {
        $this->seedCOA();

        AkunCOA::where('kode', '110-01')->update(['is_active' => false]);
        AkunCOA::where('kode', '510-02')->update(['is_active' => false, 'nama' => 'SALAH SEED']);
        AkunCOA::where('kode', '420-01')->delete();

        $this->seedCOA();

        $this->assertTrue(AkunCOA::where('kode', '110-01')->value('is_active'));
        $this->assertSame('Harga Pokok Penjualan (HPP)', AkunCOA::where('kode', '510-02')->value('nama'));
        $this->assertTrue(AkunCOA::where('kode', '510-02')->value('is_active'));
        $this->assertTrue(AkunCOA::where('kode', '420-01')->exists(), 'Akun wajib terhapus harus dibuat ulang');
        $this->assertTrue(AkunCOA::where('kode', '420-01')->value('is_active'));
    }

    /** (4) Akun NON-wajib: status aktif pilihan admin tidak ditimpa paksa. */
    public function test_akun_non_wajib_tidak_diaktifkan_paksa(): void
    {
        $this->seedCOA();

        // Akun bukan WAJIB servis → sengaja dinonaktifkan admin
        AkunCOA::where('kode', '520-05')->update(['is_active' => false]);

        $this->seedCOA();

        $this->assertFalse(
            (bool) AkunCOA::where('kode', '520-05')->value('is_active'),
            'Seeder tidak boleh mengaktifkan kembali akun non-wajib yang dinonaktifkan admin'
        );
    }

    /**
     * (5) Regresi P0-4 yang sebenarnya: tanpa seeder COA lain, jurnal servis
     * `selesai` tetap bisa diposting (kas + pendapatan jasa).
     */
    public function test_jurnal_servis_tetah_berjalan_hanya_dengan_coa_seeder(): void
    {
        // Akar P0-4: `migrate` TIDAK menyediakan akun wajib jurnal servis
        // (migrasi hanya membuat 220-01/220-02/110-03 + akun aset tetap),
        // sehingga instalasi fresh butuh `db:seed --class=AkunCoaSeeder`.
        foreach (AkunCoaSeeder::AKUN_WAJIB_SERVIS as $kode) {
            $this->assertFalse(
                AkunCOA::where('kode', $kode)->exists(),
                "Migrasi tidak membuat akun wajib servis {$kode} — tanpa seeder jurnal servis gagal (P0-4)"
            );
        }

        $this->seedCOA();

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-T02-0001',
            'cabang_id' => 1,
            'nama_pelanggan' => 'Pelanggan T02',
            'jenis_hp' => 'iPhone 13',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Layar mati',
            'kondisi_fisik' => ['layar' => 'pecah'],
            'foto_unit' => [],
            'status' => 'qc',
            'sumber' => 'walk_in',
            'estimasi_biaya' => 150000,
        ]);

        app(ServisService::class)->updateStatus($tiket, 'selesai', $this->user, 'Selesai');

        $this->assertSame('selesai', $tiket->fresh()->status);

        $jurnalServis = JurnalAkuntansi::where('sumber', 'servis')->get();
        $this->assertGreaterThanOrEqual(2, $jurnalServis->count(), 'Jurnal servis minimal 2 baris (kas + pendapatan jasa)');

        $piutang = AkunCOA::where('kode', '120-01')->first();
        $jasa = AkunCOA::where('kode', '420-01')->first();

        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $piutang->id, 'debit' => 150000, 'kredit' => 0,
        ]);
        $this->assertDatabaseHas('jurnal_akuntansi', [
            'akun_coa_id' => $jasa->id, 'debit' => 0, 'kredit' => 150000,
        ]);
    }
}
