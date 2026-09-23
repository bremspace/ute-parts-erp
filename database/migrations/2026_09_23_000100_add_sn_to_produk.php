<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [F2-3] Flag Serial Number per produk (sn=true → wajib SN di GRN/POS/servis).
 * Idempotent — guard hasColumn.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('produk', 'sn')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->boolean('sn')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('produk', 'sn')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->dropColumn('sn');
            });
        }
    }
};
