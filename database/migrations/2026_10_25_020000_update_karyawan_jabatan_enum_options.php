<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('karyawan')) {
            if (DB::getDriverName() === 'sqlite') {
                // SQLite: string biasa tanpa enum check
                Schema::table('karyawan', function (Blueprint $table) {
                    $table->string('jabatan', 100)->default('other')->change();
                });
            } else {
                DB::statement("ALTER TABLE karyawan MODIFY jabatan ENUM('teknisi','kasir','admin','marketing','manager','staff','other')");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('karyawan') && DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE karyawan MODIFY jabatan ENUM('teknisi','kasir','admin','marketing','other')");
        }
    }
};
