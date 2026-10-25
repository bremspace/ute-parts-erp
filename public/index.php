<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Auto-redirect ke installer jika vendor/ atau .env belum ada
if (! file_exists(__DIR__.'/../vendor/autoload.php') || ! file_exists(__DIR__.'/../.env')) {
    if (file_exists(__DIR__.'/install.php')) {
        header('Location: /install.php');
        exit;
    }
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
