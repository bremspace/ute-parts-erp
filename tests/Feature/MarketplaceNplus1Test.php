<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Marketplace\Controllers\ShippingController;
use App\Modules\Marketplace\Livewire\ShopPage;
use App\Modules\Marketplace\Services\BiteshipService;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * B-15a — anti N+1 zona Marketplace (katalog publik, detail produk, tarif kurir).
 *
 * Fixture sengaja BESAR: 20 produk × 5 varian × 3 cabang.
 *
 * 1. Budget query: `assertLessThanOrEqual($budget, count(DB::getQueryLog()))`.
 * 2. Guard lebih tajam: `queryShapes()` menormalisasi query log (literal & angka
 *    di-mask, deretan placeholder di-collapse) lalu menghitung berapa kali tiap
 *    bentuk SQL dieksekusi. Bentuk yang muncul > 1x = N+1 — query agregat
 *    (`whereIn`, `group by`) hanya boleh dieksekusi SATU kali.
 * 3. Correctness: setiap angka hasil implementasi baru dibandingkan dengan
 *    implementasi LAMA (query per produk / per varian / per C×I) dan dengan
 *    hitungan manual dari definisi fixture — nilai & urutan wajib identik.
 */
class MarketplaceNplus1Test extends TestCase
{
    use RefreshDatabase;

    private const PRODUKS = 20;

    private const VARIAN_PER_PRODUK = 5;

    private const CABANGS = 3;

    /** Budget query per halaman (lihat CHANGELOG B-15a). */
    private const BUDGET_KATALOG_GUEST = 8;

    private const BUDGET_KATALOG_LOGIN = 9;

    private const BUDGET_DETAIL = 6;

    private const BUDGET_RESOLVE_ORIGINS = 3;

    private array $produkIds = [];

    private array $varianIds = [];

    private array $cabangIds = [];

    private array $gudangIds = [];

    // ===== Fixture =====

    /**
     * Seed fixture besar. `jumlah` stok deterministik: 1 + (($i + $j + $g) % 7)
     * → stok tiap produk / varian / cabang bisa dihitung manual dari definisi.
     */
    private function seedFixture(): void
    {
        for ($c = 0; $c < self::CABANGS; $c++) {
            $cabang = Cabang::create([
                'nama' => 'Cabang B15a-'.$c,
                'kode' => 'CBG-B15A-'.$c,
                'is_active' => true,
            ]);
            $this->cabangIds[$c] = $cabang->id;

            $gudang = Gudang::create([
                'cabang_id' => $cabang->id,
                'nama' => 'Gudang B15a-'.$c,
                'kode' => 'GDG-B15A-'.$c,
                'is_active' => true,
            ]);
            $this->gudangIds[$c] = $gudang->id;
        }

        $hp = [
            ['merk' => 'Samsung', 'model' => 'Galaxy A50'],
            ['merk' => 'Samsung', 'model' => 'Galaxy S21'],
            ['merk' => 'Xiaomi', 'model' => 'Redmi Note 11'],
            ['merk' => 'Apple', 'model' => 'iPhone 13'],
        ];
        // Tiga kelompok kompatibilitas (bergantian per produk) supaya filter merk
        // benar-benar mempersempit daftar model:
        //   i%3=0 → Samsung (A50 + S21), i%3=1 → Xiaomi, i%3=2 → Apple.
        $hpGroups = [
            [$hp[0], $hp[1]],
            [$hp[2]],
            [$hp[3]],
        ];

        for ($i = 0; $i < self::PRODUKS; $i++) {
            $produk = Produk::create([
                'nama' => 'Part B15a-'.$i,
                'slug' => 'part-b15a-'.$i,
                'kategori' => 'LCD',
                'kondisi' => 'baru',
                'brand_kompatibel' => $i % 2 === 0 ? 'SAMSUNG' : 'XIAOMI',
                'model_kompatibel' => 'M'.$i,
                'harga_beli' => 100000 + $i,
                'harga_jual_retail' => 200000 + $i,
                'kompatibilitas_hp' => $hpGroups[$i % 3],
                'is_active' => true,
            ]);
            $this->produkIds[$i] = $produk->id;

            for ($j = 0; $j < self::VARIAN_PER_PRODUK; $j++) {
                $varian = SkuVariant::create([
                    'produk_id' => $produk->id,
                    'sku' => 'B15A-'.$i.'-'.$j,
                    'nama_varian' => 'Varian '.$j,
                    'harga_jual_retail' => 200000 + $i,
                    'is_active' => true,
                ]);
                $this->varianIds[$i.'-'.$j] = $varian->id;

                for ($g = 0; $g < self::CABANGS; $g++) {
                    StokItem::create([
                        'produk_id' => $produk->id,
                        'sku_variant_id' => $varian->id,
                        'gudang_id' => $this->gudangIds[$g],
                        'jumlah' => $this->jumlah($i, $j, $g),
                        'jumlah_minimum' => 1,
                    ]);
                }
            }
        }
    }

