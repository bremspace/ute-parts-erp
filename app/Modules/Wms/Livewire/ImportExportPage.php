<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Wms\Exports\BulkFotoSkippedExport;
use App\Modules\Wms\Exports\ImportProdukTemplateExport;
use App\Modules\Wms\Jobs\ImportProdukExcelJob;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Services\BulkProductImageImportService;
use App\Modules\Wms\Services\ExportMasterProdukService;
use App\Modules\Wms\Services\ImportProdukService;
use App\Modules\Wms\Services\ImportSidRetailService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.backoffice', ['header' => 'WMS — Export & Import Produk'])]
class ImportExportPage extends Component
{
    use WithFileUploads;

    public string $activeTab = 'import'; // 'import' | 'export'

    // Form Import
    public $importFile = null;

    public string $importFormat = 'standar'; // 'standar' | 'sid_retail'

    public ?int $gudangTokoId = null;

    public ?int $gudangPusatId = null;

    public string $importStep = 'upload'; // 'upload' | 'preview' | 'selesai'

    public array $importPreview = [];

    public string $importFilePath = '';

    public int $importLogId = 0;

    public ?array $activeImportLog = null;

    // Resolusi Interaktif
    public int $thresholdKemiripan = 99; // 80 - 100%

    public array $aksiKemiripan = [];

    public array $resolusiDuplikat = []; // [baris => ['aksi' => 'ganti_sku|timpa_update|skip', 'sku_baru' => '...']]

    // --- TAB 3: IMPORT FOTO BULK ---
    public $fotoUploads = [];

    public $fotoZip = null;

    public string $fotoSourceType = 'files'; // 'files' | 'zip'

    public string $fotoMode = 'append'; // 'append' | 'replace' | 'skip_existing'

    public string $fotoStep = 'upload'; // 'upload' | 'preview' | 'processing' | 'completed'

    public array $fotoScanResult = [];

    public string $fotoFilterTab = 'all'; // 'all' | 'matched' | 'skipped'

    public int $fotoBatchChunkSize = 8;

    public int $fotoProcessedCount = 0;

    public int $fotoSuccessCount = 0;

    public int $fotoErrorCount = 0;

    public ?string $fotoTempDir = null;

    public array $fotoCompletedSummary = [];

