<?php

namespace App\Console\Commands;

use App\Modules\Sync\Jobs\BatchPushHybridSyncJob;
use App\Modules\Sync\Jobs\PushHybridSyncJob;
use App\Modules\Sync\Models\HybridSyncOutbox;
use Illuminate\Console\Command;

class SyncFlushCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sync:flush {--batch : Kirim dalam batch massal (BatchPushHybridSyncJob)} {--chunk=50 : Ukuran batch per request}';

    /**
     * The console command description.
     */
    protected $description = 'Kirim ulang semua item outbox hybrid sync yang berstatus pending atau failed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('sync.enabled')) {
            $this->warn('Hybrid sync sedang dinonaktifkan (SYNC_ENABLED=false).');

            return Command::SUCCESS;
        }

        $items = HybridSyncOutbox::whereIn('status', ['pending', 'failed'])
            ->orderBy('created_at', 'asc')
            ->get();

        if ($items->isEmpty()) {
            $this->info('Tidak ada item outbox pending atau failed.');

            return Command::SUCCESS;
        }

        $this->info("Ditemukan {$items->count()} item outbox untuk diproses ulang.");

        if ($this->option('batch')) {
            $chunkSize = max(1, (int) $this->option('chunk'));
            $chunks = $items->pluck('id')->chunk($chunkSize);

            foreach ($chunks as $chunkIds) {
                BatchPushHybridSyncJob::dispatch($chunkIds->all());
            }

            $this->info("Berhasil mendispatch {$chunks->count()} BatchPushHybridSyncJob ({$items->count()} total item) ke antrean queue.");

            return Command::SUCCESS;
        }

        foreach ($items as $item) {
            PushHybridSyncJob::dispatch($item->id);
        }

        $this->info("Berhasil mendispatch {$items->count()} PushHybridSyncJob ke antrean queue.");

        return Command::SUCCESS;
    }
}
