<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Phase 1 / T-01] Composite performance indexes for high-volume ledger and transaction tables.
 *
 * 1. transaksi(cabang_id, created_at) — eliminates filesort on POS history and financial date-filtered reports.
 * 2. stok_log(produk_id, created_at) — provides covering prefix for single-SKU audit trails without requiring gudang_id.
 * 3. jurnal_akuntansi(referensi_tipe, referensi_id) — optimizes subledger and reverse journal lookups across modules.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transaksi')) {
            if (! Schema::hasIndex('transaksi', 'transaksi_cabang_id_created_at_idx')) {
                Schema::table('transaksi', function (Blueprint $table) {
                    $table->index(['cabang_id', 'created_at'], 'transaksi_cabang_id_created_at_idx');
                });
            }
        }

        if (Schema::hasTable('stok_log')) {
            if (! Schema::hasIndex('stok_log', 'stok_log_produk_id_created_at_idx')) {
                Schema::table('stok_log', function (Blueprint $table) {
                    $table->index(['produk_id', 'created_at'], 'stok_log_produk_id_created_at_idx');
                });
            }
        }

        if (Schema::hasTable('jurnal_akuntansi')) {
            if (! Schema::hasIndex('jurnal_akuntansi', 'jurnal_akuntansi_referensi_tipe_id_idx')) {
                Schema::table('jurnal_akuntansi', function (Blueprint $table) {
                    $table->index(['referensi_tipe', 'referensi_id'], 'jurnal_akuntansi_referensi_tipe_id_idx');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transaksi')) {
            if (Schema::hasIndex('transaksi', 'transaksi_cabang_id_created_at_idx')) {
                Schema::table('transaksi', function (Blueprint $table) {
                    $table->dropIndex('transaksi_cabang_id_created_at_idx');
                });
            }
        }

        if (Schema::hasTable('stok_log')) {
            if (Schema::hasIndex('stok_log', 'stok_log_produk_id_created_at_idx')) {
                Schema::table('stok_log', function (Blueprint $table) {
                    $table->dropIndex('stok_log_produk_id_created_at_idx');
                });
            }
        }

        if (Schema::hasTable('jurnal_akuntansi')) {
            if (Schema::hasIndex('jurnal_akuntansi', 'jurnal_akuntansi_referensi_tipe_id_idx')) {
                Schema::table('jurnal_akuntansi', function (Blueprint $table) {
                    $table->dropIndex('jurnal_akuntansi_referensi_tipe_id_idx');
                });
            }
        }
    }
};
