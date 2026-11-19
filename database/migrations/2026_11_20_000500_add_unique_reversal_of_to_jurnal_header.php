<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [F-03] Jaring DB anti double reversal: satu jurnal asal hanya boleh punya
 * SATU jurnal pembalik per cabang. Cek is_reversed di aplikasi bisa race
 * (dua request membaca state yang sama sebelum salah satu commit); unique
 * index ini menolak reversal kedua di level mesin DB.
 *
 * reversal_of_no_jurnal nullable: header non-reversal bernilai NULL dan
 * NULL tidak dianggap duplikat oleh unique index (MySQL & SQLite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal_header', function (Blueprint $table) {
            $table->unique(['cabang_key', 'reversal_of_no_jurnal'], 'jurnal_header_cabang_reversal_of_unique');
        });
    }

    public function down(): void
    {
        Schema::table('jurnal_header', function (Blueprint $table) {
            $table->dropUnique('jurnal_header_cabang_reversal_of_unique');
        });
    }
};
