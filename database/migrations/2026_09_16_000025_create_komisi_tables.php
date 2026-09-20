<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skema komisi reseller: % atau nominal tetap per kategori produk
        Schema::create('skema_komisi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama');
            $table->string('kategori')->nullable(); // null = semua kategori
            $table->enum('tipe', ['persen', 'nominal']);
            $table->decimal('nilai', 15, 2); // persen (0-100) atau nominal rupiah
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('komisi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('no_komisi', 40);
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->foreignId('transaksi_id')->nullable()->constrained('transaksi')->nullOnDelete();
            $table->foreignId('skema_komisi_id')->nullable()->constrained('skema_komisi')->nullOnDelete();
            $table->decimal('jumlah_transaksi', 15, 2)->default(0);
            $table->decimal('nominal_komisi', 15, 2);
            $table->enum('status', ['pending', 'disetujui', 'ditolak'])->default('pending');
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->index(['pelanggan_id', 'status']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komisi');
        Schema::dropIfExists('skema_komisi');
    }
};