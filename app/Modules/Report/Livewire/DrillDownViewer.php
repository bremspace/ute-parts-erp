<?php

namespace App\Modules\Report\Livewire;

use App\Modules\Akunting\Jobs\ExportLaporanJob;
use App\Modules\Report\Services\ReportBuilderService;
use Livewire\Component;

/**
 * [F2-4] Drill-down viewer Livewire component.
 * Navigates the drill-down chain: model → records → row detail → transaction/item.
 */
class DrillDownViewer extends Component
{
    public string $currentModel = '';

    public array $items = [];

    public array $breadcrumbs = [];

    public int $currentPage = 1;

    public int $perPage = 20;

    public array $filters = [];

    public ?int $selectedItemId = null;

    public array $detail = [];

    protected $listeners = ['drillDown', 'refreshDrillDown', 'backFromDetail'];

    public function mount(?string $model = null, ?int $id = null): void
    {
        $this->currentModel = $model ?: 'Transaksi';
        $this->selectedItemId = $id;
        $this->loadRecords();

        // [P2-8] {id} pada URL /laporan/drill/{model}/{id} wajib membuka detail —
        // sebelumnya id diterima tapi diabaikan (list tetap tampil).
        // loadDetail() sudah menegakkan guard 403 item lintas cabang.
        if ($id) {
            $this->loadDetail($this->currentModel, $id);
        }
    }

    public function drillDown(string $model, ?int $itemId = null): void
    {
        $service = app(ReportBuilderService::class);

        try {
            $service->validateModel($model);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->currentModel = $model;

        if ($itemId) {
            $this->selectedItemId = $itemId;
            $this->loadDetail($model, $itemId);
        } else {
            $this->selectedItemId = null;
            $this->loadRecords();
        }
    }

    public function loadRecords(): void
    {
        $service = app(ReportBuilderService::class);
        $cabangId = session('cabang_id');

        try {
            $service->validateModel($this->currentModel);
        } catch (\InvalidArgumentException $e) {
            return;
        }

        $query = $service->buildQuery(
            $this->currentModel,
            ['*'],
            $this->filters ?: null,
            null,
            $cabangId
        );
        // [P0-3] Scope cabang sudah diterapkan di buildQuery via CABANG_SCOPE.

        $this->items = $query->paginate($this->perPage, ['*'], 'page', $this->currentPage)
            ->items();

        // Build breadcrumbs
        $this->breadcrumbs = array_map(function ($m) {
            return ['label' => app(ReportBuilderService::class)->getModelLabel($m), 'model' => $m];
        }, $service->getDrillChain($this->currentModel));
    }

    public function loadDetail(string $model, int $itemId): void
    {
        $service = app(ReportBuilderService::class);
        try {
            $service->validateModel($model);
        } catch (\InvalidArgumentException $e) {
            return;
        }

        $modelClass = ReportBuilderService::MODEL_MAP[$model];
        $item = $modelClass::find($itemId);

        if (! $item) {
            $this->detail = [];

            return;
        }

        // [P0-2] IDOR guard — baris yang ditemukan WAJIB berada di scope cabang
        // aktif (peta CABANG_SCOPE yang sama dengan buildQuery). Selain itu → 403.
        if (! $service->itemInCabang($model, $item, session('cabang_id'))) {
            abort(403, 'Anda tidak punya akses ke data cabang lain');
        }

        $this->detail = $item->toArray();
    }

    public function back(): void
    {
        if (count($this->breadcrumbs) > 1) {
            array_pop($this->breadcrumbs);
            $prev = end($this->breadcrumbs);
            $this->currentModel = $prev['model'];
            $this->selectedItemId = null;
            $this->detail = [];
            $this->loadRecords();
        }
    }

    /**
     * [F2-5] Export laporan transaksi via queue — hanya di list view model Transaksi
     * (surface laporan transaksi; async, jangan sinkron di request).
     */
    public function exportLaporan(string $format = 'xlsx'): void
    {
        if ($this->currentModel !== 'Transaksi') {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Export hanya tersedia untuk laporan transaksi']);

            return;
        }
        if (! auth()->user()?->can('laporan.cabang')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak punya izin export laporan']);

            return;
        }

        dispatch(new ExportLaporanJob(
            jenis: 'transaksi',
            periodeDari: null,
            periodeSampai: null,
            cabangId: session('cabang_id'),
            akunId: null,
            userId: auth()->id(),
            format: $format === 'csv' ? 'csv' : 'xlsx',
        ));

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Export transaksi diantre — notifikasi + link unduh muncul setelah selesai.',
        ]);
    }

    public function render()
    {
        $modelClass = ReportBuilderService::MODEL_MAP[$this->currentModel] ?? null;
        $modelObj = $modelClass ? new $modelClass : null;
        $hasCabang = $modelObj && in_array('cabang_id', $modelObj->getFillable() ?? []);

        return view('modules.report.livewire.drill-down', [
            'items' => $this->items,
            'breadcrumbs' => $this->breadcrumbs,
            'currentModel' => $this->currentModel,
            'detail' => $this->detail,
            'hasCabang' => $hasCabang,
            'cabangId' => session('cabang_id'),
        ])->layout('layouts.backoffice', ['header' => 'Drill-Down Laporan']);
    }
}
