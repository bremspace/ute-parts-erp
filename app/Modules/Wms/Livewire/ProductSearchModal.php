<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Wms\Models\Produk;
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

    public bool $isOpen = false; // [FIX] State modal (dicek test & UI)

    protected $listeners = [
        'open-global-product-search' => 'openModal',
        // [FIX] Dengarkan juga buka-pencarian-produk langsung (kontrak test).
        'buka-pencarian-produk' => 'openModal',
        'close-global-product-search' => 'closeModal',
    ];

    public function mount($emitTo = null, $targetIndex = null, $context = 'general')
    {
        $this->emitTo = $emitTo;
        $this->targetIndex = $targetIndex;
        $this->context = $context;
    }

    #[On('open-global-product-search')]
    #[On('buka-pencarian-produk')]
    public function openModal($targetIndex = null, $context = 'general')
    {
        // Dukung dua format dispatch (Livewire menyebarkan params sebagai
        // argumen individual):
        // - buka-pencarian-produk: argumen individual (targetIndex, context)
        // - open-global-product-search: satu array ['targetIndex' => ..., 'context' => ...]
        if (is_array($targetIndex)) {
            $context = $targetIndex['context'] ?? 'general';
            $targetIndex = $targetIndex['targetIndex'] ?? null;
        }

        $this->reset(['search', 'selectedProductId']);
        $this->targetIndex = $targetIndex;
        $this->context = $context;
        $this->isOpen = true;
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

    public function pilihProduk($productId)
    {
        $this->selectedProductId = $productId;

        // Emit global event for any listening component
        // [FIX] Urutan params HARUS sama dengan yang di-assert di test
        // (targetIndex, context, produkId) — matching assertDispatched
        // Livewire membandingkan array dengan === (order-sensitive).
        $this->dispatch('produk-dipilih-global', [
            'targetIndex' => $this->targetIndex,
            'context' => $this->context,
            'produkId' => $productId,
        ]);

        // Close modal after selection
        $this->closeModal();
    }

    public function render()
    {
        // [FIX] Produk adalah master global (tanpa cabang_id di tabel produk).
        // Modal pencarian menampilkan SEMUA produk aktif — bukan hanya yang
        // punya stok. Sebelumnya difilter scopeStokCabangAktif (stok>0 di
        // cabang) sehingga produk tanpa stok tidak muncul di pencarian.
        $query = Produk::query()
            ->with(['skuVariants', 'stokItems'])
            ->where('is_active', true);

        if (! empty($this->search)) {
            $query->cariPintar($this->search);
        }

        $products = $query
            ->orderBy('nama')
            ->paginate(20);

        return view('modules.wms.livewire.product-search-modal', [
            'products' => $products,
        ]);
    }
}
