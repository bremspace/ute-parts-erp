<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Akunting\Services\PembayaranSubledgerService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Models\SkemaKomisi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Servis\Models\TiketServisItem;
use App\Modules\Servis\Services\ServisService;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * [F-20] PPN keluaran servis (DPP vs 220-01, pola PajakService POS).
 * [B-3] Pelunasan servis setelah cicilan via Akunting: hanya sisa, idempoten,
 *       subledger piutang sinkron, GL 120-01 tidak negatif.
 * [F-09] Batal/ditolak setelah selesai: baris Komisi terkait dibatalkan agar
 *       tidak masuk payroll/approval.
 */
class ServisPajakPelunasanKomisiBatalTest extends TestCase
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
            'name' => 'Admin Servis Pajak',
            'email' => 'admin-servis-pajak@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);

        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');
    }

    private function buatPelanggan(bool $reseller = false): Pelanggan
    {
        return Pelanggan::create([
            'nama' => 'Pelanggan '.uniqid(),
            'telepon' => '0812'.rand(1000000, 9999999),
            'is_reseller' => $reseller,
        ]);
    }

    private function buatTiket(string $status = 'qc', float $estimasi = 200000, ?int $pelangganId = null): TiketServis
    {
        return TiketServis::create([
            'no_tiket' => 'SRV-PPN-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $pelangganId,
            'nama_pelanggan' => 'Pelanggan Test',
            'telepon_pelanggan' => '08123456789',
            'jenis_hp' => 'Samsung S21',
            'tipe_kunci' => 'tidak_ada',
            'keluhan' => 'Layar bergaris',
            'status' => $status,
            'sumber' => 'walkin',
            'estimasi_biaya' => $estimasi,
        ]);
    }

    private function aktifkanPpn(float $persen = 11.0, string $mode = 'exclusive'): void
    {
        $pajak = app(PajakService::class);
        $pajak->set($this->cabang->id, true, $persen);
        DB::table('konfigurasi')->updateOrInsert(
            ['kunci' => 'pajak_mode.'.$this->cabang->id],
            ['nilai' => $mode, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function selesai(TiketServis $tiket): TiketServis
    {
        return app(ServisService::class)->updateStatus($tiket, 'selesai', $this->user, 'Pekerjaan selesai');
    }

    /** Jumlah baris jurnal (debit/kredit) per akun untuk referensi tiket. */
    private function barisJurnalTiket(TiketServis $tiket, string $kode): array
    {
        $akun = AkunCOA::where('kode', $kode)->firstOrFail();

        return DB::table('jurnal_akuntansi')
            ->where('akun_coa_id', $akun->id)
            ->where('referensi_tipe', TiketServis::class)
            ->where('referensi_id', $tiket->id)
            ->get(['debit', 'kredit'])
            ->map(fn ($r) => [(float) $r->debit, (float) $r->kredit])
            ->all();
    }

    private function saldoGl(string $kode): float
    {
        $akun = AkunCOA::where('kode', $kode)->firstOrFail();

        return round((float) DB::table('jurnal_akuntansi')->where('akun_coa_id', $akun->id)->sum('debit')
            - (float) DB::table('jurnal_akuntansi')->where('akun_coa_id', $akun->id)->sum('kredit'), 2);
    }

    private function piutangTiket(TiketServis $tiket): Piutang
    {
        return Piutang::where('referensi_tipe', TiketServis::class)
            ->where('referensi_id', $tiket->id)
            ->firstOrFail();
    }

    // =====================================================================
    // F-20 — PPN keluaran servis
    // =====================================================================

    public function test_selesai_pkp_exclusive_memisah_dpp_dan_ppn_keluaran(): void
    {
        $this->aktifkanPpn(11.0, 'exclusive');
        $pelanggan = $this->buatPelanggan();
        $tiket = $this->buatTiket('qc', 200000, $pelanggan->id);

        $this->selesai($tiket);
        $tiket->refresh();

        // DPP jasa 200.000, PPN 11% = 22.000, tagihan 222.000.
        $this->assertSame([[222000.0, 0.0]], $this->barisJurnalTiket($tiket, '120-01'));
        $this->assertSame([[0.0, 200000.0]], $this->barisJurnalTiket($tiket, '420-01'));
        $this->assertSame([[0.0, 22000.0]], $this->barisJurnalTiket($tiket, '220-01'));

        $piutang = $this->piutangTiket($tiket);
        $this->assertEqualsWithDelta(222000.0, (float) $piutang->jumlah, 0.01);

        $this->assertEqualsWithDelta(200000.0, (float) $tiket->dpp, 0.01);
        $this->assertEqualsWithDelta(22000.0, (float) $tiket->ppn_nominal, 0.01);
        $this->assertEqualsWithDelta(11.0, (float) $tiket->ppn_percent, 0.01);

        $this->assertEqualsWithDelta(222000.0, app(ServisService::class)->getTotalTagihanBersih($tiket), 0.01);

        $rincian = $tiket->getRincianBiayaLengkap();
        $this->assertArrayHasKey('ppn_nominal', $rincian);
        $this->assertEqualsWithDelta(22000.0, (float) $rincian['ppn_nominal'], 0.01);
        $this->assertEqualsWithDelta(222000.0, (float) $rincian['total_akhir'], 0.01);
    }

    public function test_selesai_pkp_inclusive_pendapatan_hanya_dpp(): void
    {
        $this->aktifkanPpn(11.0, 'inclusive');
        $pelanggan = $this->buatPelanggan();
        $tiket = $this->buatTiket('qc', 111000, $pelanggan->id);

        $this->selesai($tiket);
        $tiket->refresh();

        // Harga 111.000 termasuk PPN → DPP 100.000 + PPN 11.000.
        $this->assertSame([[111000.0, 0.0]], $this->barisJurnalTiket($tiket, '120-01'));
        $this->assertSame([[0.0, 100000.0]], $this->barisJurnalTiket($tiket, '420-01'));
        $this->assertSame([[0.0, 11000.0]], $this->barisJurnalTiket($tiket, '220-01'));
        $this->assertEqualsWithDelta(111000.0, (float) $this->piutangTiket($tiket)->jumlah, 0.01);
    }

    public function test_selesai_non_pkp_tanpa_ppn(): void
    {
        $pelanggan = $this->buatPelanggan();
        $tiket = $this->buatTiket('qc', 200000, $pelanggan->id);

        $this->selesai($tiket);
        $tiket->refresh();

        $this->assertSame([[200000.0, 0.0]], $this->barisJurnalTiket($tiket, '120-01'));
        $this->assertSame([[0.0, 200000.0]], $this->barisJurnalTiket($tiket, '420-01'));
        $this->assertSame([], $this->barisJurnalTiket($tiket, '220-01'));
        $this->assertEqualsWithDelta(0.0, (float) $tiket->ppn_nominal, 0.01);
        $this->assertEqualsWithDelta(200000.0, app(ServisService::class)->getTotalTagihanBersih($tiket), 0.01);
    }

    public function test_selesai_jasa_dan_part_dpp_terpisah_dan_jurnal_balance(): void
    {
        $this->aktifkanPpn(11.0, 'exclusive');
        $pelanggan = $this->buatPelanggan();
        $tiket = $this->buatTiket('qc', 0, $pelanggan->id);

        // Item pekerjaan (tanpa estimasi → tanpa perubahan lingkup/stok).
        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id, 'tipe' => 'jasa',
            'nama_item' => 'Ganti LCD', 'qty' => 1, 'harga' => 200000, 'hpp' => 0,
        ]);
        TiketServisItem::create([
            'tiket_servis_id' => $tiket->id, 'tipe' => 'part',
            'nama_item' => 'LCD Samsung', 'qty' => 1, 'harga' => 100000, 'hpp' => 50000,
        ]);

        $this->selesai($tiket);
        $tiket->refresh();

        $this->assertSame([[333000.0, 0.0]], $this->barisJurnalTiket($tiket, '120-01'));
        $this->assertSame([[0.0, 200000.0]], $this->barisJurnalTiket($tiket, '420-01'));
        $this->assertSame([[0.0, 100000.0]], $this->barisJurnalTiket($tiket, '410-01'));
        $this->assertSame([[0.0, 33000.0]], $this->barisJurnalTiket($tiket, '220-01'));
        $this->assertSame([[50000.0, 0.0]], $this->barisJurnalTiket($tiket, '510-02'));
        $this->assertSame([[0.0, 50000.0]], $this->barisJurnalTiket($tiket, '130-01'));

        $totalDebit = collect($this->barisJurnalTiket($tiket, '120-01'))->sum(fn ($r) => $r[0])
            + collect($this->barisJurnalTiket($tiket, '510-02'))->sum(fn ($r) => $r[0]);
        $totalKredit = 200000 + 100000 + 33000 + 50000;
        $this->assertEqualsWithDelta($totalKredit, $totalDebit, 0.01);
    }

    public function test_goodwill_mengurangi_dpp_sebelum_ppn(): void
    {
        $this->aktifkanPpn(11.0, 'exclusive');
        $pelanggan = $this->buatPelanggan();
        $tiket = $this->buatTiket('qc', 200000, $pelanggan->id);
        $tiket->update([
            'goodwill_amount' => 20000,
            'goodwill_approved_at' => now(),
            'goodwill_approved_by' => $this->user->id,
        ]);

        $this->selesai($tiket);
        $tiket->refresh();

        // Neto 180.000 → PPN 19.800 → tagihan 199.800.
        $this->assertSame([[199800.0, 0.0]], $this->barisJurnalTiket($tiket, '120-01'));
        $this->assertSame([[0.0, 180000.0]], $this->barisJurnalTiket($tiket, '420-01'));
        $this->assertSame([[0.0, 19800.0]], $this->barisJurnalTiket($tiket, '220-01'));
        $this->assertEqualsWithDelta(199800.0, (float) $this->piutangTiket($tiket)->jumlah, 0.01);
    }

    public function test_konfigurasi_pajak_tidak_retroaktif_untuk_tiket_sudah_selesai(): void
    {
        $pelanggan = $this->buatPelanggan();
        $tiket = $this->buatTiket('qc', 200000, $pelanggan->id);
        $this->selesai($tiket);

        // Cabang baru mengaktifkan PPN setelah tiket selesai.
        $this->aktifkanPpn(11.0, 'exclusive');
        $tiket->refresh();

        $this->assertEqualsWithDelta(200000.0, app(ServisService::class)->getTotalTagihanBersih($tiket), 0.01);
        $this->assertSame([], $this->barisJurnalTiket($tiket, '220-01'));

        // Pelunasan tetap sebesar tagihan awal (tanpa PPN surut).
        app(ServisService::class)->bayar($tiket, 'tunai', $this->user, null, null, null, 200000.0);
        $this->assertSame('lunas', $tiket->fresh()->status_pembayaran);
    }

    // =====================================================================
    // B-3 — Pelunasan setelah cicilan via Akunting
    // =====================================================================

    public function test_pelunasan_setelah_cicilan_akunting_hanya_mem_post_sisa(): void
    {
        $pelanggan = $this->buatPelanggan();
        $tiket = $this->buatTiket('qc', 200000, $pelanggan->id);
        $this->selesai($tiket);
        $piutang = $this->piutangTiket($tiket);

        // Cicilan 50% via modul Akunting.
        app(PembayaranSubledgerService::class)->bayarPiutang($piutang->id, 100000, $this->cabang->id, $this->user->id);
        $this->assertSame('sebagian', $piutang->fresh()->status);

        // Pelunasan saat pengambilan: hanya sisa 100.000.
        app(ServisService::class)->bayar($tiket->fresh(), 'tunai', $this->user, null, null, null, 100000.0);

        $piutang->refresh();
        $this->assertSame('lunas', $piutang->status);
        $this->assertEqualsWithDelta(200000.0, (float) $piutang->jumlah_dibayar, 0.01);
        $this->assertSame('lunas', $tiket->fresh()->status_pembayaran);

        // Jurnal pelunasan servis hanya kredit 120-01 sebesar sisa.
        $noJurnalBayar = $tiket->fresh()->no_jurnal_bayar;
        $this->assertNotNull($noJurnalBayar);
        $kreditPiutang = (float) DB::table('jurnal_akuntansi as ja')
            ->join('akun_coa as a', 'a.id', '=', 'ja.akun_coa_id')
            ->where('ja.no_jurnal', $noJurnalBayar)
            ->where('a.kode', '120-01')
            ->sum('ja.kredit');
        $this->assertEqualsWithDelta(100000.0, $kreditPiutang, 0.01);

        // GL piutang harus nol (tidak negatif / tidak dobel).
        $this->assertEqualsWithDelta(0.0, $this->saldoGl('120-01'), 0.01);
    }

    public function test_pelunasan_tanpa_cicilan_tetap_sebesar_tagihan_penuh(): void
    {
        $pelanggan = $this->buatPelanggan();
        $tiket = $this->buatTiket('qc', 200000, $pelanggan->id);
        $this->selesai($tiket);

        app(ServisService::class)->bayar($tiket->fresh(), 'tunai', $this->user, null, null, null, 200000.0);

        $piutang = $this->piutangTiket($tiket)->fresh();
        $this->assertSame('lunas', $piutang->status);
        $this->assertEqualsWithDelta(200000.0, (float) $piutang->jumlah_dibayar, 0.01);
        $this->assertSame('lunas', $tiket->fresh()->status_pembayaran);
        $this->assertEqualsWithDelta(0.0, $this->saldoGl('120-01'), 0.01);
    }

    public function test_pelunasan_setelah_lunas_penuh_via_akunting_idempoten_tanpa_jurnal_ganda(): void
    {
        $pelanggan = $this->buatPelanggan();
        $tiket = $this->buatTiket('qc', 200000, $pelanggan->id);
        $this->selesai($tiket);
        $piutang = $this->piutangTiket($tiket);

        // Dibayar penuh via Akunting → subledger lunas, tiket belum tersinkron.
        app(PembayaranSubledgerService::class)->bayarPiutang($piutang->id, 200000, $this->cabang->id, $this->user->id);
        $this->assertSame('lunas', $piutang->fresh()->status);

        $jumlahJurnalSebelum = DB::table('jurnal_header')->count();
        app(ServisService::class)->bayar($tiket->fresh(), 'tunai', $this->user);

        // Tidak ada jurnal baru (sisa = 0), tiket tersinkron lunas.
        $this->assertSame($jumlahJurnalSebelum, DB::table('jurnal_header')->count());
        $this->assertSame('lunas', $tiket->fresh()->status_pembayaran);
        $this->assertSame('lunas', $piutang->fresh()->status);
        $this->assertEqualsWithDelta(0.0, $this->saldoGl('120-01'), 0.01);

        // Panggilan kedua ditolak (sudah lunas) — tidak ada posting ganda.
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sudah lunas');
        app(ServisService::class)->bayar($tiket->fresh(), 'tunai', $this->user);
    }

    public function test_pelunasan_menolak_nominal_selain_sisa_tagihan(): void
    {
        $pelanggan = $this->buatPelanggan();
        $tiket = $this->buatTiket('qc', 200000, $pelanggan->id);
        $this->selesai($tiket);
        $piutang = $this->piutangTiket($tiket);

        app(PembayaranSubledgerService::class)->bayarPiutang($piutang->id, 100000, $this->cabang->id, $this->user->id);

        try {
            // Kasir mencoba mem-post nilai penuh lagi → harus ditolak.
            app(ServisService::class)->bayar($tiket->fresh(), 'tunai', $this->user, null, null, null, 200000.0);
            $this->fail('Seharusnya menolak nominal selain sisa tagihan');
        } catch (\Exception $e) {
            $this->assertStringContainsString('tidak sesuai', $e->getMessage());
        }

        $this->assertSame('belum_bayar', $tiket->fresh()->status_pembayaran);
        $this->assertSame('sebagian', $piutang->fresh()->status);
        $this->assertEqualsWithDelta(100000.0, $this->saldoGl('120-01'), 0.01);
    }

    public function test_pelunasan_sisa_dengan_ppn_tetap_balance(): void
    {
        $this->aktifkanPpn(11.0, 'exclusive');
        $pelanggan = $this->buatPelanggan();
        $tiket = $this->buatTiket('qc', 200000, $pelanggan->id);
        $this->selesai($tiket);
        $piutang = $this->piutangTiket($tiket); // 222.000

        app(PembayaranSubledgerService::class)->bayarPiutang($piutang->id, 111000, $this->cabang->id, $this->user->id);
        app(ServisService::class)->bayar($tiket->fresh(), 'tunai', $this->user, null, null, null, 111000.0);

        $this->assertSame('lunas', $piutang->fresh()->status);
        $this->assertEqualsWithDelta(222000.0, (float) $piutang->fresh()->jumlah_dibayar, 0.01);
        $this->assertEqualsWithDelta(0.0, $this->saldoGl('120-01'), 0.01);
        // PPN keluaran tetap utuh (diakui saat selesai, bukan saat pelunasan).
        $this->assertEqualsWithDelta(-22000.0, $this->saldoGl('220-01'), 0.01);
    }

    // =====================================================================
    // F-09 — Komisi tiket batal/ditolak setelah selesai
    // =====================================================================

    private function buatKaryawan(): Karyawan
    {
        $u = User::create([
            'name' => 'Teknisi '.uniqid(),
            'email' => 'tek-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        return Karyawan::create([
            'user_id' => $u->id,
            'nik' => 'NIK-'.uniqid(),
            'nama' => 'Teknisi Komisi',
            'jabatan' => 'teknisi',
            'cabang_id' => $this->cabang->id,
            'tgl_masuk' => '2024-01-01',
            'gaji_pokok' => 4000000,
            'status_aktif' => true,
        ]);
    }

    private function buatKomisi(array $attrs): Komisi
    {
        return Komisi::create(array_merge([
            'no_komisi' => 'KMS-TEST-'.uniqid(),
            'jumlah_transaksi' => 200000,
            'nominal_komisi' => 10000,
            'status' => 'pending',
        ], $attrs));
    }

    public function test_batal_setelah_selesai_membatalkan_komisi_pending_dan_disetujui(): void
    {
        $pelanggan = $this->buatPelanggan();
        $karyawan = $this->buatKaryawan();
        $tiket = $this->buatTiket('qc', 200000, $pelanggan->id);

        // Komisi engine baru (terikat tiket) — masuk payroll karyawan.
        $komisiPayroll = $this->buatKomisi([
            'tiket_servis_id' => $tiket->id,
            'aktor_tipe' => 'karyawan',
            'aktor_id' => $karyawan->id,
            'status' => 'pending',
        ]);
        // Komisi legacy reseller (tanpa tiket_servis_id, hanya keterangan).
        $komisiLegacy = $this->buatKomisi([
            'pelanggan_id' => $pelanggan->id,
            'keterangan' => 'Komisi dari servis '.$tiket->no_tiket,
            'status' => 'disetujui',
        ]);
        // Sudah cair → tidak boleh diubah diam-diam (uang sudah keluar).
        $komisiCair = $this->buatKomisi([
            'tiket_servis_id' => $tiket->id,
            'status' => 'cair',
        ]);
        // Komisi tiket lain → tidak tersentuh.
        $komisiLain = $this->buatKomisi([
            'pelanggan_id' => $pelanggan->id,
            'keterangan' => 'Komisi dari servis SRV-LAIN',
            'status' => 'pending',
        ]);

        $this->selesai($tiket);
        app(ServisService::class)->updateStatus($tiket->fresh(), 'batal', $this->user, 'Dibatalkan regulator setelah selesai');

        $this->assertSame('dibatalkan', $komisiPayroll->fresh()->status);
        $this->assertSame('dibatalkan', $komisiLegacy->fresh()->status);
        $this->assertSame('cair', $komisiCair->fresh()->status);
        $this->assertSame('pending', $komisiLain->fresh()->status);

        // Baris legacy kini terikat tiket (audit trail).
        $this->assertSame($tiket->id, $komisiLegacy->fresh()->tiket_servis_id);

        // Tidak lagi masuk basis payroll (pending/disetujui).
        $this->assertSame(0, Komisi::where('aktor_tipe', 'karyawan')
            ->where('aktor_id', $karyawan->id)
            ->whereIn('status', ['pending', 'disetujui'])
            ->count());
    }

    public function test_ditolak_setelah_selesai_membatalkan_komisi(): void
    {
        $pelanggan = $this->buatPelanggan();
        $tiket = $this->buatTiket('qc', 200000, $pelanggan->id);
        $komisi = $this->buatKomisi([
            'tiket_servis_id' => $tiket->id,
            'status' => 'pending',
        ]);

        $this->selesai($tiket);
        app(ServisService::class)->updateStatus($tiket->fresh(), 'ditolak', $this->user, 'Override regulator: ditolak setelah selesai');

        $this->assertSame('dibatalkan', $komisi->fresh()->status);
    }

    public function test_batal_komisi_reseller_legacy_end_to_end(): void
    {
        $reseller = $this->buatPelanggan(true);
        SkemaKomisi::create(['nama' => 'Default Servis', 'kategori' => null, 'tipe' => 'persen', 'nilai' => 5, 'is_active' => true]);

        $tiket = $this->buatTiket('qc', 200000, $reseller->id);
        $this->selesai($tiket);

        $komisi = Komisi::where('pelanggan_id', $reseller->id)->first();
        $this->assertNotNull($komisi, 'Komisi legacy reseller harus terbentuk saat selesai');
        $this->assertSame('pending', $komisi->status);

        app(ServisService::class)->updateStatus($tiket->fresh(), 'batal', $this->user, 'Servis batal setelah selesai');

        $this->assertSame('dibatalkan', $komisi->fresh()->status);
    }

    public function test_batal_sebelum_selesai_tidak_menyentuh_komisi(): void
    {
        $pelanggan = $this->buatPelanggan();
        $tiket = $this->buatTiket('dikerjakan', 200000, $pelanggan->id);
        $komisi = $this->buatKomisi([
            'pelanggan_id' => $pelanggan->id,
            'keterangan' => 'Komisi dari servis '.$tiket->no_tiket,
            'status' => 'pending',
        ]);

        app(ServisService::class)->updateStatus($tiket->fresh(), 'batal', $this->user, 'Batal saat pengerjaan');

        // Belum pernah selesai → tidak ada komisi yang boleh dibatalkan.
        $this->assertSame('pending', $komisi->fresh()->status);
    }
}
