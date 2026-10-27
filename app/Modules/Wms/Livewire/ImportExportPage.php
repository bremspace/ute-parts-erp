<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Wms\Exports\BulkFotoSkippedExport;
use App\Modules\Wms\Exports\ImportProdukTemplateExport;
use App\Modules\Wms\Jobs\ImportFotoMasalJob;
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
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.backoffice', ['header' => 'WMS — Export & Import Produk'])]
class ImportExportPage extends Component
{
    use WithFileUploads;

    #[Url(as: 'tab')]
    public string $activeTab = 'import'; // 'import' | 'export' | 'foto'

    protected $queryString = [
        'activeTab' => ['as' => 'tab'],
    ];

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

    public array $resolusiBarcode = []; // [baris => ['aksi' => 'kosongkan_barcode|skip|ganti_barcode', 'barcode_baru' => '...']]

    public array $resolusiErrorBaris = []; // [baris => 'skip']

    public string $errorFilterTab = 'semua'; // 'semua' | 'gagal' | 'skip'

    public int $errorDisplayLimit = 50;

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

    public ?int $fotoImportLogId = null;

    public ?array $activeFotoImportLog = null;

    public array $resolusiErrorFoto = []; // [nomor|filename => 'skip']

    public string $fotoErrorFilterTab = 'semua'; // 'semua' | 'gagal' | 'skip'

    public int $fotoErrorDisplayLimit = 50;

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

