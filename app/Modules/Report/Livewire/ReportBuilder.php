<?php

namespace App\Modules\Report\Livewire;

use App\Modules\Report\Jobs\ReportExportJob;
use App\Modules\Report\Models\SavedReport;
use App\Modules\Report\Services\ReportBuilderService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;

/**
 * [F2-4] Custom Report Builder Livewire component.
 * User selects whitelisted model, columns, filters, group, exports via queue.
 */
class ReportBuilder extends Component
{
    public string $sourceModel = '';

    public array $selectedColumns = [];

    public array $filters = [];

    public array $groupBy = [];

    public string $exportFormat = 'xlsx';

    public string $reportName = '';

    public array $availableColumns = [];

    public array $whitelist = [];

    public bool $showResults = false;

    public array $queryResults = [];

    /**
     * [P1-10] Kolom yang benar-benar ada di hasil query
     * (berbeda dari selectedColumns saat groupBy → kolom tergrup + jumlah).
     */
    public array $resultColumns = [];

    public bool $exporting = false;

    /**
     * [P2-4] Simpan laporan bersama cabang? Default true (checkbox pada form simpan).
     * Visibilitas: (user_id = saya) OR (cabang_id = aktif AND shared = true).
     */
    public bool $shared = true;

    protected $listeners = ['refreshResults', 'exportReport'];

    public function mount(): void
    {
        $this->whitelist = app(ReportBuilderService::class)->getWhitelist();
    }

    public function updatedSourceModel(string $model): void
    {
        $this->sourceModel = $model;

        if (empty($model)) {
            $this->availableColumns = [];
            $this->selectedColumns = [];
            $this->filters = [];
            $this->groupBy = [];
            $this->showResults = false;

            return;
        }

        $this->availableColumns = app(ReportBuilderService::class)->getAvailableColumns($model);
        $this->selectedColumns = array_keys($this->availableColumns);
        $this->filters = [];
        $this->groupBy = [];
        $this->showResults = false;
    }

    /**
     * [P0-5] Tambah baris filter kosong — kontrak {field, op, value}.
     */
    public function addFilter(): void
    {
        $this->filters[] = ['field' => '', 'op' => '=', 'value' => ''];
    }

    /**
     * [P0-5] Hapus baris filter pada index, rapikan kembali index-nya.
     */
    public function removeFilter(int $index): void
    {
        unset($this->filters[$index]);
        $this->filters = array_values($this->filters);
    }

    /**
     * [P0-5] Navigasi ke DrillDownViewer yang sudah ada: /laporan/drill/{model}.
     */
    public function drillDown(string $model): void
    {
        $this->redirectRoute('laporan.drill', ['model' => $model]);
    }

    /**
     * [P0-5] Listener refreshResults — bangun ulang hasil laporan.
     */
    public function refreshResults(): void
    {
        $this->buildAndShow();
    }

    /**
     * [P0-5] Listener exportReport — dispatch export via queue.
     */
    public function exportReport(): void
    {
        $this->export();
    }

