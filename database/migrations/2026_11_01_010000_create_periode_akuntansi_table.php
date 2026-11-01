<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_akuntansi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabang')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan'); // 1 - 12
            $table->string('status', 20)->default('open'); // 'open' | 'closed'
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('no_jurnal_penutup', 50)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['cabang_id', 'tahun', 'bulan'], 'unique_cabang_periode');
            $table->index(['cabang_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_akuntansi');
    }
};
