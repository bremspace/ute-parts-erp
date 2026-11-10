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
        if (Schema::hasTable('tiket_servis')) {
            Schema::table('tiket_servis', function (Blueprint $table) {
                if (! Schema::hasColumn('tiket_servis', 'offline_pickup_uuid')) {
                    $table->string('offline_pickup_uuid', 36)->nullable()->unique()->after('status');
                }
                if (! Schema::hasColumn('tiket_servis', 'is_offline_pickup')) {
                    $table->boolean('is_offline_pickup')->default(false)->after('offline_pickup_uuid');
                }
                if (! Schema::hasColumn('tiket_servis', 'pickup_synced_at')) {
                    $table->timestamp('pickup_synced_at')->nullable()->after('is_offline_pickup');
                }
            });
        }

        if (Schema::hasTable('return_penjualan')) {
            Schema::table('return_penjualan', function (Blueprint $table) {
                if (! Schema::hasColumn('return_penjualan', 'offline_uuid')) {
                    $table->string('offline_uuid', 36)->nullable()->unique()->after('no_return');
                }
                if (! Schema::hasColumn('return_penjualan', 'is_offline')) {
                    $table->boolean('is_offline')->default(false)->after('offline_uuid');
                }
                if (! Schema::hasColumn('return_penjualan', 'client_created_at')) {
                    $table->timestamp('client_created_at')->nullable()->after('is_offline');
                }
                if (! Schema::hasColumn('return_penjualan', 'synced_at')) {
                    $table->timestamp('synced_at')->nullable()->after('client_created_at');
                }
                if (! Schema::hasColumn('return_penjualan', 'sync_status')) {
                    $table->string('sync_status', 30)->default('online')->after('synced_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tiket_servis')) {
            Schema::table('tiket_servis', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('tiket_servis', 'offline_pickup_uuid')) {
                    $columns[] = 'offline_pickup_uuid';
                }
                if (Schema::hasColumn('tiket_servis', 'is_offline_pickup')) {
                    $columns[] = 'is_offline_pickup';
                }
                if (Schema::hasColumn('tiket_servis', 'pickup_synced_at')) {
                    $columns[] = 'pickup_synced_at';
                }
                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('return_penjualan')) {
            Schema::table('return_penjualan', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('return_penjualan', 'offline_uuid')) {
                    $columns[] = 'offline_uuid';
                }
                if (Schema::hasColumn('return_penjualan', 'is_offline')) {
                    $columns[] = 'is_offline';
                }
                if (Schema::hasColumn('return_penjualan', 'client_created_at')) {
                    $columns[] = 'client_created_at';
                }
                if (Schema::hasColumn('return_penjualan', 'synced_at')) {
                    $columns[] = 'synced_at';
                }
                if (Schema::hasColumn('return_penjualan', 'sync_status')) {
                    $columns[] = 'sync_status';
                }
                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};