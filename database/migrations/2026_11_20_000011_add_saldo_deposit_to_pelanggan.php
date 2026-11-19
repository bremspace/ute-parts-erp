<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [H2] Saldo deposit pelanggan (subledger) untuk refund retur metode 'saldo'.
 * GL: kredit 210-07 Deposit Pelanggan; subledger: pelanggan.saldo_deposit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pelanggan', 'saldo_deposit')) {
            Schema::table('pelanggan', function (Blueprint $table) {
                $table->decimal('saldo_deposit', 15, 2)->default(0)->after('saldo_piutang');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pelanggan', 'saldo_deposit')) {
            Schema::table('pelanggan', function (Blueprint $table) {
                $table->dropColumn('saldo_deposit');
            });
        }
    }
};
