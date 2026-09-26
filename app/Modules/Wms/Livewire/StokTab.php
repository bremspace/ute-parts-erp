<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Akunting\Jobs\ExportLaporanJob;
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

    /** [B-03/P1-6] Gudang filter wajib milik cabang aktif (payload klien bisa lintas cabang). */
    public function updatedFilterGudangId(): void
    {
        if (! $this->filterGudangId) {
            return;
        }

        $cabangId = session('cabang_id');
        $sah = Gudang::where('id', $this->filterGudangId)
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->exists();

        if (! $sah) {
            $this->filterGudangId = null;
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => 'Gudang tidak sesuai cabang aktif — filter stok direset',
            ]);
        }
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

    /** [F2-5] Export laporan stok via queue (async — jangan sinkron di request). */
    public function exportLaporan(string $format = 'xlsx'): void
    {
        if (! auth()->user()?->can('laporan.cabang')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak punya izin export laporan']);

            return;
        }

        dispatch(new ExportLaporanJob(
            jenis: 'stok',
            periodeDari: null,
            periodeSampai: null,
            cabangId: session('cabang_id'),
            akunId: null,
            userId: auth()->id(),
            format: $format === 'csv' ? 'csv' : 'xlsx',
        ));

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Export stok diantre — notifikasi + link unduh muncul setelah selesai.',
        ]);
    }

    public function render()
    {
        $cabangId = session('cabang_id');

        // [B-03/P1-6] Dropdown gudang scoped ke cabang aktif — jangan tampilkan
        // (apalagi pakai) gudang cabang lain.
        $gudangs = Gudang::where('is_active', true)
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->orderBy('nama')
            ->get();

        // Filter gudang wajib milik cabang aktif (prop publik bisa di-tamper)
        $filterGudangId = $this->filterGudangId && $gudangs->contains('id', $this->filterGudangId)
            ? $this->filterGudangId
            : null;

        // Stok Query
        $stokQuery = StokItem::with(['produk', 'skuVariant', 'gudang.cabang']);
        if ($filterGudangId) {
            $stokQuery->where('gudang_id', $filterGudangId);
        } elseif ($cabangId) {
            // [B-03/P1-6] Fallback scoping: tanpa gudang terpilih → tetap batasi
            // stok ke gudang-gudang cabang aktif (jangan bocor lintas cabang)
            $stokQuery->whereHas('gudang', fn ($q) => $q->where('cabang_id', $cabangId));
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
