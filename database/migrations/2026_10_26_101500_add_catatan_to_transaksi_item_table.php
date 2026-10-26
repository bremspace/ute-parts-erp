<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_item', function (Blueprint $table) {
            if (! Schema::hasColumn('transaksi_item', 'catatan')) {
                $table->string('catatan', 255)->nullable()->after('subtotal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_item', function (Blueprint $table) {
            if (Schema::hasColumn('transaksi_item', 'catatan')) {
                $table->dropColumn('catatan');
            }
        });
    }
};
