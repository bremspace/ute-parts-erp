<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_product_mapping', function (Blueprint $table) {
            $table->foreignId('sku_variant_id')->nullable()->after('produk_id')->constrained('sku_variants')->nullOnDelete();
            $table->string('channel_model_id')->nullable()->after('channel_item_id'); // Shopee model_id untuk varian produk

            $table->index(['channel_id', 'channel_item_id', 'channel_model_id'], 'cpm_channel_item_model_idx');
        });
    }

    public function down(): void
    {
        Schema::table('channel_product_mapping', function (Blueprint $table) {
            $table->dropIndex('cpm_channel_item_model_idx');
            $table->dropForeign(['sku_variant_id']);
            $table->dropColumn(['sku_variant_id', 'channel_model_id']);
        });
    }
};
