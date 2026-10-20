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
                if (! Schema::hasColumn('transaksi', 'void_by_id')) {
                    $table->foreignId('void_by_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('transaksi', 'void_at')) {
                    $table->timestamp('void_at')->nullable()->after('void_by_id');
                }
                if (! Schema::hasColumn('transaksi', 'void_alasan')) {
                    $table->text('void_alasan')->nullable()->after('void_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transaksi')) {
            Schema::table('transaksi', function (Blueprint $table) {
                if (Schema::hasColumn('transaksi', 'void_alasan')) {
                    $table->dropColumn('void_alasan');
                }
                if (Schema::hasColumn('transaksi', 'void_at')) {
                    $table->dropColumn('void_at');
                }
                if (Schema::hasColumn('transaksi', 'void_by_id')) {
                    $table->dropForeign(['void_by_id']);
                    $table->dropColumn('void_by_id');
                }
            });
        }
    }
};
