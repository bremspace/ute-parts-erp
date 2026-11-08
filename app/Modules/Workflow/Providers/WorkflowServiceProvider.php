<?php

namespace App\Modules\Workflow\Providers;

use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Workflow\Observers\PurchaseOrderObserver;
use App\Modules\Workflow\Observers\ReturnPembelianObserver;
use App\Modules\Workflow\Observers\ReturnPenjualanObserver;
use App\Modules\Workflow\Observers\TransaksiObserver;
use Illuminate\Support\ServiceProvider;

class WorkflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Load Workflow API routes
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        // Auto-fire approval observers
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        ReturnPenjualan::observe(ReturnPenjualanObserver::class);
        ReturnPembelian::observe(ReturnPembelianObserver::class);
        Transaksi::observe(TransaksiObserver::class);
    }
}
