<?php

namespace App\Modules\Sync\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncStatusController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $enabled = (bool) config('sync.enabled', false);
        $role = (string) config('sync.node_role', 'primary_store');
        $cloudUrl = (string) config('sync.cloud_url', '');

        $pending = 0;
        $failed = 0;
        $synced = 0;
        $lastSyncedAt = null;

        if (Schema::hasTable('hybrid_sync_outbox')) {
            $pending = (int) DB::table('hybrid_sync_outbox')->where('status', 'pending')->count();
            $failed = (int) DB::table('hybrid_sync_outbox')->where('status', 'failed')->count();
            $synced = (int) DB::table('hybrid_sync_outbox')->where('status', 'synced')->count();

            $lastSyncedRow = DB::table('hybrid_sync_outbox')
                ->where('status', 'synced')
                ->whereNotNull('synced_at')
                ->latest('synced_at')
                ->first();
            $lastSyncedAt = $lastSyncedRow?->synced_at;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $enabled,
                'node_role' => $role,
                'pending_count' => $pending,
                'failed_count' => $failed,
                'synced_count' => $synced,
                'last_synced_at' => $lastSyncedAt,
                'cloud_host' => $cloudUrl ? parse_url($cloudUrl, PHP_URL_HOST) : null,
            ],
            'message' => 'Status sinkronisasi berhasil dimuat',
        ]);
    }

    public function trigger(Request $request): JsonResponse
    {
        $enabled = (bool) config('sync.enabled', false);
        if (! $enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Sinkronisasi hybrid tidak aktif pada node ini.',
            ], 400);
        }

        try {
            // Push pending outbox to cloud
            Artisan::call('sync:flush', ['--batch' => true]);

            // Pull latest updates from cloud
            Artisan::call('sync:pull', ['--all' => true]);

            $pending = 0;
            $failed = 0;
            if (Schema::hasTable('hybrid_sync_outbox')) {
                $pending = (int) DB::table('hybrid_sync_outbox')->where('status', 'pending')->count();
                $failed = (int) DB::table('hybrid_sync_outbox')->where('status', 'failed')->count();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'pending_count' => $pending,
                    'failed_count' => $failed,
                ],
                'message' => 'Sinkronisasi berhasil dijalankan.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memicu sinkronisasi: '.$e->getMessage(),
            ], 500);
        }
    }
}
