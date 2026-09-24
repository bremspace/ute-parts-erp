<?php

use App\Console\Commands\SidImportRaw;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetAssetUrl;
use App\Modules\Crm\Console\Commands\EksekusiBroadcastTerjadwal;
use App\Modules\Crm\Console\Commands\RecalcTierCommand;
use App\Modules\Hr\Jobs\HitungKpiBulananJob;
use App\Modules\Wms\Jobs\CycleCountJob;
use App\Modules\Wms\Jobs\ReorderOtomatisJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // F1-7: GET /healthz (db + queue check) — routes/web.php hot file,
        // diregistrasi via mekanisme additional-routing `then` milik ApplicationBuilder.
        then: function (): void {
            require __DIR__.'/../routes/healthz.php';
        },
    )
    ->withCommands(
        commands: [RecalcTierCommand::class, EksekusiBroadcastTerjadwal::class, SidImportRaw::class],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias middleware Spatie Permission (dipakai semua route RBAC — PRD §3, Route middleware enforcement)
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,

            // Security middleware (T-28)
            'security.headers' => SecurityHeaders::class,
            'force.https' => ForceHttps::class,

            // Dynamic asset URL (T-30/T-31)
            'set.asset.url' => SetAssetUrl::class,
        ]);

        // Sanctum SPA cookie-session auth on /api/* (T-32) — without this all
        // authenticated /api/* calls from the SPA return 401 "Unauthenticated."
        $middleware->statefulApi();

        // api group also needs session lifecycle so the web guard can read the
        // session cookie on /api/* (statefulApi alone only swaps Sanctum's
        // domain check; without StartSession the guard sees an empty session)
        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
        ]);

        // Set dynamic asset URL based on request host (IP vs domain)
        $middleware->web(prepend: [SetAssetUrl::class]);

        // Apply security headers to all responses
        $middleware->web(append: [SecurityHeaders::class]);
        $middleware->api(append: [SecurityHeaders::class]);

        // Force HTTPS in production (T-28)
        $middleware->web(prepend: [ForceHttps::class]);
        $middleware->api(prepend: [ForceHttps::class]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Rekalkulasi tier membership harian (PRD §4.4)
        $schedule->command('tier:recalc')->dailyAt('01:00');

        // [T-23] Eksekusi broadcast terjadwal tiap menit
        $schedule->command('crm:broadcast-terjadwal')->everyMinute();

        // [F1-6] Backup database harian 02:00 (mysqldump gzip, rotasi 7 hari)
        $schedule->command('ute:backup')->dailyAt('02:00');

        // [F1-5] Reorder otomatis harian 03:00 (queue: stok < minimum → usulan PO)
        $schedule->job(ReorderOtomatisJob::class)->dailyAt('03:00');

        // [F3-7] Cycle count — cek jadwal jatuh tempo (hari/jam per schedule) → generate
        // sample task acak (queue database; hourly agar hormati field `jam` tiap jadwal)
        $schedule->job(CycleCountJob::class)->hourlyAt('17');

        // [F3-8b] KPI karyawan bulanan — hitung ulang tiap awal bulan 00:30
        $schedule->job(HitungKpiBulananJob::class)->monthlyOn(1, '00:30');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
