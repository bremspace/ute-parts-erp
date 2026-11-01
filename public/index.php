<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Auto-redirect ke installer jika vendor/ atau .env belum ada atau belum dikonfigurasi
$envFile = __DIR__.'/../.env';
$needsInstall = ! file_exists(__DIR__.'/../vendor/autoload.php') || ! file_exists($envFile);
if (! $needsInstall && file_exists($envFile)) {
    $envContent = (string) @file_get_contents($envFile);
    if (! preg_match('/^APP_KEY=base64:.+/m', $envContent) || ! preg_match('/^DB_DATABASE=.+/m', $envContent)) {
        $needsInstall = true;
    }
}
if ($needsInstall && file_exists(__DIR__.'/install.php')) {
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