    public function mount(): void
    {
        $reqTab = request('tab', 'import');
        $this->activeTab = in_array($reqTab, ['import', 'export', 'foto'], true) ? $reqTab : 'import';

        $cabangId = session('cabang_id') ?? auth()->user()?->cabang_id;
        $gudangPertama = Gudang::when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))->first();
        if ($gudangPertama) {
            $this->gudangTokoId = $gudangPertama->id;
            $this->gudangPusatId = $gudangPertama->id;
        }

        $runningLog = ImportLog::where('user_id', auth()->id())
            ->where('status', 'proses')
            ->latest()
            ->first();
        if ($runningLog) {
            $this->importLogId = $runningLog->id;
            $this->importStep = 'selesai';
            $this->cekStatusImport();
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function downloadTemplate(): mixed
    {
        return Excel::download(
            new ImportProdukTemplateExport,
            'template_import_produk_uteparts_'.date('Ymd_His').'.xlsx'
        );
    }

    public function resetUpload(): void
    {
        if ($this->importFilePath) {
            Storage::disk('local')->delete($this->importFilePath);
            @unlink(storage_path('app/'.$this->importFilePath));
            @unlink(storage_path('app/private/'.$this->importFilePath));
        }

        $this->importFile = null;
        $this->importPreview = [];
        $this->aksiKemiripan = [];
        $this->resolusiDuplikat = [];
        $this->importFilePath = '';
        $this->importStep = 'upload';
        $this->activeImportLog = null;
    }

    public function previewImport(): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ], [
            'importFile.required' => 'Pilih file Excel atau CSV terlebih dahulu.',
            'importFile.mimes' => 'File harus berekstensi .xlsx, .xls, atau .csv',
            'importFile.max' => 'Ukuran file maksimal 10MB',
        ]);

        try {
            $path = $this->importFile->store('import-tmp');
            $realPath = Storage::disk('local')->path($path);
            $cabangId = session('cabang_id') ?? auth()->user()?->cabang_id ?? 1;

            if ($this->importFormat === 'sid_retail') {
                $hasil = app(ImportSidRetailService::class)->preview(
                    $realPath,
                    $cabangId,
                    $this->gudangTokoId,
                    $this->gudangPusatId
                );
            } else {
                $hasil = app(ImportProdukService::class)->preview($realPath, (int) $this->thresholdKemiripan);
            }

            $this->importFilePath = $path;
            $this->importPreview = $hasil;
            $this->importStep = 'preview';

            // Inisialisasi default aksi kemiripan
            $this->aksiKemiripan = [];
            if (! empty($hasil['kemiripan_rows'])) {
                foreach ($hasil['kemiripan_rows'] as $kr) {
                    $this->aksiKemiripan[$kr['baris']] = $kr['default_aksi'] ?? 'gabung';
                }
            }

            // Inisialisasi default resolusi duplikat SKU / Barcode
            $this->resolusiDuplikat = [];
            if (! empty($hasil['duplikat_rows'])) {
                foreach ($hasil['duplikat_rows'] as $dr) {
                    $this->resolusiDuplikat[$dr['baris']] = [
                        'aksi' => $dr['lokasi'] === 'database' ? 'timpa_update' : 'ganti_sku',
                        'sku_baru' => $dr['sku_rekomendasi'] ?? '',
                    ];
                }
            }

            if ($hasil['invalid'] > 0) {
                $this->dispatch('alert', [
                    'type' => 'warning',
                    'message' => "Preview: {$hasil['valid']} valid, {$hasil['invalid']} baris perlu penyesuaian/error. Anda dapat menyelesaikan duplikasi SKU di bawah.",
                ]);
            } else {
                $this->dispatch('alert', [
                    'type' => 'success',
                    'message' => "Preview: Semua baris valid ({$hasil['valid']} baris). Siap diimpor!",
                ]);
            }
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function updatedThresholdKemiripan(): void
    {
        $this->thresholdKemiripan = max(50, min(100, (int) $this->thresholdKemiripan));

        // Jika sudah di step preview dan file masih tersimpan, re-evaluasi preview otomatis
        if ($this->importStep === 'preview' && $this->importFilePath && $this->importFormat !== 'sid_retail') {
            try {
                $realPath = Storage::disk('local')->path($this->importFilePath);
                if (file_exists($realPath)) {
                    $hasil = app(ImportProdukService::class)->preview($realPath, $this->thresholdKemiripan);
                    $this->importPreview = $hasil;

                    // Re-init default aksi kemiripan untuk baris baru terdeteksi
                    foreach ($hasil['kemiripan_rows'] ?? [] as $kr) {
                        if (! isset($this->aksiKemiripan[$kr['baris']])) {
                            $this->aksiKemiripan[$kr['baris']] = $kr['default_aksi'] ?? 'gabung';
                        }
                    }

                    $this->dispatch('alert', [
                        'type' => 'info',
                        'message' => "Threshold diperbarui menjadi {$this->thresholdKemiripan}%. Terdeteksi {$hasil['total_kemiripan_rows']} produk mirip.",
                    ]);
                }
            } catch (\Throwable $e) {
                // Biarkan jika file hilang
            }
        }
    }

    public function setAksiKemiripan(int $baris, string $aksi): void
    {
        $this->aksiKemiripan[$baris] = $aksi;
    }

    public function setAksiDuplikat(int $baris, string $aksi): void
    {
        $dr = collect($this->importPreview['duplikat_rows'] ?? [])->firstWhere('baris', $baris);
        $rekomendasi = $dr['sku_rekomendasi'] ?? '';

        if (! isset($this->resolusiDuplikat[$baris])) {
            $this->resolusiDuplikat[$baris] = [
                'aksi' => $aksi,
                'sku_baru' => $aksi === 'ganti_sku' ? $rekomendasi : '',
            ];
        } else {
            $this->resolusiDuplikat[$baris]['aksi'] = $aksi;
            if ($aksi === 'ganti_sku' && empty($this->resolusiDuplikat[$baris]['sku_baru'])) {
                $this->resolusiDuplikat[$baris]['sku_baru'] = $rekomendasi;
            }
        }
    }

    public function setAksiDuplikatSemua(string $aksi): void
    {
        if (empty($this->importPreview['duplikat_rows'])) {
            return;
        }

        foreach ($this->importPreview['duplikat_rows'] as $dr) {
            $baris = (int) $dr['baris'];
            $targetAksi = $aksi;
            if ($aksi === 'timpa_update' && ($dr['lokasi'] ?? '') !== 'database') {
                $targetAksi = 'ganti_sku';
            }
            $this->resolusiDuplikat[$baris] = [
                'aksi' => $targetAksi,
                'sku_baru' => $dr['sku_rekomendasi'] ?? '',
            ];
        }

        $label = match ($aksi) {
            'ganti_sku' => 'Ganti SKU Otomatis',
            'timpa_update' => 'Timpa/Update Data',
            'skip' => 'Lewati (Skip)',
            default => $aksi,
        };

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Tindakan '{$label}' berhasil diterapkan untuk seluruh baris duplikat.",
        ]);
    }

    public function updateSkuBaru(int $baris, string $val): void
    {
        if (! isset($this->resolusiDuplikat[$baris])) {
            $this->resolusiDuplikat[$baris] = ['aksi' => 'ganti_sku', 'sku_baru' => trim($val)];
        } else {
            $this->resolusiDuplikat[$baris]['sku_baru'] = trim($val);
        }
    }

    public function commitImport(): void
    {
        if ($this->importStep === 'selesai') {
            return;
        }

        if (! $this->importFilePath) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'File import tidak ditemukan. Silakan upload ulang.']);

            return;
        }

        try {
            $totalBaris = (int) ($this->importPreview['total_baris'] ?? 0);
            $cabangId = session('cabang_id') ?? auth()->user()?->cabang_id ?? 1;

            if ($this->importFormat === 'sid_retail') {
                $log = ImportLog::create([
                    'tipe' => 'produk_sid_retail',
                    'nama_file' => $this->importFile?->getClientOriginalName() ?? 'sid-retail-import.xlsx',
                    'status' => 'proses',
                    'user_id' => auth()->id(),
                    'total_baris' => $totalBaris,
                ]);
                $this->importLogId = $log->id;
                $filePath = $this->importFilePath;
                $this->importFilePath = '';

                if ($totalBaris <= 100) {
                    ImportProdukExcelJob::dispatchSync(
                        $log->id,
                        $filePath,
                        auth()->id(),
                        'sid_retail',
                        $cabangId,
                        $this->gudangTokoId,
                        $this->gudangPusatId
                    );
                } else {
                    ImportProdukExcelJob::dispatch(
                        $log->id,
                        $filePath,
                        auth()->id(),
                        'sid_retail',
                        $cabangId,
                        $this->gudangTokoId,
                        $this->gudangPusatId
                    );
                }
            } else {
                $log = ImportLog::create([
                    'tipe' => 'produk_excel',
                    'nama_file' => $this->importFile?->getClientOriginalName() ?? 'produk-import.xlsx',
                    'status' => 'proses',
                    'user_id' => auth()->id(),
                    'total_baris' => $totalBaris,
                ]);
                $this->importLogId = $log->id;
                $filePath = $this->importFilePath;
                $this->importFilePath = '';

                if ($totalBaris <= 100) {
                    ImportProdukExcelJob::dispatchSync(
                        $log->id,
                        $filePath,
                        auth()->id(),
                        'standar',
                        null,
                        null,
                        null,
                        $this->aksiKemiripan,
                        $this->resolusiDuplikat,
                        $this->thresholdKemiripan
                    );
                } else {
                    ImportProdukExcelJob::dispatch(
                        $log->id,
                        $filePath,
                        auth()->id(),
                        'standar',
                        null,
                        null,
                        null,
                        $this->aksiKemiripan,
                        $this->resolusiDuplikat,
                        $this->thresholdKemiripan
                    );
                }
            }

            $this->importStep = 'selesai';
            $this->cekStatusImport();
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => $totalBaris <= 100 ? 'Import produk selesai diproses!' : 'Import sedang diproses oleh sistem di latar belakang.',
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function cekStatusImport(): void
    {
        if (! $this->importLogId) {
            return;
        }

        $log = ImportLog::find($this->importLogId);
        if ($log) {
            $totalBaris = (int) $log->total_baris;
            $sukses = (int) $log->sukses;
            $gagal = (int) $log->gagal;
            $selesai = $sukses + $gagal;
            $persen = $totalBaris > 0 ? (int) min(100, round(($selesai / $totalBaris) * 100)) : 0;

            $this->activeImportLog = [
                'id' => $log->id,
                'status' => $log->status,
                'total_baris' => $totalBaris,
                'sukses' => $sukses,
                'gagal' => $gagal,
                'persen' => $persen,
                'detail' => is_array($log->detail) ? $log->detail : [],
                'created_at' => $log->created_at?->format('d/m/Y H:i'),
            ];
        }
    }

    public function exportData(string $format = 'xlsx'): mixed
    {
        $cabangId = session('cabang_id') ?? auth()->user()?->cabang_id;

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Ekspor master produk dimulai — unduhan segera berjalan.',
        ]);

        return app(ExportMasterProdukService::class)->export($format, $cabangId);
    }

    // =========================================================================
    // AKSI FOTO PRODUK BULK (TAB 3)
    // =========================================================================

    public function setFotoFilterTab(string $tab): void
    {
        $this->fotoFilterTab = in_array($tab, ['all', 'matched', 'skipped'], true) ? $tab : 'all';
    }

    public function scanUploadedFoto(): void
    {
        $bulkService = app(BulkProductImageImportService::class);

        if ($this->fotoSourceType === 'zip') {
            $this->validate([
                'fotoZip' => 'required|file|mimes:zip|max:51200',
            ], [
                'fotoZip.required' => 'Pilih berkas ZIP foto terlebih dahulu.',
                'fotoZip.mimes' => 'Berkas harus berupa arsip ZIP (.zip).',
                'fotoZip.max' => 'Ukuran berkas ZIP maksimal 50MB.',
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
                $scan = $bulkService->preScanFiles($extracted);
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

            $scan = $bulkService->preScanFiles($items);
            $this->fotoScanResult = $scan;
            $this->fotoStep = 'preview';
        }
    }

    public function startFotoProcessing(): void
    {
        if (empty($this->fotoScanResult['matched'])) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Tidak ada foto yang cocok untuk diproses.']);

            return;
        }

        $this->fotoStep = 'processing';
        $this->fotoProcessedCount = 0;
        $this->fotoSuccessCount = 0;
        $this->fotoErrorCount = 0;

        $this->processNextFotoChunk();
    }

    public function processNextFotoChunk(): void
    {
        if ($this->fotoStep !== 'processing') {
            return;
        }

        $matchedList = $this->fotoScanResult['matched'] ?? [];
        $totalMatched = count($matchedList);

        if ($this->fotoProcessedCount >= $totalMatched) {
            $this->finishFotoProcessing();

            return;
        }

        $bulkService = app(BulkProductImageImportService::class);
        $chunk = array_slice($matchedList, $this->fotoProcessedCount, $this->fotoBatchChunkSize);

        foreach ($chunk as $item) {
            $tempPath = $item['temp_path'] ?? null;
            $produkId = $item['produk_id'] ?? null;
            $isPrimary = ! empty($item['is_primary']);

            if ($tempPath && file_exists($tempPath) && $produkId) {
                try {
                    $res = $bulkService->processAndAttach(
                        $tempPath,
                        $produkId,
                        $this->fotoMode,
                        $isPrimary
                    );
                    if ($res['success']) {
                        $this->fotoSuccessCount++;
                    } else {
                        $this->fotoErrorCount++;
                    }
                } catch (\Throwable $e) {
                    $this->fotoErrorCount++;
                }
            } else {
                $this->fotoErrorCount++;
            }

            $this->fotoProcessedCount++;
        }

        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        if ($this->fotoProcessedCount >= $totalMatched) {
            $this->finishFotoProcessing();
        }
    }

    public function finishFotoProcessing(): void
    {
        $this->fotoStep = 'completed';
        $this->fotoCompletedSummary = [
            'total' => $this->fotoScanResult['total'] ?? 0,
            'processed' => $this->fotoProcessedCount,
            'success' => $this->fotoSuccessCount,
            'error' => $this->fotoErrorCount,
            'skipped' => $this->fotoScanResult['skipped_count'] ?? 0,
        ];

        // Bersihkan direktori temp
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

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Proses import foto produk selesai diproses!',
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
        $this->fotoStep = 'upload';
        $this->fotoProcessedCount = 0;
        $this->fotoSuccessCount = 0;
        $this->fotoErrorCount = 0;
        $this->fotoCompletedSummary = [];
    }

    public function render()
    {
        $gudangList = Gudang::where('is_active', true)->orderBy('nama')->get();

        return view('modules.wms.livewire.import-export-page', [
            'gudangList' => $gudangList,
            'activeImportLog' => $this->activeImportLog,
            'importPreview' => $this->importPreview,
            'aksiKemiripan' => $this->aksiKemiripan,
            'resolusiDuplikat' => $this->resolusiDuplikat,
        ]);
    }
}
