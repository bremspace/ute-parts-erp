<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sid_import_map', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode_sumber', 100);
            $table->string('tabel_sumber', 50);
            $table->string('entity_type', 50); // produk, sku_variant, pelanggan, supplier, gudang, tiket_servis, transaksi, stok_log
            $table->unsignedBigInteger('entity_id');
            $table->timestamps();

            $table->unique(['kode_sumber', 'tabel_sumber'], 'sid_import_map_unique');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sid_import_map');
    }
};
