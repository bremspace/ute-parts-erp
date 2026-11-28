<?php

use App\Modules\Sync\Services\SyncSchemaService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Self-healing untuk database node client/Cloud yang sudah menandai migrasi
 * hybrid sync sebagai "ran" tetapi tabelnya tidak ada (misal karena restore
 * database lama atau image lama). Aman dijalankan berulang kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(SyncSchemaService::class)->ensureTables();
    }

    public function down(): void
    {
        Schema::dropIfExists('hybrid_sync_inbox');
        Schema::dropIfExists('hybrid_sync_outbox');
    }
};
