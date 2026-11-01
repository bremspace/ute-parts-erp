<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Auto-redirect ke installer HANYA jika instalasi belum pernah dijalankan
$envFile = __DIR__.'/../.env';
$lockFile = __DIR__.'/../storage/installed.lock';

$isInstalled = file_exists($lockFile);
if (! $isInstalled) {
    $hasVendor = file_exists(__DIR__.'/../vendor/autoload.php');
    $hasEnv = file_exists($envFile);
    if ($hasVendor && $hasEnv) {
        $envContent = (string) @file_get_contents($envFile);
        $hasKey = preg_match('/^APP_KEY=.+/m', $envContent) && ! preg_match('/^APP_KEY=\s*$/m', $envContent);
        $hasDb = preg_match('/^DB_DATABASE=.+/m', $envContent) || preg_match('/^DB_CONNECTION=sqlite/m', $envContent);
        if ($hasKey && $hasDb) {
            $isInstalled = true;
        }
    }
}

if (! $isInstalled && file_exists(__DIR__.'/install.php')) {
    header('Location: /install.php');
    exit;
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
