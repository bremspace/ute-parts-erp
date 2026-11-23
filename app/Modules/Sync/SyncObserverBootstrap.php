<?php

namespace App\Modules\Sync;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Sync\Observers\BrandSyncObserver;
use App\Modules\Sync\Observers\KategoriProdukSyncObserver;
use App\Modules\Sync\Observers\KualitasProdukSyncObserver;
use App\Modules\Sync\Observers\OrderOnlinePaidSyncObserver;
use App\Modules\Sync\Observers\PelangganSyncObserver;
use App\Modules\Sync\Observers\ProdukSyncObserver;
use App\Modules\Sync\Observers\SatuanUnitSyncObserver;
use App\Modules\Sync\Observers\SkuVariantSyncObserver;
use App\Modules\Sync\Observers\StokLogSyncObserver;
use App\Modules\Sync\Observers\TiketServisSyncObserver;
use App\Modules\Sync\Observers\TipeHpSyncObserver;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\TipeHp;

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

        // Master data observers: aktif di kedua node (lokal & cloud) agar
        // perubahan di salah satu sisi bisa disinkronkan ke sisi lain.
        $masterObservers = [
            Produk::class => ProdukSyncObserver::class,
            SkuVariant::class => SkuVariantSyncObserver::class,
            Pelanggan::class => PelangganSyncObserver::class,
            KategoriProduk::class => KategoriProdukSyncObserver::class,
            Brand::class => BrandSyncObserver::class,
            KualitasProduk::class => KualitasProdukSyncObserver::class,
            SatuanUnit::class => SatuanUnitSyncObserver::class,
            TipeHp::class => TipeHpSyncObserver::class,
        ];

        foreach ($masterObservers as $model => $observer) {
            if (class_exists($model)) {
                $model::observe($observer);
            }
        }

        // Observers khusus node lokal
        if ($nodeRole === 'primary_store') {
            if (class_exists(StokLog::class)) {
                StokLog::observe(StokLogSyncObserver::class);
            }

            if (class_exists(TiketServis::class)) {
                TiketServis::observe(TiketServisSyncObserver::class);
            }
        }

        // Observers khusus node cloud
        if ($nodeRole === 'cloud_storefront') {
            if (class_exists(Transaksi::class)) {
                Transaksi::observe(OrderOnlinePaidSyncObserver::class);
            }
        }
    }
}
