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
        $driver = DB::getDriverName();

        Schema::table('kualitas_produk', function (Blueprint $table) use ($driver) {
            // Drop unique lama jika ada
            if ($driver === 'mysql') {
                try {
                    $table->dropUnique('kualitas_produk_nama_unique');
                } catch (Throwable $e) {
                    // Ignore jika sudah tidak ada
                }
            }

            if (! Schema::hasColumn('kualitas_produk', 'brand_id')) {
                $table->foreignId('brand_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('brands')
                    ->nullOnDelete();
            }

            $table->index(['brand_id', 'is_active'], 'kualitas_brand_active_idx');
            $table->unique(['brand_id', 'nama'], 'kualitas_brand_nama_unique');
        });

        Schema::table('produk', function (Blueprint $table) {
            $table->index(['is_active', 'brand_id', 'kualitas_id'], 'produk_active_brand_kualitas_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropIndex('produk_active_brand_kualitas_idx');
        });

        Schema::table('kualitas_produk', function (Blueprint $table) {
            $table->dropUnique('kualitas_brand_nama_unique');
            $table->dropIndex('kualitas_brand_active_idx');
            $table->dropConstrainedForeignId('brand_id');
            $table->unique('nama', 'kualitas_produk_nama_unique');
        });
    }
};
