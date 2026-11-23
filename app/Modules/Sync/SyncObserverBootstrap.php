<?php

namespace App\Modules\Sync;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Sync\Observers\OrderOnlinePaidSyncObserver;
use App\Modules\Sync\Observers\PelangganSyncObserver;
use App\Modules\Sync\Observers\ProdukSyncObserver;
use App\Modules\Sync\Observers\StokLogSyncObserver;
use App\Modules\Sync\Observers\TiketServisSyncObserver;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\StokLog;

class SyncObserverBootstrap
{
    /**
     * Daftarkan model observer untuk auto-enqueue outbox sync.
     */
    public static function register(): void
    {
        if (! config('sync.enabled')) {
            return;
        }

        $nodeRole = config('sync.node_role');

        if ($nodeRole === 'primary_store') {
            if (class_exists(Produk::class)) {
                Produk::observe(ProdukSyncObserver::class);
            }

            if (class_exists(Pelanggan::class)) {
                Pelanggan::observe(PelangganSyncObserver::class);
            }

            if (class_exists(StokLog::class)) {
                StokLog::observe(StokLogSyncObserver::class);
            }

            if (class_exists(TiketServis::class)) {
                TiketServis::observe(TiketServisSyncObserver::class);
            }
        } elseif ($nodeRole === 'cloud_storefront') {
            if (class_exists(Transaksi::class)) {
                Transaksi::observe(OrderOnlinePaidSyncObserver::class);
            }
        }
    }
}
