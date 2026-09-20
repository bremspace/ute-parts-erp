<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Referensi pembayaran Duitku di transaksi marketplace (PRD §4.7)
        Schema::table('transaksi', function (Blueprint $table) {
            $table->string('payment_reference')->nullable()->after('catatan'); // merchantOrderId Duitku
            $table->string('payment_url')->nullable()->after('payment_reference'); // redirect/popup pembayaran
            $table->timestamp('paid_at')->nullable()->after('payment_url');
        });

        // Pengiriman via Biteship (PRD §4.8)
        Schema::create('pengiriman', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('transaksi_id')->constrained('transaksi')->cascadeOnDelete();
            $table->string('kurir');
            $table->string('layanan');
            $table->decimal('ongkir', 15, 2)->default(0);
            $table->string('asal_cabang')->nullable();
            $table->string('nama_penerima')->nullable();
            $table->text('alamat_tujuan')->nullable();
            $table->string('telepon_tujuan')->nullable();
            $table->string('tracking_id')->nullable();
            $table->string('status')->default('pending'); // pending, diproses, dikirim, terkirim, gagal
            $table->timestamps();

            $table->index(['transaksi_id']);
            $table->index(['tracking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengiriman');

        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropColumn(['payment_reference', 'payment_url', 'paid_at']);
        });
    }
};
