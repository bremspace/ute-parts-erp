<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Wms\Models\Brand;
use Illuminate\Support\Facades\Log;

class BrandSyncObserver
{
    public function saved(Brand $brand): void
    {
        if (! config('sync.enabled')) {
            return;
        }

        try {
            $payload = [
                'id' => $brand->id,
                'nama' => $brand->nama,
                'keterangan' => $brand->keterangan,
                'is_active' => (bool) $brand->is_active,
                'is_default' => (bool) $brand->is_default,
                'updated_at' => $brand->updated_at?->toIso8601String(),
            ];

            app(HybridSyncOutboxService::class)->enqueue(
                'brand.upsert',
                $brand,
                $payload,
                null
            );
        } catch (\Throwable $e) {
            Log::warning('[HybridSync] Observer Brand gagal: '.$e->getMessage());
        }
    }
}
