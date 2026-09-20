<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akun_coa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode', 20)->unique();
            $table->string('nama');
            $table->enum('tipe', ['aset', 'kewajiban', 'ekuitas', 'pendapatan', 'beban']);
            $table->string('kelompok'); // kas, bank, piutang, persediaan, utang_usaha, utang_komisi, modal, pendapatan_penjualan, dll
            $table->enum('saldo_normal', ['debit', 'kredit']);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tipe']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akun_coa');
    }
};
