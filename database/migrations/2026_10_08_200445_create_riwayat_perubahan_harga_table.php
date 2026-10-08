<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_perubahan_harga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->foreignId('cabang_id')->nullable()->constrained('cabang')->nullOnDelete();
            $table->string('sumber', 50)->default('grn'); // grn, po, manual
            $table->nullableMorphs('referensi'); // e.g. Grn, PurchaseOrder
            $table->decimal('harga_lama', 15, 2);
            $table->decimal('harga_baru', 15, 2);
            $table->decimal('selisih', 15, 2);
            $table->decimal('persentase_perubahan', 8, 2);
            $table->text('catatan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['produk_id', 'created_at']);
            $table->index(['cabang_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_perubahan_harga');
    }
};
