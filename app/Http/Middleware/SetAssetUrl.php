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
        $scheme = $request->isSecure() ? 'https' : 'http';
        $host = $request->getHttpHost();

        // Always align asset_url with current request scheme & host
        Config::set('app.asset_url', "{$scheme}://{$host}");

        return $next($request);
    }
}
