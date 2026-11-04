<?php

namespace App\Providers;

use App\Modules\Crm\Services\KonfigurasiService;
use App\Services\BranchContextManager;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
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
        $this->app->singleton(BranchContextManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // [T-04] Prevent Eloquent lazy loading in local and testing environments
        Model::preventLazyLoading(! $this->app->isProduction());

        // Dynamic Brand / App Name & Host Resolver (Multi-Port & LAN zero-config)
        // Abaikan saat console command (artisan package:discover / migrate) agar tidak probe DB prematur
        if (! $this->app->runningInConsole()) {
            if (request()->hasHeader('host')) {
                config(['app.url' => request()->getSchemeAndHttpHost()]);
            }

            try {
                $brandName = app(KonfigurasiService::class)->get('app_brand_name');
                if ($brandName) {
                    config(['app.name' => $brandName]);
                }
            } catch (\Throwable) {
                // Abaikan jika DB belum dimigrasi
            }
        }

        // Super-admin & Owner implicitly grant all permissions (Spatie recommended practice)
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'hasRole') && ($user->hasRole('super-admin') || $user->hasRole('owner')) ? true : null;
        });

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

        // [F3-1] Refresh permission cache idempotent (skip jika tabel cache belum ada / saat composer dump)
        try {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Abaikan saat booting tanpa database/migrasi (mis. composer install/package:discover di CI)
        }

        // [ISO-27001 / A.8.15] Security Event Audit Logging (Authentication & Session)
        Event::listen(Login::class, function (Login $event) {
            try {
                activity('auth')
                    ->performedOn($event->user)
                    ->causedBy($event->user)
                    ->event('login')
                    ->withProperties([
                        'guard' => $event->guard,
                        'ip' => request()?->ip(),
                        'user_agent' => substr((string) request()?->userAgent(), 0, 255),
                    ])
                    ->log('Login berhasil ke sistem (Guard: '.$event->guard.')');
            } catch (\Throwable) {
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            try {
                $target = $event->credentials['email']
                    ?? $event->credentials['telepon']
                    ?? $event->credentials['username']
                    ?? 'unknown';

                activity('auth')
                    ->event('login_failed')
                    ->withProperties([
                        'target_kredensial' => $target,
                        'guard' => $event->guard,
                        'ip' => request()?->ip(),
                        'user_agent' => substr((string) request()?->userAgent(), 0, 255),
                    ])
                    ->log("Percobaan login gagal untuk kredensial: {$target}");
            } catch (\Throwable) {
            }
        });

        Event::listen(Logout::class, function (Logout $event) {
            try {
                if ($event->user) {
                    activity('auth')
                        ->performedOn($event->user)
                        ->causedBy($event->user)
                        ->event('logout')
                        ->withProperties([
                            'guard' => $event->guard,
                            'ip' => request()?->ip(),
                            'user_agent' => substr((string) request()?->userAgent(), 0, 255),
                        ])
                        ->log('Logout dari sistem');
                }
            } catch (\Throwable) {
            }
        });

        Event::listen(Lockout::class, function () {
            try {
                activity('auth')
                    ->event('lockout')
                    ->withProperties([
                        'ip' => request()?->ip(),
                        'user_agent' => substr((string) request()?->userAgent(), 0, 255),
                    ])
                    ->log('Aktivitas mencurigakan terdeteksi: Akun/IP terkunci sementara akibat melampaui limit login');
            } catch (\Throwable) {
            }
        });
    }
}
