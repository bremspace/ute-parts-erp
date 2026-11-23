<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Wms\Models\KualitasProduk;
use Illuminate\Support\Facades\Log;

class KualitasProdukSyncObserver
{
    public function saved(KualitasProduk $kualitas): void
    {
        if (! config('sync.enabled') || config('sync.node_role') !== 'primary_store') {
            return;
        }

        try {
            $payload = [
                'id' => $kualitas->id,
                'nama' => $kualitas->nama,
                'keterangan' => $kualitas->keterangan,
                'is_active' => (bool) $kualitas->is_active,
                'brand_id' => $kualitas->brand_id,
                'is_default' => (bool) $kualitas->is_default,
                'updated_at' => $kualitas->updated_at?->toIso8601String(),
            ];

            app(HybridSyncOutboxService::class)->enqueue(
                'kualitas_produk.upsert',
                $kualitas,
                $payload,
                null
            );
        } catch (\Throwable $e) {
            Log::warning('[HybridSync] Observer KualitasProduk gagal: '.$e->getMessage());
        }
    }
}
