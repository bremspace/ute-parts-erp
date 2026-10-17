<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill audience notifikasi (staf backoffice vs konsumen pelanggan).
     * Irreversible — data backfill, bukan struktur schema.
     */
    public function up(): void
    {
        // Baris staf yang salah terisi pelanggan_id (auto auth('customer')) → jadikan murni staf
        DB::table('notifikasi_keluar')
            ->where('action_url', 'like', '/app%')
            ->whereNotNull('pelanggan_id')
            ->update(['pelanggan_id' => null]);

        // Baris konsumen yang salah terisi target_role/user_id → bersihkan
        DB::table('notifikasi_keluar')
            ->whereNotNull('pelanggan_id')
            ->update(['target_role' => null, 'user_id' => null]);
    }

    /**
     * Irreversible — data backfill (tidak ada struktur schema yang diubah).
     */
    public function down(): void
    {
        //
    }
};
