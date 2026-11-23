<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Support\Facades\Log;

class SkuVariantSyncObserver
{
    public function saved(SkuVariant $variant): void
    {
        if (! config('sync.enabled')) {
            return;
        }

        try {
            $payload = [
                'id' => $variant->id,
                'produk_id' => $variant->produk_id,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'nama_varian' => $variant->nama_varian,
                'satuan_kode' => $variant->satuan_kode,
                'atribut' => $variant->atribut,
                'harga_beli' => $variant->harga_beli,
                'harga_jual_retail' => $variant->harga_jual_retail,
                'is_active' => (bool) $variant->is_active,
                'updated_at' => $variant->updated_at?->toIso8601String(),
            ];

            app(HybridSyncOutboxService::class)->enqueue(
                'sku_variant.upsert',
                $variant,
                $payload,
                null
            );
        } catch (\Throwable $e) {
            Log::warning('[HybridSync] Observer SkuVariant gagal: '.$e->getMessage());
        }
    }
}
