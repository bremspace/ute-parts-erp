<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [T-03] Penanda baris part servis yang sudah dibatalkan (pembalikan stok).
 *
 * Latar belakang (P0-3): saat tiket `ditolak`, stok part yang sudah terpotong
 * TIDAK pernah dikembalikan — hanya SN yang dilepas. Saat tiket ditolak lalu
 * di-estimasi ulang (`ditolak → diagnosa`, transisi forward yang sah), part lama
 * tetap ikut terpotong sehingga HPP 510-02 / Persediaan 130-01 bisa dobel-credit
 * di jurnal `onSelesai`.
 *
 * `dibatalkan_at` (nullable timestamp) dipakai 3 hal sekaligus:
 *  1. reversal stok saat transisi → `ditolak` (baris lama dikembalikan persis
 *     ke gudang asalnya, dicatat StokLog pembalik);
 *  2. guard anti-dobel dua jalur input (TiketServisItem vs ServisSparepart)
 *     hanya menghitung baris AKTIF;
 *  3. perhitungan jurnal `onSelesai` mengecualikan baris terbatalkan
 *     (mencegah HPP dobel).
 *
 * Kolom sengaja NULL = aktif (bukan boolean) supaya "belum pernah dibatalkan"
 * tidak perlu backfill dan baris lama tetap berlaku.
 *
 * DITERAPKAN DI DUA TABEL: `tiket_servis_item` (jalur form pekerjaan T-17) dan
 * `servis_sparepart` (jalur legacy) — keduanya bisa menyimpan baris part yang
 * perlu dibalik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiket_servis_item', function (Blueprint $table) {
            $table->timestamp('dibatalkan_at')->nullable()->after('hpp');
            $table->index(['tiket_servis_id', 'dibatalkan_at'], 'tiket_servis_item_tiket_dibatalkan_idx');
        });

        Schema::table('servis_sparepart', function (Blueprint $table) {
            $table->timestamp('dibatalkan_at')->nullable()->after('hpp');
            $table->index(['tiket_servis_id', 'dibatalkan_at'], 'servis_sparepart_tiket_dibatalkan_idx');
        });
    }

    public function down(): void
    {
        Schema::table('servis_sparepart', function (Blueprint $table) {
            $table->dropIndex('servis_sparepart_tiket_dibatalkan_idx');
            $table->dropColumn('dibatalkan_at');
        });

        Schema::table('tiket_servis_item', function (Blueprint $table) {
            $table->dropIndex('tiket_servis_item_tiket_dibatalkan_idx');
            $table->dropColumn('dibatalkan_at');
        });
    }
};
