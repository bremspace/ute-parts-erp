<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Services\ServisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServisTrackingKonsumenTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected ServisService $servisService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Test',
            'kode' => 'TST',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'name' => 'Teknisi Test',
            'email' => 'teknisi@test.id',
        ]);
        $this->user->cabangs()->attach($this->cabang->id);

        $this->servisService = app(ServisService::class);
    }

    public function test_terima_unit_menghasilkan_token_approval_otomatis(): void
    {
        $tiket = $this->servisService->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Layar bergaris',
            'nama_pelanggan' => 'Budi Santoso',
            'telepon_pelanggan' => '08123456789',
        ], $this->user);

        $this->assertNotNull($tiket->token_approval);
        $this->assertNotEmpty($tiket->token_approval);
        $this->assertEquals(64, strlen($tiket->token_approval));
    }

    public function test_booking_online_menghasilkan_token_approval_otomatis(): void
    {
        $tiket = $this->servisService->bookingOnline([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Ani Wijaya',
            'telepon' => '08987654321',
            'jenis_hp' => 'Samsung S23',
            'keluhan' => 'Baterai boros',
        ]);

        $this->assertNotNull($tiket->token_approval);
        $this->assertNotEmpty($tiket->token_approval);
        $this->assertEquals(64, strlen($tiket->token_approval));
    }

    public function test_get_tracking_token_merender_halaman_dengan_status_200(): void
    {
        $tiket = $this->servisService->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'jenis_hp' => 'Xiaomi Redmi Note 10',
            'keluhan' => 'Mati total',
        ], $this->user);

        // Update status ke diagnosa -> menunggu_approval
        $tiket = $this->servisService->updateStatus($tiket, 'diagnosa', $this->user, 'Mulai cek');
        $tiket = $this->servisService->setEstimasi($tiket, 450000, 'Ganti IC Power', $this->user, [
            [
                'tipe' => 'jasa',
                'nama_item' => 'Jasa Perbaikan IC',
                'qty' => 1,
                'harga' => 200000,
            ],
            [
                'tipe' => 'part',
                'nama_item' => 'IC Power PM6150',
                'qty' => 1,
                'harga' => 250000,
            ],
        ]);

        $response = $this->get('/tracking/'.$tiket->token_approval);

        $response->assertStatus(200);
        $response->assertSee($tiket->no_tiket);
        $response->assertSee('Xiaomi Redmi Note 10');
        $response->assertSee('Progres Pengerjaan');
        $response->assertSee('Persetujuan Estimasi Biaya');
        $response->assertSee('Jasa Perbaikan IC');
        $response->assertSee('IC Power PM6150');
        $response->assertSee('Setujui Estimasi & Lanjutkan Perbaikan', false);
        $response->assertSee('Tolak Estimasi');
    }

    public function test_post_tracking_token_approve_mengubah_status_tiket_menjadi_disetujui(): void
    {
        $tiket = $this->servisService->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'jenis_hp' => 'Oppo Reno 6',
            'keluhan' => 'LCD Retak',
        ], $this->user);

        $tiket = $this->servisService->updateStatus($tiket, 'diagnosa', $this->user, 'Diagnosa LCD');
        $tiket = $this->servisService->setEstimasi($tiket, 600000, 'Ganti LCD Baru', $this->user);

        $this->assertEquals('menunggu_approval', $tiket->fresh()->status);

        $response = $this->from('/tracking/'.$tiket->token_approval)
            ->post('/tracking/'.$tiket->token_approval.'/approve', [
                'alasan' => 'Disetujui via browser',
            ]);

        $response->assertRedirect('/tracking/'.$tiket->token_approval);
        $response->assertSessionHas('success');
        $this->assertEquals('disetujui', $tiket->fresh()->status);
    }

    public function test_post_tracking_token_reject_mengubah_status_tiket_menjadi_ditolak(): void
    {
        $tiket = $this->servisService->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'jenis_hp' => 'Vivo Y20',
            'keluhan' => 'Kamera Buram',
        ], $this->user);

        $tiket = $this->servisService->updateStatus($tiket, 'diagnosa', $this->user, 'Diagnosa Kamera');
        $tiket = $this->servisService->setEstimasi($tiket, 300000, 'Ganti modul kamera', $this->user);

        $this->assertEquals('menunggu_approval', $tiket->fresh()->status);

        $response = $this->from('/tracking/'.$tiket->token_approval)
            ->post('/tracking/'.$tiket->token_approval.'/reject', [
                'alasan' => 'Kemahalan',
            ]);

        $response->assertRedirect('/tracking/'.$tiket->token_approval);
        $response->assertSessionHas('info');
        $this->assertEquals('ditolak', $tiket->fresh()->status);
    }

    public function test_tracking_search_page_renders_with_status_200(): void
    {
        $response = $this->get('/tracking');
        $response->assertStatus(200);
        $response->assertSee('Lacak Status Servis Gadget');
    }

    public function test_tracking_search_redirects_when_ticket_number_matched(): void
    {
        $tiket = $this->servisService->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'jenis_hp' => 'Poco X3',
            'keluhan' => 'Restart sendiri',
        ], $this->user);

        $response = $this->post('/tracking', ['q' => $tiket->no_tiket]);
        $response->assertRedirect('/tracking/'.$tiket->token_approval);
    }

    public function test_tracking_search_flashes_error_when_not_found(): void
    {
        $response = $this->post('/tracking', ['q' => 'NON-EXISTENT-999']);
        $response->assertRedirect('/tracking');
        $response->assertSessionHas('error');
    }

    public function test_api_servis_tracking_dapat_diakses_publik_tanpa_authorization(): void
    {
        $tiket = $this->servisService->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'jenis_hp' => 'Realme 7',
            'keluhan' => 'Speaker pecah',
        ], $this->user);

        $response = $this->getJson('/api/servis/tracking/'.$tiket->token_approval);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'no_tiket' => $tiket->no_tiket,
                'jenis_hp' => 'Realme 7',
                'status' => 'diterima',
            ],
        ]);
    }

    public function test_guest_membuka_tracking_tiket_bertelepon_memerlukan_verifikasi_5_digit(): void
    {
        $tiket = $this->servisService->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Budi Santoso',
            'telepon_pelanggan' => '081234567890',
            'jenis_hp' => 'iPhone 13 Pro',
            'keluhan' => 'Layar bergaris',
        ], $this->user);

        // Guest tanpa session / login mengakses tracking
        $response = $this->get('/tracking/'.$tiket->token_approval);

        $response->assertStatus(200);
        $response->assertSee('Verifikasi Keamanan Tracking');
        $response->assertSee('5 Digit Terakhir No. HP');
        // Data sensitif / keluhan / status pengerjaan tidak boleh tampil sebelum verifikasi
        $response->assertDontSee('Layar bergaris');
    }

    public function test_verifikasi_5_digit_nomor_hp_gagal_jika_salah(): void
    {
        $tiket = $this->servisService->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Budi Santoso',
            'telepon_pelanggan' => '081234567890',
            'jenis_hp' => 'iPhone 13 Pro',
            'keluhan' => 'Layar bergaris',
        ], $this->user);

        $response = $this->post('/tracking/'.$tiket->token_approval.'/verify', [
            'no_hp' => '12345',
        ]);

        $response->assertRedirect('/tracking/'.$tiket->token_approval);
        $response->assertSessionHas('error');
    }

    public function test_verifikasi_5_digit_nomor_hp_berhasil_dan_membuka_detail_tracking(): void
    {
        $tiket = $this->servisService->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Budi Santoso',
            'telepon_pelanggan' => '081234567890',
            'jenis_hp' => 'iPhone 13 Pro',
            'keluhan' => 'Layar bergaris',
        ], $this->user);

        $response = $this->post('/tracking/'.$tiket->token_approval.'/verify', [
            'no_hp' => '67890',
        ]);

        $response->assertRedirect('/tracking/'.$tiket->token_approval);
        $response->assertSessionHas('success');

        // Buka kembali tracking setelah session tersimpan
        $responsePage = $this->get('/tracking/'.$tiket->token_approval);
        $responsePage->assertStatus(200);
        $responsePage->assertSee('iPhone 13 Pro');
        $responsePage->assertSee('Layar bergaris');
        $responsePage->assertDontSee('Verifikasi Keamanan Tracking');
    }

    public function test_tracking_search_dengan_5_digit_no_hp_langsung_terverifikasi(): void
    {
        $tiket = $this->servisService->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Budi Santoso',
            'telepon_pelanggan' => '081234567890',
            'jenis_hp' => 'Samsung S22',
            'keluhan' => 'Speaker rusak',
        ], $this->user);

        // Submit search dengan no_tiket dan 5 digit terakhir no HP yang benar
        $response = $this->post('/tracking', [
            'q' => $tiket->no_tiket,
            'no_hp' => '67890',
        ]);

        $response->assertRedirect('/tracking/'.$tiket->token_approval);

        // Halaman detail harus langsung terbuka tanpa form verifikasi
        $detail = $this->get('/tracking/'.$tiket->token_approval);
        $detail->assertSee('Samsung S22');
        $detail->assertSee('Speaker rusak');
    }

    public function test_pelanggan_login_bisa_akses_tracking_langsung_tanpa_verifikasi(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Ahmad Customer',
            'telepon' => '085678901234',
            'password' => bcrypt('password123'),
        ]);

        $tiket = $this->servisService->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $pelanggan->id,
            'telepon_pelanggan' => '085678901234',
            'jenis_hp' => 'Asus ROG Phone',
            'keluhan' => 'Panas berlebih',
        ], $this->user);

        // Login sebagai customer
        $this->actingAs($pelanggan, 'customer');

        $response = $this->get('/tracking/'.$tiket->token_approval);
        $response->assertStatus(200);
        $response->assertSee('Asus ROG Phone');
        $response->assertSee('Panas berlebih');
        $response->assertDontSee('Verifikasi Keamanan Tracking');
    }

    public function test_staff_login_bisa_akses_tracking_langsung_tanpa_verifikasi(): void
    {
        $tiket = $this->servisService->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan Umum',
            'telepon_pelanggan' => '087711223344',
            'jenis_hp' => 'Google Pixel 7',
            'keluhan' => 'Mati lampu layar',
        ], $this->user);

        // Login sebagai staff backoffice
        $this->actingAs($this->user, 'web');

        $response = $this->get('/tracking/'.$tiket->token_approval);
        $response->assertStatus(200);
        $response->assertSee('Google Pixel 7');
        $response->assertSee('Mati lampu layar');
        $response->assertDontSee('Verifikasi Keamanan Tracking');
    }

    public function test_approve_estimasi_ditolak_jika_belum_verifikasi_no_hp(): void
    {
        $tiket = $this->servisService->terimaUnit([
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Eko',
            'telepon_pelanggan' => '081299887766',
            'jenis_hp' => 'Realme GT',
            'keluhan' => 'Ganti baterai',
        ], $this->user);

        $tiket = $this->servisService->updateStatus($tiket, 'diagnosa', $this->user, 'Mulai');
        $tiket = $this->servisService->setEstimasi($tiket, 200000, 'Baterai baru', $this->user);

        // Guest mencoba approve tanpa verifikasi
        $response = $this->post('/tracking/'.$tiket->token_approval.'/approve');
        $response->assertRedirect('/tracking/'.$tiket->token_approval);
        $response->assertSessionHas('error');
        $this->assertEquals('menunggu_approval', $tiket->fresh()->status);
    }
}
