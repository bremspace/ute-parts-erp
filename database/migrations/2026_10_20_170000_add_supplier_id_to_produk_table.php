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
        if (Schema::hasTable('produk') && ! Schema::hasColumn('produk', 'supplier_id')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->foreignId('supplier_id')
                    ->nullable()
                    ->after('kategori_id')
                    ->constrained('supplier')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('produk') && Schema::hasColumn('produk', 'supplier_id')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->dropForeign(['supplier_id']);
                $table->dropColumn('supplier_id');
            });
        }
    }
};
