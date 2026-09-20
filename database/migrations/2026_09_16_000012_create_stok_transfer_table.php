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
        Schema::create('stok_transfer', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('no_transfer')->unique();
            $table->foreignId('gudang_asal_id')->constrained('gudang')->cascadeOnDelete();
            $table->foreignId('gudang_tujuan_id')->constrained('gudang')->cascadeOnDelete();
            $table->foreignId('user_pengirim_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_penerima_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft'); // draft, dikirim, diterima, batal
            $table->timestamp('tanggal_kirim')->nullable();
            $table->timestamp('tanggal_terima')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['gudang_asal_id', 'status']);
            $table->index(['gudang_tujuan_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stok_transfer');
    }
};
