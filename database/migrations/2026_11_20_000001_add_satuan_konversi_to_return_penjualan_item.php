<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_penjualan_item', function (Blueprint $table) {
            $table->string('satuan', 50)->default('pcs')->after('produk_id');
            $table->decimal('konversi', 12, 4)->default(1)->after('satuan');
        });
    }

    public function down(): void
    {
        Schema::table('return_penjualan_item', function (Blueprint $table) {
            $table->dropColumn(['satuan', 'konversi']);
        });
    }
};
