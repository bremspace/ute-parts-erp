<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [F2-3] Extend tabel nomor_seri_produk (dibuat di migrasi SID fase1):
 * - cabang_id            — scoping cabang aktif (wajib)
 * - tiket_servis_item_id — relasi per-item tiket servis (trace garansi)
 * - index status         — pencarian status tersedia/terjual/servis/garansi
 * Idempotent — guard hasTable/hasColumn. Tanpa FK baru (SQLite ALTER CONSTRAINT
 * tidak didukung; kolom existing sudah cukup utk relasi Eloquent).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomor_seri_produk')) {
            return;
        }

        Schema::table('nomor_seri_produk', function (Blueprint $table) {
            if (! Schema::hasColumn('nomor_seri_produk', 'cabang_id')) {
                $table->unsignedBigInteger('cabang_id')->nullable()->index();
            }
            if (! Schema::hasColumn('nomor_seri_produk', 'tiket_servis_item_id')) {
                $table->unsignedBigInteger('tiket_servis_item_id')->nullable()->index();
            }
        });

        // Index status — guard hasIndex dgn nama eksplisit: MySQL TIDAK auto-dedupe
        // index (duplikat nama → error), re-run setelah partial failure tetap aman.
        if (! Schema::hasIndex('nomor_seri_produk', 'nomor_seri_produk_status_idx')) {
            Schema::table('nomor_seri_produk', function (Blueprint $table) {
                $table->index('status', 'nomor_seri_produk_status_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('nomor_seri_produk')) {
            return;
        }

        // Langkah independen 1: drop index status — diguard hasIndex sendiri,
        // tidak dikpl ke keberadaan kolom cabang_id.
        if (Schema::hasIndex('nomor_seri_produk', 'nomor_seri_produk_status_idx')) {
            Schema::table('nomor_seri_produk', function (Blueprint $table) {
                $table->dropIndex('nomor_seri_produk_status_idx');
            });
        }

        // Langkah independen 2: drop kolom — masing-masing diguard hasColumn.
        Schema::table('nomor_seri_produk', function (Blueprint $table) {
            if (Schema::hasColumn('nomor_seri_produk', 'cabang_id')) {
                $table->dropColumn('cabang_id');
            }
            if (Schema::hasColumn('nomor_seri_produk', 'tiket_servis_item_id')) {
                $table->dropColumn('tiket_servis_item_id');
            }
        });
    }
};
