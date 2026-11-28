<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Idempoten: aman di database client yang sudah punya tabel dari command
     * self-healing tetapi baris migrasi lamanya hilang.
     */
    public function up(): void
    {
        // 1. Antrean data keluar dari node ini ke node lawan
        if (! Schema::hasTable('hybrid_sync_outbox')) {
            Schema::create('hybrid_sync_outbox', function (Blueprint $table): void {
                $table->id();
                $table->string('sync_uuid', 36)->unique();
                $table->string('event_type', 50); // stok.mutasi, produk.upsert, transaksi.pos, servis.update, order.online_paid
                $table->string('entity_type', 150);
                $table->unsignedBigInteger('entity_id');
                $table->unsignedBigInteger('cabang_id')->nullable();
                $table->json('payload');
                $table->string('status', 20)->default('pending'); // pending, processing, synced, failed
                $table->integer('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('cabang_id');
            });
        }

        // 2. Log penerima untuk menjamin idempotency
        if (! Schema::hasTable('hybrid_sync_inbox')) {
            Schema::create('hybrid_sync_inbox', function (Blueprint $table): void {
                $table->id();
                $table->string('sync_uuid', 36)->unique();
                $table->string('source_node', 50);
                $table->string('event_type', 50);
                $table->string('payload_hash', 64);
                $table->string('status', 20); // processed, ignored, failed
                $table->text('error_message')->nullable();
                $table->timestamp('processed_at')->useCurrent();
                $table->timestamps();

                $table->index('event_type');
                $table->index('source_node');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hybrid_sync_inbox');
        Schema::dropIfExists('hybrid_sync_outbox');
    }
};
