<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('purchase_order') && ! Schema::hasColumn('purchase_order', 'akun_kas_bank')) {
            Schema::table('purchase_order', function (Blueprint $table) {
                $table->string('akun_kas_bank', 20)->nullable()->after('metode_bayar');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase_order') && Schema::hasColumn('purchase_order', 'akun_kas_bank')) {
            Schema::table('purchase_order', function (Blueprint $table) {
                $table->dropColumn('akun_kas_bank');
            });
        }
    }
};
