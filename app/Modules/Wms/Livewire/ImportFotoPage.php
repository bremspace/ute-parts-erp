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

    public string $fotoResultSearch = '';

    public array $fotoRecentStream = [];

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

            if (($scan['matched_count'] ?? 0) === 0) {
                $unmatched = collect($scan['skipped'] ?? [])->pluck('filename')->take(3)->implode(', ');
                $this->addError('fotoUploads', "Upload gagal: Nama file foto ({$unmatched}) tidak ditemukan di katalog produk. Silakan cek kembali nama file agar sesuai dengan SKU/Barcode produk.");
                $this->dispatch('alert', [
                    'type' => 'error',
                    'message' => "Upload gagal: Nama file foto ({$unmatched}) tidak ditemukan di katalog produk. Silakan cek nama file Anda.",
                ]);
                $this->fotoStep = 'upload';

                return;
            }

            if (($scan['skipped_count'] ?? 0) > 0) {
                $unmatched = collect($scan['skipped'] ?? [])->pluck('filename')->take(3)->implode(', ');
                $this->dispatch('alert', [
                    'type' => 'warning',
                    'message' => "{$scan['skipped_count']} file foto ({$unmatched}) tidak cocok dengan SKU produk dan gagal di-import. Cek kembali nama file.",
                ]);
            }

            $this->fotoStep = 'preview';
        }
    }

    /**
     * Auto-import langsung per-foto ke master produk berdasarkan nama file tanpa perlu edit master produk manual.
     */
    public function autoImportFoto(): void
    {
        $this->scanUploadedFoto();

        if ($this->fotoStep === 'preview' && ($this->fotoScanResult['matched_count'] ?? 0) > 0) {
            $this->startFotoProcessing();
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

            $detailRaw = is_array($log->detail) ? $log->detail : [];
            $detailList = isset($detailRaw['items']) && is_array($detailRaw['items'])
                ? $detailRaw['items']
                : (isset($detailRaw[0]) && is_array($detailRaw[0]) ? $detailRaw : []);

            $gagalRows = array_values(array_filter($detailList, fn ($d) => is_array($d) && ($d['status'] ?? '') === 'gagal'));
            $skipRows = array_values(array_filter($detailList, fn ($d) => is_array($d) && ($d['status'] ?? '') === 'skip'));
            $okRows = array_values(array_filter($detailList, fn ($d) => is_array($d) && in_array($d['status'] ?? '', ['ok', 'success'], true)));

            $skipCount = isset($detailRaw['summary']['skip'])
                ? (int) $detailRaw['summary']['skip']
                : (isset($detailRaw['skip']) ? (int) $detailRaw['skip'] : count($skipRows));

            $currentBaris = isset($detailRaw['current_baris']) ? (int) $detailRaw['current_baris'] : ($sukses + $gagal + $skipCount);
            $selesai = $log->status === 'selesai' ? $totalBaris : min($totalBaris, max($currentBaris, $sukses + $gagal + $skipCount));
            $persen = $log->status === 'selesai'
                ? 100
                : ($totalBaris > 0 ? (int) min(99, max(0, round(($selesai / $totalBaris) * 100))) : 0);

            $currentSku = $detailRaw['current_sku'] ?? null;
            $currentNama = $detailRaw['current_nama'] ?? null;
            $this->fotoRecentStream = $detailRaw['recent'] ?? [];

            $this->activeFotoImportLog = [
                'id' => $log->id,
                'status' => $log->status,
                'total_baris' => $totalBaris,
                'sukses' => $sukses,
                'gagal' => $gagal,
                'skip' => $skipCount,
                'selesai' => $selesai,
                'persen' => $persen,
                'current_sku' => $currentSku,
                'current_nama' => $currentNama,
                'current_baris' => $currentBaris,
                'detail' => $detailList,
                'gagal_rows' => $gagalRows,
                'skip_rows' => $skipRows,
                'ok_rows' => $okRows,
                'recent' => $this->fotoRecentStream,
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
        $this->fotoErrorFilterTab = in_array($tab, ['semua', 'sukses', 'gagal', 'skip'], true) ? $tab : 'semua';
        $this->fotoErrorDisplayLimit = 50;
    }

    public function loadMoreFotoErrors(): void
    {
        $this->fotoErrorDisplayLimit += 50;
    }

    public function exportFotoLengkap(?int $logId = null): mixed
    {
        $id = $logId ?: ($this->fotoImportLogId ?: ($this->activeFotoImportLog['id'] ?? null));
        $log = $id ? ImportLog::find($id) : null;

        $rows = [];
        if ($log && is_array($log->detail)) {
            $items = isset($log->detail['items']) && is_array($log->detail['items'])
                ? $log->detail['items']
                : (isset($log->detail[0]) && is_array($log->detail[0]) ? $log->detail : []);

            foreach ($items as $idx => $d) {
                if (is_array($d)) {
                    $statusLabel = match ($d['status'] ?? '') {
                        'ok', 'success' => 'Berhasil Disimpan',
                        'skip' => 'Dilewati (Skip)',
                        'gagal' => 'Gagal (Error)',
                        default => $d['status'] ?? '-',
                    };

                    $rows[] = [
                        'no' => $d['baris'] ?? ($idx + 1),
                        'file' => $d['file'] ?? ($d['filename'] ?? '-'),
                        'sku' => $d['sku'] ?? '-',
                        'nama' => $d['nama'] ?? ($d['produk_nama'] ?? '-'),
                        'status' => $statusLabel,
                        'keterangan' => $d['catatan'] ?? ($d['error'] ?? 'Foto berhasil diproses'),
                    ];
                }
            }
        }

        if (empty($rows)) {
            $matched = $this->fotoScanResult['matched'] ?? [];
            $skipped = $this->fotoScanResult['skipped'] ?? [];
            foreach ($matched as $idx => $m) {
                $rows[] = [
                    'no' => $idx + 1,
                    'file' => $m['filename'] ?? ($m['name'] ?? '-'),
                    'sku' => $m['sku'] ?? '-',
                    'nama' => $m['produk_nama'] ?? '-',
                    'status' => 'Berhasil Disimpan',
                    'keterangan' => 'Foto berhasil dikompresi WebP & disimpan ke master produk',
                ];
            }
            $offset = count($matched);
            foreach ($skipped as $idx => $sk) {
                $rows[] = [
                    'no' => $offset + $idx + 1,
                    'file' => $sk['filename'] ?? ($sk['name'] ?? '-'),
                    'sku' => $sk['sku'] ?? '-',
                    'nama' => '-',
                    'status' => 'Dilewati (Skip)',
                    'keterangan' => $sk['reason'] ?? 'SKU tidak ditemukan di katalog produk',
                ];
            }
        }

        if (empty($rows)) {
            $this->dispatch('alert', ['type' => 'info', 'message' => 'Tidak ada data hasil import untuk diekspor.']);

            return null;
        }

        $filename = 'laporan-lengkap-import-foto-'.($id ?: date('Ymd-His')).'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['No', 'Nama Berkas Foto', 'SKU Terdeteksi', 'Nama Produk Sistem', 'Status Hasil', 'Keterangan / Detail']);
            foreach ($rows as $r) {
                fputcsv($handle, [$r['no'], $r['file'], $r['sku'], $r['nama'], $r['status'], $r['keterangan']]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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
        $this->fotoResultSearch = '';
        $this->fotoRecentStream = [];
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
