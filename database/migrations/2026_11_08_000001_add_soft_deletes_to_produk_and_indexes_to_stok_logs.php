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
        Schema::table('produk', function (Blueprint $table) {
            if (! Schema::hasColumn('produk', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('stok_log', function (Blueprint $table) {
            $table->index(['referensi_tipe', 'referensi_id'], 'stok_log_referensi_index');
        });

        Schema::table('stock_mutation_log', function (Blueprint $table) {
            $table->index(['referensi_tipe', 'referensi_id'], 'stock_mutation_log_referensi_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            if (Schema::hasColumn('produk', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('stok_log', function (Blueprint $table) {
            $table->dropIndex('stok_log_referensi_index');
        });

        Schema::table('stock_mutation_log', function (Blueprint $table) {
            $table->dropIndex('stock_mutation_log_referensi_index');
        });
    }
};