    /** Nilai stok fixture (definisi fixture, bukan hasil query). */
    private function jumlah(int $i, int $j, int $g): int
    {
        return 1 + (($i + $j + $g) % 7);
    }

    /** Total stok manual satu produk dari definisi fixture. */
    private function totalManual(int $i): int
    {
        $total = 0;
        for ($j = 0; $j < self::VARIAN_PER_PRODUK; $j++) {
            for ($g = 0; $g < self::CABANGS; $g++) {
                $total += $this->jumlah($i, $j, $g);
            }
        }

        return $total;
    }

    // ===== Helper query-log =====

    private function startQueryLog(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
    }

    private function stopQueryLog(): int
    {
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }

    /**
     * Bentuk SQL dari query log: literal/angka di-mask jadi `?` dan deretan
     * placeholder di-collapse (`in (?, ?, ?)` → `in (?)`) sehingga per-row
     * (`where x = ?` diulang 18x) dan agregat bisa dibandingkan arity-independent.
     */
    private function queryShapes(): array
    {
        return collect(DB::getQueryLog())
            ->map(fn (array $q) => $this->sqlShape((string) $q['query']))
            ->all();
    }

    private function sqlShape(string $sql): string
    {
        $sql = preg_replace("/'([^']|'')*'/", '?', $sql);
        $sql = preg_replace('/\b\d+(\.\d+)?\b/', '?', $sql);
        $sql = preg_replace('/\?(\s*,\s*\?)+/', '?', $sql);

        return strtolower(trim((string) preg_replace('/\s+/', ' ', $sql)));
    }

    /**
     * Bentuk SQL yang dieksekusi lebih dari $max kali (= N+1).
     *
     * @return array<string, int>
     */
    private function dupeShapes(int $max = 1): array
    {
        $counts = array_count_values($this->queryShapes());
        $dupes = array_filter($counts, fn (int $n) => $n > $max);
        ksort($dupes);

        return $dupes;
    }

    private function pelangganLogin(?TierMembership $tier = null): Pelanggan
    {
        $tier ??= TierMembership::create([
            'nama' => 'Tier B15a',
            'kode' => 'TIER-B15A',
            'diskon_persen' => 0,
            'is_active' => true,
        ]);

        $pelanggan = Pelanggan::create([
            'nama' => 'Beli B15a',
            'telepon' => '081200000015',
            'tier_membership_id' => $tier->id,
            'is_reseller' => false,
            'tipe_konsumen' => 'retail',
        ]);

        $this->actingAs($pelanggan, 'customer');

        return $pelanggan;
    }

    // ================================================================
    // [P0] Katalog publik — budget query
    // ================================================================

    public function test_katalog_guest_query_budget_dan_tanpa_n_plus_one(): void
    {
        $this->seedFixture();

        $this->startQueryLog();
        Livewire::test(ShopPage::class)->assertOk();
        $jumlah = $this->stopQueryLog();

        $this->assertLessThanOrEqual(
            self::BUDGET_KATALOG_GUEST,
            $jumlah,
            'Katalog guest harus ≤ '.self::BUDGET_KATALOG_GUEST." query, aktual {$jumlah}."
        );

        // Render kedua: cache dropdown HP sudah hangat → TIDAK boleh ada query
        // yang bentuknya sama diulang.
        $this->startQueryLog();
        Livewire::test(ShopPage::class)->assertOk();
        $dupes = $this->dupeShapes();
        $this->stopQueryLog();

        $this->assertSame([], $dupes, "Query dieksekusi >1x (N+1) di katalog:\n".implode("\n", array_keys($dupes)));
    }

