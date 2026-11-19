<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\ValidasiBarisJurnal;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F-06 / F-07 / F-08 — Klasifikasi akun kontra & validasi CRUD COA.
 *
 * F-06: 410-02 Retur Penjualan = kontra-pendapatan → saldo normal DEBIT
 *       (PSAK 72/IFRS 15). Deklarasi lama 'kredit' adalah hack netting.
 * F-07: 130-02 Akumulasi Depresiasi = kontra-aset → saldo normal KREDIT
 *       (PSAK 16/IAS 16). Deklarasi lama 'debit' adalah hack netting.
 * F-08: CRUD COA wajib memvalidasi format kode (^[1-5]xx-xx) dan
 *       konsistensi tipe ↔ saldo_normal; akun kontra boleh menyimpang
 *       HANYA bila ditandai eksplisit (is_kontra).
 *
 * KONTRAK KESELAMATAN: laporan (neraca, laba rugi, trial balance, arus kas)
 * wajib TETAP BALANCE dan angkanya IDENTIK sebelum & sesudah koreksi
 * deklarasi — netting kontra di ExportLaporanService sadar-flag dan
 * invarian terhadap deklarasi saldo_normal.
 */
class AkunCoaKlasifikasiKontraTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $user;

    private JurnalService $jurnal;

    private ExportLaporanService $export;

    private string $dari;

    private string $sampai;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['kode' => 'KON-A', 'nama' => 'Cabang Kontra A', 'is_active' => true]);

        $this->user = User::create([
            'name' => 'Admin Kontra',
            'email' => 'admin-kontra@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach($this->cabang->id);
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');

        $this->jurnal = app(JurnalService::class);
        $this->export = app(ExportLaporanService::class);
        $this->dari = now()->startOfMonth()->toDateString();
        $this->sampai = now()->toDateString();
    }

    // =====================================================================
    // Fixture
    // =====================================================================

    private function postJurnal(array $pasangan, string $sumber = 'manual'): void
    {
        $lines = array_map(fn (array $p): array => [
            'akun_kode' => $p[0], 'debit' => $p[1], 'kredit' => $p[2],
        ], $pasangan);

        $this->jurnal->post(
            noJurnal: $this->jurnal->generateNoJurnal('manual', $this->cabang->id),
            tanggal: $this->sampai,
            sumber: $sumber,
            lines: $lines,
            deskripsi: 'Fixture AkunCoaKlasifikasiKontra',
            cabangId: $this->cabang->id,
            userId: $this->user->id,
        );
    }

    /**
     * Skenario: modal 1jt, jual tunai 500rb, retur 50rb (D 410-02),
     * beli aset tetap 200rb, depresiasi 10rb (K 130-02).
     * Kas akhir 1.250.000; laba 440.000; total aset neto 1.440.000.
     */
    private function seedSkenarioKontra(): void
    {
        $this->postJurnal([['110-01', 1000000, 0], ['310-01', 0, 1000000]]);            // setoran modal
        $this->postJurnal([['110-01', 500000, 0], ['410-01', 0, 500000]]);             // penjualan tunai
        $this->postJurnal([['410-02', 50000, 0], ['110-01', 0, 50000]], 'pos');        // retur penjualan
        $this->postJurnal([['160-01', 200000, 0], ['110-01', 0, 200000]]);             // beli aset tetap
        $this->postJurnal([['530-01', 10000, 0], ['130-02', 0, 10000]], 'depresiasi'); // depresiasi bulanan
    }

    /** Deklarasi PSAK yang benar (target koreksi F-06/F-07). */
    private function terapkanDeklarasiPsak(): void
    {
        DB::table('akun_coa')->where('kode', '410-02')->update(['saldo_normal' => 'debit', 'is_kontra' => true]);
        DB::table('akun_coa')->where('kode', '130-02')->update(['saldo_normal' => 'kredit', 'is_kontra' => true]);
    }

    /** Deklarasi hack netting lama (sebelum koreksi). */
    private function terapkanDeklarasiHackLama(): void
    {
        DB::table('akun_coa')->where('kode', '410-02')->update(['saldo_normal' => 'kredit', 'is_kontra' => false]);
        DB::table('akun_coa')->where('kode', '130-02')->update(['saldo_normal' => 'debit', 'is_kontra' => false]);
    }

    private function assertDeklarasiPsakAktif(): void
    {
        $this->assertTrue(Schema::hasColumn('akun_coa', 'is_kontra'), 'Kolom is_kontra harus ada (migration koreksi F-06/F-07/F-08)');
        $this->assertSame('debit', AkunCOA::where('kode', '410-02')->value('saldo_normal'), '410-02 kontra-pendapatan wajib saldo normal debit');
        $this->assertSame('kredit', AkunCOA::where('kode', '130-02')->value('saldo_normal'), '130-02 kontra-aset wajib saldo normal kredit');
        $this->assertTrue((bool) AkunCOA::where('kode', '410-02')->value('is_kontra'), '410-02 wajib ditandai is_kontra');
        $this->assertTrue((bool) AkunCOA::where('kode', '130-02')->value('is_kontra'), '130-02 wajib ditandai is_kontra');
    }

    /**
     * Snapshot angka kunci semua laporan. Kolom debit/kredit trial balance
     * dipakai (bukan field bertanda saldo_awal/saldo_akhir yang mengikuti
     * deklarasi) — kolom D/K adalah kontrak balance yang invarian.
     */
    private function snapshotLaporan(): array
    {
        $neraca = $this->export->neracaSaldo($this->cabang->id, $this->sampai);
        $tb = $this->export->neracaSaldo4Kolom($this->cabang->id, $this->dari, $this->sampai);
        $ak = $this->export->arusKasTidakLangsung($this->cabang->id, $this->dari, $this->sampai);

        $csvLr = Storage::disk('local')->get(
            $this->export->export('laba_rugi', $this->dari, $this->sampai, $this->cabang->id, null, 'csv', $this->user->id)
        );

        $tbKontra = collect($tb['akun'])->whereIn('kode', ['410-02', '130-02'])->map(fn (array $a): array => [
            'kode' => $a['kode'],
            'saldo_awal_debit' => $a['saldo_awal_debit'],
            'saldo_awal_kredit' => $a['saldo_awal_kredit'],
            'mutasi_debit' => $a['mutasi_debit'],
            'mutasi_kredit' => $a['mutasi_kredit'],
            'saldo_akhir_debit' => $a['saldo_akhir_debit'],
            'saldo_akhir_kredit' => $a['saldo_akhir_kredit'],
        ])->values()->all();

        return [
            'neraca_balance' => $neraca['balance'],
            'neraca_selisih' => $neraca['selisih'],
            'neraca_total_aset' => $neraca['total_aset'],
            'neraca_total_kewajiban' => $neraca['total_kewajiban'],
            'neraca_total_ekuitas_bersama_laba' => $neraca['total_ekuitas_bersama_laba'],
            'neraca_laba_periode_berjalan' => $neraca['laba_periode_berjalan'],
            'neraca_saldo_retur' => collect($neraca['pendapatan'])->firstWhere('kode', '410-02')['saldo'] ?? null,
            'neraca_saldo_akum' => collect($neraca['aset'])->firstWhere('kode', '130-02')['saldo'] ?? null,
            'tb_balanced' => $tb['is_balanced'],
            'tb_total' => $tb['total'],
            'tb_kontra' => $tbKontra,
            'ak_cocok' => $ak['rekonsiliasi']['cocok'],
            'ak_selisih' => $ak['rekonsiliasi']['selisih'],
            'ak_laba_bersih' => $ak['laba_bersih'],
            'csv_laba_rugi' => $csvLr,
        ];
    }

    private function exportCsv(string $jenis): string
    {
        return Storage::disk('local')->get(
            $this->export->export($jenis, $this->dari, $this->sampai, $this->cabang->id, null, 'csv', $this->user->id)
        );
    }

    // =====================================================================
    // F-06 / F-07 — deklarasi & penyajian kontra
    // =====================================================================

    public function test_migrasi_mengoreksi_deklarasi_kontra_retur_dan_akumulasi_depresiasi(): void
    {
        $this->assertDeklarasiPsakAktif();

        // Tipe tidak berubah — reklasifikasi hanya saldo_normal + flag kontra.
        $this->assertSame('pendapatan', AkunCOA::where('kode', '410-02')->value('tipe'));
        $this->assertSame('aset', AkunCOA::where('kode', '130-02')->value('tipe'));
    }

    public function test_laporan_balance_dan_kontra_tersaji_benar_dengan_deklarasi_psak(): void
    {
        $this->assertDeklarasiPsakAktif();
        $this->seedSkenarioKontra();

        // Neraca: aset neto setelah akumulasi depresiasi; ekuitas + laba berjalan.
        $neraca = $this->export->neracaSaldo($this->cabang->id, $this->sampai);
        $this->assertTrue($neraca['balance'], 'Neraca wajib balance dengan deklarasi PSAK');
        $this->assertSame(0.0, (float) $neraca['selisih']);
        $this->assertSame(1440000.0, (float) $neraca['total_aset']);
        $this->assertSame(1440000.0, (float) $neraca['total_ekuitas_bersama_laba']);
        $this->assertSame(440000.0, (float) $neraca['laba_periode_berjalan']);

        // Kontra tersaji sebagai pengurang kelompok tipenya.
        $this->assertSame(-50000.0, (float) collect($neraca['pendapatan'])->firstWhere('kode', '410-02')['saldo']);
        $this->assertSame(-10000.0, (float) collect($neraca['aset'])->firstWhere('kode', '130-02')['saldo']);

        // Laba rugi (export = groupAkun): retur mengurangi pendapatan.
        $csv = $this->exportCsv('laba_rugi');
        $this->assertStringContainsString('"Retur Penjualan",-50000', $csv);
        $this->assertStringContainsString('"TOTAL PENDAPATAN",450000', $csv);
        $this->assertStringContainsString('"LABA BERSIH",440000', $csv);

        // Trial balance: retur di kolom DEBIT, akumulasi di kolom KREDIT.
        $tb = $this->export->neracaSaldo4Kolom($this->cabang->id, $this->dari, $this->sampai);
        $this->assertTrue($tb['is_balanced'], 'Trial balance wajib balance');
        $retur = collect($tb['akun'])->firstWhere('kode', '410-02');
        $this->assertSame(50000.0, (float) $retur['saldo_akhir_debit']);
        $this->assertSame(0.0, (float) $retur['saldo_akhir_kredit']);
        $akum = collect($tb['akun'])->firstWhere('kode', '130-02');
        $this->assertSame(10000.0, (float) $akum['saldo_akhir_kredit']);
        $this->assertSame(0.0, (float) $akum['saldo_akhir_debit']);
        $this->assertSame(1510000.0, (float) $tb['total']['saldo_akhir_debit']);
        $this->assertSame(1510000.0, (float) $tb['total']['saldo_akhir_kredit']);

        // Arus kas: depresiasi add-back, rekonsiliasi ke kas riil tetap cocok.
        $ak = $this->export->arusKasTidakLangsung($this->cabang->id, $this->dari, $this->sampai);
        $this->assertSame(440000.0, (float) $ak['laba_bersih']);
        $this->assertTrue($ak['rekonsiliasi']['cocok']);
        $this->assertSame(0.0, (float) $ak['rekonsiliasi']['selisih']);
        $this->assertSame(1250000.0, (float) $ak['rekonsiliasi']['saldo_akhir_kas']);
    }

    public function test_angka_laporan_identik_sebelum_dan_sesudah_koreksi_deklarasi(): void
    {
        $this->assertDeklarasiPsakAktif();
        $this->seedSkenarioKontra();

        // Sesudah koreksi (deklarasi PSAK + flag kontra).
        $sesudah = $this->snapshotLaporan();
        $this->assertTrue($sesudah['neraca_balance']);
        $this->assertTrue($sesudah['tb_balanced']);

        // Sebelum koreksi (hack netting lama: 410-02 kredit, 130-02 debit, tanpa flag).
        $this->terapkanDeklarasiHackLama();
        $sebelum = $this->snapshotLaporan();
        $this->assertTrue($sebelum['neraca_balance'], 'Neraca wajib balance pada deklarasi lama');
        $this->assertTrue($sebelum['tb_balanced'], 'Trial balance wajib balance pada deklarasi lama');
        $this->assertSame($sesudah, $sebelum, 'Angka laporan wajib identik sebelum & sesudah koreksi deklarasi');

        // Kembali ke deklarasi PSAK — tetap identik.
        $this->terapkanDeklarasiPsak();
        $this->assertSame($sesudah, $this->snapshotLaporan());
    }

    public function test_validasi_sisi_manual_menerima_debit_retur_dan_kredit_akumulasi(): void
    {
        // F-06: baris jurnal manual D Retur Penjualan (benar secara PSAK)
        // tidak boleh ditolak aturan sisi. Cek per baris (validator sisi
        // bekerja per baris, bukan per pasangan jurnal).
        $galat = ValidasiBarisJurnal::cekBarisPerBaris([
            ['akun_kode' => '410-02', 'debit' => 50000, 'kredit' => 0],
        ]);
        $this->assertSame([], $galat[0], 'Debit ke 410-02 (kontra-pendapatan) wajib diterima');

        // F-07: baris jurnal manual K Akumulasi Depresiasi (koreksi penyusutan) diterima.
        $galatDep = ValidasiBarisJurnal::cekBarisPerBaris([
            ['akun_kode' => '130-02', 'debit' => 0, 'kredit' => 10000],
        ]);
        $this->assertSame([], $galatDep[0], 'Kredit ke 130-02 (kontra-aset) wajib diterima');

        // Sisi terbalik tetap ditolak tanpa flag penyesuaian (konsistensi aturan sisi).
        $galatBalik = ValidasiBarisJurnal::cekBarisPerBaris([
            ['akun_kode' => '410-02', 'debit' => 0, 'kredit' => 50000],
        ]);
        $this->assertNotEmpty($galatBalik[0], 'Kredit ke 410-02 tanpa penyesuaian wajib ditolak');

        // Dengan flag penyesuaian, sisi terbalik boleh (jalur koreksi terkendali).
        $galatPenyesuaian = ValidasiBarisJurnal::cekBarisPerBaris([
            ['akun_kode' => '410-02', 'debit' => 0, 'kredit' => 50000],
        ], true);
        $this->assertSame([], $galatPenyesuaian[0]);
    }

    // =====================================================================
    // F-08 — validasi format kode & konsistensi tipe ↔ saldo_normal (API)
    // =====================================================================

    public function test_api_store_coa_menolak_kode_di_luar_skema(): void
    {
        $response = $this->postJson('/api/akunting/coa', [
            'kode' => '907024506', 'nama' => 'KAS BCA', 'tipe' => 'beban',
            'kelompok' => 'STOK', 'saldo_normal' => 'debit',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('akun_coa', ['kode' => '907024506']);
    }

    public function test_api_store_coa_menolak_tipe_saldo_normal_tidak_konsisten(): void
    {
        $response = $this->postJson('/api/akunting/coa', [
            'kode' => '520-99', 'nama' => 'Beban Uji', 'tipe' => 'beban',
            'kelompok' => 'beban_operasional', 'saldo_normal' => 'kredit',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertDatabaseMissing('akun_coa', ['kode' => '520-99']);
    }

    public function test_api_store_coa_kontra_eksplisit_diterima(): void
    {
        $response = $this->postJson('/api/akunting/coa', [
            'kode' => '410-90', 'nama' => 'Diskon Penjualan', 'tipe' => 'pendapatan',
            'kelompok' => 'retur_penjualan', 'saldo_normal' => 'debit', 'is_kontra' => true,
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $akun = AkunCOA::where('kode', '410-90')->firstOrFail();
        $this->assertTrue((bool) $akun->is_kontra);
        $this->assertSame('debit', $akun->saldo_normal);
    }

    public function test_api_store_coa_kontra_tanpa_flag_ditolak(): void
    {
        // saldo_normal menyimpang dari tipe TANPA is_kontra = hack netting liar.
        $response = $this->postJson('/api/akunting/coa', [
            'kode' => '410-91', 'nama' => 'Retur Liar', 'tipe' => 'pendapatan',
            'kelompok' => 'retur_penjualan', 'saldo_normal' => 'debit',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertDatabaseMissing('akun_coa', ['kode' => '410-91']);
    }

    public function test_api_store_coa_standar_tetap_diterima(): void
    {
        $response = $this->postJson('/api/akunting/coa', [
            'kode' => '110-90', 'nama' => 'Bank Uji', 'tipe' => 'aset',
            'kelompok' => 'bank', 'saldo_normal' => 'debit',
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $this->assertDatabaseHas('akun_coa', ['kode' => '110-90', 'tipe' => 'aset']);
    }

    public function test_api_update_coa_menolak_saldo_normal_tidak_konsisten(): void
    {
        $akun = AkunCOA::create([
            'kode' => '520-98', 'nama' => 'Beban Uji Update', 'tipe' => 'beban',
            'kelompok' => 'beban_operasional', 'saldo_normal' => 'debit', 'is_active' => true,
        ]);

        $response = $this->putJson("/api/akunting/coa/{$akun->id}", ['saldo_normal' => 'kredit']);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertSame('debit', $akun->fresh()->saldo_normal);
    }

    public function test_api_update_coa_menolak_kode_baru_di_luar_skema(): void
    {
        $akun = AkunCOA::create([
            'kode' => '110-91', 'nama' => 'Bank Uji Kode', 'tipe' => 'aset',
            'kelompok' => 'bank', 'saldo_normal' => 'debit', 'is_active' => true,
        ]);

        $response = $this->putJson("/api/akunting/coa/{$akun->id}", ['kode' => '999999']);

        $response->assertStatus(422);
        $this->assertSame('110-91', $akun->fresh()->kode);
    }

    public function test_api_update_nama_akun_kontra_berjurnal_tetap_boleh(): void
    {
        $this->seedSkenarioKontra();
        $retur = AkunCOA::where('kode', '410-02')->firstOrFail();

        // Hanya nama — klasifikasi efektif (tipe/saldo_normal/is_kontra) dibaca
        // dari model, tidak boleh false-positive menolak rename.
        $response = $this->putJson("/api/akunting/coa/{$retur->id}", ['nama' => 'Retur Penjualan (rename)']);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $fresh = $retur->fresh();
        $this->assertSame('Retur Penjualan (rename)', $fresh->nama);
        $this->assertSame('debit', $fresh->saldo_normal);
        $this->assertTrue((bool) $fresh->is_kontra);
    }

    // =====================================================================
    // F-08 — validasi yang sama di UI Livewire (simpanCoa)
    // =====================================================================

    public function test_livewire_simpan_coa_menolak_kode_di_luar_skema(): void
    {
        Livewire::test(AkuntingDashboard::class)
            ->set('coaForm', [
                'id' => null, 'kode' => '907024506', 'nama' => 'KAS BCA', 'tipe' => 'beban',
                'kelompok' => 'STOK', 'saldo_normal' => 'debit', 'is_kontra' => false,
            ])
            ->call('simpanCoa')
            ->assertHasErrors(['coaForm.kode']);

        $this->assertDatabaseMissing('akun_coa', ['kode' => '907024506']);
    }

    public function test_livewire_simpan_coa_menolak_tipe_saldo_normal_tidak_konsisten(): void
    {
        Livewire::test(AkuntingDashboard::class)
            ->set('showCoaModal', true)
            ->set('coaForm', [
                'id' => null, 'kode' => '520-97', 'nama' => 'Beban Uji UI', 'tipe' => 'beban',
                'kelompok' => 'beban_operasional', 'saldo_normal' => 'kredit', 'is_kontra' => false,
            ])
            ->call('simpanCoa')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? '') === 'error')
            ->assertSet('showCoaModal', true);

        $this->assertDatabaseMissing('akun_coa', ['kode' => '520-97']);
    }

    public function test_livewire_simpan_coa_kontra_eksplisit_tersimpan(): void
    {
        Livewire::test(AkuntingDashboard::class)
            ->set('coaForm', [
                'id' => null, 'kode' => '160-90', 'nama' => 'Akumulasi Amortisasi', 'tipe' => 'aset',
                'kelompok' => 'akumulasi_depresiasi', 'saldo_normal' => 'kredit', 'is_kontra' => true,
            ])
            ->call('simpanCoa')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? '') === 'success');

        $akun = AkunCOA::where('kode', '160-90')->firstOrFail();
        $this->assertTrue((bool) $akun->is_kontra);
        $this->assertSame('kredit', $akun->saldo_normal);
    }

    public function test_livewire_edit_nama_akun_kontra_berjurnal_tetap_boleh(): void
    {
        $this->seedSkenarioKontra();
        $retur = AkunCOA::where('kode', '410-02')->firstOrFail();

        Livewire::test(AkuntingDashboard::class)
            ->set('coaForm', [
                'id' => $retur->id, 'kode' => '410-02', 'nama' => 'Retur Penjualan UI', 'tipe' => 'pendapatan',
                'kelompok' => 'retur_penjualan', 'saldo_normal' => 'debit', 'is_kontra' => true,
            ])
            ->call('simpanCoa')
            ->assertDispatched('alert', fn ($event, $params) => ($params[0]['type'] ?? $params['type'] ?? '') === 'success');

        $this->assertSame('Retur Penjualan UI', $retur->fresh()->nama);
    }

    // =====================================================================
    // F-08 — migration: akun liar dorman dinonaktifkan (guarded, idempoten)
    // =====================================================================

    public function test_migrasi_menonaktifkan_akun_liar_dorman_tanpa_menyentuh_yang_berjurnal(): void
    {
        $files = glob(database_path('migrations/*fix_coa_kontra*.php'));
        $this->assertNotEmpty($files, 'Migration koreksi klasifikasi COA harus ada');
        $migration = require $files[0];

        // Akun liar dorman (pola kasus produksi 907024506 "KAS BCA").
        $liar = AkunCOA::create([
            'kode' => '907024506', 'nama' => 'KAS BCA', 'tipe' => 'beban',
            'kelompok' => 'STOK', 'saldo_normal' => 'debit', 'is_active' => true,
        ]);
        // Akun kode liar LAIN yang sudah ber-jurnal — tidak boleh disentuh.
        $berjurnal = AkunCOA::create([
            'kode' => '907024507', 'nama' => 'Kas Uji Berjurnal', 'tipe' => 'aset',
            'kelompok' => 'kas', 'saldo_normal' => 'debit', 'is_active' => true,
        ]);
        $this->postJurnal([['907024507', 1000, 0], ['110-01', 0, 1000]]);

        $migration->up();

        $this->assertFalse((bool) $liar->fresh()->is_active, 'Akun liar dorman wajib dinonaktifkan');
        $this->assertTrue((bool) $berjurnal->fresh()->is_active, 'Akun ber-jurnal tidak boleh dinonaktifkan otomatis');

        // Idempoten — up() kedua aman.
        $migration->up();
        $this->assertFalse((bool) $liar->fresh()->is_active);
    }

    public function test_migration_down_up_roundtrip_memulihkan_deklarasi_dan_laporan_tetap_balance(): void
    {
        $files = glob(database_path('migrations/*fix_coa_kontra*.php'));
        $this->assertNotEmpty($files);
        $migration = require $files[0];

        $this->seedSkenarioKontra();
        $sebelum = $this->snapshotLaporan();
        $this->assertTrue($sebelum['neraca_balance']);

        // Rollback: deklarasi hack lama kembali, kolom is_kontra di-drop.
        $migration->down();
        $this->assertFalse(Schema::hasColumn('akun_coa', 'is_kontra'));
        $this->assertSame('kredit', AkunCOA::where('kode', '410-02')->value('saldo_normal'));
        $this->assertSame('debit', AkunCOA::where('kode', '130-02')->value('saldo_normal'));

        // Maju lagi: deklarasi PSAK pulih, angka laporan identik & balance.
        $migration->up();
        $this->assertDeklarasiPsakAktif();
        $this->assertSame($sebelum, $this->snapshotLaporan());
    }
}
