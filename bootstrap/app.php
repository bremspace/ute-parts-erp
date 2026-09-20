<?php

use App\Modules\Crm\Console\Commands\RecalcTierCommand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands(
        commands: [RecalcTierCommand::class],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias middleware Spatie Permission (dipakai semua route RBAC — PRD §3, Route middleware enforcement)
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            
            // Security middleware (T-28)
            'security.headers' => \App\Http\Middleware\SecurityHeaders::class,
            'force.https' => \App\Http\Middleware\ForceHttps::class,
            
            // Dynamic asset URL (T-30/T-31)
            'set.asset.url' => \App\Http\Middleware\SetAssetUrl::class,
        ]);

        // Sanctum SPA cookie-session auth on /api/* (T-32) — without this all
        // authenticated /api/* calls from the SPA return 401 "Unauthenticated."
        $middleware->statefulApi();

        // api group also needs session lifecycle so the web guard can read the
        // session cookie on /api/* (statefulApi alone only swaps Sanctum's
        // domain check; without StartSession the guard sees an empty session)
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        // Set dynamic asset URL based on request host (IP vs domain)
        $middleware->web(prepend: [\App\Http\Middleware\SetAssetUrl::class]);

        // Apply security headers to all responses
        $middleware->web(append: [\App\Http\Middleware\SecurityHeaders::class]);
        $middleware->api(append: [\App\Http\Middleware\SecurityHeaders::class]);

        // Force HTTPS in production (T-28)
        $middleware->web(prepend: [\App\Http\Middleware\ForceHttps::class]);
        $middleware->api(prepend: [\App\Http\Middleware\ForceHttps::class]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Rekalkulasi tier membership harian (PRD §4.4)
        $schedule->command('tier:recalc')->dailyAt('01:00');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
