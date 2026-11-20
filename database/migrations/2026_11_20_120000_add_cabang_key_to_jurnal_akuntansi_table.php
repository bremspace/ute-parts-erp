<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [F-18] Isolasi relasi header↔lines per cabang.
 *
 * Unique index jurnal_header (cabang_key, no_jurnal) memperbolehkan no_jurnal
 * sama di cabang berbeda, tetapi relasi Eloquent sebelumnya hanya keyed
 * no_jurnal → $header->lines menarik baris KEDUA cabang (buku besar tercampur,
 * balanced() header vs lines salah).
 *
 * Solusi:
 * 1. Kolom bantu jurnal_akuntansi.cabang_key (= cabang_id ?? 0, NOT NULL —
 *    pola sama dengan jurnal_header.cabang_key), di-backfill dari cabang_id.
 * 2. Kunci relasi komposit tunggal `jurnal_rel_key` = cabang_key:no_jurnal
 *    (generated column di KEDUA tabel) sehingga hasMany/belongsTo standar
 *    Eloquent benar untuk lazy-load, eager-load, dan whereHas — tanpa kelas
 *    relasi kustom. no_jurnal & cabang_key immutable → kunci stabil.
 *
 * MySQL: STORED generated + index. SQLite (test): VIRTUAL generated (SQLite
 * melarang ADD COLUMN STORED via ALTER TABLE; index pada VIRTUAL tidak
 * didukung — produksi MySQL tetap ter-index).
 *
 * Backfill idempoten: cabang_key diisi dari cabang_id baris existing via query
 * builder (baris jurnal immutable di Eloquent; ini hanya mengisi kolom bantu
 * baru, tidak mengubah data finansial).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal_akuntansi', function (Blueprint $table) {
            $table->unsignedBigInteger('cabang_key')->default(0)->after('cabang_id');
            $table->index(['cabang_key', 'no_jurnal'], 'jurnal_akuntansi_cabang_no_jurnal_index');
        });

        DB::table('jurnal_akuntansi')->update([
            'cabang_key' => DB::raw('COALESCE(cabang_id, 0)'),
        ]);

        $sqlite = DB::getDriverName() === 'sqlite';
        $ekspresi = $sqlite
            ? "cabang_key || ':' || no_jurnal"
            : "CONCAT(cabang_key, ':', no_jurnal)";

        Schema::table('jurnal_akuntansi', function (Blueprint $table) use ($sqlite, $ekspresi) {
            $kolom = $table->string('jurnal_rel_key', 80);
            $sqlite ? $kolom->virtualAs($ekspresi) : $kolom->storedAs($ekspresi);
            if (! $sqlite) {
                $table->index('jurnal_rel_key', 'jurnal_akuntansi_rel_key_index');
            }
        });

        Schema::table('jurnal_header', function (Blueprint $table) use ($sqlite, $ekspresi) {
            $kolom = $table->string('jurnal_rel_key', 80);
            $sqlite ? $kolom->virtualAs($ekspresi) : $kolom->storedAs($ekspresi);
        });
    }

    public function down(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        Schema::table('jurnal_header', function (Blueprint $table) {
            $table->dropColumn('jurnal_rel_key');
        });

        Schema::table('jurnal_akuntansi', function (Blueprint $table) use ($sqlite) {
            if (! $sqlite) {
                $table->dropIndex('jurnal_akuntansi_rel_key_index');
            }
            $table->dropColumn('jurnal_rel_key');
            $table->dropIndex('jurnal_akuntansi_cabang_no_jurnal_index');
            $table->dropColumn('cabang_key');
        });
    }
};
