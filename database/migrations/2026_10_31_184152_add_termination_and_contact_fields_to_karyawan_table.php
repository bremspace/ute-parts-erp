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
        Schema::table('karyawan', function (Blueprint $table) {
            if (! Schema::hasColumn('karyawan', 'telepon')) {
                $table->string('telepon', 30)->nullable()->after('nama');
            }
            if (! Schema::hasColumn('karyawan', 'email')) {
                $table->string('email', 100)->nullable()->after('telepon');
            }
            if (! Schema::hasColumn('karyawan', 'tgl_keluar')) {
                $table->date('tgl_keluar')->nullable()->after('tgl_masuk');
            }
            if (! Schema::hasColumn('karyawan', 'alasan_keluar')) {
                $table->string('alasan_keluar', 50)->nullable()->after('tgl_keluar');
            }
            if (! Schema::hasColumn('karyawan', 'catatan_keluar')) {
                $table->text('catatan_keluar')->nullable()->after('alasan_keluar');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $cols = ['telepon', 'email', 'tgl_keluar', 'alasan_keluar', 'catatan_keluar'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('karyawan', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
