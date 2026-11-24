<?php

namespace App\Modules\Sync\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sync\Models\HybridSyncOutbox;
use App\Modules\Sync\Services\HybridSyncProcessorService;
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
            if ($res['success'] ?? false) {
                $processed++;
            } else {
                $failed++;
            }

            $results[] = [
                'sync_uuid' => $event['sync_uuid'],
                'success' => $res['success'] ?? false,
                'message' => $res['message'] ?? '',
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
        $limit = (int) $request->query('limit', 50);
        $cabangId = $request->query('cabang_id');
        $nodeRole = config('sync.node_role');

        $query = HybridSyncOutbox::where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->limit($limit);

        if ($cabangId) {
            $query->where(function ($q) use ($cabangId) {
                $q->where('cabang_id', $cabangId)->orWhereNull('cabang_id');
            });
        }

        // Jika node adalah cloud_storefront, yang ditarik oleh toko adalah pesanan online, master data, dan transaksi cloud pos
        if ($nodeRole === 'cloud_storefront') {
            $allowedEvents = [
                'order.online_paid',
                'produk.upsert',
                'sku_variant.upsert',
                'pelanggan.upsert',
                'kategori_produk.upsert',
                'brand.upsert',
                'kualitas_produk.upsert',
                'satuan_unit.upsert',
                'tipe_hp.upsert',
                'transaksi.pos_cloud',
            ];
            $query->whereIn('event_type', $allowedEvents);
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
