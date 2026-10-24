<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('transaksi')) {
            Schema::table('transaksi', function (Blueprint $table) {
                if (! Schema::hasColumn('transaksi', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('status')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('transaksi')) {
            Schema::table('transaksi', function (Blueprint $table) {
                if (Schema::hasColumn('transaksi', 'expires_at')) {
                    $table->dropIndex(['expires_at']);
                    $table->dropColumn('expires_at');
                }
            });
        }
    }
};