    public function test_katalog_pelanggan_login_query_budget_dan_tanpa_n_plus_one(): void
    {
        $this->seedFixture();
        $this->pelangganLogin();

        $this->startQueryLog();
        Livewire::test(ShopPage::class)->assertOk();
        $jumlah = $this->stopQueryLog();

        $this->assertLessThanOrEqual(
            self::BUDGET_KATALOG_LOGIN,
            $jumlah,
            'Katalog (pelanggan login) harus ≤ '.self::BUDGET_KATALOG_LOGIN." query, aktual {$jumlah}."
        );

        $this->startQueryLog();
        Livewire::test(ShopPage::class)->assertOk();
        $dupes = $this->dupeShapes();
        $this->stopQueryLog();

        $this->assertSame([], $dupes, "Query dieksekusi >1x (N+1) di katalog:\n".implode("\n", array_keys($dupes)));
    }

    public function test_katalog_tidak_lagi_query_stok_per_produk(): void
    {
        $this->seedFixture();

        $this->startQueryLog();
        Livewire::test(ShopPage::class)->assertOk();
        $shapes = $this->queryShapes();
        $this->stopQueryLog();

        $stokShape = array_values(array_filter(
            $shapes,
            fn (string $q) => str_contains($q, 'stok_items') || str_contains($q, 'harga_tier')
        ));

        $this->assertNotEmpty($stokShape, 'Katalog harus punya query agregat stok/tier');
        foreach ($stokShape as $shape) {
            $this->assertMatchesRegularExpression(
                '/(in \(\?|= \?|sum\()/',
                $shape,
                "Query stok/tier harus agregat (subquery SUM / whereIn), bukan per-row: {$shape}"
            );
        }
    }

    // ===== Katalog: correctness =====

    public function test_stok_per_kartu_sama_dengan_implementasi_lama(): void
    {
        $this->seedFixture();

        $products = (new ShopPage)->getProductsProperty();
        $this->assertCount(18, $products, 'Fixture 20 produk → halaman pertama berisi 18');

        foreach ($products as $p) {
            $lama = (int) StokItem::where('produk_id', $p->id)->sum('jumlah'); // blade versi lama
            $baru = (int) ($p->stok_items_sum_jumlah ?? 0);

            $this->assertSame($lama, $baru, "stok_items_sum_jumlah produk {$p->id} berubah: lama {$lama} → baru {$baru}");
        }

        // Cross-check manual dari definisi fixture.
        foreach ([0, 7, 17] as $i) {
            $p = $products->firstWhere('id', $this->produkIds[$i]);
            $this->assertNotNull($p, "Produk fixture {$i} harus ada di halaman pertama");
            $this->assertSame($this->totalManual($i), (int) $p->stok_items_sum_jumlah, "Stok produk {$i} tidak cocok hitungan manual");
        }
    }

    public function test_html_katalog_menampilkan_stok_yang_benar(): void
    {
        $this->seedFixture();

        $i = 7;
        Livewire::test(ShopPage::class)
            ->assertOk()
            // Blade lama: "N tersedia" per kartu
            ->assertSee($this->totalManual($i).' tersedia')
            // Dropdown merk & model (escape=false: yang dibandingkan atribut HTML)
            ->assertSee('value="Samsung"', false)
            ->assertSee('value="Galaxy A50"', false);
    }

