<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\ServisSparepart;
use App\Modules\Servis\Models\ServisStatusLog;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Servis\Services\ServisService;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase A (docs/review-servis-plan-perbaikan.md §3) — integritas jurnal & stok modul Servis.
 *
 * - T-01 Idempotensi jurnal `onSelesai` (guard `tanggal_selesai` + idempotency key)
 * - T-03 Restore stok part saat tiket `ditolak` (+ kolom `dibatalkan_at`)
 * - T-04 Cegah HPP/Persediaan dobel pada siklus ditolak → re-estimasi → selesai
 * - T-05 Guard anti-dobel simetris (legacy ↔ form pekerjaan) + jurnal legacy tak hilang
 * - T-06 Validasi `gudang_id` satu cabang dengan tiket
 * - T-07 `setEstimasi`: log `status_dari` benar, transaksi, token tak di-regenerate
 * - T-08 `inputSparepart`: status guard, produk sn ditolak
 * - T-09 `updateStatus`: status ngawur ditolak meski override
 *
 * Review Oracle (sesi fix-1):
 * - P0-1 `dediksiPartDariStokLog` rekap NET outflow (anti stok hantu siklus ditolak ganda)
 * - P0-2 input setelah tiket selesai ditolak + `onSelesai` gagal tertutup
 * - P1-1 `lockForUpdate` di `updateStatus` & `restoreStokPartDitolak`
 * - P1-3 Guard T-05 hanya untuk payload yang memuat baris `part`
 */
