<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;
use App\Modules\Wms\Models\SatuanUnit;

class SatuanUnitSyncObserver
{
    use SyncsModelEvents;

    public function created(SatuanUnit $satuan): void
    {
        $this->enqueueSync($satuan);
    }

    public function updated(SatuanUnit $satuan): void
    {
        $this->enqueueSync($satuan);
    }

    private function enqueueSync(SatuanUnit $satuan): void
    {
        $this->enqueueModelEvent(
            $satuan,
            'satuan_unit.upsert',
            function (SatuanUnit $model): array {
                $syncUuid = $this->ensureSyncUuid($model);

                return [
                    'id' => $model->id,
                    'sync_uuid' => $syncUuid,
                    'kode' => $model->kode,
                    'nama' => $model->nama,
                    'is_active' => (bool) ($model->is_active ?? true),
                    'is_default' => (bool) ($model->is_default ?? false),
                    'created_at' => $model->created_at?->toIso8601String(),
                    'updated_at' => $model->updated_at?->toIso8601String(),
                ];
            },
            logLabel: 'SatuanUnitSyncObserver'
        );
    }
}