    public function test_harga_tier_pelanggan_login_tetap_sama_dengan_query_lama(): void
    {
        $this->seedFixture();
        $tier = $this->pelangganLogin();
        $pelanggan = Pelanggan::where('telepon', '081200000015')->first();

        // Harga tier: diskon 10% untuk tier tsb pada 3 produk pertama.
        foreach (array_slice($this->produkIds, 0, 3) as $produkId) {
            HargaTier::create([
                'produk_id' => $produkId,
                'tier_membership_id' => $tier->id,
                'tipe_konsumen' => 'retail',
                'persen_diskon' => 10,
                'harga' => 1, // kolom legacy NOT NULL; tidak dipakai jalur persen_diskon
            ]);
        }

        $produkId = $this->produkIds[0];

        // Jalur LAMA: produk tanpa relasi ter-load → resolveHarga query DB sendiri.
        $hargaLama = app(PricingService::class)->resolve(Produk::find($produkId), $pelanggan);
        // Jalur BARU: relasi `hargaTier` ter-load → rowsHargaTier tanpa query.
        $hargaBaru = app(PricingService::class)->resolve(Produk::with('hargaTier')->find($produkId), $pelanggan);

        $this->assertGreaterThan(0, $hargaBaru['diskon_nominal'], 'Fixture harus benar-benar menghasilkan diskon tier');
        $this->assertSame($hargaLama, $hargaBaru, 'Harga tier berubah setelah eager load');
    }

    public function test_harga_katalog_pelanggan_login_tetap_berdiskon(): void
    {
        $this->seedFixture();
        $tier = $this->pelangganLogin();
        $pelanggan = Pelanggan::where('telepon', '081200000015')->first();

        HargaTier::create([
            'produk_id' => $this->produkIds[0],
            'tier_membership_id' => $tier->id,
            'tipe_konsumen' => 'retail',
            'persen_diskon' => 10,
            'harga' => 1, // kolom legacy NOT NULL
        ]);

        $produk = Produk::where('id', $this->produkIds[0])->first();
        $expected = app(PricingService::class)->resolve($produk, $pelanggan);

        $hargaDasar = (float) $produk->harga_jual_retail;
        $hargaHarus = round($hargaDasar - ($hargaDasar * 10 / 100), 2);

        $this->assertSame($expected['harga'], $hargaHarus, 'Harga diskon tier tidak sesuai perhitungan');

        // Halaman katalog (pakai eager load) harus menampilkan harga yang sama.
        Livewire::test(ShopPage::class)
            ->assertOk()
            ->assertSee(number_format($hargaHarus, 0, ',', '.'));
    }

    public function test_daftar_hp_merk_dan_model_identik_dengan_implementasi_lama(): void
    {
        $this->seedFixture();

        // Implementasi LAMA persis (model penuh + cast array).
        $lamaMerk = Produk::where('is_active', true)
            ->whereNotNull('kompatibilitas_hp')
            ->where('kompatibilitas_hp', '!=', '[]')
            ->get()
            ->flatMap(fn ($p) => collect($p->kompatibilitas_hp ?? [])->pluck('merk'))
            ->unique()->sort()->values()->all();

        $lamaModel = Produk::where('is_active', true)
            ->whereNotNull('kompatibilitas_hp')
            ->where('kompatibilitas_hp', '!=', '[]')
            ->get()
            ->flatMap(fn ($p) => collect($p->kompatibilitas_hp ?? [])->pluck('model'))
            ->unique()->sort()->values()->all();

        $component = new ShopPage;
        $baruMerk = $component->getHpMerkListProperty();
        $baruModel = $component->getHpModelListProperty();

        $this->assertSame($lamaMerk, $baruMerk, 'Daftar merk berubah (nilai/urutan)');
        $this->assertSame($lamaModel, $baruModel, 'Daftar model berubah (nilai/urutan)');
        $this->assertSame(['Apple', 'Samsung', 'Xiaomi'], $baruMerk, 'Daftar merk fixture tidak sesuai harapan');
        // Urutan = sort() bawaan lama (banding byte, case-sensitive).
        $this->assertSame(['Galaxy A50', 'Galaxy S21', 'Redmi Note 11', 'iPhone 13'], $baruModel, 'Daftar model fixture tidak sesuai harapan');
        $this->assertTrue(Cache::has('shop.hp.merk-list.v1'), 'Cache daftar merk tidak terbentuk');
        $this->assertTrue(Cache::has('shop.hp.model-list.v1'), 'Cache daftar model tidak terbentuk');
    }

