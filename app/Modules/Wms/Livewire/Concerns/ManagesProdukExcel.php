<?php

namespace App\Modules\Wms\Livewire\Concerns;

use App\Modules\Wms\Jobs\ImportProdukExcelJob;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Services\ExportMasterProdukService;
use App\Modules\Wms\Services\ImportProdukService;
use App\Modules\Wms\Services\ImportSidRetailService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Trait ManagesProdukExcel
 *
 * Menangani alur import excel produk (standar & SID Retail),
 * commit queue background import, dan export pricelist/master produk.
 */
trait ManagesProdukExcel
{
    // Quick action header "Import Excel" (dari shell WmsDashboard via $dispatch)
    #[On('wms-import-modal')]
    public function openImportModal()
    {
        return $this->redirect(route('pengaturan', ['tab' => 'export_import']), navigate: true);
    }

    // Quick action header "Export Master Produk" (dari shell WmsDashboard via $dispatch)
    #[On('wms-export-master')]
    public function openExportMasterModal($format = 'xlsx')
    {
        $fmt = is_array($format) ? ($format['format'] ?? 'xlsx') : (is_string($format) ? $format : 'xlsx');

        return $this->exportMaster($fmt);
    }

    #[On('wms-export-terpilih')]
    public function openExportTerpilihModal($format = 'xlsx')
    {
        $fmt = is_array($format) ? ($format['format'] ?? 'xlsx') : (is_string($format) ? $format : 'xlsx');

        return $this->exportTerpilih($fmt);
    }

    public function tutupImportModal()
    {
        $this->showImportModal = false;
        if ($this->importFilePath) {
            Storage::disk('local')->delete($this->importFilePath);
            @unlink(storage_path('app/'.$this->importFilePath));
            @unlink(storage_path('app/private/'.$this->importFilePath));
        }
        $this->importFile = null;
        $this->importPreview = [];
        $this->aksiKemiripan = [];
        $this->importFilePath = '';
        $this->activeImportLog = null;
    }

    public function setAksiKemiripan(int $baris, string $aksi): void
    {
        $this->aksiKemiripan[$baris] = $aksi;
    }

