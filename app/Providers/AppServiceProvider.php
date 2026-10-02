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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spatie\Permission\Middleware\PermissionMiddleware;
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
        // Super-admin & Owner implicitly grant all permissions (Spatie recommended practice)
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'hasRole') && ($user->hasRole('super-admin') || $user->hasRole('owner')) ? true : null;
        });

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

        // [SEC-1] Wajib persistent: middleware permission harus jalan lagi di
        // POST /livewire/update, bukan hanya saat render halaman.
        //
        // Tanpa ini, route `->middleware('permission:kelola-payroll')` hanya
        // melindungi GET. Snapshot Livewire adalah BEARER TOKEN: `memo`-nya
        // memuat {id, name, path, method} tanpa user_id/session_id, dan
        // checksum-nya HMAC dari APP_KEY saja (Checksum::generate). Jadi
        // snapshot yang sah milik sesi lain bisa di-replay user lain —
        // `flushSession()` + `actingAs($kasir)` lalu POST snapshot milik
        // `finance` berhasil membuat baris payroll_periode (dibuktikan di
        // tests/Feature/RefereeLivewireExploitTest.php).
        //
        // `RoleMiddleware` sengaja TIDAK didaftarkan: tidak ada satu pun
        // middleware `role:` di routes/web.php, jadi itu config mati.
        // Gate::before di atas tetap menjamin super-admin/owner tidak pernah
        // terkunci, jadi registrasi ini tidak mengambil hak mereka.
        Livewire::addPersistentMiddleware(PermissionMiddleware::class);

        // [F3-1] Refresh permission cache idempotent
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