    public function test_daftar_hp_mercache_tidak_query_ulang(): void
    {
        $this->seedFixture();

        // Render pertama mengisi cache.
        Livewire::test(ShopPage::class)->assertOk();

        $this->startQueryLog();
        Livewire::test(ShopPage::class)->assertOk();
        $shapes = $this->queryShapes();
        $this->stopQueryLog();

        $hpQueries = array_values(array_filter(
            $shapes,
            fn (string $q) => str_contains($q, 'kompatibilitas_hp') && ! str_contains($q, 'json_contains') && ! str_contains($q, 'json_search')
        ));

        $this->assertSame(
            [],
            $hpQueries,
            "Dropdown HP harus dilayani cache, tidak query produk:\n".implode("\n", $hpQueries)
        );
    }

    public function test_daftar_hp_mercache_tidak_mengganggu_filter_merk(): void
    {
        $this->seedFixture();
        $this->sqliteJsonContains();

        // Warm cache dulu supaya key tanpa filter & key ber-filter sama-sama ada.
        (new ShopPage)->getHpMerkListProperty();
        (new ShopPage)->getHpModelListProperty();

        $lama = Produk::where('is_active', true)
            ->whereNotNull('kompatibilitas_hp')
            ->where('kompatibilitas_hp', '!=', '[]')
            ->whereRaw('JSON_CONTAINS(kompatibilitas_hp, ?)', [json_encode(['merk' => 'Samsung'])])
            ->get()
            ->flatMap(fn ($p) => collect($p->kompatibilitas_hp ?? [])->pluck('model'))
            ->unique()->sort()->values()->all();

        $component = new ShopPage;
        $component->filterHpMerk = 'Samsung';

        $this->assertSame(['Galaxy A50', 'Galaxy S21'], $lama, 'Fixture model untuk merk Samsung tidak sesuai harapan');
        $this->assertSame($lama, $component->getHpModelListProperty(), 'Daftar model terfilter merk berubah');

        // Tanpa filter tetap daftar penuh (key cache terpisah).
        $tanpaFilter = new ShopPage;
        $this->assertCount(4, $tanpaFilter->getHpModelListProperty(), 'Key cache tanpa filter tertimpa');
    }

    /**
     * SQLite tidak punya JSON_CONTAINS (MySQL-only). Daftarkan UFD agar jalur
     * filter merk bisa diuji di SQLite — implementasi lama & baru memakai
     * whereRaw yang sama, jadi hasilnya tetap dibandingkan apples-to-apples.
     */
    private function sqliteJsonContains(): void
    {
        $pdo = DB::connection()->getPdo();
        if (! method_exists($pdo, 'sqliteCreateFunction')) {
            $this->markTestSkipped('Butuh PDO SQLite untuk mendaftarkan JSON_CONTAINS');
        }

        $pdo->sqliteCreateFunction('JSON_CONTAINS', function ($json, $needle) {
            $items = json_decode((string) $json, true);
            $needle = json_decode((string) $needle, true);
            if (! is_array($items) || ! is_array($needle)) {
                return 0;
            }
            foreach ($items as $item) {
                foreach ($needle as $key => $value) {
                    if (($item[$key] ?? null) === $value) {
                        return 1;
                    }
                }
            }

            return 0;
        }, 2);
    }

    // ================================================================
    // [P0] Detail produk
    // ================================================================

    public function test_detail_produk_query_budget_dan_tanpa_n_plus_one(): void
    {
        $this->seedFixture();

        $this->startQueryLog();
        Livewire::test(ShopPage::class, ['slug' => 'part-b15a-3'])->assertOk();
        $jumlah = $this->stopQueryLog();

        $this->assertLessThanOrEqual(
            self::BUDGET_DETAIL,
            $jumlah,
            'Detail produk harus ≤ '.self::BUDGET_DETAIL." query, aktual {$jumlah}."
        );

        $this->startQueryLog();
        Livewire::test(ShopPage::class, ['slug' => 'part-b15a-3'])->assertOk();
        $dupes = $this->dupeShapes();
        $this->stopQueryLog();

        $this->assertSame([], $dupes, "Query dieksekusi >1x (N+1) di detail:\n".implode("\n", array_keys($dupes)));
    }

