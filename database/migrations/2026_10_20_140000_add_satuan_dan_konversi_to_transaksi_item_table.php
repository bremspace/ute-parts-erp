<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_item', function (Blueprint $table) {
            if (! Schema::hasColumn('transaksi_item', 'satuan')) {
                $table->string('satuan', 50)->nullable()->default('pcs')->after('jumlah');
            }
            if (! Schema::hasColumn('transaksi_item', 'konversi')) {
                $table->decimal('konversi', 12, 4)->default(1)->after('satuan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_item', function (Blueprint $table) {
            if (Schema::hasColumn('transaksi_item', 'konversi')) {
                $table->dropColumn('konversi');
            }
            if (Schema::hasColumn('transaksi_item', 'satuan')) {
                $table->dropColumn('satuan');
            }
        });
    }
};
