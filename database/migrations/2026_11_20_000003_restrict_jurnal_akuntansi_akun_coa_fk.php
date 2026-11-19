<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * [F-01] Proteksi level DB: jurnal_akuntansi.akun_coa_id dari
     * cascadeOnDelete → restrictOnDelete. Menghapus akun COA yang masih
     * direferensikan baris jurnal ditolak database, sehingga rollback
     * migrasi / raw query tidak dapat memusnahkan jejak audit jurnal.
     *
     * Additive & reversible: hanya mengubah aksi FK, tidak menyentuh data.
     * down() mengembalikan cascadeOnDelete (perilaku lama).
     */
    public function up(): void
    {
        Schema::table('jurnal_akuntansi', function (Blueprint $table) {
            $table->dropForeign(['akun_coa_id']);
        });
        Schema::table('jurnal_akuntansi', function (Blueprint $table) {
            $table->foreign('akun_coa_id')->references('id')->on('akun_coa')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jurnal_akuntansi', function (Blueprint $table) {
            $table->dropForeign(['akun_coa_id']);
        });
        Schema::table('jurnal_akuntansi', function (Blueprint $table) {
            $table->foreign('akun_coa_id')->references('id')->on('akun_coa')->cascadeOnDelete();
        });
    }
};
