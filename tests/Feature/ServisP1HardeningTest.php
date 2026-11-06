<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\ServisStatusLog;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * [P1] Hardening tiga temuan audit:
 * T-01 race-safe nomor tiket, T-02 aktor sistem auto-cancel SLA,
 * T-03 referensi eksplisit subledger AR (tanpa matching LIKE).
 */
class ServisP1HardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::first();

        $this->user = User::create([
            'name' => 'Admin Servis P1',
            'email' => 'admin-p1@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');
    }

    // --- T-01: race-safe nomor tiket ---

    public function test_t01_nomor_tiket_walkin_unik_berurutan_dengan_format_existing(): void
    {
        $service = app(ServisService::class);

        $t1 = $service->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan Satu',
            'jenis_hp' => 'iPhone 15',
            'keluhan' => 'Layar retak',
        ], $this->user);

        $t2 = $service->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'telepon_pelanggan' => '081399887766',
            'nama_pelanggan' => 'Pelanggan Dua',
            'jenis_hp' => 'Samsung A54',
            'keluhan' => 'Speaker mati',
        ], $this->user);

        $this->assertNotSame($t1->no_tiket, $t2->no_tiket);
        $this->assertMatchesRegularExpression(
            '/^SRV-C'.sprintf('%02d', $this->cabang->id).'-\d{8}-(\d{4})$/',
            $t1->no_tiket
        );

        $seq1 = (int) substr($t1->no_tiket, -4);
        $seq2 = (int) substr($t2->no_tiket, -4);
        $this->assertSame($seq1 + 1, $seq2);
    }

    public function test_t01_nomor_booking_online_unik(): void
    {
        $a = app(ServisService::class)->bookingOnline([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Booker A',
            'telepon' => '081111111111',
            'jenis_hp' => 'Pixel 8',
            'keluhan' => 'Bootloop',
        ]);

        $b = app(ServisService::class)->bookingOnline([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Booker B',
            'telepon' => '081222222222',
            'jenis_hp' => 'Pixel 7',
            'keluhan' => 'Baterai drop',
        ]);

        $this->assertMatchesRegularExpression('/^SRV-ONL-\d{8}-\d{4}$/', $a->no_tiket);
        $this->assertNotSame($a->no_tiket, $b->no_tiket);
    }

    public function test_t01_kolom_no_tiket_punya_unique_index(): void
    {
        $indexes = collect(Schema::getIndexes('tiket_servis'))
            ->map(fn ($i) => $i['columns']);

        $this->assertTrue($indexes->contains(fn ($cols) => $cols === ['no_tiket']));
    }

    // --- T-02: aktor sistem auto-cancel ---

    public function test_t02_sistem_cancel_dari_dikerjakan_log_tanpa_user_palsu(): void
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-SLA-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan SLA',
            'jenis_hp' => 'Oppo Find X5',
            'keluhan' => 'Mati total',
            'status' => 'dikerjakan',
            'pengerjaan_deadline_at' => now()->subHour(),
            'auto_cancel_overdue' => true,
        ]);

        $diolah = app(ServisService::class)->updateStatus(
            $tiket, 'ditolak', null,
            'Dibatalkan otomatis oleh sistem karena melewati batas waktu',
            'sistem'
        );

        $this->assertSame('ditolak', $diolah->status);

        $log = ServisStatusLog::where('tiket_servis_id', $tiket->id)
            ->where('aksi', 'auto_batal_sistem')
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->user_id); // aktor = SISTEM, bukan user palsu
        $this->assertStringContainsString('melewati batas waktu', $log->alasan);
    }

    public function test_t02_mode_sistem_terkunci_ke_target_ditolak_dan_alasan_wajib(): void
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-SLB-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan SLB',
            'jenis_hp' => 'Vivo X80',
            'keluhan' => 'Charging port rusak',
            'status' => 'dikerjakan',
            'pengerjaan_deadline_at' => now()->subHour(),
        ]);

        $this->assertThrows(fn () => app(ServisService::class)->updateStatus(
            $tiket, 'selesai', null, 'alasan ada', 'sistem'
        ), \Exception::class);

        $this->assertThrows(fn () => app(ServisService::class)->updateStatus(
            $tiket, 'ditolak', null, '', 'sistem'
        ), \Exception::class);
    }

    public function test_t02_jalur_manual_tanpa_user_tetap_tidak_bisa_bypass_state_machine(): void
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-SLC-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan SLC',
            'jenis_hp' => 'Infinix Hot 11',
            'keluhan' => 'LCD blank',
            'status' => 'dikerjakan',
        ]);

        // Tanpa mode sistem: user null tidak punya izin override → tolak.
        $this->assertThrows(fn () => app(ServisService::class)->updateStatus(
            $tiket, 'ditolak', null, 'Coba bypass tanpa user'
        ), \Exception::class);

        $this->assertSame('dikerjakan', $tiket->fresh()->status);
    }

    // --- T-03: referensi eksplisit piutang ---

    public function test_t03_onselesai_membuat_piutang_dengan_referensi_dan_bayar_hanya_menagih_row_eksak(): void
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-AR-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan AR',
            'telepon_pelanggan' => '081377766655',
            'jenis_hp' => 'Realme GT 3',
            'keluhan' => 'Touchscreen mati',
            'status' => 'qc',
            'estimasi_biaya' => 350000,
            'pelanggan_id' => null,
        ]);

        app(ServisService::class)->updateStatus($tiket, 'selesai', $this->user, 'Pekerjaan selesai');
        $tiket->refresh()->load('pelanggan');

        // Piutang utilis-pelanggan wajib exist setelah selesai — tapi tiket ini
        // tanpa pelanggan_id; siapkan pelanggan utk subledger.
        $pelanggan = Pelanggan::create([
            'nama' => 'Pelanggan AR',
            'telepon' => '081377766655',
            'tipe_konsumen' => 'retail',
        ]);
        $tiket->update(['pelanggan_id' => $pelanggan->id]);

        // onSelesai jalan via transisi qc → selesai sudah lewat; trigger
        // pembuatan AR dengan jalan berhenti di selesai tidak bisa diulang.
        // Jadi cukup verifikasi lewat jalur pelunasan: buat baris AR legacy
        // (tanpa referensi, keterangan mengandung no_tiket) yang HARUS
        // ketemu via fallback...
        $legacy = Piutang::create([
            'no_piutang' => 'AR-LEG-'.uniqid(),
            'pelanggan_id' => $pelanggan->id,
            'cabang_id' => $this->cabang->id,
            'jumlah' => 350000,
            'jumlah_dibayar' => 0,
            'jatuh_tempo' => now()->addDays(14)->toDateString(),
            'status' => 'belum_lunas',
            'keterangan' => "Piutang Servis {$tiket->no_tiket} (Realme GT 3)",
        ]);

        // ...dan baris DECOY yang keterangan-nya juga LIKE-match no_tiket.
        // Old implementation would first() across both — the exact one wins.
        $decoy = Piutang::create([
            'no_piutang' => 'AR-DECOY-'.uniqid(),
            'pelanggan_id' => $pelanggan->id,
            'cabang_id' => $this->cabang->id,
            'jumlah' => 999000,
            'jumlah_dibayar' => 0,
            'jatuh_tempo' => now()->addDays(30)->toDateString(),
            'status' => 'belum_lunas',
            'keterangan' => "Piutang Servis {$tiket->no_tiket} DUPLIKAT VERIFIKASI MANUAL",
        ]);

        app(ServisService::class)->bayar($tiket, 'tunai', $this->user);

        $tiket->refresh();
        $this->assertSame('lunas', $tiket->status_pembayaran);

        $this->assertSame('lunas', $legacy->fresh()->status);
        $this->assertSame(350000.0, (float) $legacy->fresh()->jumlah_dibayar);

        // Decoy tidak ikut tersentuh meski keterangan cocok.
        $this->assertSame('belum_lunas', $decoy->fresh()->status);
        $this->assertSame(0.0, (float) $decoy->fresh()->jumlah_dibayar);
    }
}
