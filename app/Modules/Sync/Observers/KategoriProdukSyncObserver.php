<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Wms\Models\KategoriProduk;
use Illuminate\Support\Facades\Log;

class KategoriProdukSyncObserver
{
    public function saved(KategoriProduk $kategori): void
    {
        if (! config('sync.enabled')) {
            return;
        }

        try {
            $payload = [
                'id' => $kategori->id,
                'parent_id' => $kategori->parent_id,
                'nama' => $kategori->nama,
                'slug' => $kategori->slug,
                'icon' => $kategori->icon,
                'urutan' => $kategori->urutan,
                'is_active' => (bool) $kategori->is_active,
                'is_default' => (bool) $kategori->is_default,
                'updated_at' => $kategori->updated_at?->toIso8601String(),
            ];

            app(HybridSyncOutboxService::class)->enqueue(
                'kategori_produk.upsert',
                $kategori,
                $payload,
                null
            );
        } catch (\Throwable $e) {
            Log::warning('[HybridSync] Observer KategoriProduk gagal: '.$e->getMessage());
        }
    }
}
