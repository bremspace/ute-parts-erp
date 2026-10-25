<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('return_penjualan')) {
            Schema::table('return_penjualan', function (Blueprint $table) {
                if (! Schema::hasColumn('return_penjualan', 'tukar_items_payload')) {
                    $table->json('tukar_items_payload')->nullable()->after('selisih_nominal');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('return_penjualan')) {
            Schema::table('return_penjualan', function (Blueprint $table) {
                if (Schema::hasColumn('return_penjualan', 'tukar_items_payload')) {
                    $table->dropColumn('tukar_items_payload');
                }
            });
        }
    }
};
