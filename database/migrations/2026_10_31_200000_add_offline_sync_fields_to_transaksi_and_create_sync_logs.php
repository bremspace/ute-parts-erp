<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->uuid('offline_uuid')->nullable()->unique()->after('no_transaksi');
            $table->boolean('is_offline')->default(false)->after('status');
            $table->timestamp('client_created_at')->nullable()->after('is_offline');
            $table->timestamp('synced_at')->nullable()->after('client_created_at');
            $table->string('sync_status', 30)->default('online')->after('synced_at'); // online, pending, synced, discrepancy_fixed
            $table->decimal('discrepancy_amount', 15, 2)->default(0)->after('sync_status');

            $table->index(['cabang_id', 'is_offline', 'sync_status'], 'idx_transaksi_offline_sync');
        });

        Schema::create('pos_offline_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('batch_id', 100);
            $table->integer('total_payload')->default(0);
            $table->integer('total_success')->default(0);
            $table->integer('total_conflicts')->default(0);
            $table->json('conflict_details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['cabang_id', 'created_at'], 'idx_pos_offline_sync_logs_cabang_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_offline_sync_logs');

        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropIndex('idx_transaksi_offline_sync');
            $table->dropColumn([
                'offline_uuid',
                'is_offline',
                'client_created_at',
                'synced_at',
                'sync_status',
                'discrepancy_amount',
            ]);
        });
    }
};
