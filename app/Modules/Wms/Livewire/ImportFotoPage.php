<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Wms\Exports\BulkFotoSkippedExport;
use App\Modules\Wms\Jobs\ImportFotoMasalJob;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Services\BulkProductImageImportService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.backoffice', ['header' => 'WMS — Import Foto Massal'])]
class ImportFotoPage extends Component
{
    use WithFileUploads;

    public $fotoUploads = [];

    public $fotoZip = null;

    public string $activeTab = 'foto';

    public string $fotoSourceType = 'files';

    public string $fotoMode = 'append';

    public string $fotoStep = 'upload'; // 'upload' | 'preview' | 'processing' | 'completed'

    public array $fotoScanResult = [];

    public string $fotoFilterTab = 'all';

    public int $fotoBatchChunkSize = 8;

    public int $fotoProcessedCount = 0;

    public int $fotoSuccessCount = 0;

    public int $fotoErrorCount = 0;

    public ?string $fotoTempDir = null;

    public array $fotoCompletedSummary = [];

    public ?int $fotoImportLogId = null;

    public ?array $activeFotoImportLog = null;

    public array $resolusiErrorFoto = [];

    public string $fotoErrorFilterTab = 'semua';

    public int $fotoErrorDisplayLimit = 50;

    public function mount(): void
    {
        $runningFotoLog = ImportLog::where('user_id', auth()->id())
            ->where('tipe', 'produk_foto')
            ->where('status', 'proses')
            ->latest()
            ->first();

        if ($runningFotoLog) {
            $this->fotoImportLogId = $runningFotoLog->id;
            $this->fotoStep = 'processing';
            $this->cekStatusFotoImport();
        }
    }

    public function setFotoFilterTab(string $tab): void
    {
        $this->fotoFilterTab = in_array($tab, ['all', 'matched', 'skipped'], true) ? $tab : 'all';
    }

    public function scanUploadedFoto(): void
    {
        $bulkService = app(BulkProductImageImportService::class);

        if ($this->fotoSourceType === 'zip') {
            $this->validate([
                'fotoZip' => 'required|file|mimes:zip|max:512000',
            ], [
                'fotoZip.required' => 'Pilih berkas ZIP foto terlebih dahulu.',
                'fotoZip.mimes' => 'Berkas harus berupa arsip ZIP (.zip).',
                'fotoZip.max' => 'Ukuran berkas ZIP maksimal 500MB.',
            ]);

            $time = time();
            $folderName = 'tmp_foto_'.auth()->id().'_'.$time;
            $tempDir = Storage::disk('local')->path($folderName);
            if (! is_dir($tempDir)) {
                @mkdir($tempDir, 0775, true);
            }
            $this->fotoTempDir = $tempDir;
            $zipRealPath = $this->fotoZip->getRealPath();

            try {
                $extracted = $bulkService->extractZipArchive($zipRealPath, $tempDir);
                if (empty($extracted)) {
                    $this->dispatch('alert', ['type' => 'error', 'message' => 'Tidak ditemukan file gambar yang didukung di dalam berkas ZIP.']);

                    return;
                }
                $scan = $bulkService->preScanFiles($extracted, $this->fotoMode);
                $this->fotoScanResult = $scan;
                $this->fotoStep = 'preview';
            } catch (\Throwable $e) {
                $this->dispatch('alert', ['type' => 'error', 'message' => 'Gagal mengekstrak ZIP: '.$e->getMessage()]);

                return;
            }
        } else {
            $this->validate([
                'fotoUploads' => 'required|array|min:1',
                'fotoUploads.*' => 'file|max:15360',
            ], [
                'fotoUploads.required' => 'Pilih satu atau beberapa file foto terlebih dahulu.',
                'fotoUploads.min' => 'Minimal pilih 1 file foto.',
                'fotoUploads.*.max' => 'Ukuran file foto maksimal 15MB.',
            ]);

            $items = [];
            $time = time();
            $folderName = 'tmp_foto_'.auth()->id().'_'.$time;
            $tempDir = Storage::disk('local')->path($folderName);
            if (! is_dir($tempDir)) {
                @mkdir($tempDir, 0775, true);
            }
            $this->fotoTempDir = $tempDir;

            foreach ($this->fotoUploads as $idx => $file) {
                $origName = $file->getClientOriginalName();
                $tempStored = $file->storeAs($folderName, 'f_'.$idx.'_'.Str::slug(pathinfo($origName, PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension(), 'local');
                $absStored = Storage::disk('local')->path($tempStored);

                $items[] = [
                    'name' => $origName,
                    'relative_path' => null,
                    'size' => $file->getSize(),
                    'temp_path' => $absStored,
                ];
            }

            $scan = $bulkService->preScanFiles($items, $this->fotoMode);
            $this->fotoScanResult = $scan;
            $this->fotoStep = 'preview';
        }
    }

    public function scanFromStagingSession(string $sessionId): void
    {
        $cleanSessionId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sessionId);
        if ($cleanSessionId === '') {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'ID Sesi upload tidak valid.']);

            return;
        }

