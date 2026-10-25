<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pelanggan') && ! Schema::hasColumn('pelanggan', 'mode_reward_mitra')) {
            Schema::table('pelanggan', function (Blueprint $table) {
                $table->string('mode_reward_mitra', 30)->nullable()->after('tipe_konsumen')
                    ->comment('Mode reward: diskon_langsung | komisi_akumulasi | null (ikut global)');
            });
        }

        if (Schema::hasTable('transaksi')) {
            Schema::table('transaksi', function (Blueprint $table) {
                if (! Schema::hasColumn('transaksi', 'diskon_poin')) {
                    $table->decimal('diskon_poin', 12, 2)->default(0)->after('diskon_nominal');
                }
                if (! Schema::hasColumn('transaksi', 'poin_ditukar')) {
                    $table->integer('poin_ditukar')->default(0)->after('diskon_poin');
                }
                if (! Schema::hasColumn('transaksi', 'diskon_partai')) {
                    $table->decimal('diskon_partai', 12, 2)->default(0)->after('poin_ditukar');
                }
                if (! Schema::hasColumn('transaksi', 'mode_reward_mitra')) {
                    $table->string('mode_reward_mitra', 30)->nullable()->after('diskon_partai');
                }
                if (! Schema::hasColumn('transaksi', 'komisi_mitra_nominal')) {
                    $table->decimal('komisi_mitra_nominal', 12, 2)->default(0)->after('mode_reward_mitra');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pelanggan') && Schema::hasColumn('pelanggan', 'mode_reward_mitra')) {
            Schema::table('pelanggan', function (Blueprint $table) {
                $table->dropColumn('mode_reward_mitra');
            });
        }

        if (Schema::hasTable('transaksi')) {
            Schema::table('transaksi', function (Blueprint $table) {
                $cols = array_filter([
                    'diskon_poin', 'poin_ditukar', 'diskon_partai', 'mode_reward_mitra', 'komisi_mitra_nominal',
                ], fn ($c) => Schema::hasColumn('transaksi', $c));
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
