<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;
use App\Modules\Wms\Models\ProdukSatuanKonversi;

class ProdukSatuanKonversiSyncObserver
{
    use SyncsModelEvents;

    public function created(ProdukSatuanKonversi $konversi): void
    {
        $this->enqueueSync($konversi, false);
    }

    public function updated(ProdukSatuanKonversi $konversi): void
    {
        $this->enqueueSync($konversi, false);
    }

    public function deleted(ProdukSatuanKonversi $konversi): void
    {
        $this->enqueueSync($konversi, true);
    }

    private function enqueueSync(ProdukSatuanKonversi $konversi, bool $deleted): void
    {
        $this->enqueueModelEvent(
            $konversi,
            'produk_satuan_konversi.upsert',
            function (ProdukSatuanKonversi $model) use ($deleted): array {
                $syncUuid = $this->ensureSyncUuid($model);
                $model->loadMissing('produk');

                return [
                    'id' => $model->id,
                    'sync_uuid' => $syncUuid,
                    'produk_id' => $model->produk_id,
                    'produk_sync_uuid' => $model->produk?->getAttribute('sync_uuid'),
                    'produk_slug' => $model->produk?->getAttribute('slug'),
                    'produk_barcode' => $model->produk?->getAttribute('barcode'),
                    'satuan_kode' => $model->satuan_kode,
                    'satuan_asal' => $model->satuan_asal,
                    'pengali' => (float) ($model->pengali ?? 1),
                    'faktor_konversi' => (float) ($model->faktor_konversi ?? 1),
                    'harga_beli' => $this->toNullableFloat($model, 'harga_beli'),
                    'harga_jual' => $this->toNullableFloat($model, 'harga_jual'),
                    'barcode' => $model->barcode,
                    'is_satuan_beli' => (bool) ($model->is_satuan_beli ?? false),
                    'is_satuan_jual' => (bool) ($model->is_satuan_jual ?? false),
                    'urutan' => (int) ($model->urutan ?? 1),
                    'deleted' => $deleted,
                ];
            },
            logLabel: 'ProdukSatuanKonversiSyncObserver'
        );
    }
}
