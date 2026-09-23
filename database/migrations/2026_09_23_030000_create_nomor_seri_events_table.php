<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [P1-6] nomor_seri_events — riwayat append-only per SN.
 *
 * klaimJual/klaimServis menimpa transaksi_item_id / tiket_servis_id (+item);
 * sebelum timpa, link LAMA di-snapshot ke sini agar rantai permanen
 * (2 kunjungan servis / resale / return) tidak hilang. Kolom status terkini
 * di nomor_seri_produk tetap dipertahankan apa adanya (semantik status).
 * Idempotent — guard hasTable. Tanpa FK (pola migrasi F2-3, SQLite aman).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nomor_seri_events')) {
            return;
        }

        Schema::create('nomor_seri_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nomor_seri_id')->index();
            $table->unsignedBigInteger('cabang_id')->nullable()->index();
            $table->string('aksi', 20);           // klaim_jual | klaim_servis
            $table->string('status_sebelum', 30); // status SN sebelum overwrite
            $table->unsignedBigInteger('transaksi_item_id')->nullable()->index();
            $table->unsignedBigInteger('tiket_servis_id')->nullable()->index();
            $table->unsignedBigInteger('tiket_servis_item_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomor_seri_events');
    }
};
