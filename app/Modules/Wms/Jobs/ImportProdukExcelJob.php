<?php

namespace App\Modules\Wms\Jobs;

use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Services\ImportProdukService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * [T-43] Proses import master produk via queue (QUEUE_CONNECTION=database).
 * Bukan sync di request — setelah commit, job ini di-antri; hasil dikirim
 * lewat notifikasi_keluar (inapp) + import_log (sukses/gagal per baris).
 */
class ImportProdukExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(
        public int $importLogId,
        public string $filePath,
        public ?int $userId = null
    ) {}

    public function handle(ImportProdukService $service, NotificationService $notifikasi): void
    {
        $log = ImportLog::find($this->importLogId);
        if (! $log) {
            return;
        }

        $log->update(['status' => 'proses', 'detail' => null]);

        try {
            $filePath = $this->filePath;
            // Relative path (storage/app/...) → absolut
            if (! str_starts_with($filePath, DIRECTORY_SEPARATOR)) {
                $filePath = storage_path('app/'.$filePath);
            }

            $hasil = $service->commit($filePath, $this->importLogId, $this->userId);

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
                "Import produk selesai: {$hasil['sukses']} sukses, {$hasil['gagal']} gagal (jurnal stok awal Rp "
                    .number_format($hasil['jurnal_nilai'], 0, ',', '.').').',
                [
                    'import_log_id' => $this->importLogId,
                    'sukses' => $hasil['sukses'],
                    'gagal' => $hasil['gagal'],
                    'type' => $hasil['gagal'] > 0 ? 'warning' : 'success',
                ]
            );

            // Bersihkan file sementara
            @unlink($filePath);
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
