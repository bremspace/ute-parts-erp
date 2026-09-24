<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * [F3-8c] Identitas agen & referral code (PRD §4.3):
     * - kode_agen: kode unik milik agen (pelanggan berperan agen).
     * - referral_kode: kode agen yang dipakai pembeli (boleh banyak pembeli memakai kode sama).
     */
    public function up(): void
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->string('kode_agen', 40)->nullable()->unique()->after('is_reseller');
            $table->string('referral_kode', 40)->nullable()->index()->after('kode_agen');
        });
    }

    public function down(): void
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->dropColumn(['kode_agen', 'referral_kode']);
        });
    }
};
