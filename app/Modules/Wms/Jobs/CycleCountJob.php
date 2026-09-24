<?php

namespace App\Modules\Wms\Jobs;

use App\Modules\Wms\Services\CycleCountService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * [F3-7] Job periodik cycle count — dijadwalkan via bootstrap/app.php
 * (Schedule::job → dispatch ke queue, database driver + Supervisor — pola F1-6/F1-5).
 * Cek jadwal jatuh tempo (hari/jam per schedule) → generate sample task acak.
 * Berat: query stok + shuffle sample — tidak boleh jalan sync di request.
 */
class CycleCountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function handle(CycleCountService $service): void
    {
        $service->jalankanHarian();
    }
}
