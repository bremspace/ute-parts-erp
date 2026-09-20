<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // [T-10] Supplier + PurchaseOrder (PO)
        Schema::create('supplier', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama');
            $table->string('kontak')->nullable();
            $table->string('telepon')->nullable();
            $table->text('alamat')->nullable();
            $table->integer('termin_hari')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('purchase_order', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('no_po')->unique();
            $table->foreignId('supplier_id')->constrained('supplier')->cascadeOnDelete();
            $table->foreignId('gudang_tujuan_id')->constrained('gudang')->cascadeOnDelete();
            $table->enum('status', ['draft', 'dikirim', 'diterima', 'dibatalkan'])->default('draft');
            $table->enum('metode_bayar', ['tunai', 'kredit'])->default('kredit');
            $table->date('jatuh_tempo')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('total_dibayar', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
        });

        Schema::create('purchase_order_item', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('purchase_order_id')->constrained('purchase_order')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('sku_variant_id')->nullable()->constrained('sku_variants')->nullOnDelete();
            $table->decimal('harga_beli', 15, 2);
            $table->integer('jumlah');
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();

            $table->index(['purchase_order_id']);
        });

        Schema::create('pembayaran_supplier', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('po_id')->constrained('purchase_order')->cascadeOnDelete();
            $table->decimal('jumlah', 15, 2);
            $table->timestamp('dibayar_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->index(['po_id']);
        });

        // [T-11] Master produk: satuan referensi + field baru
        Schema::create('satuan_unit', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode')->unique(); // pcs, box, unit
            $table->string('nama');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('produk', function (Blueprint $table) {
            $table->string('barcode')->nullable()->unique()->after('slug');
            $table->json('foto')->nullable()->after('gambar');
            $table->string('meta_title')->nullable()->after('foto');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->json('kompatibilitas_hp')->nullable()->after('meta_description'); // terstruktur [{merk, model}]
        });

        Schema::table('sku_variants', function (Blueprint $table) {
            $table->string('satuan_kode', 20)->nullable()->after('nama_varian'); // ref satuan_unit.kode
            $table->string('barcode')->nullable()->unique()->after('sku');
        });

        // [T-12] Rak (bin) per gudang
        Schema::create('rak', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('gudang_id')->constrained('gudang')->cascadeOnDelete();
            $table->string('nama');
            $table->string('kode')->unique();
            $table->string('zona')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['gudang_id']);
        });

        Schema::table('stok_items', function (Blueprint $table) {
            $table->foreignId('rak_id')->nullable()->after('gudang_id')->constrained('rak')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stok_items', fn (Blueprint $t) => $t->dropConstrainedForeignId('rak_id'));
        Schema::dropIfExists('rak');
        Schema::table('sku_variants', function (Blueprint $t) {
            $t->dropColumn(['satuan_kode', 'barcode']);
        });
        Schema::table('produk', fn (Blueprint $t) => $t->dropColumn(['barcode', 'foto', 'meta_title', 'meta_description', 'kompatibilitas_hp']));
        Schema::dropIfExists('satuan_unit');
        Schema::dropIfExists('pembayaran_supplier');
        Schema::dropIfExists('purchase_order_item');
        Schema::dropIfExists('purchase_order');
        Schema::dropIfExists('supplier');
    }
};
