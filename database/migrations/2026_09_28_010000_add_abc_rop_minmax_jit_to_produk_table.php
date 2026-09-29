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
            $table->enum('abc_class', ['A', 'B', 'C'])->default('B')->after('is_active');
            $table->integer('reorder_point')->nullable()->after('abc_class');
            $table->integer('min_stock')->nullable()->after('reorder_point');
            $table->integer('max_stock')->nullable()->after('min_stock');
            $table->boolean('is_ondemand')->default(false)->after('max_stock');

            $table->index(['abc_class', 'is_ondemand']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropIndex(['abc_class', 'is_ondemand']);
            $table->dropColumn(['abc_class', 'reorder_point', 'min_stock', 'max_stock', 'is_ondemand']);
        });
    }
};
