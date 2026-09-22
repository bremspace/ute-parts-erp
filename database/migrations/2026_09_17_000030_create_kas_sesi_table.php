<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // [T-09] Kas Sesi — shift kasir, sinkron jurnal akunting (PRD §4.6)
        Schema::create('kas_sesi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('cabang_id')->constrained('cabang')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('saldo_awal', 15, 2)->default(0);
            $table->decimal('saldo_akhir_sistem', 15, 2)->nullable();
            $table->decimal('saldo_akhir_fisik', 15, 2)->nullable();
            $table->decimal('selisih', 15, 2)->nullable();
            $table->enum('status', ['buka', 'tutup'])->default('buka');
            $table->timestamp('dibuka_at')->nullable();
            $table->timestamp('ditutup_at')->nullable();
            $table->timestamps();

            $table->index(['cabang_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kas_sesi');
    }
};
