<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Pos\Models\HargaTier;
use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;

class HargaTierSyncObserver
{
    use SyncsModelEvents;

    public function created(HargaTier $hargaTier): void
    {
        $this->enqueueSync($hargaTier, false);
    }

    public function updated(HargaTier $hargaTier): void
    {
        $this->enqueueSync($hargaTier, false);
    }

    public function deleted(HargaTier $hargaTier): void
    {
        $this->enqueueSync($hargaTier, true);
    }

    private function enqueueSync(HargaTier $hargaTier, bool $deleted): void
    {
        $this->enqueueModelEvent(
            $hargaTier,
            'harga_tier.upsert',
            function (HargaTier $model) use ($deleted): array {
                $syncUuid = $this->ensureSyncUuid($model);
                $model->loadMissing(['produk', 'skuVariant', 'tierMembership']);

                return [
                    'id' => $model->id,
                    'sync_uuid' => $syncUuid,
                    'produk_id' => $model->produk_id,
                    'produk_sync_uuid' => $model->produk?->getAttribute('sync_uuid'),
                    'produk_slug' => $model->produk?->getAttribute('slug'),
                    'produk_barcode' => $model->produk?->getAttribute('barcode'),
                    'sku_variant_id' => $model->sku_variant_id,
                    'sku_variant_sync_uuid' => $model->skuVariant?->getAttribute('sync_uuid'),
                    'sku_variant_sku' => $model->skuVariant?->getAttribute('sku'),
                    'tier_membership_id' => $model->tier_membership_id,
                    'tier_membership_kode' => $model->tierMembership?->getAttribute('kode'),
                    'is_reseller' => (bool) ($model->is_reseller ?? false),
                    'tipe_konsumen' => $model->tipe_konsumen,
                    'harga' => (float) ($model->harga ?? 0),
                    'persen_diskon' => $this->toNullableFloat($model, 'persen_diskon'),
                    'nominal_tetap' => $this->toNullableFloat($model, 'nominal_tetap'),
                    'deleted' => $deleted,
                ];
            },
            logLabel: 'HargaTierSyncObserver'
        );
    }
}
