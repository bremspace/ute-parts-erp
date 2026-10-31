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
        if (! Schema::hasTable('karyawan_riwayat')) {
            Schema::create('karyawan_riwayat', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Pencatat / author
                $table->enum('tipe_event', [
                    'rekrut',
                    'promosi',
                    'mutasi',
                    'perubahan_gaji',
                    'terminasi',
                    'rehire',
                    'peringatan',
                    'penghargaan',
                    'catatan',
                ])->default('catatan');
                $table->date('tanggal_efektif');
                $table->string('judul', 255);
                $table->text('deskripsi')->nullable();
                $table->string('jabatan_lama', 50)->nullable();
                $table->string('jabatan_baru', 50)->nullable();
                $table->foreignId('cabang_lama_id')->nullable()->constrained('cabang')->nullOnDelete();
                $table->foreignId('cabang_baru_id')->nullable()->constrained('cabang')->nullOnDelete();
                $table->decimal('gaji_lama', 15, 2)->nullable();
                $table->decimal('gaji_baru', 15, 2)->nullable();
                $table->string('dokumen_referensi', 100)->nullable(); // Nomor SK / Surat / memo
                $table->timestamps();

                $table->index(['karyawan_id', 'tanggal_efektif']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('karyawan_riwayat');
    }
};
