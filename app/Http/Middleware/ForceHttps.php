<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force HTTPS Middleware
 *
 * Redirects all HTTP requests to HTTPS in production.
 * Can be disabled via FORCE_HTTPS=false env var.
 */
class ForceHttps
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if not in production or FORCE_HTTPS is disabled
        if (! config('app.force_https', true) || ! $this->shouldForceHttps($request)) {
            return $next($request);
        }

        // If already HTTPS, continue
        if ($request->isSecure()) {
            return $next($request);
        }

        // Redirect to HTTPS with same path and query
        $httpsUrl = str_replace('http://', 'https://', $request->fullUrl());

        return redirect($httpsUrl, 301);
    }

    /**
     * Determine if HTTPS should be forced for this request
     */
    protected function shouldForceHttps(Request $request): bool
    {
        // Don't force on health check endpoints
        if ($request->is('up') || $request->is('health')) {
            return false;
        }

        // Don't force on local development
        $host = $request->getHost();
        if ($host === 'localhost' || $host === '127.0.0.1' || str_starts_with($host, '192.168.')) {
            return false;
        }

        // Don't force on IP addresses (no SSL cert for IPs)
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return true;
    }
}
