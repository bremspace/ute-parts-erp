<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;
use App\Modules\Wms\Models\TipeHp;

class TipeHpSyncObserver
{
    use SyncsModelEvents;

    public function created(TipeHp $tipeHp): void
    {
        $this->enqueueSync($tipeHp);
    }

    public function updated(TipeHp $tipeHp): void
    {
        $this->enqueueSync($tipeHp);
    }

    public function deleted(TipeHp $tipeHp): void
    {
        $this->enqueueDeletedSnapshot(
            $tipeHp,
            'tipe_hp.upsert',
            [
                'merk' => $tipeHp->merk,
                'model' => $tipeHp->model,
            ],
            logLabel: 'TipeHpSyncObserver:delete'
        );
    }

    private function enqueueSync(TipeHp $tipeHp): void
    {
        $this->enqueueModelEvent(
            $tipeHp,
            'tipe_hp.upsert',
            function (TipeHp $model): array {
                $syncUuid = $this->ensureSyncUuid($model);

                return [
                    'id' => $model->id,
                    'sync_uuid' => $syncUuid,
                    'merk' => $model->merk,
                    'model' => $model->model,
                    'kode_produk' => $model->kode_produk,
                    'nama' => $model->nama,
                    'is_active' => (bool) ($model->is_active ?? true),
                    'is_default' => (bool) ($model->is_default ?? false),
                    'created_at' => $model->created_at?->toIso8601String(),
                    'updated_at' => $model->updated_at?->toIso8601String(),
                ];
            },
            logLabel: 'TipeHpSyncObserver'
        );
    }
}
