<?php

use App\Modules\Sync\BackofficeSyncRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (BackofficeSyncRegistry::tableNames() as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'sync_uuid')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->string('sync_uuid', 36)->nullable();
                $table->unique('sync_uuid', "{$tableName}_sync_uuid_unique");
            });
        }
    }

    public function down(): void
    {
        foreach (['pelanggan_kode_aktivasi', 'rekonsiliasi_bank_item', 'rekonsiliasi_bank'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'sync_uuid')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropUnique("{$tableName}_sync_uuid_unique");
                $table->dropColumn('sync_uuid');
            });
        }
    }
};
