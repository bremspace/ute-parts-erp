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
        Schema::create('produk', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama');
            $table->string('slug')->unique();
            $table->text('deskripsi')->nullable();
            $table->string('kategori')->nullable()->index();
            $table->string('brand_kompatibel')->nullable();
            $table->string('model_kompatibel')->nullable();
            $table->string('kondisi')->default('baru'); // baru, oem, compatible
            $table->string('satuan')->default('pcs');
            $table->decimal('harga_beli', 15, 2)->default(0);
            $table->decimal('harga_jual_retail', 15, 2)->default(0);
            $table->string('gambar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produk');
    }
};
