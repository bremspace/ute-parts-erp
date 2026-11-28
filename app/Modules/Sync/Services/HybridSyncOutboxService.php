<?php

namespace App\Modules\Sync\Services;

use App\Modules\Sync\Jobs\PushHybridSyncJob;
use App\Modules\Sync\Models\HybridSyncOutbox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HybridSyncOutboxService
{
    /**
     * Enqueue data to outbox and dispatch background push job only when node acts as active pusher.
     */
    public function enqueue(string $eventType, Model $entity, array $payload, ?int $cabangId = null): ?HybridSyncOutbox
    {
        if (SyncExecutionGuard::isSuppressed() || ! config('sync.enabled')) {
            return null;
        }

        $outbox = HybridSyncOutbox::create([
            'sync_uuid' => (string) Str::uuid(),
            'event_type' => $eventType,
            'entity_type' => get_class($entity),
            'entity_id' => $entity->getKey() ?? 0,
            'cabang_id' => $cabangId,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
        ]);

        // Node cloud_storefront menunggu ditarik via sync:pull (primary_store di balik NAT),
        // sehingga tidak perlu memenuhi tabel jobs dengan push job tanpa target.
        if (config('sync.node_role') === 'primary_store' && ! empty(config('sync.remote_url'))) {
            PushHybridSyncJob::dispatch($outbox->id);
        }

        return $outbox;
    }

    /**
     * Get pending or failed items for retry/pull.
     */
    public function getPendingItems(int $limit = 50): Collection
    {
        return HybridSyncOutbox::whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', 10)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();
    }
}
