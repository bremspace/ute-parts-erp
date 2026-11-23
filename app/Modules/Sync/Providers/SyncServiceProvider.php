<?php

namespace App\Modules\Sync\Providers;

use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Sync\Services\HybridSyncProcessorService;
use App\Modules\Sync\SyncObserverBootstrap;
use Illuminate\Support\ServiceProvider;
use Throwable;

class SyncServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../../config/sync.php', 'sync');

        $this->app->singleton(HybridSyncOutboxService::class, function () {
            return new HybridSyncOutboxService;
        });

        $this->app->singleton(HybridSyncProcessorService::class, function () {
            return new HybridSyncProcessorService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->applyDatabaseConfig();

        SyncObserverBootstrap::register();
    }

    /**
     * Pengaturan Hybrid Sync dari interface (tabel konfigurasi) harus menjadi
     * sumber runtime. Tanpa ini, UI hanya menyimpan `hybrid_sync_config` tetapi
     * observer/processor tetap membaca `config('sync.*')` dari .env.
     */
    protected function applyDatabaseConfig(): void
    {
        try {
            $saved = app(KonfigurasiService::class)->get('hybrid_sync_config');
            if (! is_array($saved) || $saved === []) {
                return;
            }

            $map = [
                'enabled' => 'sync.enabled',
                'node_role' => 'sync.node_role',
                'node_id' => 'sync.node_id',
                'remote_url' => 'sync.remote_url',
                'api_secret' => 'sync.api_secret',
                'pull_interval_seconds' => 'sync.pull_interval_seconds',
            ];

            foreach ($map as $key => $target) {
                if (! array_key_exists($key, $saved)) {
                    continue;
                }

                $value = $saved[$key];

                if ($key === 'enabled') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } elseif ($key === 'pull_interval_seconds') {
                    $value = max(5, (int) $value);
                } elseif ($key === 'node_role') {
                    // Hanya dua peran yang dikenal; nilai lain (mis. hasil sunting
                    // manual tabel konfigurasi) jangan dipakai.
                    if (! in_array((string) $value, ['primary_store', 'cloud_storefront'], true)) {
                        continue;
                    }
                    $value = (string) $value;
                } elseif (is_scalar($value)) {
                    $value = trim((string) $value);

                    // String kosong berarti "tidak disetel di UI". Jangan timpa
                    // nilai .env yang masih berlaku — kalau ditimpa dengan '',
                    // VerifySyncSignature menolak semua request dengan 401 dan
                    // push sync gagal diam-diam tanpa pesan yang jelas.
                    if (($key === 'api_secret' || $key === 'remote_url') && $value === '') {
                        continue;
                    }
                } else {
                    // Array/objek tidak punya padanan di config('sync.*').
                    continue;
                }

                config()->set($target, $value);
            }
        } catch (Throwable $e) {
            // Saat install/migrasi awal tabel konfigurasi mungkin belum ada.
            // Fallback ke konfigurasi .env.
        }
    }
}
