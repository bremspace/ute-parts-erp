<?php

namespace App\Modules\Wms\Livewire;

use App\Modules\Wms\Exports\ImportProdukTemplateExport;
use App\Modules\Wms\Jobs\ImportProdukExcelJob;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Services\ImportProdukService;
use App\Modules\Wms\Services\ImportSidRetailService;
use Illuminate\Support\Facades\Storage;
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

    public function mount(): void
    {
        $this->activeTab = request('tab', 'import') === 'export' ? 'export' : 'import';

        $cabangId = session('cabang_id') ?? auth()->user()?->cabang_id;
        $gudangPertama = Gudang::when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))->first();
        if ($gudangPertama) {
            $this->gudangTokoId = $gudangPertama->id;
            $this->gudangPusatId = $gudangPertama->id;
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
        if (! isset($this->resolusiDuplikat[$baris])) {
            $this->resolusiDuplikat[$baris] = ['aksi' => $aksi, 'sku_baru' => ''];
        } else {
            $this->resolusiDuplikat[$baris]['aksi'] = $aksi;
        }
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
        // Panggil download data master produk langsung
        return redirect()->route('wms.produk.import.template'); // fallback jika template
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
