<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;
use App\Modules\Wms\Models\StokLog;

class StokLogSyncObserver
{
    use SyncsModelEvents;

    public function created(StokLog $stokLog): void
    {
        if ($stokLog->referensi_tipe === 'sync_inbox') {
            return;
        }

        // Pada node cloud_storefront, mutasi stok dari order.online_paid maupun failover pos_cloud (VPS-*)
        // sudah dipotong langsung saat primary_store memproses transaksi tersebut; cegah double-deduction.
        if (config('sync.node_role') === 'cloud_storefront'
            && $stokLog->referensi_tipe === Transaksi::class
            && $stokLog->referensi_id
        ) {
            $trx = Transaksi::find($stokLog->referensi_id);
            if ($trx) {
                $sumber = strtolower((string) ($trx->sumber ?? ''));
                if ($sumber === 'marketplace'
                    || $sumber === 'pos_cloud'
                    || str_starts_with((string) $trx->no_transaksi, 'VPS-')
                ) {
                    return;
                }
            }
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
