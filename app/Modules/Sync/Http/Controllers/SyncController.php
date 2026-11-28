<?php

namespace App\Modules\Sync\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sync\Models\HybridSyncOutbox;
use App\Modules\Sync\Services\HybridSyncProcessorService;
use App\Modules\Sync\Services\SyncBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    /**
     * POST /api/internal/sync/push
     * Terima payload sinkronisasi dari node lawan (terproteksi signature).
     */
    public function push(Request $request, HybridSyncProcessorService $processor): JsonResponse
    {
        $payload = $request->all();

        if (empty($payload['sync_uuid']) || empty($payload['event_type'])) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Parameter sync_uuid dan event_type wajib disertakan.',
            ], 422);
        }

        $result = $processor->process($payload);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * POST /api/internal/sync/push-batch
     * Terima array payload sinkronisasi massal (hingga 50 event) dalam 1 HTTP request.
     */
    public function pushBatch(Request $request, HybridSyncProcessorService $processor): JsonResponse
    {
        $events = $request->input('events', $request->all());

        if (isset($events['events']) && is_array($events['events'])) {
            $events = $events['events'];
        }

        if (! is_array($events) || empty($events)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Parameter events wajib berupa array dan tidak boleh kosong.',
            ], 422);
        }

        $processed = 0;
        $failed = 0;
        $results = [];

        foreach ($events as $event) {
            if (! is_array($event) || empty($event['sync_uuid']) || empty($event['event_type'])) {
                $failed++;
                $results[] = [
                    'sync_uuid' => is_array($event) ? ($event['sync_uuid'] ?? null) : null,
                    'success' => false,
                    'message' => 'sync_uuid dan event_type wajib disertakan.',
                ];

                continue;
            }

            $res = $processor->process($event);
            if ($res['success']) {
                $processed++;
            } else {
                $failed++;
            }

            $results[] = [
                'sync_uuid' => $event['sync_uuid'],
                'success' => $res['success'],
                'message' => $res['message'],
            ];
        }

        return response()->json([
            'success' => $failed === 0,
            'data' => [
                'total' => count($events),
                'processed' => $processed,
                'failed' => $failed,
                'results' => $results,
            ],
            'message' => "Batch sync selesai: {$processed} diproses, {$failed} gagal.",
        ], ($failed > 0 && $processed === 0) ? 500 : 200);
    }

    /**
     * GET /api/internal/sync/pull
     * Fallback endpoint untuk node lokal menarik event yang belum tersinkronisasi.
     */
    public function pull(Request $request): JsonResponse
    {
        $limit = max(1, min(200, (int) $request->query('limit', 50)));
        $cabangId = $request->query('cabang_id');
        $nodeRole = config('sync.node_role');

        $query = HybridSyncOutbox::where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit($limit);

        if ($cabangId) {
            $query->where(function ($q) use ($cabangId) {
                $q->where('cabang_id', $cabangId)->orWhereNull('cabang_id');
            });
        }

        // Node cloud_storefront mengekspos seluruh antrean backoffice & order online
        // untuk ditarik oleh primary_store (kecuali event transaksi.pos milik toko).
        if ($nodeRole === 'cloud_storefront') {
            $query->where('event_type', '!=', 'transaksi.pos');
        }

        $items = $query->get()->map(function ($item) {
            return [
                'sync_uuid' => $item->sync_uuid,
                'event_type' => $item->event_type,
                'entity_type' => $item->entity_type,
                'entity_id' => $item->entity_id,
                'cabang_id' => $item->cabang_id,
                'payload' => $item->payload,
                'source_node' => config('sync.node_id'),
                'created_at' => $item->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'message' => 'Data antrean sync berhasil diambil.',
        ]);
    }

    /**
     * POST /api/internal/sync/ack
     * Konfirmasi penerimaan item oleh node lawan agar outbox di-mark synced.
     */
    public function ack(Request $request): JsonResponse
    {
        $uuids = $request->input('sync_uuids', []);
        if (! empty($uuids) && is_array($uuids)) {
            HybridSyncOutbox::whereIn('sync_uuid', $uuids)
                ->where('status', 'pending')
                ->update([
                    'status' => 'synced',
                    'synced_at' => now(),
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => count($uuids).' item outbox berhasil di-ACK.',
        ]);
    }

    /**
     * GET /api/internal/sync/bootstrap
     * Ekspor data canonical cloud untuk bootstrap client lokal.
     */
    public function bootstrap(Request $request, SyncBootstrapService $bootstrap): JsonResponse
    {
        if (config('sync.node_role') !== 'cloud_storefront') {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Bootstrap hanya bisa diambil dari node cloud_storefront.',
            ], 403);
        }

        $table = (string) $request->query('table', '');
        $limit = max(1, min(500, (int) $request->query('limit', 200)));
        $offset = max(0, (int) $request->query('offset', 0));

        if ($table === '') {
            return response()->json([
                'success' => true,
                'data' => ['tables' => $bootstrap->tables()],
                'message' => 'Daftar tabel bootstrap berhasil dimuat.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'table' => $table,
                'offset' => $offset,
                'limit' => $limit,
                'events' => $bootstrap->export($table, $limit, $offset),
            ],
            'message' => 'Data bootstrap berhasil diambil.',
        ]);
    }

    /**
     * GET /api/internal/sync/health
     * Endpoint status kesehatan dan statistik sinkronisasi node.
     */
    public function health(): JsonResponse
    {
        $pendingCount = HybridSyncOutbox::where('status', 'pending')->count();
        $lastSynced = HybridSyncOutbox::whereNotNull('synced_at')->latest('synced_at')->value('synced_at');

        return response()->json([
            'success' => true,
            'data' => [
                'node_id' => config('sync.node_id'),
                'node_role' => config('sync.node_role'),
                'sync_enabled' => (bool) config('sync.enabled'),
                'pending_count' => $pendingCount,
                'last_synced_at' => $lastSynced,
                'server_time' => now()->toIso8601String(),
            ],
            'message' => 'Node sync aktif dan sehat.',
        ]);
    }
}
