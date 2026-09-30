<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('return_penjualan')) {
            Schema::table('return_penjualan', function (Blueprint $table) {
                if (! Schema::hasColumn('return_penjualan', 'cabang_id')) {
                    $table->unsignedBigInteger('cabang_id')->nullable()->after('pelanggan_id');
                }
                if (! Schema::hasColumn('return_penjualan', 'gudang_id')) {
                    $table->unsignedBigInteger('gudang_id')->nullable()->after('cabang_id');
                }
                if (! Schema::hasColumn('return_penjualan', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('gudang_id');
                }
                if (! Schema::hasColumn('return_penjualan', 'metode_pengembalian')) {
                    $table->string('metode_pengembalian', 20)->default('kas')->after('status'); // kas / piutang / saldo
                }
            });
        }

        if (Schema::hasTable('return_pembelian')) {
            Schema::table('return_pembelian', function (Blueprint $table) {
                if (! Schema::hasColumn('return_pembelian', 'cabang_id')) {
                    $table->unsignedBigInteger('cabang_id')->nullable()->after('supplier_id');
                }
                if (! Schema::hasColumn('return_pembelian', 'gudang_id')) {
                    $table->unsignedBigInteger('gudang_id')->nullable()->after('cabang_id');
                }
                if (! Schema::hasColumn('return_pembelian', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('gudang_id');
                }
                if (! Schema::hasColumn('return_pembelian', 'metode_pengembalian')) {
                    $table->string('metode_pengembalian', 20)->default('utang')->after('status'); // utang / kas
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('return_penjualan')) {
            Schema::table('return_penjualan', function (Blueprint $table) {
                $table->dropColumn(['cabang_id', 'gudang_id', 'user_id', 'metode_pengembalian']);
            });
        }

        if (Schema::hasTable('return_pembelian')) {
            Schema::table('return_pembelian', function (Blueprint $table) {
                $table->dropColumn(['cabang_id', 'gudang_id', 'user_id', 'metode_pengembalian']);
            });
        }
    }
};
