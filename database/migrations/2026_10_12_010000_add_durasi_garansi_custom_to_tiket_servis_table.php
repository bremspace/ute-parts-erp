<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiket_servis', function (Blueprint $table) {
            if (! Schema::hasColumn('tiket_servis', 'durasi_garansi_custom')) {
                $table->integer('durasi_garansi_custom')->nullable()->after('batas_waktu_menit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tiket_servis', function (Blueprint $table) {
            if (Schema::hasColumn('tiket_servis', 'durasi_garansi_custom')) {
                $table->dropColumn('durasi_garansi_custom');
            }
        });
    }
};
