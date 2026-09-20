<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Log keluaran notifikasi (WA/email) via queue — provider WA keputusan user, hook siap
        Schema::create('notifikasi_keluar', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tipe'); // wa, email, inapp
            $table->string('tujuan')->nullable(); // nomor HP / email
            $table->string('judul')->nullable();
            $table->text('konten');
            $table->json('payload')->nullable();
            $table->string('status')->default('pending'); // pending, terkirim, gagal
            $table->text('error')->nullable();
            $table->timestamps();
        });

        // Tabel notifikasi in-app bawaan Laravel (DatabaseChannel)
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_keluar');
        Schema::dropIfExists('notifications');
    }
};
