<?php

namespace App\Modules\Hr\Jobs;

use App\Modules\Hr\Services\KpiService;
use App\Modules\Reseller\Services\KomisiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * [F3-8b] Job KPI bulanan — hitung KPI semua karyawan per periode dari data
 * real (queue database, dijadwalkan tiap awal bulan via bootstrap/app.php).
 * [F3-8c] Setelah perhitungan KPI, trigger komisi target_kpi (PRD §4.3).
 */
class HitungKpiBulananJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public ?string $periode = null) {}

    public function handle(KpiService $service, KomisiService $komisiService): void
    {
        $periode = $this->periode ?: now()->format('Y-m');
        $service->hitung($periode);
        $komisiService->hitungKomisiMultiAktor('target_kpi', ['periode' => $periode]);
    }
}
