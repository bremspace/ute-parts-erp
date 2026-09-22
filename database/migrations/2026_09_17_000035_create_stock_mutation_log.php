<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // [T-26] Source-of-Truth mutasi stok — semua perubahan StokItem dicatat di sini (berurutan waktu)
        Schema::create('stock_mutation_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('sku_variant_id')->nullable()->constrained('sku_variants')->nullOnDelete();
            $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
            $table->integer('delta'); // + masuk, - keluar
            $table->string('sumber'); // pos | servis | transfer | opname | po | channel:{nama} | manual
            $table->string('referensi_tipe')->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->timestamp('terjadi_at')->nullable();
            $table->timestamps();

            $table->index(['produk_id', 'terjadi_at']);
            $table->index(['gudang_id', 'terjadi_at']);
        });

        // [T-26] Beban biaya admin marketplace (per channel) — laporan margin per channel akurat
        Schema::table('channel_orders', function (Blueprint $table) {
            $table->decimal('estimasi_biaya_platform', 15, 2)->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('channel_orders', function (Blueprint $table) {
            $table->dropColumn(['estimasi_biaya_platform']);
        });
        Schema::dropIfExists('stock_mutation_log');
    }
};
