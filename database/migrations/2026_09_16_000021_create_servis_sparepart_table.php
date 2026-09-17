<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servis_sparepart', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('tiket_servis_id')->constrained('tiket_servis')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('sku_variant_id')->nullable()->constrained('sku_variants')->nullOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
            $table->integer('jumlah');
            $table->decimal('harga_satuan', 15, 2)->default(0); // harga jual ke pelanggan
            $table->decimal('hpp', 15, 2)->default(0); // HPP untuk jurnal
            $table->timestamps();

            $table->index(['tiket_servis_id']);
            $table->index(['produk_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servis_sparepart');
    }
};