    public function buildAndShow(): void
    {
        if (empty($this->sourceModel)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih sumber data terlebih dahulu']);

            return;
        }

        try {
            app(ReportBuilderService::class)->validateModel($this->sourceModel);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        $service = app(ReportBuilderService::class);
        $cabangId = session('cabang_id');

        $query = $service->buildQuery(
            $this->sourceModel,
            $this->selectedColumns,
            $this->filters ?: null,
            $this->groupBy ?: null,
            $cabangId
        );

        // Limit to 500 rows for display
        $rawResults = $query->limit(500)->get()->toArray();
        $this->queryResults = $service->formatRowsForDisplay($rawResults);

        // [P1-10] Header/isi tabel mengikuti kolom hasil aktual
        // (saat groupBy: kolom tergrup + 'jumlah', bukan selectedColumns mentah).
        $this->resultColumns = $this->queryResults !== []
            ? array_keys($this->queryResults[0])
            : $this->selectedColumns;

        $this->showResults = true;
    }

    public function export()
    {
        if (empty($this->sourceModel)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih sumber data terlebih dahulu']);

            return null;
        }

        try {
            app(ReportBuilderService::class)->validateModel($this->sourceModel);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);

            return null;
        }

        $service = app(ReportBuilderService::class);
        $cabangId = session('cabang_id');
        $userId = auth()->id() ?? 0;

        $reportName = trim($this->reportName);
        if ($reportName === '') {
            $label = $this->availableModels[$this->sourceModel]['label'] ?? class_basename($this->sourceModel);
            $reportName = 'Laporan '.$label.' '.now()->format('d-m-Y');
            $this->reportName = $reportName;
        }

        $format = $this->exportFormat === 'csv' ? 'csv' : 'xlsx';

        // Dispatch QUEUE job — async background processing & audit
        dispatch(new ReportExportJob(
            modelName: $this->sourceModel,
            columns: $this->selectedColumns,
            filters: $this->filters ?: null,
            groupBy: $this->groupBy ?: null,
            cabangId: $cabangId,
            format: $format,
            userId: $userId,
            reportName: $reportName
        ));

        // Generate file langsung untuk download instan ke browser
        $query = $service->buildQuery(
            $this->sourceModel,
            $this->selectedColumns ?: ['*'],
            $this->filters ?: null,
            $this->groupBy ?: null,
            $cabangId
        );

        $limit = ReportExportJob::ROW_LIMIT;
        $rawRows = $query->limit($limit)->get()
            ->map(fn ($item) => $item instanceof Model ? $item->toArray() : (array) $item)
            ->all();

        $rows = $service->formatRowsForDisplay($rawRows);
        $exportRows = $service->formatRowsForExport($rows);

        $ext = $format;
        $slug = Str::slug($reportName) ?: 'laporan';
        $filename = $userId.'_laporan_'.$slug.'-'.now()->format('Ymd-His').'.'.$ext;
        $path = 'exports/'.$filename;

        if ($format === 'csv') {
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

        $this->exporting = false;
        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Export laporan "'.$reportName.'" berhasil — berkas mulai diunduh.',
        ]);

        $fullPath = Storage::disk('local')->path($path);

        return response()->download($fullPath, $slug.'.'.$ext);
    }

    public function saveReport(): void
    {
        if (empty($this->reportName) || empty($this->sourceModel)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Isi nama laporan dan pilih sumber data']);

            return;
        }

        $service = app(ReportBuilderService::class);
        try {
            $service->validateModel($this->sourceModel);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        $cabangId = session('cabang_id');

        SavedReport::create([
            'name' => $this->reportName,
            'source_model' => $this->sourceModel,
            'columns' => $this->selectedColumns,
            'filters' => $this->filters ?: null,
            'group_by' => $this->groupBy ?: null,
            'export_format' => $this->exportFormat,
            'user_id' => auth()->id(),
            'cabang_id' => $cabangId,
            'shared' => $this->shared,
        ]);

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Laporan "'.$this->reportName.'" berhasil disimpan']);
    }

    public function deleteReport(int $reportId): void
    {
        $report = SavedReport::findOrFail($reportId);
        $user = auth()->user();

        // Hak akses hapus:
        // 1. Pemilik laporan (user pembuat)
        // 2. Super Admin (akses penuh seluruh sistem)
        // 3. Admin Toko (laporan bersama di cabang yang sama)
        $canDelete = $report->user_id === auth()->id()
            || ($user && $user->hasRole('super-admin'))
            || ($user && $user->hasRole('admin-toko') && (int) $report->cabang_id === (int) session('cabang_id'));

        if (! $canDelete) {
            abort(403, 'Anda tidak memiliki hak akses untuk menghapus laporan ini');
        }

        $report->delete();
        $this->dispatch('toast', type: 'success', message: 'Laporan berhasil dihapus');
    }

    public function render()
    {
        // [P2-4] Dua grup AND: laporan milik saya (apa pun shared/cabangnya)
        // ATAU laporan cabang aktif yang di-share (shared = 1).
        // Query lama `user_id = me OR cabang_id = X` membocorkan laporan
        // user lain (nama + konfigurasi) di cabang yang sama.
        $cabangId = session('cabang_id');
        $savedReports = SavedReport::query()
            ->where(function ($q) use ($cabangId) {
                $q->where('user_id', auth()->id())
                    ->when(
                        $cabangId,
                        fn ($qq) => $qq->orWhere(function ($qShared) use ($cabangId) {
                            $qShared->where('cabang_id', $cabangId)->where('shared', true);
                        })
                    );
            })
            ->latest()
            ->get();

        return view('modules.report.livewire.report-builder', [
            'savedReports' => $savedReports,
            'whitelist' => $this->whitelist,
            'resultColumns' => $this->resultColumns,
        ])->layout('layouts.backoffice', ['header' => 'Laporan Kustom']);
    }
}
