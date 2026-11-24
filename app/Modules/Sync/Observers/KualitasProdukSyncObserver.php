<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;
use App\Modules\Wms\Models\KualitasProduk;

class KualitasProdukSyncObserver
{
    use SyncsModelEvents;

    public function created(KualitasProduk $kualitas): void
    {
        $this->enqueueSync($kualitas);
    }

    public function updated(KualitasProduk $kualitas): void
    {
        $this->enqueueSync($kualitas);
    }

    private function enqueueSync(KualitasProduk $kualitas): void
    {
        $this->enqueueModelEvent(
            $kualitas,
            'kualitas_produk.upsert',
            function (KualitasProduk $model): array {
                $syncUuid = $this->ensureSyncUuid($model);
                $model->loadMissing('brand');

                return [
                    'id' => $model->id,
                    'sync_uuid' => $syncUuid,
                    'nama' => $model->nama,
                    'keterangan' => $model->keterangan,
                    'brand_id' => $model->brand_id,
                    'brand_sync_uuid' => $model->brand?->getAttribute('sync_uuid'),
                    'brand_nama' => $model->brand?->getAttribute('nama'),
                    'is_active' => (bool) ($model->is_active ?? true),
                    'is_default' => (bool) ($model->getAttribute('is_default') ?? false),
                    'created_at' => $model->created_at?->toIso8601String(),
                    'updated_at' => $model->updated_at?->toIso8601String(),
                ];
            },
            logLabel: 'KualitasProdukSyncObserver'
        );
    }
}
