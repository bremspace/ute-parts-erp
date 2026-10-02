<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hr\Models\AbsensiLog;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KaryawanKomponenGaji;
use App\Modules\Hr\Models\KomisiTeknisiRule;
use App\Modules\Hr\Models\KpiHasil;
use App\Modules\Hr\Models\KpiMetric;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Hr\Models\PayrollSlip;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\ShiftJadwal;
use App\Modules\Rbac\Livewire\RiwayatAktivitas;
use App\Modules\Rbac\Models\AktivitasLog;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Audit trail modul HR — pemilik WAJIB bisa menemukan setiap CRUD payroll,
 * absensi, KPI, dan jadwal shift.
 *
 * Tiap model HR memakai `CatatAktivitas` (bukan `LogOptions::defaults()` polos)
 * supaya: (1) activity log dapat label entitas Bahasa Indonesia, (2) `cabang_id`
 * tersimpan ke baris log sehingga filter per cabang tidak bocor lintas cabang,
 * (3) `logOnlyDirty()` agar tidak memboroskan storage di server 1GB.
 *
 * Catatan repo: method wajib prefix test_ (PHPUnit 12 tidak deteksi @test docblock).
 */
class HrAuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Karyawan $karyawan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'HR01', 'nama' => 'Cabang HR', 'is_active' => true,
        ]);

        $this->karyawan = Karyawan::create([
            'nik' => 'NIK-'.Str::random(6), 'nama' => 'Budi Santoso', 'jabatan' => 'teknisi',
            'cabang_id' => $this->cabang->id, 'tgl_masuk' => '2026-01-01', 'gaji_pokok' => 3000000,
        ]);

        session(['cabang_id' => $this->cabang->id]);
    }

    private function userAuthed(string $role): User
    {
        $user = User::create([
            'name' => 'Uji '.ucfirst($role), 'email' => Str::slug($role).'-'.Str::random(6).'@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole($role);
        $this->actingAs($user, 'web');

        return $user;
    }

    private function shift(): Shift
    {
        return Shift::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Shift Pagi',
            'jam_mulai' => '07:00:00', 'jam_selesai' => '15:00:00', 'is_aktif' => true,
        ]);
    }

    /** Baris log terakhir untuk subject tertentu. */
    private function logTerakhir(string $modelClass, int $subjectId): ?AktivitasLog
    {
        return AktivitasLog::query()
            ->where('subject_type', $modelClass)
            ->where('subject_id', $subjectId)
            ->orderByDesc('id')
            ->first();
    }

    /** ===== Karyawan ===== */
    public function test_karyawan_create_tercatat_di_audit_log(): void
    {
        $this->userAuthed('super-admin');

        $k = Karyawan::create([
            'nik' => 'NIK-BARU', 'nama' => 'Siti Aminah', 'jabatan' => 'kasir',
            'cabang_id' => $this->cabang->id, 'tgl_masuk' => '2026-02-01', 'gaji_pokok' => 2500000,
        ]);

        $log = $this->logTerakhir(Karyawan::class, $k->id);
        $this->assertNotNull($log, 'Karyawan create harus masuk activity log');
        $this->assertSame('created', $log->event);
    }

    public function test_karyawan_update_tercatat_di_audit_log(): void
    {
        $this->userAuthed('super-admin');

        $this->karyawan->update(['gaji_pokok' => 4500000, 'jabatan' => 'admin']);

        $log = $this->logTerakhir(Karyawan::class, $this->karyawan->id);
        $this->assertNotNull($log);
        $this->assertSame('updated', $log->event);

        // Nilai salary lama harus tersimpan agar owner bisa membandingkan.
        $changes = $log->attribute_changes['old'] ?? [];
        $this->assertArrayHasKey('gaji_pokok', $changes);
        $this->assertSame('3000000.00', (string) $changes['gaji_pokok']);
    }

    public function test_karyawan_delete_tercatat_di_audit_log(): void
    {
        $this->userAuthed('super-admin');

        $k = Karyawan::create([
            'nik' => 'NIK-HAPUS', 'nama' => 'Temp', 'jabatan' => 'other',
            'cabang_id' => $this->cabang->id, 'tgl_masuk' => '2026-03-01',
        ]);
        $id = $k->id;
        $k->delete();

        $log = $this->logTerakhir(Karyawan::class, $id);
        $this->assertNotNull($log, 'Karyawan delete harus masuk activity log');
        $this->assertSame('deleted', $log->event);
    }

    public function test_label_entitas_karyawan_bahasa_indonesia(): void
    {
        $this->userAuthed('super-admin');

        $this->karyawan->update(['status_aktif' => false]);

        $this->assertSame('Data Karyawan diperbarui', $this->logTerakhir(Karyawan::class, $this->karyawan->id)->description);
    }

    public function test_perubahan_karyawan_terlihat_oleh_super_admin(): void
    {
        $superAdmin = $this->userAuthed('super-admin');

        $this->karyawan->update(['nama' => 'Budi Diperbarui']);

        $log = $this->logTerakhir(Karyawan::class, $this->karyawan->id);
        $this->assertNotNull($log);
        // Super-admin harus bisa tahu SIAPA yang mengubah, bukan hanya bahwa ada perubahan.
        $this->assertSame($superAdmin->id, $log->causer_id);
        $this->assertSame($superAdmin->name, $log->causerNama);
    }

    public function test_nomor_rekening_tidak_bocor_ke_audit_log(): void
    {
        $this->userAuthed('super-admin');

        $this->karyawan->update(['rekening_bank' => '1234567890', 'nama' => 'Budi Ganti Rekening']);

        $log = $this->logTerakhir(Karyawan::class, $this->karyawan->id);

        // Perubahan rekening TIDAK boleh menaruh nomor rekening di snapshot.
        $this->assertStringNotContainsString(
            '1234567890',
            json_encode($log->attribute_changes, JSON_UNESCAPED_UNICODE),
            'Nomor rekening employee tidak boleh tersimpan di activity log'
        );
    }

    public function test_cabang_id_tersimpan_di_baris_log_karyawan(): void
    {
        $this->userAuthed('super-admin');

        $this->karyawan->update(['nama' => 'Budi Pindah Nama']);

        $log = $this->logTerakhir(Karyawan::class, $this->karyawan->id);
        $this->assertSame($this->cabang->id, $log->cabang_id, 'Log harus bisa disaring per cabang');
    }

    /** ===== Payroll ===== */
    public function test_payroll_periode_create_update_delete_tercatat(): void
    {
        $this->userAuthed('super-admin');

        $periode = PayrollPeriode::create([
            'periode' => '2026-01', 'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-01-31', 'status' => 'draft',
        ]);
        $this->assertSame('created', $this->logTerakhir(PayrollPeriode::class, $periode->id)->event);

        $periode->update(['status' => 'dibayar']);
        $this->assertSame('updated', $this->logTerakhir(PayrollPeriode::class, $periode->id)->event);

        $periode->delete();
        $this->assertSame('deleted', $this->logTerakhir(PayrollPeriode::class, $periode->id)->event);
    }

    public function test_label_entitas_periode_payroll(): void
    {
        $this->userAuthed('super-admin');

        $periode = PayrollPeriode::create([
            'periode' => '2026-02', 'tanggal_mulai' => '2026-02-01', 'tanggal_selesai' => '2026-02-28',
        ]);

        $this->assertSame('Periode Payroll dibuat', $this->logTerakhir(PayrollPeriode::class, $periode->id)->description);
    }

    public function test_payroll_slip_create_update_tercatat(): void
    {
        $this->userAuthed('super-admin');

        $periode = PayrollPeriode::create([
            'periode' => '2026-03', 'tanggal_mulai' => '2026-03-01', 'tanggal_selesai' => '2026-03-31',
        ]);

        $slip = PayrollSlip::create([
            'payroll_periode_id' => $periode->id, 'karyawan_id' => $this->karyawan->id,
            'gaji_pokok' => 3000000, 'total_tunjangan' => 500000, 'total_gaji' => 3500000,
        ]);
        $this->assertSame('created', $this->logTerakhir(PayrollSlip::class, $slip->id)->event);

        $slip->update(['total_gaji' => 4000000]);
        $log = $this->logTerakhir(PayrollSlip::class, $slip->id);
        $this->assertSame('updated', $log->event);
        $this->assertSame('Slip Gaji diperbarui', $log->description);
    }

    public function test_rincian_json_tidak_membanjiri_audit_log(): void
    {
        $this->userAuthed('super-admin');

        $periode = PayrollPeriode::create([
            'periode' => '2026-04', 'tanggal_mulai' => '2026-04-01', 'tanggal_selesai' => '2026-04-30',
        ]);

        $slip = PayrollSlip::create([
            'payroll_periode_id' => $periode->id, 'karyawan_id' => $this->karyawan->id,
            'total_gaji' => 3500000,
            'rincian' => ['tunjangan' => ['makan' => 300000, 'transport' => 200000], 'potongan' => ['bpjs' => 150000]],
        ]);

        $log = $this->logTerakhir(PayrollSlip::class, $slip->id);
        $attributes = $log->attribute_changes['attributes'] ?? [];

        $this->assertArrayNotHasKey('rincian', $attributes, 'Rincian JSON tidak ikut dilog');
        $this->assertArrayHasKey('total_gaji', $attributes, 'Total gaji tetap harus terlacak');
    }

    public function test_karyawan_komponen_gaji_crud_tercatat(): void
    {
        $this->userAuthed('super-admin');

        $komponen = KaryawanKomponenGaji::create([
            'karyawan_id' => $this->karyawan->id, 'tipe' => 'tunjangan',
            'nama' => 'Tunjangan Transport', 'nominal_bulanan' => 500000, 'is_aktif' => true,
        ]);
        $log = $this->logTerakhir(KaryawanKomponenGaji::class, $komponen->id);
        $this->assertSame('created', $log->event);
        $this->assertSame('Komponen Gaji Karyawan dibuat', $log->description);

        $komponen->update(['nominal_bulanan' => 700000]);
        $this->assertSame('updated', $this->logTerakhir(KaryawanKomponenGaji::class, $komponen->id)->event);

        $komponen->delete();
        $this->assertSame('deleted', $this->logTerakhir(KaryawanKomponenGaji::class, $komponen->id)->event);
    }

    public function test_komisi_teknisi_rule_crud_tercatat(): void
    {
        $this->userAuthed('super-admin');

        $rule = KomisiTeknisiRule::create([
            'cabang_id' => $this->cabang->id, 'jabatan_target' => 'teknisi',
            'jenis' => 'per_tiket', 'nominal' => 25000, 'is_aktif' => true,
        ]);
        $log = $this->logTerakhir(KomisiTeknisiRule::class, $rule->id);
        $this->assertSame('created', $log->event);
        $this->assertSame('Aturan Komisi Teknisi dibuat', $log->description);
        $this->assertSame($this->cabang->id, $log->cabang_id);

        $rule->update(['nominal' => 35000]);
        $this->assertSame('updated', $this->logTerakhir(KomisiTeknisiRule::class, $rule->id)->event);

        $rule->delete();
        $this->assertSame('deleted', $this->logTerakhir(KomisiTeknisiRule::class, $rule->id)->event);
    }

    /** ===== Absensi ===== */
    public function test_absensi_create_dan_update_tercatat(): void
    {
        $this->userAuthed('super-admin');

        $absensi = AbsensiLog::create([
            'karyawan_id' => $this->karyawan->id, 'tanggal' => '2026-05-04',
            'jam_masuk' => '07:05:00', 'status' => 'hadir',
        ]);
        $log = $this->logTerakhir(AbsensiLog::class, $absensi->id);
        $this->assertSame('created', $log->event);
        $this->assertSame('Log Absensi dibuat', $log->description);

        // Koreksi manual oleh HR = perubahan yang wajib bisa diaudit.
        $absensi->update(['status' => 'izin']);
        $log = $this->logTerakhir(AbsensiLog::class, $absensi->id);
        $this->assertSame('updated', $log->event);
        $this->assertSame('izin', $log->attribute_changes['attributes']['status'] ?? null);
        $this->assertSame('hadir', $log->attribute_changes['old']['status'] ?? null);
    }

    public function test_lokasi_dan_foto_tidak_masuk_audit_log(): void
    {
        $this->userAuthed('super-admin');

        $absensi = AbsensiLog::create([
            'karyawan_id' => $this->karyawan->id, 'tanggal' => '2026-05-05',
            'status' => 'hadir', 'lokasi' => '-6.200000,106.816666',
            'self_photo' => 'absensi/budi-2026-05-05.jpg',
        ]);

        $attributes = $this->logTerakhir(AbsensiLog::class, $absensi->id)->attribute_changes['attributes'] ?? [];

        $this->assertArrayNotHasKey('lokasi', $attributes, 'Koordinat GPS employee tidak boleh dilog');
        $this->assertArrayNotHasKey('self_photo', $attributes, 'Path foto tidak boleh dilog');
        $this->assertArrayHasKey('status', $attributes);
    }

    public function test_absensi_tidak_menulis_log_saat_dibuat_ulang_tanpa_perubahan(): void
    {
        $this->userAuthed('super-admin');

        $absensi = AbsensiLog::create([
            'karyawan_id' => $this->karyawan->id, 'tanggal' => '2026-05-06', 'status' => 'hadir',
        ]);
        $jumlahAwal = AktivitasLog::where('subject_type', AbsensiLog::class)
            ->where('subject_id', $absensi->id)->count();

        // Re-save identik (mis. sync ulang dari ponsel) tidak boleh menambah log.
        $absensi->update(['status' => 'hadir']);

        $this->assertSame(
            $jumlahAwal,
            AktivitasLog::where('subject_type', AbsensiLog::class)->where('subject_id', $absensi->id)->count(),
            'Re-save tanpa perubahan tidak boleh membanjiri audit trail'
        );
    }

    /** ===== KPI & Shift ===== */
    public function test_kpi_hasil_create_dan_update_tercatat(): void
    {
        $this->userAuthed('super-admin');

        $metric = KpiMetric::create(['kode' => 'tiket_selesai', 'nama' => 'Tiket Selesai', 'rumus' => 'tiket_selesai']);

        $hasil = KpiHasil::create([
            'karyawan_id' => $this->karyawan->id, 'kpi_metric_id' => $metric->id,
            'periode' => '2026-05', 'nilai_aktual' => 40, 'persen_capaian' => 133.33,
        ]);
        $log = $this->logTerakhir(KpiHasil::class, $hasil->id);
        $this->assertSame('created', $log->event);
        $this->assertSame('Hasil KPI dibuat', $log->description);

        $hasil->update(['nilai_aktual' => 45]);
        $this->assertSame('updated', $this->logTerakhir(KpiHasil::class, $hasil->id)->event);
    }

    public function test_shift_jadwal_crud_tercatat(): void
    {
        $this->userAuthed('super-admin');
        $shift = $this->shift();

        $jadwal = ShiftJadwal::create([
            'cabang_id' => $this->cabang->id, 'karyawan_id' => $this->karyawan->id,
            'shift_id' => $shift->id, 'tanggal' => '2026-06-01',
        ]);
        $log = $this->logTerakhir(ShiftJadwal::class, $jadwal->id);
        $this->assertSame('created', $log->event);
        $this->assertSame('Jadwal Shift dibuat', $log->description);
        $this->assertSame($this->cabang->id, $log->cabang_id);

        $jadwal->update(['shift_id' => $shift->id, 'tanggal' => '2026-06-02']);
        $this->assertSame('updated', $this->logTerakhir(ShiftJadwal::class, $jadwal->id)->event);

        $jadwal->delete();
        $this->assertSame('deleted', $this->logTerakhir(ShiftJadwal::class, $jadwal->id)->event);
    }

    /**
     * [F1-4] Key HR di `RiwayatAktivitas::TIPE` harus benar-benar bisa
     * DILEWATI end-to-end: buka log per entitas + judulnya terbaca.
     *
     * Tanpa test ini, key HR bisa terdaftar tapi `judul` salah (mis. `tanggal`
     * bukan Carbon, atau relasi `periode` null) dan owner tidak pernah sadar.
     */
    public function test_semua_key_hr_bisa_dibuka_lewat_riwayat_aktivitas(): void
    {
        $this->userAuthed('owner');

        $shift = $this->shift();
        $karyawan = $this->karyawan;
        $periode = PayrollPeriode::create([
            'periode' => '2026-06', 'status' => 'draft',
            'tanggal_mulai' => '2026-06-01', 'tanggal_selesai' => '2026-06-30',
        ]);
        $slip = PayrollSlip::create([
            'payroll_periode_id' => $periode->id, 'karyawan_id' => $karyawan->id,
            'gaji_pokok' => 5000000, 'total_gaji' => 5000000, 'status' => 'draft',
        ]);
        $komponen = KaryawanKomponenGaji::create([
            'karyawan_id' => $karyawan->id, 'nama' => 'Tunjangan Makan',
            'tipe' => 'tunjangan', 'nominal_bulanan' => 500000,
        ]);
        $absensi = AbsensiLog::create([
            'karyawan_id' => $karyawan->id, 'tanggal' => '2026-06-01', 'status' => 'hadir',
        ]);
        $kpiMetric = KpiMetric::create([
            'kode' => 'TIKET', 'nama' => 'Tiket Selesai', 'rumus' => 'tiket_selesai',
            'target' => 20, 'satuan' => 'unit', 'periode' => 'bulanan', 'is_aktif' => true,
        ]);
        $kpi = KpiHasil::create([
            'karyawan_id' => $karyawan->id, 'kpi_metric_id' => $kpiMetric->id,
            'periode' => '2026-06', 'nilai_aktual' => 10, 'persen_capaian' => 80,
        ]);
        $jadwal = ShiftJadwal::create([
            'cabang_id' => $this->cabang->id, 'karyawan_id' => $karyawan->id,
            'shift_id' => $shift->id, 'tanggal' => '2026-06-01',
        ]);

        $kasus = [
            'karyawan' => [$karyawan, $karyawan->nama],
            'payroll_periode' => [$periode, 'Periode 2026-06'],
            'payroll_slip' => [$slip, $karyawan->nama],
            'komponen_gaji' => [$komponen, 'Tunjangan Makan'],
            'absensi' => [$absensi, '2026-06-01'],
            'kpi_hasil' => [$kpi, $karyawan->nama],
            'shift_jadwal' => [$jadwal, '2026-06-01'],
        ];

        foreach ($kasus as $tipe => [$model, $harapan]) {
            $c = Livewire::test(RiwayatAktivitas::class, ['tipe' => $tipe, 'entityId' => $model->id]);

            $this->assertSame('ok', $c->viewData('akses'), "akses {$tipe}");
            $judul = $c->viewData('judul');
            $this->assertNotSame('', $judul, "judul {$tipe} kosong");
            $this->assertStringContainsString($harapan, $judul, "judul {$tipe}");
            $this->assertGreaterThan(0, $c->viewData('aktivitas')->total(), "tidak ada log untuk {$tipe}");
        }
    }
}
