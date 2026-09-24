<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: run sebelumnya gagal di backfill (DDL auto-commit) → kolom sudah ada.
        if (! Schema::hasColumn('piutang', 'sisa')) {
            Schema::table('piutang', function (Blueprint $table) {
                $table->decimal('sisa', 20, 2)->default(0)->after('jumlah_dibayar');
            });
        }

        // Backfill rows existing: CASE WHEN (ANSI) — portabel MySQL & SQLite (tes :memory:).
        // `MAX(jumlah - jumlah_dibayar, 0)` ditolak MySQL staging (SQLSTATE 1064).
        DB::table('piutang')->update([
            'sisa' => DB::raw('CASE WHEN jumlah - jumlah_dibayar > 0 THEN jumlah - jumlah_dibayar ELSE 0 END'),
        ]);
    }

    public function down(): void
    {
        Schema::table('piutang', function (Blueprint $table) {
            $table->dropColumn('sisa');
        });
    }
};
