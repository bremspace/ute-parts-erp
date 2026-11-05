<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'kategori_produk',
            'brands',
            'kualitas_produk',
            'satuan_unit',
            'tipe_hp',
            'jenis_servis',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'is_default')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->boolean('is_default')->default(false)->after('is_active');
                });
            }
        }

        // Tandai record default bawaan sistem yang sudah ada
        if (Schema::hasTable('kategori_produk')) {
            DB::table('kategori_produk')->where('slug', 'umum')->update(['is_default' => true]);
        }

        if (Schema::hasTable('brands')) {
            DB::table('brands')->where('nama', 'Tanpa Merek')->update(['is_default' => true]);
        }

        if (Schema::hasTable('kualitas_produk')) {
            DB::table('kualitas_produk')->where('nama', 'Standar')->whereNull('brand_id')->update(['is_default' => true]);
        }

        if (Schema::hasTable('satuan_unit')) {
            DB::table('satuan_unit')->where('kode', 'pcs')->update(['is_default' => true]);
        }

        if (Schema::hasTable('tipe_hp')) {
            DB::table('tipe_hp')->where('merk', 'Universal')->where('model', 'Semua Tipe HP')->update(['is_default' => true]);
        }

        if (Schema::hasTable('jenis_servis')) {
            DB::table('jenis_servis')->where('kode', 'SVC-UMUM')->update(['is_default' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'kategori_produk',
            'brands',
            'kualitas_produk',
            'satuan_unit',
            'tipe_hp',
            'jenis_servis',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'is_default')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('is_default');
                });
            }
        }
    }
};
