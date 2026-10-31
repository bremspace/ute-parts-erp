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
        // 1. Tambah kolom pada return_penjualan_item untuk mendukung kondisi, alasan, tipe penyelesaian, dan foto per barang
        if (Schema::hasTable('return_penjualan_item')) {
            Schema::table('return_penjualan_item', function (Blueprint $table) {
                if (! Schema::hasColumn('return_penjualan_item', 'alasan')) {
                    $table->string('alasan', 255)->nullable()->after('kondisi_barang');
                }
                if (! Schema::hasColumn('return_penjualan_item', 'tipe_penyelesaian')) {
                    $table->string('tipe_penyelesaian', 20)->default('refund')->after('alasan'); // refund / tukar_produk
                }
                if (! Schema::hasColumn('return_penjualan_item', 'metode_pengembalian')) {
                    $table->string('metode_pengembalian', 20)->default('kas')->after('tipe_penyelesaian'); // kas / piutang / saldo
                }
                if (! Schema::hasColumn('return_penjualan_item', 'tukar_items_payload')) {
                    $table->json('tukar_items_payload')->nullable()->after('metode_pengembalian');
                }
                if (! Schema::hasColumn('return_penjualan_item', 'selisih_nominal')) {
                    $table->decimal('selisih_nominal', 15, 2)->default(0)->after('tukar_items_payload');
                }
                if (! Schema::hasColumn('return_penjualan_item', 'foto_bukti')) {
                    $table->json('foto_bukti')->nullable()->after('selisih_nominal');
                }
            });
        }

        // 2. Tambah kolom pembayaran selisih pada return_penjualan
        if (Schema::hasTable('return_penjualan')) {
            Schema::table('return_penjualan', function (Blueprint $table) {
                if (! Schema::hasColumn('return_penjualan', 'metode_bayar_selisih')) {
                    $table->string('metode_bayar_selisih', 30)->nullable()->after('selisih_nominal'); // tunai, transfer, qris
                }
                if (! Schema::hasColumn('return_penjualan', 'jumlah_bayar_selisih')) {
                    $table->decimal('jumlah_bayar_selisih', 15, 2)->default(0)->after('metode_bayar_selisih');
                }
                if (! Schema::hasColumn('return_penjualan', 'kembalian_selisih')) {
                    $table->decimal('kembalian_selisih', 15, 2)->default(0)->after('jumlah_bayar_selisih');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('return_penjualan_item')) {
            Schema::table('return_penjualan_item', function (Blueprint $table) {
                $cols = ['alasan', 'tipe_penyelesaian', 'metode_pengembalian', 'tukar_items_payload', 'selisih_nominal', 'foto_bukti'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('return_penjualan_item', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('return_penjualan')) {
            Schema::table('return_penjualan', function (Blueprint $table) {
                $cols = ['metode_bayar_selisih', 'jumlah_bayar_selisih', 'kembalian_selisih'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('return_penjualan', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
