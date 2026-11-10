<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Marketplace\Models\WebReview;
use App\Modules\Marketplace\Services\CartService;
use App\Modules\Marketplace\Services\WebKonsumenService;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\SmartSearchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
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

    // Catalog filters with URL binding for clean permalink architecture & SEO
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'kategori', except: '')]
    public string $filterKategori = '';

    #[Url(as: 'kondisi', except: '')]
    public string $filterKondisi = '';

    #[Url(as: 'brand', except: '')]
    public string $filterBrand = '';

    #[Url(as: 'hpMerk', except: '')]
    public string $filterHpMerk = '';

    #[Url(as: 'hpModel', except: '')]
    public string $filterHpModel = '';

    #[Url(as: 'kualitas', except: '')]
    public string $filterKualitas = '';

    #[Url(as: 'stok', except: '')]
    public string $filterStok = '';

    #[Url(as: 'sort', except: '')]
    public string $sort = '';

    #[Url(as: 'hargaMax', except: null)]
    public ?int $hargaMax = null;

    // Detail: pilihan varian & qty
    public ?int $selectedVariantId = null;

    #[Url(as: 'tipe', except: '')]
    public ?string $selectedGadgetModel = null;

    public int $qty = 1;

    // Review submission state (pada detail produk)
    public int $reviewRating = 5;

    public string $reviewJudul = '';

    public string $reviewIsi = '';

    public bool $reviewSuccess = false;

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

    public function mount(?string $slug = null, ?string $kategori = null, ?string $hpModel = null): void
    {
        $this->slug = $slug;
        $this->stokSummaryMemo = null;

        if ($kategori) {
            $this->filterKategori = $this->resolveKategoriParam($kategori);
        }
        if ($hpModel) {
            $this->filterHpModel = TipeHp::resolveModelBySlug($hpModel);
        }

        // Support query parameters on direct landing
        if (request()->has('kategori') && empty($this->filterKategori)) {
            $this->filterKategori = (string) request()->query('kategori');
        }
        if (request()->has('category') && empty($this->filterKategori)) {
            $this->filterKategori = (string) request()->query('category');
        }
        if (request()->has('hpModel') && empty($this->filterHpModel)) {
            $this->filterHpModel = (string) request()->query('hpModel');
        }
        if (request()->has('hpMerk') && empty($this->filterHpMerk)) {
            $this->filterHpMerk = (string) request()->query('hpMerk');
        }
        if (request()->has('brand') && empty($this->filterBrand)) {
            $this->filterBrand = (string) request()->query('brand');
        }
        if (request()->has('q') && empty($this->search)) {
            $this->search = (string) request()->query('q');
        }
        if (request()->has('search') && empty($this->search)) {
            $this->search = (string) request()->query('search');
        }
        if (request()->has('kondisi') && empty($this->filterKondisi)) {
            $this->filterKondisi = (string) request()->query('kondisi');
        }
        if (request()->has('sort') && empty($this->sort)) {
            $this->sort = (string) request()->query('sort');
        }
        if (request()->has('tipe') && empty($this->selectedGadgetModel)) {
            $this->selectedGadgetModel = (string) request()->query('tipe');
        }
        if (! empty($this->selectedGadgetModel) && $this->slug) {
            $this->resolveAndMatchSelectedGadget($this->selectedGadgetModel);
        }
    }

    protected function resolveKategoriParam(string $kat): string
    {
        $kategori = KategoriProduk::where('slug', $kat)
            ->orWhere('nama', $kat)
            ->orWhere('id', is_numeric($kat) ? (int) $kat : 0)
            ->first();

        if ($kategori) {
            return (string) $kategori->id;
        }

        return str_replace('-', ' ', urldecode($kat));
    }

    public function setFilterKategori(string $kategori): void
    {
        $this->filterKategori = ($this->filterKategori === $kategori) ? '' : $kategori;
        $this->resetPage();
    }

    public function setFilterHpModel(string $model): void
    {
        $this->filterHpModel = ($this->filterHpModel === $model) ? '' : $model;
        $this->resetPage();
    }

    public function setFilterBrand(string $brand): void
    {
        $this->filterBrand = ($this->filterBrand === $brand) ? '' : $brand;
        $this->resetPage();
    }

    public function getActiveCategoryNameProperty(): ?string
    {
        if (empty($this->filterKategori)) {
            return null;
        }

        if (is_numeric($this->filterKategori)) {
            $cat = KategoriProduk::find($this->filterKategori);

            return $cat?->nama ?? $this->filterKategori;
        }

        return ucwords(str_replace(['-', '_'], ' ', $this->filterKategori));
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

        $this->resolveAndMatchSelectedGadget($gadgetModel);
    }

    public function resolveAndMatchSelectedGadget(string $gadgetModel): void
    {
        $target = trim($gadgetModel);
        if ($target === '') {
            $this->selectedGadgetModel = null;

            return;
        }

        $p = $this->productDetail;
        $canonical = $target;

        if ($p && $p->relationLoaded('tipeHps') && $p->tipeHps->isNotEmpty()) {
            $targetSlug = Str::slug($target);
            foreach ($p->tipeHps as $thp) {
                $thpNama = trim("{$thp->merk} {$thp->model}");
                if (Str::slug($thpNama) === $targetSlug ||
                    Str::slug($thp->model) === $targetSlug ||
                    strcasecmp($thpNama, $target) === 0 ||
                    strcasecmp($thp->model, $target) === 0) {
                    $canonical = $thpNama;
                    break;
                }
            }
        }

        $this->selectedGadgetModel = $canonical;

        // Auto-match SKU Variant jika ada varian yang namanya relevan dengan model gadget
        if ($p && $p->skuVariants->isNotEmpty()) {
            $gNama = strtolower($canonical);
            $matchedVariant = $p->skuVariants->first(function ($v) use ($gNama) {
                $vNama = strtolower($v->nama_varian);

                return str_contains($vNama, $gNama) || str_contains($gNama, $vNama);
            });

            if ($matchedVariant) {
                $this->selectedVariantId = $matchedVariant->id;
            }
        }
    }

    public function updatedQty($value): void
    {
        if ((int) $value < 1) {
            $this->qty = 1;
        } else {
            $this->qty = (int) $value;
        }
    }

    public function addToCart(?int $produkId = null, ?int $variantId = null)
    {
        $produk = $produkId ? Produk::with('skuVariants')->find($produkId) : $this->productDetail;
        if (! $produk) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Produk tidak ditemukan']);

            return;
        }

        if ($produk->stok_total <= 0) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Produk ini sedang habis stok.']);

            return;
        }

        $activeVariantId = $variantId;
        if ($produkId) {
            if ($activeVariantId === null) {
                $activeVariantId = $produk->skuVariants->where('is_active', true)->first()?->id;
            }
        } else {
            $activeVariantId = $this->selectedVariantId;
        }

        $itemQty = $produkId ? 1 : max(1, $this->qty);
        $catatan = (! $produkId || ($this->productDetail && $produkId === $this->productDetail->id)) && $this->selectedGadgetModel
            ? "Model: {$this->selectedGadgetModel}"
            : null;

        try {
            app(CartService::class)->isi(
                $produk->id,
                $activeVariantId,
                $itemQty,
                $catatan
            );
            $this->dispatch('cart-updated', count: app(CartService::class)->jumlahItem());
            $this->dispatch('alert', ['type' => 'success', 'message' => "{$produk->nama} berhasil ditambahkan ke keranjang"]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function beliLangsung(?int $produkId = null)
    {
        $produk = $produkId ? Produk::with('skuVariants')->find($produkId) : $this->productDetail;
        if (! $produk) {
            return;
        }

        if ($produk->stok_total <= 0) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Produk ini sedang habis stok.']);

            return;
        }

        $activeVariantId = $produkId
            ? ($produk->skuVariants->where('is_active', true)->first()?->id ?? null)
            : $this->selectedVariantId;

        $catatan = (! $produkId || ($this->productDetail && $produkId === $this->productDetail->id)) && $this->selectedGadgetModel
            ? "Model: {$this->selectedGadgetModel}"
            : null;

        try {
            app(CartService::class)->isi(
                $produk->id,
                $activeVariantId,
                $produkId ? 1 : max(1, $this->qty),
                $catatan
            );
            $this->dispatch('cart-updated', count: app(CartService::class)->jumlahItem());

            return redirect()->to('/checkout');
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function submitReview(): void
    {
        if (! auth('customer')->check()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Silakan login terlebih dahulu untuk menulis ulasan.']);

            return;
        }

        $this->validate([
            'reviewRating' => 'required|integer|min:1|max:5',
            'reviewJudul' => 'nullable|string|max:150',
            'reviewIsi' => 'required|string|min:5|max:1000',
        ]);

        $produk = $this->productDetail;
        if (! $produk) {
            return;
        }

        WebReview::create([
            'pelanggan_id' => auth('customer')->id(),
            'produk_id' => $produk->id,
            'tipe_ulasan' => 'produk',
            'rating' => $this->reviewRating,
            'judul_ulasan' => $this->reviewJudul ?: null,
            'isi_ulasan' => $this->reviewIsi,
            'is_approved' => false, // Moderasi
            'is_verified_purchase' => true,
        ]);

        $this->reviewJudul = '';
        $this->reviewIsi = '';
        $this->reviewSuccess = true;
        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Ulasan Anda berhasil dikirim dan menunggu verifikasi tim kami.',
        ]);
    }

    public function render()
    {
        if ($this->slug) {
            $webService = app(WebKonsumenService::class);
            $reviews = WebReview::with('pelanggan')
                ->where('produk_id', $this->productDetail?->id)
                ->where('is_approved', true)
                ->latest()
                ->get();

            return view('modules.marketplace.livewire.shop-detail', [
                'produk' => $this->productDetail,
                'hargaInfo' => $this->hargaInfo,
                'stokPerCabang' => $this->stokPerCabang,
                'stokPerVariant' => $this->stokPerVariant,
                'stokTotal' => $this->stokTotal,
                'selectedGadgetModel' => $this->selectedGadgetModel,
                'webConfig' => $webService->getAll(),
                'reviews' => $reviews,
            ])->layout('layouts.marketplace', ['title' => $this->productDetail?->nama ?? 'Produk']);
        }

        $webService = app(WebKonsumenService::class);
        $webConfig = $webService->getAll();

        $metaTitle = 'Katalog Sparepart — '.($webConfig['store_name'] ?? 'Bremspace');
        if ($this->activeCategoryName && $this->filterHpModel) {
            $modelLabel = TipeHp::resolveLabelForModel($this->filterHpModel, $this->filterHpMerk ?: null);
            $metaTitle = "Sparepart {$this->activeCategoryName} {$modelLabel} — ".($webConfig['store_name'] ?? 'Bremspace');
        } elseif ($this->filterHpModel) {
            $modelLabel = TipeHp::resolveLabelForModel($this->filterHpModel, $this->filterHpMerk ?: null);
            $metaTitle = "Katalog Suku Cadang {$modelLabel} — ".($webConfig['store_name'] ?? 'Bremspace');
        } elseif ($this->activeCategoryName) {
            $metaTitle = "Katalog {$this->activeCategoryName} — ".($webConfig['store_name'] ?? 'Bremspace');
        }

        return view('modules.marketplace.livewire.shop-catalog', [
            'categories' => $this->categories,
            'brands' => $this->brands,
            'hpMerkList' => $this->hpMerkList,
            'hpModelList' => $this->hpModelList,
            'kualitasList' => $this->kualitasList,
            'products' => $this->products,
            'webConfig' => $webConfig,
            'webBanners' => $webService->getActiveBanners(),
            'activeCategoryName' => $this->activeCategoryName,
            'filterKategori' => $this->filterKategori,
            'filterHpModel' => $this->filterHpModel,
            'filterHpMerk' => $this->filterHpMerk,
            'filterBrand' => $this->filterBrand,
            'filterKondisi' => $this->filterKondisi,
            'search' => $this->search,
        ])->layout('layouts.marketplace', [
            'title' => $metaTitle,
        ]);
    }
}
