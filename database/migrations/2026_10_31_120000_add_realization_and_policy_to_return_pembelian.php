<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kebijakan retur pada tabel supplier
        if (Schema::hasTable('supplier')) {
            Schema::table('supplier', function (Blueprint $table) {
                if (! Schema::hasColumn('supplier', 'kebijakan_retur')) {
                    $table->string('kebijakan_retur', 50)->default('faktur_asli')->after('catatan');
                }
            });
        }

        // 2. Tambah nilai_realisasi dan selisih_klaim pada return_pembelian
        if (Schema::hasTable('return_pembelian')) {
            Schema::table('return_pembelian', function (Blueprint $table) {
                if (! Schema::hasColumn('return_pembelian', 'nilai_realisasi')) {
                    $table->decimal('nilai_realisasi', 15, 2)->nullable()->after('jumlah');
                }
                if (! Schema::hasColumn('return_pembelian', 'selisih_klaim')) {
                    $table->decimal('selisih_klaim', 15, 2)->default(0)->after('nilai_realisasi');
                }
                if (! Schema::hasColumn('return_pembelian', 'kebijakan_retur')) {
                    $table->string('kebijakan_retur', 50)->nullable()->after('metode_pengembalian');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('return_pembelian')) {
            Schema::table('return_pembelian', function (Blueprint $table) {
                $table->dropColumn(['nilai_realisasi', 'selisih_klaim', 'kebijakan_retur']);
            });
        }

        if (Schema::hasTable('supplier')) {
            Schema::table('supplier', function (Blueprint $table) {
                $table->dropColumn(['kebijakan_retur']);
            });
        }
    }
};
