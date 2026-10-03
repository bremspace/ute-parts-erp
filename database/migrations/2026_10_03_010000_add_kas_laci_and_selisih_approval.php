<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [KAS-LACI] Kas Laci (110-04) + selisih approval flow.
 *
 * 1. COA: insert 110-04 "Kas Laci" (idempotent).
 * 2. kas_sesi: add akun_sumber_kode, catatan, extend status enum to include 'menunggu_approval'.
 * 3. approval_rules: seed selisih_kas rule (super-admin, any amount).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. COA: Kas Laci (idempotent)
        if (Schema::hasTable('akun_coa')) {
            DB::table('akun_coa')->updateOrInsert(
                ['kode' => '110-04'],
                [
                    'nama' => 'Kas Laci',
                    'tipe' => 'aset',
                    'kelompok' => 'kas',
                    'saldo_normal' => 'debit',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 2. kas_sesi: add columns + extend status enum
        Schema::table('kas_sesi', function (Blueprint $table) {
            $table->string('akun_sumber_kode', 20)->nullable()->after('sumber');
            $table->text('catatan')->nullable()->after('selisih');
        });

        // Extend status enum: buka, tutup → buka, tutup, menunggu_approval (MySQL only; SQLite handles strings transparently)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE kas_sesi MODIFY COLUMN status ENUM('buka','tutup','menunggu_approval') NOT NULL DEFAULT 'buka'");
        }

        // 3. approval_rules: selisih_kas — super-admin, owner, admin-toko (idempotent)
        if (Schema::hasTable('approval_rules')) {
            foreach (['super-admin', 'owner', 'admin-toko'] as $role) {
                DB::table('approval_rules')->updateOrInsert(
                    ['entity_type' => 'selisih_kas', 'approver_role' => $role, 'level' => 1],
                    [
                        'cabang_id' => null,
                        'min_amount' => 0.01,
                        'max_amount' => 999999999,
                        'is_aktif' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        // Revert status enum (MySQL only)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE kas_sesi MODIFY COLUMN status ENUM('buka','tutup') NOT NULL DEFAULT 'buka'");
        }

        Schema::table('kas_sesi', function (Blueprint $table) {
            $table->dropColumn(['akun_sumber_kode', 'catatan']);
        });

        // Don't delete COA account (might have journals) — just deactivate
        if (Schema::hasTable('akun_coa')) {
            DB::table('akun_coa')->where('kode', '110-04')->update(['is_active' => false]);
        }

        if (Schema::hasTable('approval_rules')) {
            DB::table('approval_rules')->where('entity_type', 'selisih_kas')->delete();
        }
    }
};
