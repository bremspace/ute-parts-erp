<?php

namespace App\Modules\Crm\Console\Commands;

use App\Modules\Crm\Services\TierService;
use Illuminate\Console\Command;

class RecalcTierCommand extends Command
{
    protected $signature = 'tier:recalc';

    protected $description = 'Rekalkulasi tier membership semua pelanggan dari total belanja 12 bulan (PRD §4.4)';

    public function handle(TierService $tierService): int
    {
        $updated = $tierService->recalcSemua();

        $this->info("Tier membership direkalkulasi. Pelanggan yang diupdate: {$updated}");

        return self::SUCCESS;
    }
}
