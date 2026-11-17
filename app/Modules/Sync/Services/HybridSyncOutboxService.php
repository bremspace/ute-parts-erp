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
     * Enqueue data to outbox and dispatch background job.
     */
    public function enqueue(string $eventType, Model $entity, array $payload, ?int $cabangId = null): ?HybridSyncOutbox
    {
        if (! config('sync.enabled')) {
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

        PushHybridSyncJob::dispatch($outbox->id);

        return $outbox;
    }

    /**
     * Get pending or failed items for retry/pull.
     */
    public function getPendingItems(int $limit = 50): Collection
    {
        return HybridSyncOutbox::whereIn('status', ['pending', 'failed'])
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }
}
