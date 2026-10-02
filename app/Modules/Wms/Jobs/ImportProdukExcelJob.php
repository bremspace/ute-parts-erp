<?php

namespace App\Modules\Wms\Jobs;

use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Services\ImportProdukService;
use App\Modules\Wms\Services\ImportSidRetailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * [T-43] Proses import master produk via queue (QUEUE_CONNECTION=database).
 * Bukan sync di request — setelah commit, job ini di-antri; hasil dikirim
 * lewat notifikasi_keluar (inapp) + import_log (sukses/gagal per baris).
 */
class ImportProdukExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 3600;

    public function __construct(
        public int $importLogId,
        public string $filePath,
        public ?int $userId = null,
        public string $format = 'standar',
        public ?int $cabangId = null,
        public ?int $gudangTokoId = null,
        public ?int $gudangPusatId = null
    ) {}

    public function handle(
        ImportProdukService $service,
        ImportSidRetailService $sidService,
        NotificationService $notifikasi
    ): void {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $log = ImportLog::find($this->importLogId);
        if (! $log) {
            return;
        }

        $log->update(['status' => 'proses', 'detail' => null]);

        try {
            $filePath = $this->filePath;
            // Relative path (storage/app/...) → absolut via resolveFilePath
            if (! str_starts_with($filePath, DIRECTORY_SEPARATOR)) {
                $filePath = $this->format === 'sid_retail'
                    ? $sidService->resolveFilePath($filePath)
                    : $service->resolveFilePath($filePath);
            }

            if ($this->format === 'sid_retail') {
                $cabangId = $this->cabangId ?? 1;
                $hasil = $sidService->commit(
                    $filePath,
                    $this->importLogId,
                    $cabangId,
                    $this->gudangTokoId,
                    $this->gudangPusatId,
                    $this->userId
                );
            } else {
                $hasil = $service->commit($filePath, $this->importLogId, $this->userId);
            }

            $label = $hasil['label_status'] ?? ($hasil['gagal'] > 0 ? 'Diterima Sebagian' : 'Diterima Sempurna');
            $pesanPeringatan = (! empty($hasil['total_peringatan']) && $hasil['total_peringatan'] > 0)
                ? " (Terdapat {$hasil['total_peringatan']} catatan kemiripan/kompatibilitas)."
                : '.';

            $log->update([
                'total_baris' => $hasil['total_baris'],
                'sukses' => $hasil['sukses'],
                'gagal' => $hasil['gagal'],
                'status' => 'selesai',
                'detail' => $hasil['detail'],
            ]);

            $notifikasi->kirim(
                'inapp',
                null,
                'Import Produk Selesai',
                "Import produk selesai ({$label}): {$hasil['sukses']} sukses, {$hasil['gagal']} gagal{$pesanPeringatan} (jurnal stok awal Rp "
                    .number_format($hasil['jurnal_nilai'], 0, ',', '.').').',
                [
                    'import_log_id' => $this->importLogId,
                    'status_keseluruhan' => $hasil['status_keseluruhan'] ?? ($hasil['gagal'] > 0 ? 'sukses_sebagian' : 'sukses_penuh'),
                    'label_status' => $label,
                    'sukses' => $hasil['sukses'],
                    'gagal' => $hasil['gagal'],
                    'total_peringatan' => $hasil['total_peringatan'] ?? 0,
                    'type' => $hasil['gagal'] > 0 ? 'warning' : 'success',
                ]
            );

            // Bersihkan file sementara
            @unlink($filePath);
            if (! empty($this->filePath)) {
                Storage::disk('local')->delete($this->filePath);
            }
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'gagal',
                'detail' => ['fatal' => $e->getMessage()],
            ]);

            $notifikasi->kirim(
                'inapp',
                null,
                'Import Produk Gagal',
                'Import produk gagal diproses: '.$e->getMessage(),
                ['import_log_id' => $this->importLogId, 'type' => 'error']
            );

            throw $e;
        }
    }
}
