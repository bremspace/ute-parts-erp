<?php

namespace App\Modules\Omnichannel\Jobs;

use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Omnichannel\Models\ChannelOrder;
use App\Modules\Omnichannel\Models\ChannelWebhookInbound;
use App\Modules\Omnichannel\Services\ChannelSyncService;
use App\Modules\Rbac\Models\Cabang;
use App\Services\BranchContextManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * [GAP-INT-01 / GAP-ERP-01] Queue Job asynchronous untuk pemrosesan order masuk
 * dari webhook marketplace / omnichannel dengan branch context terisolasi.
 */
class ProcessChannelOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public function __construct(
        public int $channelId,
        public array $payload,
        public ?int $cabangId = null,
        public ?int $inboundDeliveryId = null
    ) {}

    public function handle(ChannelSyncService $syncService, BranchContextManager $branchContext): ?ChannelOrder
    {
        $channel = Channel::find($this->channelId);

        if (! $channel) {
            if ($this->inboundDeliveryId) {
                ChannelWebhookInbound::where('id', $this->inboundDeliveryId)->update([
                    'status' => 'failed',
                    'error' => "Channel #{$this->channelId} tidak ditemukan",
                ]);
            }
            Log::error("ProcessChannelOrderJob: Channel #{$this->channelId} tidak ditemukan");

            return null;
        }

        $cabangId = $this->cabangId
            ?? $channel->kredensial['cabang_id']
            ?? currentCabangId()
            ?? Cabang::value('id')
            ?? 1;

        if ($this->inboundDeliveryId) {
            ChannelWebhookInbound::where('id', $this->inboundDeliveryId)->update([
                'status' => 'processing',
            ]);
        }

        try {
            return $branchContext->runInBranchContext((int) $cabangId, function () use ($channel, $syncService, $cabangId) {
                $channelOrder = $syncService->prosesOrderMasuk($channel, $this->payload);

                if ($this->inboundDeliveryId) {
                    ChannelWebhookInbound::where('id', $this->inboundDeliveryId)->update([
                        'status' => 'processed',
                        'processed_at' => now(),
                        'error' => null,
                    ]);
                }

                Log::info("[{$channel->platform}-webhook-async] Order berhasil diproses", [
                    'channel' => $channel->nama,
                    'order' => $channelOrder->channel_order_id,
                    'status' => $channelOrder->status,
                    'cabang_id' => $cabangId,
                ]);

                return $channelOrder;
            });
        } catch (\Throwable $e) {
            if ($this->inboundDeliveryId) {
                ChannelWebhookInbound::where('id', $this->inboundDeliveryId)->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);
            }

            Log::error("ProcessChannelOrderJob gagal untuk channel #{$channel->id}: {$e->getMessage()}");

            throw $e;
        }
    }
}
