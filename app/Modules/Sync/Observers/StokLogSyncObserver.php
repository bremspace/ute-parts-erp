<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Wms\Models\StokLog;
use Illuminate\Support\Facades\Log;

class StokLogSyncObserver
{
    public function created(StokLog $log): void
    {
        if (! config('sync.enabled') || config('sync.node_role') !== 'primary_store') {
            return;
        }

        try {
            $cabangId = $log->gudang?->cabang_id ?? null;

            $payload = [
                'id' => $log->id,
                'produk_id' => $log->produk_id,
                'gudang_id' => $log->gudang_id,
                'cabang_id' => $cabangId,
                'jumlah' => $log->perubahan ?? 0,
                'tipe' => $log->jenis ?? null,
                'stok_akhir' => $log->jumlah_setelah ?? 0,
            ];

            app(HybridSyncOutboxService::class)->enqueue(
                'stok.mutasi',
                $log,
                $payload,
                $cabangId
            );
        } catch (\Throwable $e) {
            Log::warning('[HybridSync] Observer StokLog gagal: '.$e->getMessage());
        }
    }
}