        $folderName = 'tmp_foto_'.auth()->id().'_'.$cleanSessionId;
        $tempDir = Storage::disk('local')->path($folderName);

        if (! is_dir($tempDir)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Direktori berkas upload tidak ditemukan atau telah kedaluwarsa.']);

            return;
        }

        $bulkService = app(BulkProductImageImportService::class);
        $items = $bulkService->scanStagingDirectory($tempDir);

        if (empty($items)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Tidak ditemukan berkas gambar yang didukung dalam sesi ini.']);

            return;
        }

        $this->fotoTempDir = $tempDir;
        $this->fotoScanResult = $bulkService->preScanFiles($items, $this->fotoMode);
        $this->fotoStep = 'preview';

        $totalFound = count($items);
        $totalMatched = $this->fotoScanResult['matched_count'] ?? 0;
        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Berhasil memindai {$totalFound} foto ({$totalMatched} cocok dengan SKU master).",
        ]);
    }

    public function startFotoProcessing(): void
    {
        $matched = $this->fotoScanResult['matched'] ?? [];
        if (empty($matched)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Tidak ada foto yang cocok untuk diproses.']);

            return;
        }

        $totalItems = count($matched);
        $fileName = $this->fotoSourceType === 'zip'
            ? ($this->fotoZip?->getClientOriginalName() ?? 'foto-archive.zip')
            : ($totalItems.' berkas foto');

        $log = ImportLog::create([
            'tipe' => 'produk_foto',
            'nama_file' => $fileName,
            'status' => 'proses',
            'user_id' => auth()->id(),
            'total_baris' => $totalItems,
        ]);

        $this->fotoImportLogId = $log->id;
        $this->fotoStep = 'processing';

        if ($totalItems <= 10) {
            ImportFotoMasalJob::dispatchSync(
                $log->id,
                $matched,
                auth()->id(),
                $this->fotoMode,
                $this->fotoTempDir,
                $this->resolusiErrorFoto
            );
        } else {
            ImportFotoMasalJob::dispatch(
                $log->id,
                $matched,
                auth()->id(),
                $this->fotoMode,
                $this->fotoTempDir,
                $this->resolusiErrorFoto
            );
        }

        $this->cekStatusFotoImport();

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => $totalItems <= 10 ? 'Import foto produk selesai diproses!' : 'Import foto sedang diproses oleh sistem di latar belakang.',
        ]);
    }

    public function cekStatusFotoImport(): void
    {
        if (! $this->fotoImportLogId) {
            return;
        }

        $log = ImportLog::find($this->fotoImportLogId);
        if ($log) {
            $totalBaris = (int) $log->total_baris;
            $sukses = (int) $log->sukses;
            $gagal = (int) $log->gagal;
            $selesai = $sukses + $gagal;
            $persen = $totalBaris > 0 ? (int) min(100, round(($selesai / $totalBaris) * 100)) : 0;

            $detailList = is_array($log->detail) ? $log->detail : [];
            $gagalRows = array_values(array_filter($detailList, fn ($d) => is_array($d) && ($d['status'] ?? '') === 'gagal'));
            $skipRows = array_values(array_filter($detailList, fn ($d) => is_array($d) && ($d['status'] ?? '') === 'skip'));

            $trimmedDetail = count($detailList) > 100
                ? array_slice(array_values(array_filter($detailList, fn ($d) => is_array($d) && in_array($d['status'] ?? '', ['gagal', 'skip'], true))), 0, 50)
                : $detailList;

            $currentSku = is_array($log->detail) ? ($log->detail['current_sku'] ?? null) : null;
            $currentNama = is_array($log->detail) ? ($log->detail['current_nama'] ?? null) : null;
            $currentBaris = is_array($log->detail) ? ($log->detail['current_baris'] ?? $selesai) : $selesai;

            $this->activeFotoImportLog = [
                'id' => $log->id,
                'status' => $log->status,
                'total_baris' => $totalBaris,
                'sukses' => $sukses,
                'gagal' => $gagal,
                'skip' => count($skipRows),
                'persen' => $persen,
                'current_sku' => $currentSku,
                'current_nama' => $currentNama,
                'current_baris' => $currentBaris,
                'detail' => $trimmedDetail,
                'gagal_rows' => $gagalRows,
                'skip_rows' => $skipRows,
                'created_at' => $log->created_at?->format('d/m/Y H:i'),
            ];

            $this->fotoSuccessCount = $sukses;
            $this->fotoErrorCount = $gagal;
            $this->fotoProcessedCount = $selesai;

            if ($log->status === 'selesai' || $log->status === 'gagal') {
                $this->fotoStep = 'completed';
            }
        }
    }

    public function setFotoErrorAction(int|string $key, string $aksi): void
    {
        if ($aksi === 'skip') {
            $this->resolusiErrorFoto[$key] = 'skip';
        } else {
            unset($this->resolusiErrorFoto[$key]);
        }
    }

    public function abaikanSemuaFotoError(): void
    {
        $skipped = $this->fotoScanResult['skipped'] ?? [];
        foreach ($skipped as $idx => $sk) {
            $key = $sk['filename'] ?? ($idx + 1);
            $this->resolusiErrorFoto[$key] = 'skip';
        }

        $this->dispatch('alert', [
            'type' => 'info',
            'message' => count($skipped).' berkas foto yang tidak cocok ditandai untuk dilewati (skip).',
        ]);
    }

    public function batalAbaikanSemuaFotoError(): void
    {
        $this->resolusiErrorFoto = [];
        $this->dispatch('alert', [
            'type' => 'info',
            'message' => 'Penandaan abaikan foto telah dibatalkan.',
        ]);
    }

    public function setFotoErrorFilterTab(string $tab): void
    {
        $this->fotoErrorFilterTab = in_array($tab, ['semua', 'gagal', 'skip'], true) ? $tab : 'semua';
        $this->fotoErrorDisplayLimit = 50;
    }

    public function loadMoreFotoErrors(): void
    {
        $this->fotoErrorDisplayLimit += 50;
    }

    public function exportFotoErrorRows(?int $logId = null): mixed
    {
        $id = $logId ?: ($this->fotoImportLogId ?: ($this->activeFotoImportLog['id'] ?? null));
        $log = $id ? ImportLog::find($id) : null;

        $rows = [];
        if ($log && is_array($log->detail)) {
            foreach ($log->detail as $d) {
                if (is_array($d) && (in_array($d['status'] ?? '', ['gagal', 'skip'], true) || ! empty($d['error']))) {
                    $rows[] = [
                        'baris' => $d['baris'] ?? '-',
                        'file' => $d['file'] ?? '-',
                        'sku' => $d['sku'] ?? '-',
                        'nama' => $d['nama'] ?? '-',
                        'status' => ($d['status'] ?? '') === 'skip' ? 'Dilewati (Skip)' : 'Gagal (Error)',
                        'error' => $d['error'] ?? ($d['catatan'] ?? 'Terjadi kendala pada file ini'),
                    ];
                }
            }
        } elseif (! empty($this->fotoScanResult['skipped'])) {
            foreach ($this->fotoScanResult['skipped'] as $idx => $sk) {
                $rows[] = [
                    'baris' => $idx + 1,
                    'file' => $sk['filename'] ?? ($sk['name'] ?? '-'),
                    'sku' => $sk['sku'] ?? '-',
                    'nama' => '-',
                    'status' => 'Tidak Cocok / Dilewati',
                    'error' => $sk['reason'] ?? 'SKU tidak ditemukan di database produk',
                ];
            }
        }

        if (empty($rows)) {
            $this->dispatch('alert', ['type' => 'info', 'message' => 'Tidak ada data file foto bermasalah untuk diekspor.']);

            return null;
        }

        $filename = 'laporan-error-foto-'.($id ?: date('Ymd-His')).'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['No', 'Nama File', 'SKU Terdeteksi', 'Nama Produk', 'Status', 'Alasan / Keterangan']);
            foreach ($rows as $r) {
                fputcsv($handle, [$r['baris'], $r['file'], $r['sku'], $r['nama'], $r['status'], $r['error']]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadFotoSkippedExcel(): mixed
    {
        $skipped = $this->fotoScanResult['skipped'] ?? [];
        if (empty($skipped)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Tidak ada data file yang dilewati.']);

            return null;
        }

        return Excel::download(
            new BulkFotoSkippedExport($skipped),
            'laporan_foto_dilewati_'.date('Ymd_His').'.xlsx'
        );
    }

    public function resetFotoUpload(): void
    {
        if ($this->fotoTempDir && is_dir($this->fotoTempDir)) {
            $files = glob($this->fotoTempDir.'/*');
            if (is_array($files)) {
                foreach ($files as $f) {
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
            }
            @rmdir($this->fotoTempDir);
            $this->fotoTempDir = null;
        }

        $this->fotoUploads = [];
        $this->fotoZip = null;
        $this->fotoScanResult = [];
        $this->resolusiErrorFoto = [];
        $this->fotoErrorFilterTab = 'semua';
        $this->fotoErrorDisplayLimit = 50;
        $this->activeFotoImportLog = null;
        $this->fotoImportLogId = null;
        $this->fotoStep = 'upload';
        $this->fotoProcessedCount = 0;
        $this->fotoSuccessCount = 0;
        $this->fotoErrorCount = 0;
        $this->fotoCompletedSummary = [];
    }

    public function render()
    {
        return view('modules.wms.livewire.import-foto-page');
    }
}
