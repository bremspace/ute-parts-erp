<?php

namespace App\Modules\Sync\Jobs;

use App\Modules\Sync\Models\HybridSyncOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BatchPushHybridSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /**
     * @param  array<int>  $outboxIds  Daftar ID outbox yang akan dikirim dalam 1 batch
     */
    public function __construct(public array $outboxIds)
    {
        $this->onQueue('sync');
    }

    public function backoff(): array
    {
        return [30, 60, 300, 900];
    }

    public function handle(): void
    {
        if (empty($this->outboxIds)) {
            return;
        }

        if (! config('sync.enabled') || config('sync.node_role') !== 'primary_store' || empty(config('sync.remote_url'))) {
            return;
        }

        $items = HybridSyncOutbox::whereIn('id', $this->outboxIds)
            ->where('status', '!=', 'synced')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        // Tandai item sedang diproses
        HybridSyncOutbox::whereIn('id', $items->pluck('id'))->update(['status' => 'processing']);

        $events = $items->map(function ($outbox) {
            return [
                'sync_uuid' => $outbox->sync_uuid,
                'event_type' => $outbox->event_type,
                'entity_type' => $outbox->entity_type,
                'entity_id' => $outbox->entity_id,
                'cabang_id' => $outbox->cabang_id,
                'payload' => $outbox->payload,
                'source_node' => config('sync.node_id'),
            ];
        })->values()->all();

        $body = ['events' => $events];
        $jsonPayload = json_encode($body);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$jsonPayload, (string) config('sync.api_secret'));

        try {
            $response = Http::timeout(25)
                ->connectTimeout(10)
                ->withHeaders([
                    'X-Sync-Node' => config('sync.node_id'),
                    'X-Sync-Signature' => $signature,
                    'X-Sync-Timestamp' => (string) $timestamp,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->withBody($jsonPayload, 'application/json')
                ->post(rtrim((string) config('sync.remote_url'), '/').'/api/internal/sync/push-batch');

            if ($response->successful()) {
                Cache::put('hybrid_sync:remote_reachable', true, now()->addMinutes(5));

                $results = $response->json('data.results', []);
                $successUuids = [];
                $failedMap = [];

                foreach ($results as $res) {
                    $uuid = $res['sync_uuid'] ?? null;
                    if ($uuid) {
                        if ($res['success'] ?? false) {
                            $successUuids[] = $uuid;
                        } else {
                            $failedMap[$uuid] = $res['message'] ?? 'Unknown error';
                        }
                    }
                }

                if (! empty($successUuids)) {
                    HybridSyncOutbox::whereIn('sync_uuid', $successUuids)->update([
                        'status' => 'synced',
                        'last_error' => null,
                        'synced_at' => now(),
                    ]);
                }

                // Pastikan tidak ada item tersangkut di 'processing': item yang gagal ATAU
                // terlewat dari array results ditandai failed dan attempts dinaikkan.
                foreach ($items as $item) {
                    if (in_array($item->sync_uuid, $successUuids, true)) {
                        continue;
                    }
                    $errMsg = $failedMap[$item->sync_uuid] ?? 'Respons batch tidak mengonfirmasi status event.';
                    $item->update([
                        'status' => 'failed',
                        'attempts' => (int) $item->attempts + 1,
                        'last_error' => $errMsg,
                    ]);
                }
            } else {
                Cache::put('hybrid_sync:remote_reachable', false, now()->addMinutes(2));
                $attempts = $this->attempts();
                foreach ($items as $item) {
                    $item->update([
                        'status' => 'failed',
                        'attempts' => max($attempts, (int) $item->attempts + 1),
                        'last_error' => 'HTTP '.$response->status().': '.$response->body(),
                    ]);
                }
                if ($this->job) {
                    $this->release($this->backoff()[$attempts - 1] ?? 900);
                }
            }
        } catch (\Throwable $e) {
            Cache::put('hybrid_sync:remote_reachable', false, now()->addMinutes(2));
            $attempts = $this->attempts();
            foreach ($items as $item) {
                $item->update([
                    'status' => 'failed',
                    'attempts' => max($attempts, (int) $item->attempts + 1),
                    'last_error' => $e->getMessage(),
                ]);
            }
            if ($this->job) {
                $this->release($this->backoff()[$attempts - 1] ?? 900);
            }
        }
    }
}
