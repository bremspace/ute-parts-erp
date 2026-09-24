<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hr\Models\AbsensiLog;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Services\AbsensiService;
use App\Modules\Pos\Services\KasSesiState;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AbsensiShiftTest extends TestCase
{
    use RefreshDatabase;

    protected AbsensiService $absensi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        $this->absensi = app(AbsensiService::class);
        session(['cabang_id' => 1]);
    }

    private function buatUser(string $email = 'kasir@test.local'): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => $email,
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    private function buatKaryawan(User $user, string $jabatan = 'kasir'): Karyawan
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

    private function buatShift(string $mulai, string $selesai, string $nama = 'Pagi'): Shift
    {
        return Shift::create([
            'cabang_id' => 1,
            'nama' => $nama,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'is_aktif' => true,
        ]);
    }

    public function test_clock_in_idempotent_per_karyawan_tanggal(): void
    {
        $karyawan = $this->buatKaryawan($this->buatUser());

        $this->absensi->clockIn($karyawan->id, null, 'Toko', null, 'pagi pertama');
        $this->absensi->clockIn($karyawan->id, null, 'Lokasi lain', null, 'kedua');

        $this->assertEquals(1, AbsensiLog::count());
        $log = AbsensiLog::first();
        $this->assertNotNull($log->jam_masuk);
        // Idempotent: jam masuk & lokasi pertama tidak ditimpa
        $this->assertSame('Toko', $log->lokasi);
    }

    public function test_clock_in_status_terlambat_sesuai_jam_mulai_shift(): void
    {
        $this->buatShift('08:00:00', '16:00:00');
        $terlambat = $this->buatKaryawan($this->buatUser('a@test.local'));

        $log = $this->absensi->clockIn($terlambat->id, 1, null, null, null, Carbon::create(2026, 9, 24, 9, 0, 0));
        $this->assertSame('terlambat', $log->status);

        $tepat = $this->buatKaryawan($this->buatUser('b@test.local'));
        $log2 = $this->absensi->clockIn($tepat->id, 1, null, null, null, Carbon::create(2026, 9, 24, 7, 50, 0));
        $this->assertSame('hadir', $log2->status);
    }

    public function test_shift_overnight(): void
    {
        $shift = $this->buatShift('22:00:00', '06:00:00', 'Malam');
        $this->assertTrue($shift->apakahOvernight());

        // 23:00 → masih dalam jendela shift malam → hadir
        $dalam = $this->buatKaryawan($this->buatUser('c@test.local'));
        $log = $this->absensi->clockIn($dalam->id, $shift->id, null, null, null, Carbon::create(2026, 9, 24, 23, 0, 0));
        $this->assertSame('hadir', $log->status);

        // 07:00 → lewat jam selesai shift → terlambat
        $lewat = $this->buatKaryawan($this->buatUser('d@test.local'));
        $log2 = $this->absensi->clockIn($lewat->id, $shift->id, null, null, null, Carbon::create(2026, 9, 24, 7, 0, 0));
        $this->assertSame('terlambat', $log2->status);
    }

    public function test_clock_out_mengisi_jam_keluar_idempotent(): void
    {
        $karyawan = $this->buatKaryawan($this->buatUser());
        $this->absensi->clockIn($karyawan->id);

        $waktuKeluar = Carbon::create(2026, 9, 24, 17, 0, 0);
        $this->absensi->clockOut($karyawan->id, 'pulang', $waktuKeluar);
        $this->absensi->clockOut($karyawan->id, 'pulang kedua', $waktuKeluar);

        $log = AbsensiLog::first();
        $this->assertSame('17:00:00', $log->jam_keluar);
        // Idempotent: clock-out ganda tidak menimpa catatan
        $this->assertStringContainsString('pulang', $log->catatan);
        $this->assertStringNotContainsString('kedua', $log->catatan);
    }

    public function test_buka_kas_wajib_clock_in_terlebih_dahulu(): void
    {
        $user = $this->buatUser();
        $karyawan = $this->buatKaryawan($user, 'kasir');
        $kasSesi = app(KasSesiState::class);

        // Tanpa clock-in → gate menolak
        try {
            $kasSesi->bukaKas(100000, 1, $user->id);
            $this->fail('Seharusnya buka kas ditolak tanpa clock-in');
        } catch (\Exception $e) {
            $this->assertStringContainsString('clock-in', strtolower($e->getMessage()));
        }

        // Setelah clock-in → berhasil
        app(AbsensiService::class)->clockIn($karyawan->id);
        $hasil = $kasSesi->bukaKas(100000, 1, $user->id);
        $this->assertArrayHasKey('id', $hasil);
        $this->assertNotEmpty($hasil['id']);
    }

    public function test_tutup_kas_saran_clock_out_otomatis(): void
    {
        $user = $this->buatUser();
        $karyawan = $this->buatKaryawan($user, 'kasir');
        $kasSesi = app(KasSesiState::class);
        $this->actingAs($user);

        app(AbsensiService::class)->clockIn($karyawan->id);
        $kasSesi->bukaKas(100000, 1, $user->id);

        // Tutup dengan saldo sama → selisih 0, jam_keluar terisi otomatis
        $kasSesi->tutupKas(100000);

        $log = AbsensiLog::first();
        $this->assertNotNull($log->jam_keluar);
        $this->assertStringContainsString('Clock-out otomatis', $log->catatan);
    }

    public function test_non_karyawan_tidak_diblokir_buka_kas(): void
    {
        $user = $this->buatUser('owner@test.local');
        // User tanpa record karyawan → tidak diblokir gate absensi

        $hasil = app(KasSesiState::class)->bukaKas(100000, 1, $user->id);
        $this->assertArrayHasKey('id', $hasil);
    }
}