    public function test_detail_produk_tidak_query_stok_per_varian(): void
    {
        $this->seedFixture();

        $this->startQueryLog();
        Livewire::test(ShopPage::class, ['slug' => 'part-b15a-3'])->assertOk();
        $shapes = $this->queryShapes();
        $this->stopQueryLog();

        $stokQueries = array_values(array_filter($shapes, fn (string $q) => str_contains($q, 'from "stok_items"') || str_contains($q, 'from stok_items')));

        $this->assertCount(
            1,
            $stokQueries,
            "Detail produk harus 1 query stok (gabungan varian + cabang), aktual:\n".implode("\n", $stokQueries)
        );
    }

    public function test_stok_per_varian_sama_dengan_query_lama_per_varian(): void
    {
        $this->seedFixture();

        $i = 4;
        $component = new ShopPage;
        $component->slug = 'part-b15a-'.$i;

        $perVariant = $component->getStokPerVariantProperty();
        $varianIds = $component->getProductDetailProperty()->skuVariants->pluck('id')->all();
        $this->assertCount(self::VARIAN_PER_PRODUK, $varianIds);

        foreach ($varianIds as $j => $variantId) {
            $lama = (int) StokItem::where('produk_id', $this->produkIds[$i])
                ->where('sku_variant_id', $variantId)
                ->sum('jumlah'); // blade versi lama

            $manual = 0;
            for ($g = 0; $g < self::CABANGS; $g++) {
                $manual += $this->jumlah($i, $j, $g);
            }

            $this->assertSame($lama, (int) ($perVariant[$variantId] ?? 0), "Stok varian {$variantId} berubah");
            $this->assertSame($manual, (int) ($perVariant[$variantId] ?? 0), "Stok varian {$variantId} tidak cocok hitungan manual");
        }
    }

    public function test_html_detail_menampilkan_stok_varian_yang_benar(): void
    {
        $this->seedFixture();

        $i = 4;
        $stokVarian0 = 0;
        for ($g = 0; $g < self::CABANGS; $g++) {
            $stokVarian0 += $this->jumlah($i, 0, $g);
        }

        Livewire::test(ShopPage::class, ['slug' => 'part-b15a-'.$i])
            ->assertOk()
            ->assertSee('Varian 0 <span class="opacity-60">('.$stokVarian0.')</span>', false)
            ->assertSee($this->totalManual($i).' unit tersedia');
    }

    public function test_stok_per_cabang_sama_dengan_implementasi_lama(): void
    {
        $this->seedFixture();

        $i = 9;
        $produkId = $this->produkIds[$i];

        // Implementasi LAMA persis seperti sebelum B-15a.
        $lama = StokItem::with('gudang.cabang')
            ->where('produk_id', $produkId)
            ->get()
            ->groupBy(fn ($s) => $s->gudang?->cabang_id)
            ->map(fn ($rows) => [
                'cabang' => $rows->first()->gudang?->cabang?->nama,
                'alamat' => $rows->first()->gudang?->cabang?->alamat,
                'stok' => $rows->sum('jumlah'),
            ])
            ->values()
            ->toArray();

        $component = new ShopPage;
        $component->slug = 'part-b15a-'.$i;

        $this->assertCount(self::CABANGS, $lama, 'Fixture harus punya 3 cabang berisi stok');
        $this->assertSame($lama, $component->getStokPerCabangProperty(), 'Stok per cabang berubah (nama/alamat/angka/urutan)');
    }

    public function test_stok_total_sama_dengan_implementasi_lama(): void
    {
        $this->seedFixture();

        $i = 2;
        $lama = (int) StokItem::where('produk_id', $this->produkIds[$i])->sum('jumlah');

        $component = new ShopPage;
        $component->slug = 'part-b15a-'.$i;

        $this->assertSame($lama, $component->getStokTotalProperty(), 'Stok total berubah');
        $this->assertSame($this->totalManual($i), $component->getStokTotalProperty(), 'Stok total tidak cocok hitungan manual');
    }

