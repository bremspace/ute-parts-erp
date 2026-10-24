<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grn', function (Blueprint $table) {
            if (! Schema::hasColumn('grn', 'akun_kas_bank')) {
                $table->string('akun_kas_bank', 20)->nullable()->after('total_hpp');
            }
            if (! Schema::hasColumn('grn', 'dibayar_oleh')) {
                $table->string('dibayar_oleh', 100)->nullable()->after('akun_kas_bank');
            }
        });
    }

    public function down(): void
    {
        Schema::table('grn', function (Blueprint $table) {
            if (Schema::hasColumn('grn', 'dibayar_oleh')) {
                $table->dropColumn('dibayar_oleh');
            }
            if (Schema::hasColumn('grn', 'akun_kas_bank')) {
                $table->dropColumn('akun_kas_bank');
            }
        });
    }
};
