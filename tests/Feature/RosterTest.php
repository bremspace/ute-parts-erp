<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\ShiftJadwal;
use Database\Seeders\CabangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CabangSeeder::class);
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

    private function buatShift(): Shift
    {
        return Shift::create([
            'cabang_id' => 1,
            'nama' => 'Pagi',
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '16:00:00',
            'is_aktif' => true,
        ]);
    }

    public function test_assign_roster_idempotent_per_karyawan_tanggal(): void
    {
        $karyawan = $this->buatKaryawan($this->buatUser('a@test.local'));
        $shift = $this->buatShift();

        ShiftJadwal::updateOrCreate(
            ['cabang_id' => 1, 'karyawan_id' => $karyawan->id, 'tanggal' => '2026-09-24'],
            ['shift_id' => $shift->id]
        );
        ShiftJadwal::updateOrCreate(
            ['cabang_id' => 1, 'karyawan_id' => $karyawan->id, 'tanggal' => '2026-09-24'],
            ['shift_id' => $shift->id]
        );

        $this->assertEquals(1, ShiftJadwal::count());
        $this->assertEquals($shift->id, ShiftJadwal::first()->shift_id);
    }

    public function test_dua_karyawan_satu_tanggal_boleh_berbeda_shift(): void
    {
        $k1 = $this->buatKaryawan($this->buatUser('b@test.local'));
        $k2 = $this->buatKaryawan($this->buatUser('c@test.local'), 'admin');
        $pagi = $this->buatShift();
        $malam = Shift::create([
            'cabang_id' => 1,
            'nama' => 'Malam',
            'jam_mulai' => '22:00:00',
            'jam_selesai' => '06:00:00',
            'is_aktif' => true,
        ]);

        ShiftJadwal::updateOrCreate(
            ['cabang_id' => 1, 'karyawan_id' => $k1->id, 'tanggal' => '2026-09-24'],
            ['shift_id' => $pagi->id]
        );
        ShiftJadwal::updateOrCreate(
            ['cabang_id' => 1, 'karyawan_id' => $k2->id, 'tanggal' => '2026-09-24'],
            ['shift_id' => $malam->id]
        );

        $this->assertEquals(2, ShiftJadwal::count());
        $this->assertEquals(1, ShiftJadwal::where('karyawan_id', $k1->id)->whereDate('tanggal', '2026-09-24')->count());
        $this->assertEquals(1, ShiftJadwal::where('karyawan_id', $k2->id)->whereDate('tanggal', '2026-09-24')->count());
    }

    public function test_isolasi_cabang_roster(): void
    {
        $karyawan = $this->buatKaryawan($this->buatUser('d@test.local'));
        $shift = $this->buatShift();

        // Assign di cabang 1
        ShiftJadwal::updateOrCreate(
            ['cabang_id' => 1, 'karyawan_id' => $karyawan->id, 'tanggal' => '2026-09-24'],
            ['shift_id' => $shift->id]
        );

        // Cabang 2 (dari seed) tidak melihat roster cabang 1
        $this->assertEquals(0, ShiftJadwal::where('cabang_id', 2)->count());
        $this->assertEquals(1, ShiftJadwal::where('cabang_id', 1)->count());
    }
}