    public function test_summary_stok_satu_query_dan_memoized(): void
    {
        $this->seedFixture();

        // Komponen baru: detail sudah di-warm, jadi satu-satunya query yang
        // tersisa adalah agregasi stok (varian + cabang + total).
        $component = new ShopPage;
        $component->slug = 'part-b15a-5';
        $component->productDetail; // warm memoization computed property

        $this->startQueryLog();
        $summary = $component->stokSummary;
        $jumlah = $this->stopQueryLog();

        $this->assertSame(1, $jumlah, 'stokSummary harus tepat 1 query');
        $this->assertSame($this->totalManual(5), $summary['total']);
        $this->assertCount(self::CABANGS, $summary['perCabang']);
        $this->assertCount(self::VARIAN_PER_PRODUK, $summary['perVariant']);

        // Computed property di-memoize per request → 3 property turunan tanpa
        // query tambahan (satu sumber = satu query).
        $this->startQueryLog();
        $component->stokTotal;
        $component->stokPerCabang;
        $component->stokPerVariant;
        $lagi = $this->stopQueryLog();

        $this->assertSame(0, $lagi, 'stokSummary harus di-memoize (satu sumber untuk 3 computed property)');
    }

    public function test_detail_produk_tanpa_stok_aman(): void
    {
        $produk = Produk::create([
            'nama' => 'Part Kosong', 'slug' => 'part-kosong', 'kategori' => 'LCD',
            'kondisi' => 'baru', 'harga_jual_retail' => 100000, 'is_active' => true,
        ]);
        SkuVariant::create([
            'produk_id' => $produk->id, 'sku' => 'KOSONG-1', 'nama_varian' => 'V1', 'is_active' => true,
        ]);

        $component = new ShopPage;
        $component->slug = 'part-kosong';

        $this->assertSame(0, $component->getStokTotalProperty());
        $this->assertSame([], $component->getStokPerCabangProperty());
        $this->assertSame([], $component->getStokPerVariantProperty());

        Livewire::test(ShopPage::class, ['slug' => 'part-kosong'])->assertOk();
    }

    public function test_detail_produk_tidak_ditemukan_aman(): void
    {
        $this->seedFixture();

        $component = new ShopPage;
        $component->slug = 'tidak-ada-slug-ini';

        $this->assertNull($component->getProductDetailProperty());
        $this->assertSame(0, $component->getStokTotalProperty());
        $this->assertSame([], $component->getStokPerCabangProperty());
        $this->assertSame([], $component->getStokPerVariantProperty());

        Livewire::test(ShopPage::class, ['slug' => 'tidak-ada-slug-ini'])->assertOk();
    }

    // ================================================================
    // [P1] Tarif kurir (resolveOrigins)
    // ================================================================

    /** @return array<int, array{produk_id: int, jumlah: int}> */
    private function orderItems(int $jumlahItem = 3): array
    {
        $items = [];
        for ($k = 0; $k < $jumlahItem; $k++) {
            $items[] = ['produk_id' => $this->produkIds[$k], 'jumlah' => 2];
        }

        return $items;
    }

    /** Origins versi LAMA (C × I query) — acuan kebenaran. */
    private function legacyOrigins(array $items): array
    {
        $origins = [];
        foreach (Cabang::where('is_active', true)->get() as $cabang) {
            $stokCukup = true;
            foreach ($items as $item) {
                $total = StokItem::where('produk_id', $item['produk_id'])
                    ->whereHas('gudang', fn ($q) => $q->where('cabang_id', $cabang->id))
                    ->sum('jumlah');

                if ($total < (int) $item['jumlah']) {
                    $stokCukup = false;
                    break;
                }
            }

            if ($stokCukup) {
                $hash = crc32($cabang->kode);
                $origins[] = [
                    round(-6.2 + (($hash % 100) - 50) / 1000, 6),
                    round(106.8 + (($hash % 71) - 35) / 1000, 6),
                ];
            }
        }

        return $origins;
    }

    private function resolveOrigins(array $items): array
    {
        $method = new \ReflectionMethod(ShippingController::class, 'resolveOrigins');

        return $method->invoke(app(ShippingController::class), $items);
    }

    public function test_resolve_origins_query_budget_dan_tanpa_n_plus_one(): void
    {
        $this->seedFixture();
        $items = $this->orderItems(3);

        $this->startQueryLog();
        $origins = $this->resolveOrigins($items);
        $jumlah = $this->stopQueryLog();

        $this->assertLessThanOrEqual(
            self::BUDGET_RESOLVE_ORIGINS,
            $jumlah,
            'resolveOrigins harus ≤ '.self::BUDGET_RESOLVE_ORIGINS." query, aktual {$jumlah} (sebelumnya 1 + C×I = 10).",
        );

        $this->startQueryLog();
        $this->resolveOrigins($items);
        $dupes = $this->dupeShapes();
        $this->stopQueryLog();

        $this->assertSame([], $dupes, "Query dieksekusi >1x di resolveOrigins:\n".implode("\n", array_keys($dupes)));

        $expected = $this->legacyOrigins($items);
        $this->assertNotEmpty($origins, 'Fixture harus punya cabang dengan stok cukup');
        $this->assertSame($expected, $origins, 'Daftar origin berubah');
    }

