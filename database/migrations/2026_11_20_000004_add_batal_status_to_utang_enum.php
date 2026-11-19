<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [B-4] Void transaksi berkomisi menandai utang komisi 'batal', tetapi enum
 * utang.status hanya ('belum_lunas','sebagian','lunas'). Pada MySQL dengan
 * STRICT_TRANS_TABLES nilai di luar enum ditolak -> seluruh void rollback.
 * Tambahkan nilai 'batal' agar pembatalan utang komisi saat void sah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('utang')) {
            if (DB::getDriverName() === 'sqlite') {
                // SQLite: string biasa tanpa enum check (pola migrasi karyawan.jabatan).
                Schema::table('utang', function (Blueprint $table) {
                    $table->string('status', 20)->default('belum_lunas')->change();
                });
            } else {
                DB::statement("ALTER TABLE utang MODIFY status ENUM('belum_lunas','sebagian','lunas','batal') NOT NULL DEFAULT 'belum_lunas'");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('utang') && DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE utang MODIFY status ENUM('belum_lunas','sebagian','lunas') NOT NULL DEFAULT 'belum_lunas'");
        }
    }
};
