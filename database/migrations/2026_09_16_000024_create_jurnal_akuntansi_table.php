<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal_akuntansi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('no_jurnal', 40);
            $table->timestamp('tanggal');
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->nullOnDelete();
            $table->foreignId('akun_coa_id')->constrained('akun_coa')->cascadeOnDelete();
            $table->string('sumber'); // pos, servis, komisi, opname, pembelian, manual
            $table->string('deskripsi');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('kredit', 15, 2)->default(0);
            $table->string('referensi_tipe')->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['no_jurnal']);
            $table->index(['akun_coa_id', 'tanggal']);
            $table->index(['cabang_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_akuntansi');
    }
};
