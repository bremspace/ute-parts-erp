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
        Schema::create('stok_transfer_item', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('stok_transfer_id')->constrained('stok_transfer')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('sku_variant_id')->nullable()->constrained('sku_variants')->nullOnDelete();
            $table->integer('jumlah');
            $table->timestamps();

            $table->index(['stok_transfer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stok_transfer_item');
    }
};
