<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [F-20] Servis mengenakan PPN keluaran seperti POS cabang yang sama.
 * DPP/PPN disimpan per tiket saat selesai (satu kali, tidak retroaktif —
 * perubahan konfigurasi pajak hanya berlaku untuk transaksi baru).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tiket_servis')) {
            Schema::table('tiket_servis', function (Blueprint $table) {
                if (! Schema::hasColumn('tiket_servis', 'dpp')) {
                    $table->decimal('dpp', 15, 2)->nullable()->after('estimasi_biaya');
                }
                if (! Schema::hasColumn('tiket_servis', 'ppn_nominal')) {
                    $table->decimal('ppn_nominal', 15, 2)->default(0)->after('dpp');
                }
                if (! Schema::hasColumn('tiket_servis', 'ppn_percent')) {
                    $table->decimal('ppn_percent', 5, 2)->default(0)->after('ppn_nominal');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tiket_servis')) {
            Schema::table('tiket_servis', function (Blueprint $table) {
                foreach (['dpp', 'ppn_nominal', 'ppn_percent'] as $col) {
                    if (Schema::hasColumn('tiket_servis', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
