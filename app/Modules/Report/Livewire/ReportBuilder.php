<?php

namespace App\Modules\Report\Livewire;

use App\Modules\Report\Jobs\ReportExportJob;
use App\Modules\Report\Models\SavedReport;
use App\Modules\Report\Services\ReportBuilderService;
use Livewire\Component;

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
            $this->dispatch('toast', type: 'error', message: 'Pilih sumber data terlebih dahulu');

            return;
        }

        try {
            app(ReportBuilderService::class)->validateModel($this->sourceModel);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

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
        $this->queryResults = $query->limit(500)->get()->toArray();

        // [P1-10] Header/isi tabel mengikuti kolom hasil aktual
        // (saat groupBy: kolom tergrup + 'jumlah', bukan selectedColumns mentah).
        $this->resultColumns = $this->queryResults !== []
            ? array_keys($this->queryResults[0])
            : $this->selectedColumns;

        $this->showResults = true;
    }

    public function export(): void
    {
        if (empty($this->sourceModel)) {
            $this->dispatch('toast', type: 'error', message: 'Pilih sumber data terlebih dahulu');

            return;
        }

        if (empty($this->reportName)) {
            $this->dispatch('toast', type: 'error', message: 'Masukkan nama laporan');

            return;
        }

        try {
            app(ReportBuilderService::class)->validateModel($this->sourceModel);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $cabangId = session('cabang_id');

        // Dispatch QUEUE job — never sync
        dispatch(new ReportExportJob(
            modelName: $this->sourceModel,
            columns: $this->selectedColumns,
            filters: $this->filters ?: null,
            groupBy: $this->groupBy ?: null,
            cabangId: $cabangId,
            format: $this->exportFormat,
            userId: auth()->id(),
            reportName: $this->reportName
        ));

        $this->dispatch('toast', type: 'success', message: 'Export laporan "'.$this->reportName.'" diantri. Notifikasi akan muncul saat selesai.');
        $this->exporting = false;
    }

    public function saveReport(): void
    {
        if (empty($this->reportName) || empty($this->sourceModel)) {
            $this->dispatch('toast', type: 'error', message: 'Isi nama laporan dan pilih sumber data');

            return;
        }

        $service = app(ReportBuilderService::class);
        try {
            $service->validateModel($this->sourceModel);
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

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

        $this->dispatch('toast', type: 'success', message: 'Laporan "'.$this->reportName.'" berhasil disimpan');
    }

    public function deleteReport(int $reportId): void
    {
        $report = SavedReport::findOrFail($reportId);

        // Scope to owner
        if ($report->user_id !== auth()->id()) {
            abort(403, 'Anda tidak dapat menghapus laporan ini');
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
