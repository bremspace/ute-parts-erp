<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security Headers Middleware
 *
 * Adds security headers to all responses per ADR 0006 / T-28 requirements.
 * CSP policy configured for Laravel 13 + Livewire + Alpine.js + Vite.
 * Supports CSP nonce for inline scripts/styles (Livewire 3 @nonce support).
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate CSP nonce for this request
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        // Content Security Policy
        // Livewire 3 supports @nonce directive for inline scripts/styles
        // Alpine.js still needs 'unsafe-eval' for x-data expressions
        $csp = $this->buildCsp($request, $nonce);

        $response->headers->set('Content-Security-Policy', $csp);

        // HTTP Strict Transport Security (HSTS)
        // JANGAN aktifkan HSTS untuk IP address (self-signed cert membuat browser stuck di HTTPS dicoret)
        $host = $request->getHost();
        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;

        if ($isIp) {
            // Bersihkan memori HSTS browser jika sebelumnya pernah tercatat
            $response->headers->set('Strict-Transport-Security', 'max-age=0');
        } elseif ($request->isSecure() && config('app.force_https')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy (Feature Policy) - camera=(self) wajib aktif untuk Barcode Scanner (POS, WMS, Servis)
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()');

        // Remove server header
        $response->headers->remove('Server');
        $response->headers->remove('X-Powered-By');

        return $response;
    }

    /**
     * Build CSP policy based on environment
     */
    protected function buildCsp(Request $request, string $nonce): string
    {
        $isLocal = $request->getHost() === 'localhost' || $request->getHost() === '127.0.0.1' || str_starts_with($request->getHost(), '192.168.');

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'",
            "style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com https://fonts.googleapis.com",
            "img-src 'self' data: https:",
            "connect-src 'self'".($isLocal ? ' ws://localhost:5173 http://localhost:5173' : ''),
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];

        // Add Vite dev server in local development
        if ($isLocal) {
            $directives[] = "script-src-elem 'self' 'nonce-{$nonce}' http://localhost:5173";
            $directives[] = "style-src-elem 'self' 'nonce-{$nonce}' http://localhost:5173";
        }

        return implode('; ', $directives);
    }
}
