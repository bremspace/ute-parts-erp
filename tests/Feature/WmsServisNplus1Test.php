<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Jobs\DepresiasiAsetJob;
use App\Modules\Akunting\Livewire\AsetRegister;
use App\Modules\Akunting\Models\AsetTetap;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\DepresiasiService;
use App\Modules\Crm\Livewire\LeadKanban;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Marketplace\Livewire\CustomerAccount;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Livewire\ServisBoard;
use App\Modules\Servis\Models\ServisSparepart;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Livewire\GrnTab;
use App\Modules\Wms\Livewire\OpnameTab;
use App\Modules\Wms\Livewire\PoTab;
use App\Modules\Wms\Livewire\TransferTab;
use App\Modules\Wms\Models\Grn;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokTransfer;
use App\Modules\Wms\Models\StokTransferItem;
use App\Modules\Wms\Models\Supplier;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * B-15c — anti N+1 zona Backoffice WMS + Servis (+ depresiasi).
 *
 * Mirip B-15a: (1) budget query, (2) guard bentuk-SQL yang dieksekusi >1x
 * (`dupeShapes()` → `assertSame([], $dupes)`), (3) paritas angka — setiap nilai
 * dari implementasi baru dibandingkan dengan implementasi LAMA (query per-item /
 * select *) dan dengan definisi fixture.
 *
 * P0 servis: `foto_unit` (JSON base64, 431.406 byte/baris terukur di db_staging)
 * tidak boleh ikut ter-fetch oleh query kanban — 100 tiket ≈ 43MB per render di
 * server 1GB. Foto tetap tampil di modal detail (query terpisah `select *`).
 */
class WmsServisNplus1Test extends TestCase
{
    use RefreshDatabase;

    /** Budget query per render (lihat CHANGELOG B-15c). */
    private const BUDGET_KANBAN = 18;

    private const BUDGET_TRANSFER_MODAL = 30;

    private const BUDGET_TRANSFER_LIST = 14;

    private const BUDGET_OPNAME = 8;

    private const BUDGET_LEAD = 8;

    private const BUDGET_AKUN_PELANGGAN = 8;

    /** Ukuran payload foto kanban (base64 ~400KB/baris). */
    private const UKURAN_FOTO = 400_000;

    private const TIKET_BERFOTO = 30;

    private Cabang $cabang;

    private Cabang $cabangLain;

    private User $user;

    private Gudang $gudangAsal;

    private Gudang $gudangTujuan;

    /** @var array<int, Produk> */
    private array $produk = [];

    /** Snapshot query mentah terakhir (diperlukan untuk assert kolom SELECT). */
    private array $rawQueriesSnapshot = [];

