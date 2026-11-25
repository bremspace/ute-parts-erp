<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;
use App\Modules\Wms\Models\StokLog;

class StokLogSyncObserver
{
    use SyncsModelEvents;

    public function created(StokLog $stokLog): void
    {
        if (config('sync.node_role') !== 'primary_store' || $stokLog->referensi_tipe === 'sync_inbox') {
            return;
        }

        $this->enqueueModelEvent(
            $stokLog,
            'stok.mutasi',
            function (StokLog $model): array {
                $model->loadMissing(['produk', 'skuVariant', 'gudang']);

                return [
                    'id' => $model->id,
                    'produk_id' => $model->produk_id,
                    'produk_sync_uuid' => $model->produk?->getAttribute('sync_uuid'),
                    'produk_slug' => $model->produk?->getAttribute('slug'),
                    'produk_barcode' => $model->produk?->getAttribute('barcode'),
                    'sku_variant_id' => $model->sku_variant_id,
                    'sku_variant_sku' => $model->skuVariant?->getAttribute('sku'),
                    'gudang_id' => $model->gudang_id,
                    'gudang_kode' => $model->gudang?->getAttribute('kode'),
                    'user_id' => $model->user_id,
                    'jenis' => $model->jenis,
                    'referensi_tipe' => $model->referensi_tipe,
                    'referensi_id' => $model->referensi_id,
                    'jumlah_sebelum' => (int) $model->jumlah_sebelum,
                    'perubahan' => (int) $model->perubahan,
                    'jumlah_setelah' => (int) $model->jumlah_setelah,
                    'stok_akhir' => (int) $model->jumlah_setelah,
                    'qty' => (int) $model->jumlah_setelah,
                    'catatan' => $model->catatan,
                    'created_at' => $model->created_at?->toIso8601String(),
                ];
            },
            cabangId: $stokLog->gudang?->cabang_id,
            logLabel: 'StokLogSyncObserver'
        );
    }
}
