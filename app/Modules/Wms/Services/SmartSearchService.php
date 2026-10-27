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
     * Mengutamakan nama produk di posisi paling atas (Exact, Prefix, All-words phrase),
     * lalu kecocokan kompatibilitas, brand, kategori, dan ketersediaan stok.
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

        // 1. Exact Barcode (+1200) - Scan barcode fisik / exact match
        $cases[] = 'CASE WHEN produk.barcode = ? THEN 1200 ELSE 0 END';
        $bindings[] = $term;

        // 2. Exact SKU Varian (+1100)
        $cases[] = 'CASE WHEN EXISTS (SELECT 1 FROM sku_variants WHERE sku_variants.produk_id = produk.id AND sku_variants.sku = ?) THEN 1100 ELSE 0 END';
        $bindings[] = $term;

        // 3. Exact Nama Produk Cocok Persis (+1000)
        $cases[] = 'CASE WHEN LOWER(produk.nama) = LOWER(?) THEN 1000 ELSE 0 END';
        $bindings[] = $term;

        // 4. Prefix Nama Produk (Diawali kata pencarian) (+800)
        $cases[] = 'CASE WHEN LOWER(produk.nama) LIKE LOWER(?) THEN 800 ELSE 0 END';
        $bindings[] = $term.'%';

        // 5. Frasa Lengkap Ada di Nama Produk (+600)
        $cases[] = 'CASE WHEN LOWER(produk.nama) LIKE LOWER(?) THEN 600 ELSE 0 END';
        $bindings[] = '%'.$term.'%';

        // 6. Seluruh Kata Token Ada di Nama Produk (+400)
        if (count($words) > 1) {
            $allWordsCase = [];
            foreach ($words as $w) {
                $allWordsCase[] = 'LOWER(produk.nama) LIKE LOWER(?)';
                $bindings[] = '%'.$w.'%';
            }
            $cases[] = 'CASE WHEN ('.implode(' AND ', $allWordsCase).') THEN 400 ELSE 0 END';
        }

        // 7. Kata per kata di Nama Produk (+120 per kata nama)
        if (! empty($words)) {
            foreach ($words as $w) {
                $cases[] = 'CASE WHEN LOWER(produk.nama) LIKE LOWER(?) THEN 120 ELSE 0 END';
                $bindings[] = '%'.$w.'%';
            }
        }

        // 8. Brand / Model Exact (+150)
        $cases[] = 'CASE WHEN LOWER(produk.brand_kompatibel) = LOWER(?) OR LOWER(produk.model_kompatibel) = LOWER(?) THEN 150 ELSE 0 END';
        $bindings[] = $term;
        $bindings[] = $term;

        // 9. Brand / Model per kata (+50 per kata)
        if (! empty($words)) {
            foreach ($words as $w) {
                $cases[] = 'CASE WHEN LOWER(produk.brand_kompatibel) LIKE LOWER(?) OR LOWER(produk.model_kompatibel) LIKE LOWER(?) THEN 50 ELSE 0 END';
                $bindings[] = '%'.$w.'%';
                $bindings[] = '%'.$w.'%';
            }
        }

        // 10. Kategori / Deskripsi (+30)
        $cases[] = 'CASE WHEN LOWER(produk.kategori) LIKE LOWER(?) OR LOWER(produk.deskripsi) LIKE LOWER(?) THEN 30 ELSE 0 END';
        $bindings[] = '%'.$term.'%';
        $bindings[] = '%'.$term.'%';

        // 11. Ready Stock Boost (+25)
        $cases[] = 'CASE WHEN EXISTS (SELECT 1 FROM stok_items WHERE stok_items.produk_id = produk.id AND stok_items.jumlah > 0) THEN 25 ELSE 0 END';

        $sql = '('.implode(' + ', $cases).')';

        return $query->orderByRaw($sql.' DESC', $bindings)->orderBy('produk.nama', 'asc');
    }

    /**
     * Terapkan kumpulan filter katalog dan pencarian cerdas secara terpusat.
     * Dapat digunakan di POS Kasir, Katalog Toko Online, WMS Master Produk, dan Modal Global.
     *
     * @param  array<string, mixed>  $filters
     */
    public function applyCatalogFilters(Builder $query, array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $this->applySmartSearch($query, $search);
            if ($filters['auto_relevance'] ?? true) {
                $this->applyRelevanceRanking($query, $search);
            }
        }

        // Filter Kategori
        $katVal = $filters['kategori_id'] ?? $filters['kategori'] ?? null;
        if (! empty($katVal)) {
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

        // Filter Brand Part / Merk Komponen
        $bId = $filters['brand_id'] ?? null;
        if (! empty($bId)) {
            $query->where('brand_id', (int) $bId);
        } elseif (! empty($filters['brand'])) {
            $bVal = (string) $filters['brand'];
            $query->where(function ($q) use ($bVal) {
                $q->where('brand_kompatibel', $bVal)
                    ->orWhereHas('brand', fn ($bq) => $bq->where('nama', $bVal));
            });
        }

        // Filter Kualitas (Grade)
        $kualVal = $filters['kualitas_id'] ?? $filters['kualitas'] ?? null;
        if (! empty($kualVal)) {
            if (is_numeric($kualVal)) {
                $query->where('kualitas_id', (int) $kualVal);
            } else {
                $query->whereHas('kualitas', fn ($kq) => $kq->where('nama', (string) $kualVal));
            }
        }

        // Filter Tipe HP Spesifik (ID)
        $tipeHpId = $filters['tipe_hp_id'] ?? null;
        if (! empty($tipeHpId)) {
            $targetTipe = TipeHp::find($tipeHpId);
            $query->where(function ($q) use ($tipeHpId, $targetTipe) {
                $q->whereHas('tipeHps', fn ($th) => $th->where('tipe_hp.id', (int) $tipeHpId));
                if ($targetTipe) {
                    $q->orWhere(function ($sub) use ($targetTipe) {
                        $sub->where('brand_kompatibel', $targetTipe->merk)
                            ->where('model_kompatibel', $targetTipe->model);
                    });
                }
            });
        }

        // Filter Merk HP / Brand Kompatibel
        $merkHp = $filters['brand_kompatibel'] ?? $filters['hpMerk'] ?? $filters['merk_hp'] ?? null;
        if (! empty($merkHp)) {
            $query->where(function ($q) use ($merkHp) {
                $q->where('brand_kompatibel', $merkHp)
                    ->orWhereHas('tipeHps', fn ($tq) => $tq->where('merk', $merkHp))
                    ->orWhere('kompatibilitas_hp', 'like', '%"merk":"'.$merkHp.'"%');
            });
        }

        // Filter Model HP / Model Kompatibel
        $modelHp = $filters['model_kompatibel'] ?? $filters['hpModel'] ?? $filters['tipe_hp'] ?? null;
        if (! empty($modelHp) && ! is_numeric($modelHp)) {
            $query->where(function ($q) use ($modelHp) {
                $q->where('model_kompatibel', $modelHp)
                    ->orWhereHas('tipeHps', fn ($tq) => $tq->where('model', $modelHp))
                    ->orWhere('kompatibilitas_hp', 'like', '%"model":"'.$modelHp.'"%');
            });
        }

        // Filter Kondisi
        if (! empty($filters['kondisi'])) {
            $query->where('kondisi', (string) $filters['kondisi']);
        }

        // Filter Satuan Unit
        if (! empty($filters['satuan'])) {
            $query->where('satuan', (string) $filters['satuan']);
        }

        // Filter Klasifikasi ABC
        if (! empty($filters['abc_class'])) {
            $query->where('abc_class', (string) $filters['abc_class']);
        }

        // Filter Ketersediaan Stok
        $stokFilter = $filters['stok'] ?? null;
        $hanyaReady = filter_var($filters['hanya_ready_stok'] ?? $filters['ready_stok'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($stokFilter === 'tersedia' || $hanyaReady) {
            $cabangId = $filters['cabang_id'] ?? null;
            $gudangId = $filters['gudang_id'] ?? null;

            $query->whereHas('stokItems', function ($sq) use ($cabangId, $gudangId) {
                $sq->where('jumlah', '>', 0);
                if ($gudangId) {
                    $sq->where('gudang_id', $gudangId);
                } elseif ($cabangId) {
                    $sq->whereIn('gudang_id', function ($gq) use ($cabangId) {
                        $gq->select('id')->from('gudang')->where('cabang_id', $cabangId);
                    });
                }
            });
        } elseif ($stokFilter === 'habis') {
            $query->doesntHave('stokItems');
        }

        // Filter Range Harga
        if (isset($filters['harga_min']) && is_numeric($filters['harga_min'])) {
            $query->where('harga_jual_retail', '>=', (float) $filters['harga_min']);
        }
        if (isset($filters['harga_max']) && is_numeric($filters['harga_max'])) {
            $query->where('harga_jual_retail', '<=', (float) $filters['harga_max']);
        }

        // Filter Supplier
        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', (int) $filters['supplier_id']);
        }

        return $query;
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
