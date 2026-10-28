<?php

namespace App\Modules\Wms\Livewire;

use App\Models\UserPreference;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\TipeHp;
use App\Modules\Wms\Services\ScanResolverService;
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

    public ?int $curatedSupplierId = null;

    public string $supplierScope = 'curated'; // 'curated' (Supplier PO) | 'all' (Semua Produk)

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

    public string $filterStokStatus = 'semua'; // 'semua', 'ready', 'menipis', 'habis'

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
                    'stok' => [
                        $this->filterHanyaReadyStok = false,
                        $this->filterStokStatus = 'semua',
                    ],
                    default => null,
                };
                $this->resetPage();
            }
            $this->simpanPreferensi();
        }
    }

    public function resetSingleFilter(string $key): void
    {
        match ($key) {
            'search' => $this->search = '',
            'kategori' => $this->filterKategoriId = null,
            'brand' => $this->filterBrandId = null,
            'kualitas' => $this->filterKualitasId = null,
            'tipe_hp' => $this->filterTipeHpId = null,
            'brand_kompatibel' => $this->filterBrandKompatibel = null,
            'model_kompatibel' => $this->filterModelKompatibel = null,
            'kondisi' => $this->filterKondisi = null,
            'satuan' => $this->filterSatuan = null,
            'abc_class' => $this->filterAbcClass = null,
            'stok' => [
                $this->filterHanyaReadyStok = false,
                $this->filterStokStatus = 'semua',
            ],
            default => null,
        };
        $this->resetPage();
    }

    public function resetAllFilters(): void
    {
        $this->search = '';
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
        $this->filterStokStatus = 'semua';
        $this->resetPage();
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
        $this->filterStokStatus = 'semua';
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
    public function openModal($targetIndex = null, $context = 'general', $supplierId = null)
    {
        if (is_array($targetIndex)) {
            $context = $targetIndex['context'] ?? 'general';
            $supplierId = $targetIndex['supplierId'] ?? $supplierId;
            $targetIndex = $targetIndex['targetIndex'] ?? null;
        }

        $this->reset(['search', 'selectedProductId']);
        $this->targetIndex = $targetIndex;
        $this->context = $context;
        $this->curatedSupplierId = $supplierId ? (int) $supplierId : null;
        $this->supplierScope = ($this->curatedSupplierId && $this->context === 'po') ? 'curated' : 'all';
        $this->isOpen = true;
        $this->loadUserPreferences();
        $this->dispatch('open-product-search-modal');
    }

    public function setSupplierScope(string $scope): void
    {
        $this->supplierScope = in_array($scope, ['curated', 'all'], true) ? $scope : 'all';
        $this->resetPage();
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
        if ($this->filterKualitasId) {
            $k = KualitasProduk::find($this->filterKualitasId);
            if ($k && $k->brand_id && $this->filterBrandId && (int) $k->brand_id !== (int) $this->filterBrandId) {
                $this->filterKualitasId = null;
            }
        }
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

    public function scanBarcodeDirect(string $code): void
    {
        $term = trim($code);
        if ($term === '') {
            return;
        }

        // Resolusi Barcode / QR Code secara terstandarisasi
        $resolver = app(ScanResolverService::class);
        $result = $resolver->resolveProduct($term);

        if ($result['found'] && $result['produk']) {
            $this->pilihProduk($result['produk']->id);
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Produk ditemukan: {$result['produk']->nama}",
            ]);
        } else {
            // Jika bukan exact match, masukkan ke search query agar menampilkan hasil pencarian mirip
            $cleanTerm = $result['search_term'] ?: $term;
            $this->search = $cleanTerm;
            $this->resetPage();
            $this->dispatch('alert', [
                'type' => 'info',
                'message' => "Mencari '{$cleanTerm}'...",
            ]);
        }
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
                'supplier',
                'satuanKonversi',
            ])
            ->withSum('stokItems as total_stok', 'jumlah')
            ->where('is_active', true);

        $filters = [
            'search' => $this->search,
            'kategori_id' => $this->visibleFilters['kategori'] ? $this->filterKategoriId : null,
            'brand_id' => $this->visibleFilters['brand'] ? $this->filterBrandId : null,
            'kualitas_id' => $this->visibleFilters['kualitas'] ? $this->filterKualitasId : null,
            'tipe_hp_id' => $this->visibleFilters['tipe_hp'] ? $this->filterTipeHpId : null,
            'brand_kompatibel' => $this->visibleFilters['brand_kompatibel'] ? $this->filterBrandKompatibel : null,
            'model_kompatibel' => $this->visibleFilters['model_kompatibel'] ? $this->filterModelKompatibel : null,
            'kondisi' => $this->visibleFilters['kondisi'] ? $this->filterKondisi : null,
            'satuan' => $this->visibleFilters['satuan'] ? $this->filterSatuan : null,
            'abc_class' => $this->visibleFilters['abc_class'] ? $this->filterAbcClass : null,
            'stok_status' => $this->visibleFilters['stok'] && $this->filterStokStatus !== 'semua' ? $this->filterStokStatus : null,
            'hanya_ready_stok' => $this->visibleFilters['stok'] && ($this->filterHanyaReadyStok || $this->filterStokStatus === 'ready'),
        ];

        if ($this->context === 'po' && $this->curatedSupplierId && $this->supplierScope === 'curated') {
            $filters['supplier_id'] = $this->curatedSupplierId;
        }

        $query->filterKatalog($filters);

        if (empty($this->search)) {
            $query->orderBy('nama');
        }

        $products = $query->paginate(20);

        $selectedMerk = $this->filterBrandKompatibel;

        $brandKompatibelList = TipeHp::where('is_active', true)
            ->distinct()
            ->pluck('merk')
            ->merge(Brand::where('is_active', true)->pluck('nama'))
            ->merge(
                Produk::whereNotNull('brand_kompatibel')
                    ->where('brand_kompatibel', '!=', '')
                    ->pluck('brand_kompatibel')
                    ->flatMap(fn ($b) => preg_split('/[,;\/]+/', (string) $b))
                    ->map(fn ($b) => trim($b))
            )
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $modelHpQuery = Produk::whereNotNull('model_kompatibel')
            ->where('model_kompatibel', '!=', '');

        if (! empty($selectedMerk)) {
            $modelHpQuery->where(fn ($sub) => $sub->where('brand_kompatibel', $selectedMerk)->orWhere('brand_kompatibel', 'like', "%{$selectedMerk}%"))
                ->whereDoesntHave('tipeHps')
                ->where(function ($q) {
                    $q->whereNull('kompatibilitas_hp')
                        ->orWhere('kompatibilitas_hp', '[]')
                        ->orWhere('kompatibilitas_hp', '');
                });
        }

        $modelKompatibelList = TipeHp::where('is_active', true)
            ->when($selectedMerk, fn ($q) => $q->where('merk', $selectedMerk))
            ->distinct()
            ->pluck('model')
            ->merge(
                $modelHpQuery->pluck('model_kompatibel')
                    ->flatMap(fn ($m) => preg_split('/[,;\/]+/', (string) $m))
                    ->map(fn ($m) => trim($m))
            )
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

        $curatedSupplier = $this->curatedSupplierId ? Supplier::find($this->curatedSupplierId) : null;
        $curatedProductCount = 0;
        if ($curatedSupplier) {
            $curatedProductCount = Produk::where('is_active', true)->where('supplier_id', $this->curatedSupplierId)->count();
        }

        return view('modules.wms.livewire.product-search-modal', [
            'products' => $products,
            'curatedSupplier' => $curatedSupplier,
            'curatedProductCount' => $curatedProductCount,
            'supplierScope' => $this->supplierScope,
            'kategoriList' => KategoriProduk::whereNull('parent_id')->with('children')->where('is_active', true)->get(),
            'brandList' => Brand::where('is_active', true)->orderBy('nama')->get(),
            'kualitasList' => KualitasProduk::with('brand')
                ->where('is_active', true)
                ->when($this->filterBrandId, fn ($q) => $q->forBrand($this->filterBrandId))
                ->orderByRaw('brand_id IS NULL ASC')
                ->orderBy('nama')
                ->get(),
            'tipeHpList' => TipeHp::where('is_active', true)->orderBy('merk')->orderBy('model')->get(),
            'brandKompatibelList' => $brandKompatibelList,
            'modelKompatibelList' => $modelKompatibelList,
            'kondisiList' => $kondisiList,
            'satuanList' => SatuanUnit::where('is_active', true)->orderBy('nama')->get(),
        ]);
    }
}
