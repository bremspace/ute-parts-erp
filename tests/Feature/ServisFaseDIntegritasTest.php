<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCoa;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Notifikasi\Jobs\KirimNotifikasiJob;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ServisFaseDIntegritasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cabang $cabang;

    private ServisService $servisService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        // Ensure permission exists in test DB
        Permission::findOrCreate('servis.approve-estimasi', 'web');

        $this->cabang = Cabang::first();

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-fase-d@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('super-admin');
        $this->admin->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->admin, 'web');

        $this->servisService = app(ServisService::class);
    }

    private function buatTiket(string $status = 'menunggu_approval', float $estimasi = 200000): TiketServis
    {
        return TiketServis::create([
            'no_tiket' => 'SRV-TEST-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan Test',
            'telepon_pelanggan' => '081234567890',
            'jenis_hp' => 'Samsung S21',
            'tipe_kunci' => 'pola',
            'kunci_terenkripsi' => 'rahasia123',
            'keluhan' => 'Layar bergaris',
            'status' => $status,
            'status_pembayaran' => 'belum_bayar',
            'sumber' => 'walkin',
            'estimasi_biaya' => $estimasi,
        ]);
    }

    public function test_servis_board_proses_approve_ditolak_jika_tidak_punya_permission_approve_estimasi(): void
    {
        $userTanpaPerm = User::create([
            'name' => 'Staf Tanpa Perm',
            'email' => 'staf-tanpa-perm@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $userTanpaPerm->cabangs()->attach($this->cabang->id);

        $this->actingAs($userTanpaPerm, 'web');

        $tiket = $this->buatTiket('menunggu_approval');

        Livewire::test(ServisBoard::class)
            ->set('approveTiketId', $tiket->id)
            ->call('prosesApprove', 'approve')
            ->assertDispatched('alert', function ($event, $params) {
                $payload = $params[0] ?? $params;

                return ($payload['type'] ?? '') === 'error'
                    && str_contains($payload['message'] ?? '', 'Anda tidak punya izin');
            });

        $this->assertSame('menunggu_approval', $tiket->fresh()->status);
    }

    public function test_update_status_override_tanpa_alasan_melempar_exception(): void
    {
        $tiket = $this->buatTiket('diterima');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Alasan wajib diisi saat melakukan override status');

        // 'diterima' langsung ke 'selesai' adalah override (melewati diagnosa, dikerjakan, dll)
        $this->servisService->updateStatus($tiket, 'selesai', $this->admin, '');
    }

    public function test_input_pekerjaan_saat_tiket_berstatus_qc_ditolak(): void
    {
        $tiket = $this->buatTiket('qc');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Input pekerjaan hanya saat status disetujui / dikerjakan');

        $this->servisService->inputPekerjaan($tiket, [
            [
                'tipe' => 'jasa',
                'nama_item' => 'Jasa Ganti Layar',
                'qty' => 1,
                'harga' => 100000,
            ],
        ], $this->admin);
    }

    public function test_shop_controller_account_servis_tidak_memuat_kunci_terenkripsi_dan_foto_unit(): void
    {
        $pelanggan = Pelanggan::create([
            'cabang_id' => $this->cabang->id,
            'nama' => 'Customer Shop',
            'telepon' => '0899998888',
            'password' => bcrypt('secret'),
        ]);

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-SHOP-01',
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $pelanggan->id,
            'nama_pelanggan' => $pelanggan->nama,
            'telepon_pelanggan' => $pelanggan->telepon,
            'jenis_hp' => 'iPhone 12',
            'tipe_kunci' => 'pin',
            'kunci_terenkripsi' => 'supersecretpin',
            'foto_unit' => ['data:image/png;base64,samplephoto'],
            'keluhan' => 'Baterai drop',
            'status' => 'diterima',
            'status_pembayaran' => 'belum_bayar',
            'sumber' => 'walkin',
            'estimasi_biaya' => 300000,
        ]);

        $this->actingAs($pelanggan, 'customer');

        $response = $this->getJson('/api/account/servis');
        $response->assertOk();

        $data = $response->json('data.data');
        $this->assertNotEmpty($data);

        $pertama = $data[0];
        $this->assertArrayNotHasKey('kunci_terenkripsi', $pertama);
        $this->assertArrayNotHasKey('tipe_kunci', $pertama);
        $this->assertArrayNotHasKey('pola_kunci', $pertama);
        $this->assertArrayNotHasKey('pin_kunci', $pertama);
        $this->assertArrayNotHasKey('foto_unit', $pertama);
    }

    public function test_jurnal_service_post_dengan_sumber_ngawur_melempar_invalid_argument_exception(): void
    {
        $jurnalService = app(JurnalService::class);
        $akunKas = AkunCoa::where('kode', '111-01')->first() ?? AkunCoa::first();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Sumber jurnal 'sumber_ngawur' tidak valid");

        $jurnalService->post(
            noJurnal: 'JRN-TEST-001',
            tanggal: now(),
            sumber: 'sumber_ngawur',
            lines: [
                ['akun_kode' => $akunKas->kode, 'debit' => 10000, 'kredit' => 0],
                ['akun_kode' => $akunKas->kode, 'debit' => 0, 'kredit' => 10000],
            ],
            cabangId: $this->cabang->id,
            userId: $this->admin->id
        );
    }

    public function test_kirim_notifikasi_job_kirim_whats_app_mengirim_http_request_saat_gateway_configured(): void
    {
        config(['services.wa' => [
            'url' => 'https://api.wa-gateway.test/send',
            'token' => 'test-bearer-token',
        ]]);

        Http::fake([
            'https://api.wa-gateway.test/send' => Http::response(['status' => true, 'id' => 'msg-123'], 200),
        ]);

        $log = NotifikasiKeluar::create([
            'tipe' => 'wa',
            'tujuan' => '081234567890',
            'judul' => 'Servis Selesai',
            'konten' => 'Unit Anda telah selesai diperbaiki.',
            'status' => 'antri',
        ]);

        $job = new KirimNotifikasiJob($log->id);
        $job->handle();

        Http::assertSent(function ($request) use ($log) {
            return $request->url() === 'https://api.wa-gateway.test/send'
                && $request->hasHeader('Authorization', 'test-bearer-token')
                && $request['target'] === $log->tujuan
                && $request['message'] === $log->konten;
        });

        $this->assertSame('terkirim', $log->fresh()->status);
        $this->assertNull($log->fresh()->error);
    }
}
