<?php

namespace App\Modules\Wms\Jobs;

use App\Modules\Wms\Services\ReorderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * [F1-5] Job harian reorder otomatis — dijadwalkan via bootstrap/app.php
 * (Schedule::job → dispatch ke queue, database driver + Supervisor).
 * Berat: query stok + grouping PO + notifikasi — tidak boleh jalan sync di request.
 */
class ReorderOtomatisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function handle(ReorderService $service): void
    {
        $service->jalankan();
    }
}
