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
        $node = $request->header('X-Sync-Node');
        $signature = $request->header('X-Sync-Signature');
        $timestamp = $request->header('X-Sync-Timestamp');
        $apiSecret = config('sync.api_secret');

        if (empty($apiSecret)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Konfigurasi sync secret tidak ditemukan.',
            ], 401);
        }

        if (empty($node) || empty($signature)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Header autentikasi sync tidak lengkap.',
            ], 401);
        }

        // Jika timestamp disediakan, validasi replay attack window
        if (! empty($timestamp)) {
            $requestTime = (int) $timestamp;
            $currentTime = time();
            if (abs($currentTime - $requestTime) > self::MAX_TIMESTAMP_DRIFT_SECONDS) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Timestamp request sync kadaluarsa atau tidak valid.',
                ], 401);
            }
        }

        $rawBody = $request->getContent();
        $payloadToVerify = $request->isMethod('GET') ? '' : $rawBody;

        // Mendukung verifikasi signature dengan timestamp atau signature legacy
        $expectedSignatureWithTs = ! empty($timestamp)
            ? hash_hmac('sha256', $timestamp.'.'.$payloadToVerify, $apiSecret)
            : null;
        $expectedSignatureDirect = hash_hmac('sha256', $payloadToVerify, $apiSecret);

        $isValid = hash_equals($expectedSignatureDirect, $signature)
            || ($expectedSignatureWithTs && hash_equals($expectedSignatureWithTs, $signature));

        if (! $isValid) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Tanda tangan tidak valid.',
            ], 401);
        }

        return $next($request);
    }
}
