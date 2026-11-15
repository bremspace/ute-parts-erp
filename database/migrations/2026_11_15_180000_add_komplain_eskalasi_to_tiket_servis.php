<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eskalasi komplain: tanda sekali saja saat komplain pasca-servis melewati
 * SLA (notifikasi regulator) — mencegah notifikasi berulang tiap menit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tiket_servis', 'komplain_eskalasi_at')) {
            Schema::table('tiket_servis', function (Blueprint $table) {
                $table->timestamp('komplain_eskalasi_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tiket_servis', 'komplain_eskalasi_at')) {
            Schema::table('tiket_servis', function (Blueprint $table) {
                $table->dropColumn('komplain_eskalasi_at');
            });
        }
    }
};
