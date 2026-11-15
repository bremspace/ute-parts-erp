<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Servis\Models\Garansi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Services\ServisService;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mitigasi komplain pasca-servis: konsumen kembali ke toko karena keluhan
 * setelah servis selesai. Tiket komplain ditautkan ke tiket asal + menandai
 * garansi yang masih berlaku.
 */
class ServisKomplainTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(CabangSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin Komplain',
            'email' => 'admin-komplain@uteparts.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('super-admin');
    }

    private function buatTiketSelesai(string $no): TiketServis
    {
        return TiketServis::create([
            'no_tiket' => $no,
            'cabang_id' => 1,
            'nama_pelanggan' => 'Pelanggan Komplain',
            'telepon_pelanggan' => '08123456789',
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Ganti LCD',
            'status' => 'selesai',
            'sumber' => 'walk_in',
            'token_approval' => 'token-'.$no,
        ]);
    }

    /** @test */
    public function test_komplain_dalam_garansi(): void
    {
        $tiket = $this->buatTiketSelesai('SRV-KOM-1');

        Garansi::create([
            'tiket_servis_id' => $tiket->id,
            'durasi_hari' => 30,
            'tanggal_mulai' => now()->subDays(2)->toDateString(),
            'tanggal_berakhir' => now()->addDays(28)->toDateString(),
            'keterangan' => 'Garansi servis',
        ]);

        $komplain = app(ServisService::class)->buatTiketKomplain(
            $tiket->fresh(),
            'LCD mulai bergaris 3 hari setelah servis',
            $this->admin,
            'Unit diterima, akan dicek modul LCD'
        );

        $this->assertSame('diterima', $komplain->status);
        $this->assertSame('komplain', $komplain->sumber);
        $this->assertSame($tiket->id, (int) $komplain->tiket_servis_lama_id);
        $this->assertTrue($komplain->garansi_berlaku);
        $this->assertStringContainsString('LCD', $komplain->keluhan);
        $this->assertNotSame($tiket->no_tiket, $komplain->no_tiket);

        // Garansi tampil di tiket komplain
        $this->assertTrue($komplain->garansi_berlaku);
    }

    /** @test */
    public function test_komplain_diluar_garansi_ditandai_false(): void
    {
        $tiket = $this->buatTiketSelesai('SRV-KOM-2');

        Garansi::create([
            'tiket_servis_id' => $tiket->id,
            'durasi_hari' => 30,
            'tanggal_mulai' => now()->subDays(60)->toDateString(),
            'tanggal_berakhir' => now()->subDays(30)->toDateString(),
            'keterangan' => 'Garansi habis',
        ]);

        $komplain = app(ServisService::class)->buatTiketKomplain(
            $tiket->fresh(),
            'Tombol power tidak berfungsi',
            $this->admin
        );

        $this->assertFalse($komplain->garansi_berlaku);
    }

    /** @test */
    public function test_komplain_hanya_untuk_tiket_selesai(): void
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-KOM-3',
            'cabang_id' => 1,
            'jenis_hp' => 'Samsung A52',
            'keluhan' => 'Baterai',
            'status' => 'dikerjakan',
            'sumber' => 'walk_in',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('hanya untuk tiket yang sudah selesai');

        app(ServisService::class)->buatTiketKomplain($tiket, 'Komplain prematur', $this->admin);
    }

    /** @test */
    public function test_perbaikan_gratis_garansi_wajib_persetujuan_regulator(): void
    {
        $tiket = $this->buatTiketSelesai('SRV-KOM-4');

        Garansi::create([
            'tiket_servis_id' => $tiket->id,
            'durasi_hari' => 30,
            'tanggal_mulai' => now()->subDay()->toDateString(),
            'tanggal_berakhir' => now()->addDays(29)->toDateString(),
            'keterangan' => 'Garansi servis',
        ]);

        $komplain = app(ServisService::class)->buatTiketKomplain($tiket->fresh(), 'LCD bergaris lagi', $this->admin);

        $this->assertTrue($komplain->komplain_gratis, 'Komplain garansi otomatis mengusulkan perbaikan gratis');
        $this->assertSame(4320, $komplain->batas_waktu_menit, 'SLA komplain 72 jam');

        $service = app(ServisService::class);
        $service->updateStatus($komplain->fresh(), 'dikerjakan', $this->admin, 'Komplain garansi — tindak lanjut langsung');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('belum disetujui regulator');

        $service->updateStatus($komplain->fresh(), 'selesai', $this->admin, 'Selesaikan perbaikan');
    }

    /** @test */
    public function test_setelah_disetujui_selesai_lunas_otomatis_tanpa_piutang(): void
    {
        $tiket = $this->buatTiketSelesai('SRV-KOM-5');

        Garansi::create([
            'tiket_servis_id' => $tiket->id,
            'durasi_hari' => 30,
            'tanggal_mulai' => now()->subDay()->toDateString(),
            'tanggal_berakhir' => now()->addDays(29)->toDateString(),
            'keterangan' => 'Garansi servis',
        ]);

        $komplain = app(ServisService::class)->buatTiketKomplain($tiket->fresh(), 'LCD bergaris lagi', $this->admin);

        // Regulator berbeda menyetujui (four-eyes)
        $regulator = User::create([
            'name' => 'Regulator Komplain',
            'email' => 'regulator-komplain@uteparts.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $regulator->assignRole('super-admin');

        app(ServisService::class)->setujuiGratisGaransi($komplain->fresh(), $regulator);

        $service = app(ServisService::class);
        $service->updateStatus($komplain->fresh(), 'dikerjakan', $this->admin, 'Komplain garansi — tindak lanjut langsung');
        $service->updateStatus($komplain->fresh(), 'selesai', $this->admin, 'Selesaikan perbaikan');

        $komplain = $komplain->fresh();
        $this->assertSame('lunas', $komplain->status_pembayaran);
        $this->assertSame('garansi', $komplain->metode_pembayaran);
        $this->assertNotNull($komplain->tanggal_bayar);

        // Tidak ada piutang untuk perbaikan garansi gratis
        $this->assertSame(
            0,
            \App\Modules\Akunting\Models\Piutang::where('referensi_tipe', TiketServis::class)
                ->where('referensi_id', $komplain->id)
                ->count()
        );
    }

    /** @test */
    public function test_four_eyes_approver_beda_dengan_pengusul(): void
    {
        $tiket = $this->buatTiketSelesai('SRV-KOM-6');

        Garansi::create([
            'tiket_servis_id' => $tiket->id,
            'durasi_hari' => 30,
            'tanggal_mulai' => now()->subDay()->toDateString(),
            'tanggal_berakhir' => now()->addDays(29)->toDateString(),
            'keterangan' => 'Garansi servis',
        ]);

        $komplain = app(ServisService::class)->buatTiketKomplain($tiket->fresh(), 'LCD bergaris lagi', $this->admin);

        // Pengusul = admin; approver yang sama harus ditolak
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('berbeda orang');

        app(ServisService::class)->setujuiGratisGaransi($komplain->fresh(), $this->admin);
    }
}
