<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\SupplierScore;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * [F3-2] Supplier Scoring — tabel skor supplier di form PO.
 *
 * RBAC: permission wms.view | wms.create.
 * Scoping KETAT: semua query scope cabang_id.
 */
class SupplierScoreTable extends Component
{
    use WithPagination;

    public string $search = '';

    public string $periode = '';

    public int $perPage = 10;

    protected $listeners = ['refreshScores' => '$refresh'];

    public function mount(): void
    {
        $this->periode = now()->format('Y-m');
    }

    public function getScoresProperty()
    {
        $query = SupplierScore::query()
            ->with('supplier');

        if ($this->search) {
            $query->whereHas('supplier', fn ($q) => $q->where('nama', 'like', '%'.$this->search.'%'));
        }

        if ($this->periode) {
            $query->where('periode', $this->periode);
        }

        return $query->orderByDesc('total_score')
            ->paginate($this->perPage);
    }

    public function getSuppliersProperty()
    {
        return Supplier::where('is_active', true)
            ->when(session('cabang_id'), fn ($q) => $q->where('cabang_id', session('cabang_id')))
            ->get();
    }

    public function refresh(): void
    {
        $this->emitSelf('refreshScores');
    }

    public function render()
    {
        return view('modules.wms.livewire.supplier-score-table', [
            // Legacy computed (`getScoresProperty()`) diakses sebagai `$this->scores`,
            // bukan `$this->scoresProperty` — nama property literal tidak ada di komponen.
            'scores' => $this->scores,
            'suppliers' => $this->suppliers,
        ])->layout('layouts.backoffice', ['header' => 'Skor Supplier']);
    }
}
