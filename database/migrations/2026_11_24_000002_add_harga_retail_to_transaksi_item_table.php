<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot harga retail per SATUAN DASAR pada item transaksi.
 *
 * Dipakai rincian pesanan marketplace untuk menampilkan harga coret sebelum
 * potongan kemasan/per box maupun potongan member. Nullable agar baris lama
 * tetap valid (fallback ke harga_jual_retail produk saat ini).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_item', function (Blueprint $table) {
            if (! Schema::hasColumn('transaksi_item', 'harga_retail')) {
                $table->decimal('harga_retail', 15, 2)->nullable()->after('harga_satuan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_item', function (Blueprint $table) {
            if (Schema::hasColumn('transaksi_item', 'harga_retail')) {
                $table->dropColumn('harga_retail');
            }
        });
    }
};
