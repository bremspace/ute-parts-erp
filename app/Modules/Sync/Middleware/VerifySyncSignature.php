<?php

namespace App\Modules\Sync\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySyncSignature
{
    /**
     * Toleransi drift timestamp (dalam detik) untuk mencegah replay attack.
     */
    public const MAX_TIMESTAMP_DRIFT_SECONDS = 300; // 5 menit

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Health endpoint tetap mengizinkan pengecekan status meski sync.enabled = false
        // agar panel pengaturan admin bisa melihat status remote node.
        if (! config('sync.enabled') && ! $request->is('api/internal/sync/health')) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Sinkronisasi hybrid sedang dinonaktifkan pada node ini.',
            ], 503);
        }

        $node = $request->header('X-Sync-Node');
        $signature = $request->header('X-Sync-Signature');
        $timestamp = $request->header('X-Sync-Timestamp');
        $apiSecret = (string) config('sync.api_secret', '');

        if ($apiSecret === '') {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Konfigurasi sync secret tidak ditemukan.',
            ], 401);
        }

        if (empty($node) || empty($signature) || empty($timestamp) || ! ctype_digit((string) $timestamp)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Header autentikasi sync tidak lengkap.',
            ], 401);
        }

        $requestTime = (int) $timestamp;
        if (abs(time() - $requestTime) > self::MAX_TIMESTAMP_DRIFT_SECONDS) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Timestamp request sync kadaluarsa atau tidak valid.',
            ], 401);
        }

        // Untuk GET sertakan query string agar parameter filter (cabang_id/limit) tidak bisa dimodifikasi in-flight.
        $payloadToVerify = $request->isMethod('GET')
            ? (string) ($request->getQueryString() ?? '')
            : (string) $request->getContent();

        $expectedSignature = hash_hmac('sha256', $timestamp.'.'.$payloadToVerify, $apiSecret);

        if (! hash_equals($expectedSignature, (string) $signature)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Tanda tangan tidak valid.',
            ], 401);
        }

        return $next($request);
    }
}
