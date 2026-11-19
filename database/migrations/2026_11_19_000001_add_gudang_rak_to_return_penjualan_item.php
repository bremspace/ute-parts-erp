<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_penjualan_item', function (Blueprint $table) {
            $table->unsignedBigInteger('gudang_id')->nullable()->after('produk_id');
            $table->unsignedBigInteger('rak_id')->nullable()->after('gudang_id');
            $table->foreign('gudang_id')->references('id')->on('gudang')->nullOnDelete();
            $table->foreign('rak_id')->references('id')->on('rak')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('return_penjualan_item', function (Blueprint $table) {
            $table->dropForeign(['gudang_id']);
            $table->dropForeign(['rak_id']);
            $table->dropColumn(['gudang_id', 'rak_id']);
        });
    }
};
