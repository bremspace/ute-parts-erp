<?php

namespace App\Modules\Sync;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Sync\Observers\BackofficeModelSyncObserver;
use App\Modules\Sync\Observers\BrandSyncObserver;
use App\Modules\Sync\Observers\HargaTierSyncObserver;
use App\Modules\Sync\Observers\JurnalHeaderSyncObserver;
use App\Modules\Sync\Observers\KategoriProdukSyncObserver;
use App\Modules\Sync\Observers\KualitasProdukSyncObserver;
use App\Modules\Sync\Observers\OrderOnlinePaidSyncObserver;
use App\Modules\Sync\Observers\PayrollPeriodeSyncObserver;
use App\Modules\Sync\Observers\PelangganSyncObserver;
use App\Modules\Sync\Observers\ProdukBarcodeSyncObserver;
use App\Modules\Sync\Observers\ProdukSatuanKonversiSyncObserver;
use App\Modules\Sync\Observers\ProdukSyncObserver;
use App\Modules\Sync\Observers\SatuanUnitSyncObserver;
use App\Modules\Sync\Observers\SkuVariantSyncObserver;
use App\Modules\Sync\Observers\StokLogSyncObserver;
use App\Modules\Sync\Observers\TiketServisSyncObserver;
use App\Modules\Sync\Observers\TipeHpSyncObserver;
use App\Modules\Sync\Observers\TransaksiCloudPosSyncObserver;
use App\Modules\Sync\Observers\TransaksiPosSyncObserver;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\ProdukBarcode;
use App\Modules\Wms\Models\ProdukSatuanKonversi;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\Supplier;
use App\Modules\Wms\Models\TipeHp;

class SyncObserverBootstrap
{
    /**
     * Daftarkan model observer untuk auto-enqueue outbox sync.
     * Observer dipasang sekali per lifecycle app; pengecekan enabled & node_role
     * dievaluasi secara dinamis di tiap observer agar perubahan config runtime / test langsung aktif.
     */
    public static function register(): void
    {
        $app = app();
        if ($app->bound('sync.observers_registered')) {
            return;
        }
        $app->instance('sync.observers_registered', true);

        $observers = [
            Produk::class => [ProdukSyncObserver::class],
            SkuVariant::class => [SkuVariantSyncObserver::class],
            Pelanggan::class => [PelangganSyncObserver::class],
            KategoriProduk::class => [KategoriProdukSyncObserver::class],
            Brand::class => [BrandSyncObserver::class],
            KualitasProduk::class => [KualitasProdukSyncObserver::class],
            SatuanUnit::class => [SatuanUnitSyncObserver::class],
            TipeHp::class => [TipeHpSyncObserver::class],
            HargaTier::class => [HargaTierSyncObserver::class],
            ProdukSatuanKonversi::class => [ProdukSatuanKonversiSyncObserver::class],
            ProdukBarcode::class => [ProdukBarcodeSyncObserver::class],
            StokLog::class => [StokLogSyncObserver::class],
            TiketServis::class => [TiketServisSyncObserver::class],
            JurnalHeader::class => [JurnalHeaderSyncObserver::class],
            PayrollPeriode::class => [PayrollPeriodeSyncObserver::class],
            Transaksi::class => [
                TransaksiPosSyncObserver::class,
                OrderOnlinePaidSyncObserver::class,
                TransaksiCloudPosSyncObserver::class,
            ],
            // Bidirectional backoffice generik
            // Catatan: Gudang/Rak sengaja tidak dipasang observer generik karena
            // dibuat otomatis oleh migrasi/setup dan memicu event saat install awal.
            Cabang::class => [BackofficeModelSyncObserver::class],
            Supplier::class => [BackofficeModelSyncObserver::class],
            Karyawan::class => [BackofficeModelSyncObserver::class],
            AkunCOA::class => [BackofficeModelSyncObserver::class],
            JenisServis::class => [BackofficeModelSyncObserver::class],
            TierMembership::class => [BackofficeModelSyncObserver::class],
        ];

        foreach ($observers as $model => $modelObservers) {
            if (! class_exists($model)) {
                continue;
            }
            foreach ($modelObservers as $observer) {
                $model::observe($observer);
            }
        }

        foreach (BackofficeSyncRegistry::models() as $backofficeModel) {
            if (class_exists($backofficeModel)) {
                $backofficeModel::observe(BackofficeModelSyncObserver::class);
            }
        }
    }
}
