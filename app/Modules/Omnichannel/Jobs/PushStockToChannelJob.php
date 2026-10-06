<?php

namespace App\Modules\Omnichannel\Jobs;

use App\Modules\Omnichannel\Services\ChannelSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue job untuk push stok ke channel marketplace (PRD §4.10).
 * - Diserialkan per produk/SKU (anti oversell race condition).
 * - Debounce per produk melalui ShouldBeUnique.
 */
class PushStockToChannelJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $uniqueFor = 10; // Debounce 10 detik per produk

    public function __construct(
        public int $produkId,
        public ?int $skuVariantId = null
    ) {}

    public function uniqueId(): string
    {
        return $this->skuVariantId
            ? "{$this->produkId}:{$this->skuVariantId}"
            : (string) $this->produkId;
    }

    public function handle(ChannelSyncService $syncService): void
    {
        try {
            $syncService->syncStokSemuaChannel($this->produkId, $this->skuVariantId);
        } catch (\Throwable $e) {
            Log::error("PushStockToChannelJob gagal untuk produk #{$this->produkId}: {$e->getMessage()}");
            throw $e;
        }
    }
}
