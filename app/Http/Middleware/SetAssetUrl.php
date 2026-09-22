<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Dynamically set ASSET_URL based on request host.
 *
 * When accessing via IP: assets load from http://{IP}/build/assets/...
 * When accessing via domain: assets load from https://{domain}/build/assets/...
 */
class SetAssetUrl
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHttpHost(); // includes port when non-standard

        // Check if host is an IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // IP access: use HTTP with IP
            $scheme = $request->isSecure() ? 'https' : 'http';
            Config::set('app.asset_url', "{$scheme}://{$host}");
        } else {
            // Domain access: use configured APP_URL (HTTPS)
            if (config('app.url')) {
                Config::set('app.asset_url', config('app.url'));
            }
        }

        return $next($request);
    }
}
