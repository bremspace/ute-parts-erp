<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [C3] Kontrak satuan/HPP retur pembelian (mirror POS-native & C2):
 * jumlah item retur dalam SATUAN PO; konversi = faktor ke base unit;
 * harga_grn = biaya GRN aktual per satuan (kredit persediaan at biaya aktual).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_pembelian_item', function (Blueprint $table) {
            if (! Schema::hasColumn('return_pembelian_item', 'satuan')) {
                $table->string('satuan', 50)->default('pcs')->after('sku_variant_id');
            }
            if (! Schema::hasColumn('return_pembelian_item', 'konversi')) {
                $table->decimal('konversi', 12, 4)->default(1)->after('satuan');
            }
            if (! Schema::hasColumn('return_pembelian_item', 'harga_grn')) {
                $table->decimal('harga_grn', 15, 2)->nullable()->after('harga_beli');
            }
        });
    }

    public function down(): void
    {
        Schema::table('return_pembelian_item', function (Blueprint $table) {
            $table->dropColumn(['satuan', 'konversi', 'harga_grn']);
        });
    }
};
