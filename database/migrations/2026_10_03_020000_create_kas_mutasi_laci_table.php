<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Seed COA akun pendapatan lain-lain jika belum ada
        if (Schema::hasTable('akun_coa')) {
            DB::table('akun_coa')->updateOrInsert(
                ['kode' => '430-01'],
                [
                    'nama' => 'Pendapatan Lain-lain',
                    'tipe' => 'pendapatan',
                    'kelompok' => 'pendapatan_lain',
                    'saldo_normal' => 'kredit',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 2. Buat tabel kas_mutasi_laci untuk merekam mutasi masuk/keluar kas laci
        if (! Schema::hasTable('kas_mutasi_laci')) {
            Schema::create('kas_mutasi_laci', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('kas_sesi_id')->constrained('kas_sesi')->cascadeOnDelete();
                $table->foreignId('cabang_id')->constrained('cabang')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('jenis', ['masuk', 'keluar']);
                $table->decimal('nominal', 15, 2);
                $table->string('akun_lawan_kode', 20);
                $table->string('keterangan', 255);
                $table->string('no_jurnal', 50)->nullable()->index();
                $table->timestamps();

                $table->index(['kas_sesi_id', 'jenis']);
                $table->index(['cabang_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kas_mutasi_laci');
    }
};
