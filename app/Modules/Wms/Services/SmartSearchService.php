<?php

namespace App\Modules\Wms\Services;

use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\TipeHp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SmartSearchService
{
    /**
     * TTL cache faceted options (detik).
     */
    public const FACETED_CACHE_TTL = 300; // 5 menit

    /**
     * Kamus sinonim & singkatan umum industri suku cadang gadget.
     */
    protected const SYNONYMS = [
        'ip' => ['iphone', 'apple'],
        'iph' => ['iphone', 'apple'],
        'ipon' => ['iphone', 'apple'],
        'iphone' => ['ip', 'apple'],
        'sam' => ['samsung'],
        'sams' => ['samsung'],
        'samsung' => ['sam'],
        'xi' => ['xiaomi', 'redmi'],
        'mi' => ['xiaomi', 'redmi'],
        'red' => ['redmi', 'xiaomi'],
        'redmi' => ['xiaomi'],
        'xiaomi' => ['redmi', 'mi'],
        'inf' => ['infinix'],
        'infinix' => ['inf'],
        'rm' => ['realme'],
        'realme' => ['rm'],
        'batre' => ['baterai', 'battery'],
        'batere' => ['baterai', 'battery'],
        'batt' => ['baterai', 'battery'],
        'battery' => ['baterai', 'batre'],
        'baterai' => ['batre', 'battery'],
        'lcd' => ['touchscreen', 'layar', 'display'],
        'tc' => ['touchscreen', 'lcd'],
        'layar' => ['lcd', 'touchscreen', 'display'],
        'touchscreen' => ['lcd', 'tc'],
        'flex' => ['fleksibel', 'flexible'],
        'fleksibel' => ['flex', 'flexible'],
        'flexible' => ['flex', 'fleksibel'],
        'fleksi' => ['flex', 'fleksibel'],
        'con' => ['konektor', 'connector'],
        'konektor' => ['connector', 'con'],
        'connector' => ['konektor', 'con'],
        'chg' => ['charger', 'charging', 'cas'],
        'cas' => ['charger', 'charging'],
        'casan' => ['charger', 'charging'],
        'charger' => ['chg', 'cas', 'charging'],
        'cam' => ['kamera', 'camera'],
        'kamera' => ['camera', 'cam'],
        'camera' => ['kamera', 'cam'],
        'spk' => ['speaker', 'buzzer'],
        'spkr' => ['speaker', 'buzzer'],
        'speaker' => ['spk', 'buzzer'],
        'buzzer' => ['speaker', 'spk'],
        'bc' => ['backdoor', 'backcover', 'housing', 'casing'],
        'backdoor' => ['backcover', 'housing', 'casing'],
        'backcover' => ['backdoor', 'housing', 'casing'],
        'casing' => ['housing', 'backdoor', 'backcover'],
        'housing' => ['casing', 'backdoor', 'backcover'],
        'lem' => ['glue', 'adhesive', 't7000', 'b7000'],
        'glue' => ['lem', 'adhesive'],
        'sw' => ['switch', 'on off', 'power'],
        'switch' => ['sw', 'on off', 'power'],
        'power' => ['on off', 'switch'],
        'vol' => ['volume'],
        'volume' => ['vol'],
        'ori' => ['original', 'oem'],
        'original' => ['ori', 'oem'],
        'oem' => ['ori', 'original'],
    ];

    /**
     * Normalisasi query pencarian menjadi kumpulan token pintar beserta sinonimnya.
     *
     * @return array<int, array<int, string>> Array of synonym groups. Setiap group wajib cocok (AND across groups, OR within group).
     */
    public function tokenize(string $query): array
    {
        $clean = trim(mb_strtolower($query));
        if ($clean === '') {
            return [];
        }

        // 1. Ekstrak kata dasar
        $rawWords = array_values(array_filter(preg_split('/[\s,\.\-_+\/]+/', $clean), fn ($w) => strlen($w) > 0));

        $tokenGroups = [];

        foreach ($rawWords as $word) {
            $synonyms = [$word];

            // Cek sinonim kamus
            if (isset(self::SYNONYMS[$word])) {
                foreach (self::SYNONYMS[$word] as $syn) {
                    $synonyms[] = $syn;
                }
            }

            // Alphanumeric splitting (misal: "ip11" -> "ip" + "11", "a50" -> "a" + "50")
            if (preg_match('/^([a-z]+)(\d+)$/i', $word, $matches)) {
                $prefix = strtolower($matches[1]);
                $number = $matches[2];
                $synonyms[] = $prefix.' '.$number;

                if (isset(self::SYNONYMS[$prefix])) {
                    foreach (self::SYNONYMS[$prefix] as $synPrefix) {
                        $synonyms[] = $synPrefix.' '.$number;
                        $synonyms[] = $synPrefix;
                    }
                }
            }

            $tokenGroups[] = array_values(array_unique($synonyms));
        }

        return $tokenGroups;
    }

    /**
     * Terapkan pencarian cerdas ke Eloquent Builder.
     */
    public function applySmartSearch(Builder $query, ?string $q): Builder
    {
        if (empty($q)) {
            return $query;
        }

        $term = trim($q);
        if ($term === '') {
            return $query;
        }

        $tokenGroups = $this->tokenize($term);
        if (empty($tokenGroups)) {
            return $query;
        }

        // Kumpulkan semua kata kunci unik untuk pre-matching
        $allKeywords = [];
        foreach ($tokenGroups as $group) {
            foreach ($group as $item) {
                $allKeywords[] = $item;
            }
        }
        $allKeywords = array_values(array_unique(array_filter($allKeywords, fn ($k) => strlen($k) >= 2)));

        // Pre-fetch matching IDs dari master data (uncorrelated scan untuk kecepatan instan)
        $matchingBrandIds = ! empty($allKeywords) ? Brand::query()
            ->where(function ($bq) use ($allKeywords) {
                foreach ($allKeywords as $kw) {
                    $bq->orWhere('nama', 'like', "%{$kw}%");
                }
            })
            ->pluck('id')
            ->all() : [];

        $matchingKualitasIds = ! empty($allKeywords) ? KualitasProduk::query()
            ->where(function ($kq) use ($allKeywords) {
                foreach ($allKeywords as $kw) {
                    $kq->orWhere('nama', 'like', "%{$kw}%");
                }
            })
            ->pluck('id')
            ->all() : [];

        $matchingKategoriIds = ! empty($allKeywords) ? KategoriProduk::query()
            ->where(function ($cq) use ($allKeywords) {
                foreach ($allKeywords as $kw) {
                    $cq->orWhere('nama', 'like', "%{$kw}%");
                }
            })
            ->pluck('id')
            ->all() : [];

        $matchingTipeHpIds = ! empty($allKeywords) ? TipeHp::query()
            ->where(function ($tq) use ($allKeywords) {
                foreach ($allKeywords as $kw) {
                    $tq->orWhere('merk', 'like', "%{$kw}%")
                        ->orWhere('model', 'like', "%{$kw}%");
                }
            })
            ->pluck('id')
            ->all() : [];

        return $query->where(function ($sub) use ($term, $tokenGroups, $matchingBrandIds, $matchingKualitasIds, $matchingKategoriIds, $matchingTipeHpIds) {
            // 1. Exact match Barcode utama produk (B-Tree index)
            $sub->where('produk.barcode', $term)
                // 2. Barcode & SKU varian produk (subquery terindeks)
                ->orWhereIn('produk.id', SkuVariant::query()
                    ->where('is_active', true)
                    ->where(function ($sq) use ($term) {
                        $sq->where('barcode', $term)
                            ->orWhere('sku', $term)
                            ->orWhere('sku', 'like', "%{$term}%");
                    })
                    ->select('produk_id')
                )
                // 3. Multi-word AND Matching dengan toleransi sinonim & variasi part
                ->orWhere(function ($allTokensQ) use ($tokenGroups, $matchingBrandIds, $matchingKualitasIds, $matchingKategoriIds, $matchingTipeHpIds) {
                    foreach ($tokenGroups as $group) {
                        $allTokensQ->where(function ($oneTokenQ) use ($group, $matchingBrandIds, $matchingKualitasIds, $matchingKategoriIds, $matchingTipeHpIds) {
                            foreach ($group as $syn) {
                                $oneTokenQ->orWhere(function ($clause) use ($syn, $matchingBrandIds, $matchingKualitasIds, $matchingKategoriIds, $matchingTipeHpIds) {
                                    $clause->where('produk.nama', 'like', "%{$syn}%")
                                        ->orWhere('produk.brand_kompatibel', 'like', "%{$syn}%")
                                        ->orWhere('produk.model_kompatibel', 'like', "%{$syn}%")
                                        ->orWhere('produk.kompatibilitas_hp', 'like', "%{$syn}%")
                                        ->orWhere('produk.kategori', 'like', "%{$syn}%")
                                        ->orWhere('produk.kondisi', 'like', "%{$syn}%")
                                        ->orWhere('produk.deskripsi', 'like', "%{$syn}%");

                                    if (! empty($matchingBrandIds)) {
                                        $clause->orWhereIn('produk.brand_id', $matchingBrandIds);
                                    }
                                    if (! empty($matchingKualitasIds)) {
                                        $clause->orWhereIn('produk.kualitas_id', $matchingKualitasIds);
                                    }
                                    if (! empty($matchingKategoriIds)) {
                                        $clause->orWhereIn('produk.kategori_id', $matchingKategoriIds);
                                    }
                                    if (! empty($matchingTipeHpIds)) {
                                        $clause->orWhereExists(function ($ex) use ($matchingTipeHpIds) {
                                            $ex->select(DB::raw(1))
                                                ->from('kompatibilitas_produk_tipe_hp')
                                                ->whereColumn('kompatibilitas_produk_tipe_hp.produk_id', 'produk.id')
                                                ->whereIn('kompatibilitas_produk_tipe_hp.tipe_hp_id', $matchingTipeHpIds);
                                        });
                                    }
                                });
                            }
                        });
                    }
                });
        });
    }

    /**
     * Urutkan hasil pencarian berdasarkan skor relevansi kecocokan tertinggi.
     */
    public function applyRelevanceRanking(Builder $query, ?string $q): Builder
    {
        if (empty($q)) {
            return $query;
        }

        $term = trim($q);
        if ($term === '') {
            return $query;
        }

        $words = array_values(array_filter(preg_split('/\s+/', $term), fn ($w) => strlen($w) > 0));

        $cases = [];
        $bindings = [];

        // 1. Exact Barcode (+300)
        $cases[] = 'CASE WHEN produk.barcode = ? THEN 300 ELSE 0 END';
        $bindings[] = $term;

        // 2. Exact SKU Varian (+250)
        $cases[] = 'CASE WHEN EXISTS (SELECT 1 FROM sku_variants WHERE sku_variants.produk_id = produk.id AND sku_variants.sku = ?) THEN 250 ELSE 0 END';
        $bindings[] = $term;

        // 3. Exact Nama (+200)
        $cases[] = 'CASE WHEN LOWER(produk.nama) = LOWER(?) THEN 200 ELSE 0 END';
        $bindings[] = $term;

        // 4. Prefix Nama (+150)
        $cases[] = 'CASE WHEN LOWER(produk.nama) LIKE LOWER(?) THEN 150 ELSE 0 END';
        $bindings[] = $term.'%';

        // 5. Frasa Nama (+100)
        $cases[] = 'CASE WHEN LOWER(produk.nama) LIKE LOWER(?) THEN 100 ELSE 0 END';
        $bindings[] = '%'.$term.'%';

        // 6. Brand / Model Exact (+80)
        $cases[] = 'CASE WHEN LOWER(produk.brand_kompatibel) = LOWER(?) OR LOWER(produk.model_kompatibel) = LOWER(?) THEN 80 ELSE 0 END';
        $bindings[] = $term;
        $bindings[] = $term;

        // 7. Kata per kata (+30 per kata nama, +15 per kata model/brand)
        if (count($words) > 1) {
            foreach ($words as $w) {
                $cases[] = 'CASE WHEN LOWER(produk.nama) LIKE LOWER(?) THEN 30 ELSE 0 END';
                $bindings[] = '%'.$w.'%';

                $cases[] = 'CASE WHEN LOWER(produk.brand_kompatibel) LIKE LOWER(?) OR LOWER(produk.model_kompatibel) LIKE LOWER(?) THEN 15 ELSE 0 END';
                $bindings[] = '%'.$w.'%';
                $bindings[] = '%'.$w.'%';
            }
        }

        // 8. Ready Stock Boost (+20)
        $cases[] = 'CASE WHEN EXISTS (SELECT 1 FROM stok_items WHERE stok_items.produk_id = produk.id AND stok_items.jumlah > 0) THEN 20 ELSE 0 END';

        $sql = '('.implode(' + ', $cases).')';

        return $query->orderByRaw($sql.' DESC', $bindings);
    }

    /**
     * Hitung opsi filter yang saling terkait (Faceted Search) secara instan.
     *
     * @param  array<string, mixed>  $activeFilters  [kategori, brand, hpMerk, hpModel, kualitas, kondisi, stok, hargaMax]
     * @return array{
     *     categories: array,
     *     brands: array,
     *     hpMerkList: array,
     *     hpModelList: array,
     *     kualitasList: array
     * }
     */
    public function getFacetedOptions(array $activeFilters, ?string $search = null): array
    {
        // 1. Dapatkan opsi Kategori (difilter oleh search + brand + hp + kualitas + harga + stok, tanpa filter kategori itu sendiri)
        $catFilteredQuery = $this->buildBaseFilteredQuery($activeFilters, $search, ['kategori']);
        $catCounts = (clone $catFilteredQuery)
            ->whereNotNull('kategori_id')
            ->groupBy('kategori_id')
            ->select('kategori_id', DB::raw('count(*) as aggregate'))
            ->pluck('aggregate', 'kategori_id')
            ->all();

        // Bangun pohon kategori terfilter secara rekursif
        $allCategories = KategoriProduk::where('is_active', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();

        $categoriesResult = [];
        if ($allCategories->isNotEmpty()) {
            $grouped = $allCategories->groupBy('parent_id');

            $calcSubtreeCount = function ($catId) use (&$calcSubtreeCount, $grouped, $catCounts): int {
                $count = $catCounts[$catId] ?? 0;
                $children = $grouped->get($catId, collect());
                foreach ($children as $child) {
                    $count += $calcSubtreeCount($child->id);
                }

                return $count;
            };

            $roots = $grouped->get(null, collect());

            foreach ($roots as $r) {
                $children = $grouped->get($r->id, collect())->map(function ($c) use ($calcSubtreeCount) {
                    $count = $calcSubtreeCount($c->id);

                    return [
                        'id' => (string) $c->id,
                        'nama' => (string) $c->nama,
                        'slug' => (string) $c->slug,
                        'count' => $count,
                    ];
                })->filter(fn ($c) => $c['count'] > 0)->values()->all();

                $totalCount = $calcSubtreeCount($r->id);

                if ($totalCount > 0 || empty($activeFilters['brand'] ?? null)) {
                    $categoriesResult[] = [
                        'id' => (string) $r->id,
                        'nama' => (string) $r->nama,
                        'slug' => (string) $r->slug,
                        'count' => $totalCount,
                        'children' => $children,
                    ];
                }
            }
        }

        // 2. Dapatkan opsi Brand (difilter oleh kategori + hp + kualitas + harga + stok, tanpa filter brand itu sendiri)
        $brandFilteredQuery = $this->buildBaseFilteredQuery($activeFilters, $search, ['brand']);
        $brandsResult = (clone $brandFilteredQuery)
            ->whereNotNull('brand_kompatibel')
            ->where('brand_kompatibel', '!=', '')
            ->groupBy('brand_kompatibel')
            ->select('brand_kompatibel', DB::raw('count(*) as aggregate'))
            ->orderBy('brand_kompatibel')
            ->pluck('aggregate', 'brand_kompatibel')
            ->all();

        // 3. Dapatkan opsi Merk HP (difilter oleh kategori + brand + kualitas + harga + stok, tanpa filter hpMerk & hpModel)
        $hpMerkFilteredQuery = $this->buildBaseFilteredQuery($activeFilters, $search, ['hpMerk', 'hpModel']);
        $hpMerkResult = [];

        // Ambil merk dari pivot tipe_hp
        $tipeHpMerks = DB::table('kompatibilitas_produk_tipe_hp')
            ->join('tipe_hp', 'tipe_hp.id', '=', 'kompatibilitas_produk_tipe_hp.tipe_hp_id')
            ->whereIn('kompatibilitas_produk_tipe_hp.produk_id', (clone $hpMerkFilteredQuery)->select('produk.id'))
            ->groupBy('tipe_hp.merk')
            ->select('tipe_hp.merk', DB::raw('count(distinct kompatibilitas_produk_tipe_hp.produk_id) as aggregate'))
            ->orderBy('tipe_hp.merk')
            ->pluck('aggregate', 'tipe_hp.merk')
            ->all();

        foreach ($tipeHpMerks as $merk => $cnt) {
            $hpMerkResult[$merk] = $cnt;
        }

        // Fallback dari kolom JSON kompatibilitas_hp untuk produk tanpa pivot
        $jsonHpRows = (clone $hpMerkFilteredQuery)
            ->whereNotNull('kompatibilitas_hp')
            ->where('kompatibilitas_hp', '!=', '[]')
            ->pluck('kompatibilitas_hp');

        foreach ($jsonHpRows as $json) {
            $decoded = is_array($json) ? $json : json_decode((string) $json, true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    $m = trim($item['merk'] ?? '');
                    if ($m !== '') {
                        $hpMerkResult[$m] = ($hpMerkResult[$m] ?? 0) + 1;
                    }
                }
            }
        }
        ksort($hpMerkResult);

        // 4. Dapatkan opsi Model HP (difilter oleh kategori + brand + kualitas + harga + stok + hpMerk aktif, tanpa filter hpModel)
        $selectedHpMerk = $activeFilters['hpMerk'] ?? null;
        $hpModelFilteredQuery = $this->buildBaseFilteredQuery($activeFilters, $search, ['hpModel']);
        $hpModelResult = [];

        $modelQuery = DB::table('kompatibilitas_produk_tipe_hp')
            ->join('tipe_hp', 'tipe_hp.id', '=', 'kompatibilitas_produk_tipe_hp.tipe_hp_id')
            ->whereIn('kompatibilitas_produk_tipe_hp.produk_id', (clone $hpModelFilteredQuery)->select('produk.id'));

        if (! empty($selectedHpMerk)) {
            $modelQuery->where('tipe_hp.merk', $selectedHpMerk);
        }

        $tipeHpModels = $modelQuery
            ->groupBy('tipe_hp.model')
            ->select('tipe_hp.model', DB::raw('count(distinct kompatibilitas_produk_tipe_hp.produk_id) as aggregate'))
            ->orderBy('tipe_hp.model')
            ->pluck('aggregate', 'tipe_hp.model')
            ->all();

        foreach ($tipeHpModels as $mdl => $cnt) {
            $hpModelResult[$mdl] = (int) $cnt;
        }

        // Fallback untuk produk yang kompatibilitasnya tersimpan di JSON/model_kompatibel tanpa pivot
        if (! empty($selectedHpMerk)) {
            $jsonRows = (clone $hpModelFilteredQuery)
                ->where(function ($q) use ($selectedHpMerk) {
                    $q->where('brand_kompatibel', $selectedHpMerk)
                        ->orWhere('kompatibilitas_hp', 'like', '%"merk":"'.$selectedHpMerk.'"%');
                })
                ->whereNotIn('produk.id', function ($pQ) {
                    $pQ->select('produk_id')->from('kompatibilitas_produk_tipe_hp');
                })
                ->get(['model_kompatibel', 'kompatibilitas_hp']);

            foreach ($jsonRows as $row) {
                if ($row->model_kompatibel) {
                    $hpModelResult[$row->model_kompatibel] = ($hpModelResult[$row->model_kompatibel] ?? 0) + 1;
                }
                $decoded = is_array($row->kompatibilitas_hp) ? $row->kompatibilitas_hp : json_decode((string) $row->kompatibilitas_hp, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $item) {
                        if (($item['merk'] ?? '') === $selectedHpMerk && ! empty($item['model'])) {
                            $hpModelResult[$item['model']] = ($hpModelResult[$item['model']] ?? 0) + 1;
                        }
                    }
                }
            }
        }
        ksort($hpModelResult);

        // 5. Dapatkan opsi Kualitas (difilter oleh search + kategori + brand + hp + harga + stok, tanpa filter kualitas)
        $kualitasFilteredQuery = $this->buildBaseFilteredQuery($activeFilters, $search, ['kualitas']);
        $kualitasCounts = (clone $kualitasFilteredQuery)
            ->whereNotNull('kualitas_id')
            ->groupBy('kualitas_id')
            ->select('kualitas_id', DB::raw('count(*) as aggregate'))
            ->pluck('aggregate', 'kualitas_id')
            ->all();

        $allKualitas = KualitasProduk::where('is_active', true)->orderBy('nama')->get();
        $kualitasResult = [];
        foreach ($allKualitas as $k) {
            $count = $kualitasCounts[$k->id] ?? 0;
            $kualitasResult[] = [
                'id' => (string) $k->id,
                'nama' => (string) $k->nama,
                'count' => $count,
            ];
        }

        return [
            'categories' => $categoriesResult,
            'brands' => $brandsResult,
            'hpMerkList' => $hpMerkResult,
            'hpModelList' => $hpModelResult,
            'kualitasList' => $kualitasResult,
        ];
    }

    /**
     * Bangun query dasar yang difilter oleh filter-filter aktif dengan mengabaikan filter tertentu.
     *
     * @param  array<string, mixed>  $activeFilters
     * @param  array<int, string>  $ignoredFilterKeys
     */
    protected function buildBaseFilteredQuery(array $activeFilters, ?string $search = null, array $ignoredFilterKeys = []): Builder
    {
        $query = Produk::query()->where('is_active', true);

        if (! empty($search)) {
            $this->applySmartSearch($query, $search);
        }

        if (! in_array('kategori', $ignoredFilterKeys, true) && ! empty($activeFilters['kategori'])) {
            $katVal = $activeFilters['kategori'];
            if (is_numeric($katVal)) {
                $kat = KategoriProduk::find($katVal);
                $ids = $kat ? $kat->semuaKeturunanIds() : [(int) $katVal];
                $query->whereIn('kategori_id', $ids);
            } else {
                $query->where(function ($q) use ($katVal) {
                    $q->where('kategori', $katVal)
                        ->orWhereHas('kategoriRelasi', fn ($kq) => $kq->where('nama', $katVal)->orWhere('slug', $katVal));
                });
            }
        }

        if (! in_array('kondisi', $ignoredFilterKeys, true) && ! empty($activeFilters['kondisi'])) {
            $query->where('kondisi', $activeFilters['kondisi']);
        }

        if (! in_array('brand', $ignoredFilterKeys, true) && ! empty($activeFilters['brand'])) {
            $bVal = $activeFilters['brand'];
            $query->where(function ($q) use ($bVal) {
                $q->where('brand_kompatibel', $bVal)
                    ->orWhereHas('brand', fn ($bq) => $bq->where('nama', $bVal));
            });
        }

        if (! in_array('hpMerk', $ignoredFilterKeys, true) && ! empty($activeFilters['hpMerk'])) {
            $merkVal = $activeFilters['hpMerk'];
            $query->where(function ($q) use ($merkVal) {
                $q->whereHas('tipeHps', fn ($tq) => $tq->where('merk', $merkVal))
                    ->orWhere('brand_kompatibel', $merkVal)
                    ->orWhere('kompatibilitas_hp', 'like', '%"merk":"'.$merkVal.'"%');
            });
        }

        if (! in_array('hpModel', $ignoredFilterKeys, true) && ! empty($activeFilters['hpModel'])) {
            $modelVal = $activeFilters['hpModel'];
            $query->where(function ($q) use ($modelVal) {
                $q->whereHas('tipeHps', fn ($tq) => $tq->where('model', $modelVal))
                    ->orWhere('model_kompatibel', $modelVal)
                    ->orWhere('kompatibilitas_hp', 'like', '%"model":"'.$modelVal.'"%');
            });
        }

        if (! in_array('kualitas', $ignoredFilterKeys, true) && ! empty($activeFilters['kualitas'])) {
            $kVal = $activeFilters['kualitas'];
            if (is_numeric($kVal)) {
                $query->where('kualitas_id', (int) $kVal);
            } else {
                $query->whereHas('kualitas', fn ($kq) => $kq->where('nama', $kVal));
            }
        }

        if (! in_array('stok', $ignoredFilterKeys, true) && ! empty($activeFilters['stok'])) {
            if ($activeFilters['stok'] === 'tersedia') {
                $query->has('stokItems');
            } elseif ($activeFilters['stok'] === 'habis') {
                $query->doesntHave('stokItems');
            }
        }

        if (! in_array('hargaMax', $ignoredFilterKeys, true) && ! empty($activeFilters['hargaMax'])) {
            $query->where('harga_jual_retail', '<=', (int) $activeFilters['hargaMax']);
        }

        return $query;
    }
}
