<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Servis\Services\ServisService;
use App\Modules\Wms\Models\Produk;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * [REPRO] Estimasi disetujui (jasa + part LCD), lalu saat pengerjaan teknisi
 * menambah part tambahan (lem). Penagihan harus memuat ketiganya.
 */
class ServisEstimasiTambahanPartTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CabangSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin Servis',
            'email' => 'admin-servis@uteparts.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    private function buatTiketDenganEstimasi(): TiketServis
    {
        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-REPRO-'.rand(10000, 99999),
            'cabang_id' => 1,
            'jenis_hp' => 'iPhone 14',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Layar retak',
            'kondisi_fisik' => ['kerusakan' => 'layar retak'],
            'foto_unit' => [],
            'status' => 'diagnosa',
            'sumber' => 'walk_in',
        ]);

        // Estimasi disetujui pelanggan: jasa 100.000 + part LCD 300.000
        app(ServisService::class)->setEstimasi(
            $tiket,
            400000,
            'Estimasi awal',
            $this->admin,
            [
                ['tipe' => 'jasa', 'nama_item' => 'Jasa ganti layar', 'qty' => 1, 'harga' => 100000],
                ['tipe' => 'part', 'nama_item' => 'LCD iPhone 14', 'qty' => 1, 'harga' => 300000],
            ]
        );

        // Pelanggan menyetujui estimasi
        app(ServisService::class)->updateStatus($tiket->fresh(), 'disetujui', $this->admin);

        // Saat pengerjaan: teknisi menambah part lem 20.000 (di luar estimasi)
        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'part',
            'nama_item' => 'Lem layar',
            'qty' => 1,
            'harga' => 20000,
            'hpp' => 0,
            'dibatalkan_at' => null,
        ]);

        // Selesai
        $service = app(ServisService::class);
        $service->updateStatus($tiket->fresh(), 'dikerjakan', $this->admin);
        $service->updateStatus($tiket->fresh(), 'qc', $this->admin);
        $service->updateStatus($tiket->fresh(), 'selesai', $this->admin);

        return $tiket->fresh();
    }

    /** @test */
    public function test_rincian_penagihan_memuat_estimasi_plus_tambahan(): void
    {
        $tiket = $this->buatTiketDenganEstimasi();

        $rincian = $tiket->getRincianBiayaLengkap();

        // Harusnya: jasa 100.000 + LCD 300.000 + lem 20.000 = 420.000
        $this->assertEquals(100000, $rincian['total_jasa']);
        $this->assertEquals(320000, $rincian['total_part']);
        $this->assertEquals(420000, $rincian['total']);
    }

    /** @test */
    public function test_total_piutang_jurnal_klasifikasi_sesuai_rincian(): void
    {
        $tiket = $this->buatTiketDenganEstimasi();

        $jurnal = JurnalAkuntansi::with('akun')
            ->where('referensi_tipe', TiketServis::class)
            ->where('referensi_id', $tiket->id)
            ->get();

        $totalDebit = (float) $jurnal->sum('debit');
        $kreditJasa = (float) $jurnal->filter(fn ($j) => $j->akun?->kode === '420-01')->sum('kredit');
        $kreditPart = (float) $jurnal->filter(fn ($j) => $j->akun?->kode === '410-01')->sum('kredit');

        // Piutang 420.000; pendapatan jasa 100.000; pendapatan part 320.000
        $this->assertEquals(420000, $totalDebit);
        $this->assertEquals(100000, $kreditJasa);
        $this->assertEquals(320000, $kreditPart);
    }

    /** @test */
    public function test_perubahan_lingkup_terdeteksi(): void
    {
        $tiket = $this->buatTiketDenganEstimasi();

        $rincian = $tiket->getRincianBiayaLengkap();

        $this->assertTrue($rincian['perubahan_lingkup']);
        $this->assertCount(1, $rincian['item_diluar_estimasi']);
        $this->assertSame('Lem layar', $rincian['item_diluar_estimasi'][0]['nama']);
        $this->assertEquals(20000, $rincian['item_diluar_estimasi'][0]['subtotal']);
    }

    /** @test */
    public function test_part_estimasi_yang_dicatat_ulang_tidak_dobel(): void
    {
        $lcd = Produk::create([
            'nama' => 'LCD iPhone 14',
            'slug' => 'lcd-iphone-14-'.uniqid(),
            'satuan' => 'pcs',
            'harga_beli' => 100000,
            'harga_jual_retail' => 300000,
            'is_active' => true,
        ]);

        $tiket = TiketServis::create([
            'no_tiket' => 'SRV-DEDUP-'.rand(10000, 99999),
            'cabang_id' => 1,
            'jenis_hp' => 'iPhone 14',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Layar retak',
            'kondisi_fisik' => [],
            'foto_unit' => [],
            'status' => 'diagnosa',
            'sumber' => 'walk_in',
        ]);

        app(ServisService::class)->setEstimasi($tiket, 300000, 'Estimasi', $this->admin, [
            ['tipe' => 'part', 'produk_id' => $lcd->id, 'nama_item' => 'LCD', 'qty' => 1, 'harga' => 300000],
        ]);
        app(ServisService::class)->updateStatus($tiket->fresh(), 'disetujui', $this->admin);

        // Teknisi mencatat part yang sama persis (produk sama) saat pengerjaan
        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'part',
            'produk_id' => $lcd->id,
            'nama_item' => 'LCD',
            'qty' => 1,
            'harga' => 300000,
            'hpp' => 100000,
            'dibatalkan_at' => null,
        ]);

        $rincian = $tiket->fresh()->getRincianBiayaLengkap();

        // Tetap 300.000 (bukan 600.000) — catatan pekerjaan menang, estimasi di-drop
        $this->assertEquals(300000, $rincian['total_part']);
        $this->assertEquals(300000, $rincian['total']);
        $this->assertFalse($rincian['perubahan_lingkup']);
    }

    /** @test */
    public function test_bayar_fail_closed_jika_jumlah_tidak_sesuai(): void
    {
        $tiket = $this->buatTiketDenganEstimasi();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('tidak sesuai rincian penagihan');

        app(ServisService::class)->bayar($tiket, 'tunai', $this->admin, null, null, null, 999);
    }

    /** @test */
    public function test_bayar_terima_jumlah_sesuai_rincian(): void
    {
        $tiket = $this->buatTiketDenganEstimasi();

        app(ServisService::class)->bayar($tiket, 'tunai', $this->admin, null, null, null, 420000);

        $this->assertSame('lunas', $tiket->fresh()->status_pembayaran);
        $this->assertNotNull($tiket->fresh()->no_jurnal_bayar);
    }

    /** @test */
    public function test_pelunasan_tidak_menagih_jumlah_berbeda_dari_rincian(): void
    {
        $tiket = $this->buatTiketDenganEstimasi();

        $rincianTotal = (float) $tiket->getRincianBiayaLengkap()['total'];

        // Jurnal pelunasan (bayar) harus sama dengan rincian penagihan
        app(ServisService::class)->bayar($tiket, 'tunai', $this->admin);

        $jurnalBayar = JurnalAkuntansi::where('no_jurnal', $tiket->fresh()->no_jurnal_bayar)->get();
        $this->assertEquals($rincianTotal, (float) $jurnalBayar->sum('debit'));
    }
}
