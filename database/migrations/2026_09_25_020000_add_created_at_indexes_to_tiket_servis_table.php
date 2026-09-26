<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [B-07] Index ORDER BY `created_at` untuk daftar tiket servis.
 *
 * Root cause error 1038 "Out of sort memory": MySQL mem-packing seluruh baris
 * yang akan di-sort ke dalam `sort_buffer_size` (default 256KB), sedangkan
 * `tiket_servis.foto_unit` menyimpan base64 foto (terukur 431.406 byte / baris
 * di db_staging) → satu baris saja sudah melebihi sort buffer.
 *
 * Tanpa index, `ORDER BY created_at DESC LIMIT …` wajib filesort (EXPLAIN
 * `Using filesort`, `type: ALL`) → 1038. Dengan index, optimizer bisa
 * membaca lewat index (backward index scan, `Using index`) tanpa filesort.
 *
 * Dua index:
 * - `tiket_servis(created_at)`            — query global (papan kanban
 *   ServisBoard & SERVICE-02 tanpa cabang di session).
 * - `tiket_servis(cabang_id, created_at)` — query scoped cabang
 *   (SERVICE-02 `WHERE cabang_id = ? ORDER BY created_at DESC`).
 *
 * Idempotent — guard `Schema::hasIndex` (MySQL tidak auto-dedupe index).
 * Catatan: fix aplikasi B-07 (urutkan kolom sempit `id` dulu, lalu hydrate)
 * sudah menyelesaikan error tanpa menunggu index; index = mitigasi biaya
 * sort (covering index scan) utk query-kolom-sempit & query list lainnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tiket_servis')) {
            return;
        }

        if (! Schema::hasIndex('tiket_servis', 'tiket_servis_created_at_idx')) {
            Schema::table('tiket_servis', function (Blueprint $table) {
                $table->index('created_at', 'tiket_servis_created_at_idx');
            });
        }

        if (! Schema::hasIndex('tiket_servis', 'tiket_servis_cabang_id_created_at_idx')) {
            Schema::table('tiket_servis', function (Blueprint $table) {
                $table->index(['cabang_id', 'created_at'], 'tiket_servis_cabang_id_created_at_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tiket_servis')) {
            return;
        }

        if (Schema::hasIndex('tiket_servis', 'tiket_servis_cabang_id_created_at_idx')) {
            Schema::table('tiket_servis', function (Blueprint $table) {
                $table->dropIndex('tiket_servis_cabang_id_created_at_idx');
            });
        }

        if (Schema::hasIndex('tiket_servis', 'tiket_servis_created_at_idx')) {
            Schema::table('tiket_servis', function (Blueprint $table) {
                $table->dropIndex('tiket_servis_created_at_idx');
            });
        }
    }
};
