<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Servis\Models\TiketServis;
use App\Modules\Sync\Services\HybridSyncOutboxService;
use Illuminate\Support\Facades\Log;

class TiketServisSyncObserver
{
    public function updated(TiketServis $tiket): void
    {
        if (! config('sync.enabled') || config('sync.node_role') !== 'primary_store') {
            return;
        }

        if (! $tiket->wasChanged('status')) {
            return;
        }

        try {
            $payload = [
                'id' => $tiket->id,
                'no_tiket' => $tiket->no_tiket ?? null,
                'nomor_tiket' => $tiket->no_tiket ?? null,
                'status' => $tiket->status,
                'catatan_teknisi' => $tiket->catatan_admin ?? null,
                'cabang_id' => $tiket->cabang_id ?? null,
            ];

            app(HybridSyncOutboxService::class)->enqueue(
                'servis.update',
                $tiket,
                $payload,
                $tiket->cabang_id ?? null
            );
        } catch (\Throwable $e) {
            Log::warning('[HybridSync] Observer TiketServis gagal: '.$e->getMessage());
        }
    }
}
