<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [H1] Snapshot harga unit NETO setelah diskon item pada transaksi_item.
 *
 * ReturnPenjualanService menghitung refund dari `harga_final ?? harga_satuan`;
 * tanpa kolom ini refund selalu memakai harga kotor sebelum diskon item
 * (over-refund). Checkout POS kini menulis harga_final = subtotal_item / jumlah.
 * Nullable: baris legacy tetap sah (fallback harga_satuan di sisi retur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_item', function (Blueprint $table) {
            if (! Schema::hasColumn('transaksi_item', 'harga_final')) {
                $table->decimal('harga_final', 15, 2)->nullable()->after('subtotal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_item', function (Blueprint $table) {
            if (Schema::hasColumn('transaksi_item', 'harga_final')) {
                $table->dropColumn('harga_final');
            }
        });
    }
};
