<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index pencarian part servis: filter `is_active` + lookup `barcode`.
 *
 * CATATAN: `produk.nama` bertipe TEXT di produksi, jadi index btree pada kolom
 * itu gagal (MySQL 1170 "BLOB/TEXT column used in key specification"). Index
 * `produk_nama_idx` yang sudah ada dipertahankan; `barcode` sudah punya unique
 * index sendiri. Migration ini hanya menambahkan index `is_active`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('produk', 'produk_is_active_index')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->index('is_active', 'produk_is_active_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('produk', 'produk_is_active_index')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->dropIndex('produk_is_active_index');
            });
        }
    }
};
