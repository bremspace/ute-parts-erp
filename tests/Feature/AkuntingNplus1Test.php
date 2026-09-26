<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Livewire\AkuntingDashboard;
use App\Modules\Akunting\Livewire\LaporanPajak;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * [B-15b] N+1 & full-set — laporan akunting WAJIB agregasi di SQL, bukan hydrate
 * seluruh baris jurnal ke memori.
 *
 * Empat temuan yang dikunci di sini:
 *  1. `AkuntingDashboard::getLabaRugiProperty()` — SEBELUMNYA `with('akun')->get()`
 *     untuk SELURUH periode; sekarang `GROUP BY akun` + cache 15 menit.
 *  2. `AkuntingDashboard::getArusKasProperty()` — sama seperti (1).
 *  3. `ExportLaporanService::neracaSaldo()` — SEBELUMNYA menarik seluruh jurnal
 *     sejak awal pembukuan tanpa batas bawah; sekarang agregat per akun.
 *  4. `getPiutangsProperty()` / `getUtangsProperty()` — `get()` tanpa paginasi;
 *     `LaporanPajak::getKeluaranRowsProperty()` — `get()` tanpa cache.
 *
 * Yang DIJAGA: ANGKA TIDAK BISA BERUBAH. Setiap laporan diuji dua sisi —
 * query/memori diturunkan, DAN hasilnya dibandingkan dengan implementasi LAMA
 * (replikasi row-level di test ini) serta dengan SUM manual di PHP atas fixture
 * yang sama.
 *
 * CATATAN PENGUKURAN MEMORI: `memory_reset_peak_usage()` menyetel peak ke
 * `memory_get_usage()` SAAT INI (bukan nol). Jadi `memory_get_peak_usage()`
 * sesudah reset tidak pernah bisa lebih kecil dari memori yang sudah dipakai
 * fixture (puluhan MB), sehingga peak ABSOLUT tidak bisa dijadikan dasar
 * ambang. Yang diuji — dan tidak boleh dilonggarkan — adalah TAMBAHAN memori
 * yang ditanggung laporan: peak laporan dikurangi usage saat pengukuran mulai.
 * Ambang 20 MB / 64 MB tidak diubah.
 *
 * CATATAN API PAGINATOR: `AbstractPaginator::items()` mengembalikan ARRAY
 * (bukan Collection) di framework ini — berlaku untuk `simplePaginate()` maupun
 * `paginate()`. Assertion baris memakai `collect($paginator->items())` supaya
 * tidak mengunci kelas paginator tertentu; nilai yang dibandingkan tetap dari
 * query independen.
 */
