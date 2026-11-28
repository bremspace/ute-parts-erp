<?php

namespace App\Modules\Sync\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memastikan tabel infrastruktur hybrid sync tersedia.
 *
 * Dipakai oleh migrasi self-healing dan command `sync:ensure-tables` yang
 * dijalankan entrypoint Docker client. Aman dipanggil berulang kali.
 */
class SyncSchemaService
{
    /** @return array<int, string> Nama tabel yang baru dibuat */
    public function ensureTables(): array
    {
        $created = [];

        if (! Schema::hasTable('hybrid_sync_outbox')) {
            Schema::create('hybrid_sync_outbox', function (Blueprint $table): void {
                $table->id();
                $table->string('sync_uuid', 36)->unique();
                $table->string('event_type', 50);
                $table->string('entity_type', 150);
                $table->unsignedBigInteger('entity_id');
                $table->unsignedBigInteger('cabang_id')->nullable();
                $table->json('payload');
                $table->string('status', 20)->default('pending');
                $table->integer('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('cabang_id');
            });
            $created[] = 'hybrid_sync_outbox';
        }

        if (! Schema::hasTable('hybrid_sync_inbox')) {
            Schema::create('hybrid_sync_inbox', function (Blueprint $table): void {
                $table->id();
                $table->string('sync_uuid', 36)->unique();
                $table->string('source_node', 50);
                $table->string('event_type', 50);
                $table->string('payload_hash', 64);
                $table->string('status', 20);
                $table->text('error_message')->nullable();
                $table->timestamp('processed_at')->useCurrent();
                $table->timestamps();

                $table->index('event_type');
                $table->index('source_node');
            });
            $created[] = 'hybrid_sync_inbox';
        }

        return $created;
    }
}