    public function test_resolve_origins_hanya_cabang_dengan_stok_cukup(): void
    {
        $this->seedFixture();

        // Jumlah >> stok → tidak ada cabang yang memenuhi.
        $items = [['produk_id' => $this->produkIds[0], 'jumlah' => 999999]];
        $this->assertSame([], $this->resolveOrigins($items), 'Cabang tanpa stok cukup tidak boleh jadi origin');
        $this->assertSame($this->legacyOrigins($items), $this->resolveOrigins($items));

        // Jumlah kecil → semua cabang aktif jadi origin, urutannya ikut id.
        $items = [['produk_id' => $this->produkIds[0], 'jumlah' => 1]];
        $semua = $this->resolveOrigins($items);
        $this->assertCount(self::CABANGS, $semua, 'Semua cabang punya stok → semua jadi origin');
        $this->assertSame($this->legacyOrigins($items), $semua, 'Urutan/koordinat origin berubah');

        // Produk tanpa stok sama sekali.
        $this->assertSame([], $this->resolveOrigins([['produk_id' => 999999, 'jumlah' => 1]]));
    }

    public function test_resolve_origins_duplikat_item_per_produk_ekuivalen(): void
    {
        $this->seedFixture();

        // Produk yang sama dua kali → dicek dua kali terhadap total yang sama
        // (persis perilaku lama).
        $items = [
            ['produk_id' => $this->produkIds[0], 'jumlah' => 2],
            ['produk_id' => $this->produkIds[0], 'jumlah' => 2],
        ];

        $this->assertSame($this->legacyOrigins($items), $this->resolveOrigins($items));
    }

    public function test_resolve_origins_abaikan_cabang_nonaktif(): void
    {
        $this->seedFixture();

        Cabang::whereKey($this->cabangIds[1])->update(['is_active' => false]);

        $items = [['produk_id' => $this->produkIds[0], 'jumlah' => 1]];
        $origins = $this->resolveOrigins($items);

        $this->assertCount(2, $origins, 'Cabang nonaktif tidak boleh jadi origin');
        $this->assertSame($this->legacyOrigins($items), $origins);
    }

    public function test_endpoint_tarif_kurir_origins_dan_item_terjaga(): void
    {
        $this->seedFixture();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->staff();

        $this->mock(BiteshipService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('getRates')->andReturn([
                ['kurir' => 'JNE', 'layanan' => 'REG', 'deskripsi' => 'Reguler', 'durasi' => '2-3', 'harga' => 18000.0],
            ]);
        });

        $items = $this->orderItems(3);
        $response = $this->postJson('/api/shipping/rates', [
            'kode_pos_tujuan' => '12345',
            'items' => $items,
            'kurir' => 'jne',
        ])->assertOk();

        $response->assertJsonPath('success', true);
        $this->assertSame(
            $this->legacyOrigins($items),
            $response->json('data.origins'),
            'Origins endpoint berubah'
        );
        $this->assertCount(1, $response->json('data.rates'));
    }

    public function test_endpoint_tarif_kurir_tanpa_biteship_tetap_503(): void
    {
        $this->seedFixture();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->staff();

        $this->mock(BiteshipService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturnFalse();
        });

        $this->postJson('/api/shipping/rates', [
            'kode_pos_tujuan' => '12345',
            'items' => $this->orderItems(1),
        ])->assertStatus(503)->assertJsonPath('success', false);
    }

    private function staff(): User
    {
        $user = User::create([
            'name' => 'Kurir B15a', 'email' => 'kurir-b15a@test.com',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $user->cabangs()->attach($this->cabangIds[0]);
        $this->actingAs($user, 'web');

        return $user;
    }
}
