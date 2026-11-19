<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Livewire\LaporanPajak;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fix HIGH audit 4-integration-db: A-1/F-02 (filter jurnal disetujui),
 * A-2 (exclude penutup dari P&L/arus kas), A-3 (trial balance akun nonaktif),
 * A-4 (arus kas lengkap + rekonsiliasi + satu metode UI/API/export),
 * A-6 (invalidasi cache semua laporan saat posting).
 */
class LaporanKeuanganFixTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $user;

    private JurnalService $jurnalService;

    private ExportLaporanService $exportService;

    private string $dari;

    private string $sampai;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::flush();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['kode' => 'LKF-A', 'nama' => 'Cabang Laporan Fix', 'is_active' => true]);
        $this->user = User::create([
            'name' => 'Admin Laporan Fix',
            'email' => 'admin-laporan-fix@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');

        $this->jurnalService = app(JurnalService::class);
        $this->exportService = app(ExportLaporanService::class);
        $this->dari = now()->startOfMonth()->toDateString();
        $this->sampai = now()->toDateString();
    }

    private function komponen(): AkuntingDashboard
    {
        $component = app(AkuntingDashboard::class);
        $component->mount();

        return $component;
    }

    /**
     * Post jurnal double-entry via engine resmi (header + baris).
     *
     * @param  array<int,array{0:string,1:float,2:float}>  $pasangan  [kode, debit, kredit]
     */
    private function postJurnal(array $pasangan, string $sumber = 'manual', string $status = 'disetujui', ?string $tanggal = null): string
    {
        $no = $this->jurnalService->generateNoJurnal($sumber === 'manual' ? 'manual' : 'manual', $this->cabang->id);
        $lines = array_map(fn (array $p): array => [
            'akun_kode' => $p[0], 'debit' => $p[1], 'kredit' => $p[2],
        ], $pasangan);
        $this->jurnalService->post(
            noJurnal: $no,
            tanggal: $tanggal ?? $this->sampai,
            sumber: $sumber,
            lines: $lines,
            deskripsi: 'Fixture LaporanKeuanganFix '.$no,
            cabangId: $this->cabang->id,
            userId: $this->user->id,
            statusApproval: $status,
        );

        return $no;
    }

    private function exportCsv(string $jenis, ?string $dari = null, ?string $sampai = null): string
    {
        $path = $this->exportService->export(
            $jenis, $dari ?? $this->dari, $sampai ?? $this->sampai,
            $this->cabang->id, null, 'csv', $this->user->id
        );

        return Storage::disk('local')->get($path);
    }

    // =====================================================================
    // A-1 / F-02 — hanya jurnal status_approval=disetujui yang masuk laporan
    // =====================================================================

    public function test_jurnal_pending_dan_ditolak_tidak_masuk_laba_rugi_ui_api_export(): void
    {
        $this->postJurnal([['110-01', 100000, 0], ['410-01', 0, 100000]]);
        $this->postJurnal([['110-01', 70000, 0], ['410-01', 0, 70000]], status: 'pending');
        $this->postJurnal([['110-01', 30000, 0], ['410-01', 0, 30000]], status: 'ditolak');

        // UI dashboard
        $lr = $this->komponen()->getLabaRugiProperty();
        $this->assertSame(100000.0, (float) $lr['total_pendapatan'], 'Laba rugi UI hanya boleh memuat jurnal disetujui');
        $this->assertSame(100000.0, (float) $lr['laba_bersih']);

        // API ACC-05
        $api = $this->getJson('/api/akunting/laporan/laba-rugi?dari='.$this->dari.'&sampai='.$this->sampai);
        $api->assertSuccessful();
        $this->assertSame(100000.0, (float) $api->json('data.total_pendapatan'), 'Laba rugi API hanya boleh memuat jurnal disetujui');

        // Export Excel/CSV (fputcsv mengutip label berspasial).
        $csv = $this->exportCsv('laba_rugi');
        $this->assertStringContainsString('"TOTAL PENDAPATAN",100000', $csv);
        $this->assertStringNotContainsString('200000', $csv, 'Jurnal pending+ditolak tidak boleh ikut terexport');
    }

    public function test_jurnal_pending_dan_ditolak_tidak_masuk_neraca_trial_balance_buku_besar_arus_kas(): void
    {
        $this->postJurnal([['110-01', 100000, 0], ['410-01', 0, 100000]]);
        $noPending = $this->postJurnal([['110-01', 70000, 0], ['410-01', 0, 70000]], status: 'pending');
        $this->postJurnal([['110-01', 30000, 0], ['410-01', 0, 30000]], status: 'ditolak');

        // Neraca (UI widget + API + export satu sumber)
        $neraca = $this->exportService->neracaSaldo($this->cabang->id, $this->sampai);
        $this->assertSame(100000.0, (float) $neraca['total_aset'], 'Neraca hanya boleh memuat jurnal disetujui');
        $this->assertTrue($neraca['balance']);
        $apiNeraca = $this->getJson('/api/akunting/laporan/neraca?sampai='.$this->sampai);
        $apiNeraca->assertSuccessful();
        $this->assertSame(100000.0, (float) $apiNeraca->json('data.total_aset'));

        // Trial balance 4 kolom
        $tb = $this->exportService->neracaSaldo4Kolom($this->cabang->id, $this->dari, $this->sampai);
        $this->assertSame(100000.0, (float) $tb['total']['saldo_akhir_debit']);
        $this->assertTrue($tb['is_balanced']);
        $apiTb = $this->getJson('/api/akunting/laporan/neraca-saldo?dari='.$this->dari.'&sampai='.$this->sampai);
        $apiTb->assertSuccessful();
        $this->assertSame(100000.0, (float) $apiTb->json('data.total.saldo_akhir_debit'));

        // Buku besar UI (akun Kas)
        $kas = AkunCOA::where('kode', '110-01')->firstOrFail();
        $component = $this->komponen();
        $component->selectedBukuBesarAkunId = $kas->id;
        $bb = $component->getBukuBesarDataProperty();
        $this->assertSame(100000.0, (float) $bb['total_debit'], 'Buku besar UI hanya boleh memuat jurnal disetujui');
        $this->assertCount(1, $bb['mutasi']);
        $this->assertNotContains($noPending, collect($bb['mutasi'])->pluck('no_jurnal')->all());

        // Buku besar API
        $apiBb = $this->getJson('/api/akunting/buku-besar/'.$kas->id.'?dari='.$this->dari.'&sampai='.$this->sampai);
        $apiBb->assertSuccessful();
        $this->assertSame(100000.0, (float) $apiBb->json('data.saldo_akhir'));
        $this->assertCount(1, $apiBb->json('data.entries'));

        // Arus kas
        $ak = $this->komponen()->getArusKasProperty();
        $this->assertSame(100000.0, (float) $ak['kenaikan_kas_neto'], 'Arus kas hanya boleh memuat jurnal disetujui');

        // Export buku besar & neraca saldo
        $csvBb = $this->exportCsv('buku_besar');
        $this->assertStringNotContainsString($noPending, $csvBb, 'Export buku besar tidak boleh memuat jurnal pending');
    }

    public function test_jurnal_disetujui_dan_baris_legacy_tanpa_header_tetap_dihitung(): void
    {
        $this->postJurnal([['110-01', 100000, 0], ['410-01', 0, 100000]]);

        // Baris legacy tanpa header (pra-migrasi header) tidak boleh hilang dari laporan.
        $kas = AkunCOA::where('kode', '110-01')->firstOrFail();
        $pendapatan = AkunCOA::where('kode', '410-01')->firstOrFail();
        foreach ([[$kas->id, 5000, 0], [$pendapatan->id, 0, 5000]] as [$akunId, $deb, $kre]) {
            JurnalAkuntansi::create([
                'no_jurnal' => 'JRL-LEGACY-1',
                'tanggal' => $this->sampai,
                'cabang_id' => $this->cabang->id,
                'akun_coa_id' => $akunId,
                'sumber' => 'manual',
                'deskripsi' => 'Legacy tanpa header',
                'debit' => $deb,
                'kredit' => $kre,
                'user_id' => $this->user->id,
            ]);
        }

        $lr = $this->komponen()->getLabaRugiProperty();
        $this->assertSame(105000.0, (float) $lr['total_pendapatan'], 'Jurnal disetujui + baris legacy tetap dihitung');
        $neraca = $this->exportService->neracaSaldo($this->cabang->id, $this->sampai);
        $this->assertSame(105000.0, (float) $neraca['total_aset']);
    }

    public function test_jurnal_pending_yang_disetujui_approver_langsung_masuk_laporan(): void
    {
        $no = $this->postJurnal([['110-01', 70000, 0], ['410-01', 0, 70000]], status: 'pending');

        $lr = $this->komponen()->getLabaRugiProperty();
        $this->assertSame(0.0, (float) $lr['total_pendapatan'], 'Pending belum boleh diakui');

        // Approve via jalur ApprovalService (update header saja).
        $header = JurnalHeader::where('no_jurnal', $no)->where('cabang_key', $this->cabang->id)->firstOrFail();
        $header->update(['status_approval' => 'disetujui', 'approved_by_user_id' => $this->user->id, 'approved_at' => now()]);

        $lr = $this->komponen()->getLabaRugiProperty();
        $this->assertSame(70000.0, (float) $lr['total_pendapatan'], 'Setelah disetujui, jurnal harus langsung masuk laporan (cache terinvalidasi)');
    }

    // =====================================================================
    // A-2 — jurnal penutup (tutup_buku) tidak boleh meng-offset P&L periode
    // =====================================================================

    public function test_jurnal_penutup_tidak_mengurangi_laba_rugi_dan_arus_kas_periode(): void
    {
        $this->postJurnal([['110-01', 100000, 0], ['410-01', 0, 100000]]);
        $this->postJurnal([['520-03', 40000, 0], ['110-01', 0, 40000]]);
        // Ayat penutup: tutup pendapatan & beban ke laba ditahan.
        $this->postJurnal([['410-01', 100000, 0], ['520-03', 0, 40000], ['310-02', 0, 60000]], sumber: 'tutup_buku');

        // Laba rugi periode tetap menampilkan angka sebelum penutup.
        $lr = $this->komponen()->getLabaRugiProperty();
        $this->assertSame(100000.0, (float) $lr['total_pendapatan'], 'Penutup tidak boleh meng-offset pendapatan periode');
        $this->assertSame(40000.0, (float) $lr['total_beban'], 'Penutup tidak boleh meng-offset beban periode');
        $this->assertSame(60000.0, (float) $lr['laba_bersih']);

        $api = $this->getJson('/api/akunting/laporan/laba-rugi?dari='.$this->dari.'&sampai='.$this->sampai);
        $api->assertSuccessful();
        $this->assertSame(60000.0, (float) $api->json('data.laba_bersih'));

        $csv = $this->exportCsv('laba_rugi');
        $this->assertStringContainsString('"LABA BERSIH",60000', $csv);

        // Arus kas basis laba bersih ikut benar.
        $ak = $this->komponen()->getArusKasProperty();
        $this->assertSame(60000.0, (float) $ak['laba_bersih']);
        $this->assertSame(60000.0, (float) $ak['kenaikan_kas_neto']);

        // Neraca & trial balance WAJIB tetap memuat penutup (transfer saldo).
        $neraca = $this->exportService->neracaSaldo($this->cabang->id, $this->sampai);
        $this->assertTrue($neraca['balance']);
        $this->assertSame(0.0, (float) $neraca['laba_periode_berjalan'], 'Setelah penutup, akun nominal nol');
        $labaDitahan = collect($neraca['ekuitas'])->firstWhere('kode', '310-02');
        $this->assertSame(60000.0, (float) $labaDitahan['saldo']);

        $tb = $this->exportService->neracaSaldo4Kolom($this->cabang->id, $this->dari, $this->sampai);
        $this->assertTrue($tb['is_balanced']);
        $this->assertContains('310-02', collect($tb['akun'])->pluck('kode')->all(), 'Trial balance harus memuat ayat penutup');
    }

    // =====================================================================
    // A-3 — trial balance wajib memuat akun nonaktif yang bersaldo
    // =====================================================================

    public function test_trial_balance_memuat_akun_nonaktif_bersaldo_dan_tetap_balance(): void
    {
        $this->postJurnal([['110-01', 80000, 0], ['410-01', 0, 80000]]);

        // hapusCoa menonaktifkan akun ber-riwayat (bukan delete).
        AkunCOA::where('kode', '410-01')->update(['is_active' => false]);

        $tb = $this->exportService->neracaSaldo4Kolom($this->cabang->id, $this->dari, $this->sampai);
        $this->assertContains('410-01', collect($tb['akun'])->pluck('kode')->all(), 'Akun nonaktif bersaldo wajib tampil di trial balance');
        $this->assertTrue($tb['is_balanced'], 'Trial balance harus tetap balance meski akun dinonaktifkan');
        $this->assertSame(80000.0, (float) $tb['total']['saldo_akhir_debit']);
        $this->assertSame(80000.0, (float) $tb['total']['saldo_akhir_kredit']);

        // Konsisten dengan neraca (yang memakai semua akun).
        $neraca = $this->exportService->neracaSaldo($this->cabang->id, $this->sampai);
        $this->assertSame($neraca['total_aset'], $tb['total']['saldo_akhir_debit'], 'Trial balance harus sama dengan neraca');
    }

    // =====================================================================
    // A-4 — arus kas tidak langsung lengkap, rekonsiliasi ke kas riil
    // =====================================================================

    /** Fixture: penjualan tunai + channel, depresiasi, akrual gaji, beli kredit. */
    private function seedSkenarioArusKas(): void
    {
        $this->postJurnal([['110-01', 100000, 0], ['410-01', 0, 100000]]);   // jual tunai
        $this->postJurnal([['120-03', 50000, 0], ['410-01', 0, 50000]]);     // jual channel Shopee
        $this->postJurnal([['530-01', 10000, 0], ['130-02', 0, 10000]], sumber: 'depresiasi'); // depresiasi
        $this->postJurnal([['520-01', 20000, 0], ['210-02', 0, 20000]]);     // akrual gaji
        $this->postJurnal([['130-01', 30000, 0], ['210-01', 0, 30000]]);     // beli persediaan kredit
    }

    public function test_arus_kas_operasi_addback_depresiasi_delta_piutang_channel_dan_utang_gaji(): void
    {
        $this->seedSkenarioArusKas();

        $ak = $this->komponen()->getArusKasProperty();

        // NI = 150.000 - 10.000 (depresiasi) - 20.000 (gaji) = 120.000
        $this->assertSame(120000.0, (float) $ak['laba_bersih']);
        // Add-back non-kas + delta kelompok (piutang channel 120-03 & utang gaji 210-02 ikut).
        $this->assertSame(10000.0, (float) $ak['penyesuaian']['beban_depresiasi'], 'Depresiasi wajib di-add-back');
        $this->assertSame(-50000.0, (float) $ak['penyesuaian']['kenaikan_piutang'], 'Delta piutang harus mencakup akun channel (120-02..04)');
        $this->assertSame(-30000.0, (float) $ak['penyesuaian']['kenaikan_persediaan']);
        $this->assertSame(50000.0, (float) $ak['penyesuaian']['kenaikan_utang'], 'Delta utang harus mencakup utang gaji (210-02)');
        // CFO = 120.000 + 10.000 - 50.000 - 30.000 + 50.000 = 100.000 = kas riil masuk.
        $this->assertSame(100000.0, (float) $ak['arus_kas_operasi']);
        $this->assertSame(100000.0, (float) $ak['kenaikan_kas_neto']);

        // Rekonsiliasi ke saldo kas/bank riil (PSAK 2).
        $this->assertSame(0.0, (float) $ak['rekonsiliasi']['saldo_awal_kas']);
        $this->assertSame(100000.0, (float) $ak['rekonsiliasi']['perubahan_kas_aktual']);
        $this->assertSame(100000.0, (float) $ak['rekonsiliasi']['saldo_akhir_kas']);
        $this->assertSame(0.0, (float) $ak['rekonsiliasi']['selisih'], 'Kenaikan kas neto harus rekonsiliasi ke mutasi kas/bank');
    }

    public function test_arus_kas_rekonsiliasi_tetap_cocok_dengan_akun_pajak_dan_saldo_awal(): void
    {
        // Saldo awal kas bulan lalu (setoran modal).
        $this->postJurnal(
            [['110-01', 20000, 0], ['310-01', 0, 20000]],
            tanggal: now()->subMonth()->startOfMonth()->addDays(5)->toDateString()
        );
        // PPN keluaran diterima tunai — akun pajak (di luar bucket lama) harus ikut direkonsiliasi.
        $this->postJurnal([['110-01', 5000, 0], ['220-01', 0, 5000]]);

        $ak = $this->komponen()->getArusKasProperty();
        $this->assertSame(5000.0, (float) $ak['kenaikan_kas_neto']);
        $this->assertSame(20000.0, (float) $ak['rekonsiliasi']['saldo_awal_kas']);
        $this->assertSame(5000.0, (float) $ak['rekonsiliasi']['perubahan_kas_aktual']);
        $this->assertSame(25000.0, (float) $ak['rekonsiliasi']['saldo_akhir_kas']);
        $this->assertSame(0.0, (float) $ak['rekonsiliasi']['selisih']);
    }

    public function test_arus_kas_ui_api_export_satu_metode_dan_angka_identik(): void
    {
        $this->seedSkenarioArusKas();

        $ui = $this->komponen()->getArusKasProperty();

        $api = $this->getJson('/api/akunting/laporan/arus-kas?dari='.$this->dari.'&sampai='.$this->sampai);
        $api->assertSuccessful();
        foreach (['laba_bersih', 'arus_kas_operasi', 'arus_kas_investasi', 'arus_kas_pendanaan', 'kenaikan_kas_neto'] as $key) {
            $this->assertSame((float) $ui[$key], (float) $api->json('data.'.$key), "API arus kas key {$key} harus identik dengan UI");
        }
        $this->assertSame((float) $ui['penyesuaian']['beban_depresiasi'], (float) $api->json('data.penyesuaian.beban_depresiasi'));
        $this->assertSame(0.0, (float) $api->json('data.rekonsiliasi.selisih'));

        // Export harus metode & angka yang sama (bukan metode langsung per hari).
        $csv = $this->exportCsv('arus_kas');
        $this->assertStringContainsString('TIDAK LANGSUNG', $csv, 'Export arus kas harus berlabel metode tidak langsung');
        $this->assertStringContainsString('"ARUS KAS OPERASI",100000', $csv);
        $this->assertStringContainsString('"KENAIKAN KAS NETO",100000', $csv);
        $this->assertStringContainsString('REKONSILIASI', $csv);
    }

    // =====================================================================
    // A-6 — invalidasi cache SEMUA laporan saat ada posting/approval
    // =====================================================================

    public function test_posting_baru_menginvalidasi_cache_laba_rugi_arus_kas_neraca_trial_balance_dan_pajak(): void
    {
        // Prime semua cache laporan.
        $component = $this->komponen();
        $this->assertSame(0.0, (float) $component->getLabaRugiProperty()['total_pendapatan']);
        $this->assertSame(0.0, (float) $component->getArusKasProperty()['kenaikan_kas_neto']);
        $this->assertSame(0.0, (float) $component->getNeracaProperty()['total_aset']);
        $this->getJson('/api/akunting/laporan/neraca-saldo?dari='.$this->dari.'&sampai='.$this->sampai)->assertSuccessful();
        $pajak = app(LaporanPajak::class);
        $pajak->mount();
        $this->assertSame(0.0, (float) $pajak->getRekapProperty()['masukan']);

        // Posting baru (jalur engine — mewakili semua modul: POS/servis/manual/dll).
        $this->postJurnal([['110-01', 12345, 0], ['410-01', 0, 12345]]);
        $this->postJurnal([['110-03', 1000, 0], ['220-01', 0, 1000]]);

        // Semua laporan harus langsung segar tanpa menunggu TTL 15 menit.
        $this->assertSame(12345.0, (float) $component->getLabaRugiProperty()['total_pendapatan'], 'Cache laba rugi basi setelah posting');
        $this->assertSame(12345.0, (float) $component->getArusKasProperty()['kenaikan_kas_neto'], 'Cache arus kas basi setelah posting');
        $this->assertSame(13345.0, (float) $component->getNeracaProperty()['total_aset'], 'Cache neraca basi setelah posting');
        $apiTb = $this->getJson('/api/akunting/laporan/neraca-saldo?dari='.$this->dari.'&sampai='.$this->sampai);
        $this->assertSame(13345.0, (float) $apiTb->json('data.total.saldo_akhir_debit'), 'Cache trial balance API basi setelah posting');
        $this->assertSame(1000.0, (float) $pajak->getRekapProperty()['masukan'], 'Cache laporan pajak basi setelah posting');
    }

    public function test_cache_periode_lain_ikut_terinvalidasi_saat_posting_backdate(): void
    {
        $bulanLaluDari = now()->subMonth()->startOfMonth()->toDateString();
        $bulanLaluSampai = now()->subMonth()->endOfMonth()->toDateString();

        $component = $this->komponen();
        $component->periodeDari = $bulanLaluDari;
        $component->periodeSampai = $bulanLaluSampai;
        $this->assertSame(0.0, (float) $component->getLabaRugiProperty()['total_pendapatan']);

        // Posting backdate ke bulan lalu (periode yang sedang dibuka user lain).
        $this->postJurnal(
            [['110-01', 9000, 0], ['410-01', 0, 9000]],
            tanggal: now()->subMonth()->startOfMonth()->addDays(10)->toDateString()
        );

        $this->assertSame(9000.0, (float) $component->getLabaRugiProperty()['total_pendapatan'], 'Cache periode lain tidak boleh basi setelah posting backdate');
    }

    public function test_bersihkan_cache_laporan_menaikkan_versi_cache_cabang_dan_konsolidasi(): void
    {
        $versiCabang = ExportLaporanService::versiCacheLaporan($this->cabang->id);
        $versiKonsolidasi = ExportLaporanService::versiCacheLaporan(null);

        $this->komponen()->bersihkanCacheLaporan();

        $this->assertGreaterThan($versiCabang, ExportLaporanService::versiCacheLaporan($this->cabang->id));
        $this->assertGreaterThan($versiKonsolidasi, ExportLaporanService::versiCacheLaporan(null));
    }
}
