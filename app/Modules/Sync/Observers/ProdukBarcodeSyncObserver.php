<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;
use App\Modules\Wms\Models\ProdukBarcode;

class ProdukBarcodeSyncObserver
{
    use SyncsModelEvents;

    public function created(ProdukBarcode $produkBarcode): void
    {
        $this->enqueueSync($produkBarcode, false);
    }

    public function updated(ProdukBarcode $produkBarcode): void
    {
        $this->enqueueSync($produkBarcode, false);
    }

    public function deleted(ProdukBarcode $produkBarcode): void
    {
        $this->enqueueSync($produkBarcode, true);
    }

    private function enqueueSync(ProdukBarcode $produkBarcode, bool $deleted): void
    {
        $this->enqueueModelEvent(
            $produkBarcode,
            'produk_barcode.sync',
            function (ProdukBarcode $model) use ($deleted): array {
                $model->loadMissing('produk');

                return [
                    'id' => $model->id,
                    'produk_id' => $model->produk_id,
                    'produk_sync_uuid' => $model->produk?->getAttribute('sync_uuid'),
                    'produk_slug' => $model->produk?->getAttribute('slug'),
                    'produk_barcode' => $model->produk?->getAttribute('barcode'),
                    'sku' => $model->sku,
                    'barcode' => $model->barcode,
                    'label' => $model->label,
                    'urutan' => (int) ($model->urutan ?? 1),
                    'is_utama' => (bool) ($model->is_utama ?? false),
                    'deleted' => $deleted,
                ];
            },
            logLabel: 'ProdukBarcodeSyncObserver'
        );
    }
}
