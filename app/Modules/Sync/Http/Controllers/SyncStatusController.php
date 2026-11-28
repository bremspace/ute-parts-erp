<?php

namespace App\Modules\Sync\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sync\Models\HybridSyncOutbox;
use App\Modules\Sync\Providers\SyncServiceProvider;
use App\Modules\Sync\Services\SyncSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SyncStatusController extends Controller
{
    public const REACHABLE_CACHE_KEY = 'hybrid_sync:remote_reachable';

    /**
     * Status real-time sinkronisasi hybrid (untuk POS / Servis UI Badge).
     */
    public function status(Request $request): JsonResponse
    {
        SyncServiceProvider::refreshRuntimeConfigIfChanged();

        return response()->json([
            'success' => true,
            'data' => $this->buildStatusData(probeRemote: $request->boolean('probe')),
            'message' => 'Status sinkronisasi berhasil dimuat',
        ]);
    }

    /**
     * Trigger sinkronisasi manual (Push outbox + Pull cloud) dari toolbar POS/Servis.
     */
    public function trigger(Request $request): JsonResponse
    {
        SyncServiceProvider::refreshRuntimeConfigIfChanged();

        $enabled = (bool) config('sync.enabled', false);
        $role = (string) config('sync.node_role', 'standalone');
        $remoteUrl = rtrim((string) config('sync.remote_url', ''), '/');

        if (! $enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Sinkronisasi hybrid tidak aktif pada node ini.',
            ], 400);
        }

        if ($role === 'primary_store' && $remoteUrl === '') {
            return response()->json([
                'success' => false,
                'message' => 'URL node remote (SYNC_REMOTE_URL) belum dikonfigurasi.',
            ], 422);
        }

        try {
            if ($role === 'primary_store') {
                Artisan::call('sync:flush', ['--batch' => true, '--sync-now' => true]);
                Artisan::call('sync:pull', ['--all' => true]);
            }

            $data = $this->buildStatusData(probeRemote: true);

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Sinkronisasi berhasil dijalankan.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memicu sinkronisasi: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildStatusData(bool $probeRemote = false): array
    {
        app(SyncSchemaService::class)->ensureTables();

        $enabled = (bool) config('sync.enabled', false);
        $role = (string) config('sync.node_role', 'standalone');
        $nodeId = (string) config('sync.node_id', 'NODE-01');
        $remoteUrl = rtrim((string) config('sync.remote_url', ''), '/');
        $remoteHost = $remoteUrl !== '' ? parse_url($remoteUrl, PHP_URL_HOST) : null;

        $pending = HybridSyncOutbox::where('status', 'pending')->count();
        $processing = HybridSyncOutbox::where('status', 'processing')->count();
        $stuckProcessing = HybridSyncOutbox::where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(5))
            ->count();
        $failed = HybridSyncOutbox::where('status', 'failed')->count();
        $synced = HybridSyncOutbox::where('status', 'synced')->count();
        $lastSyncedAt = HybridSyncOutbox::where('status', 'synced')
            ->max('synced_at');

        $remoteReachable = null;
        if ($enabled && $remoteUrl !== '') {
            if ($probeRemote || ! Cache::has(self::REACHABLE_CACHE_KEY)) {
                $remoteReachable = $this->probeRemoteHealth($remoteUrl, $nodeId);
            } else {
                $remoteReachable = (bool) Cache::get(self::REACHABLE_CACHE_KEY);
            }
        }

        return [
            'enabled' => $enabled,
            'node_role' => $role,
            'node_id' => $nodeId,
            'pending_count' => $pending,
            'processing_count' => $processing,
            'stuck_processing_count' => $stuckProcessing,
            'failed_count' => $failed,
            'synced_count' => $synced,
            'last_synced_at' => $lastSyncedAt,
            'remote_configured' => $remoteUrl !== '',
            'remote_reachable' => $remoteReachable,
            'cloud_host' => $remoteHost,
        ];
    }

    private function probeRemoteHealth(string $remoteUrl, string $nodeId): bool
    {
        $secret = (string) config('sync.api_secret', '');
        $timestamp = (string) time();
        $signature = $secret !== '' ? hash_hmac('sha256', $timestamp.'.', $secret) : '';

        try {
            $response = Http::timeout(3)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Sync-Node' => $nodeId,
                    'X-Sync-Timestamp' => $timestamp,
                    'X-Sync-Signature' => $signature,
                ])
                ->get($remoteUrl.'/api/internal/sync/health');

            $reachable = $response->successful();
        } catch (\Throwable) {
            $reachable = false;
        }

        Cache::put(self::REACHABLE_CACHE_KEY, $reachable, now()->addSeconds(60));

        return $reachable;
    }
}
