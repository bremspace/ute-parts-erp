<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite performance indexes for customer 360 transaction and service ticket histories.
 *
 * 1. tiket_servis(pelanggan_id, created_at) — eliminates filesort/sort memory errors on large JSON/TEXT service records.
 * 2. transaksi(pelanggan_id, created_at) — accelerates CRM transaction history lookups.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tiket_servis')) {
            if (! Schema::hasIndex('tiket_servis', 'tiket_servis_pelanggan_created_idx')) {
                Schema::table('tiket_servis', function (Blueprint $table) {
                    $table->index(['pelanggan_id', 'created_at'], 'tiket_servis_pelanggan_created_idx');
                });
            }
        }

        if (Schema::hasTable('transaksi')) {
            if (! Schema::hasIndex('transaksi', 'transaksi_pelanggan_id_created_at_idx')) {
                Schema::table('transaksi', function (Blueprint $table) {
                    $table->index(['pelanggan_id', 'created_at'], 'transaksi_pelanggan_id_created_at_idx');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tiket_servis')) {
            if (Schema::hasIndex('tiket_servis', 'tiket_servis_pelanggan_created_idx')) {
                Schema::table('tiket_servis', function (Blueprint $table) {
                    $table->dropIndex('tiket_servis_pelanggan_created_idx');
                });
            }
        }

        if (Schema::hasTable('transaksi')) {
            if (Schema::hasIndex('transaksi', 'transaksi_pelanggan_id_created_at_idx')) {
                Schema::table('transaksi', function (Blueprint $table) {
                    $table->dropIndex('transaksi_pelanggan_id_created_at_idx');
                });
            }
        }
    }
};
