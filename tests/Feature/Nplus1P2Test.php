<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Crm\Services\LeadService;
use App\Modules\Dashboard\Livewire\DashboardIndex;
use App\Modules\Omnichannel\Contracts\ChannelAdapterInterface;
use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Omnichannel\Models\ChannelOrder;
use App\Modules\Omnichannel\Services\ChannelSyncService;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Models\SkemaKomisi;
use App\Modules\Reseller\Models\SkemaKomisiReseller;
use App\Modules\Reseller\Services\KomisiService;
use App\Modules\Wms\Models\CycleCountSchedule;
use App\Modules\Wms\Models\CycleCountTask;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\SupplierScore;
use App\Modules\Wms\Services\CycleCountService;
use App\Modules\Wms\Services\SupplierScoringService;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Workflow\Models\ApprovalRule;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * [B-15d] Regression N+1 P1/P2 — budget query + PARITAS ANGKA wajib.
 *
 * 9 titik perbaikan (P1 dashboard & komisi, P2 sisanya):
 *  1. DashboardIndex    — cache per widget (key wajib cabang + role)
 *  2. KomisiService     — eager-load skema (2 query, bukan 2 per kategori)
 *  3. KonfigurasiService + CrmController — 1 query `whereIn` utk 5 kunci
 *  4. LeadService       — funnel 1 query GROUP BY
 *  5. layout backoffice — badge approval ber-cache 60 dtk
 *  6. SupplierScoringService — batch PO + tanpa findOrFail redundan
 *  7. CycleCountService — 1 query stok utk semua item sample/baris hasil
 *  8. ChannelSyncService — guard set 1 query (idempotensi tetap utuh)
 *  9. AkunCOA           — accessor saldo di-deprecate + scope withSaldo()
 *
 * CATATAN cache: `phpunit.xml` memakai CACHE_STORE=array → cache HIT = 0 query.
 * Di produksi (CACHE_STORE=database) 1 cache GET = 1 SELECT ringan di tabel
 * `cache` (indexed by key), ditukar dengan agregasi berat yang dihemat. Paritas
 * diuji dengan membandingkan nilai widget render PERTAMA (cache kosong) vs
 * render KEDUA (cache hit) — wajib identik.
 */
