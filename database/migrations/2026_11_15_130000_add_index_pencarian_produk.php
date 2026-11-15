<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk pencarian part servis (modal cari part, POS, WMS): filter
 * `is_active` + `nama`, serta `barcode` (pindai/lookup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            if (! Schema::hasIndex('produk', ['is_active', 'nama'])) {
                $table->index(['is_active', 'nama'], 'produk_is_active_nama_index');
            }

            if (! Schema::hasIndex('produk', ['barcode'])) {
                $table->index('barcode', 'produk_barcode_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropIndex('produk_is_active_nama_index');
            $table->dropIndex('produk_barcode_index');
        });
    }
};