        $runningFotoLog = ImportLog::where('user_id', auth()->id())
            ->where('tipe', 'produk_foto')
            ->where('status', 'proses')
            ->latest()
            ->first();
        if ($runningFotoLog) {
            $this->activeTab = 'foto';
            $this->fotoImportLogId = $runningFotoLog->id;
            $this->fotoStep = 'processing';
            $this->cekStatusFotoImport();
        } else {
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
        $this->resolusiBarcode = [];
        $this->resolusiErrorBaris = [];
        $this->errorFilterTab = 'semua';
        $this->errorDisplayLimit = 50;
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

            // Inisialisasi default resolusi duplikat SKU & Barcode
            $this->resolusiDuplikat = [];
            $this->resolusiBarcode = [];
            $this->resolusiErrorBaris = [];
            if (! empty($hasil['duplikat_rows'])) {
                foreach ($hasil['duplikat_rows'] as $dr) {
                    $isBarcode = ($dr['tipe'] ?? '') === 'barcode';
                    if ($isBarcode) {
                        $this->resolusiBarcode[$dr['baris']] = [
                            'aksi' => 'kosongkan_barcode',
                            'barcode_baru' => '',
                        ];
                    }
                    $this->resolusiDuplikat[$dr['baris']] = [
                        'aksi' => $isBarcode ? 'kosongkan_barcode' : ($dr['lokasi'] === 'database' ? 'timpa_update' : 'ganti_sku'),
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

    public function setBarcodeAction(int $baris, string $aksi, ?string $barcodeBaru = null): void
    {
        $this->resolusiBarcode[$baris] = [
            'aksi' => $aksi,
            'barcode_baru' => $barcodeBaru ?? '',
        ];
        if (isset($this->resolusiDuplikat[$baris])) {
            $this->resolusiDuplikat[$baris]['aksi'] = $aksi;
        }
    }

    public function kosongkanSemuaBarcodeDuplikat(): void
    {
        if (empty($this->importPreview['duplikat_rows'])) {
            return;
        }

        $count = 0;
        foreach ($this->importPreview['duplikat_rows'] as $dr) {
            if (($dr['tipe'] ?? '') === 'barcode' || ! empty($dr['barcode'])) {
                $this->resolusiBarcode[$dr['baris']] = [
                    'aksi' => 'kosongkan_barcode',
                    'barcode_baru' => '',
                ];
                if (isset($this->resolusiDuplikat[$dr['baris']])) {
                    $this->resolusiDuplikat[$dr['baris']]['aksi'] = 'kosongkan_barcode';
                }
                $count++;
            }
        }

        $this->dispatch('alert', [
            'type' => 'info',
            'message' => "{$count} barcode duplikat ditandai untuk dikosongkan (produk tetap diimpor).",
        ]);
    }

    public function lewatiSemuaBarcodeDuplikat(): void
    {
        if (empty($this->importPreview['duplikat_rows'])) {
            return;
        }

        $count = 0;
        foreach ($this->importPreview['duplikat_rows'] as $dr) {
            if (($dr['tipe'] ?? '') === 'barcode' || ! empty($dr['barcode'])) {
                $this->resolusiBarcode[$dr['baris']] = [
                    'aksi' => 'skip',
                    'barcode_baru' => '',
                ];
                if (isset($this->resolusiDuplikat[$dr['baris']])) {
                    $this->resolusiDuplikat[$dr['baris']]['aksi'] = 'skip';
                }
                $count++;
            }
        }

        $this->dispatch('alert', [
            'type' => 'info',
            'message' => "{$count} baris barcode duplikat ditandai untuk dilewati (skip).",
        ]);
    }

    public function setErrorAction(int $baris, string $aksi): void
    {
        if ($aksi === 'skip') {
            $this->resolusiErrorBaris[$baris] = 'skip';
        } else {
            unset($this->resolusiErrorBaris[$baris]);
        }
    }

    public function abaikanSemuaBarisError(): void
    {
        if (empty($this->importPreview['error_rows'])) {
            return;
        }

        foreach ($this->importPreview['error_rows'] as $er) {
            $this->resolusiErrorBaris[$er['baris']] = 'skip';
        }

        $this->dispatch('alert', [
            'type' => 'info',
            'message' => count($this->importPreview['error_rows']).' baris error ditandai untuk dilewati (skip).',
        ]);
    }

    public function batalAbaikanSemuaBarisError(): void
    {
        $this->resolusiErrorBaris = [];
        $this->dispatch('alert', [
            'type' => 'info',
            'message' => 'Status abaikan baris error telah dibatalkan.',
        ]);
    }

    public function setErrorFilterTab(string $tab): void
    {
        $this->errorFilterTab = in_array($tab, ['semua', 'gagal', 'skip'], true) ? $tab : 'semua';
        $this->errorDisplayLimit = 50;
    }

    public function loadMoreErrors(): void
    {
        $this->errorDisplayLimit += 50;
    }

    public function exportErrorRows(?int $logId = null): mixed
    {
        $id = $logId ?: ($this->importLogId ?: ($this->activeImportLog['id'] ?? null));
        $log = $id ? ImportLog::find($id) : null;

        $rows = [];
        if ($log && is_array($log->detail)) {
            foreach ($log->detail as $d) {
                if (is_array($d) && (in_array($d['status'] ?? '', ['gagal', 'skip'], true) || ! empty($d['error']))) {
                    $rows[] = [
                        'baris' => $d['baris'] ?? '-',
                        'sku' => $d['sku'] ?? ($d['kode'] ?? '-'),
                        'nama' => $d['nama'] ?? '-',
                        'status' => ($d['status'] ?? '') === 'skip' ? 'Dilewati (Skip)' : 'Gagal (Error)',
                        'error' => $d['error'] ?? ($d['catatan'] ?? 'Terjadi kendala pada baris ini'),
                    ];
                }
            }
        } elseif (! empty($this->importPreview['error_rows'])) {
            foreach ($this->importPreview['error_rows'] as $er) {
                $isSkip = ($this->resolusiErrorBaris[$er['baris']] ?? '') === 'skip';
                $rows[] = [
                    'baris' => $er['baris'] ?? '-',
                    'sku' => $er['sku'] ?? '-',
                    'nama' => $er['nama'] ?? '-',
                    'status' => $isSkip ? 'Dilewati (Skip)' : 'Format Invalid',
                    'error' => is_array($er['errors'] ?? null) ? implode('; ', $er['errors']) : ($er['errors'] ?? ''),
                ];
            }
        }

        if (empty($rows)) {
            $this->dispatch('alert', ['type' => 'info', 'message' => 'Tidak ada data error atau baris yang dilewati untuk diekspor.']);

            return null;
        }

        $filename = 'laporan-error-import-'.($id ?: date('Ymd-His')).'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['No. Baris', 'SKU', 'Nama Produk', 'Status', 'Alasan / Pesan Error']);
            foreach ($rows as $r) {
                fputcsv($handle, [$r['baris'], $r['sku'], $r['nama'], $r['status'], $r['error']]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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
                        $cabangId,
                        null,
                        null,
                        $this->aksiKemiripan,
                        $this->resolusiDuplikat,
                        $this->thresholdKemiripan,
                        $this->resolusiBarcode,
                        $this->resolusiErrorBaris
                    );
                } else {
                    ImportProdukExcelJob::dispatch(
                        $log->id,
                        $filePath,
                        auth()->id(),
                        'standar',
                        $cabangId,
                        null,
                        null,
                        $this->aksiKemiripan,
                        $this->resolusiDuplikat,
                        $this->thresholdKemiripan,
                        $this->resolusiBarcode,
                        $this->resolusiErrorBaris
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

            $detailList = is_array($log->detail) ? $log->detail : [];
            $gagalRows = array_values(array_filter($detailList, fn ($d) => is_array($d) && ($d['status'] ?? '') === 'gagal'));
            $skipRows = array_values(array_filter($detailList, fn ($d) => is_array($d) && ($d['status'] ?? '') === 'skip'));

            $this->activeImportLog = [
                'id' => $log->id,
                'status' => $log->status,
                'total_baris' => $totalBaris,
                'sukses' => $sukses,
                'gagal' => $gagal,
                'skip' => count($skipRows),
                'persen' => $persen,
                'current_sku' => $log->current_sku,
                'current_nama' => $log->current_nama,
                'current_baris' => $log->current_baris ?? $selesai,
                'detail' => $detailList,
                'gagal_rows' => $gagalRows,
                'skip_rows' => $skipRows,
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

            // Optimasi RAM/payload Livewire: jangan bawa ribuan baris 'ok' ke Livewire wire:poll state
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
        $cabangId = session('cabang_id');
        $gudangList = Gudang::when($cabangId, fn ($q, $c) => $q->where('cabang_id', $c))
            ->where('is_active', true)
            ->orderBy('nama')
            ->get();

        return view('modules.wms.livewire.import-export-page', [
            'gudangList' => $gudangList,
            'activeImportLog' => $this->activeImportLog,
            'importPreview' => $this->importPreview,
            'aksiKemiripan' => $this->aksiKemiripan,
            'resolusiDuplikat' => $this->resolusiDuplikat,
        ]);
    }
}
