<?php

namespace App\Providers;

use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Workflow\Observers\PurchaseOrderObserver;
use App\Modules\Workflow\Observers\ReturnPembelianObserver;
use App\Modules\Workflow\Observers\ReturnPenjualanObserver;
use App\Modules\Workflow\Observers\TransaksiObserver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // [F1-1] Approval Engine auto-fire — observer pada model (PO, retur, diskon besar),
        // menyala terlepas dari komponen/controller mana pun yang menyimpan model tsb.
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        ReturnPenjualan::observe(ReturnPenjualanObserver::class);
        ReturnPembelian::observe(ReturnPembelianObserver::class);
        Transaksi::observe(TransaksiObserver::class);

        // [F3-1] Blade directive @cansee('harga_beli') — calls global helper canSeeField()
        // defined in app/Helpers/functions.php (autoloaded via composer.json files)
        Blade::directive('cansee', function ($field) {
            return "<?php if(canSeeField({$field})): ?>";
        });
        Blade::directive('cannotsee', function ($field) {
            return "<?php elseif(! canSeeField({$field})): ?>";
        });
        Blade::directive('endcansee', function () {
            return '<?php endif; ?>';
        });

        // [F3-1] Refresh permission cache idempotent
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