class Nplus1P2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Cache array = in-memory per test. Flush supaya "render dingin" benar-benar
        // dingin dan test tidak bergantung urutan assertion.
        Cache::flush();
    }

    // =====================================================================
    // Helper
    // =====================================================================

    /**
     * Jalankan callable sambil merekam SEMUA query. Return [hasil, jumlah, sql].
     *
     * @return array{0: mixed, 1: int, 2: array<int, string>}
     */
    private function rekam(callable $fn): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $hasil = $fn();
            $sql = array_column(DB::getQueryLog(), 'query');
        } finally {
            DB::disableQueryLog();
        }

        return [$hasil, count($sql), $sql];
    }

    private function jumlah(callable $fn): int
    {
        return $this->rekam($fn)[1];
    }

    /** @return array<int, string> */
    private function sql(callable $fn): array
    {
        return $this->rekam($fn)[2];
    }

    /**
     * @param  array<int, string>  $sql
     * @return array<int, string>
     */
    private function sqlYang(array $sql, string $needle): array
    {
        return array_values(array_filter($sql, fn (string $q) => str_contains($q, $needle)));
    }

    /** @return array{0: User, 1: Cabang, 2: Cabang, 3: Gudang, 4: Gudang} */
    private function authed(array $roles = ['super-admin'], bool $seedCoa = true): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        if ($seedCoa) {
            $this->seed(AkunCoaSeeder::class);
        }

        $cabang = Cabang::create(['nama' => 'Pusat B15d', 'kode' => 'CBG-N1', 'is_active' => true]);
        $cabangLain = Cabang::create(['nama' => 'Cabang Lain B15d', 'kode' => 'CBG-N2', 'is_active' => true]);
        $gudang = Gudang::create(['cabang_id' => $cabang->id, 'nama' => 'Gudang B15d', 'kode' => 'GDG-N1', 'is_active' => true]);
        $gudangLain = Gudang::create(['cabang_id' => $cabangLain->id, 'nama' => 'Gudang N2', 'kode' => 'GDG-N2', 'is_active' => true]);

        $user = User::create([
            'name' => 'Tester Nplus1', 'email' => 'nplus1@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        foreach ($roles as $role) {
            $user->assignRole($role);
        }
        $user->cabangs()->attach($cabang->id);

        session(['cabang_id' => $cabang->id]);
        $this->actingAs($user, 'web');

        return [$user, $cabang, $cabangLain, $gudang, $gudangLain];
    }

    private function produk(string $slug, string $kategori = 'LCD'): Produk
    {
        return Produk::create([
            'nama' => 'Produk '.Str::upper($slug), 'slug' => $slug,
            'kategori' => $kategori, 'kondisi' => 'baru',
            'harga_beli' => 75000, 'harga_jual_retail' => 110000,
        ]);
    }

    private function transaksi(int $cabangId, string $status, float $total, ?int $kasirId = null, ?Produk $produk = null): Transaksi
    {
        $trx = Transaksi::create([
            'no_transaksi' => 'TRX-N1-'.Str::random(8),
            'cabang_id' => $cabangId,
            'kasir_id' => $kasirId,
            'total_akhir' => $total,
            'status' => $status,
        ]);

        if ($produk) {
            TransaksiItem::create([
                'transaksi_id' => $trx->id,
                'produk_id' => $produk->id,
                'jumlah' => 1,
                'harga_satuan' => $total,
                'subtotal' => $total,
            ]);
        }

        return $trx;
    }

    private function piutang(int $cabangId, Pelanggan $pelanggan, float $jumlah, int $hari): Piutang
    {
        return Piutang::create([
            'no_piutang' => 'PIU-N1-'.Str::random(8),
            'pelanggan_id' => $pelanggan->id,
            'cabang_id' => $cabangId,
            'jumlah' => $jumlah,
            'jumlah_dibayar' => 0,
            'jatuh_tempo' => now()->addDays($hari),
            'status' => 'belum_lunas',
        ]);
    }

    /**
     * Normalisasi nilai widget jadi bentuk stabil untuk dibandingkan
     * (Collection model → primary key) supaya perbandingan render dingin vs
     * cache-hit tidak dipengaruhi state internal object hasil (un)serialize.
     */
    private function snapshot(mixed $value): mixed
    {
        if ($value instanceof EloquentCollection) {
            return $value->modelKeys();
        }
        if ($value instanceof Collection) {
            return $value->map(fn ($v) => $this->snapshot($v))->all();
        }
        if ($value instanceof Model) {
            return $value->getKey();
        }
        if (is_array($value)) {
            return array_map(fn ($v) => $this->snapshot($v), $value);
        }

        return $value;
    }

    /** @return array<int, string> */
    private function widgetKeys(): array
    {
        return [
            'omzetHariIni', 'antrianServis', 'stokKritis', 'piutangJatuhTempo', 'komisiPending',
            'transaksiTerbaru', 'chartOmzet30', 'chartKategori', 'chartServisStatus',
            'chartPiutangAging', 'chartStokKritis', 'omzetShiftKasir', 'ringkasanKeuangan',
            'treasuryProjection', 'marketInsight', 'poPending',
        ];
    }

    /** @return array<string, mixed> */
    private function snapshotWidget(object $component): array
    {
        $snap = [];
        foreach ($this->widgetKeys() as $key) {
            $snap[$key] = $this->snapshot($component->viewData($key));
        }

        return $snap;
    }

    // =====================================================================
    // [P1-1] DashboardIndex — cache per widget
    // =====================================================================

    public function test_dashboard_render_kedua_murah_dan_angka_widget_identik(): void
    {
        [$user, $cabang, $cabangLain, $gudang] = $this->authed(
            ['super-admin', 'kasir', 'finance', 'marketing', 'staff-gudang']
        );

        $produk = $this->produk('b15d-lcd');
        $this->transaksi($cabang->id, 'selesai', 100000, $user->id, $produk);
        $this->transaksi($cabang->id, 'lunas', 250000, $user->id, $produk);
        $this->transaksi($cabangLain->id, 'selesai', 900000, null, $produk);
        StokItem::create(['produk_id' => $produk->id, 'gudang_id' => $gudang->id, 'jumlah' => 2, 'jumlah_minimum' => 5]);
        $pelanggan = Pelanggan::create(['nama' => 'Pelanggan B15d', 'email' => 'pel-b15d@test.com']);
        $this->piutang($cabang->id, $pelanggan, 1234000, 3);
        $this->piutang($cabangLain->id, $pelanggan, 9999000, 2);

        // Render #1 = cache kosong (query penuh). Render #2 = semua widget cache hit.
        [$component1, $dingin] = $this->rekam(fn () => Livewire::test(DashboardIndex::class));
        [$component2, $hangat, $sqlHangat] = $this->rekam(fn () => Livewire::test(DashboardIndex::class));

        // Batas keras: ~38 round-trip MySQL per interaksi Livewire SEBELUM perbaikan.
        $this->assertLessThanOrEqual(
            25, $hangat,
            "render ke-2 (cache hit) harus di bawah budget 25 query, dapat {$hangat}"
        );
        $this->assertLessThan($dingin, $hangat, "cache tidak berefek: dingin {$dingin} → hangat {$hangat}");

        // Tidak ada query agregasi berat yang terulang di render ke-2.
        foreach (['from "transaksi"', 'from "piutang"', 'from "jurnal_akuntansi"', 'from "stok_items"'] as $tabel) {
            $this->assertSame(
                [],
                $this->sqlYang($sqlHangat, $tabel),
                "render cache-hit tidak boleh query agregasi {$tabel}: ".implode(' | ', $this->sqlYang($sqlHangat, $tabel))
            );
        }

        // PARITAS: seluruh widget identik antara render dingin & cache hit.
        $this->assertEquals(
            $this->snapshotWidget($component1),
            $this->snapshotWidget($component2),
            'angka/label widget berubah setelah masuk cache — paritas B-01 rusak'
        );

        // Smoke: angka agregat yang sudah diverifikasi B-01 tetap sama.
        $omzet = $component1->viewData('omzetHariIni');
        $this->assertEquals(350000.0, (float) $omzet['cabang_aktif']);
        $this->assertEquals(1250000.0, (float) $omzet['semua_cabang']);
        $this->assertSame(3, (int) $omzet['jumlah_transaksi']);
        $this->assertEquals(350000.0, array_sum($component1->viewData('chartOmzet30')['values']));
        $this->assertSame(1, (int) $component1->viewData('piutangJatuhTempo')['total']);
        $this->assertSame(1, (int) $component1->viewData('stokKritis')['total']);
    }

    public function test_dashboard_cache_tidak_membocorkan_antar_cabang(): void
    {
        [$user, $cabang, $cabangLain] = $this->authed(['super-admin']);

        $akunKas = (int) AkunCOA::where('kode', '110-01')->value('id');
        foreach ([$cabang->id => 10000000, $cabangLain->id => 99000000] as $cabangId => $saldo) {
            JurnalAkuntansi::create([
                'no_jurnal' => 'JRL-N1-'.$cabangId,
                'tanggal' => now(),
                'cabang_id' => $cabangId,
                'akun_coa_id' => $akunKas,
                'sumber' => 'test',
                'deskripsi' => 'Kas B15d',
                'debit' => $saldo,
                'kredit' => 0,
                'user_id' => $user->id,
            ]);
        }

        // Render dua kali supaya cabang kedua benar-benar cache hit (bukan dingin).
        $t1 = Livewire::test(DashboardIndex::class)->viewData('treasuryProjection');
        $t2 = Livewire::test(DashboardIndex::class)->viewData('treasuryProjection');
        $this->assertEquals(10000000.0, (float) $t1['saldo_kas']);
        $this->assertEquals(10000000.0, (float) $t2['saldo_kas'], 'cache hit cabang 1 berubah');

        // Ganti cabang aktif → key cache berbeda → angka berbeda.
        session(['cabang_id' => $cabangLain->id]);
        $t3 = Livewire::test(DashboardIndex::class)->viewData('treasuryProjection');
        $this->assertEquals(99000000.0, (float) $t3['saldo_kas'], 'kas cabang lain bocor lewat cache');
    }

    public function test_key_cache_dashboard_memisahkan_role_dan_aktor(): void
    {
        [$kasir, $cabang] = $this->authed(['kasir'], false);

        $kasirLain = User::create([
            'name' => 'Kasir 2', 'email' => 'kasir2-b15d@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $kasirLain->assignRole('kasir');
        $kasirLain->cabangs()->attach($cabang->id);

        $this->transaksi($cabang->id, 'selesai', 70000, $kasir->id);
        $this->transaksi($cabang->id, 'selesai', 130000, $kasirLain->id);

        $a = Livewire::test(DashboardIndex::class)->viewData('omzetShiftKasir');
        $this->assertEquals(70000.0, (float) $a['omzet']);

        $this->actingAs($kasirLain, 'web');
        $b = Livewire::test(DashboardIndex::class)->viewData('omzetShiftKasir');
        $this->assertEquals(130000.0, (float) $b['omzet'], 'omzet shift kasir bocor antar user (key harus per-aktor)');
    }

    // =====================================================================
    // [P1-2] KomisiService — eager-load skema (2 query, bukan 2 per kategori)
    // =====================================================================

    public function test_komisi_query_konstan_per_kategori_dan_nominal_identik(): void
    {
        $reseller = Pelanggan::create(['nama' => 'Reseller B15d', 'is_reseller' => true, 'kode_agen' => 'RS-1']);
        $resellerLain = Pelanggan::create(['nama' => 'Reseller B15d 2', 'is_reseller' => true, 'kode_agen' => 'RS-2']);

        // Skema "Baterai" nominal dibuat lebih dulu (id kecil) supaya wins untuk
        // kategori Baterai — mendokumentasikan presedensi "baris pertama yang
        // cocok" persis seperti `->first()` versi lama.
        SkemaKomisi::create(['nama' => 'Baterai flat', 'kategori' => 'Baterai', 'tipe' => 'nominal', 'nilai' => 5000]);
        SkemaKomisi::create(['nama' => 'Default', 'kategori' => null, 'tipe' => 'persen', 'nilai' => 5]);
        SkemaKomisiReseller::create([
            'pelanggan_id' => $reseller->id, 'kategori' => 'LCD', 'tipe' => 'persen', 'nilai' => 10,
        ]);

        $svc = app(KomisiService::class);

        // Empat kategori: override reseller (LCD), fallback default (Aki),
        // nominal per barang (Baterai 2 pcs), dan kategori lain (Sparepart).
        $items4 = [
            ['kategori' => 'LCD', 'subtotal' => 100000, 'jumlah' => 1],      // 10%      → 10.000
            ['kategori' => 'Aki', 'subtotal' => 50000, 'jumlah' => 1],       //  5%      →  2.500
            ['kategori' => 'Baterai', 'subtotal' => 60000, 'jumlah' => 2],  // 5.000×2  → 10.000
            ['kategori' => 'Sparepart', 'subtotal' => 40000, 'jumlah' => 1], //  5%     →  2.000
        ];

        [$komisi4, $q4] = $this->rekam(fn () => $svc->hitungKomisiDariItems($reseller, $items4, ['keterangan' => 'b15d']));

        // Kategori tunggal → query HARUS sama (konstan, bukan 2K).
        $items1 = [['kategori' => 'LCD', 'subtotal' => 100000, 'jumlah' => 1]];
        [$komisi1, $q1] = $this->rekam(fn () => $svc->hitungKomisiDariItems($resellerLain, $items1, ['keterangan' => 'b15d']));

        $this->assertSame(
            $q1, $q4,
            "query komisi harus konstan terhadap jumlah kategori: K=1 → {$q1}, K=4 → {$q4}"
        );
        $this->assertLessThanOrEqual(6, $q4, "query skema komisi harus max 6, dapat {$q4}");

        // PARITAS ANGKA (SalarySensitive) — gold case dihitung manual.
        $this->assertNotNull($komisi4);
        $this->assertEquals(24500.0, (float) $komisi4->nominal_komisi, 'komisi 4 kategori harus persis 24.500');
        $this->assertNotNull($komisi1);
        // Reseller tanpa override → fallback skema default (kategori null) 5%.
        $this->assertEquals(5000.0, (float) $komisi1->nominal_komisi, 'fallback reseller tanpa override = 5% default');

        // Override reseller (10% utk LCD) tetap menang utk kategori LCD.
        [$komisiLcd] = $this->rekam(fn () => $svc->hitungKomisiDariItems(
            $reseller, [['kategori' => 'LCD', 'subtotal' => 200000, 'jumlah' => 1]], ['keterangan' => 'b15d']
        ));
        $this->assertNotNull($komisiLcd);
        $this->assertEquals(20000.0, (float) $komisiLcd->nominal_komisi, 'override 10% dari 200.000');
        $this->assertSame(
            (int) SkemaKomisiReseller::where('pelanggan_id', $reseller->id)->value('id'),
            (int) $komisiLcd->skema_komisi_id,
            'kategori tunggal dgn override harus memakai skema reseller'
        );

        // Non-reseller tetap tidak dapat komisi (paritas guard lama).
        $bukan = Pelanggan::create(['nama' => 'Bukan Reseller', 'is_reseller' => false]);
        $this->assertNull($svc->hitungKomisiDariItems($bukan, $items4, []));
    }

    // =====================================================================
    // [P2-3] KonfigurasiService + CrmController — batch 1 query + invalidasi cache
    // =====================================================================

    public function test_konfigurasi_lima_kunci_satu_query_dan_cache_terinvalidate(): void
    {
        $svc = app(KonfigurasiService::class);
        $kunci = array_keys($svc->defaults());
        $this->assertCount(5, $kunci);

        [$hasil, $q] = $this->rekam(fn () => $svc->getMany($kunci));
        $this->assertSame(1, $q, "getMany 5 kunci harus 1 query, dapat {$q}");
        $this->assertSame([], $hasil, 'tabel konfigurasi masih kosong');

        // get() ber-cache: query pertama 1, berikutnya 0.
        $qA = $this->jumlah(fn () => $svc->get('poin_earn_persen'));
        $qB = $this->jumlah(fn () => $svc->get('poin_earn_persen'));
        $this->assertSame(1, $qA, 'get() dingin = 1 query');
        $this->assertSame(0, $qB, 'get() hangat = 0 query (cache aktif)');

        // set() wajib meng-invalidate cache.
        $svc->set('poin_earn_persen', 9, 'test');
        $this->assertEquals(9.0, (float) $svc->get('poin_earn_persen'), 'cache tidak di-invalidate oleh set()');

        // setPpn() (dua kunci) juga menyegarkan cache.
        $svc->setPpn(7, true, 12);
        $this->assertTrue($svc->getPpnEnabled(7));
        $this->assertEquals(12.0, $svc->getPpnPercent(7));
        $this->assertEquals(11.0, $svc->getPpnPercent(), 'default PPN 11% tetap berlaku');

        $this->assertDatabaseHas('konfigurasi', ['kunci' => 'poin_earn_persen', 'nilai' => '9']);
        $this->assertDatabaseHas('konfigurasi', ['kunci' => 'ppn_enabled.7', 'nilai' => '1']);
    }

    public function test_api_config_get_mengembalikan_nilai_tersimpan(): void
    {
        $this->authed(['super-admin'], false);
        app(KonfigurasiService::class)->set('diskon_gold', 6, 'Strategi loyalitas');

        $this->getJson('/api/crm/config')
            ->assertSuccessful()
            ->assertJsonPath('data.poin_earn_persen', 5)      // fallback default
            ->assertJsonPath('data.poin_redeem_rupiah', 100)  // fallback default
            ->assertJsonPath('data.diskon_silver', 3)
            ->assertJsonPath('data.diskon_gold', 6)            // nilai tersimpan
            ->assertJsonPath('data.diskon_platinum', 10);
    }

    // =====================================================================
    // [P2-4] LeadService::getFunnelData — 1 query GROUP BY
    // =====================================================================

    public function test_funnel_lead_satu_query_dan_angka_identik_dengan_hitung_manual(): void
    {
        [, $cabang, $cabangLain] = $this->authed(['super-admin'], false);

        $data = [
            ['baru', 100000.0, $cabang->id],
            ['baru', 50000.0, $cabang->id],
            ['kontak', 250000.0, $cabang->id],
            ['won', 700000.0, $cabang->id],
            ['won', 300000.0, $cabang->id],
            ['lost', 90000.0, $cabang->id],
            // Cabang lain → tidak boleh bocor (scopeForCabang).
            ['baru', 9999999.0, $cabangLain->id],
        ];

        foreach ($data as $i => [$stage, $nilai, $cabangId]) {
            Lead::create([
                'cabang_id' => $cabangId,
                'sumber' => 'website',
                'stage' => $stage,
                'nama' => 'Lead B15d '.$i,
                'nilai_estimasi' => $nilai,
            ]);
        }

        $svc = app(LeadService::class);
        [$funnel, $q, $sql] = $this->rekam(fn () => $svc->getFunnelData($cabang->id));

        $this->assertSame(1, $q, "funnel lead harus 1 query, dapat {$q}: ".implode(' | ', $sql));
        $this->assertStringContainsString('group by', strtolower($sql[0]));

        // PARITAS: hitung manual per tahap (cabang aktif saja).
        foreach (['baru', 'kontak', 'kualifikasi', 'negosiasi', 'won', 'lost'] as $stage) {
            $baris = array_values(array_filter($data, fn ($d) => $d[0] === $stage && $d[2] === $cabang->id));
            $this->assertSame(count($baris), (int) $funnel[$stage]['count'], "jumlah lead {$stage} salah");
            $this->assertEquals(array_sum(array_column($baris, 1)), (float) $funnel[$stage]['value'], "nilai lead {$stage} salah");
            $this->assertNotSame('', $funnel[$stage]['label'], "label {$stage} tidak boleh kosong");
        }

        $this->assertSame(2, (int) $funnel['baru']['count']);
        $this->assertEquals(150000.0, (float) $funnel['baru']['value']);
        $this->assertSame(2, (int) $funnel['won']['count']);
        $this->assertEquals(1000000.0, (float) $funnel['won']['value']);
        $this->assertSame(0, (int) $funnel['kualifikasi']['count']);
    }

    // =====================================================================
    // [P2-5] Layout backoffice — badge approval ber-cache
    // =====================================================================

    public function test_badge_approval_bercache_dan_angka_tetap_benar(): void
    {
        [$user, $cabang, $cabangLain] = $this->authed(['super-admin']);

        $rule = ApprovalRule::where('entity_type', 'po')->firstOrFail();

        $buat = function (string $status, ?int $cabangId, int $entityId) use ($rule, $user): void {
            ApprovalRequest::create([
                'approval_rule_id' => $rule->id,
                'entity_type' => 'po',
                'entity_id' => $entityId,
                'cabang_id' => $cabangId,
                'payload_json' => ['amount' => 20000000],
                'status' => $status,
                'requested_by' => $user->id,
                'approver_role' => 'finance',
            ]);
        };

        $buat('pending', $cabang->id, 1);
        $buat('pending', $cabang->id, 2);
        $buat('pending', null, 3);           // global (whereNull cabang_id) ikut
        $buat('pending', $cabangLain->id, 4); // cabang lain → tidak ikut
        $buat('disetujui', $cabang->id, 5);   // bukan pending → tidak ikut

        // Harapan badge = 3 (2 cabang aktif + 1 global).
        $expected = ApprovalRequest::query()
            ->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('cabang_id')->orWhere('cabang_id', $cabang->id))
            ->count();
        $this->assertSame(3, $expected);

        $sql1 = $this->sql(fn () => $this->get('/app/akunting')->assertSuccessful());
        $badge1 = $this->sqlYang($sql1, 'from "approval_requests"');
        $this->assertCount(1, $badge1, 'render pertama harus query badge persis 1x');

        // Render kedua (masih dalam TTL 60 dtk) → badge dari cache, 0 query.
        $sql2 = $this->sql(fn () => $this->get('/app/akunting')->assertSuccessful());
        $this->assertSame(
            [],
            $this->sqlYang($sql2, 'from "approval_requests"'),
            'badge harus di-cache (tidak query ulang dalam 60 dtk)'
        );

        // PARITAS: angka badge di HTML tetap sama dengan hitungan DB.
        $html = (string) $this->get('/app/akunting')->assertSuccessful()->getContent();
        $this->assertSame(1, preg_match('/bg-up-red[^>]*>\s*(\d+)\s*</', $html, $m), 'badge approval tidak dirender');
        $this->assertSame(3, (int) $m[1], 'angka badge harus sama dengan hitungan DB');
    }

    // =====================================================================
    // [P2-6] SupplierScoringService — batch + angka identik
    // =====================================================================

    public function test_skor_supplier_batch_dan_nilai_identik_dengan_hitung_manual(): void
    {
        [$user, $cabang, , $gudang] = $this->authed(['super-admin'], false);

        $suppliers = collect([
            Supplier::create(['nama' => 'Supplier 1', 'termin_hari' => 30, 'is_active' => true]),
            Supplier::create(['nama' => 'Supplier 2', 'termin_hari' => 30, 'is_active' => true]),
            Supplier::create(['nama' => 'Supplier 3', 'termin_hari' => 30, 'is_active' => true]),
        ]);

        $hargaPerSupplier = [];
        foreach ($suppliers as $idx => $supplier) {
            $produk = $this->produk('b15d-po-'.$idx, 'Sparepart');

            // 2 PO: 'diterima' (non-draft, dihitung quality return) dan 'draft'
            // (tidak dihitung quality, tetap masuk rata-rata harga).
            foreach (['diterima', 'draft'] as $status) {
                $po = PurchaseOrder::create([
                    'no_po' => 'PO-N1-'.Str::random(8),
                    'supplier_id' => $supplier->id,
                    'gudang_tujuan_id' => $gudang->id,
                    'status' => $status,
                    'metode_bayar' => 'kredit',
                    'jatuh_tempo' => now()->addDays(30),
                    'total' => 300000,
                    'total_dibayar' => 0,
                ]);

                $harga = 100000 + ($idx * 10000) + ($status === 'draft' ? 5000 : 0);
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'produk_id' => $produk->id,
                    'harga_beli' => $harga,
                    'jumlah' => 2,
                    'subtotal' => $harga * 2,
                ]);

                $hargaPerSupplier[$supplier->id][] = $harga;
            }
        }

        $svc = app(SupplierScoringService::class);
        $periode = now()->format('Y-m');

        [$hasil, $qBatch] = $this->rekam(fn () => $svc->hitungSemua($periode));
        $this->assertSame(3, $hasil['diproses'], '3 supplier aktif harus diproses');
        $this->assertLessThanOrEqual(20, $qBatch, "hitungSemua 3 supplier harus ringan, dapat {$qBatch} query");

        $batch = SupplierScore::where('periode', $periode)->orderBy('supplier_id')->get()
            ->mapWithKeys(fn (SupplierScore $s) => [$s->supplier_id => (float) $s->total_score])->all();
        $this->assertCount(3, $batch);

        // PARITAS: hitungSkor (per supplier) menghasilkan angka sama dgn batch.
        $single = [];
        foreach ($suppliers as $supplier) {
            $r = $svc->hitungSkor($supplier->id, $periode);
            $single[$supplier->id] = $r;

            // Manual (formula lama): on-time 0 (tidak ada PO status 'selesai'),
            // quality 0 (tidak ada 'ditolak'), harga = rata-rata harga_beli item.
            $harga = $hargaPerSupplier[$supplier->id];
            $manualAvg = round(array_sum($harga) / count($harga), 2);
            $manualScore = round((0 * 0.4) + ((100 - 0) * 0.3) + (0 * 0.3), 2);

            $this->assertEquals(0.0, (float) $r['on_time_percent'], 'on-time harus 0');
            $this->assertEquals(0.0, (float) $r['quality_return_percent']);
            $this->assertEquals($manualAvg, (float) $r['avg_harga'], 'rata-rata harga harus sama dgn hitungan manual');
            $this->assertEquals($manualScore, (float) $r['total_score'], 'total score harus sama dgn formula lama');
            $this->assertEquals($r['total_score'], $batch[$supplier->id], "skor batch supplier #{$supplier->id} beda dgn hitungSkor");
        }

        // Scoping cabang pada tabel SUPAYA SCORE tetap berlaku (baris terpisah
        // per cabang; tabel `purchase_order` memang tidak punya kolom cabang_id
        // sehingga filter PO per cabang tidak bisa diuji di sini).
        $scoped = $svc->hitungSkor($suppliers->first()->id, $periode, $cabang->id);
        $this->assertEquals($single[$suppliers->first()->id]['total_score'], (float) $scoped['total_score'], 'skor cabang tidak boleh berbeda');
        $this->assertDatabaseHas('supplier_scores', [
            'supplier_id' => $suppliers->first()->id,
            'periode' => $periode,
            'cabang_id' => $cabang->id,
        ]);
        $this->assertDatabaseHas('supplier_scores', [
            'supplier_id' => $suppliers->first()->id,
            'periode' => $periode,
            'cabang_id' => null,
        ]);
    }

    // =====================================================================
    // [P2-7] CycleCountService — 1 query stok utk semua item
    // =====================================================================

    public function test_cycle_count_baca_stok_sekali_dan_hasil_identik(): void
    {
        [$user, $cabang, , $gudang, $gudangLain] = $this->authed(['super-admin']);

        $produk = $this->produk('b15d-oli', 'Oli');

        $sample = [];
        $fisik = [];
        $harapanSelisih = [];
        for ($i = 1; $i <= 5; $i++) {
            // Item terakhir sengaja berada di gudang CABANG LAIN → harus di-skip
            // guard scoping, nilainya jatuh ke snapshot `stok_sistem`.
            $gudangId = $i === 5 ? $gudangLain->id : $gudang->id;
            $stok = StokItem::create([
                'produk_id' => $produk->id, 'gudang_id' => $gudangId,
                'jumlah' => 10, 'jumlah_minimum' => 2,
            ]);

            $fisik[$stok->id] = $i <= 2 ? 12 : 10; // 2 item berselisih +2 (minor)
            $harapanSelisih[$stok->id] = $i <= 2 ? 2 : 0;

            $sample[] = [
                'stok_item_id' => $stok->id, 'produk_id' => $produk->id, 'gudang_id' => $gudangId,
                'rak_id' => null, 'nama' => 'Oli B15d '.$i, 'stok_sistem' => 10,
            ];
        }

        $schedule = CycleCountSchedule::create([
            'cabang_id' => $cabang->id, 'nama' => 'Rak B15d', 'tipe_target' => 'rak',
            'frekuensi' => 'mingguan', 'hari' => 1, 'jam' => '08:00',
            'sample_size' => 5, 'threshold_unit' => 5, 'threshold_persen' => 10, 'is_aktif' => true,
        ]);

        $task = CycleCountTask::create([
            'cycle_count_schedule_id' => $schedule->id,
            'cabang_id' => $cabang->id,
            'no_task' => 'CCT-N1-0001',
            'tanggal' => now()->toDateString(),
            'tipe_target' => 'rak',
            'target_label' => 'Rak B15d',
            'seed' => 4242,
            'sample_items' => $sample,
            'status' => 'menunggu_count',
            'threshold_unit' => 5,
            'threshold_persen' => 10,
        ]);

        $svc = app(CycleCountService::class);
        [$task, $q, $sql] = $this->rekam(fn () => $svc->hitung($task, $fisik, $user->id));

        // 1 query BACA (whereIn) untuk 5 item sample (hitung) + 1 lagi untuk 5
        // baris hasil (koreksi) — SEBELUM perbaikan: 1 query per item di kedua
        // tempat. (Query `where "id" = ?` per baris tetap ada — itu jalur activity
        // log saat update, TIDAK terkait baca.)
        $bacaStok = $this->sqlYang($sql, 'from "stok_items" where "id" in');
        $this->assertCount(
            2, $bacaStok,
            'cycle count harus baca stok 2x total (hitung + koreksi), bukan per item: '.implode(' | ', $bacaStok)
        );

        // PARITAS: selisih & klasifikasi per baris hasil.
        $hasil = $task->fresh()->hasil;
        $this->assertCount(5, $hasil);
        foreach ($hasil as $row) {
            $id = (int) $row['stok_item_id'];
            $this->assertSame(10, (int) $row['stok_sistem'], "stok sistem item #{$id} salah");
            $this->assertSame($harapanSelisih[$id], (int) $row['selisih'], "selisih item #{$id} salah");
            $this->assertSame(
                $harapanSelisih[$id] === 0 ? 'cocok' : 'minor',
                $row['klasifikasi'],
                "klasifikasi item #{$id} salah"
            );
        }

        // Item gudang cabang lain tidak boleh ikut dikoreksi.
        $this->assertSame(10, (int) StokItem::find($sample[4]['stok_item_id'])->fresh()->jumlah);

        // State machine F3-7 utuh: minor → koreksi langsung, task selesai.
        $this->assertSame('selesai', $task->fresh()->status);
        $this->assertSame(12, (int) StokItem::find($sample[0]['stok_item_id'])->fresh()->jumlah);
        $this->assertSame(10, (int) StokItem::find($sample[2]['stok_item_id'])->fresh()->jumlah);
        $this->assertSame(2, StokLog::where('jenis', 'cycle_count')->count());
        $this->assertDatabaseHas('jurnal_akuntansi', ['sumber' => 'opname']);
    }

    // =====================================================================
    // [P2-8] ChannelSyncService — guard set 1 query, idempotensi utuh
    // =====================================================================

    public function test_pull_orders_guard_satu_query_dan_tetap_idempoten(): void
    {
        $this->authed(['super-admin'], false);

        $channel = Channel::create([
            'nama' => 'Shopee B15d', 'platform' => 'shopee', 'status' => 'terhubung',
            'kredensial' => ['biaya_persen' => 5], 'is_active' => true,
        ]);

        $svc = new ChannelSyncService;
        $adapter = new FakeChannelAdapter;
        $ref = new \ReflectionProperty(ChannelSyncService::class, 'adapters');
        $ref->setAccessible(true);
        $ref->setValue($svc, ['shopee' => $adapter]);

        // Batch 1: 3 order unik + 1 duplikat di dalam batch yang sama.
        $adapter->orders = [
            ['order_sn' => 'SN-1', 'order_status' => 'PAYMENT', 'total_amount' => 200000],
            ['order_sn' => 'SN-2', 'order_status' => 'READY_TO_SHIP', 'total_amount' => 100000],
            ['order_sn' => 'SN-1', 'order_status' => 'PAYMENT', 'total_amount' => 200000],
            ['order_sn' => 'SN-3', 'order_status' => 'CANCELLED', 'total_amount' => 50000],
        ];

        [$created, $q, $sql] = $this->rekam(fn () => $svc->pullOrders($channel));

        $this->assertSame(3, $created, 'duplikat dalam 1 batch harus di-skip');
        $this->assertSame(3, ChannelOrder::where('channel_id', $channel->id)->count());
        $this->assertCount(
            1, $this->sqlYang($sql, 'from "channel_orders"'),
            'guard idempotensi harus 1 query: '.implode(' | ', $this->sqlYang($sql, 'from "channel_orders"'))
        );

        // PARITAS angka: biaya admin 5% & pemetaan status.
        $sn1 = ChannelOrder::where('channel_order_id', 'SN-1')->firstOrFail();
        $this->assertEquals(10000.0, (float) $sn1->estimasi_biaya_platform, '5% dari 200.000');
        $this->assertSame('menunggu_pembayaran', $sn1->status);
        $this->assertSame('batal', ChannelOrder::where('channel_order_id', 'SN-3')->firstOrFail()->status);

        // Batch 2: order sama persis → tidak ada yang dibuat (idempoten).
        [$created2] = $this->rekam(fn () => $svc->pullOrders($channel));
        $this->assertSame(0, $created2, 'pull kedua harus 0 order baru');
        $this->assertSame(3, ChannelOrder::where('channel_id', $channel->id)->count(), 'tidak boleh ada baris duplikat');

        // Batch 3: 1 order baru → guard tetap 1 query (bukan N).
        $adapter->orders = [['order_sn' => 'SN-4', 'order_status' => 'PAYMENT', 'total_amount' => 10000]];
        [$created3, $q3, $sql3] = $this->rekam(fn () => $svc->pullOrders($channel));
        $this->assertSame(1, $created3);
        $this->assertCount(1, $this->sqlYang($sql3, 'from "channel_orders"'), 'guard tetap 1 query');
    }

    // =====================================================================
    // [P2-9] AkunCOA::saldo — deprecation + scope withSaldo (anti N+1)
    // =====================================================================

    public function test_saldo_coa_bisa_dibaca_tanpa_n_plus_one(): void
    {
        [$user, $cabang] = $this->authed(['super-admin']);

        $kas = AkunCOA::where('kode', '110-01')->firstOrFail();
        $persediaan = AkunCOA::where('kode', '130-01')->firstOrFail();

        foreach ([[$kas, 8000000, 1000000], [$persediaan, 2000000, 500000]] as [$akun, $debit, $kredit]) {
            JurnalAkuntansi::create([
                'no_jurnal' => 'JRL-SALDO-'.$akun->id,
                'tanggal' => now(),
                'cabang_id' => $cabang->id,
                'akun_coa_id' => $akun->id,
                'sumber' => 'test',
                'deskripsi' => 'Saldo B15d',
                'debit' => $debit,
                'kredit' => $kredit,
                'user_id' => $user->id,
            ]);
        }

        // Aksesor lama (1 query) tetap menghasilkan angka yang sama.
        $this->assertEquals(7000000.0, (float) $kas->fresh()->saldo);
        $this->assertEquals(1500000.0, (float) $persediaan->fresh()->saldo);

        // Scope withSaldo(): 1 query akun, 0 query tambahan saat baca atribut.
        [$akun, $q, $sql] = $this->rekam(fn () => AkunCOA::withSaldo()->whereIn('kode', ['110-01', '130-01'])->get());

        $this->assertCount(1, $this->sqlYang($sql, 'from "akun_coa"'), 'withSaldo harus 1 query akun');
        $saldo = [];
        foreach ($akun as $a) {
            $saldo[$a->kode] = (float) $a->saldo;
        }
        $this->assertEquals(7000000.0, $saldo['110-01'], 'saldo withSaldo beda dgn accessor');
        $this->assertEquals(1500000.0, $saldo['130-01'], 'saldo withSaldo beda dgn accessor');

        $qBaca = $this->jumlah(function () use ($akun) {
            foreach ($akun as $a) {
                $a->saldo;
            }
        });
        $this->assertSame(0, $qBaca, 'baca saldo dari hasil withSaldo tidak boleh query lagi');
    }
}

/**
 * Adapter channel palsu — hanya untuk menguji guard idempotensi pullOrders
 * tanpa memanggil API Shopee sungguhan.
 */
class FakeChannelAdapter implements ChannelAdapterInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $orders = [];

    public function platform(): string
    {
        return 'shopee';
    }

    public function testConnection(array $kredensial): bool
    {
        return true;
    }

    public function pullOrders(array $kredensial): array
    {
        return $this->orders;
    }

    public function pushStock(array $kredensial, array $items): bool
    {
        return true;
    }

    public function pushPrice(array $kredensial, array $items): bool
    {
        return true;
    }

    public function fetchProducts(array $kredensial): array
    {
        return [];
    }

    public function mapProduct(array $channelProduct): array
    {
        return [];
    }
}
