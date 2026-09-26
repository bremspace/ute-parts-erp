<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [B-10d / P1-3] Lengkapi tabel `audit_logs` (AuditService::catat).
 *
 * Masalah yang diperbaiki:
 * 1. Tidak ada kolom `cabang_id` → audit log tidak bisa disaring per cabang
 *    (melanggar aturan "semua query WAJIB scope cabang_id", AGENTS.md).
 * 2. Backfill baris lama: tidak ada `session('cabang_id')` di CLI, jadi kolom
 *    lama dibiarkan NULL (entitas global / cabangnya belum diketahui) —
 *    nullable, TIDAK dipaksa isi. Mengisi data bohong lebih berbahaya
 *    daripada membiarkan NULL.
 *
 * Backfill ke depan dilakukan di `AuditService::catat()` (membaca
 * `session('cabang_id')` saat request) — lihat file tersebut.
 *
 * Snapshot `sebelum`/`sesudah` yang NULL TIDAK diisi `{}` di migrasi ini:
 * memalsukan data audit lebih buruk daripada membiarkan kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        if (! Schema::hasColumn('audit_logs', 'cabang_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->foreignId('cabang_id')->nullable()->after('user_id')
                    ->constrained('cabang')->nullOnDelete();
            });
        }

        // Index komposit (entitas + cabang) supaya filter riwayat audit per
        // cabang tidak full-scan tabel yang bisa membesar terus.
        $indexAda = collect(Schema::getIndexes('audit_logs'))
            ->contains(fn (array $idx) => in_array('cabang_id', $idx['columns'], true) && count($idx['columns']) > 1);

        if (! $indexAda) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->index(['entitas', 'entitas_id', 'cabang_id'], 'audit_logs_entitas_cabang_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_entitas_cabang_index');
        });

        if (Schema::hasColumn('audit_logs', 'cabang_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('cabang_id');
            });
        }
    }
};
