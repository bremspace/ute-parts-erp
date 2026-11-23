<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Wms\Models\SatuanUnit;
use Illuminate\Support\Facades\Log;

class SatuanUnitSyncObserver
{
    public function saved(SatuanUnit $satuan): void
    {
        if (! config('sync.enabled')) {
            return;
        }

        try {
            $payload = [
                'id' => $satuan->id,
                'kode' => $satuan->kode,
                'nama' => $satuan->nama,
                'is_active' => (bool) $satuan->is_active,
                'is_default' => (bool) $satuan->is_default,
                'updated_at' => $satuan->updated_at?->toIso8601String(),
            ];

            app(HybridSyncOutboxService::class)->enqueue(
                'satuan_unit.upsert',
                $satuan,
                $payload,
                null
            );
        } catch (\Throwable $e) {
            Log::warning('[HybridSync] Observer SatuanUnit gagal: '.$e->getMessage());
        }
    }
}
