<?php

namespace Tests\Feature;

use App\Models\User;
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
}
