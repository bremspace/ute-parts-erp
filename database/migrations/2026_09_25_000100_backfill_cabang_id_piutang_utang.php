<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [B-01] Backfill cabang_id NULL pada piutang/utang → cabang default (id terkecil).
 *
 * Baris tanpa cabang_id disembunyikan oleh semua widget dashboard yang scoped
 * (Ringkasan Keuangan, Treasury, Piutang Jatuh Tempo) → angka beda dalam 1 halaman.
 * Sumber NULL: seeder lama (IntegrationSeeder) yang membuat piutang/utang tanpa cabang.
 *
 * IDEMPOTEN: hanya menyentuh baris `cabang_id IS NULL` → aman dijalankan berulang,
 * dan aman saat belum ada cabang (skip, baris dibiarkan NULL sampai ada cabang).
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaultCabangId = DB::table('cabang')->orderBy('id')->value('id');

        if (! $defaultCabangId) {
            return; // belum ada cabang → tidak ada yang bisa di-backfill
        }

        foreach (['piutang', 'utang'] as $tabel) {
            if (! Schema::hasTable($tabel) || ! Schema::hasColumn($tabel, 'cabang_id')) {
                continue;
            }

            DB::table($tabel)
                ->whereNull('cabang_id')
                ->update(['cabang_id' => $defaultCabangId]);
        }
    }

    public function down(): void
    {
        // Backfill data — sengaja tidak di-rollback (kehilangan asal cabang asli).
    }
};
