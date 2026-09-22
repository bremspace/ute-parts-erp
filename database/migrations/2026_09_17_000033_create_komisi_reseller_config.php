<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // [T-21] Skema komisi PER RESELLER (override per kategori) — fallback ke skema default
        Schema::create('skema_komisi_reseller', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->string('kategori')->nullable(); // null = semua (fallback utk reseller ini)
            $table->enum('tipe', ['persen', 'nominal']);
            $table->decimal('nilai', 15, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pelanggan_id', 'kategori']);
        });

        // [T-22] Konfigurasi strategi loyalitas (ganti pakai operational tanpa deploy)
        Schema::create('konfigurasi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kunci')->unique();
            $table->text('nilai')->nullable(); // JSON string utk nilai kompleks
            $table->string('deskripsi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konfigurasi');
        Schema::dropIfExists('skema_komisi_reseller');
    }
};
