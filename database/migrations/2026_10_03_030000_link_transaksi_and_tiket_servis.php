<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            if (! Schema::hasColumn('transaksi', 'tiket_servis_id')) {
                $table->foreignId('tiket_servis_id')
                    ->nullable()
                    ->after('pelanggan_id')
                    ->constrained('tiket_servis')
                    ->nullOnDelete();
            }
        });

        Schema::table('tiket_servis', function (Blueprint $table) {
            if (! Schema::hasColumn('tiket_servis', 'transaksi_id')) {
                $table->foreignId('transaksi_id')
                    ->nullable()
                    ->after('cabang_id')
                    ->constrained('transaksi')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            if (Schema::hasColumn('transaksi', 'tiket_servis_id')) {
                $table->dropForeign(['tiket_servis_id']);
                $table->dropColumn('tiket_servis_id');
            }
        });

        Schema::table('tiket_servis', function (Blueprint $table) {
            if (Schema::hasColumn('tiket_servis', 'transaksi_id')) {
                $table->dropForeign(['transaksi_id']);
                $table->dropColumn('transaksi_id');
            }
        });
    }
};
