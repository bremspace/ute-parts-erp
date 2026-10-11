<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KpiMetric;
use App\Modules\Hr\Services\KpiService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ServisKpiBatasWaktuDanProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $teknisi;

    private Cabang $cabang;

    private JenisServis $jenisServis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang SLA Test',
            'kode' => 'CBG-SLA',
            'is_active' => true,
        ]);

        $this->teknisi = User::create([
            'name' => 'Teknisi Handal',
            'email' => 'teknisi-sla@test.com',
            'password' => Hash::make('password'),
            'cabang_id' => $this->cabang->id,
            'phone' => '081234567890',
        ]);
        $this->teknisi->assignRole('teknisi');

        $this->jenisServis = JenisServis::create([
            'nama' => 'Ganti LCD Fast Track',
            'kode' => 'SVC-LCD-FAST',
            'biaya_jasa' => 100000,
            'durasi_garansi_hari' => 30,
            'estimasi_durasi' => 60,
            'batas_waktu_menit' => 90,
            'peringatan_menit' => 30,
            'auto_cancel_jika_overdue' => true,
            'is_active' => true,
        ]);

        session(['cabang_id' => $this->cabang->id]);
    }

    public function test_estimasi_dapat_mengatur_batas_waktu_manual_dan_auto_cancel(): void
    {
        $this->actingAs($this->teknisi, 'web');

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-SLA-001',
            'cabang_id' => $this->cabang->id,
            'teknisi_id' => $this->teknisi->id,
            'jenis_servis_id' => $this->jenisServis->id,
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Layar blank',
            'status' => 'diagnosa',
        ]);

        // Simpan estimasi dengan batas waktu 45 menit (override dari 90 menit master)
        app(ServisService::class)->setEstimasi(
            $tiket,
            120000,
            'Ganti LCD & flexibel',
            $this->teknisi,
            [['tipe' => 'jasa', 'nama_item' => 'Jasa Ganti LCD', 'qty' => 1, 'harga' => 120000]],
            45,
            true
        );

        $tiket->refresh();
        $this->assertSame(45, $tiket->batas_waktu_menit);
        $this->assertTrue($tiket->auto_cancel_overdue);
        $this->assertSame('menunggu_approval', $tiket->status);
    }

    public function test_pengerjaan_mulai_menghitung_deadline_dan_kpi_on_time_saat_selesai(): void
    {
        $this->actingAs($this->teknisi, 'web');

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-SLA-002',
            'cabang_id' => $this->cabang->id,
            'teknisi_id' => $this->teknisi->id,
            'jenis_servis_id' => $this->jenisServis->id,
            'jenis_hp' => 'iPhone 14',
            'keluhan' => 'Ganti Baterai',
            'status' => 'disetujui',
            'batas_waktu_menit' => 60,
        ]);

        // Transisi ke dikerjakan -> qc -> selesai
        app(ServisService::class)->updateStatus($tiket, 'dikerjakan', $this->teknisi, 'Mulai perbaikan');
        $tiket->refresh();

        $this->assertNotNull($tiket->pengerjaan_mulai_at);
        $this->assertNotNull($tiket->pengerjaan_deadline_at);
        $this->assertTrue($tiket->pengerjaan_deadline_at->greaterThan($tiket->pengerjaan_mulai_at));

        app(ServisService::class)->updateStatus($tiket, 'qc', $this->teknisi, 'Pekerjaan masuk tahap uji QC');
        $tiket->refresh();

        // Transisi ke selesai sebelum deadline -> status_sla_kpi = on_time
        app(ServisService::class)->updateStatus($tiket, 'selesai', $this->teknisi, 'Pekerjaan selesai lolos QC');
        $tiket->refresh();

        $this->assertSame('selesai', $tiket->status);
        $this->assertSame('on_time', $tiket->status_sla_kpi);
        $this->assertNotNull($tiket->durasi_pengerjaan_menit);
    }

    public function test_teknisi_bisa_upload_progress_kerja_dan_tampil_di_tracking(): void
    {
        $this->actingAs($this->teknisi, 'web');

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-SLA-003',
            'cabang_id' => $this->cabang->id,
            'teknisi_id' => $this->teknisi->id,
            'jenis_hp' => 'Xiaomi 13',
            'keluhan' => 'Kamera buram',
            'status' => 'dikerjakan',
            'token_approval' => 'token-test-progress-123',
        ]);

        // Input progress via ServisService
        $progress = app(ServisService::class)->catatProgress(
            $tiket,
            $this->teknisi,
            75,
            'Modul kamera baru berhasil dipasang, sedang tahap kalibrasi',
            'Pembersihan lensa dalam dan tes sensor fokus',
            ['data:image/png;base64,sample-foto-progress'],
            true
        );

        $this->assertDatabaseHas('tiket_servis_progress', [
            'tiket_servis_id' => $tiket->id,
            'persentase' => 75,
            'judul' => 'Modul kamera baru berhasil dipasang, sedang tahap kalibrasi',
            'tampilkan_ke_konsumen' => true,
        ]);

        // Web tracking konsumen harus merender progress
        $response = $this->get('/tracking/token-test-progress-123');
        $response->assertOk();
        $response->assertSee('75% Selesai');
        $response->assertSee('Modul kamera baru berhasil dipasang');
    }

    public function test_command_cek_batas_waktu_mengeksekusi_auto_cancel_jika_overdue(): void
    {
        $admin = User::create([
            'name' => 'Super Admin Test',
            'email' => 'admin-sla@test.com',
            'password' => Hash::make('password'),
            'cabang_id' => $this->cabang->id,
        ]);
        $admin->assignRole('super-admin');

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-SLA-004',
            'cabang_id' => $this->cabang->id,
            'teknisi_id' => $this->teknisi->id,
            'jenis_servis_id' => $this->jenisServis->id,
            'jenis_hp' => 'Oppo Reno',
            'keluhan' => 'Mati total',
            'status' => 'dikerjakan',
            'batas_waktu_menit' => 60,
            'auto_cancel_overdue' => true,
            'pengerjaan_mulai_at' => now()->subHours(2),
            'pengerjaan_deadline_at' => now()->subHour(), // Lewat 1 jam (overdue)
        ]);

        $this->artisan('servis:cek-batas-waktu')->assertSuccessful();

        $tiket->refresh();
        $this->assertSame('ditolak', $tiket->status, 'Tiket overdue dengan auto_cancel wajib dibatalkan ke ditolak');
        $this->assertSame('cancelled_overdue', $tiket->status_sla_kpi);
    }

    public function test_kpi_service_menghitung_metrik_sla_ontime_teknisi(): void
    {
        $karyawan = Karyawan::create([
            'user_id' => $this->teknisi->id,
            'cabang_id' => $this->cabang->id,
            'nik' => 'TEK-001',
            'nama' => 'Teknisi Handal',
            'jabatan' => 'teknisi',
            'tgl_masuk' => now()->subYear()->toDateString(),
            'status_aktif' => true,
        ]);

        // Tiket on_time bulan ini
        TiketServis::create([
            'no_tiket' => 'SRV-KPI-01',
            'cabang_id' => $this->cabang->id,
            'teknisi_id' => $this->teknisi->id,
            'jenis_hp' => 'Realme 7',
            'keluhan' => 'Baterai kembung',
            'status' => 'selesai',
            'status_sla_kpi' => 'on_time',
            'durasi_pengerjaan_menit' => 45,
            'tanggal_selesai' => now(),
        ]);

        $metric = KpiMetric::create([
            'kode' => 'SLA_ONTIME',
            'nama' => 'SLA Servis On-Time',
            'jabatan' => 'teknisi',
            'rumus' => 'sla_servis_ontime',
            'target' => 1,
            'bobot' => 100,
            'satuan' => 'tiket',
            'is_aktif' => true,
        ]);

        $kpiService = app(KpiService::class);
        $periode = now()->format('Y-m');

        $hasil = $kpiService->hitungKaryawan($karyawan, $metric, $periode);

        $this->assertEquals(1.0, (float) $hasil->nilai_aktual);
        $this->assertEquals(100.0, (float) $hasil->persen_capaian);
    }
}
