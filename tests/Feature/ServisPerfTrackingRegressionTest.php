<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Servis\Services\ServisService;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi performa & 500:
 * - halaman tracking konsumen 200 setelah perubahan lingkup disetujui
 *   (dulu: `format()` pada string timestamp → Error 500)
 * - flag `revisi_lingkup_pending` menggerakkan daftar tiket tertahan tanpa
 *   menghitung diff untuk semua tiket di setiap render
 * - foto progress disimpan sebagai file (bukan base64 di JSON)
 */
class ServisPerfTrackingRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(CabangSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin Perf',
            'email' => 'admin-perf@uteparts.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('super-admin');

        session(['cabang_id' => 1]);
    }

    /** @test */
    public function test_tracking_tidak_500_setelah_perubahan_lingkup_disetujui(): void
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-PERF-'.rand(10000, 99999),
            'cabang_id' => 1,
            'jenis_hp' => 'iPhone 14',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Layar retak',
            'kondisi_fisik' => [],
            'foto_unit' => [],
            'status' => 'diagnosa',
            'sumber' => 'walk_in',
            'telepon_pelanggan' => '0812',
        ]);

        app(ServisService::class)->setEstimasi($tiket, 400000, 'Estimasi', $this->admin, [
            ['tipe' => 'jasa', 'nama_item' => 'Jasa ganti layar', 'qty' => 1, 'harga' => 100000],
            ['tipe' => 'part', 'nama_item' => 'LCD', 'qty' => 1, 'harga' => 300000],
        ]);
        app(ServisService::class)->updateStatus($tiket->fresh(), 'disetujui', $this->admin);

        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'part',
            'nama_item' => 'Lem layar',
            'qty' => 1,
            'harga' => 20000,
            'dibatalkan_at' => null,
        ]);

        $service = app(ServisService::class);
        $service->refreshPerubahanLingkup($tiket->fresh());
        $service->setujuiPerubahanLingkup($tiket->fresh(), 'Disetujui pelanggan');

        $tiket = $tiket->fresh();
        $this->assertNotNull($tiket->revisi_lingkup_disetujui_at);

        $response = $this->get('/tracking/'.$tiket->token_approval);
        $response->assertOk();
        $response->assertSee('Perubahan lingkup servis telah disetujui');
    }

    /** @test */
    public function test_flag_perubahan_lingkup_menggerakkan_daftar_tiket_pending(): void
    {
        $this->actingAs($this->admin, 'web');

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-PERF-2-'.rand(10000, 99999),
            'cabang_id' => 1,
            'jenis_hp' => 'Samsung A52',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Baterai',
            'kondisi_fisik' => [],
            'foto_unit' => [],
            'status' => 'diagnosa',
            'sumber' => 'walk_in',
            'telepon_pelanggan' => '0812',
        ]);

        $service = app(ServisService::class);
        $service->setEstimasi($tiket, 400000, 'Estimasi', $this->admin, [
            ['tipe' => 'jasa', 'nama_item' => 'Jasa', 'qty' => 1, 'harga' => 100000],
            ['tipe' => 'part', 'nama_item' => 'LCD', 'qty' => 1, 'harga' => 300000],
        ]);
        $service->updateStatus($tiket->fresh(), 'disetujui', $this->admin);

        $this->assertFalse($tiket->fresh()->revisi_lingkup_pending);

        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'part',
            'nama_item' => 'Lem',
            'qty' => 1,
            'harga' => 20000,
            'dibatalkan_at' => null,
        ]);
        $service->refreshPerubahanLingkup($tiket->fresh());

        $this->assertTrue($tiket->fresh()->revisi_lingkup_pending);

        $daftar = Livewire::test(ServisBoard::class)->viewData('tiketPerluPersetujuan');
        $this->assertNotEmpty($daftar);
        $this->assertSame($tiket->id, $daftar[0]['id']);

        $service->setujuiPerubahanLingkup($tiket->fresh(), 'Disetujui');

        $this->assertFalse($tiket->fresh()->revisi_lingkup_pending);
        $daftar = Livewire::test(ServisBoard::class)->viewData('tiketPerluPersetujuan');
        $this->assertEmpty($daftar);
    }

    /** @test */
    public function test_foto_progress_disimpan_sebagai_file(): void
    {
        Storage::fake('public');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        $this->assertNotFalse($png);

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-PERF-3-'.rand(10000, 99999),
            'cabang_id' => 1,
            'jenis_hp' => 'Xiaomi 13',
            'keluhan' => 'Kamera',
            'status' => 'dikerjakan',
            'token_approval' => 'token-perf-foto',
        ]);

        $progress = app(ServisService::class)->catatProgress(
            $tiket,
            $this->admin,
            60,
            'Tahap pemasangan',
            'Foto pemasangan modul',
            ['data:image/png;base64,'.base64_encode($png)],
            true
        );

        $foto = $progress->fresh()->foto;
        $this->assertCount(1, $foto);
        $this->assertStringStartsNotWith('data:', $foto[0], 'Foto harus disimpan sebagai path file');

        Storage::disk('public')->assertExists($foto[0]);

        $urls = $progress->fresh()->getFotoUrls();
        $this->assertCount(1, $urls);
        $this->assertStringContainsString('/storage/', $urls[0]);
    }
}
