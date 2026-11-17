<?php

namespace App\Modules\Sync\Jobs;

use App\Modules\Sync\Models\HybridSyncOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class PushHybridSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public function __construct(public int $outboxId)
    {
        $this->onQueue('sync');
    }

    public function backoff(): array
    {
        return [30, 60, 300, 900];
    }

    public function handle(): void
    {
        try {
            $outbox = HybridSyncOutbox::find($this->outboxId);
            if (! $outbox || $outbox->status === 'synced') {
                return;
            }

            if (! config('sync.enabled') || empty(config('sync.remote_url'))) {
                return;
            }

            $outbox->update(['status' => 'processing']);

            $body = [
                'sync_uuid' => $outbox->sync_uuid,
                'event_type' => $outbox->event_type,
                'entity_type' => $outbox->entity_type,
                'entity_id' => $outbox->entity_id,
                'cabang_id' => $outbox->cabang_id,
                'payload' => $outbox->payload,
                'source_node' => config('sync.node_id'),
            ];

            $jsonPayload = json_encode($body);
            $timestamp = time();
            $signature = hash_hmac('sha256', $timestamp.'.'.$jsonPayload, config('sync.api_secret'));

            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withHeaders([
                    'X-Sync-Node' => config('sync.node_id'),
                    'X-Sync-Signature' => $signature,
                    'X-Sync-Timestamp' => (string) $timestamp,
                    'Content-Type' => 'application/json',
                ])
                ->withBody($jsonPayload, 'application/json')
                ->post(rtrim(config('sync.remote_url'), '/').'/api/internal/sync/push');

            if ($response->status() === 200 && ($response->json('success') === true)) {
                $outbox->update([
                    'status' => 'synced',
                    'synced_at' => now(),
                ]);
            } else {
                $attempts = $this->attempts();
                $outbox->update([
                    'status' => 'failed',
                    'attempts' => $attempts,
                    'last_error' => 'HTTP '.$response->status().': '.$response->body(),
                ]);
                $this->release($this->backoff()[$attempts - 1] ?? 900);
            }
        } catch (\Throwable $e) {
            $attempts = $this->attempts();

            if (isset($outbox)) {
                $outbox->update([
                    'status' => 'failed',
                    'attempts' => $attempts,
                    'last_error' => $e->getMessage(),
                ]);
            }

            $this->release($this->backoff()[$attempts - 1] ?? 900);
        }
    }
}
