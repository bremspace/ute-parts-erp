<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Servis\Services\ServisService;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Services\ProdukService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * [ISO 9001 §8.7] Mitigasi pembatalan servis:
 * - pembatalan saat pengerjaan (konsumen/staf) → stok dikembalikan, tanpa jurnal
 * - pembatalan oleh pelanggan via token tracking
 * - pembatalan wajib alasan
 * - pembatalan setelah selesai → reversal jurnal fail-closed
 */
class ServisBatalTest extends TestCase
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
            'name' => 'Admin Batal',
            'email' => 'admin-batal@uteparts.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('super-admin');
    }

    private function buatTiket(string $noTiket, string $status = 'diagnosa'): TiketServis
    {
        return TiketServis::create([
            'no_tiket' => $noTiket,
            'cabang_id' => 1,
            'jenis_hp' => 'iPhone 14',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Layar retak',
            'kondisi_fisik' => [],
            'foto_unit' => [],
            'status' => $status,
            'sumber' => 'walk_in',
            'telepon_pelanggan' => '0812',
        ]);
    }

    /** @test */
    public function test_batal_saat_pengerjaan_mengembalikan_stok_tanpa_jurnal(): void
    {
        $gudang = Gudang::getDefault(1);

        $lcd = Produk::create([
            'nama' => 'LCD iPhone 14',
            'slug' => 'lcd-batal-'.uniqid(),
            'satuan' => 'pcs',
            'harga_beli' => 100000,
            'harga_jual_retail' => 300000,
            'is_active' => true,
        ]);

        app(ProdukService::class)->tambahStokPembelian(
            $lcd->id,
            null,
            $gudang->id,
            5,
            100000,
            'Stok awal pengujian',
            $this->admin->id,
            postJurnal: false
        );

        $tiket = $this->buatTiket('SRV-BATAL-1-'.rand(10000, 99999));

        app(ServisService::class)->setEstimasi($tiket, 300000, 'Estimasi', $this->admin, [
            ['tipe' => 'part', 'produk_id' => $lcd->id, 'nama_item' => 'LCD iPhone 14', 'qty' => 1, 'harga' => 300000],
        ]);

        $service = app(ServisService::class);
        $service->updateStatus($tiket->fresh(), 'disetujui', $this->admin);
        $service->updateStatus($tiket->fresh(), 'dikerjakan', $this->admin);

        // Teknisi memakai 1 LCD (stok 5 → 4)
        $service->inputPekerjaan($tiket->fresh(), [[
            'tipe' => 'part',
            'produk_id' => $lcd->id,
            'gudang_id' => $gudang->id,
            'qty' => 1,
            'harga' => 300000,
            'nama_item' => 'LCD iPhone 14',
        ]], $this->admin);

        $stok = \App\Modules\Wms\Models\StokItem::where('produk_id', $lcd->id)
            ->where('gudang_id', $gudang->id)
            ->first();
        $this->assertEquals(4, (int) $stok->jumlah);

        // Pelanggan membatalkan saat pengerjaan
        $service->updateStatus($tiket->fresh(), 'batal', $this->admin, 'Pelanggan membatalkan perbaikan');

        $tiket = $tiket->fresh();
        $this->assertSame('batal', $tiket->status);

        // Stok kembali 5 (part dikembalikan ke gudang asal)
        $stok = \App\Modules\Wms\Models\StokItem::where('produk_id', $lcd->id)
            ->where('gudang_id', $gudang->id)
            ->first();
        $this->assertEquals(5, (int) $stok->jumlah);

        // Belum ada jurnal yang diposting untuk tiket ini
        $this->assertSame(
            0,
            JurnalHeader::where('referensi_tipe', TiketServis::class)->where('referensi_id', $tiket->id)->count()
        );

        // Alasan pembatalan tercatat di log status
        $this->assertDatabaseHas('servis_status_log', [
            'tiket_servis_id' => $tiket->id,
            'status_ke' => 'batal',
        ]);
    }

    /** @test */
    public function test_batal_wajib_alasan(): void
    {
        $tiket = $this->buatTiket('SRV-BATAL-3-'.rand(10000, 99999));
        app(ServisService::class)->setEstimasi($tiket, 100000, 'Estimasi', $this->admin, [
            ['tipe' => 'jasa', 'nama_item' => 'Jasa', 'qty' => 1, 'harga' => 100000],
        ]);
        app(ServisService::class)->updateStatus($tiket->fresh(), 'disetujui', $this->admin);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Alasan pembatalan');

        app(ServisService::class)->updateStatus($tiket->fresh(), 'batal', $this->admin, '');
    }

    /** @test */
    public function test_batal_dari_selesai_me_reversal_jurnal_dan_tidak_mengembalikan_stok(): void
    {
        $gudang = Gudang::getDefault(1);

        $lcd = Produk::create([
            'nama' => 'LCD Reversal',
            'slug' => 'lcd-reversal-'.uniqid(),
            'satuan' => 'pcs',
            'harga_beli' => 100000,
            'harga_jual_retail' => 300000,
            'is_active' => true,
        ]);

        app(ProdukService::class)->tambahStokPembelian(
            $lcd->id,
            null,
            $gudang->id,
            5,
            100000,
            'Stok awal pengujian',
            $this->admin->id,
            postJurnal: false
        );

        $tiket = $this->buatTiket('SRV-BATAL-4-'.rand(10000, 99999));
        app(ServisService::class)->setEstimasi($tiket, 300000, 'Estimasi', $this->admin, [
            ['tipe' => 'part', 'produk_id' => $lcd->id, 'nama_item' => 'LCD Reversal', 'qty' => 1, 'harga' => 300000],
        ]);

        $service = app(ServisService::class);
        $service->updateStatus($tiket->fresh(), 'disetujui', $this->admin);
        $service->updateStatus($tiket->fresh(), 'dikerjakan', $this->admin);

        $service->inputPekerjaan($tiket->fresh(), [[
            'tipe' => 'part',
            'produk_id' => $lcd->id,
            'gudang_id' => $gudang->id,
            'qty' => 1,
            'harga' => 300000,
            'nama_item' => 'LCD Reversal',
        ]], $this->admin);

        $service->updateStatus($tiket->fresh(), 'qc', $this->admin);
        $service->updateStatus($tiket->fresh(), 'selesai', $this->admin);
        $service->bayar($tiket->fresh(), 'tunai', $this->admin, null, null, null, 300000);

        $jurnalAwal = JurnalHeader::where('referensi_tipe', TiketServis::class)
            ->where('referensi_id', $tiket->id)
            ->where('sumber', '!=', 'reversal')
            ->count();
        $this->assertGreaterThanOrEqual(2, $jurnalAwal, 'Jurnal pendapatan + pelunasan harus terposting');

        // Override: selesai → batal (butuh override-status + alasan)
        $service->updateStatus($tiket->fresh(), 'batal', $this->admin, 'Unit sudah tidak bisa diperbaiki — dibatalkan regulator');

        $tiket = $tiket->fresh();
        $this->assertSame('batal', $tiket->status);

        // Jurnal asal ditandai sudah dibalik (header reversal memakai referensi
        // jurnal_header, bukan tiket — jadi verifikasi lewat metadata header asal)
        $this->assertTrue(
            JurnalHeader::where('referensi_tipe', TiketServis::class)
                ->where('referensi_id', $tiket->id)
                ->where('sumber', '!=', 'reversal')
                ->where('is_reversed', true)
                ->exists(),
            'Jurnal servis harus dibalik saat pembatalan'
        );

        // Part sudah terpasang → stok TIDAK dikembalikan
        $stok = \App\Modules\Wms\Models\StokItem::where('produk_id', $lcd->id)
            ->where('gudang_id', $gudang->id)
            ->first();
        $this->assertEquals(4, (int) $stok->jumlah, 'Stok part terpasang tidak dikembalikan setelah selesai');
    }

    /** @test */
    public function test_pembatalan_online_tidak_tersedia_lagi(): void
    {
        // Pembatalan hanya atas permintaan langsung konsumen di toko → jalur
        // online (tracking) dicabut total, bukan sekadar disembunyikan di UI.
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('servis.tracking.batal'),
            'Jalur pembatalan online harus dicabut'
        );

        $tiket = $this->buatTiket('SRV-BATAL-6-'.rand(10000, 99999));
        app(ServisService::class)->setEstimasi($tiket, 300000, 'Estimasi', $this->admin, [
            ['tipe' => 'jasa', 'nama_item' => 'Jasa ganti layar', 'qty' => 1, 'harga' => 300000],
        ]);
        app(ServisService::class)->updateStatus($tiket->fresh(), 'disetujui', $this->admin);
        app(ServisService::class)->updateStatus($tiket->fresh(), 'dikerjakan', $this->admin);

        $response = $this->get("/tracking/".$tiket->fresh()->token_approval);
        $response->assertOk();

        $this->assertStringNotContainsString('Batalkan Servis Saya', $response->getContent());
    }

    /** @test */
    public function test_livewire_daftar_dan_kirim_ulang_persetujuan_perubahan(): void
    {
        session(['cabang_id' => 1]);
        $this->actingAs($this->admin, 'web');

        $tiket = $this->buatTiket('SRV-BATAL-7-'.rand(10000, 99999));
        app(ServisService::class)->setEstimasi($tiket, 400000, 'Estimasi', $this->admin, [
            ['tipe' => 'jasa', 'nama_item' => 'Jasa ganti layar', 'qty' => 1, 'harga' => 100000],
            ['tipe' => 'part', 'nama_item' => 'LCD', 'qty' => 1, 'harga' => 300000],
        ]);
        app(ServisService::class)->updateStatus($tiket->fresh(), 'disetujui', $this->admin);

        // Tambahan part di luar estimasi → menunggu persetujuan
        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'part',
            'nama_item' => 'Lem layar',
            'qty' => 1,
            'harga' => 20000,
            'dibatalkan_at' => null,
        ]);

        // Item dibuat langsung (bukan lewat inputPekerjaan) → evaluasi ulang flag
        app(ServisService::class)->refreshPerubahanLingkup($tiket->fresh());

        $component = Livewire::test(ServisBoard::class);

        $daftar = $component->viewData('tiketPerluPersetujuan');
        $this->assertNotEmpty($daftar, 'Tiket tertahan harus muncul di daftar');

        $component->call('kirimUlangPersetujuan');

        $this->assertNotNull($tiket->fresh()->revisi_lingkup_diajukan_at, 'Pengiriman ulang harus ditandai');
    }

    /** @test */
    public function test_batal_dari_status_tidak_valid_ditolak(): void
    {
        // User tanpa `servis.override-status` — transisi ngawur harus ditolak.
        $teknisi = User::create([
            'name' => 'Teknisi Batal',
            'email' => 'teknisi-batal@uteparts.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $teknisi->assignRole('teknisi');

        $tiket = $this->buatTiket('SRV-BATAL-5-'.rand(10000, 99999), 'menunggu_approval');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('tidak valid');

        app(ServisService::class)->updateStatus($tiket->fresh(), 'batal', $teknisi, 'Coba batalkan');
    }
}
