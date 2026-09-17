<?php

namespace App\Modules\Akunting\Jobs;

use App\Modules\Akunting\Services\ExportLaporanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * [T-24] Export laporan via queue (async, RAM 1GB) — link download via InteractWithDownloads
 * atau dicatat ke tabel export; sederhananya: kirim ke session flash utk ditampilkan setelah job.
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
        public ?int $userId
    ) {}

    public function handle(ExportLaporanService $service): void
    {
        try {
            $path = $service->export($this->jenis, $this->periodeDari, $this->periodeSampai, $this->cabangId, $this->akunId);

            // Storing path di session — pemilik request dapat mengambil link
            session()->flash('export_ready', [
                'jenis' => $this->jenis,
                'path' => $path,
                'url' => url('/api/akunting/export/download?path=' . base64_encode($path)),
            ]);
        } catch (\Throwable $e) {
            session()->flash('export_error', $e->getMessage());
        }
    }
}