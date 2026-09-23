<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\StokItem;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * [F1-8 / S-01] Tab "Inventori & Stok" — dipecah dari WmsDashboard (paritas perilaku).
 */
class StokTab extends Component
{
    use PunyaRiwayatAktivitas;
    use WithPagination;

    // Stok Tab filters
    public string $search = '';

    public string $kategori = '';

    public ?int $filterGudangId = null;

    // [T-12] Rak management
    public bool $showRakModal = false;

    public array $rakForm = ['gudang_id' => null, 'nama' => '', 'kode' => '', 'zona' => ''];

    public function mount()
    {
        $cabangId = session('cabang_id');
        if ($cabangId) {
            $this->filterGudangId = Gudang::where('cabang_id', $cabangId)->value('id');
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    // Quick action header "Atur Rak" (dari shell WmsDashboard via $dispatch)
    #[On('wms-rak-modal')]
    public function openRakModal(): void
    {
        $this->showRakModal = true;
    }

    public function simpanRak()
    {
        $this->validate([
            'rakForm.gudang_id' => 'required|exists:gudang,id',
            'rakForm.nama' => 'required|string|max:255',
            'rakForm.kode' => 'required|string|max:20|unique:rak,kode',
        ]);

        Rak::create($this->rakForm);
        $this->showRakModal = false;
        $this->rakForm = ['gudang_id' => null, 'nama' => '', 'kode' => '', 'zona' => ''];
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Rak ditambahkan']);
    }

    public function render()
    {
        $gudangs = Gudang::where('is_active', true)->get();

        // Stok Query
        $stokQuery = StokItem::with(['produk', 'skuVariant', 'gudang.cabang']);
        if ($this->filterGudangId) {
            $stokQuery->where('gudang_id', $this->filterGudangId);
        }
        if (! empty($this->search)) {
            $s = $this->search;
            $stokQuery->whereHas('produk', function ($q) use ($s) {
                $q->where('nama', 'like', "%{$s}%")
                    ->orWhere('brand_kompatibel', 'like', "%{$s}%")
                    ->orWhere('model_kompatibel', 'like', "%{$s}%");
            });
        }
        $stokItems = $stokQuery->paginate(15);

        return view('modules.wms.livewire.stok-tab', [
            'gudangs' => $gudangs,
            'stokItems' => $stokItems,
            'raks' => Rak::with('gudang')->get(),
        ]);
    }
}
