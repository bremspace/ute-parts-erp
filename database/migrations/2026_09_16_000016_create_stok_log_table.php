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
        Schema::create('stok_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('sku_variant_id')->nullable()->constrained('sku_variants')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('jenis'); // masuk, keluar, transfer_masuk, transfer_keluar, opname, penjualan, servis, penyesuaian
            $table->string('referensi_tipe')->nullable(); // App\Modules\Pos\Models\Transaksi, dll
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->integer('jumlah_sebelum');
            $table->integer('perubahan'); // positif (tambah) atau negatif (kurang)
            $table->integer('jumlah_setelah');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['gudang_id', 'produk_id']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stok_log');
    }
};
