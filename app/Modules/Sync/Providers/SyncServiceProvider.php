<?php

namespace App\Modules\Sync\Providers;

use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Sync\Services\HybridSyncProcessorService;
use App\Modules\Sync\SyncObserverBootstrap;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

class SyncServiceProvider extends ServiceProvider
{
    public const CONFIG_VERSION_CACHE_KEY = 'hybrid_sync:config_version';

    private static ?string $appliedConfigVersion = null;

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
        self::applyDatabaseConfig();
        try {
            $version = Cache::get(self::CONFIG_VERSION_CACHE_KEY);
            self::$appliedConfigVersion = is_string($version) ? $version : null;
        } catch (Throwable) {
            self::$appliedConfigVersion = null;
        }

        SyncObserverBootstrap::register();
    }

    /**
     * Tandai perubahan konfigurasi runtime Hybrid Sync agar queue:work / worker lain
     * otomatis memuat ulang konfigurasi terbaru tanpa restart proses.
     */
    public static function markRuntimeConfigUpdated(): void
    {
        $version = (string) Str::uuid();
        self::$appliedConfigVersion = null;

        try {
            Cache::forever(self::CONFIG_VERSION_CACHE_KEY, $version);
        } catch (Throwable) {
            // Cache backend mungkin belum siap saat CLI setup awal.
        }
    }

    /**
     * Muat ulang config('sync.*') dari DB hanya jika versi konfigurasi di cache berubah
     * sejak terakhir diterapkan pada worker proses ini.
     */
    public static function refreshRuntimeConfigIfChanged(): void
    {
        try {
            $version = Cache::get(self::CONFIG_VERSION_CACHE_KEY);
        } catch (Throwable) {
            return;
        }

        if (! is_string($version) || $version === '' || $version === self::$appliedConfigVersion) {
            return;
        }

        try {
            app(KonfigurasiService::class)->flushMemoryCache();
            Cache::forget('crm-konfigurasi:hybrid_sync_config');
        } catch (Throwable) {
            // Abaikan bila cache belum siap.
        }

        self::applyDatabaseConfig();
        self::$appliedConfigVersion = $version;
    }

    /**
     * Pengaturan Hybrid Sync dari interface (tabel konfigurasi) harus menjadi
     * sumber runtime. Tanpa ini, UI hanya menyimpan `hybrid_sync_config` tetapi
     * observer/processor tetap membaca `config('sync.*')` dari .env.
     */
    protected static function applyDatabaseConfig(): void
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

                    if ($key === 'api_secret') {
                        try {
                            $value = Crypt::decryptString($value);
                        } catch (DecryptException) {
                            // Legacy plaintext tetap didukung sampai disimpan ulang via UI.
                        }
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