class ServisIntegritasFaseATest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Cabang $cabang;

    private Cabang $cabangLain;

    private Gudang $gudang;

    private Gudang $gudangLain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AkunCoaSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'Pusat', 'kode' => 'CBG-A1', 'is_active' => true]);
        $this->cabangLain = Cabang::create(['nama' => 'Selatan', 'kode' => 'CBG-A2', 'is_active' => true]);

        $this->gudang = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang Utama', 'kode' => 'GDG-A1', 'is_active' => true,
        ]);
        $this->gudangLain = Gudang::create([
            'cabang_id' => $this->cabangLain->id, 'nama' => 'Gudang Selatan', 'kode' => 'GDG-A2', 'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Admin Servis A',
            'email' => 'admin-servis-a@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin'); // punya servis.override-status
        $this->user->cabangs()->attach($this->cabang->id, ['is_default' => true]);
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    private function produk(string $nama, float $hargaBeli, float $hargaJual, bool $sn = false): Produk
    {
        return Produk::create([
            'nama' => $nama,
            'slug' => str($nama)->slug()->value(),
            'kategori' => 'Sparepart',
            'kondisi' => 'baru',
            'harga_beli' => $hargaBeli,
            'harga_jual_retail' => $hargaJual,
            'sn' => $sn,
        ]);
    }

    private function tiket(string $status = 'diagnosa', float $estimasi = 150000, string $no = 'SRV-A-0001'): TiketServis
    {
        return TiketServis::create([
            'no_tiket' => $no,
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan A',
            'telepon_pelanggan' => '081111222333',
            'jenis_hp' => 'iPhone 13',
            'keluhan' => 'Layar mati',
            'kondisi_fisik' => [],
            'foto_unit' => [],
            'status' => $status,
            'sumber' => 'walkin',
            'estimasi_biaya' => $estimasi,
        ]);
    }

    private function svc(): ServisService
    {
        return app(ServisService::class);
    }

    private function akunId(string $kode): int
    {
        return (int) AkunCOA::where('kode', $kode)->value('id');
    }

    private function kredit(string $kode): float
    {
        return (float) JurnalAkuntansi::where('akun_coa_id', $this->akunId($kode))->sum('kredit');
    }

    private function debit(string $kode): float
    {
        return (float) JurnalAkuntansi::where('akun_coa_id', $this->akunId($kode))->sum('debit');
    }

    private function jumlahStok(int $produkId, Gudang $gudang): int
    {
        return (int) StokItem::where('produk_id', $produkId)->where('gudang_id', $gudang->id)->value('jumlah');
    }

    // ==================================================================
    // T-01 — Idempotensi jurnal onSelesai
    // ==================================================================

    public function test_t01_jurnal_servis_hanya_satu_meski_status_selesai_diulang(): void
    {
        $tiket = $this->tiket('qc');
        $svc = $this->svc();

        $svc->updateStatus($tiket, 'selesai', $this->user, 'Selesai');

        $tiket->refresh();
        $this->assertSame('selesai', $tiket->status);
        $this->assertNotNull($tiket->tanggal_selesai);
        $tanggalSelesaiAwal = $tiket->tanggal_selesai->toDateTimeString();

        $noJurnalAwal = (string) JurnalAkuntansi::where('sumber', 'servis')->value('no_jurnal');
        $this->assertSame(2, JurnalAkuntansi::where('sumber', 'servis')->count(), 'Jasa saja = 2 baris');

        // Override mundur selesai → qc, lalu qc → selesai lagi
        $svc->updateStatus($tiket->fresh(), 'qc', $this->user, 'Cek ulang');
        $this->assertSame('qc', $tiket->fresh()->status);

        $svc->updateStatus($tiket->fresh(), 'selesai', $this->user, 'Selesai (ulang)');

        $tiket->refresh();
        $this->assertSame('selesai', $tiket->status);
        $this->assertSame(2, JurnalAkuntansi::where('sumber', 'servis')->count(), 'Jurnal tidak boleh dobel');
        $this->assertSame(
            $noJurnalAwal,
            (string) JurnalAkuntansi::where('sumber', 'servis')->value('no_jurnal'),
            'no_jurnal tidak boleh berubah'
        );
        $this->assertSame(
            $tanggalSelesaiAwal,
            $tiket->tanggal_selesai->toDateTimeString(),
            'tanggal_selesai tidak boleh ditimpa'
        );

        // Idempotency key tetap & hanya 1 header untuk tiket ini
        $this->assertSame(
            1,
            JurnalHeader::where('referensi_tipe', TiketServis::class)
                ->where('referensi_id', $tiket->id)->count()
        );
        $this->assertSame(
            'servis:'.$tiket->id,
            (string) JurnalHeader::where('referensi_tipe', TiketServis::class)
                ->where('referensi_id', $tiket->id)->value('idempotency_key')
        );

        // Garansi tetap 1 (updateOrCreate idempoten)
        $this->assertSame(1, (int) $tiket->garansi()->count());
    }

    public function test_t01_key_idempotensi_menylangga_posting_ganda_tiket_sama(): void
    {
        $tiket = $this->tiket('qc');
        $jurnalService = app(JurnalService::class);

        $jurnalService->post(
            noJurnal: $jurnalService->generateNoJurnal('servis', $this->cabang->id),
            tanggal: now(),
            sumber: 'servis',
            lines: [
                ['akun_kode' => '110-01', 'debit' => 10000, 'kredit' => 0],
                ['akun_kode' => '420-01', 'debit' => 0, 'kredit' => 10000],
            ],
            deskripsi: 'Klaim pertama',
            cabangId: $this->cabang->id,
            userId: $this->user->id,
            referensiTipe: TiketServis::class,
            referensiId: $tiket->id,
            idempotencyKey: 'servis:'.$tiket->id,
        );

        // Posting kedua dengan KEY SAMA harus ditolak (index unik), bukan dobel
        $gagal = false;
        try {
            $jurnalService->post(
                noJurnal: $jurnalService->generateNoJurnal('servis', $this->cabang->id),
                tanggal: now(),
                sumber: 'servis',
                lines: [
                    ['akun_kode' => '110-01', 'debit' => 10000, 'kredit' => 0],
                    ['akun_kode' => '420-01', 'debit' => 0, 'kredit' => 10000],
                ],
                deskripsi: 'Klaim kedua',
                cabangId: $this->cabang->id,
                userId: $this->user->id,
                referensiTipe: TiketServis::class,
                referensiId: $tiket->id,
                idempotencyKey: 'servis:'.$tiket->id,
            );
        } catch (\Throwable $e) {
            $gagal = true;
        }

        $this->assertTrue($gagal, 'Key idempotensi sama harus ditolak DB');
        $this->assertSame(2, JurnalAkuntansi::where('sumber', 'servis')->count());
    }

    // ==================================================================
    // T-03 — Restore stok saat tiket ditolak
    // ==================================================================

    public function test_t03_stok_part_kembali_penuh_saat_tiket_ditolak(): void
    {
        $produk = $this->produk('LCD A', 100000, 150000);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 10, 'jumlah_minimum' => 2,
        ]);

        $tiket = $this->tiket('dikerjakan');
        $svc = $this->svc();

        $svc->inputPekerjaan($tiket->fresh(), [[
            'tipe' => 'part', 'produk_id' => $produk->id, 'nama_item' => 'LCD',
            'qty' => 2, 'harga' => 150000, 'gudang_id' => $this->gudang->id,
        ]], $this->user);

        $this->assertSame(8, $this->jumlahStok($produk->id, $this->gudang), 'Part 2 terpotong');

        $svc->updateStatus($tiket->fresh(), 'ditolak', $this->user, 'Unit tidak layak');

        $this->assertSame(10, $this->jumlahStok($produk->id, $this->gudang), 'Stok wajib kembali penuh');
        $this->assertSame('ditolak', $tiket->fresh()->status);

        // Baris item ditandai dibatalkan
        $item = TiketServisItem::where('tiket_servis_id', $tiket->id)->where('tipe', 'part')->firstOrFail();
        $this->assertNotNull($item->fresh()->dibatalkan_at, 'Baris part wajib ditandai dibatalkan');

        // StokLog pembalik + StockMutationLog (SOT)
        $balik = StokLog::where('jenis', 'servis')
            ->where('referensi_tipe', TiketServis::class)
            ->where('referensi_id', $tiket->id)
            ->where('perubahan', '>', 0)
            ->first();
        $this->assertNotNull($balik, 'StokLog pembalik wajib ada');
        $this->assertSame(2, (int) $balik->perubahan);
        $this->assertSame(8, (int) $balik->jumlah_sebelum);
        $this->assertSame(10, (int) $balik->jumlah_setelah);
        $this->assertSame($this->gudang->id, (int) $balik->gudang_id, 'Harus kembali ke gudang asal');
        $this->assertStringContainsString('pembalikan part (ditolak)', (string) $balik->catatan);

        $this->assertSame(1, StockMutationLog::where('delta', '>', 0)->count());
        $this->assertSame(2, (int) StockMutationLog::where('delta', '>', 0)->value('delta'));
    }

    public function test_t03_stok_legacy_sparepart_kembali_saat_ditolak(): void
    {
        $produk = $this->produk('Baterai A', 40000, 80000);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 5, 'jumlah_minimum' => 1,
        ]);

        $tiket = $this->tiket('dikerjakan');
        $svc = $this->svc();

        $svc->inputSparepart($tiket->fresh(), [[
            'produk_id' => $produk->id, 'jumlah' => 3, 'harga_satuan' => 80000,
        ]], $this->gudang->id, $this->user);

        $this->assertSame(2, $this->jumlahStok($produk->id, $this->gudang));

        $svc->updateStatus($tiket->fresh(), 'ditolak', $this->user, 'Batal');

        $this->assertSame(5, $this->jumlahStok($produk->id, $this->gudang), 'Stok legacy wajib kembali');
        $sp = ServisSparepart::where('tiket_servis_id', $tiket->id)->firstOrFail();
        $this->assertNotNull($sp->fresh()->dibatalkan_at);
    }

    public function test_t03_ditolak_dari_menunggu_approval_tanpa_part_adalah_noop_aman(): void
    {
        $tiket = $this->tiket('menunggu_approval');
        $svc = $this->svc();

        $svc->updateStatus($tiket->fresh(), 'ditolak', $this->user, 'Pelanggan batal');

        $this->assertSame('ditolak', $tiket->fresh()->status);
        $this->assertSame(0, StokLog::count(), 'Tidak ada mutasi stok untuk tiket tanpa part');
        $this->assertSame(0, StockMutationLog::count());
        $this->assertNull($tiket->fresh()->tanggal_selesai);
    }

    public function test_t03_tiket_ditolak_tanpa_menembak_gudang_lain(): void
    {
        $produk = $this->produk('Kamera A', 50000, 90000);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 4, 'jumlah_minimum' => 0,
        ]);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $this->gudangLain->id, 'jumlah' => 4, 'jumlah_minimum' => 0,
        ]);

        $tiket = $this->tiket('dikerjakan');
        $svc = $this->svc();

        $svc->inputPekerjaan($tiket->fresh(), [[
            'tipe' => 'part', 'produk_id' => $produk->id, 'nama_item' => 'Kamera',
            'qty' => 1, 'harga' => 90000, 'gudang_id' => $this->gudang->id,
        ]], $this->user);

        $svc->updateStatus($tiket->fresh(), 'ditolak', $this->user, 'Batal');

        $this->assertSame(4, $this->jumlahStok($produk->id, $this->gudang), 'Gudang asal kembali');
        $this->assertSame(4, $this->jumlahStok($produk->id, $this->gudangLain), 'Gudang lain tidak boleh tersentuh');
    }

    // ==================================================================
    // T-04 — Cegah HPP/Persediaan dobel setelah ditolak → re-estimasi
    // ==================================================================

    public function test_t04_hpp_hanya_menghitung_part_aktif_satu_kali(): void
    {
        $produk = $this->produk('LCD T04', 100000, 150000);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 10, 'jumlah_minimum' => 2,
        ]);

        $tiket = $this->tiket('diagnosa', 150000, 'SRV-A-T04');
        $svc = $this->svc();

        $svc->setEstimasi($tiket->fresh(), 150000, 'Ganti LCD', $this->user);
        $svc->updateStatus($tiket->fresh(), 'disetujui', $this->user, 'Setuju');
        $svc->updateStatus($tiket->fresh(), 'dikerjakan', $this->user, 'Kerja');

        $svc->inputPekerjaan($tiket->fresh(), [[
            'tipe' => 'part', 'produk_id' => $produk->id, 'nama_item' => 'LCD lama',
            'qty' => 2, 'harga' => 150000, 'gudang_id' => $this->gudang->id,
        ]], $this->user);
        $this->assertSame(8, $this->jumlahStok($produk->id, $this->gudang));

        // Ditolak → stok balik, baris lama dibatalkan
        $svc->updateStatus($tiket->fresh(), 'ditolak', $this->user, 'Pelanggan tidak jadi');
        $this->assertSame(10, $this->jumlahStok($produk->id, $this->gudang));

        // Re-estimasi (ditolak → diagnosa → menunggu_approval), disetujui, dikerjakan
        $svc->updateStatus($tiket->fresh(), 'diagnosa', $this->user, 'Diagnosa ulang');
        $svc->setEstimasi($tiket->fresh(), 200000, 'Ganti LCD + jasa', $this->user);
        $svc->updateStatus($tiket->fresh(), 'disetujui', $this->user, 'Setuju');
        $svc->updateStatus($tiket->fresh(), 'dikerjakan', $this->user, 'Kerja');

        $svc->inputPekerjaan($tiket->fresh(), [[
            'tipe' => 'jasa', 'nama_item' => 'Ongkos pasang', 'qty' => 1, 'harga' => 50000,
        ], [
            'tipe' => 'part', 'produk_id' => $produk->id, 'nama_item' => 'LCD baru',
            'qty' => 1, 'harga' => 150000, 'gudang_id' => $this->gudang->id,
        ]], $this->user);

        $this->assertSame(9, $this->jumlahStok($produk->id, $this->gudang));

        $svc->updateStatus($tiket->fresh(), 'qc', $this->user, 'QC');
        $svc->updateStatus($tiket->fresh(), 'selesai', $this->user, 'Selesai');

        // Jurnal: HPP + Persediaan hanya part AKTIF (1 × 100.000)
        $this->assertEqualsWithDelta(100000.0, $this->debit('510-02'), 0.01, 'HPP hanya part aktif 1x');
        $this->assertEqualsWithDelta(100000.0, $this->kredit('130-01'), 0.01, 'Persediaan hanya part aktif 1x');
        $this->assertEqualsWithDelta(150000.0, $this->kredit('410-01'), 0.01, 'Pendapatan part hanya part aktif 1x');
        $this->assertEqualsWithDelta(50000.0, $this->kredit('420-01'), 0.01, 'Pendapatan jasa dari item jasa');

        // Jurnal tetap balance
        $no = (string) JurnalAkuntansi::where('sumber', 'servis')->value('no_jurnal');
        $rows = JurnalAkuntansi::where('no_jurnal', $no)->get();
        $this->assertEqualsWithDelta((float) $rows->sum('debit'), (float) $rows->sum('kredit'), 0.01);
    }

    public function test_t04_legacy_sparepart_terbatalkan_tidak_ikut_hpp(): void
    {
        $produk = $this->produk('Baterai T04', 40000, 80000);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 6, 'jumlah_minimum' => 0,
        ]);

        $tiket = $this->tiket('dikerjakan', 100000, 'SRV-A-T04B');
        $svc = $this->svc();

        $svc->inputSparepart($tiket->fresh(), [[
            'produk_id' => $produk->id, 'jumlah' => 2, 'harga_satuan' => 80000,
        ]], $this->gudang->id, $this->user);
        $this->assertSame(4, $this->jumlahStok($produk->id, $this->gudang));

        $svc->updateStatus($tiket->fresh(), 'ditolak', $this->user, 'Batal');
        $this->assertSame(6, $this->jumlahStok($produk->id, $this->gudang));

        // Re-estimasi + input jasa saja (part lama sudah dibatalkan)
        $svc->updateStatus($tiket->fresh(), 'diagnosa', $this->user, 'Diagnosa ulang');
        $svc->setEstimasi($tiket->fresh(), 100000, 'Jasa saja', $this->user);
        $svc->updateStatus($tiket->fresh(), 'disetujui', $this->user, 'Setuju');
        $svc->updateStatus($tiket->fresh(), 'dikerjakan', $this->user, 'Kerja');
        $svc->inputPekerjaan($tiket->fresh(), [[
            'tipe' => 'jasa', 'nama_item' => 'Jasa diagnosa', 'qty' => 1, 'harga' => 100000,
        ]], $this->user);

        $svc->updateStatus($tiket->fresh(), 'qc', $this->user, 'QC');
        $svc->updateStatus($tiket->fresh(), 'selesai', $this->user, 'Selesai');

        $this->assertEqualsWithDelta(0.0, $this->debit('510-02'), 0.01, 'Part terbatalkan tidak boleh jadi HPP');
        $this->assertEqualsWithDelta(0.0, $this->kredit('130-01'), 0.01, 'Persediaan tidak boleh dobel');
        $this->assertEqualsWithDelta(0.0, $this->kredit('410-01'), 0.01, 'Tidak ada pendapatan part');
        $this->assertEqualsWithDelta(100000.0, $this->kredit('420-01'), 0.01);
    }

    // ==================================================================
    // T-05 — Guard anti-dobel simetris + jurnal legacy tidak hilang
    // ==================================================================

    public function test_t05_jurnal_legacy_tetap_ada_walau_items_hanya_jasa(): void
    {
        $produk = $this->produk('Speaker A', 30000, 70000);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 6, 'jumlah_minimum' => 0,
        ]);

        $tiket = $this->tiket('dikerjakan', 50000, 'SRV-A-T05');
        $svc = $this->svc();

        // Legacy: 2 speaker (harga 70.000, hpp 30.000) via jalur inputSparepart
        $svc->inputSparepart($tiket->fresh(), [[
            'produk_id' => $produk->id, 'jumlah' => 2, 'harga_satuan' => 70000,
        ]], $this->gudang->id, $this->user);
        $this->assertSame(4, $this->jumlahStok($produk->id, $this->gudang));

        // Item kerja HANYA jasa — data campuran yang bisa ada dari sebelum guard
        // T-05 dipasang (guard symmetric sekarang melarang pembuatan state ini
        // lewat service). Jurnal WAJIB tetap menghitung legacy.
        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'nama_item' => 'Ongkos pasang',
            'qty' => 1,
            'harga' => 50000,
            'hpp' => 0,
        ]);

        $svc->updateStatus($tiket->fresh(), 'qc', $this->user, 'QC');
        $svc->updateStatus($tiket->fresh(), 'selesai', $this->user, 'Selesai');

        $this->assertEqualsWithDelta(50000.0, $this->kredit('420-01'), 0.01, 'Jasa dari item jasa');
        $this->assertEqualsWithDelta(140000.0, $this->kredit('410-01'), 0.01, 'Pendapatan part legacy ikut');
        $this->assertEqualsWithDelta(60000.0, $this->debit('510-02'), 0.01, 'HPP part legacy ikut');
        $this->assertEqualsWithDelta(60000.0, $this->kredit('130-01'), 0.01, 'Persediaan part legacy ikut');
        $this->assertEqualsWithDelta(190000.0, $this->debit('120-01'), 0.01, 'Piutang Usaha = jasa + part');
    }

    public function test_t05_guard_anti_dobel_dua_arah(): void
    {
        $produk = $this->produk('Kabel A', 10000, 25000);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 10, 'jumlah_minimum' => 0,
        ]);

        $tiket = $this->tiket('dikerjakan', 100000, 'SRV-A-T05B');
        $svc = $this->svc();

        // Legacy dulu
        $svc->inputSparepart($tiket->fresh(), [[
            'produk_id' => $produk->id, 'jumlah' => 1, 'harga_satuan' => 25000,
        ]], $this->gudang->id, $this->user);
        $this->assertSame(9, $this->jumlahStok($produk->id, $this->gudang));

        // [T-05] Form pekerjaan ditolak — stok tidak boleh terpotong 2x
        $err = null;
        try {
            $svc->inputPekerjaan($tiket->fresh(), [[
                'tipe' => 'part', 'produk_id' => $produk->id, 'nama_item' => 'Kabel',
                'qty' => 1, 'harga' => 25000, 'gudang_id' => $this->gudang->id,
            ]], $this->user);
        } catch (\Exception $e) {
            $err = $e;
        }
        $this->assertNotNull($err, 'inputPekerjaan wajib menolak bila legacy terisi');
        $this->assertSame(9, $this->jumlahStok($produk->id, $this->gudang), 'Stok tidak boleh terpotong 2x');

        // Arah sebaliknya: form pekerjaan dulu, lalu legacy ditolak
        $tiket2 = $this->tiket('dikerjakan', 100000, 'SRV-A-T05C');
        $svc->inputPekerjaan($tiket2->fresh(), [[
            'tipe' => 'part', 'produk_id' => $produk->id, 'nama_item' => 'Kabel',
            'qty' => 1, 'harga' => 25000, 'gudang_id' => $this->gudang->id,
        ]], $this->user);
        $this->assertSame(8, $this->jumlahStok($produk->id, $this->gudang));

        $err2 = null;
        try {
            $svc->inputSparepart($tiket2->fresh(), [[
                'produk_id' => $produk->id, 'jumlah' => 1, 'harga_satuan' => 25000,
            ]], $this->gudang->id, $this->user);
        } catch (\Exception $e) {
            $err2 = $e;
        }
        $this->assertNotNull($err2, 'inputSparepart wajib menolak bila item part via form pekerjaan');
        $this->assertStringContainsString('form pekerjaan', $err2->getMessage());
        $this->assertSame(8, $this->jumlahStok($produk->id, $this->gudang), 'Stok tidak boleh terpotong 2x');
    }

    // ==================================================================
    // T-06 — Validasi gudang satu cabang
    // ==================================================================

    public function test_t06_gudang_lintas_cabang_ditolak(): void
    {
        $produk = $this->produk('Sensor A', 20000, 45000);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $this->gudangLain->id, 'jumlah' => 10, 'jumlah_minimum' => 0,
        ]);

        $tiket = $this->tiket('dikerjakan', 100000, 'SRV-A-T06');
        $svc = $this->svc();

        // inputSparepart: gudang cabang lain
        $err = null;
        try {
            $svc->inputSparepart($tiket->fresh(), [[
                'produk_id' => $produk->id, 'jumlah' => 1, 'harga_satuan' => 45000,
            ]], $this->gudangLain->id, $this->user);
        } catch (\Exception $e) {
            $err = $e;
        }
        $this->assertNotNull($err, 'Gudang cabang lain wajib ditolak');
        $this->assertSame('Gudang harus berada di cabang yang sama dengan tiket', $err->getMessage());
        $this->assertSame(10, $this->jumlahStok($produk->id, $this->gudangLain), 'Stok tidak boleh tersentuh');

        // inputPekerjaan: gudang cabang lain
        $err2 = null;
        try {
            $svc->inputPekerjaan($tiket->fresh(), [[
                'tipe' => 'part', 'produk_id' => $produk->id, 'nama_item' => 'Sensor',
                'qty' => 1, 'harga' => 45000, 'gudang_id' => $this->gudangLain->id,
            ]], $this->user);
        } catch (\Exception $e) {
            $err2 = $e;
        }
        $this->assertNotNull($err2, 'Gudang cabang lain wajib ditolak (form pekerjaan)');
        $this->assertSame('Gudang harus berada di cabang yang sama dengan tiket', $err2->getMessage());
        $this->assertSame(0, TiketServisItem::where('tiket_servis_id', $tiket->id)->count());
    }

    // ==================================================================
    // T-07 — setEstimasi
    // ==================================================================

    public function test_t07_log_status_dari_benar_dan_token_tidak_di_regenerate(): void
    {
        $tiket = $this->tiket('diagnosa');
        $svc = $this->svc();

        $svc->setEstimasi($tiket->fresh(), 150000, 'Ganti LCD', $this->user);

        $log = ServisStatusLog::where('tiket_servis_id', $tiket->id)
            ->where('status_ke', 'menunggu_approval')->firstOrFail();
        $this->assertSame('diagnosa', $log->status_dari, 'status_dari harus status SEBELUM update');
        $this->assertNotSame('menunggu_approval', $log->status_dari, 'Self-loop harus hilang');

        $tokenAwal = (string) $tiket->fresh()->token_approval;
        $this->assertNotSame('', $tokenAwal);

        // Estimasi diedit ulang dari status ditolak (re-estimasi) — link lama harus hidup
        $svc->updateStatus($tiket->fresh(), 'ditolak', $this->user, 'Revisi estimasi');
        $this->assertSame('ditolak', $tiket->fresh()->status);

        $svc->updateStatus($tiket->fresh(), 'diagnosa', $this->user, 'Diagnosa ulang');
        $svc->setEstimasi($tiket->fresh(), 175000, 'Ganti LCD + tempered', $this->user);

        $this->assertSame($tokenAwal, (string) $tiket->fresh()->token_approval, 'Token tidak boleh di-regenerate');
        $this->assertSame('menunggu_approval', $tiket->fresh()->status);
        $this->assertEqualsWithDelta(175000.0, (float) $tiket->fresh()->estimasi_biaya, 0.01);
    }

    public function test_t07_setestimasi_menolak_status_di_luar_allowlist(): void
    {
        $tiket = $this->tiket('dikerjakan');
        $err = null;

        try {
            $this->svc()->setEstimasi($tiket->fresh(), 100000, 'Ubah', $this->user);
        } catch (\Exception $e) {
            $err = $e;
        }

        $this->assertNotNull($err, 'Estimasi hanya dari diagnosa/ditolak');
        $this->assertSame('dikerjakan', $tiket->fresh()->status, 'Status tidak boleh berubah');
        $this->assertNull($tiket->fresh()->token_approval, 'Token tidak boleh dibuat');
    }

    // ==================================================================
    // T-08 — inputSparepart guard
    // ==================================================================

    public function test_t08_input_sparepart_ditolak_saat_diagnosa(): void
    {
        $produk = $this->produk('Frame A', 20000, 45000);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 5, 'jumlah_minimum' => 0,
        ]);

        $tiket = $this->tiket('diagnosa', 100000, 'SRV-A-T08');
        $err = null;

        try {
            $this->svc()->inputSparepart($tiket->fresh(), [[
                'produk_id' => $produk->id, 'jumlah' => 1, 'harga_satuan' => 45000,
            ]], $this->gudang->id, $this->user);
        } catch (\Exception $e) {
            $err = $e;
        }

        $this->assertNotNull($err, 'Part tidak boleh dipotong sebelum estimasi disetujui');
        $this->assertStringContainsString('disetujui atau dikerjakan', $err->getMessage());
        $this->assertSame(5, $this->jumlahStok($produk->id, $this->gudang));
        $this->assertSame(0, ServisSparepart::count());
    }

    public function test_t08_produk_sn_ditolak_di_jalur_legacy(): void
    {
        $produk = $this->produk('Layar SN', 200000, 350000, true);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 3, 'jumlah_minimum' => 0,
        ]);

        $tiket = $this->tiket('dikerjakan', 350000, 'SRV-A-T08B');
        $err = null;

        try {
            $this->svc()->inputSparepart($tiket->fresh(), [[
                'produk_id' => $produk->id, 'jumlah' => 1, 'harga_satuan' => 350000,
            ]], $this->gudang->id, $this->user);
        } catch (\Exception $e) {
            $err = $e;
        }

        $this->assertNotNull($err, 'Produk sn wajib ditolak di jalur legacy (tanpa klaim SN)');
        $this->assertSame('Produk bernomor seri harus diinput lewat form pekerjaan', $err->getMessage());
        $this->assertSame(3, $this->jumlahStok($produk->id, $this->gudang), 'Stok tidak boleh terpotong');
        $this->assertSame(0, ServisSparepart::count());
    }

    // ==================================================================
    // T-09 — updateStatus validasi status
    // ==================================================================

    public function test_t09_status_ngawur_ditolak_walaupun_override(): void
    {
        $tiket = $this->tiket('dikerjakan');
        $err = null;

        try {
            $this->svc()->updateStatus($tiket->fresh(), 'ngawur_banget', $this->user, 'Override');
        } catch (\Exception $e) {
            $err = $e;
        }

        $this->assertNotNull($err, 'Status di luar STATUS_VALID wajib ditolak');
        $this->assertSame('Status tidak valid', $err->getMessage());
        $this->assertSame('dikerjakan', $tiket->fresh()->status);
        $this->assertNull($tiket->fresh()->tanggal_selesai);
        $this->assertSame(0, JurnalAkuntansi::count());
    }

    // ==================================================================
    // [P0-1] dediksiPartDariStokLog — rekap NET outflow (anti stok hantu)
    // ==================================================================

    public function test_t03_siklus_ditolak_ganda_tidak_menghasilkan_stok_hantu(): void
    {
        $produk = $this->produk('LCD P01', 100000, 150000);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 10, 'jumlah_minimum' => 2,
        ]);

        $tiket = $this->tiket('dikerjakan', 150000, 'SRV-A-P01');
        $svc = $this->svc();

        // Stok awal 10
        $stokAwal = $this->jumlahStok($produk->id, $this->gudang);
        $this->assertSame(10, $stokAwal);

        // ── Siklus 1: pakai part 2 → stok 8, lalu ditolak → kembali 10
        $svc->inputPekerjaan($tiket->fresh(), [[
            'tipe' => 'part', 'produk_id' => $produk->id, 'nama_item' => 'LCD siklus 1',
            'qty' => 2, 'harga' => 150000, 'gudang_id' => $this->gudang->id,
        ]], $this->user);
        $this->assertSame(8, $this->jumlahStok($produk->id, $this->gudang), 'Siklus 1: part 2 terpotong');

        $svc->updateStatus($tiket->fresh(), 'ditolak', $this->user, 'Pelanggan batal');
        $this->assertSame(10, $this->jumlahStok($produk->id, $this->gudang), 'Siklus 1: stok kembali 10');

        // ── Re-estimasi (ditolak → diagnosa → menunggu_approval → disetujui → dikerjakan)
        $svc->updateStatus($tiket->fresh(), 'diagnosa', $this->user, 'Diagnosa ulang');
        $svc->setEstimasi($tiket->fresh(), 150000, 'Ganti LCD lagi', $this->user);
        $svc->updateStatus($tiket->fresh(), 'disetujui', $this->user, 'Setuju');
        $svc->updateStatus($tiket->fresh(), 'dikerjakan', $this->user, 'Kerja');

        // ── Siklus 2: pakai part 1 → stok 9
        $svc->inputPekerjaan($tiket->fresh(), [[
            'tipe' => 'part', 'produk_id' => $produk->id, 'nama_item' => 'LCD siklus 2',
            'qty' => 1, 'harga' => 150000, 'gudang_id' => $this->gudang->id,
        ]], $this->user);
        $this->assertSame(9, $this->jumlahStok($produk->id, $this->gudang), 'Siklus 2: part 1 terpotong');

        // ── Ditolak lagi → stok WAJIB kembali 10, bukan 12 (stok hantu)
        $svc->updateStatus($tiket->fresh(), 'ditolak', $this->user, 'Batal lagi');

        $stokAkhir = $this->jumlahStok($produk->id, $this->gudang);
        $this->assertSame(
            $stokAwal,
            $stokAkhir,
            'Stok akhir harus PERSIS sama dengan stok awal — rekap net outflow mencegah pembalikan hantu'
        );
        $this->assertNotSame(12, $stokAkhir, 'Stok hantu (12) tidak boleh muncul');
        $this->assertSame('ditolak', $tiket->fresh()->status);

        // Kedua baris part ditandai dibatalkan
        $this->assertSame(2, TiketServisItem::where('tiket_servis_id', $tiket->id)
            ->where('tipe', 'part')->whereNotNull('dibatalkan_at')->count());
    }

    // ==================================================================
    // [P0-2] Item setelah tiket selesai = pendapatan/stok tanpa jurnal
    // ==================================================================

    public function test_p0_2_input_setelah_tiket_selesai_ditolak(): void
    {
        $produk = $this->produk('Baterai P02', 40000, 80000);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 5, 'jumlah_minimum' => 0,
        ]);

        $tiket = $this->tiket('qc', 100000, 'SRV-A-P02');
        $svc = $this->svc();

        $svc->updateStatus($tiket->fresh(), 'selesai', $this->user, 'Selesai');
        $this->assertNotNull($tiket->fresh()->tanggal_selesai);
        $jurnalAwal = JurnalAkuntansi::count();
        $stokAwal = $this->jumlahStok($produk->id, $this->gudang);

        // Legacy: inputSparepart setelah selesai WAJIB ditolak
        $err = null;
        try {
            $svc->inputSparepart($tiket->fresh(), [[
                'produk_id' => $produk->id, 'jumlah' => 1, 'harga_satuan' => 80000,
            ]], $this->gudang->id, $this->user);
        } catch (\Exception $e) {
            $err = $e;
        }
        $this->assertNotNull($err, 'inputSparepart setelah selesai wajib ditolak');
        $this->assertSame('Tidak dapat menambah item pada tiket yang sudah pernah diselesaikan', $err->getMessage());

        // Form pekerjaan: inputPekerjaan setelah selesai WAJIB ditolak
        $err2 = null;
        try {
            $svc->inputPekerjaan($tiket->fresh(), [[
                'tipe' => 'part', 'produk_id' => $produk->id, 'nama_item' => 'Baterai',
                'qty' => 1, 'harga' => 80000, 'gudang_id' => $this->gudang->id,
            ]], $this->user);
        } catch (\Exception $e) {
            $err2 = $e;
        }
        $this->assertNotNull($err2, 'inputPekerjaan setelah selesai wajib ditolak');
        $this->assertSame('Tidak dapat menambah item pada tiket yang sudah pernah diselesaikan', $err2->getMessage());

        // Tidak ada item baru, tidak ada stok berubah, tidak ada jurnal baru
        $this->assertSame(0, TiketServisItem::where('tiket_servis_id', $tiket->id)->count());
        $this->assertSame(0, ServisSparepart::where('tiket_servis_id', $tiket->id)->count());
        $this->assertSame($stokAwal, $this->jumlahStok($produk->id, $this->gudang));
        $this->assertSame($jurnalAwal, JurnalAkuntansi::count());

        // Guard juga berlaku walau status di-override mundur ke 'dikerjakan'
        $svc->updateStatus($tiket->fresh(), 'dikerjakan', $this->user, 'Override mundur');

        $err3 = null;
        try {
            $svc->inputPekerjaan($tiket->fresh(), [[
                'tipe' => 'jasa', 'nama_item' => 'Jasa tambahan', 'qty' => 1, 'harga' => 50000,
            ]], $this->user);
        } catch (\Exception $e) {
            $err3 = $e;
        }
        $this->assertNotNull($err3, 'Override mundur tidak boleh membuka jalan input setelah selesai');
        $this->assertSame(0, TiketServisItem::where('tiket_servis_id', $tiket->id)->count());
    }

    public function test_p0_2_onselesai_gagal_tepat_kalau_item_masuk_setelah_selesai(): void
    {
        $tiket = $this->tiket('qc', 100000, 'SRV-A-P02B');
        $svc = $this->svc();

        $svc->updateStatus($tiket->fresh(), 'selesai', $this->user, 'Selesai');
        $tanggalSelesai = $tiket->fresh()->tanggal_selesai;
        $jurnalAwal = JurnalAkuntansi::count();

        // Simulasi data yang lolos dari jalur lama / impor langsung: baris item
        // dibuat SETELAH jurnal selesai ter-post.
        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id,
            'tipe' => 'jasa',
            'nama_item' => 'Jasa telat',
            'qty' => 1,
            'harga' => 75000,
            'hpp' => 0,
        ]);
        TiketServisItem::where('tiket_servis_id', $tiket->id)
            ->update(['created_at' => $tanggalSelesai->copy()->addHour()]);

        // Override mundur lalu selesai lagi → harus gagal tertutup, bukan diam-diam
        $svc->updateStatus($tiket->fresh(), 'qc', $this->user, 'Cek ulang');

        $err = null;
        try {
            $svc->updateStatus($tiket->fresh(), 'selesai', $this->user, 'Selesai ulang');
        } catch (\Throwable $e) {
            $err = $e;
        }

        $this->assertNotNull($err, 'Item setelah selesai harus membuat posting ulang gagal');
        $this->assertInstanceOf(\RuntimeException::class, $err);
        $this->assertSame(
            'Tiket sudah pernah diselesaikan. Penambahan item baru setelah selesai memerlukan penyesuaian jurnal manual.',
            $err->getMessage()
        );
        $this->assertSame('qc', $tiket->fresh()->status, 'Status tidak boleh final (rollback)');
        $this->assertSame($jurnalAwal, JurnalAkuntansi::count(), 'Tidak boleh ada jurnal tambahan');
    }

    // ==================================================================
    // [P1-3] Guard T-05 hanya berlaku bila payload berisi baris 'part'
    // ==================================================================

    public function test_p1_3_jasa_tetap_boleh_dicatat_saat_legacy_sparepart_ada(): void
    {
        $produk = $this->produk('Kabel P13', 10000, 25000);
        StokItem::create([
            'produk_id' => $produk->id, 'gudang_id' => $this->gudang->id, 'jumlah' => 10, 'jumlah_minimum' => 0,
        ]);

        $tiket = $this->tiket('dikerjakan', 100000, 'SRV-A-P13');
        $svc = $this->svc();

        // Legacy sparepart aktif (1 kabel) → stok 9
        $svc->inputSparepart($tiket->fresh(), [[
            'produk_id' => $produk->id, 'jumlah' => 1, 'harga_satuan' => 25000,
        ]], $this->gudang->id, $this->user);
        $this->assertSame(9, $this->jumlahStok($produk->id, $this->gudang));

        // Payload HANYA baris jasa → HARUS berhasil (jasa tidak menyentuh stok)
        $created = $svc->inputPekerjaan($tiket->fresh(), [[
            'tipe' => 'jasa', 'nama_item' => 'Ongkos pasang', 'qty' => 1, 'harga' => 50000,
        ]], $this->user);

        $this->assertCount(1, $created, 'Baris jasa wajib tersimpan');
        $this->assertSame('jasa', $created[0]->tipe);
        $this->assertSame(1, TiketServisItem::where('tiket_servis_id', $tiket->id)
            ->where('tipe', 'jasa')->count());
        $this->assertSame(9, $this->jumlahStok($produk->id, $this->gudang), 'Jasa tidak boleh sentuh stok');

        // Payload campur (part + jasa) TETAP ditolak karena ada baris part
        $err = null;
        try {
            $svc->inputPekerjaan($tiket->fresh(), [[
                'tipe' => 'part', 'produk_id' => $produk->id, 'nama_item' => 'Kabel lagi',
                'qty' => 1, 'harga' => 25000, 'gudang_id' => $this->gudang->id,
            ], [
                'tipe' => 'jasa', 'nama_item' => 'Jasa lain', 'qty' => 1, 'harga' => 10000,
            ]], $this->user);
        } catch (\Exception $e) {
            $err = $e;
        }
        $this->assertNotNull($err, 'Payload yang memuat baris part tetap wajib ditolak');
        $this->assertSame(
            'Item part sudah dicatat lewat form sparepart. Untuk menambah jasa, kirim baris tipe jasa saja.',
            $err->getMessage()
        );
        $this->assertSame(9, $this->jumlahStok($produk->id, $this->gudang), 'Stok tidak boleh terpotong 2x');
        $this->assertSame(1, TiketServisItem::where('tiket_servis_id', $tiket->id)->count());
    }
}
