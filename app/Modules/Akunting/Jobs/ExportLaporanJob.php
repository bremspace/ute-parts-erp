<?php

namespace App\Modules\Akunting\Jobs;

use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Notifikasi\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * [T-24] Export laporan via queue (async, RAM 1GB).
 * Selesai → notifikasi inapp via NotificationService (persist ke tabel notifikasi_keluar,
 * bukan session flash — session tidak tersedia di context job & flash rapuh).
 * Link download: GET /api/akunting/export/download?path={base64}  (route sudah ada :149).
 */
class ExportLaporanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $jenis,
        public ?string $periodeDari,
        public ?string $periodeSampai,
        public ?int $cabangId,
        public ?int $akunId,
        public ?int $userId,
        public string $format = 'xlsx' // [F2-5] xlsx | csv
    ) {}

    public function handle(ExportLaporanService $service, NotificationService $notif): void
    {
        try {
            // [P0] Forward userId eksplisit — di queue worker auth() = null, tanpa
            // ini filename prefix jatuh ke "0_" dan download 403 (ownership ACC-11b).
            $path = $service->export($this->jenis, $this->periodeDari, $this->periodeSampai, $this->cabangId, $this->akunId, $this->format, $this->userId);

            // Notifikasi persist + queue (inapp = catat di database, tanpa outbound)
            $notif->kirim(
                'inapp', null,
                'Export '.str_replace('_', ' ', $this->jenis).' selesai',
                'Laporan '.$this->jenis.' siap diunduh (periode '.($this->periodeDari ?? '-').' s.d. '.($this->periodeSampai ?? '-').').',
                [
                    'url' => url('/api/akunting/export/download?path='.base64_encode($path)),
                    'jenis' => $this->jenis,
                    'path' => $path,
                    'user_id' => $this->userId,
                ]
            );
        } catch (\Throwable $e) {
            Log::error("ExportLaporanJob gagal (jenis={$this->jenis}): {$e->getMessage()}");

            // Beri tahu user yang meminta export (jika ada)
            if ($this->userId) {
                $notif->kirim(
                    'inapp', null,
                    'Export laporan gagal',
                    'Laporan '.$this->jenis.' gagal diproses: '.$e->getMessage(),
                    ['jenis' => $this->jenis, 'user_id' => $this->userId]
                );
            }
        }
    }
}
