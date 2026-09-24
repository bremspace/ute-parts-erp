<?php

namespace App\Modules\Wms\Jobs;

use App\Modules\Wms\Services\SupplierScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * [F3-2] Job hitung skor supplier per periode (queue).
 */
class SupplierScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $periode,
        public ?int $cabangId = null
    ) {}

    public function handle(SupplierScoringService $service): array
    {
        return $service->hitungSemua($this->periode, $this->cabangId);
    }
}
