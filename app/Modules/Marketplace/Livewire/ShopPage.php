<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Marketplace\Services\CartService;
use App\Modules\Pos\Services\PricingService;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use Livewire\Component;
use Livewire\WithPagination;

class ShopPage extends Component
{
    use WithPagination;

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

    public function mount(?string $slug = null)
    {
        $this->slug = $slug;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function getCategoriesProperty()
    {
        return Produk::where('is_active', true)->distinct()->orderBy('kategori')->pluck('kategori')->filter()->values();
    }

    public function getHpMerkListProperty()
    {
        // [T-11] Ekstrak merk unik dari kompatibilitas_hp JSON
        return Produk::where('is_active', true)
            ->whereNotNull('kompatibilitas_hp')
            ->where('kompatibilitas_hp', '!=', '[]')
            ->get()
            ->flatMap(fn ($p) => collect($p->kompatibilitas_hp ?? [])->pluck('merk'))
            ->unique()
            ->sort()
            ->values();
    }

    public function getHpModelListProperty()
    {
        // [T-11] Ekstrak model unik dari kompatibilitas_hp JSON (filtered by merk if selected)
        $query = Produk::where('is_active', true)
            ->whereNotNull('kompatibilitas_hp')
            ->where('kompatibilitas_hp', '!=', '[]');

        if ($this->filterHpMerk) {
            $query->whereRaw('JSON_CONTAINS(kompatibilitas_hp, ?)', [json_encode(['merk' => $this->filterHpMerk])]);
        }

        return $query->get()
            ->flatMap(fn ($p) => collect($p->kompatibilitas_hp ?? [])->pluck('model'))
            ->unique()
            ->sort()
            ->values();
    }

    public function getBrandsProperty()
    {
        return Produk::where('is_active', true)->distinct()->orderBy('brand_kompatibel')->pluck('brand_kompatibel')->filter()->values();
    }

    public function getProductsProperty()
    {
        $query = Produk::where('is_active', true)->withCount('stokItems');

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
            ->with(['skuVariants' => fn ($q) => $q->where('is_active', true)])
            ->first();
    }

    public function getStokPerCabangProperty(): array
    {
        $produk = $this->productDetail;
        if (! $produk) {
            return [];
        }

        return StokItem::with('gudang.cabang')
            ->where('produk_id', $produk->id)
            ->get()
            ->groupBy(fn ($s) => $s->gudang?->cabang_id)
            ->map(fn ($rows) => [
                'cabang' => $rows->first()->gudang?->cabang?->nama,
                'alamat' => $rows->first()->gudang?->cabang?->alamat,
                'stok' => $rows->sum('jumlah'),
            ])
            ->values()
            ->toArray();
    }

    public function getStokTotalProperty(): int
    {
        return $this->productDetail ? (int) StokItem::where('produk_id', $this->productDetail->id)->sum('jumlah') : 0;
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
