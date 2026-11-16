<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Services\KpiService;
use App\Modules\Servis\Models\Garansi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\Voucher;
use App\Modules\Servis\Services\ServisService;
use Database\Seeders\AkunCoaSeeder;
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
        $this->seed(AkunCoaSeeder::class);

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
            Piutang::where('referensi_tipe', TiketServis::class)
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

    /** @test */
    public function test_goodwill_hanya_berlaku_setelah_disetujui(): void
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-KOM-7',
            'cabang_id' => 1,
            'jenis_hp' => 'iPhone 12',
            'keluhan' => 'LCD bergaris',
            'status' => 'diagnosa',
            'sumber' => 'walk_in',
            'telepon_pelanggan' => '0812',
        ]);

        $service = app(ServisService::class);
        $service->setEstimasi($tiket, 200000, 'Estimasi jasa', $this->admin, [
            ['tipe' => 'jasa', 'nama_item' => 'Jasa perbaikan', 'qty' => 1, 'harga' => 200000],
        ]);
        $service->updateStatus($tiket->fresh(), 'disetujui', $this->admin);

        $service->usulkanGoodwill($tiket->fresh(), 50000, 'Penyebab ambigu', $this->admin);
        $this->assertSame(0.0, $service->getGoodwillBerlaku($tiket->fresh()), 'Goodwill belum disetujui');

        $regulator = User::create([
            'name' => 'Regulator Goodwill',
            'email' => 'regulator-goodwill@uteparts.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $regulator->assignRole('super-admin');

        $service->setujuiGoodwill($tiket->fresh(), $regulator);
        $this->assertSame(50000.0, $service->getGoodwillBerlaku($tiket->fresh()));
        $this->assertSame(150000.0, $service->getTotalTagihanBersih($tiket->fresh()));

        $service->updateStatus($tiket->fresh(), 'dikerjakan', $this->admin, 'Tindak lanjut');
        $service->updateStatus($tiket->fresh(), 'qc', $this->admin, 'QC');
        $service->updateStatus($tiket->fresh(), 'selesai', $this->admin, 'Selesai');

        $jurnal = JurnalAkuntansi::with('akun')
            ->where('referensi_tipe', TiketServis::class)
            ->where('referensi_id', $tiket->id)
            ->get();

        $this->assertEquals(150000.0, (float) $jurnal->sum('debit'), 'Piutang = total bersih');

        $idAkunJasa = AkunCOA::where('kode', '420-01')->value('id');
        $this->assertEquals(
            150000.0,
            (float) $jurnal->where('akun_coa_id', $idAkunJasa)->sum('kredit'),
            'Pendapatan jasa net setelah goodwill'
        );
    }

    /** @test */
    public function test_dashboard_komplain_render(): void
    {
        $this->actingAs($this->admin, 'web');

        $response = $this->get('/app/servis/komplain');
        $response->assertOk();
        $response->assertSee('Dashboard Komplain');
    }

    /** @test */
    public function test_kpi_komplain_berulang_dihitung_dari_tiket_asal(): void
    {
        $teknisi = User::create([
            'name' => 'Teknisi KPI',
            'email' => 'teknisi-kpi@uteparts.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $teknisi->assignRole('teknisi');

        $karyawan = Karyawan::create([
            'user_id' => $teknisi->id,
            'cabang_id' => 1,
            'nik' => 'TEK-KOM-01',
            'nama' => 'Teknisi KPI',
            'jabatan' => 'teknisi',
            'tgl_masuk' => now()->subYear()->toDateString(),
            'status_aktif' => true,
        ]);

        $tiketAsal = TiketServis::create([
            'no_tiket' => 'SRV-KOM-KPI',
            'cabang_id' => 1,
            'teknisi_id' => $teknisi->id,
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Ganti LCD',
            'status' => 'selesai',
            'sumber' => 'walk_in',
        ]);

        app(ServisService::class)->buatTiketKomplain($tiketAsal, 'LCD bermasalah lagi', $this->admin);

        $kpi = app(KpiService::class);
        $periode = now()->format('Y-m');

        $this->assertSame(1.0, $kpi->nilaiAktual('komplain_berulang', $karyawan, $periode));
        $this->assertSame(50.0, $kpi->persenCapaian('komplain_berulang', 1, 2));
    }

    /** @test */
    public function test_komplain_overdue_dieskalasi_oleh_command(): void
    {
        $tiket = $this->buatTiketSelesai('SRV-KOM-ESC');
        Garansi::create([
            'tiket_servis_id' => $tiket->id,
            'durasi_hari' => 30,
            'tanggal_mulai' => now()->subDay()->toDateString(),
            'tanggal_berakhir' => now()->addDays(29)->toDateString(),
            'keterangan' => 'Garansi servis',
        ]);

        $komplain = app(ServisService::class)->buatTiketKomplain($tiket->fresh(), 'Komplain overdue', $this->admin);
        app(ServisService::class)->updateStatus($komplain->fresh(), 'dikerjakan', $this->admin, 'Mulai tindak lanjut');

        $komplain = $komplain->fresh();
        $komplain->update(['pengerjaan_deadline_at' => now()->subHour()]);
        $this->assertNull($komplain->fresh()->komplain_eskalasi_at);

        $this->artisan('servis:cek-batas-waktu')->assertSuccessful();

        $this->assertNotNull($komplain->fresh()->komplain_eskalasi_at, 'Komplain overdue harus di-eskalasi');

        // Idempoten: jalan kedua tidak mengubah penanda
        $pertama = $komplain->fresh()->komplain_eskalasi_at;
        $this->artisan('servis:cek-batas-waktu')->assertSuccessful();
        $this->assertSame($pertama?->toDateTimeString(), $komplain->fresh()->komplain_eskalasi_at?->toDateTimeString());
    }

    /** @test */
    public function test_goodwill_voucher_dipakai_servis_berikutnya(): void
    {
        $pelanggan = Pelanggan::create([
            'nama' => 'Pelanggan Voucher',
            'telepon' => '08123456789',
        ]);

        $service = app(ServisService::class);

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-KOM-VCR',
            'cabang_id' => 1,
            'pelanggan_id' => $pelanggan->id,
            'nama_pelanggan' => $pelanggan->nama,
            'telepon_pelanggan' => $pelanggan->telepon,
            'jenis_hp' => 'iPhone 12',
            'keluhan' => 'LCD bergaris',
            'status' => 'diagnosa',
            'sumber' => 'walk_in',
        ]);

        $service->setEstimasi($tiket, 200000, 'Estimasi jasa', $this->admin, [
            ['tipe' => 'jasa', 'nama_item' => 'Jasa perbaikan', 'qty' => 1, 'harga' => 200000],
        ]);
        $service->updateStatus($tiket->fresh(), 'disetujui', $this->admin);

        $service->usulkanGoodwill($tiket->fresh(), 80000, 'Ambigu — bentuk voucher', $this->admin, true);

        $regulator = User::create([
            'name' => 'Regulator Voucher',
            'email' => 'regulator-voucher@uteparts.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $regulator->assignRole('super-admin');
        $service->setujuiGoodwill($tiket->fresh(), $regulator);

        $voucher = Voucher::where('pelanggan_id', $pelanggan->id)->first();
        $this->assertNotNull($voucher, 'Voucher harus terbit');
        $this->assertSame('aktif', $voucher->status);
        $this->assertSame(80000.0, (float) $voucher->sisa);

        // Tiket asal TIDAK terdiskon (goodwill berbentuk voucher)
        $this->assertSame(200000.0, $service->getTotalTagihanBersih($tiket->fresh()));

        // Servis baru pelanggan yang sama → voucher dipakai otomatis
        $tiket2 = TiketServis::create([
            'no_tiket' => 'SRV-KOM-VCR-2',
            'cabang_id' => 1,
            'pelanggan_id' => $pelanggan->id,
            'nama_pelanggan' => $pelanggan->nama,
            'telepon_pelanggan' => $pelanggan->telepon,
            'jenis_hp' => 'iPhone 12',
            'keluhan' => 'Servis lanjutan',
            'status' => 'diagnosa',
            'sumber' => 'walk_in',
        ]);

        $service->setEstimasi($tiket2, 300000, 'Estimasi jasa', $this->admin, [
            ['tipe' => 'jasa', 'nama_item' => 'Jasa servis lanjutan', 'qty' => 1, 'harga' => 300000],
        ]);
        $service->updateStatus($tiket2->fresh(), 'disetujui', $this->admin);

        $this->assertSame(220000.0, $service->getTotalTagihanBersih($tiket2->fresh()), 'Voucher 80.000 dipakai pada servis ini');

        $service->updateStatus($tiket2->fresh(), 'dikerjakan', $this->admin, 'Mulai');
        $service->updateStatus($tiket2->fresh(), 'qc', $this->admin, 'QC');
        $service->updateStatus($tiket2->fresh(), 'selesai', $this->admin, 'Selesai');

        $jurnal = JurnalAkuntansi::where('referensi_tipe', TiketServis::class)
            ->where('referensi_id', $tiket2->id)
            ->get();
        $this->assertEquals(220000.0, (float) $jurnal->sum('debit'), 'Piutang = total setelah voucher');

        $voucher = $voucher->fresh();
        $this->assertSame('terpakai', $voucher->status);
        $this->assertSame(0.0, (float) $voucher->sisa);
        $this->assertSame($tiket2->id, (int) $voucher->used_tiket_id);
    }
}
