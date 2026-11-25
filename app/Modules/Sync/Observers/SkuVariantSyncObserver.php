<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;
use App\Modules\Wms\Models\SkuVariant;

class SkuVariantSyncObserver
{
    use SyncsModelEvents;

    public function created(SkuVariant $variant): void
    {
        $this->enqueueSync($variant);
    }

    public function updated(SkuVariant $variant): void
    {
        $this->enqueueSync($variant);
    }

    private function enqueueSync(SkuVariant $variant): void
    {
        $this->enqueueModelEvent(
            $variant,
            'sku_variant.upsert',
            function (SkuVariant $model): array {
                $syncUuid = $this->ensureSyncUuid($model);
                $model->loadMissing('produk');

                return [
                    'id' => $model->id,
                    'sync_uuid' => $syncUuid,
                    'produk_id' => $model->produk_id,
                    'produk_sync_uuid' => $model->produk?->getAttribute('sync_uuid'),
                    'produk_slug' => $model->produk?->getAttribute('slug'),
                    'produk_barcode' => $model->produk?->getAttribute('barcode'),
                    'sku' => $model->sku,
                    'barcode' => $model->getAttribute('barcode'),
                    'nama_varian' => $model->nama_varian,
                    'satuan_kode' => $model->getAttribute('satuan_kode'),
                    'atribut' => $model->atribut,
                    'harga_beli' => $this->toNullableFloat($model, 'harga_beli'),
                    'harga_jual_retail' => $this->toNullableFloat($model, 'harga_jual_retail'),
                    'is_active' => (bool) ($model->is_active ?? true),
                ];
            },
            logLabel: 'SkuVariantSyncObserver'
        );
    }
}
