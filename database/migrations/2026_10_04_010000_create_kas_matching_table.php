<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kas_matching')) {
            Schema::create('kas_matching', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('cabang_id')->nullable()->constrained('cabang')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('akun_id')->constrained('akun_coa')->cascadeOnDelete();
                $table->date('tanggal');
                $table->decimal('saldo_sistem', 15, 2)->default(0);
                $table->decimal('saldo_fisik', 15, 2)->default(0);
                $table->decimal('selisih', 15, 2)->default(0); // saldo_fisik - saldo_sistem
                $table->enum('status', ['cocok', 'selisih', 'disesuaikan'])->default('cocok');
                $table->json('rincian_pecahan')->nullable();
                $table->text('catatan')->nullable();
                $table->foreignId('jurnal_id')->nullable()->constrained('jurnal_akuntansi')->nullOnDelete();
                $table->timestamps();

                $table->index(['cabang_id', 'tanggal']);
                $table->index(['akun_id', 'tanggal']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kas_matching');
    }
};
