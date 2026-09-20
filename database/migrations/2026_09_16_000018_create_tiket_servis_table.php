<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiket_servis', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('no_tiket')->unique();
            $table->foreignId('cabang_id')->constrained('cabang')->cascadeOnDelete();
            $table->foreignId('jenis_servis_id')->nullable()->constrained('jenis_servis')->nullOnDelete();
            $table->foreignId('pelanggan_id')->nullable()->constrained('pelanggan')->nullOnDelete();
            $table->foreignId('teknisi_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_pelanggan')->nullable(); // guest tanpa akun
            $table->string('telepon_pelanggan')->nullable();
            $table->string('jenis_hp');
            $table->string('seri_hp')->nullable(); // IMEI/serial
            $table->text('keluhan');
            $table->json('kondisi_fisik')->nullable(); // checklist kondisi fisik unit
            $table->json('foto_unit')->nullable(); // wajib minimal 2 foto
            $table->string('status')->default('diterima');
            $table->string('sumber')->default('walkin'); // walkin, online
            $table->decimal('estimasi_biaya', 15, 2)->nullable();
            $table->text('alasan_estimasi')->nullable();
            $table->string('token_approval')->unique()->nullable(); // token publik approve/reject
            $table->timestamp('tanggal_terima')->nullable();
            $table->timestamp('tanggal_selesai')->nullable();
            $table->timestamp('tanggal_diambil')->nullable();
            $table->text('catatan_admin')->nullable(); // alasan override
            $table->timestamps();

            $table->index(['cabang_id', 'status']);
            $table->index(['pelanggan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiket_servis');
    }
};