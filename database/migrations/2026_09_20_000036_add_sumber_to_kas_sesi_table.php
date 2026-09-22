<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * [T-33] Lacak sumber saldo awal sesi kas: manual | legacy | carryover.
     */
    public function up(): void
    {
        Schema::table('kas_sesi', function (Blueprint $table) {
            $table->string('sumber', 20)->default('manual')->after('saldo_awal');
        });
    }

    public function down(): void
    {
        Schema::table('kas_sesi', function (Blueprint $table) {
            $table->dropColumn('sumber');
        });
    }
};
