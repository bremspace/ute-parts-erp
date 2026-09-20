<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Piutang (AR): invoice/kasbon pelanggan
        Schema::create('piutang', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('no_piutang', 40);
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->foreignId('transaksi_id')->nullable()->constrained('transaksi')->nullOnDelete();
            $table->decimal('jumlah', 15, 2);
            $table->decimal('jumlah_dibayar', 15, 2)->default(0);
            $table->date('jatuh_tempo')->nullable();
            $table->enum('status', ['belum_lunas', 'sebagian', 'lunas'])->default('belum_lunas');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['pelanggan_id', 'status']);
            $table->index(['jatuh_tempo']);
        });

        // Utang (AP): utang supplier & komisi reseller belum dibayar
        Schema::create('utang', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('no_utang', 40);
            $table->string('referensi_tipe'); // komisi, pembelian
            $table->unsignedBigInteger('referensi_id');
            $table->foreignId('pelanggan_id')->nullable()->constrained('pelanggan')->nullOnDelete();
            $table->string('kreditor_nama')->nullable(); // nama supplier atau reseller
            $table->decimal('jumlah', 15, 2);
            $table->decimal('jumlah_dibayar', 15, 2)->default(0);
            $table->date('jatuh_tempo')->nullable();
            $table->enum('status', ['belum_lunas', 'sebagian', 'lunas'])->default('belum_lunas');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['jatuh_tempo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utang');
        Schema::dropIfExists('piutang');
    }
};