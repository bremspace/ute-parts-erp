<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;
use App\Modules\Wms\Models\Produk;

class ProdukSyncObserver
{
    use SyncsModelEvents;

    public function created(Produk $produk): void
    {
        $this->enqueueSync($produk);
    }

    public function updated(Produk $produk): void
    {
        $this->enqueueSync($produk);
    }

    public function deleted(Produk $produk): void
    {
        $this->enqueueSync($produk, forceDeleted: ! $produk->exists);
    }

    public function restored(Produk $produk): void
    {
        $this->enqueueSync($produk);
    }

    private function enqueueSync(Produk $produk, bool $forceDeleted = false): void
    {
        $this->enqueueModelEvent(
            $produk,
            'produk.upsert',
            function (Produk $model) use ($forceDeleted): array {
                $syncUuid = $this->ensureSyncUuid($model);
                $model->loadMissing(['brand', 'kualitas', 'kategoriRelasi']);

                return [
                    'id' => $model->id,
                    'sync_uuid' => $syncUuid,
                    'nama' => $model->nama,
                    'slug' => $model->slug,
                    'barcode' => $model->barcode,
                    'barcode_alt' => $model->getAttribute('barcode_alt'),
                    'deskripsi' => $model->deskripsi,
                    'kategori' => $model->kategori,
                    'kategori_id' => $model->kategori_id,
                    'kategori_sync_uuid' => $model->kategoriRelasi?->getAttribute('sync_uuid'),
                    'kategori_slug' => $model->kategoriRelasi?->getAttribute('slug'),
                    'brand_kompatibel' => $model->brand_kompatibel,
                    'model_kompatibel' => $model->model_kompatibel,
                    'kondisi' => $model->kondisi,
                    'satuan' => $model->satuan,
                    'harga_beli' => (float) $model->harga_beli,
                    'metode_harga_beli' => $model->metode_harga_beli,
                    'harga_jual_retail' => (float) $model->harga_jual_retail,
                    'margin_persen' => $this->toNullableFloat($model, 'margin_persen'),
                    'gambar' => $model->gambar,
                    'foto' => $model->foto,
                    'meta_title' => $model->meta_title,
                    'meta_description' => $model->meta_description,
                    'kompatibilitas_hp' => $model->kompatibilitas_hp,
                    'is_active' => (bool) ($model->is_active ?? true),
                    'brand_id' => $model->brand_id,
                    'brand_sync_uuid' => $model->brand?->getAttribute('sync_uuid'),
                    'brand_nama' => $model->brand?->getAttribute('nama'),
                    'kualitas_id' => $model->kualitas_id,
                    'kualitas_sync_uuid' => $model->kualitas?->getAttribute('sync_uuid'),
                    'kualitas_nama' => $model->kualitas?->getAttribute('nama'),
                    'supplier_id' => $model->supplier_id,
                    'harga_fleksibel' => (bool) ($model->getAttribute('harga_fleksibel') ?? false),
                    'sn' => (bool) ($model->getAttribute('sn') ?? false),
                    'abc_class' => $model->abc_class,
                    'reorder_point' => $model->reorder_point !== null ? (int) $model->reorder_point : null,
                    'min_stock' => $model->min_stock !== null ? (int) $model->min_stock : null,
                    'max_stock' => $model->max_stock !== null ? (int) $model->max_stock : null,
                    'is_ondemand' => (bool) ($model->is_ondemand ?? false),
                    'kena_pajak' => (bool) ($model->kena_pajak ?? false),
                    'deleted_at' => $this->formatIso8601($model->getAttribute('deleted_at')),
                    'force_deleted' => $forceDeleted,
                ];
            },
            cabangId: null,
            logLabel: 'ProdukSyncObserver'
        );
    }
}
