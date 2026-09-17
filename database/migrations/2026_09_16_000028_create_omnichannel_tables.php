<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Channel marketplace eksternal (PRD §4.10)
        Schema::create('channels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama');                       // Ute Parts Official — Shopee
            $table->string('platform');                  // shopee, tokopedia, blibli, tiktok, lazada
            $table->string('status')->default('belum_terhubung'); // belum_terhubung, terhubung, token_bermasalah
            $table->json('kredensial')->nullable();      // OAuth tokens terenkripsi (partner_id, access_token, refresh_token, expired_at)
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_status')->nullable(); // sukses, error
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['platform', 'status']);
        });

        // Pemetaan produk lokal ↔ SKU channel
        Schema::create('channel_product_mapping', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('gudang_id')->nullable()->constrained('gudang')->nullOnDelete();
            $table->string('channel_sku')->nullable();   // SKU di channel (dipakai auto-match)
            $table->string('channel_item_id')->nullable();
            $table->string('status')->default('belum_dipetakan'); // belum_dipetakan, tersinkron, error
            $table->string('error_message')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'produk_id']);
        });

        // Order dari channel eksternal
        Schema::create('channel_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->string('channel_order_id');          // order_id di marketplace
            $table->foreignId('transaksi_id')->nullable()->constrained('transaksi')->nullOnDelete();
            $table->json('payload')->nullable();         // raw data order dari channel
            $table->string('channel_status')->nullable();
            $table->string('status')->default('menunggu_proses'); // menunggu_proses, diproses, selesai, gagal
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'channel_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_orders');
        Schema::dropIfExists('channel_product_mapping');
        Schema::dropIfExists('channels');
    }
};