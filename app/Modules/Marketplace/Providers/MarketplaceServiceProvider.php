<?php

namespace App\Modules\Marketplace\Providers;

use App\Modules\Marketplace\Services\WebKonsumenService;
use Illuminate\Support\ServiceProvider;

class MarketplaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // View composer konfigurasi CMS Web Konsumen (Marketplace)
        view()->composer([
            'layouts.marketplace',
            'modules.marketplace.livewire.shop-catalog',
            'modules.marketplace.livewire.shop-detail',
        ], function ($view) {
            try {
                $service = app(WebKonsumenService::class);
                $view->with('webConfig', $service->getAll());
            } catch (\Throwable) {
                $view->with('webConfig', []);
            }
        });

        view()->composer('modules.marketplace.livewire.shop-catalog', function ($view) {
            try {
                $service = app(WebKonsumenService::class);
                $view->with('webBanners', $service->getActiveBanners());
            } catch (\Throwable) {
                $view->with('webBanners', collect());
            }
        });
    }
}
