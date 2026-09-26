<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Marketplace\Services\CartService;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class ShopPage extends Component
{
    use WithPagination;

    /**
     * TTL cache dropdown kompatibilitas HP (detik).
     *
     * [B-15a] Dropdown HP di katalog dibaca sekali per render; sumbernya
     * (produk + JSON kompatibilitas_hp) jarang berubah, jadi 1 jam cukup.
     * CATATAN: cache TIDAK di-invalidate otomatis saat produk berubah (Produk
     * ada di modul Wms, di luar scope perbaikan ini) — worst case dropdown
     * tertinggal ≤ 1 jam; hasil & urutan tidak pernah berubah.
     */
    private const HP_LIST_CACHE_TTL = 3600;

    public ?string $slug = null; // bila ada → halaman detail

    // Catalog filters
    public string $search = '';

    public string $filterKategori = '';

    public string $filterKondisi = '';

    public string $filterBrand = '';

    public string $filterHpMerk = ''; // [T-11] filter dari kompatibilitas_hp JSON

    public string $filterHpModel = ''; // [T-11] filter dari kompatibilitas_hp JSON

    public ?int $hargaMax = null;

    // Detail: pilihan varian & qty
    public ?int $selectedVariantId = null;

    public int $qty = 1;

    /**
     * [B-15a] Memo per-instance untuk {@see getStokSummaryProperty()}.
     *
     * Livewire v4 hanya memoize computed property lewat `__get`; pemanggilan
     * method `getStokXProperty()` langsung (blade / test / computed lain)
     * menghitung ulang. Tanpa memo ini, 3 computed (stokTotal, stokPerCabang,
     * stokPerVariant) = 3 query agregat untuk 1 produk.
     *
     * Sifat `private` → tidak pernah ikut snapshot/hydrate Livewire
     * (`Component::all()` hanya public props), jadi mustahil basi antar
     * request; `mount()` juga meresetnya eksplisit.
     *
     * @var array{perCabang: array, total: int, perVariant: array}|null
     */
    private ?array $stokSummaryMemo = null;

    public function mount(?string $slug = null)
    {
        $this->slug = $slug;
        $this->stokSummaryMemo = null;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function getCategoriesProperty()
    {
        return Produk::where('is_active', true)->distinct()->orderBy('kategori')->pluck('kategori')->filter()->values();
    }

    public function getHpMerkListProperty(): array
    {
        // [T-11] Ekstrak merk unik dari kompatibilitas_hp JSON
        // [B-15a] pluck() (bukan model penuh) + cache 1 jam — hasilnya & urutannya
        // tetap sama, tapi tidak lagi menarik seluruh tabel produk tiap render.
        return Cache::remember(
            'shop.hp.merk-list.v1',
            self::HP_LIST_CACHE_TTL,
            fn (): array => $this->hpListValues('merk')
        );
    }

    public function getHpModelListProperty(): array
    {
        // [T-11] Ekstrak model unik dari kompatibilitas_hp JSON (filtered by merk if selected)
        // [B-15a] Cache per filter merk; hanya kolom JSON yang di-pluck.
        return Cache::remember(
            'shop.hp.model-list.v1'.($this->filterHpMerk ? ':'.$this->filterHpMerk : ''),
            self::HP_LIST_CACHE_TTL,
            function (): array {
                $query = Produk::where('is_active', true)
                    ->whereNotNull('kompatibilitas_hp')
                    ->where('kompatibilitas_hp', '!=', '[]');

                if ($this->filterHpMerk) {
                    $query->whereRaw('JSON_CONTAINS(kompatibilitas_hp, ?)', [json_encode(['merk' => $this->filterHpMerk])]);
                }

                return $this->hpListValues('model', $query);
            }
        );
    }

    /**
     * [B-15a] Ambil daftar unik nilai (merk|model) dari JSON kompatibilitas_hp.
     *
     * Mirip implementasi lama (model penuh + cast array), hanya sumber datanya
     * `pluck('kompatibilitas_hp')` — 1 kolom, bukan seluruh row produk.
     * Normalisasi `unique() → sort() → values()` dipertahankan agar hasil dan
     * urutan dropdown identik dengan sebelumnya.
     *
     * @param  'merk'|'model'  $field
     * @return array<int, mixed>
     */
    private function hpListValues(string $field, ?Builder $query = null): array
    {
        $rows = ($query ?? Produk::where('is_active', true)
            ->whereNotNull('kompatibilitas_hp')
            ->where('kompatibilitas_hp', '!=', '[]'))
            ->pluck('kompatibilitas_hp');

        return collect($rows)
            ->flatMap(function ($json) use ($field): Collection {
                $decoded = is_array($json) ? $json : json_decode((string) $json, true);

                return collect(is_array($decoded) ? $decoded : [])->pluck($field);
            })
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function getBrandsProperty()
    {
        return Produk::where('is_active', true)->distinct()->orderBy('brand_kompatibel')->pluck('brand_kompatibel')->filter()->values();
    }

    public function getProductsProperty()
    {
        // [B-15a] withSum (bukan withCount) supaya total stok per kartu datang
        // dari subquery — SUM(jumlah) — tanpa query per produk. Alias mengikuti
        // default Laravel >= 12 (`{relasi_snake}_{kolom}` = stok_items_sum_jumlah)
        // dan dibaca di blade. with('hargaTier') dipakai
        // PricingService::resolve() lewat relasi yang sudah di-load
        // (PelangganService::rowsHargaTier) sehingga harga tier cukup 1 query
        // untuk seluruh halaman, bukan 1 per produk.
        $query = Produk::where('is_active', true)
            ->withSum('stokItems', 'jumlah')
            ->with('hargaTier');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('brand_kompatibel', 'like', "%{$this->search}%")
                    ->orWhere('model_kompatibel', 'like', "%{$this->search}%")
                    ->orWhere('kategori', 'like', "%{$this->search}%")
                  // [T-11] Search juga di kompatibilitas_hp JSON
                    ->orWhereRaw("JSON_SEARCH(kompatibilitas_hp, 'one', ?) IS NOT NULL", ["%{$this->search}%"]);
            });
        }
        if ($this->filterKategori) {
            $query->where('kategori', $this->filterKategori);
        }
        if ($this->filterKondisi) {
            $query->where('kondisi', $this->filterKondisi);
        }
        if ($this->filterBrand) {
            $query->where('brand_kompatibel', $this->filterBrand);
        }
        // [T-11] Filter by HP merk/model dari kompatibilitas_hp terstruktur (prioritaskan data terstruktur)
        if ($this->filterHpMerk) {
            $query->whereRaw('JSON_CONTAINS(kompatibilitas_hp, ?)', [json_encode(['merk' => $this->filterHpMerk])]);
        }
        if ($this->filterHpModel) {
            $query->whereRaw('JSON_CONTAINS(kompatibilitas_hp, ?)', [json_encode(['model' => $this->filterHpModel])]);
        }
        if ($this->hargaMax) {
            $query->where('harga_jual_retail', '<=', $this->hargaMax);
        }

        return $query->paginate(18);
    }

    public function getProductDetailProperty(): ?Produk
    {
        if (! $this->slug) {
            return null;
        }

        return Produk::where('slug', $this->slug)
            ->where('is_active', true)
            // [B-15a] hargaTier di-load sekalian: resolveHarga() memakainya via
            // relasi (rowsHargaTier) sehingga tidak query terpisah per render.
            ->with(['hargaTier', 'skuVariants' => fn ($q) => $q->where('is_active', true)])
            ->first();
    }

    /**
     * [B-15a] Ringkasan stok produk dari SATU query.
     *
     * Sebelumnya 3 sumber terpisah (stok per cabang + total + per varian) =
     * 3 query + 1 query per varian. Sekarang satu scan `stok_items` (LEFT JOIN
     * gudang + cabang, tanpa filter gudang agar total/varian tetap sama seperti
     * `StokItem::where('produk_id', …)->sum('jumlah')`) diturunkan di PHP:
     *
     * - perCabang  → sama dengan getStokPerCabangProperty() lama (group by
     *   cabang_id, nama/alamat dari cabang pertama pada grup, urutan kemunculan
     *   baris stok id ASC).
     * - total      → sama dengan getStokTotalProperty() lama.
     * - perVariant → total stok per sku_variant_id (pengganti query per varian).
     *
     * Hasil di-memoize per instance (`$stokSummaryMemo`) supaya 3 computed
     * yang memakainya (stokTotal / stokPerCabang / stokPerVariant) =
     * 1 query, bukan 3. Angka & urutannya tetap identik dengan query lama.
     *
     * @return array{perCabang: array<int, array{cabang: ?string, alamat: ?string, stok: int}>, total: int, perVariant: array<int, int>}
     */
    public function getStokSummaryProperty(): array
    {
        if ($this->stokSummaryMemo !== null) {
            return $this->stokSummaryMemo;
        }

        $produk = $this->productDetail;
        if (! $produk) {
            return $this->stokSummaryMemo = ['perCabang' => [], 'total' => 0, 'perVariant' => []];
        }

        $rows = DB::table('stok_items as si')
            ->leftJoin('gudang as g', 'g.id', '=', 'si.gudang_id')
            ->leftJoin('cabang as c', 'c.id', '=', 'g.cabang_id')
            ->where('si.produk_id', $produk->id)
            ->orderBy('si.id')
            ->get(['si.sku_variant_id', 'si.jumlah', 'g.cabang_id', 'c.nama as cabang_nama', 'c.alamat as cabang_alamat']);

        $cabangGroups = [];
        $perVariant = [];
        $total = 0;

        foreach ($rows as $row) {
            $jumlah = (int) $row->jumlah;
            $total += $jumlah;

            if ($row->sku_variant_id !== null) {
                $vid = (int) $row->sku_variant_id;
                $perVariant[$vid] = ($perVariant[$vid] ?? 0) + $jumlah;
            }

            // Grouping legacy: by gudang->cabang_id, nama/alamat dari baris
            // pertama grup, urutan = kemunculan baris stok (id ASC).
            $key = $row->cabang_id === null ? '~null~' : 'c'.$row->cabang_id;
            if (! array_key_exists($key, $cabangGroups)) {
                $cabangGroups[$key] = [
                    'cabang' => $row->cabang_nama,
                    'alamat' => $row->cabang_alamat,
                    'stok' => 0,
                ];
            }
            $cabangGroups[$key]['stok'] += $jumlah;
        }

        return $this->stokSummaryMemo = [
            'perCabang' => array_values($cabangGroups),
            'total' => $total,
            'perVariant' => $perVariant,
        ];
    }

    public function getStokPerCabangProperty(): array
    {
        return $this->stokSummary['perCabang'];
    }

    public function getStokPerVariantProperty(): array
    {
        return $this->stokSummary['perVariant'];
    }

    public function getStokTotalProperty(): int
    {
        return (int) ($this->stokSummary['total'] ?? 0);
    }

    public function getHargaInfoProperty(): array
    {
        $produk = $this->productDetail;
        if (! $produk) {
            return [];
        }

        $customer = auth('customer')->user();
        $variant = $this->selectedVariantId ? SkuVariant::find($this->selectedVariantId) : null;

        return app(PricingService::class)->resolve($produk, $customer, $variant);
    }

    public function addToCart(?int $produkId = null)
    {
        $produk = $produkId ? Produk::find($produkId) : $this->productDetail;
        if (! $produk) {
            return;
        }

        try {
            app(CartService::class)->isi(
                $produk->id,
                $this->selectedVariantId,
                max(1, $this->qty)
            );
            $this->dispatch('alert', ['type' => 'success', 'message' => "{$produk->nama} masuk keranjang"]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function render()
    {
        if ($this->slug) {
            return view('modules.marketplace.livewire.shop-detail', [
                'produk' => $this->productDetail,
                'hargaInfo' => $this->hargaInfo,
                'stokPerCabang' => $this->stokPerCabang,
                'stokPerVariant' => $this->stokPerVariant,
                'stokTotal' => $this->stokTotal,
            ])->layout('layouts.marketplace', ['title' => $this->productDetail?->nama ?? 'Produk']);
        }

        return view('modules.marketplace.livewire.shop-catalog', [
            'categories' => $this->categories,
            'brands' => $this->brands,
            'hpMerkList' => $this->hpMerkList,
            'hpModelList' => $this->hpModelList,
            'products' => $this->products,
        ])->layout('layouts.marketplace', ['title' => 'Katalog Sparepart']);
    }
}
