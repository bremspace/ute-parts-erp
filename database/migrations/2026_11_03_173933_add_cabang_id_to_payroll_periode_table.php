<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_periode', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_periode', 'cabang_id')) {
                $table->foreignId('cabang_id')->nullable()->after('id')->constrained('cabang')->nullOnDelete();
                $table->index(['cabang_id', 'periode']);
            }
        });

        // Drop unique 'periode' lama jika ada dan gantikan dengan composite unique (periode + cabang_id)
        // agar periode '2026-11' bisa ada terpisah untuk cabang 1, cabang 2, dst.
        // Di SQLite / MySQL, kita cek keberadaan indeks sebelum drop.
        try {
            Schema::table('payroll_periode', function (Blueprint $table) {
                $table->dropUnique('payroll_periode_periode_unique');
            });
        } catch (Throwable $e) {
            // Abaikan jika indeks tidak ditemukan
        }

        try {
            Schema::table('payroll_periode', function (Blueprint $table) {
                $table->unique(['periode', 'cabang_id'], 'payroll_periode_periode_cabang_unique');
            });
        } catch (Throwable $e) {
            // Abaikan jika sudah ada
        }
    }

    public function down(): void
    {
        Schema::table('payroll_periode', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_periode', 'cabang_id')) {
                try {
                    $table->dropUnique('payroll_periode_periode_cabang_unique');
                } catch (Throwable $e) {
                }
                $table->dropForeign(['cabang_id']);
                $table->dropColumn('cabang_id');
                $table->unique('periode', 'payroll_periode_periode_unique');
            }
        });
    }
};
