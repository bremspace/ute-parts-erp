<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * Emulates Apache mod_rewrite / Nginx try_files for PHP CLI built-in web server.
 * Allows routes like /app/login, /app/pos, and Livewire endpoints to work seamlessly.
 */
$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? ''
);

// Serve static assets directly if they exist in public/
if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
