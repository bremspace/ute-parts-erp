<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;
use App\Modules\Wms\Models\Brand;

class BrandSyncObserver
{
    use SyncsModelEvents;

    public function created(Brand $brand): void
    {
        $this->enqueueSync($brand);
    }

    public function updated(Brand $brand): void
    {
        $this->enqueueSync($brand);
    }

    public function deleted(Brand $brand): void
    {
        $this->enqueueDeletedSnapshot(
            $brand,
            'brand.upsert',
            ['nama' => $brand->nama],
            logLabel: 'BrandSyncObserver:delete'
        );
    }

    private function enqueueSync(Brand $brand): void
    {
        $this->enqueueModelEvent(
            $brand,
            'brand.upsert',
            function (Brand $model): array {
                $syncUuid = $this->ensureSyncUuid($model);

                return [
                    'id' => $model->id,
                    'sync_uuid' => $syncUuid,
                    'nama' => $model->nama,
                    'keterangan' => $model->keterangan,
                    'is_active' => (bool) ($model->is_active ?? true),
                    'is_default' => (bool) ($model->is_default ?? false),
                    'created_at' => $model->created_at?->toIso8601String(),
                    'updated_at' => $model->updated_at?->toIso8601String(),
                ];
            },
            logLabel: 'BrandSyncObserver'
        );
    }
}
