<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [T-43] Log hasil import massal (Excel/CSV) — produk, dsb.
 * Dipakai job ImportProdukExcelJob untuk mencatat sukses/gagal per baris.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tipe')->default('produk_excel');
            $table->string('nama_file')->nullable();
            $table->integer('total_baris')->default(0);
            $table->integer('sukses')->default(0);
            $table->integer('gagal')->default(0);
            $table->enum('status', ['proses', 'selesai', 'gagal'])->default('proses');
            $table->json('detail')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tipe', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_log');
    }
};
