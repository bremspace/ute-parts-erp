<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [AUDIT-FIX F-04] Tautkan baris payroll_komisi_detail ke komisi sumber.
 *
 * Sebelumnya pencairan komisi saat bayarPayroll memakai window created_at
 * global (semua karyawan, semua cabang) — komisi yang tidak pernah dibayar
 * ikut ditandai cair. Dengan komisi_id, pencairan di-scope persis ke komisi
 * yang tercantum pada slip periode+cabang yang dibayar.
 *
 * Nullable: baris detail lama (pra-migrasi) tidak punya tautan; komisi tanpa
 * tautan TIDAK auto-cair (fail-closed, perlu rekonsiliasi manual).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_komisi_detail') || Schema::hasColumn('payroll_komisi_detail', 'komisi_id')) {
            return;
        }

        Schema::table('payroll_komisi_detail', function (Blueprint $table) {
            $table->foreignId('komisi_id')->nullable()->after('slip_id')->constrained('komisi')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_komisi_detail') && Schema::hasColumn('payroll_komisi_detail', 'komisi_id')) {
            Schema::table('payroll_komisi_detail', function (Blueprint $table) {
                $table->dropForeign(['komisi_id']);
                $table->dropColumn('komisi_id');
            });
        }
    }
};
