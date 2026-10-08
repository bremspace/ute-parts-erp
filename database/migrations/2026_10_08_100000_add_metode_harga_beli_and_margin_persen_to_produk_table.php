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
            $table->string('metode_harga_beli', 20)->default('average')->after('harga_beli');
            $table->decimal('margin_persen', 5, 2)->nullable()->after('harga_jual_retail');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropColumn(['metode_harga_beli', 'margin_persen']);
        });
    }
};
