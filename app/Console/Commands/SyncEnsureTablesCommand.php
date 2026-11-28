<?php

namespace App\Console\Commands;

use App\Modules\Sync\Services\SyncSchemaService;
use Illuminate\Console\Command;

class SyncEnsureTablesCommand extends Command
{
    protected $signature = 'sync:ensure-tables';

    protected $description = 'Buat ulang tabel hybrid_sync_outbox/inbox bila belum ada (idempotent)';

    public function handle(SyncSchemaService $schema): int
    {
        $created = $schema->ensureTables();

        $this->info($created === []
            ? 'Tabel hybrid sync sudah tersedia.'
            : 'Tabel dibuat: '.implode(', ', $created).'.');

        return Command::SUCCESS;
    }
}
