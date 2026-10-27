<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Marketplace\Services\CartService;
use App\Modules\Marketplace\Services\WebKonsumenService;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Services\SmartSearchService;
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

    public string $filterKualitas = '';

    public string $filterStok = ''; // '', 'tersedia', 'habis'

    public string $sort = ''; // '', 'terbaru', 'terlaris', 'termurah', 'termahal', 'relevansi'

    public ?int $hargaMax = null;

    // Detail: pilihan varian & qty
    public ?int $selectedVariantId = null;

    public ?string $selectedGadgetModel = null;

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

    public function updatedFilterKategori()
    {
        $this->resetPage();
    }

    public function updatedFilterBrand()
    {
        $this->resetPage();
    }

    public function updatedFilterKondisi()
    {
        $this->resetPage();
    }

    public function updatedFilterHpMerk()
    {
        // Reset model HP jika ganti merk agar tidak terjadi filter zonk
        $this->filterHpModel = '';
        $this->resetPage();
    }

    public function updatedFilterHpModel()
    {
        $this->resetPage();
    }

    public function updatedFilterKualitas()
    {
        $this->resetPage();
    }

    public function updatedFilterStok()
    {
        $this->resetPage();
    }

    public function updatedHargaMax()
    {
        $this->resetPage();
    }

    public function updatedSort()
    {
        $this->resetPage();
    }

    public function resetSingleFilter(string $key): void
    {
        match ($key) {
            'search' => $this->search = '',
            'kategori' => $this->filterKategori = '',
            'kondisi' => $this->filterKondisi = '',
            'brand' => $this->filterBrand = '',
            'hpMerk' => $this->filterHpMerk = '',
            'hpModel' => $this->filterHpModel = '',
            'kualitas' => $this->filterKualitas = '',
            'stok' => $this->filterStok = '',
            'hargaMax' => $this->hargaMax = null,
            'sort' => $this->sort = '',
            default => null,
        };
        $this->resetPage();
    }

    public function resetAllFilters(): void
    {
        $this->search = '';
        $this->filterKategori = '';
        $this->filterKondisi = '';
        $this->filterBrand = '';
        $this->filterHpMerk = '';
        $this->filterHpModel = '';
        $this->filterKualitas = '';
        $this->filterStok = '';
        $this->hargaMax = null;
        $this->sort = '';
        $this->resetPage();
    }

    public function getFacetedOptionsProperty(): array
    {
        return app(SmartSearchService::class)->getFacetedOptions([
            'kategori' => $this->filterKategori,
            'brand' => $this->filterBrand,
            'kondisi' => $this->filterKondisi,
            'hpMerk' => $this->filterHpMerk,
            'hpModel' => $this->filterHpModel,
            'kualitas' => $this->filterKualitas,
            'stok' => $this->filterStok,
            'hargaMax' => $this->hargaMax,
        ], $this->search);
    }

    public function getKualitasListProperty(): array
    {
        return $this->facetedOptions['kualitasList'] ?? [];
    }

    public function getCategoriesProperty(): array
    {
        return $this->facetedOptions['categories'] ?? [];
    }

    public function getHpMerkListProperty(): array
    {
        return $this->facetedOptions['hpMerkList'] ?? [];
    }

    public function getHpModelListProperty(): array
    {
        return $this->facetedOptions['hpModelList'] ?? [];
    }

    public function getBrandsProperty(): array
    {
        return $this->facetedOptions['brands'] ?? [];
    }

    public function getProductsProperty()
    {
        // [B-15a] withSum (bukan withCount) supaya total stok per kartu datang
        // dari subquery — SUM(jumlah) — tanpa query per produk.
        // Diurutkan dari ketersediaan stok (tersedia > habis) dan produk terlaris.
        $query = Produk::where('is_active', true)
            ->withSum('stokItems', 'jumlah')
            ->with(['hargaTier', 'skuVariants']);

        $filters = [
            'search' => $this->search,
            'auto_relevance' => false,
            'kategori' => $this->filterKategori,
            'kondisi' => $this->filterKondisi,
            'brand' => $this->filterBrand,
            'hpMerk' => $this->filterHpMerk,
            'hpModel' => $this->filterHpModel,
            'kualitas' => $this->filterKualitas,
            'stok' => $this->filterStok,
            'harga_max' => $this->hargaMax,
        ];
        $query->filterKatalog($filters);

        // Urutan: dropdown user > relevansi dgn keyword (default saat search aktif) > kompatibilitas HP > default (ready-stok & terlaris)
        if ($this->sort === 'termurah') {
            $query->orderBy('harga_jual_retail', 'asc');
        } elseif ($this->sort === 'termahal') {
            $query->orderBy('harga_jual_retail', 'desc');
        } elseif ($this->sort === 'terbaru') {
            $query->latest('produk.id');
        } elseif ($this->sort === 'terlaris') {
            $query->orderByDesc('total_terjual')->orderByDesc('stok_katalog');
        } elseif (! empty($this->search)) {
            $query->urutkanRelevansi($this->search);
        } elseif (! $this->sort && $this->filterHpModel) {
            $query->urutkanKompatibilitas($this->filterHpModel, $this->filterHpMerk ?: null);
        } else {
            $query->urutKetersediaanDanTerlaris();
        }

        return $query->paginate(24);
    }

    public function getProductDetailProperty(): ?Produk
    {
        if (! $this->slug) {
            return null;
        }

        return Produk::where('slug', $this->slug)
            ->where('is_active', true)
            ->with([
                'hargaTier',
                'skuVariants' => fn ($q) => $q->where('is_active', true),
                'kategoriRelasi.parent',
                'brand',
                'kualitas',
                'tipeHps',
                'produkKompatibel',
            ])
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

    public function selectGadgetModel(string $gadgetModel): void
    {
        $gadgetModel = trim($gadgetModel);
        if ($this->selectedGadgetModel === $gadgetModel) {
            $this->selectedGadgetModel = null;

            return;
        }

        $this->selectedGadgetModel = $gadgetModel;

        // Auto-match SKU Variant jika ada varian yang namanya relevan dengan model gadget
        if ($this->productDetail && $this->productDetail->skuVariants->isNotEmpty()) {
            $matchedVariant = $this->productDetail->skuVariants->first(function ($v) use ($gadgetModel) {
                $vNama = strtolower($v->nama_varian);
                $gNama = strtolower($gadgetModel);

                return str_contains($vNama, $gNama) || str_contains($gNama, $vNama);
            });

            if ($matchedVariant) {
                $this->selectedVariantId = $matchedVariant->id;
            }
        }
    }

    public function addToCart(?int $produkId = null)
    {
        $produk = $produkId ? Produk::find($produkId) : $this->productDetail;
        if (! $produk) {
            return;
        }

        $catatan = (! $produkId || ($this->productDetail && $produkId === $this->productDetail->id)) && $this->selectedGadgetModel
            ? "Model: {$this->selectedGadgetModel}"
            : null;

        try {
            app(CartService::class)->isi(
                $produk->id,
                $this->selectedVariantId,
                max(1, $this->qty),
                $catatan
            );
            $this->dispatch('cart-updated', count: app(CartService::class)->jumlahItem());
            $this->dispatch('alert', ['type' => 'success', 'message' => "{$produk->nama} masuk keranjang"]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function render()
    {
        if ($this->slug) {
            $webService = app(WebKonsumenService::class);

            return view('modules.marketplace.livewire.shop-detail', [
                'produk' => $this->productDetail,
                'hargaInfo' => $this->hargaInfo,
                'stokPerCabang' => $this->stokPerCabang,
                'stokPerVariant' => $this->stokPerVariant,
                'stokTotal' => $this->stokTotal,
                'selectedGadgetModel' => $this->selectedGadgetModel,
                'webConfig' => $webService->getAll(),
            ])->layout('layouts.marketplace', ['title' => $this->productDetail?->nama ?? 'Produk']);
        }

        $webService = app(WebKonsumenService::class);

        return view('modules.marketplace.livewire.shop-catalog', [
            'categories' => $this->categories,
            'brands' => $this->brands,
            'hpMerkList' => $this->hpMerkList,
            'hpModelList' => $this->hpModelList,
            'kualitasList' => $this->kualitasList,
            'products' => $this->products,
            'webConfig' => $webService->getAll(),
            'webBanners' => $webService->getActiveBanners(),
        ])->layout('layouts.marketplace', ['title' => 'Katalog Sparepart']);
    }
}
