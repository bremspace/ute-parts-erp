<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekonsiliasi_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->cascadeOnDelete();
            $table->foreignId('akun_coa_id')->constrained('akun_coa')->cascadeOnDelete();
            $table->string('nama_bank', 50)->default('BCA');
            $table->string('nomor_rekening', 50)->nullable();
            $table->date('periode_dari');
            $table->date('periode_sampai');
            $table->string('nama_file');
            $table->string('format_file', 20); // 'csv', 'xlsx', 'pdf'
            $table->decimal('saldo_awal_bank', 15, 2)->default(0);
            $table->decimal('saldo_akhir_bank', 15, 2)->default(0);
            $table->decimal('saldo_buku_besar', 15, 2)->default(0);
            $table->decimal('selisih', 15, 2)->default(0);
            $table->string('status', 20)->default('draft'); // 'draft', 'reconciled'
            $table->unsignedInteger('total_baris_bank')->default(0);
            $table->unsignedInteger('total_matched')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['cabang_id', 'status']);
            $table->index(['cabang_id', 'akun_coa_id']);
        });

        Schema::create('rekonsiliasi_bank_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekonsiliasi_bank_id')->constrained('rekonsiliasi_bank')->cascadeOnDelete();
            $table->date('tanggal');
            $table->text('deskripsi');
            $table->string('tipe', 10); // 'DB' (Debit/Keluar) | 'CR' (Kredit/Masuk)
            $table->decimal('nominal', 15, 2);
            $table->string('status', 20)->default('unmatched'); // 'matched', 'unmatched', 'adjusted'
            $table->foreignId('jurnal_akuntansi_id')->nullable()->constrained('jurnal_akuntansi')->nullOnDelete();
            $table->string('no_jurnal_matched', 50)->nullable();
            $table->string('catatan', 255)->nullable();
            $table->timestamps();

            $table->index(['rekonsiliasi_bank_id', 'status']);
            $table->index(['tanggal', 'nominal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekonsiliasi_bank_item');
        Schema::dropIfExists('rekonsiliasi_bank');
    }
};