    /** Snapshot bentuk SQL terakhir (normalisasi literal/placeholder). */
    private array $shapesSnapshot = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AkunCoaSeeder::class);

        $this->cabang = Cabang::create(['nama' => 'B15c Pusat', 'kode' => 'CBG-B15C', 'is_active' => true]);
        $this->cabangLain = Cabang::create(['nama' => 'B15c Lain', 'kode' => 'CBG-B15CX', 'is_active' => true]);
        session(['cabang_id' => $this->cabang->id]);

        $this->gudangAsal = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang Asal', 'kode' => 'GDG-ASAL', 'is_active' => true,
        ]);
        $this->gudangTujuan = Gudang::create([
            'cabang_id' => $this->cabang->id, 'nama' => 'Gudang Tujuan', 'kode' => 'GDG-TUJU', 'is_active' => true,
        ]);
        Gudang::create([
            'cabang_id' => $this->cabangLain->id, 'nama' => 'Gudang Cabang Lain', 'kode' => 'GDG-LAIN', 'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'Admin B15c', 'email' => 'admin-b15c@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $this->user->assignRole('super-admin');
        $this->user->cabangs()->attach([$this->cabang->id, $this->cabangLain->id]);
        $this->actingAs($this->user, 'web');
    }

    // ===== Helper query-log (pola B-15a) =====

    private function startQueryLog(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
    }

    private function stopQueryLog(): int
    {
        $log = DB::getQueryLog();
        $this->rawQueriesSnapshot = collect($log)->map(fn (array $q) => (string) $q['query'])->all();
        $this->shapesSnapshot = collect($log)->map(fn (array $q) => $this->sqlShape((string) $q['query']))->all();
        DB::disableQueryLog();
        DB::flushQueryLog();

        return count($log);
    }

    /** @return array<int, string> */
    private function queryShapes(): array
    {
        return $this->shapesSnapshot;
    }

    private function sqlShape(string $sql): string
    {
        $sql = preg_replace("/'([^']|'')*'/", '?', $sql);
        $sql = preg_replace('/\b\d+(\.\d+)?\b/', '?', $sql);
        $sql = preg_replace('/\?(\s*,\s*\?)+/', '?', $sql);

        return strtolower(trim((string) preg_replace('/\s+/', ' ', $sql)));
    }

    /**
     * Bentuk SQL yang dieksekusi >1 kali tapi TIDAK skalabel dengan jumlah baris
     * (konstan per render) → bukan N+1, dikecualikan dari guard duplikat.
     */
    private const SHAPE_BUKAN_N_PLUS_ONE = [
        // ServisBoard::render → `User::role(['teknisi','admin-toko','super-admin'])`:
        // 3 lookup nama role di Spatie, selalu 3 per render (bukan per tiket).
        'select * from "roles" where "name" = ? and "guard_name" = ? limit ?',
    ];

    /**
     * Bentuk SQL yang dieksekusi lebih dari $max kali (= N+1).
     *
     * @return array<string, int>
     */
    private function dupeShapes(int $max = 1): array
    {
        $counts = array_count_values($this->shapesSnapshot);
        $dupes = array_filter($counts, fn (int $n) => $n > $max);
        foreach (self::SHAPE_BUKAN_N_PLUS_ONE as $shape) {
            unset($dupes[$shape]);
        }
        ksort($dupes);

        return $dupes;
    }

    /** Query mentah (belum dinormalisasi) — untuk assert kolom SELECT. */
    private function rawQueries(): array
    {
        return $this->rawQueriesSnapshot;
    }

    // ===== Fixture =====

    private function buatProduk(string $nama, ?Gudang $stokDi = null, int $jumlah = 0): Produk
    {
        $produk = Produk::create([
            'nama' => $nama,
            'slug' => str($nama)->slug()->value(),
            'kategori' => 'LCD',
            'kondisi' => 'baru',
            'harga_beli' => 100000,
            'harga_jual_retail' => 150000,
            'is_active' => true,
        ]);

        if ($stokDi) {
            StokItem::create([
                'produk_id' => $produk->id,
                'gudang_id' => $stokDi->id,
                'jumlah' => $jumlah,
                'jumlah_minimum' => 1,
            ]);
        }

        return $produk;
    }

    /** Baris `foto_unit` selebar yang terukur di db_staging (> sort_buffer 256KB). */
    private function fotoLebar(): array
    {
        return ['data:image/jpeg;base64,'.str_repeat('A', self::UKURAN_FOTO)];
    }

    private function buatTiket(int $i, array $overrides = []): TiketServis
    {
        $tiket = TiketServis::create(array_merge([
            'no_tiket' => sprintf('SRV-B15C-%04d', $i),
            'cabang_id' => $this->cabang->id,
            'nama_pelanggan' => 'Pelanggan '.$i,
            'telepon_pelanggan' => '08120000'.sprintf('%02d', $i),
            'jenis_hp' => 'Samsung A52',
            'keluhan' => 'Layar retak nomor '.$i,
            'status' => 'diterima',
            'tanggal_terima' => now(),
        ], $overrides));

        if (isset($overrides['created_at'])) {
            $tiket->created_at = $overrides['created_at'];
            $tiket->save();
        }

        return $tiket;
    }

    // ================================================================
    // [P0] Kanban servis — foto_unit TIDAK ikut ter-fetch
    // ================================================================

    public function test_kanban_tidak_menyertakan_foto_unit_di_select(): void
    {
        $this->buatTiketBerfoto(self::TIKET_BERFOTO);

        $this->startQueryLog();
        Livewire::test(ServisBoard::class)->assertOk();
        $this->stopQueryLog();
        $queries = $this->rawQueries();

        // Query hydrate kanban (tahap-2): `whereIn(id)` + `withCount(spareparts)` digabung
        // dalam 1 query oleh Eloquent → kolom 'sparepart' muncul di subquery COUNT.
        $queryKanban = array_values(array_filter(
            $queries,
            fn (string $q) => str_contains($q, 'from "tiket_servis" where "id" in (')
        ));

        $this->assertCount(1, $queryKanban, 'Harus ada tepat 1 query hydrate kanban (tahap-2 B-07): '.print_r($queries, true));

        // withCount spareparts harus tetap hidup (badge kartu).
        $this->assertStringContainsString(
            'as "spareparts_count"',
            $queryKanban[0],
            'withCount spareparts tetap harus ada (badge kartu)'
        );

        // B-07 tahap-1 (ORDER BY) tetap hanya menyentuh kolom `id`.
        foreach ($queries as $q) {
            if (str_contains($q, 'tiket_servis') && str_contains($q, 'order by "created_at"')) {
                $this->assertMatchesRegularExpression(
                    '/^select\s+"id"\s+from\s+"tiket_servis"/',
                    trim($q),
                    'Query B-07 tahap-1 hanya boleh menyentuh kolom `id`: '.substr($q, 0, 160)
                );
            }
        }

        foreach ($queryKanban as $q) {
            $this->assertStringNotContainsString(
                'foto_unit',
                $q,
                'Query kanban TIDAK boleh memilih kolom foto_unit (431KB/baris): '.substr($q, 0, 200)
            );
        }
    }

    public function test_kanban_data_pesan_tidak_membawa_foto_unit(): void
    {
        $this->buatTiketBerfoto(self::TIKET_BERFOTO);

        $grouped = Livewire::test(ServisBoard::class)->viewData('groupedTikets');

        $baris = collect($grouped)->flatten();
        $this->assertCount(self::TIKET_BERFOTO, $baris, 'Semua tiket berfoto harus tetap tampil di board');

        foreach ($baris as $tiket) {
            $this->assertNull($tiket->foto_unit, "Tiket {$tiket->no_tiket} tidak boleh memuat foto_unit");
        }

        $ukuran = strlen(json_encode($grouped));
        $payloadFoto = self::TIKET_BERFOTO * self::UKURAN_FOTO;

        $this->assertLessThan(
            300_000,
            $ukuran,
            'Payload board harus < 300KB (payload foto yang dihindari ≈ '.round($payloadFoto / 1_048_576, 1)."MB), aktual {$ukuran} byte"
        );
    }

    public function test_peak_memory_kanban_tidak_meledak_walau_tiket_berfoto_lebar(): void
    {
        $this->buatTiketBerfoto(self::TIKET_BERFOTO);

        gc_collect_cycles();
        memory_reset_peak_usage();
        $baseline = memory_get_usage(true);

        Livewire::test(ServisBoard::class)->assertOk();

        $growth = memory_get_peak_usage(true) - $baseline;

        // Tanpa fix: 30 foto ± 400KB ter-fetch + di-json_decode → +12MB (payload) dan
        // +12MB (array hasil cast) = ±20MB. Dengan fix, growth terukur ~4MB (render
        // Livewire + layout backoffice), jadi ambang 8MB tetap membedakan jauh.
        $this->assertLessThan(
            8 * 1_048_576,
            $growth,
            'Pertumbuhan memory render kanban harus jauh di bawah payload foto (~12MB), aktual '.round($growth / 1_048_576, 2).'MB'
        );
    }

    public function test_detail_tiket_yang_dibuka_tetap_menampilkan_foto(): void
    {
        $foto = ['data:image/jpeg;base64,FOTOB15C', 'data:image/jpeg;base64,FOTOKEDUA'];
        $tiket = $this->buatTiket(1, ['foto_unit' => $foto, 'status' => 'dikerjakan']);

        $component = Livewire::test(ServisBoard::class)->call('openDetail', $tiket->id);

        $selected = $component->viewData('selectedTiket');
        $this->assertNotNull($selected);
        $this->assertSame($foto, $selected->foto_unit, 'Detail tiket wajib tetap membawa foto_unit');

        $component->assertSee('data:image/jpeg;base64,FOTOB15C', false)
            ->assertSee('data:image/jpeg;base64,FOTOKEDUA', false);
    }

    public function test_kanban_query_budget_dan_tanpa_n_plus_one(): void
    {
        $this->buatTiketBerfoto(5);

        // Warm Spatie permission cache (first render loads role/permission tables).
        Livewire::test(ServisBoard::class)->assertOk();

        $this->startQueryLog();
        Livewire::test(ServisBoard::class)->assertOk();
        $jumlah5 = $this->stopQueryLog();

        // Properti anti-N+1: jumlah query TIDAK boleh ikut jumlah tiket.
        $this->buatTiketBerfoto(30, 6);
        $this->startQueryLog();
        Livewire::test(ServisBoard::class)->assertOk();
        $jumlah30 = $this->stopQueryLog();

        $this->assertSame(
            $jumlah5,
            $jumlah30,
            "Jumlah query kanban harus konstan terhadap jumlah tiket (5 tik: {$jumlah5}, 30 tik: {$jumlah30})"
        );
        $this->assertLessThanOrEqual(
            self::BUDGET_KANBAN,
            $jumlah30,
            'Kanban harus ≤ '.self::BUDGET_KANBAN.' query, aktual '.$jumlah30
        );

        $this->startQueryLog();
        Livewire::test(ServisBoard::class)->assertOk();
        $dupes = $this->dupeShapes();
        $this->stopQueryLog();

        // Spatie `User::role([...])` melakukan 1 lookup per nama role — bukan N+1 data,
        // melainkan perilaku internal Spatie. Exclude dari assertion duplikat.
        unset($dupes[$this->sqlShape('select * from "roles" where "name" = ? and "guard_name" = ? limit 1')]);

        $this->assertSame([], $dupes, "Query dieksekusi >1x (N+1) di kanban:\n".implode("\n", array_keys($dupes)));
    }

    public function test_kanban_urutan_dan_angka_identik_dengan_select_penuh(): void
    {
        $this->buatTiketBerfoto(6);

        // Implementasi LAMA persis: select * + eager load + withCount.
        $lama = TiketServis::with(['jenisServis', 'pelanggan', 'teknisi', 'garansi'])
            ->withCount('spareparts')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TiketServis $t) => [
                'id' => $t->id,
                'no_tiket' => $t->no_tiket,
                'status' => $t->status,
                'jenis_hp' => $t->jenis_hp,
                'nama_pelanggan' => $t->nama_pelanggan,
                'keluhan' => $t->keluhan,
                'estimasi_biaya' => (float) $t->estimasi_biaya,
                'spareparts_count' => (int) $t->spareparts_count,
            ])->all();

        $component = Livewire::test(ServisBoard::class);
        $baru = collect($component->viewData('groupedTikets'))->flatten()
            ->map(fn (TiketServis $t) => [
                'id' => $t->id,
                'no_tiket' => $t->no_tiket,
                'status' => $t->status,
                'jenis_hp' => $t->jenis_hp,
                'nama_pelanggan' => $t->nama_pelanggan,
                'keluhan' => $t->keluhan,
                'estimasi_biaya' => (float) $t->estimasi_biaya,
                'spareparts_count' => (int) $t->spareparts_count,
            ])->all();

        $this->assertSame($lama, $baru, 'Kartu kanban (nilai/urutan) berubah setelah select eksplisit');
    }

    public function test_kanban_spareparts_count_dan_filter_tetap_berfungsi(): void
    {
        $adaSparepart = $this->buatTiketBerfoto(1)->first();
        $diagnosa = $this->buatTiket(2, ['status' => 'diagnosa']);
        $produk = $this->buatProduk('LCD B15c', $this->gudangAsal, 10);
        ServisSparepart::create([
            'tiket_servis_id' => $adaSparepart->id,
            'produk_id' => $produk->id,
            'gudang_id' => $this->gudangAsal->id,
            'jumlah' => 2,
            'harga_satuan' => 150000,
            'hpp' => 100000,
        ]);

        $component = Livewire::test(ServisBoard::class);
        $diterima = $component->viewData('groupedTikets')['diterima'];
        $this->assertSame(1, (int) $diterima->firstWhere('id', $adaSparepart->id)->spareparts_count);

        $filtered = Livewire::test(ServisBoard::class)->set('filterStatus', 'diagnosa');
        $this->assertCount(0, $filtered->viewData('groupedTikets')['diterima']);
        $this->assertSame(
            [$diagnosa->id],
            $filtered->viewData('groupedTikets')['diagnosa']->pluck('id')->all()
        );
    }

    private function buatTiketBerfoto(int $jumlah, int $mulai = 1): Collection
    {
        $out = collect();
        for ($i = $mulai; $i < $mulai + $jumlah; $i++) {
            $out->push($this->buatTiket($i, [
                'foto_unit' => $this->fotoLebar(),
                'created_at' => now()->subMinutes($jumlah - ($i - $mulai)),
            ]));
        }

        return $out;
    }

    // ================================================================
    // [P1] Transfer antar gudang — 1 query stok untuk semua baris draft
    // ================================================================

    /**
     * Render komponen TransferTab SATU kali tanpa lifecycle Livewire → jumlah query
     * bisa dihitung persis (tiap `set()`/`call()` di `Livewire::test()` memicu
     * render tambahan yang akan mengaburkan hitungan).
     */
    private function renderTransferSekali(array $items, bool $modal = true): array
    {
        $component = new TransferTab;
        $component->showTransferModal = $modal;
        $component->transferGudangAsalId = $this->gudangAsal->id;
        $component->transferGudangTujuanId = $modal ? $this->gudangTujuan->id : null;
        $component->transferItems = $items;

        return [$component, $component->render()];
    }

    public function test_transfer_stok_rows_satu_query_stok(): void
    {
        $p1 = $this->buatProduk('LCD A', $this->gudangAsal, 10);
        $p2 = $this->buatProduk('LCD B', $this->gudangAsal, 4);
        $p3 = $this->buatProduk('Baterai', $this->gudangAsal, 2);

        // Stok tujuan supaya kolom estimasi terisi.
        StokItem::create([
            'produk_id' => $p1->id, 'gudang_id' => $this->gudangTujuan->id, 'jumlah' => 3, 'jumlah_minimum' => 1,
        ]);

        $this->startQueryLog();
        [, $view] = $this->renderTransferSekali([
            ['produk_id' => $p1->id, 'sku_variant_id' => null, 'rak_id' => null, 'jumlah' => 5],
            ['produk_id' => $p2->id, 'sku_variant_id' => null, 'rak_id' => null, 'jumlah' => 1],
            ['produk_id' => $p3->id, 'sku_variant_id' => null, 'rak_id' => null, 'jumlah' => 2],
        ]);
        $jumlah = $this->stopQueryLog();
        $rows = $view->getData()['transferStokRows'];

        $stokQueries = array_values(array_filter(
            $this->queryShapes(),
            fn (string $q) => str_contains($q, 'from "stok_items" where "produk_id" in (')
        ));

        $this->assertLessThanOrEqual(
            self::BUDGET_TRANSFER_MODAL,
            $jumlah,
            'Render form transfer harus ≤ '.self::BUDGET_TRANSFER_MODAL.' query, aktual '.$jumlah
        );

        $this->assertCount(
            1,
            $stokQueries,
            "Info stok form transfer harus 1 query batch (sebelumnya 2 per baris = 6):\n".implode("\n", $stokQueries)
        );

        // 3 baris → 3 indeks terisi, angka masuk akal.
        $this->assertCount(3, $rows);
        $this->assertSame(10, $rows[0]['stok_sumber']);
        $this->assertSame(3, $rows[0]['stok_tujuan']);
    }

    public function test_transfer_stok_rows_identik_dengan_query_per_baris(): void
    {
        $p1 = $this->buatProduk('LCD A', $this->gudangAsal, 10);
        $p2 = $this->buatProduk('LCD B', $this->gudangAsal, 4);
        $p3 = $this->buatProduk('Baterai', $this->gudangAsal, 2);

        StokItem::create([
            'produk_id' => $p1->id, 'gudang_id' => $this->gudangTujuan->id, 'jumlah' => 3, 'jumlah_minimum' => 1,
        ]);
        StokItem::create([
            'produk_id' => $p3->id, 'gudang_id' => $this->gudangTujuan->id, 'jumlah' => 7, 'jumlah_minimum' => 1,
        ]);

        // Draft transfer lain mengunci 2 unit produk A di gudang asal (T-13).
        $pending = StokTransfer::create([
            'no_transfer' => 'TRF-B15C-PENDING',
            'gudang_asal_id' => $this->gudangAsal->id,
            'gudang_tujuan_id' => $this->gudangTujuan->id,
            'user_pengirim_id' => $this->user->id,
            'status' => 'draft',
        ]);
        StokTransferItem::create([
            'stok_transfer_id' => $pending->id,
            'produk_id' => $p1->id,
            'jumlah' => 2,
            'created_by' => $this->user->id,
        ]);

        $items = [
            ['produk_id' => $p1->id, 'sku_variant_id' => null, 'rak_id' => null, 'jumlah' => 5],
            ['produk_id' => $p2->id, 'sku_variant_id' => null, 'rak_id' => null, 'jumlah' => 1],
            ['produk_id' => $p3->id, 'sku_variant_id' => null, 'rak_id' => null, 'jumlah' => 2],
        ];

        // Acuan: implementasi LAMA (1-2 query stok_items per baris).
        $locked = StokTransfer::pendingLockedByGudang($this->gudangAsal->id);
        $lama = [];
        foreach ($items as $idx => $row) {
            $key = $row['produk_id'].':'.($row['sku_variant_id'] ?? 'null');
            $sumber = StokItem::where('gudang_id', $this->gudangAsal->id)
                ->where('produk_id', $row['produk_id'])->where('sku_variant_id', $row['sku_variant_id'])->first();
            $tujuan = StokItem::where('gudang_id', $this->gudangTujuan->id)
                ->where('produk_id', $row['produk_id'])->where('sku_variant_id', $row['sku_variant_id'])->first();

            $lama[$idx] = [
                'stok_sumber' => $sumber?->jumlah ?? 0,
                'stok_dikunci' => $locked[$key] ?? 0,
                'stok_tersedia' => max(0, ($sumber?->jumlah ?? 0) - ($locked[$key] ?? 0)),
                'stok_tujuan' => $tujuan?->jumlah ?? 0,
                'estimasi_tujuan' => ($tujuan?->jumlah ?? 0) + $row['jumlah'],
            ];
        }

        [$component] = $this->renderTransferSekali($items);
        $baru = $component->render()->getData()['transferStokRows'];

        $this->assertSame($lama, $baru, 'Info stok per baris transfer berubah setelah query batch');

        // Cross-check angka fixture (bukan cuma "sama dengan dirinya sendiri").
        $this->assertSame(10, $baru[0]['stok_sumber']);
        $this->assertSame(2, $baru[0]['stok_dikunci']);
        $this->assertSame(8, $baru[0]['stok_tersedia']);
        $this->assertSame(3, $baru[0]['stok_tujuan']);
        $this->assertSame(8, $baru[0]['estimasi_tujuan']);
        $this->assertSame(0, $baru[1]['stok_dikunci']);
        $this->assertSame(9, $baru[2]['estimasi_tujuan']); // 7 + 2
    }

    public function test_transfer_stok_rows_tanpa_gudang_tujuan_tetap_aman(): void
    {
        $p1 = $this->buatProduk('LCD A', $this->gudangAsal, 6);

        [, $view] = $this->renderTransferSekali([
            ['produk_id' => $p1->id, 'sku_variant_id' => null, 'rak_id' => null, 'jumlah' => 2],
        ]);

        $row = $view->getData()['transferStokRows'][0];
        $this->assertSame(6, $row['stok_sumber']);
        $this->assertSame(0, $row['stok_tujuan']);
        $this->assertSame(2, $row['estimasi_tujuan']);
    }

    public function test_save_transfer_validasi_batch_dengan_pesan_yang_sama(): void
    {
        $p1 = $this->buatProduk('LCD A', $this->gudangAsal, 10);
        $p2 = $this->buatProduk('LCD B', $this->gudangAsal, 1);
        $p3 = $this->buatProduk('Baterai', $this->gudangAsal, 0);

        [$component] = $this->renderTransferSekali([
            ['produk_id' => $p1->id, 'sku_variant_id' => null, 'rak_id' => null, 'jumlah' => 1],
            ['produk_id' => $p2->id, 'sku_variant_id' => null, 'rak_id' => null, 'jumlah' => 5],
            ['produk_id' => $p3->id, 'sku_variant_id' => null, 'rak_id' => null, 'jumlah' => 1],
        ]);

        $this->startQueryLog();
        $component->saveTransfer();
        $this->stopQueryLog();

        $stokQueries = array_values(array_filter(
            $this->queryShapes(),
            fn (string $q) => str_contains($q, 'from "stok_items" where "produk_id" in (')
        ));

        $this->assertCount(1, $stokQueries, "Validasi save transfer harus 1 query batch:\n".implode("\n", $stokQueries));

        // Pesan error Indonesia per baris tetap sama (paritas).
        $errors = $component->getErrorBag()->toArray();
        $this->assertArrayHasKey('transferItems.1.jumlah', $errors);
        $this->assertArrayHasKey('transferItems.2.jumlah', $errors);
        $this->assertStringContainsString(
            'Qty melebihi stok tersedia di gudang sumber (tersedia: 1 unit)',
            $errors['transferItems.1.jumlah'][0]
        );
        $this->assertDatabaseCount('stok_transfer', 0);
    }

    public function test_transfer_daftar_tanpa_modal_tidak_query_stok_per_baris(): void
    {
        $this->buatProduk('LCD A', $this->gudangAsal, 10);

        $this->startQueryLog();
        Livewire::test(TransferTab::class)->assertOk();
        $jumlah = $this->stopQueryLog();

        $this->assertLessThanOrEqual(
            self::BUDGET_TRANSFER_LIST,
            $jumlah,
            'Tab transfer (modal tertutup) harus ≤ '.self::BUDGET_TRANSFER_LIST.' query, aktual '.$jumlah
        );
    }

    public function test_dropdown_transfer_scoped_cabang_dan_produk_terbatas(): void
    {
        // Produk stok cabang aktif (boleh), produk tanpa stok (kandidat restock),
        // produk HANYA di gudang cabang lain (harus disembunyikan).
        $pAktif = $this->buatProduk('LCD Aktif', $this->gudangAsal, 5);
        $pBaru = $this->buatProduk('Produk Baru');
        Gudang::where('kode', 'GDG-LAIN')->first();
        $pLain = $this->buatProduk('LCD Cabang Lain', Gudang::where('kode', 'GDG-LAIN')->first(), 7);

        $component = Livewire::test(TransferTab::class)->assertOk();

        $gudangs = $component->viewData('gudangs');
        $produk = $component->viewData('allProducts');

        $this->assertSame(
            [$this->gudangAsal->id, $this->gudangTujuan->id],
            $gudangs->pluck('id')->sort()->values()->all(),
            'Dropdown gudang hanya boleh berisi gudang cabang aktif'
        );

        $ids = $produk->pluck('id');
        $this->assertTrue($ids->contains($pAktif->id), 'Produk berstok di cabang aktif harus tetap bisa dipilih');
        $this->assertTrue($ids->contains($pBaru->id), 'Produk baru (belum ada stok) tidak boleh hilang dari dropdown');
        $this->assertFalse($ids->contains($pLain->id), 'Produk milik gudang cabang lain TIDAK boleh bocor');
        $this->assertLessThanOrEqual(TransferTab::PRODUK_DROPDOWN_LIMIT, $ids->count());
    }

    public function test_dropdown_produk_terpotong_memberi_pesan_indonesia(): void
    {
        // 1 produk di luar limit → total = limit + 1 → toast wajib keluar.
        Produk::query()->delete();
        for ($i = 0; $i <= TransferTab::PRODUK_DROPDOWN_LIMIT; $i++) {
            $this->buatProduk('Produk Massal '.$i);
        }

        Livewire::test(TransferTab::class)
            ->call('openNewTransferModal')
            ->assertDispatched('alert', fn ($nama, $params) => ($params[0]['type'] ?? null) === 'info'
                && str_contains($params[0]['message'] ?? '', 'dibatasi')
                && str_contains($params[0]['message'] ?? '', 'Produk'));
    }

    // ================================================================
    // [P1] API [WMS-02] storeTransfer — validasi batch
    // ================================================================

    public function test_api_store_transfer_validasi_stok_satu_query(): void
    {
        $p1 = $this->buatProduk('LCD A', $this->gudangAsal, 10);
        $p2 = $this->buatProduk('LCD B', $this->gudangAsal, 2);
        $p3 = $this->buatProduk('Baterai', $this->gudangAsal, 3);

        $payload = [
            'gudang_asal_id' => $this->gudangAsal->id,
            'gudang_tujuan_id' => $this->gudangTujuan->id,
            'items' => [
                ['produk_id' => $p1->id, 'jumlah' => 4],
                ['produk_id' => $p2->id, 'jumlah' => 1],
                ['produk_id' => $p3->id, 'jumlah' => 1],
            ],
        ];

        $this->startQueryLog();
        $this->postJson('/api/wms/transfer', $payload)->assertCreated();
        $this->stopQueryLog();

        $stokQueries = array_values(array_filter($this->queryShapes(), fn (string $q) => str_contains($q, 'from "stok_items"')));
        $this->assertCount(1, $stokQueries, "Validasi [WMS-02] harus 1 query batch:\n".implode("\n", $stokQueries));

        $this->assertDatabaseHas('stok_transfer', ['gudang_asal_id' => $this->gudangAsal->id, 'status' => 'draft']);
    }

    public function test_api_store_transfer_tolak_qty_kurang_dengan_pesan_sama(): void
    {
        $p1 = $this->buatProduk('LCD A', $this->gudangAsal, 10);
        $p2 = $this->buatProduk('LCD B', $this->gudangAsal, 1);

        $response = $this->postJson('/api/wms/transfer', [
            'gudang_asal_id' => $this->gudangAsal->id,
            'gudang_tujuan_id' => $this->gudangTujuan->id,
            'items' => [
                ['produk_id' => $p1->id, 'jumlah' => 2],
                ['produk_id' => $p2->id, 'jumlah' => 9],
            ],
        ])->assertStatus(422);

        $this->assertStringContainsString(
            'Qty melebihi stok tersedia di gudang sumber (tersedia: 1 unit) untuk produk ID '.$p2->id,
            $response->json('message')
        );
        $this->assertDatabaseCount('stok_transfer', 0);
    }

    public function test_api_store_transfer_menghormati_stok_terkunci_draft(): void
    {
        $p1 = $this->buatProduk('LCD A', $this->gudangAsal, 10);

        $pending = StokTransfer::create([
            'no_transfer' => 'TRF-B15C-LOCK',
            'gudang_asal_id' => $this->gudangAsal->id,
            'gudang_tujuan_id' => $this->gudangTujuan->id,
            'user_pengirim_id' => $this->user->id,
            'status' => 'draft',
        ]);
        StokTransferItem::create([
            'stok_transfer_id' => $pending->id,
            'produk_id' => $p1->id,
            'jumlah' => 4,
            'created_by' => $this->user->id,
        ]);

        // Tersedia = 10 - 4 terkunci = 6 → qty 7 harus ditolak.
        $this->postJson('/api/wms/transfer', [
            'gudang_asal_id' => $this->gudangAsal->id,
            'gudang_tujuan_id' => $this->gudangTujuan->id,
            'items' => [['produk_id' => $p1->id, 'jumlah' => 7]],
        ])->assertStatus(422);

        $this->postJson('/api/wms/transfer', [
            'gudang_asal_id' => $this->gudangAsal->id,
            'gudang_tujuan_id' => $this->gudangTujuan->id,
            'items' => [['produk_id' => $p1->id, 'jumlah' => 6]],
        ])->assertCreated();
    }

    // ================================================================
    // [P1] Stock opname — withCount, bukan eager load item
    // ================================================================

    public function test_rekap_opname_memakai_jumlah_item_tanpa_menarik_seluruh_item(): void
    {
        $produk = $this->buatProduk('LCD A', $this->gudangAsal, 5);
        $opname = StokOpname::create([
            'no_opname' => 'OPN-B15C-0001',
            'gudang_id' => $this->gudangAsal->id,
            'user_id' => $this->user->id,
            'status' => 'menunggu_approval',
        ]);
        for ($i = 0; $i < 25; $i++) {
            $opname->items()->create([
                'produk_id' => $produk->id,
                'stok_sistem' => 5,
                'stok_fisik' => 4,
                'selisih' => -1,
            ]);
        }

        $this->startQueryLog();
        $component = Livewire::test(OpnameTab::class);
        $jumlah = $this->stopQueryLog();

        $itemQueries = array_values(array_filter(
            $this->rawQueries(),
            fn (string $q) => str_contains($q, 'from "stok_opname_item"') && ! str_contains($q, 'count(')
        ));

        $this->assertLessThanOrEqual(
            self::BUDGET_OPNAME,
            $jumlah,
            'Rekap opname harus ≤ '.self::BUDGET_OPNAME.' query, aktual '.$jumlah
        );
        $this->assertSame(
            [],
            $itemQueries,
            "Rekap opname tidak boleh menarik seluruh baris stok_opname_item:\n".implode("\n", $itemQueries)
        );

        $baris = $component->viewData('opnames')->firstWhere('id', $opname->id);
        $this->assertSame(25, (int) $baris->items_count, 'Jumlah item opname harus sama dengan jumlah baris asli');
        $this->assertSame($opname->items()->count(), (int) $baris->items_count);
    }

    public function test_html_rekap_opname_menampilkan_jumlah_item_yang_benar(): void
    {
        $produk = $this->buatProduk('LCD A', $this->gudangAsal, 5);
        $opname = StokOpname::create([
            'no_opname' => 'OPN-B15C-0002',
            'gudang_id' => $this->gudangAsal->id,
            'user_id' => $this->user->id,
            'status' => 'menunggu_approval',
        ]);
        for ($i = 0; $i < 7; $i++) {
            $opname->items()->create([
                'produk_id' => $produk->id, 'stok_sistem' => 5, 'stok_fisik' => 5, 'selisih' => 0,
            ]);
        }

        Livewire::test(OpnameTab::class)
            ->assertSee('OPN-B15C-0002')
            ->assertSee('7 sparepart');
    }

    // ================================================================
    // [P1] PO siap diterima — batas + pesan Indonesia
    // ================================================================

    public function test_daftar_po_siap_diterima_dibatasi_dengan_pesan_indonesia(): void
    {
        $supplier = Supplier::create(['nama' => 'Supplier B15c', 'termin_hari' => 30]);

        $total = GrnTab::PO_SIAP_DITERIMA_LIMIT + 5;
        for ($i = 1; $i <= $total; $i++) {
            PurchaseOrder::create([
                'no_po' => sprintf('PO-B15C-%04d', $i),
                'supplier_id' => $supplier->id,
                'gudang_tujuan_id' => $this->gudangTujuan->id,
                'status' => 'dikirim',
                'metode_bayar' => 'kredit',
                'total' => 100000,
                'total_dibayar' => 0,
            ]);
        }

        $component = Livewire::test(GrnTab::class);

        $this->assertCount(
            GrnTab::PO_SIAP_DITERIMA_LIMIT,
            $component->viewData('poList'),
            'Daftar PO siap diterima harus dipotong ke batas'
        );
        $component->assertDispatched('alert', fn ($nama, $params) => ($params[0]['type'] ?? null) === 'info'
            && str_contains($params[0]['message'] ?? '', 'dibatasi'));
    }

    public function test_daftar_po_tidak_dipotong_tanpa_pesan(): void
    {
        $supplier = Supplier::create(['nama' => 'Supplier B15c', 'termin_hari' => 30]);
        PurchaseOrder::create([
            'no_po' => 'PO-B15C-SATU',
            'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $this->gudangTujuan->id,
            'status' => 'dikirim',
            'metode_bayar' => 'tunai',
            'total' => 50000,
            'total_dibayar' => 0,
        ]);

        $component = Livewire::test(GrnTab::class);

        $this->assertCount(1, $component->viewData('poList'));
        $component->assertNotDispatched('alert');
    }

    public function test_daftar_grn_tetap_paginasi_dan_scoping_cabang(): void
    {
        $supplier = Supplier::create(['nama' => 'Supplier B15c', 'termin_hari' => 30]);
        $po = PurchaseOrder::create([
            'no_po' => 'PO-B15C-GRN',
            'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $this->gudangTujuan->id,
            'status' => 'diterima',
            'metode_bayar' => 'kredit',
            'total' => 100000,
            'total_dibayar' => 100000,
        ]);
        Grn::create([
            'no_grn' => 'GRN-B15C-0001',
            'po_id' => $po->id,
            'cabang_id' => $this->cabang->id,
            'gudang_id' => $this->gudangTujuan->id,
            'status' => 'terima',
            'total_hpp' => 100000,
            'item_qty_received' => [],
            'user_id' => $this->user->id,
        ]);
        Grn::create([
            'no_grn' => 'GRN-B15C-LAIN',
            'po_id' => $po->id,
            'cabang_id' => $this->cabangLain->id,
            'gudang_id' => Gudang::where('kode', 'GDG-LAIN')->first()->id,
            'status' => 'terima',
            'total_hpp' => 100000,
            'item_qty_received' => [],
            'user_id' => $this->user->id,
        ]);

        $grnList = Livewire::test(GrnTab::class)->viewData('grnList');

        $this->assertSame(['GRN-B15C-0001'], $grnList->pluck('no_grn')->all());
    }

    public function test_dropdown_po_scoped_cabang_dan_produk_terbatas(): void
    {
        $pAktif = $this->buatProduk('LCD Aktif', $this->gudangAsal, 5);
        $pBaru = $this->buatProduk('Produk Baru');
        $pLain = $this->buatProduk('LCD Cabang Lain', Gudang::where('kode', 'GDG-LAIN')->first(), 7);

        $component = Livewire::test(PoTab::class);

        $ids = $component->viewData('allProducts')->pluck('id');
        $this->assertTrue($ids->contains($pAktif->id));
        $this->assertTrue($ids->contains($pBaru->id));
        $this->assertFalse($ids->contains($pLain->id), 'Produk cabang lain tidak boleh bocor ke PO');
        $this->assertLessThanOrEqual(PoTab::PRODUK_DROPDOWN_LIMIT, $ids->count());

        $this->assertSame(
            [$this->gudangAsal->id, $this->gudangTujuan->id],
            $component->viewData('gudangs')->pluck('id')->sort()->values()->all()
        );
    }

    public function test_po_tetap_membuat_dan_menampilkan_po(): void
    {
        $supplier = Supplier::create(['nama' => 'Supplier B15c', 'termin_hari' => 30]);
        $produk = $this->buatProduk('LCD A', $this->gudangTujuan, 5);

        Livewire::test(PoTab::class)
            ->call('openPoModal')
            ->set('poForm.supplier_id', $supplier->id)
            ->set('poForm.gudang_tujuan_id', $this->gudangTujuan->id)
            ->set('poForm.metode_bayar', 'kredit')
            ->set('poForm.items', [[
                'produk_id' => $produk->id, 'sku_variant_id' => null, 'harga_beli' => 90000, 'jumlah' => 2,
            ]])
            ->call('simpanPo')
            ->assertHasNoErrors()
            ->assertDispatched('alert', fn ($nama, $params) => str_contains($params[0]['message'] ?? '', 'dibuat (draft)'));

        $this->assertDatabaseHas('purchase_order', ['supplier_id' => $supplier->id, 'total' => 180000]);
    }

    // ================================================================
    // [P1] Lead pipeline — select sempit, rekap tidak berubah
    // ================================================================

    public function test_kanban_lead_select_sempit_tanpa_mengubah_rekap(): void
    {
        $leads = [];
        foreach ([
            ['baru', 100000, 1],
            ['negosiasi', 250000, 2],
            ['won', 400000, 3],
            ['lost', 150000, 4],
            ['won', 50000, 5],
        ] as [$stage, $nilai, $i]) {
            $leads[] = Lead::create([
                'cabang_id' => $this->cabang->id,
                'nama' => 'Lead '.$i,
                'telepon' => '08130000000'.$i,
                'email' => 'lead'.$i.'@test.com',
                'sumber' => 'walkin',
                'stage' => $stage,
                'nilai_estimasi' => $nilai,
                // `catatan` = text panjang yang tidak pernah dirender di board.
                'catatan' => str_repeat('catatan panjang ', 50),
            ]);
        }
        // Lead cabang lain tidak boleh muncul.
        Lead::create([
            'cabang_id' => $this->cabangLain->id, 'nama' => 'Lead Asing', 'telepon' => '08139999999',
            'sumber' => 'walkin', 'stage' => 'baru', 'nilai_estimasi' => 999999,
        ]);

        // Rekap versi LAMA (select *) — angka acuan.
        $lamaLeads = Lead::forCabang()->orderBy('stage')->orderBy('created_at', 'desc')->get();
        $lama = [
            'total' => $lamaLeads->count(),
            'total_nilai' => round($lamaLeads->sum('nilai_estimasi'), 2),
            'won_nilai' => round($lamaLeads->where('stage', 'won')->sum('nilai_estimasi'), 2),
            'lost_count' => $lamaLeads->where('stage', 'lost')->count(),
            'conversion_rate' => round(($lamaLeads->where('stage', 'won')->count() / $lamaLeads->count()) * 100, 1),
        ];

        $component = Livewire::test(LeadKanban::class);

        $this->startQueryLog();
        Livewire::test(LeadKanban::class);
        $jumlah = $this->stopQueryLog();

        $summary = $component->viewData('summary');
        $this->assertSame($lama['total'], $summary['total']);
        $this->assertSame($lama['total_nilai'], (float) $summary['total_nilai']);
        $this->assertSame($lama['won_nilai'], (float) $summary['won_nilai']);
        $this->assertSame($lama['lost_count'], $summary['lost_count']);
        $this->assertSame($lama['conversion_rate'], (float) $summary['conversion_rate']);

        $baris = collect($component->viewData('kanbanData'))->flatten();
        $this->assertCount(5, $baris, 'Lead cabang lain tidak boleh bocor & tidak boleh ada yang hilang');
        $this->assertNull($baris->first()->catatan, 'Kolom `catatan` tidak dirender di board → tidak perlu di-select');
        $this->assertSame('Lead 1', $baris->firstWhere('id', $leads[0]->id)->nama);

        $this->assertLessThanOrEqual(
            self::BUDGET_LEAD,
            $jumlah,
            'Kanban lead harus ≤ '.self::BUDGET_LEAD.' query, aktual '.$jumlah
        );
    }

    public function test_kanban_lead_filter_tetap_berfungsi(): void
    {
        foreach ([
            ['baru', '081300000001'],
            ['won', '081300000002'],
        ] as [$stage, $telp]) {
            Lead::create([
                'cabang_id' => $this->cabang->id, 'nama' => 'Lead '.$stage, 'telepon' => $telp,
                'sumber' => 'walkin', 'stage' => $stage, 'nilai_estimasi' => 1000,
            ]);
        }

        $component = Livewire::test(LeadKanban::class)->set('filterSumber', 'walkin');
        $baris = collect($component->viewData('kanbanData'))->flatten();
        $this->assertCount(2, $baris);

        $cari = Livewire::test(LeadKanban::class)->set('search', '081300000002');
        $this->assertCount(1, collect($cari->viewData('kanbanData'))->flatten());
    }

    // ================================================================
    // [P1] Akun pelanggan — daftar tiket tanpa foto_unit, jumlah tetap
    // ================================================================

    public function test_daftar_tiket_pelanggan_tetap_lengkap_tanpa_foto_unit(): void
    {
        $pelanggan = $this->pelangganLogin();

        for ($i = 1; $i <= 12; $i++) {
            $this->buatTiket($i, [
                'pelanggan_id' => $pelanggan->id,
                'foto_unit' => $this->fotoLebar(),
            ]);
        }

        $component = Livewire::test(CustomerAccount::class)->set('activeTab', 'servis');
        $servis = $component->instance()->getServisProperty();

        $this->assertCount(12, $servis, 'Jumlah tiket pelanggan tidak boleh berubah (kartu statistik memakai count())');
        $this->assertNull($servis->first()->foto_unit, 'Foto tidak pernah dirender di akun pelanggan');

        $ukuran = strlen(json_encode($servis->toArray()));
        $this->assertLessThan(300_000, $ukuran, "Payload tiket pelanggan harus < 300KB, aktual {$ukuran} byte");

        $this->startQueryLog();
        Livewire::test(CustomerAccount::class)->set('activeTab', 'servis');
        $jumlah = $this->stopQueryLog();

        $this->assertLessThanOrEqual(
            self::BUDGET_AKUN_PELANGGAN,
            $jumlah,
            'Akun pelanggan harus ≤ '.self::BUDGET_AKUN_PELANGGAN.' query, aktual '.$jumlah
        );
    }

    private function pelangganLogin(): Pelanggan
    {
        $tier = TierMembership::create([
            'nama' => 'Tier B15c', 'kode' => 'TIER-B15C', 'diskon_persen' => 0, 'is_active' => true,
        ]);
        $pelanggan = Pelanggan::create([
            'nama' => 'Beli B15c', 'telepon' => '081200009999',
            'tier_membership_id' => $tier->id, 'is_reseller' => false, 'tipe_konsumen' => 'retail',
        ]);

        $this->actingAs($pelanggan, 'customer');

        return $pelanggan;
    }

    // ================================================================
    // [P1] Depresiasi — guard jurnal batch + dispatch (async)
    // ================================================================

    /** @return array<int, AsetTetap> */
    private function buatAset(int $jumlah): array
    {
        $aset = [];
        for ($i = 1; $i <= $jumlah; $i++) {
            $aset[] = AsetTetap::create([
                'cabang_id' => $this->cabang->id,
                'nama' => 'Aset B15c '.$i,
                'kategori' => 'it',
                'harga_perolehan' => 1200000,
                'tanggal_perolehan' => now()->subMonths(2)->toDateString(),
                'umur_bulan' => 24,
                'metode' => 'garis_lurus',
                'status' => 'aktif',
                'user_id' => $this->user->id,
            ]);
        }

        return $aset;
    }

    public function test_guard_jurnal_depresiasi_dibatch_satu_query(): void
    {
        $aset = $this->buatAset(8);

        $this->startQueryLog();
        $hasil = app(DepresiasiService::class)->prosesPeriode(now()->format('Y-m'), $this->cabang->id, $this->user->id);
        $this->stopQueryLog();

        // Guard 2 (cek jurnal existing) harus SATU query `whereIn`, bukan 1 per aset.
        $guardBatch = array_values(array_filter(
            $this->rawQueries(),
            fn (string $q) => str_contains($q, 'jurnal_akuntansi') && str_contains($q, 'no_jurnal')
                && ! str_contains($q, 'exists') && ! str_contains(strtolower($q), 'insert')
        ));
        $guardPerAset = array_values(array_filter(
            $this->queryShapes(),
            fn (string $q) => str_contains($q, 'jurnal_akuntansi') && str_contains($q, 'no_jurnal')
                && str_contains($q, 'exists') && ! str_contains($q, 'insert')
        ));

        $this->assertCount(1, $guardBatch, 'Guard jurnal harus 1 query batch whereIn');
        $this->assertStringContainsString('in (', $guardBatch[0], 'Query batch guard harus memakai whereIn');

        // Sisa query per-aset = guard idempotensi milik `JurnalService::post()` sendiri
        // (1 per jurnal yang benar-benar diposting) — DI LUAR cakupan B-15c dan tetap
        // dijaga. Yang dihilangkan B-15c adalah N+1 `exists()` milik DepresiasiService
        // yang tadinya jalan untuk SEMUA aset (kandidat maupun yang sudah diposting).
        $this->assertCount(
            $hasil['diproses'],
            $guardPerAset,
            "Hanya JurnalService::post() boleh query per-aset (1 per jurnal diposting):\n".implode("\n", $guardPerAset)
        );

        $this->assertSame(8, $hasil['diproses']);
        $this->assertSame(0, $hasil['dilewati']);
        $this->assertSame(8 * 50000.0, (float) $hasil['total']); // 1.2jt / 24 = 50rb
        $this->assertSame(16, JurnalAkuntansi::where('sumber', 'depresiasi')->count());

        foreach ($aset as $a) {
            $this->assertSame(50000.0, (float) $a->fresh()->akumulasi_depresiasi);
        }
    }

    public function test_depresiasi_via_job_idempoten_dijalankan_dua_kali(): void
    {
        Queue::fake();
        $this->buatAset(3);
        $periode = now()->format('Y-m');

        Livewire::test(AsetRegister::class)
            ->set('periodeDepresiasi', $periode)
            ->call('jalankanDepresiasi')
            ->assertHasNoErrors()
            ->assertDispatched('alert', fn ($nama, $params) => ($params[0]['type'] ?? null) === 'success'
                && str_contains($params[0]['message'] ?? '', 'diantre'));

        Queue::assertPushed(DepresiasiAsetJob::class, fn (DepresiasiAsetJob $j) => $j->periode === $periode
            && $j->cabangId === $this->cabang->id);

        // Request tidak boleh menyentuh jurnal.
        $this->assertDatabaseCount('jurnal_akuntansi', 0);

        $pertama = (new DepresiasiAsetJob($periode, $this->cabang->id))
            ->handle(app(DepresiasiService::class), app(NotificationService::class));
        $kedua = (new DepresiasiAsetJob($periode, $this->cabang->id))
            ->handle(app(DepresiasiService::class), app(NotificationService::class));

        $this->assertSame(3, $pertama['diproses']);
        $this->assertSame(0, $kedua['diproses'], 'Jalankan kedua tidak boleh menambah aset terproses');
        $this->assertSame(150000.0, (float) $pertama['total']);
        $this->assertSame(0.0, (float) $kedua['total']);

        $this->assertSame(6, JurnalAkuntansi::where('sumber', 'depresiasi')->count(), 'Jurnal tidak boleh dobel');
        $this->assertSame(50000.0, (float) AsetTetap::first()->fresh()->akumulasi_depresiasi);
    }
}
