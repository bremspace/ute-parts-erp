<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthCheckController extends Controller
{
    /**
     * Handle the incoming health check probe request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // 1. Security Authorization Validation
        $configuredSecret = config('app.health_check_secret') ?: env('HEALTH_CHECK_SECRET');

        if (empty($configuredSecret)) {
            return response()->json([
                'status' => 'unauthorized',
                'message' => 'HEALTH_CHECK_SECRET is not configured on the server.',
            ], 401);
        }

        $providedSecret = $request->header('X-Health-Check-Secret') ?? $request->query('token');

        if (empty($providedSecret) || !hash_equals((string) $configuredSecret, (string) $providedSecret)) {
            return response()->json([
                'status' => 'unauthorized',
                'message' => 'Invalid or missing health check authorization credentials.',
            ], 401);
        }

        $checks = [];
        $errors = [];

        // 2. Database Probe (Connection + Latency)
        $dbStart = microtime(true);
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1;');
            $dbLatencyMs = round((microtime(true) - $dbStart) * 1000, 2);

            $checks['database'] = [
                'status' => 'ok',
                'latency_ms' => $dbLatencyMs,
                'connection' => DB::getDefaultConnection(),
            ];
        } catch (Throwable $e) {
            $errors[] = 'Database probe failed: ' . $e->getMessage();
            $checks['database'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }

        // 3. Cache Driver Probe (Read/Write/Delete + Latency)
        $cacheStart = microtime(true);
        try {
            $probeKey = 'health_probe_' . bin2hex(random_bytes(6));
            $probeVal = 'probe_val_' . time();

            Cache::put($probeKey, $probeVal, 15);
            $retrieved = Cache::get($probeKey);
            Cache::forget($probeKey);

            if ($retrieved !== $probeVal) {
                throw new \RuntimeException('Cache write/read integrity mismatch.');
            }

            $cacheLatencyMs = round((microtime(true) - $cacheStart) * 1000, 2);
            $checks['cache'] = [
                'status' => 'ok',
                'latency_ms' => $cacheLatencyMs,
                'store' => config('cache.default'),
            ];
        } catch (Throwable $e) {
            $errors[] = 'Cache probe failed: ' . $e->getMessage();
            $checks['cache'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }

        // 4. Storage & Permissions Probe (Essential Directories Writable)
        $storagePaths = [
            'storage' => storage_path(),
            'storage_framework' => storage_path('framework'),
            'storage_logs' => storage_path('logs'),
            'bootstrap_cache' => base_path('bootstrap/cache'),
        ];

        $storageDetails = [];
        $storageOk = true;

        foreach ($storagePaths as $key => $path) {
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);

            $storageDetails[$key] = [
                'path' => $path,
                'exists' => $exists,
                'writable' => $writable,
            ];

            if (!$exists || !$writable) {
                $storageOk = false;
                $errors[] = "Directory [{$path}] is " . (!$exists ? 'missing' : 'not writable') . '.';
            }
        }

        $checks['storage'] = [
            'status' => $storageOk ? 'ok' : 'failed',
            'directories' => $storageDetails,
        ];

        // 5. Final Evaluation Response
        $isHealthy = empty($errors);
        $payload = [
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'checks' => $checks,
        ];

        if (!$isHealthy) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $isHealthy ? 200 : 503);
    }
}
