<?php

namespace App\Console\Commands;

use App\Modules\Sync\Jobs\BatchPushHybridSyncJob;
use App\Modules\Sync\Jobs\PushHybridSyncJob;
use App\Modules\Sync\Models\HybridSyncOutbox;
use App\Modules\Sync\Providers\SyncServiceProvider;
use Illuminate\Console\Command;

class SyncFlushCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sync:flush {--batch : Kirim dalam batch massal (BatchPushHybridSyncJob)} {--chunk=50 : Ukuran batch per request} {--sync-now : Jalankan langsung tanpa menunggu queue worker}';

    /**
     * The console command description.
     */
    protected $description = 'Kirim ulang semua item outbox hybrid sync yang berstatus pending atau failed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        SyncServiceProvider::refreshRuntimeConfigIfChanged();

        if (! config('sync.enabled')) {
            $this->warn('Hybrid sync sedang dinonaktifkan (SYNC_ENABLED=false).');

            return Command::SUCCESS;
        }

        if (config('sync.node_role') !== 'primary_store' || empty(config('sync.remote_url'))) {
            $this->info('Node ini tidak memiliki target remote push (hanya node primary_store dengan SYNC_REMOTE_URL yang melakukan push).');

            return Command::SUCCESS;
        }

        // Pulihkan item 'processing' yang menggantung > 5 menit akibat crash worker
        HybridSyncOutbox::where('status', 'processing')
            ->where('updated_at', '<=', now()->subMinutes(5))
            ->update(['status' => 'pending']);

        $items = HybridSyncOutbox::whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', 10)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($items->isEmpty()) {
            $this->info('Tidak ada item outbox pending atau failed.');

            return Command::SUCCESS;
        }

        $this->info("Ditemukan {$items->count()} item outbox untuk diproses ulang.");

        $syncNow = (bool) $this->option('sync-now');

        if ($this->option('batch') || $syncNow) {
            $chunkSize = max(1, (int) $this->option('chunk'));
            $chunks = $items->pluck('id')->chunk($chunkSize);

            foreach ($chunks as $chunkIds) {
                if ($syncNow) {
                    BatchPushHybridSyncJob::dispatchSync($chunkIds->all());
                } else {
                    BatchPushHybridSyncJob::dispatch($chunkIds->all());
                }
            }

            $modeLabel = $syncNow ? 'dieksekusi langsung' : 'didispatch ke antrean queue';
            $this->info("Berhasil {$chunks->count()} BatchPushHybridSyncJob ({$items->count()} total item) {$modeLabel}.");

            return Command::SUCCESS;
        }

        foreach ($items as $item) {
            PushHybridSyncJob::dispatch($item->id);
        }

        $this->info("Berhasil mendispatch {$items->count()} PushHybridSyncJob ke antrean queue.");

        return Command::SUCCESS;
    }
}
