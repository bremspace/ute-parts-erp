<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hybrid_sync_pos_transaksi', function (Blueprint $table) {
            $table->id();
            $table->string('no_transaksi');
            $table->string('source_node')->default('STORE-01');
            $table->string('sumber')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('cabang_id')->nullable();
            $table->unsignedBigInteger('kasir_id')->nullable();
            $table->string('kasir_nama')->nullable();
            $table->unsignedBigInteger('pelanggan_id')->nullable();
            $table->string('pelanggan_nama')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('diskon_total', 18, 2)->default(0);
            $table->decimal('pajak_nominal', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->string('metode_bayar')->nullable();
            $table->unsignedInteger('jumlah_item')->default(0);
            $table->timestamp('transaksi_created_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['no_transaksi', 'source_node'], 'hybrid_sync_pos_trx_unique');
            $table->index(['cabang_id', 'transaksi_created_at'], 'hybrid_sync_pos_trx_cabang_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hybrid_sync_pos_transaksi');
    }
};
