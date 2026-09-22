<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Piutang
        if (! Schema::hasColumn('piutang', 'cabang_id')) {
            Schema::table('piutang', function (Blueprint $table) {
                $table->foreignId('cabang_id')
                    ->nullable()
                    ->constrained('cabang')
                    ->nullOnDelete()
                    ->after('keterangan');
                $table->index(['cabang_id', 'status']);
            });
        }

        // Backfill piutang.cabang_id from transaksi.cabang_id where transaksi_id exists
        // PORTABLE (MySQL + SQLite): correlated scalar subquery — join()->update() gagal di SQLite
        DB::table('piutang')
            ->whereNotNull('transaksi_id')
            ->whereNull('cabang_id')
            ->update(['cabang_id' => DB::raw('(SELECT t.cabang_id FROM transaksi t WHERE t.id = piutang.transaksi_id)')]);

        // Remaining null → fallback to cabang default (1)
        DB::table('piutang')
            ->whereNull('piutang.cabang_id')
            ->update(['piutang.cabang_id' => 1]);

        // Utang
        if (! Schema::hasColumn('utang', 'cabang_id')) {
            Schema::table('utang', function (Blueprint $table) {
                $table->foreignId('cabang_id')
                    ->nullable()
                    ->constrained('cabang')
                    ->nullOnDelete()
                    ->after('keterangan');
                $table->index(['cabang_id', 'status']);
            });
        }

        // Backfill utang.cabang_id: if referensi_tipe='transaksi' and referensi_id -> transaksi.cabang_id
        // else fallback 1 (PORTABLE — lihat backfill piutang di atas)
        DB::table('utang')
            ->where('referensi_tipe', 'transaksi')
            ->whereNotNull('referensi_id')
            ->whereNull('cabang_id')
            ->update(['cabang_id' => DB::raw('(SELECT t.cabang_id FROM transaksi t WHERE t.id = utang.referensi_id)')]);

        DB::table('utang')
            ->whereNull('utang.cabang_id')
            ->update(['utang.cabang_id' => 1]);
    }

    public function down(): void
    {
        Schema::table('piutang', function (Blueprint $table) {
            $table->dropForeign(['cabang_id']);
            $table->dropIndex(['cabang_id', 'status']);
            $table->dropColumn('cabang_id');
        });

        Schema::table('utang', function (Blueprint $table) {
            $table->dropForeign(['cabang_id']);
            $table->dropIndex(['cabang_id', 'status']);
            $table->dropColumn('cabang_id');
        });
    }
};