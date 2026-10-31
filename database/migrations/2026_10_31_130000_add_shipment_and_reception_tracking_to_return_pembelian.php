<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('return_pembelian')) {
            Schema::table('return_pembelian', function (Blueprint $table) {
                if (! Schema::hasColumn('return_pembelian', 'ekspedisi')) {
                    $table->string('ekspedisi', 100)->nullable()->after('tanggal_kirim');
                }
                if (! Schema::hasColumn('return_pembelian', 'no_resi')) {
                    $table->string('no_resi', 100)->nullable()->after('ekspedisi');
                }
                if (! Schema::hasColumn('return_pembelian', 'penerima_vendor')) {
                    $table->string('penerima_vendor', 191)->nullable()->after('no_resi');
                }
                if (! Schema::hasColumn('return_pembelian', 'tanggal_terima_vendor')) {
                    $table->dateTime('tanggal_terima_vendor')->nullable()->after('penerima_vendor');
                }
                if (! Schema::hasColumn('return_pembelian', 'unit_pengganti_detail')) {
                    $table->json('unit_pengganti_detail')->nullable()->after('catatan_penyelesaian');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('return_pembelian')) {
            Schema::table('return_pembelian', function (Blueprint $table) {
                $table->dropColumn([
                    'ekspedisi',
                    'no_resi',
                    'penerima_vendor',
                    'tanggal_terima_vendor',
                    'unit_pengganti_detail',
                ]);
            });
        }
    }
};
