<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;
use App\Modules\Wms\Models\KategoriProduk;

class KategoriProdukSyncObserver
{
    use SyncsModelEvents;

    public function created(KategoriProduk $kategori): void
    {
        $this->enqueueSync($kategori);
    }

    public function updated(KategoriProduk $kategori): void
    {
        $this->enqueueSync($kategori);
    }

    public function deleted(KategoriProduk $kategori): void
    {
        $this->enqueueDeletedSnapshot(
            $kategori,
            'kategori_produk.upsert',
            [
                'slug' => $kategori->slug,
                'nama' => $kategori->nama,
            ],
            logLabel: 'KategoriProdukSyncObserver:delete'
        );
    }

    private function enqueueSync(KategoriProduk $kategori): void
    {
        $this->enqueueModelEvent(
            $kategori,
            'kategori_produk.upsert',
            function (KategoriProduk $model): array {
                $syncUuid = $this->ensureSyncUuid($model);
                $model->loadMissing('parent');

                return [
                    'id' => $model->id,
                    'sync_uuid' => $syncUuid,
                    'parent_id' => $model->parent_id,
                    'parent_sync_uuid' => $model->parent?->getAttribute('sync_uuid'),
                    'parent_slug' => $model->parent?->getAttribute('slug'),
                    'nama' => $model->nama,
                    'slug' => $model->slug,
                    'icon' => $model->icon,
                    'urutan' => (int) ($model->urutan ?? 0),
                    'is_active' => (bool) ($model->is_active ?? true),
                    'is_default' => (bool) ($model->getAttribute('is_default') ?? false),
                    'created_at' => $model->created_at?->toIso8601String(),
                    'updated_at' => $model->updated_at?->toIso8601String(),
                ];
            },
            logLabel: 'KategoriProdukSyncObserver'
        );
    }
}