class AkuntingNplus1Test extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Cabang $cabangLain;

    private User $user;

    private string $dari;

    private string $sampai;

    /** @var array<string,int> kode akun COA => id */
    private array $akun = [];

    /**
     * Pasangan akun debit/kredit untuk generator fixture jurnal besar.
     * Menutup semua tipe + kelompok akun yang dipakai arus kas.
     *
     * @var array<int,array{0:string,1:string}>
     */
    private array $pasanganJurnal = [
        ['110-01', '410-01'], // penjualan tunai   (Dr Kas / Cr Pendapatan)
        ['130-01', '510-02'], // HPP               (Dr HPP / Cr Persediaan)
        ['130-01', '210-01'], // pembelian         (Dr Persediaan / Cr Utang Usaha)
        ['110-01', '520-03'], // listrik & air     (Dr Beban / Cr Kas)
        ['150-02', '310-01'], // beli peralatan    (Dr Peralatan / Cr Modal)
        ['150-03', '210-03'], // beli perlengkapan   (Dr Perlengkapan / Cr Utang Pajak)
        ['120-01', '410-01'], // penjualan kredit  (Dr Piutang / Cr Pendapatan)
        ['150-01', '310-01'], // aset tetap        (Dr Aset Tetap / Cr Modal)
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seedCoa();

        $this->cabang = Cabang::create(['kode' => 'CBG-NP1', 'nama' => 'Cabang N+1', 'is_active' => true]);
        $this->cabangLain = Cabang::create(['kode' => 'CBG-NP2', 'nama' => 'Cabang N+1 Lain', 'is_active' => true]);

        $this->user = User::create([
            'name' => 'Akuntan N+1',
            'email' => 'akuntan-nplus1@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('finance');
        $this->user->cabangs()->attach($this->cabang->id);
        session(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user, 'web');

        // Periode yang sama dengan `mount()` AkuntingDashboard.
        $this->dari = now()->startOfMonth()->toDateString();
        $this->sampai = now()->toDateString();
    }

    private function seedCoa(): void
    {
        $akun = [
            '110-01' => ['Kas', 'aset', 'kas', 'debit'],
            '120-01' => ['Piutang Usaha', 'aset', 'piutang', 'debit'],
            '130-01' => ['Persediaan', 'aset', 'persediaan', 'debit'],
            '150-01' => ['Tanah & Bangunan', 'aset', 'aset_tetap', 'debit'],
            '150-02' => ['Peralatan Kantor', 'aset', 'peralatan', 'debit'],
            '150-03' => ['Perlengkapan', 'aset', 'perlengkapan', 'debit'],
            '210-01' => ['Utang Usaha', 'kewajiban', 'utang_usaha', 'kredit'],
            '210-03' => ['Utang Pajak', 'kewajiban', 'utang_pajak', 'kredit'],
            '310-01' => ['Modal Pemilik', 'ekuitas', 'modal', 'kredit'],
            '310-02' => ['Laba Ditahan', 'ekuitas', 'laba_ditahan', 'kredit'],
            '410-01' => ['Pendapatan Penjualan', 'pendapatan', 'pendapatan_penjualan', 'kredit'],
            '510-02' => ['HPP', 'beban', 'hpp', 'debit'],
            '520-03' => ['Beban Listrik & Air', 'beban', 'operasional', 'debit'],
        ];

        foreach ($akun as $kode => [$nama, $tipe, $kelompok, $saldo]) {
            // firstOrCreate: sebagian kode (mis. 160-01) sudah dibuat migrasi,
            // jadi jangan pakai create() yang akan bentrok UNIQUE.
            $this->akun[$kode] = AkunCOA::firstOrCreate(
                ['kode' => $kode],
                ['nama' => $nama, 'tipe' => $tipe, 'kelompok' => $kelompok, 'saldo_normal' => $saldo, 'is_active' => true]
            )->id;
        }
    }

    /**
     * Fixture jurnal besar via bulk insert (melewati model supaya tidak menulis
     * activity_log puluhan ribu kali).
     *
     * @return int jumlah baris jurnal yang dibuat
     */
    private function seedJurnalBesar(int $jumlahPasangan, ?int $cabangId = null): int
    {
        $cabangId ??= $this->cabang->id;
        $now = now();
        $dalamPeriode = [
            $now->copy()->startOfMonth()->toDateString(),
            $now->copy()->subDays(4)->toDateString(),
            $now->toDateString(),
        ];
        // 1 dari 5 pasang ber tanggal SEBELUM periode: laba rugi/arus kas periode
        // TIDAK boleh ikut, tapi neraca kumulatif HARUS ikut.
        $sebelumPeriode = $now->copy()->subMonth()->toDateString();

        $buffer = [];
        $total = 0;

        for ($i = 0; $i < $jumlahPasangan; $i++) {
            [$kodeDebit, $kodeKredit] = $this->pasanganJurnal[$i % count($this->pasanganJurnal)];
            // Nominal desimal (bukan bulat) supaya pembulatan round(...,2) ikut teruji
            $nominal = round(10_000 + (($i * 7919) % 875_000) + (($i % 97) / 100), 2);
            $tanggal = ($i % 5 === 0) ? $sebelumPeriode : $dalamPeriode[$i % 3];
            $noJurnal = 'JRL-NP-'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT);
            $deskripsi = 'Jurnal fixture B-15b #'.($i + 1);

            $buffer[] = [
                'no_jurnal' => $noJurnal, 'tanggal' => $tanggal, 'cabang_id' => $cabangId,
                'akun_coa_id' => $this->akun[$kodeDebit], 'sumber' => 'manual', 'deskripsi' => $deskripsi,
                'debit' => $nominal, 'kredit' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ];
            $buffer[] = [
                'no_jurnal' => $noJurnal, 'tanggal' => $tanggal, 'cabang_id' => $cabangId,
                'akun_coa_id' => $this->akun[$kodeKredit], 'sumber' => 'manual', 'deskripsi' => $deskripsi,
                'debit' => 0, 'kredit' => $nominal,
                'created_at' => $now, 'updated_at' => $now,
            ];
            $total += 2;

            if (count($buffer) >= 200) {
                DB::table('jurnal_akuntansi')->insert($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::table('jurnal_akuntansi')->insert($buffer);
        }

        return $total;
    }

    private function componentBaru(): AkuntingDashboard
    {
        $component = app(AkuntingDashboard::class);
        $component->mount();

        return $component;
    }

    // =============================================================
    // (1) + (2) Laba rugi & arus kas: query + memori
    // =============================================================

    public function test_laba_rugi_dan_arus_kas_jumlah_query_dan_memori_terkontrol(): void
    {
        $baris = $this->seedJurnalBesar(20_000); // 40.000 baris jurnal
        $this->seedJurnalBesar(200, $this->cabangLain->id); // jurnal cabang lain
        $this->assertSame(40_400, (int) DB::table('jurnal_akuntansi')->count());

        $component = $this->componentBaru();
        Cache::flush();

        // Puncak memori diukur KHUSUS untuk pemanggilan laporan: fixture
        // (SQLite :memory: 40.000 baris) sudah menduduki memori sebelum
        // pengukuran dimulai, jadi yang diukur adalah tambahan memori yang
        // ditanggung laporan — itulah yang OOM di server RAM 1GB.
        //
        // CATATAN PENGUKURAN: `memory_reset_peak_usage()` menyetel peak := usage
        // SAAT INI (bukan nol), jadi `memory_get_peak_usage()` sesudahnya
        // tidak pernah bisa di bawah memori yang sudah dipakai fixture. Yang
        // diuji adalah SELISIH peak laporan terhadap usage saat pengukuran
        // dimulai — angka itulah yang dimaksud "tambahan memori".
        $memoriSebelum = memory_get_usage();
        $puncakFixture = memory_get_peak_usage();
        memory_reset_peak_usage();

        DB::enableQueryLog();
        $labaRugi = $component->getLabaRugiProperty();
        $arusKas = $component->getArusKasProperty();
        $jumlahQuery = count(DB::getQueryLog());
        $puncakLaporan = memory_get_peak_usage();
        DB::disableQueryLog();

        $tambahanMemori = max(0, $puncakLaporan - $memoriSebelum);

        $this->assertLessThanOrEqual(
            50,
            $jumlahQuery,
            "Laba rugi + arus kas tidak boleh query per baris/akun (terlihat {$jumlahQuery} query untuk {$baris} baris jurnal)"
        );
        $this->assertLessThan(
            20_000_000,
            $tambahanMemori,
            'Laporan tidak boleh menambah memori >20MB untuk 40.000 baris jurnal '
            .'(terukur '.number_format($tambahanMemori).' byte tambahan; memori awal '
            .number_format($memoriSebelum).' byte & puncak fixture '
            .number_format($puncakFixture).' byte tidak dihitung sebagai tambahan laporan)'
        );

        // Fixture benar-benar terbaca (bukan hasil kosong yang lolos).
        $this->assertNotSame(0, count($labaRugi['pendapatan']));
        $this->assertNotSame(0, count($labaRugi['beban']));
        $this->assertNotSame(0.0, (float) $labaRugi['total_pendapatan']);
        $this->assertNotSame(0.0, (float) $arusKas['arus_kas_operasi']);
        $this->assertNotSame(0.0, (float) $arusKas['kenaikan_kas_neto']);
    }

    public function test_laba_rugi_dan_arus_kas_identik_dengan_perhitungan_lama_setelah_query_dan_memori_diturunkan(): void
    {
        $this->seedJurnalBesar(10_000); // 20.000 baris jurnal
        $this->seedJurnalBesar(200, $this->cabangLain->id);

        // --- SEBELUM (replikasi kode lama, row-level `with('akun')->get()`) ---
        DB::enableQueryLog();
        memory_reset_peak_usage();
        $lama = [
            'laba_rugi' => $this->labaRugiVersiLama(),
            'arus_kas' => $this->arusKasVersiLama(),
        ];
        $queryLama = count(DB::getQueryLog());
        $puncakLama = memory_get_peak_usage();

        // --- SESUDAH (agregat SQL per akun + cache 15 menit) ---
        DB::flushQueryLog();
        memory_reset_peak_usage();
        $component = $this->componentBaru();
        Cache::flush();
        $baru = [
            'laba_rugi' => $component->getLabaRugiProperty(),
            'arus_kas' => $component->getArusKasProperty(),
        ];
        $queryBaru = count(DB::getQueryLog());
        $puncakBaru = memory_get_peak_usage();
        DB::disableQueryLog();

        // 1) ANGKA WAJIB IDENTIK — per akun pendapatan & beban (dibandingkan per
        //    kode akun: urutan baris versi lama ikut rencana query, bukan kontrak)
        $this->assertEquals(
            $this->petakanPerKode($lama['laba_rugi']['pendapatan']),
            $this->petakanPerKode($baru['laba_rugi']['pendapatan']),
            'Pendapatan per akun (kode/nama/total) tidak boleh berubah'
        );
        $this->assertEquals(
            $this->petakanPerKode($lama['laba_rugi']['beban']),
            $this->petakanPerKode($baru['laba_rugi']['beban']),
            'Beban per akun (kode/nama/total) tidak boleh berubah'
        );
        $this->assertEquals($lama['laba_rugi']['total_pendapatan'], $baru['laba_rugi']['total_pendapatan']);
        $this->assertEquals($lama['laba_rugi']['total_beban'], $baru['laba_rugi']['total_beban']);
        $this->assertEquals($lama['laba_rugi']['laba_bersih'], $baru['laba_rugi']['laba_bersih']);

        // 2) ANGKA WAJIB IDENTIK — arus kas per kelompok akun (termasuk yang
        //    terlihat quirky: delta piutang/persediaan/utang = PERGERAKAN periode)
        foreach ([
            'laba_bersih', 'arus_kas_operasi', 'arus_kas_investasi', 'arus_kas_pendanaan', 'kenaikan_kas_neto',
        ] as $key) {
            $this->assertEqualsWithDelta(
                $lama['arus_kas'][$key],
                $baru['arus_kas'][$key],
                0.005,
                "Arus kas pada key {$key} tidak boleh berubah"
            );
        }
        foreach (['kenaikan_piutang', 'kenaikan_persediaan', 'kenaikan_utang'] as $key) {
            $this->assertEqualsWithDelta(
                $lama['arus_kas']['penyesuaian'][$key],
                $baru['arus_kas']['penyesuaian'][$key],
                0.005,
                "Penyesuaian arus kas pada key {$key} tidak boleh berubah"
            );
        }

        // 3) BUKTI perbaikan: query & memori turun jauh, angka tetap sama
        $this->assertLessThanOrEqual(50, $queryBaru, "Query sesudah = {$queryBaru}");
        $this->assertLessThan($queryLama, $queryBaru, "Query lama {$queryLama} harus lebih besar dari {$queryBaru}");
        $this->assertLessThan(
            $puncakLama,
            $puncakBaru,
            'Puncak memori setelah harus lebih kecil dari versi lama '
            .'(lama '.number_format($puncakLama).' byte, baru '.number_format($puncakBaru).' byte)'
        );
    }

    public function test_laba_rugi_dan_arus_kas_dilayani_cache_dan_scoped_cabang(): void
    {
        $this->seedJurnalBesar(20);

        $component = $this->componentBaru();
        $pertama = ['laba_rugi' => $component->getLabaRugiProperty(), 'arus_kas' => $component->getArusKasProperty()];

        // Panggilan kedua harus 100% dari cache (0 query) dan angkanya sama.
        DB::enableQueryLog();
        DB::flushQueryLog();
        $kedua = ['laba_rugi' => $component->getLabaRugiProperty(), 'arus_kas' => $component->getArusKasProperty()];
        $queryCache = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $queryCache, 'Panggilan kedua wajib dari cache (tidak boleh query lagi)');
        $this->assertEquals($pertama['laba_rugi'], $kedua['laba_rugi']);
        $this->assertEquals($pertama['arus_kas'], $kedua['arus_kas']);

        // Jurnal cabang lain tidak boleh bocor ke laporan cabang aktif.
        $this->seedJurnalBesar(50, $this->cabangLain->id);
        $lain = $this->componentBaru();
        $cabangLain = ['laba_rugi' => $lain->getLabaRugiProperty(), 'arus_kas' => $lain->getArusKasProperty()];

        $this->assertEquals($pertama['laba_rugi'], $cabangLain['laba_rugi'], 'Jurnal cabang lain tidak boleh masuk laba rugi');
        $this->assertEquals($pertama['arus_kas'], $cabangLain['arus_kas'], 'Jurnal cabang lain tidak boleh masuk arus kas');
    }

    public function test_view_dashboard_menampilkan_laba_rugi_dan_arus_kas_yang_sama_dengan_method(): void
    {
        $this->seedJurnalBenar();

        $langsung = $this->componentBaru();
        $ekspektasiLabaRugi = $langsung->getLabaRugiProperty();
        $ekspektasiArusKas = $langsung->getArusKasProperty();

        $view = Livewire::test(AkuntingDashboard::class);

        $this->assertEquals($ekspektasiLabaRugi, $view->viewData('labaRugi'));
        $this->assertEquals($ekspektasiArusKas, $view->viewData('arusKas'));
    }

    /** @param  Collection<int,array<string,mixed>>  $baris */
    private function petakanPerKode(Collection $baris): array
    {
        $peta = $baris->mapWithKeys(fn (array $r): array => [$r['kode'] => [$r['nama'], $r['total']]])->all();
        ksort($peta);

        return $peta;
    }

    /** Jurnal double-entry yang nyata (5 pasang) supaya angka bisa dibaca mata. */
    private function seedJurnalBenar(): void
    {
        $pasangan = [
            [['110-01', 5_000_000, 0], ['310-01', 0, 5_000_000]], // modal
            [['130-01', 2_000_000, 0], ['210-01', 0, 2_000_000]], // pembelian
            [['110-01', 3_000_000, 0], ['410-01', 0, 3_000_000]], // penjualan
            [['510-02', 2_000_000, 0], ['130-01', 0, 2_000_000]], // HPP
            [['110-01', 0, 100_000], ['520-03', 100_000, 0]],     // listrik
        ];

        foreach ($pasangan as $idx => $baris) {
            $noJurnal = 'JRL-NPX-'.str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT);
            foreach ($baris as [$kode, $debit, $kredit]) {
                JurnalAkuntansi::create([
                    'no_jurnal' => $noJurnal,
                    'tanggal' => $this->sampai,
                    'cabang_id' => $this->cabang->id,
                    'akun_coa_id' => $this->akun[$kode],
                    'sumber' => 'manual',
                    'deskripsi' => 'Jurnal expectancy #'.($idx + 1),
                    'debit' => $debit,
                    'kredit' => $kredit,
                    'user_id' => $this->user->id,
                ]);
            }
        }
    }

    // =============================================================
    // (3) Neraca: agregat kumulatif
    // =============================================================

    public function test_neraca_saldo_agregat_jumlah_query_dan_memori_terkontrol(): void
    {
        $this->seedJurnalBesar(30_000); // 60.000 baris jurnal (dataset besar)
        $this->assertSame(60_000, (int) DB::table('jurnal_akuntansi')->count());

        $service = app(ExportLaporanService::class);

        // Sama seperti di atas: yang diuji adalah TAMBAHAN memori yang ditanggung
        // `neracaSaldo()`, bukan memori absolut. Fixture 60.000 baris sudah
        // menduduki heap sebelum pengukuran dimulai, dan
        // `memory_reset_peak_usage()` menyetel peak ke usage SAAT INI (bukan
        // nol), jadi peak absolut tidak pernah bisa di bawah 64MB.
        $memoriSebelum = memory_get_usage();
        $puncakFixture = memory_get_peak_usage();
        memory_reset_peak_usage();

        DB::enableQueryLog();
        $neraca = $service->neracaSaldo($this->cabang->id, $this->sampai);
        $jumlahQuery = count(DB::getQueryLog());
        $puncakNeraca = memory_get_peak_usage();
        DB::disableQueryLog();

        $tambahanMemori = max(0, $puncakNeraca - $memoriSebelum);

        $this->assertLessThanOrEqual(
            3,
            $jumlahQuery,
            "Neraca harus 1 query agregat (terlihat {$jumlahQuery} query untuk 60.000 baris jurnal)"
        );
        $this->assertLessThan(
            64 * 1024 * 1024,
            $tambahanMemori,
            'Neraca tidak boleh menambah memori >64MB untuk 60.000 baris jurnal '
            .'(terukur '.number_format($tambahanMemori).' byte tambahan; memori awal '
            .number_format($memoriSebelum).' byte & puncak fixture '
            .number_format($puncakFixture).' byte tidak dihitung sebagai tambahan laporan)'
        );

        // --- PARITAS: saldo per akun dari agregat SQL vs SUM manual di PHP ---
        $manual = $this->saldoKumulatifManual($this->cabang->id, $this->sampai);
        $dariNeraca = [];
        foreach (['aset', 'kewajiban', 'ekuitas', 'pendapatan', 'beban'] as $seksi) {
            foreach ($neraca[$seksi] as $baris) {
                $dariNeraca[$baris['kode']] = $baris['saldo'];
            }
        }
        ksort($dariNeraca);
        ksort($manual);

        $this->assertSame(
            array_keys($manual),
            array_keys($dariNeraca),
            'Daftar akun yang muncul di neraca wajib sama dengan hasil SUM manual'
        );
        foreach ($manual as $kode => $saldo) {
            $this->assertEqualsWithDelta(
                $saldo,
                $dariNeraca[$kode],
                0.005,
                "Saldo akun {$kode} di neraca tidak boleh berbeda dari SUM manual"
            );
        }

        // --- Widget dashboard & API WAJIB angka sama (sumber tunggal) ---
        $widget = Livewire::test(AkuntingDashboard::class)->viewData('neraca');
        foreach ([
            'total_aset', 'total_kewajiban', 'total_ekuitas',
            'laba_periode_berjalan', 'total_ekuitas_bersama_laba', 'selisih',
        ] as $key) {
            $this->assertEqualsWithDelta(
                $neraca[$key],
                (float) $widget[$key],
                0.01,
                "Widget neraca tidak boleh berbeda dari neracaSaldo() pada key {$key}"
            );
        }
        $this->assertSame($neraca['balance'], $widget['balance']);
    }

    public function test_neraca_saldo_tidak_menyertakan_jurnal_setelah_tanggal_sampai(): void
    {
        $this->seedJurnalBenar();

        $service = app(ExportLaporanService::class);
        // Neraca HARI INI dulu, sebelum jurnal masa depan ada.
        $sebelum = $service->neracaSaldo($this->cabang->id, $this->sampai);

        // Jurnal "besok" tidak boleh masuk neraca hari ini.
        foreach ([['110-01', 999_999, 0], ['210-01', 0, 999_999]] as [$kode, $debit, $kredit]) {
            JurnalAkuntansi::create([
                'no_jurnal' => 'JRL-NP-FUTUR', 'tanggal' => now()->addDay()->toDateString(),
                'cabang_id' => $this->cabang->id,
                'akun_coa_id' => $this->akun[$kode], 'sumber' => 'manual', 'deskripsi' => 'Jurnal masa depan',
                'debit' => $debit, 'kredit' => $kredit, 'user_id' => $this->user->id,
            ]);
        }

        $sesudah = $service->neracaSaldo($this->cabang->id, $this->sampai);

        $this->assertSame($sebelum['total_aset'], $sesudah['total_aset'], 'Jurnal masa depan tidak boleh mengubah total aset');
        $this->assertSame($sebelum['total_kewajiban'], $sesudah['total_kewajiban'], 'Jurnal masa depan tidak boleh mengubah total kewajiban');
    }

    /**
     * SUM manual di PHP atas fixture yang sama (baris per baris, bukan SQL).
     *
     * @return array<string,float> kode akun => saldo
     */
    private function saldoKumulatifManual(int $cabangId, string $sampai): array
    {
        $akun = DB::table('akun_coa')->get()->keyBy('id');
        $debit = [];
        $kredit = [];

        DB::table('jurnal_akuntansi')
            ->where('cabang_id', $cabangId)
            ->whereDate('tanggal', '<=', $sampai)
            ->orderBy('id')
            ->select('akun_coa_id', 'debit', 'kredit')
            ->cursor()
            ->each(function ($baris) use (&$debit, &$kredit): void {
                $id = $baris->akun_coa_id;
                $debit[$id] = ($debit[$id] ?? 0.0) + (float) $baris->debit;
                $kredit[$id] = ($kredit[$id] ?? 0.0) + (float) $baris->kredit;
            });

        $hasil = [];
        foreach ($debit as $akunId => $totalDebit) {
            $a = $akun[$akunId] ?? null;
            if (! $a) {
                continue; // akun hilang → versi lama juga membuangnya
            }
            $totalKredit = $kredit[$akunId] ?? 0.0;
            $hasil[$a->kode] = $a->saldo_normal === 'debit'
                ? round($totalDebit - $totalKredit, 2)
                : round($totalKredit - $totalDebit, 2);
        }

        ksort($hasil);

        return $hasil;
    }

    // =============================================================
    // (4) Piutang / Utang: paginasi
    // =============================================================

    public function test_daftar_piutang_dan_utang_terpaginasi_tanpa_mengubah_isi_tabel(): void
    {
        $pelanggan = Pelanggan::create(['nama' => 'Pelanggan N+1', 'telepon' => '0812000099']);

        for ($i = 1; $i <= 40; $i++) {
            Piutang::create([
                'no_piutang' => 'PTG-NP-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'pelanggan_id' => $pelanggan->id,
                'cabang_id' => $this->cabang->id,
                'jumlah' => 100_000 + $i,
                'jumlah_dibayar' => 0,
                'status' => 'belum_lunas',
            ]);
            Utang::create([
                'no_utang' => 'UTG-NP-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'referensi_tipe' => 'pembelian',
                'referensi_id' => $i,
                'cabang_id' => $this->cabang->id,
                'kreditor_nama' => 'Supplier N+1',
                'jumlah' => 200_000 + $i,
                'jumlah_dibayar' => 0,
                'status' => 'belum_lunas',
            ]);
        }

        // Cabang lain = 5 baris, tidak boleh bocor
        for ($i = 1; $i <= 5; $i++) {
            Piutang::create([
                'no_piutang' => 'PTG-NPX-'.$i, 'pelanggan_id' => $pelanggan->id,
                'cabang_id' => $this->cabangLain->id, 'jumlah' => 999_000, 'jumlah_dibayar' => 0,
                'status' => 'belum_lunas',
            ]);
            Utang::create([
                'no_utang' => 'UTG-NPX-'.$i, 'referensi_tipe' => 'pembelian', 'referensi_id' => $i,
                'cabang_id' => $this->cabangLain->id, 'kreditor_nama' => 'Supplier Lain',
                'jumlah' => 999_000, 'jumlah_dibayar' => 0, 'status' => 'belum_lunas',
            ]);
        }

        $component = $this->componentBaru();
        $piutang = $component->getPiutangsProperty();
        $utang = $component->getUtangsProperty();

        $this->assertCount(15, $piutang->items(), 'Piutang harus dipaginate 15 baris/halaman');
        $this->assertCount(15, $utang->items(), 'Utang harus dipaginate 15 baris/halaman');

        $this->assertSame(40, Piutang::where('cabang_id', $this->cabang->id)->count(), '40 piutang cabang aktif tetap utuh');
        $this->assertSame(40, Utang::where('cabang_id', $this->cabang->id)->count(), '40 utang cabang aktif tetap utuh');
        $this->assertTrue($piutang->hasMorePages(), 'Paginasi harus melaporkan masih ada halaman berikutnya');
        $this->assertTrue($utang->hasMorePages());

        // Halaman pertama harus berisi 15 baris PERTAMA dari hasil query lama
        // (urutan & isi tidak boleh berubah, hanya dipotong per halaman).
        //
        // CATATAN API: `AbstractPaginator::items()` mengembalikan ARRAY (bukan
        // Collection) di framework ini — baik untuk `simplePaginate()` maupun
        // `paginate()`. Jadi `items()` dibungkus `collect()` supaya assertion
        // ini tidak mengunci kelas paginator tertentu; nilai yang dibandingkan
        // tetap diambil dari query independen, jadi tidak ada yang dilonggarkan.
        $harapanPiutang = Piutang::where('cabang_id', $this->cabang->id)->latest()->limit(15)->pluck('no_piutang')->all();
        $this->assertSame($harapanPiutang, collect($piutang->items())->pluck('no_piutang')->all(), 'Baris & urutan piutang wajib sama');
        $harapanUtang = Utang::where('cabang_id', $this->cabang->id)->latest()->limit(15)->pluck('no_utang')->all();
        $this->assertSame($harapanUtang, collect($utang->items())->pluck('no_utang')->all(), 'Baris & urutan utang wajib sama');

        // Kolom & isi baris tidak berubah: pelanggan eager-load, kolom utuh.
        $baris = $piutang->items()[0];
        $this->assertSame('Pelanggan N+1', $baris->pelanggan?->nama);
        $this->assertSame('belum_lunas', $baris->status);
        $this->assertNotNull($baris->jatuh_tempo_lewat, 'Accessor Piutang harus tetap hidup');
        $this->assertEquals(
            100_000 + (int) substr((string) $baris->no_piutang, -3),
            (float) $baris->jumlah,
        );
        $this->assertSame(0.0, (float) $baris->jumlah_dibayar);

        $barisUtang = $utang->items()[0];
        $this->assertSame('Supplier N+1', $barisUtang->kreditor_nama);
        $this->assertSame('pembelian', $barisUtang->referensi_tipe);

        // Halaman 2 tetap bisa diambil (paginasi, bukan pemotongan data).
        $halaman2 = $this->componentBaru();
        $this->assertCount(15, $halaman2->getPiutangsProperty()->items());

        // Query count harus kecil: 1 (piutang) + 1 (eager load pelanggan).
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->componentBaru()->getPiutangsProperty();
        $queryPiutang = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(4, $queryPiutang, "Daftar piutang hanya boleh 1-2 query (terlihat {$queryPiutang})");
    }

    // =============================================================
    // (5) Laporan Pajak: cache baris keluaran
    // =============================================================

    public function test_baris_keluaran_pajak_dicache_tanpa_mengubah_isi(): void
    {
        foreach (range(1, 3) as $i) {
            Transaksi::create([
                'no_transaksi' => 'TRX-NP-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'cabang_id' => $this->cabang->id,
                'subtotal' => 1_000_000,
                'diskon_nominal' => 0,
                'dpp' => 1_000_000,
                'pajak_nominal' => 110_000,
                'ppn_nominal' => 110_000,
                'total_akhir' => 1_110_000,
                'status' => 'selesai',
            ]);
        }

        $component = app(LaporanPajak::class);
        $component->mount();
        Cache::flush();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $pertama = $component->getKeluaranRowsProperty();
        $queryPertama = count(DB::getQueryLog());
        DB::flushQueryLog();
        $kedua = $component->getKeluaranRowsProperty();
        $queryKedua = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(3, $pertama, 'Semua transaksi ber-PPN periode harus jadi baris');
        $this->assertSame([
            'tanggal' => now()->format('d/m/Y'),
            'no_transaksi' => 'TRX-NP-001',
            'dpp' => 1_000_000.0,
            'percent' => 11.0,
            'ppn' => 110_000.0,
        ], $pertama[0], 'Isi baris (DPP/percent/PPN) tidak boleh berubah');

        $this->assertSame(1, $queryPertama, 'Hitungan pertama = 1 query transaksi');
        $this->assertSame(0, $queryKedua, 'Hitungan kedua wajib dari cache (0 query)');
        $this->assertEquals($pertama, $kedua);
    }

    // =============================================================
    // Replikasi kode LAMA (row-level) — acuan paritas angka
    // =============================================================

    /**
     * @return array{pendapatan:Collection<int,array{nama:string,kode:string,total:float}>,beban:Collection<int,array{nama:string,kode:string,total:float}>,total_pendapatan:float,total_beban:float,laba_bersih:float}
     */
    private function labaRugiVersiLama(): array
    {
        $jurnals = JurnalAkuntansi::whereDate('tanggal', '>=', $this->dari)
            ->whereDate('tanggal', '<=', $this->sampai)
            ->where('cabang_id', $this->cabang->id)
            ->with('akun')
            ->get();

        $pendapatan = $jurnals->where('akun.tipe', 'pendapatan')->groupBy('akun_coa_id')->map(fn ($rows) => [
            'nama' => $rows->first()->akun?->nama ?? '?',
            'kode' => $rows->first()->akun?->kode ?? '?',
            'total' => round($rows->sum('kredit') - $rows->sum('debit'), 2),
        ])->values();

        $beban = $jurnals->where('akun.tipe', 'beban')->groupBy('akun_coa_id')->map(fn ($rows) => [
            'nama' => $rows->first()->akun?->nama ?? '?',
            'kode' => $rows->first()->akun?->kode ?? '?',
            'total' => round($rows->sum('debit') - $rows->sum('kredit'), 2),
        ])->values();

        return [
            'pendapatan' => $pendapatan,
            'beban' => $beban,
            'total_pendapatan' => round($pendapatan->sum('total'), 2),
            'total_beban' => round($beban->sum('total'), 2),
            'laba_bersih' => round($pendapatan->sum('total') - $beban->sum('total'), 2),
        ];
    }

    /**
     * @return array{laba_bersih:float,penyesuaian:array{kenaikan_piutang:float,kenaikan_persediaan:float,kenaikan_utang:float},arus_kas_operasi:float,arus_kas_investasi:float,arus_kas_pendanaan:float,kenaikan_kas_neto:float}
     */
    private function arusKasVersiLama(): array
    {
        $jurnals = JurnalAkuntansi::whereDate('tanggal', '>=', $this->dari)
            ->whereDate('tanggal', '<=', $this->sampai)
            ->where('cabang_id', $this->cabang->id)
            ->with('akun')
            ->get();

        $labaBersih = round(
            $jurnals->where('akun.tipe', 'pendapatan')->sum(fn ($j) => (float) $j->kredit - (float) $j->debit)
            - $jurnals->where('akun.tipe', 'beban')->sum(fn ($j) => (float) $j->debit - (float) $j->kredit),
            2
        );

        $piutangDelta = round($jurnals->where('akun.kode', '120-01')->sum('debit') - $jurnals->where('akun.kode', '120-01')->sum('kredit'), 2);
        $persediaanDelta = round($jurnals->where('akun.kode', '130-01')->sum('debit') - $jurnals->where('akun.kode', '130-01')->sum('kredit'), 2);
        $utangDelta = round(
            $jurnals->where('akun.kode', '210-01')->sum('kredit') - $jurnals->where('akun.kode', '210-01')->sum('debit')
            + $jurnals->where('akun.kode', '210-03')->sum('kredit') - $jurnals->where('akun.kode', '210-03')->sum('debit'),
            2
        );

        $arusKasOperasi = round($labaBersih - $piutangDelta - $persediaanDelta + $utangDelta, 2);

        $arusInvestasi = round(
            -$jurnals->where('akun.kelompok', 'aset_tetap')->sum(fn ($j) => (float) $j->debit - (float) $j->kredit)
            - $jurnals->where('akun.kelompok', 'peralatan')->sum(fn ($j) => (float) $j->debit - (float) $j->kredit)
            - $jurnals->where('akun.kelompok', 'perlengkapan')->sum(fn ($j) => (float) $j->debit - (float) $j->kredit),
            2
        );

        $arusPendanaan = round(
            $jurnals->where('akun.kelompok', 'modal')->sum(fn ($j) => (float) $j->kredit - (float) $j->debit)
            + $jurnals->where('akun.kelompok', 'laba_ditahan')->sum(fn ($j) => (float) $j->kredit - (float) $j->debit),
            2
        );

        return [
            'laba_bersih' => $labaBersih,
            'penyesuaian' => [
                'kenaikan_piutang' => -$piutangDelta,
                'kenaikan_persediaan' => -$persediaanDelta,
                'kenaikan_utang' => $utangDelta,
            ],
            'arus_kas_operasi' => $arusKasOperasi,
            'arus_kas_investasi' => $arusInvestasi,
            'arus_kas_pendanaan' => $arusPendanaan,
            'kenaikan_kas_neto' => round($arusKasOperasi + $arusInvestasi + $arusPendanaan, 2),
        ];
    }
}
