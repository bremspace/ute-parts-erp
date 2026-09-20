<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * [T-37] Field ulang tahun pelanggan (targeting kampanye via whereMonth/whereDay).
     */
    public function up(): void
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->date('tanggal_lahir')->nullable()->after('alamat');
        });
    }

    public function down(): void
    {
        Schema::table('pelanggan', function (Blueprint $table) {
            $table->dropColumn('tanggal_lahir');
        });
    }
};
