<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_servis', function (Blueprint $table) {
            $table->decimal('biaya_jasa', 12, 2)->default(0)->after('estimasi_durasi');
        });

        Schema::create('tiket_servis_estimasi_item', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('tiket_servis_id')->constrained('tiket_servis')->cascadeOnDelete();
            $table->enum('tipe', ['part', 'jasa'])->default('jasa');
            $table->foreignId('jenis_servis_id')->nullable()->constrained('jenis_servis')->nullOnDelete();
            $table->foreignId('produk_id')->nullable()->constrained('produk')->nullOnDelete();
            $table->foreignId('sku_variant_id')->nullable()->constrained('sku_variants')->nullOnDelete();
            $table->string('nama_item');
            $table->integer('qty')->default(1);
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['tiket_servis_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiket_servis_estimasi_item');
        Schema::table('jenis_servis', function (Blueprint $table) {
            $table->dropColumn('biaya_jasa');
        });
    }
};
