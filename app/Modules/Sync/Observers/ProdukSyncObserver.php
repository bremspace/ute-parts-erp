<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Wms\Models\Produk;
use Illuminate\Support\Facades\Log;

class ProdukSyncObserver
{
    public function saved(Produk $produk): void
    {
        if (! config('sync.enabled') || config('sync.node_role') !== 'primary_store') {
            return;
        }

        try {
            $payload = [
                'id' => $produk->id,
                'sku' => $produk->barcode ?? $produk->slug ?? (string) $produk->id,
                'nama' => $produk->nama,
                'harga_jual' => $produk->harga_jual_retail ?? 0,
                'harga_beli' => $produk->harga_beli ?? 0,
                'cabang_id' => $produk->cabang_id ?? null,
                'aktif' => (bool) ($produk->is_active ?? true),
            ];

            app(HybridSyncOutboxService::class)->enqueue(
                'produk.upsert',
                $produk,
                $payload,
                $produk->cabang_id ?? null
            );
        } catch (\Throwable $e) {
            Log::warning('[HybridSync] Observer Produk gagal: '.$e->getMessage());
        }
    }
}
