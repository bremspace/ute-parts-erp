<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Wms\Models\TipeHp;
use Illuminate\Support\Facades\Log;

class TipeHpSyncObserver
{
    public function saved(TipeHp $tipeHp): void
    {
        if (! config('sync.enabled')) {
            return;
        }

        try {
            $payload = [
                'id' => $tipeHp->id,
                'merk' => $tipeHp->merk,
                'model' => $tipeHp->model,
                'kode_produk' => $tipeHp->kode_produk,
                'nama' => $tipeHp->nama,
                'is_active' => (bool) $tipeHp->is_active,
                'is_default' => (bool) $tipeHp->is_default,
                'updated_at' => $tipeHp->updated_at?->toIso8601String(),
            ];

            app(HybridSyncOutboxService::class)->enqueue(
                'tipe_hp.upsert',
                $tipeHp,
                $payload,
                null
            );
        } catch (\Throwable $e) {
            Log::warning('[HybridSync] Observer TipeHp gagal: '.$e->getMessage());
        }
    }
}
