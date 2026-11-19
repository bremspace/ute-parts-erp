<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [H1] Porsi PPN keluaran per item retur penjualan (snapshot saat retur dibuat)
 * agar refund membalik PPN (Dr 220-01) secara proporsional, termasuk retur parsial.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('return_penjualan_item', 'ppn_nominal')) {
            Schema::table('return_penjualan_item', function (Blueprint $table) {
                $table->decimal('ppn_nominal', 15, 2)->default(0)->after('subtotal');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('return_penjualan_item', 'ppn_nominal')) {
            Schema::table('return_penjualan_item', function (Blueprint $table) {
                $table->dropColumn('ppn_nominal');
            });
        }
    }
};
