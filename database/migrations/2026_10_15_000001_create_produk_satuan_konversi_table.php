<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('produk_satuan_konversi')) {
            Schema::create('produk_satuan_konversi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
                $table->string('satuan_kode', 50);
                $table->string('satuan_asal', 50)->default('pcs');
                $table->decimal('pengali', 12, 4)->default(1);
                $table->decimal('faktor_konversi', 12, 4)->default(1);
                $table->decimal('harga_beli', 15, 2)->nullable();
                $table->decimal('harga_jual', 15, 2)->nullable();
                $table->string('barcode', 50)->nullable();
                $table->boolean('is_satuan_beli')->default(false);
                $table->boolean('is_satuan_jual')->default(false);
                $table->integer('urutan')->default(1);
                $table->timestamps();

                $table->index(['produk_id', 'satuan_kode']);
            });
        }

        Schema::table('purchase_order_item', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_order_item', 'satuan')) {
                $table->string('satuan', 50)->nullable()->after('jumlah');
            }
            if (! Schema::hasColumn('purchase_order_item', 'konversi')) {
                $table->decimal('konversi', 12, 4)->default(1)->after('satuan');
            }
            if (! Schema::hasColumn('purchase_order_item', 'jumlah_dasar')) {
                $table->decimal('jumlah_dasar', 15, 2)->default(0)->after('konversi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_item', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_item', 'jumlah_dasar')) {
                $table->dropColumn('jumlah_dasar');
            }
            if (Schema::hasColumn('purchase_order_item', 'konversi')) {
                $table->dropColumn('konversi');
            }
            if (Schema::hasColumn('purchase_order_item', 'satuan')) {
                $table->dropColumn('satuan');
            }
        });

        Schema::dropIfExists('produk_satuan_konversi');
    }
};
