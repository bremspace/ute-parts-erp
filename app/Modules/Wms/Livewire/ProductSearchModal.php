<?php

namespace App\Modules\Wms\Livewire;

use App\Models\UserPreference;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\TipeHp;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ProductSearchModal extends Component
{
    use WithPagination;

    public $search = '';

    public $selectedProductId = null;

    public $emitTo = null; // Target component to emit selected product to

    public $targetIndex = null; // For PO/Transfer item index

    public $context = 'general'; // po, transfer, servis, etc.

    public bool $isOpen = false; // State modal (dicek test & UI)

    // Filter values
    public ?int $filterKategoriId = null;

    public ?int $filterBrandId = null;

    public ?int $filterKualitasId = null;

    public ?int $filterTipeHpId = null;

    public ?string $filterBrandKompatibel = null;

    public ?string $filterModelKompatibel = null;

    public ?string $filterKondisi = null;

    public ?string $filterSatuan = null;

    public ?string $filterAbcClass = null;

    public bool $filterHanyaReadyStok = false;

    // Filter toggle preferences (checkbox custom)
    public array $visibleFilters = [
        'kategori' => false,
        'brand' => false,
        'kualitas' => false,
        'tipe_hp' => false,
        'brand_kompatibel' => false,
        'model_kompatibel' => false,
        'kondisi' => false,
        'satuan' => false,
        'abc_class' => false,
        'stok' => false,
    ];

    public bool $showCustomFilterSettings = false;

    protected $listeners = [
        'open-global-product-search' => 'openModal',
        'buka-pencarian-produk' => 'openModal',
        'close-global-product-search' => 'closeModal',
    ];

    public function mount($emitTo = null, $targetIndex = null, $context = 'general')
    {
        $this->emitTo = $emitTo;
        $this->targetIndex = $targetIndex;
        $this->context = $context;

        $this->loadUserPreferences();
    }

    public function loadUserPreferences(): void
    {
        $userId = auth()->id();
        if ($userId) {
            $pref = UserPreference::getPref($userId, 'product_search_custom_filters');
            if (is_array($pref)) {
                $this->visibleFilters = array_merge($this->visibleFilters, $pref);
            }
        }
    }

    public function updatedVisibleFilters(): void
    {
        $this->simpanPreferensi();
    }

    public function toggleFilter(string $key): void
    {
        if (array_key_exists($key, $this->visibleFilters)) {
            $this->visibleFilters[$key] = ! $this->visibleFilters[$key];
            if (! $this->visibleFilters[$key]) {
                // Reset filter value if turned off
                match ($key) {
                    'kategori' => $this->filterKategoriId = null,
                    'brand' => $this->filterBrandId = null,
                    'kualitas' => $this->filterKualitasId = null,
                    'tipe_hp' => $this->filterTipeHpId = null,
                    'brand_kompatibel' => $this->filterBrandKompatibel = null,
                    'model_kompatibel' => $this->filterModelKompatibel = null,
                    'kondisi' => $this->filterKondisi = null,
                    'satuan' => $this->filterSatuan = null,
                    'abc_class' => $this->filterAbcClass = null,
                    'stok' => $this->filterHanyaReadyStok = false,
                    default => null,
                };
                $this->resetPage();
            }
            $this->simpanPreferensi();
        }
    }

    public function resetCustomFilters(): void
    {
        $this->visibleFilters = [
            'kategori' => false,
            'brand' => false,
            'kualitas' => false,
            'tipe_hp' => false,
            'brand_kompatibel' => false,
            'model_kompatibel' => false,
            'kondisi' => false,
            'satuan' => false,
            'abc_class' => false,
            'stok' => false,
        ];

        $this->filterKategoriId = null;
        $this->filterBrandId = null;
        $this->filterKualitasId = null;
        $this->filterTipeHpId = null;
        $this->filterBrandKompatibel = null;
        $this->filterModelKompatibel = null;
        $this->filterKondisi = null;
        $this->filterSatuan = null;
        $this->filterAbcClass = null;
        $this->filterHanyaReadyStok = false;
        $this->resetPage();

        $userId = auth()->id();
        if ($userId) {
            UserPreference::setPref($userId, 'product_search_custom_filters', $this->visibleFilters);
        }
    }

    protected function simpanPreferensi(): void
    {
        $userId = auth()->id();
        if ($userId) {
            UserPreference::setPref($userId, 'product_search_custom_filters', $this->visibleFilters);
        }
    }

    #[On('open-global-product-search')]
    #[On('buka-pencarian-produk')]
    public function openModal($targetIndex = null, $context = 'general')
    {
        if (is_array($targetIndex)) {
            $context = $targetIndex['context'] ?? 'general';
            $targetIndex = $targetIndex['targetIndex'] ?? null;
        }

        $this->reset(['search', 'selectedProductId']);
        $this->targetIndex = $targetIndex;
        $this->context = $context;
        $this->isOpen = true;
        $this->loadUserPreferences();
        $this->dispatch('open-product-search-modal');
    }

    #[On('close-global-product-search')]
    public function closeModal()
    {
        $this->isOpen = false;
        $this->dispatch('close-product-search-modal');
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterKategoriId()
    {
        $this->resetPage();
    }

    public function updatedFilterBrandId()
    {
        $this->resetPage();
    }

    public function updatedFilterKualitasId()
    {
        $this->resetPage();
    }

    public function updatedFilterTipeHpId()
    {
        $this->resetPage();
    }

    public function updatedFilterBrandKompatibel()
    {
        $this->resetPage();
    }

    public function updatedFilterModelKompatibel()
    {
        $this->resetPage();
    }

    public function updatedFilterKondisi()
    {
        $this->resetPage();
    }

    public function updatedFilterSatuan()
    {
        $this->resetPage();
    }

    public function updatedFilterAbcClass()
    {
        $this->resetPage();
    }

    public function updatedFilterHanyaReadyStok()
    {
        $this->resetPage();
    }

    public function pilihProduk($productId)
    {
        $this->selectedProductId = $productId;

        $this->dispatch('produk-dipilih-global', [
            'targetIndex' => $this->targetIndex,
            'context' => $this->context,
            'produkId' => $productId,
        ]);

        $this->closeModal();
    }

    public function render()
    {
        $cabangId = session('cabang_id');

        $query = Produk::query()
            ->with([
                'skuVariants' => fn ($q) => $q->where('is_active', true),
                'brand',
                'kategoriRelasi',
                'kualitas',
            ])
            ->withSum('stokItems as total_stok', 'jumlah')
            ->where('is_active', true);

        if (! empty($this->search)) {
            $query->cariPintar($this->search);
        }

        if ($this->visibleFilters['kategori'] && $this->filterKategoriId) {
            $kat = KategoriProduk::with('children')->find($this->filterKategoriId);
            $katIds = $kat ? array_merge([$kat->id], $kat->children->pluck('id')->all()) : [$this->filterKategoriId];
            $query->whereIn('kategori_id', $katIds);
        }

        if ($this->visibleFilters['brand'] && $this->filterBrandId) {
            $query->where('brand_id', $this->filterBrandId);
        }

        if ($this->visibleFilters['kualitas'] && $this->filterKualitasId) {
            $query->where('kualitas_id', $this->filterKualitasId);
        }

        if ($this->visibleFilters['tipe_hp'] && $this->filterTipeHpId) {
            $query->whereHas('tipeHps', fn ($q) => $q->where('tipe_hp.id', $this->filterTipeHpId));
        }

        if ($this->visibleFilters['brand_kompatibel'] && $this->filterBrandKompatibel) {
            $query->where('brand_kompatibel', $this->filterBrandKompatibel);
        }

        if ($this->visibleFilters['model_kompatibel'] && $this->filterModelKompatibel) {
            $query->where('model_kompatibel', $this->filterModelKompatibel);
        }

        if ($this->visibleFilters['kondisi'] && $this->filterKondisi) {
            $query->where('kondisi', $this->filterKondisi);
        }

        if ($this->visibleFilters['satuan'] && $this->filterSatuan) {
            $query->where('satuan', $this->filterSatuan);
        }

        if ($this->visibleFilters['abc_class'] && $this->filterAbcClass) {
            $query->where('abc_class', $this->filterAbcClass);
        }

        if ($this->visibleFilters['stok'] && $this->filterHanyaReadyStok) {
            $query->whereHas('stokItems', fn ($q) => $q->where('jumlah', '>', 0));
        }

        $products = $query
            ->orderBy('nama')
            ->paginate(20);

        $brandKompatibelList = TipeHp::where('is_active', true)
            ->distinct()
            ->pluck('merk')
            ->merge(Brand::where('is_active', true)->pluck('nama'))
            ->merge(Produk::whereNotNull('brand_kompatibel')->where('brand_kompatibel', '!=', '')->distinct()->pluck('brand_kompatibel'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $modelKompatibelList = TipeHp::where('is_active', true)
            ->distinct()
            ->pluck('model')
            ->merge(Produk::whereNotNull('model_kompatibel')->where('model_kompatibel', '!=', '')->distinct()->pluck('model_kompatibel'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $config = app(KonfigurasiService::class);
        $kondisiList = $config->get('master_produk_kondisi_list') ?? [
            ['kode' => 'baru', 'nama' => 'Baru'],
            ['kode' => 'oem', 'nama' => 'OEM'],
            ['kode' => 'compatible', 'nama' => 'Compatible'],
            ['kode' => 'bekas', 'nama' => 'Bekas / Copotan'],
        ];

        return view('modules.wms.livewire.product-search-modal', [
            'products' => $products,
            'kategoriList' => KategoriProduk::whereNull('parent_id')->with('children')->where('is_active', true)->get(),
            'brandList' => Brand::where('is_active', true)->orderBy('nama')->get(),
            'kualitasList' => KualitasProduk::where('is_active', true)->orderBy('nama')->get(),
            'tipeHpList' => TipeHp::where('is_active', true)->orderBy('merk')->orderBy('model')->get(),
            'brandKompatibelList' => $brandKompatibelList,
            'modelKompatibelList' => $modelKompatibelList,
            'kondisiList' => $kondisiList,
            'satuanList' => SatuanUnit::where('is_active', true)->orderBy('nama')->get(),
        ]);
    }
}
