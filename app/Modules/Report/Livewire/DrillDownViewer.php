<?php

namespace App\Modules\Report\Livewire;

use App\Modules\Akunting\Jobs\ExportLaporanJob;
use App\Modules\Report\Jobs\ReportExportJob;
use App\Modules\Report\Services\ReportBuilderService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;

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

    public string $periodeDari = '';

    public string $periodeSampai = '';

    public ?int $selectedItemId = null;

    public array $detail = [];

    public array $childItems = [];

    public ?string $childModel = null;

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
        $this->currentPage = 1;

        if ($itemId) {
            $this->selectedItemId = $itemId;
            $this->loadDetail($model, $itemId);
        } else {
            $this->selectedItemId = null;
            $this->loadRecords();
        }
    }

    public function updatedPeriodeDari(): void
    {
        $this->currentPage = 1;
        $this->loadRecords();
    }

    public function updatedPeriodeSampai(): void
    {
        $this->currentPage = 1;
        $this->loadRecords();
    }

    public function resetPeriode(): void
    {
        $this->periodeDari = '';
        $this->periodeSampai = '';
        $this->currentPage = 1;
        $this->loadRecords();
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

        $dateCol = $service->getDateColumnForModel($this->currentModel);
        if ($dateCol) {
            if ($this->periodeDari !== '') {
                $query->whereDate($dateCol, '>=', $this->periodeDari);
            }
            if ($this->periodeSampai !== '') {
                $query->whereDate($dateCol, '<=', $this->periodeSampai);
            }
        }

        $rawItems = collect($query->paginate($this->perPage, ['*'], 'page', $this->currentPage)->items())
            ->map(fn ($item) => $item instanceof Model ? $item->toArray() : (array) $item)
            ->all();

        $this->items = $service->formatRowsForDisplay($rawItems, $this->currentModel);

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

        $this->detail = $service->formatRowForDisplay($item->toArray(), $model);

        $this->childModel = $service->getDrillDownTarget($model);
        $this->childItems = [];

        if ($this->childModel && isset(ReportBuilderService::MODEL_MAP[$this->childModel])) {
            $childClass = ReportBuilderService::MODEL_MAP[$this->childModel];
            $childForeignKey = match ($model) {
                'Transaksi' => 'transaksi_id',
                'TiketServis' => 'tiket_servis_id',
                'PurchaseOrder' => 'purchase_order_id',
                'StokItem' => 'produk_id',
                'Produk' => 'produk_id',
                default => null,
            };

            if ($childForeignKey) {
                $rawChild = $childClass::where($childForeignKey, $item->id)->get()
                    ->map(fn ($c) => $c->toArray())
                    ->all();
                $this->childItems = $service->formatRowsForDisplay($rawChild, $this->childModel);
            }
        }
    }

    public function back(): void
    {
        if ($this->selectedItemId !== null) {
            $this->selectedItemId = null;
            $this->detail = [];
            $this->childItems = [];
            $this->childModel = null;
            $this->loadRecords();

            return;
        }

        if (count($this->breadcrumbs) > 1) {
            array_pop($this->breadcrumbs);
            $prev = end($this->breadcrumbs);
            $this->currentModel = $prev['model'];
            $this->selectedItemId = null;
            $this->detail = [];
            $this->childItems = [];
            $this->childModel = null;
            $this->loadRecords();
        }
    }

    /**
     * [F2-5] Export laporan drill-down via unduh langsung.
     * Mendukung seluruh model sumber data dengan formatting UI/UX dan header bahasa manusia.
     */
    public function exportLaporan(string $format = 'xlsx')
    {
        if (! auth()->user()?->can('laporan.cabang')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak punya izin export laporan']);

            return null;
        }

        $service = app(ReportBuilderService::class);
        try {
            $service->validateModel($this->currentModel);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);

            return null;
        }

        $cabangId = session('cabang_id');
        $userId = auth()->id() ?? 0;
        $fmt = $format === 'csv' ? 'csv' : 'xlsx';

        // Kompatibilitas dispatch queue untuk Transaksi (ExportLaporanJob)
        if ($this->currentModel === 'Transaksi') {
            dispatch(new ExportLaporanJob(
                jenis: 'transaksi',
                periodeDari: $this->periodeDari !== '' ? $this->periodeDari : null,
                periodeSampai: $this->periodeSampai !== '' ? $this->periodeSampai : null,
                cabangId: $cabangId,
                akunId: null,
                userId: $userId,
                format: $fmt,
            ));
        }

        if ($this->selectedItemId && ! empty($this->detail)) {
            // Ekspor detail tunggal beserta child items jika ada
            $headerDetail = $service->formatRowsForExport([$this->detail], $this->currentModel);
            $exportRows = $headerDetail;

            if (! empty($this->childItems) && $this->childModel) {
                $exportRows[] = []; // Baris pemisah
                $exportRows[] = ['--- RINCIAN ITEM ('.app(ReportBuilderService::class)->getModelLabel($this->childModel).') ---' => ''];
                $childExport = $service->formatRowsForExport($this->childItems, $this->childModel);
                foreach ($childExport as $cr) {
                    $exportRows[] = $cr;
                }
            }
        } else {
            $query = $service->buildQuery(
                $this->currentModel,
                ['*'],
                $this->filters ?: null,
                null,
                $cabangId
            );

            $dateCol = $service->getDateColumnForModel($this->currentModel);
            if ($dateCol) {
                if ($this->periodeDari !== '') {
                    $query->whereDate($dateCol, '>=', $this->periodeDari);
                }
                if ($this->periodeSampai !== '') {
                    $query->whereDate($dateCol, '<=', $this->periodeSampai);
                }
            }

            $limit = ReportExportJob::ROW_LIMIT;
            $rawRows = $query->limit($limit)->get()
                ->map(fn ($item) => $item instanceof Model ? $item->toArray() : (array) $item)
                ->all();

            $rows = $service->formatRowsForDisplay($rawRows, $this->currentModel);
            $exportRows = $service->formatRowsForExport($rows, $this->currentModel);
        }

        $slug = Str::slug($this->currentModel) ?: 'laporan';
        if ($this->selectedItemId) {
            $slug .= '_detail_'.$this->selectedItemId;
        }
        $filename = $userId.'_drilldown_'.$slug.'_'.now()->format('Ymd-His').'.'.$fmt;
        $path = 'exports/'.$filename;

        if ($fmt === 'csv') {
            $out = fopen('php://memory', 'r+');
            if (! empty($exportRows)) {
                fputcsv($out, array_keys($exportRows[0]));
                foreach ($exportRows as $row) {
                    $values = array_map(function ($val) {
                        if (is_array($val) || is_object($val)) {
                            return json_encode($val);
                        }

                        return (string) $val;
                    }, array_values($row));
                    fputcsv($out, $values);
                }
            }
            rewind($out);
            $csv = stream_get_contents($out);
            fclose($out);
            Storage::disk('local')->put($path, $csv);
        } else {
            Excel::store(new class($exportRows) implements FromArray, WithHeadings
            {
                public function __construct(private array $rows) {}

                public function headings(): array
                {
                    return ! empty($this->rows) ? array_keys($this->rows[0]) : [];
                }

                public function array(): array
                {
                    return array_map(fn ($r) => array_values((array) $r), $this->rows);
                }
            }, $path, 'local');
        }

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Export laporan '.$this->currentModel.' selesai — berkas mulai diunduh.',
        ]);

        $fullPath = Storage::disk('local')->path($path);

        return response()->download($fullPath, 'laporan-'.$slug.'-'.now()->format('Ymd-His').'.'.$fmt);
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
            'childItems' => $this->childItems,
            'childModel' => $this->childModel,
            'hasCabang' => $hasCabang,
            'cabangId' => session('cabang_id'),
        ])->layout('layouts.backoffice', ['header' => 'Drill-Down Laporan']);
    }
}
