<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transaksi')) {
            Schema::table('transaksi', function (Blueprint $table) {
                if (! Schema::hasColumn('transaksi', 'diskon_manual_authorized_by_id')) {
                    $table->foreignId('diskon_manual_authorized_by_id')
                        ->nullable()
                        ->after('diskon_nominal')
                        ->constrained('users')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('transaksi', 'diskon_manual_alasan')) {
                    $table->string('diskon_manual_alasan', 255)
                        ->nullable()
                        ->after('diskon_manual_authorized_by_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transaksi')) {
            Schema::table('transaksi', function (Blueprint $table) {
                if (Schema::hasColumn('transaksi', 'diskon_manual_authorized_by_id')) {
                    $table->dropForeign(['diskon_manual_authorized_by_id']);
                    $table->dropColumn('diskon_manual_authorized_by_id');
                }
                if (Schema::hasColumn('transaksi', 'diskon_manual_alasan')) {
                    $table->dropColumn('diskon_manual_alasan');
                }
            });
        }
    }
};
