<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [F-09] Komisi tiket servis yang dibatalkan setelah selesai harus bisa
 * ditandai 'dibatalkan' agar tidak masuk payroll/approval. Enum komisi.status
 * lama hanya ('pending','disetujui','ditolak') — MySQL STRICT menolak nilai
 * baru. Sekalian sahkan 'cair' yang sudah dipakai KomisiService (belum ada
 * di migrasi) dan 'batal' piutang yang sudah ditulis ServisService::onBatal
 * (pola migrasi B-4 utang).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('komisi')) {
            if (DB::getDriverName() === 'sqlite') {
                Schema::table('komisi', function (Blueprint $table) {
                    $table->string('status', 20)->default('pending')->change();
                });
            } else {
                DB::statement("ALTER TABLE komisi MODIFY status ENUM('pending','disetujui','ditolak','cair','dibatalkan') NOT NULL DEFAULT 'pending'");
            }
        }

        if (Schema::hasTable('piutang')) {
            if (DB::getDriverName() === 'sqlite') {
                Schema::table('piutang', function (Blueprint $table) {
                    $table->string('status', 20)->default('belum_lunas')->change();
                });
            } else {
                DB::statement("ALTER TABLE piutang MODIFY status ENUM('belum_lunas','sebagian','lunas','batal') NOT NULL DEFAULT 'belum_lunas'");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            if (Schema::hasTable('komisi')) {
                DB::statement("ALTER TABLE komisi MODIFY status ENUM('pending','disetujui','ditolak') NOT NULL DEFAULT 'pending'");
            }
            if (Schema::hasTable('piutang')) {
                DB::statement("ALTER TABLE piutang MODIFY status ENUM('belum_lunas','sebagian','lunas') NOT NULL DEFAULT 'belum_lunas'");
            }
        }
    }
};