    /**
     * [T-43] Preview (dry-run): validasi seluruh baris, tampilkan 5 baris pertama + error per baris.
     * Wajib sebelum commit. File sementara disimpan sampai commit.
     */
    public function previewImport()
    {
        $this->validate([
            'importFile' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        try {
            $path = $this->importFile->store('import-tmp');
            $realPath = Storage::disk('local')->path($path);
            $cabangId = currentCabangId() ?? auth()->user()?->cabang_id ?? 1;

            if ($this->importFormat === 'sid_retail') {
                $hasil = app(ImportSidRetailService::class)->preview(
                    $realPath,
                    $cabangId,
                    $this->gudangTokoId,
                    $this->gudangPusatId
                );
            } else {
                $hasil = app(ImportProdukService::class)->preview($realPath);
            }

            $this->importFilePath = $path;
            $this->importPreview = $hasil;
            $this->importStep = 'preview';
            $this->aksiKemiripan = [];
            if (! empty($hasil['kemiripan_rows'])) {
                foreach ($hasil['kemiripan_rows'] as $kr) {
                    $this->aksiKemiripan[$kr['baris']] = $kr['default_aksi'] ?? 'gabung';
                }
            }

            if ($hasil['invalid'] > 0) {
                $this->dispatch('alert', [
                    'type' => 'warning',
                    'message' => "Preview: {$hasil['valid']} baris valid, {$hasil['invalid']} baris error — perbaiki file lalu upload ulang, atau import hanya baris valid",
                ]);
            } elseif (! empty($hasil['total_peringatan']) && $hasil['total_peringatan'] > 0) {
                $this->dispatch('alert', [
                    'type' => 'info',
                    'message' => "Preview: Semua baris valid! Terdapat {$hasil['total_peringatan']} catatan perhatian kompatibilitas.",
                ]);
            }
        } catch (\Throwable $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * [T-43] Commit: antrikan ImportProdukExcelJob (queue database) + catat import_log.
     * Import = inisialisasi master (bukan stok masuk harian — tetap lewat PO).
     */
    public function commitImport()
    {
        if (! $this->boleh('wms.create', 'Anda tidak memiliki izin mengimpor produk.')) {
            return;
        }

        if (! $this->importFilePath) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Lakukan preview terlebih dahulu']);

            return;
        }

        try {
            $totalBaris = (int) ($this->importPreview['total_baris'] ?? 0);
            $cabangId = currentCabangId() ?? auth()->user()?->cabang_id ?? 1;

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

                // Lepas referensi importFilePath agar tidak terhapus jika modal ditutup saat proses
                $this->importFilePath = '';

                // Jika <= 100 baris, proses langsung secara sinkron agar hasil instan dan tidak stuck di antrian
                if ($totalBaris <= 100) {
                    ImportProdukExcelJob::dispatchSync($log->id, $filePath, auth()->id(), 'standar', $cabangId, null, null, $this->aksiKemiripan);
                } else {
                    ImportProdukExcelJob::dispatch($log->id, $filePath, auth()->id(), 'standar', $cabangId, null, null, $this->aksiKemiripan);
                }
            }

            $this->importPreview = [];
            $this->importStep = 'selesai';
            $this->cekStatusImport();
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => $totalBaris <= 100 ? 'Import produk selesai diproses.' : 'Import sedang diproses oleh sistem di latar belakang.',
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
            $this->activeImportLog = [
                'id' => $log->id,
                'status' => $log->status,
                'status_label' => $log->status_label,
                'status_badge_class' => $log->status_badge_class,
                'total_baris' => $log->total_baris,
                'sukses' => $log->sukses,
                'gagal' => $log->gagal,
                'peringatan_count' => count($log->peringatan_list),
                'peringatan_list' => $log->peringatan_list,
                'gagal_list' => $log->gagal_list,
            ];
        }
    }

    public function exportPricelist(string $format = 'xlsx')
    {
        $selectedKategoriIds = [];
        if ($this->filterKategoriId) {
            $kat = KategoriProduk::find($this->filterKategoriId);
            if ($kat) {
                $selectedKategoriIds = $kat->semuaKeturunanIds();
            }
        }

        $query = Produk::with(['brand', 'kualitas', 'kategoriRelasi', 'hargaTier.tierMembership', 'skuVariants'])
            ->when($this->produkSearch, fn ($q) => $q->cariPintar($this->produkSearch)->urutkanRelevansi($this->produkSearch))
            ->when(! empty($selectedKategoriIds), fn ($q) => $q->whereIn('kategori_id', $selectedKategoriIds))
            ->when($this->filterBrandId, fn ($q) => $q->where('brand_id', $this->filterBrandId))
            ->when($this->filterBrandKompatibel && $this->filterModelKompatibel, fn ($q) => $q->where(fn ($sub) => $sub->where(fn ($bq) => $bq->where('brand_kompatibel', $this->filterBrandKompatibel)->orWhere('brand_kompatibel', 'like', "%{$this->filterBrandKompatibel}%"))->where(fn ($mq) => $mq->where('model_kompatibel', $this->filterModelKompatibel)->orWhere('model_kompatibel', 'like', "%{$this->filterModelKompatibel}%"))->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.merk', $this->filterBrandKompatibel)->where('tipe_hp.model', $this->filterModelKompatibel))))
            ->when($this->filterBrandKompatibel && ! $this->filterModelKompatibel, fn ($q) => $q->where(fn ($sub) => $sub->where('brand_kompatibel', $this->filterBrandKompatibel)->orWhere('brand_kompatibel', 'like', "%{$this->filterBrandKompatibel}%")->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.merk', $this->filterBrandKompatibel))))
            ->when(! $this->filterBrandKompatibel && $this->filterModelKompatibel, fn ($q) => $q->where(fn ($sub) => $sub->where('model_kompatibel', $this->filterModelKompatibel)->orWhere('model_kompatibel', 'like', "%{$this->filterModelKompatibel}%")->orWhereHas('tipeHps', fn ($th) => $th->where('tipe_hp.model', $this->filterModelKompatibel))))
            ->where('is_active', true);

        return $this->lakukanExportPricelist($query, $format, 'pricelist_'.$this->filterTierHarga);
    }

    public function exportTerpilih(string $format = 'xlsx')
    {
        if (empty($this->selectedProdukIds)) {
            $this->dispatch('alert', ['type' => 'warning', 'message' => 'Pilih minimal satu produk terlebih dahulu.']);

            return null;
        }

        $query = Produk::with(['brand', 'kualitas', 'kategoriRelasi', 'hargaTier.tierMembership', 'skuVariants'])
            ->whereIn('id', $this->selectedProdukIds);

        return $this->lakukanExportPricelist($query, $format, 'terpilih_'.count($this->selectedProdukIds));
    }

    protected function lakukanExportPricelist($query, string $format = 'xlsx', string $slugSuffix = 'pricelist')
    {
        $fmt = $format === 'csv' ? 'csv' : 'xlsx';
        $userId = auth()->id() ?? 0;

        $produks = $query->orderBy('nama')->get();

        $rows = [];
        $tierLabel = $this->labelTierAktif;
        foreach ($produks as $p) {
            $hargaJual = (float) $p->harga_jual_retail;
            $tierInfo = $this->resolveHargaTierProduk($p, $this->filterTierHarga);
            $hargaTierFinal = $tierInfo['harga'];

            $rows[] = [
                'Kode / SKU' => $p->kode ?: ($p->skuVariants->first()?->sku ?? '-'),
                'Barcode' => $p->barcode ?: '-',
                'Nama Produk' => $p->nama,
                'Kategori' => $p->kategoriRelasi?->nama ?? $p->kategori ?? '-',
                'Brand' => $p->brand?->nama ?? $p->brand_kompatibel ?? '-',
                'Kualitas' => $p->kualitas?->nama ?? '-',
                'Satuan' => $p->satuan ?: 'pcs',
                'Status' => $p->is_active ? 'Aktif' : 'Nonaktif',
                'Harga Retail (Rp)' => number_format($hargaJual, 0, ',', '.'),
                'Tier Harga' => $tierLabel,
                'Harga Pricelist (Rp)' => number_format(max(0, $hargaTierFinal), 0, ',', '.'),
            ];
        }

        $slug = Str::slug($slugSuffix) ?: 'pricelist';
        $filename = $userId.'_'.$slug.'_'.now()->format('Ymd-His').'.'.$fmt;
        $path = 'exports/'.$filename;

        if ($fmt === 'csv') {
            $out = fopen('php://memory', 'r+');
            if (! empty($rows)) {
                fputcsv($out, array_keys($rows[0]));
                foreach ($rows as $row) {
                    fputcsv($out, array_values($row));
                }
            }
            rewind($out);
            $csv = stream_get_contents($out);
            fclose($out);
            Storage::disk('local')->put($path, $csv);
        } else {
            Excel::store(new class($rows) implements FromArray, WithHeadings
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
            'message' => 'Ekspor '.$tierLabel.' berhasil — unduhan dimulai.',
        ]);

        $fullPath = Storage::disk('local')->path($path);

        return response()->download($fullPath, 'pricelist-'.$slug.'-'.now()->format('Ymd-His').'.'.$fmt);
    }

    public function exportMaster(string $format = 'xlsx')
    {
        if (! $this->boleh('wms.view', 'Anda tidak memiliki hak akses untuk ekspor master produk.')) {
            return null;
        }

        $selectedKategoriIds = [];
        if ($this->filterKategoriId) {
            $kat = KategoriProduk::find($this->filterKategoriId);
            if ($kat) {
                $selectedKategoriIds = $kat->semuaKeturunanIds();
            }
        }

        $filter = [
            'search' => $this->produkSearch,
            'kategori_ids' => $selectedKategoriIds,
            'brand_id' => $this->filterBrandId,
            'brand_kompatibel' => $this->filterBrandKompatibel,
            'model_kompatibel' => $this->filterModelKompatibel,
        ];

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Ekspor master produk berhasil — unduhan dimulai.',
        ]);

        return app(ExportMasterProdukService::class)->export($format, currentCabangId(), $filter);
    }
}
