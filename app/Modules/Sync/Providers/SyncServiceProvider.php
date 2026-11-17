<?php

namespace App\Modules\Sync\Providers;

use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Sync\Services\HybridSyncProcessorService;
use App\Modules\Sync\SyncObserverBootstrap;
use Illuminate\Support\ServiceProvider;

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
        SyncObserverBootstrap::register();
    }
}
