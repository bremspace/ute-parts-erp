<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Index untuk tabel produk (pencarian brand & model kompatibel)
        Schema::table('produk', function (Blueprint $table) {
            $table->index(['brand_kompatibel'], 'produk_brand_kompatibel_idx');
            $table->index(['model_kompatibel'], 'produk_model_kompatibel_idx');
        });

        // 2. Index untuk pivot kompatibilitas_produk_tipe_hp (reverse lookup tipe_hp_id -> produk_id)
        if (Schema::hasTable('kompatibilitas_produk_tipe_hp')) {
            Schema::table('kompatibilitas_produk_tipe_hp', function (Blueprint $table) {
                $table->index(['tipe_hp_id'], 'pivot_tipe_hp_id_idx');
            });
        }

        // 3. Index nama pelanggan untuk pencarian cepat di POS & Servis
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->index(['nama'], 'pelanggan_nama_idx');
        });

        // 4. Index aktif pada sku_variants
        Schema::table('sku_variants', function (Blueprint $table) {
            $table->index(['is_active', 'produk_id'], 'sku_variants_active_produk_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropIndex('produk_brand_kompatibel_idx');
            $table->dropIndex('produk_model_kompatibel_idx');
        });

        if (Schema::hasTable('kompatibilitas_produk_tipe_hp')) {
            Schema::table('kompatibilitas_produk_tipe_hp', function (Blueprint $table) {
                $table->dropIndex('pivot_tipe_hp_id_idx');
            });
        }

        Schema::table('pelanggan', function (Blueprint $table) {
            $table->dropIndex('pelanggan_nama_idx');
        });

        Schema::table('sku_variants', function (Blueprint $table) {
            $table->dropIndex('sku_variants_active_produk_idx');
        });
    }
};
