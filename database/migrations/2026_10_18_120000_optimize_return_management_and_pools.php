<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom pada return_penjualan
        if (Schema::hasTable('return_penjualan')) {
            Schema::table('return_penjualan', function (Blueprint $table) {
                if (! Schema::hasColumn('return_penjualan', 'tipe_penyelesaian')) {
                    $table->string('tipe_penyelesaian', 20)->default('refund')->after('metode_pengembalian'); // refund / tukar_produk
                }
                if (! Schema::hasColumn('return_penjualan', 'tukar_transaksi_id')) {
                    $table->unsignedBigInteger('tukar_transaksi_id')->nullable()->after('tipe_penyelesaian');
                }
                if (! Schema::hasColumn('return_penjualan', 'selisih_nominal')) {
                    $table->decimal('selisih_nominal', 15, 2)->default(0)->after('tukar_transaksi_id');
                }
                if (! Schema::hasColumn('return_penjualan', 'foto_bukti')) {
                    $table->json('foto_bukti')->nullable()->after('selisih_nominal');
                }
                if (! Schema::hasColumn('return_penjualan', 'catatan_qc')) {
                    $table->text('catatan_qc')->nullable()->after('foto_bukti');
                }
                if (! Schema::hasColumn('return_penjualan', 'channel')) {
                    $table->string('channel', 30)->default('pos')->after('catatan_qc'); // pos, web, shopee, dll
                }
            });
        }

        // 2. Tambah kolom pada return_penjualan_item
        if (Schema::hasTable('return_penjualan_item')) {
            Schema::table('return_penjualan_item', function (Blueprint $table) {
                if (! Schema::hasColumn('return_penjualan_item', 'kondisi_barang')) {
                    $table->string('kondisi_barang', 20)->default('rusak')->after('hpp'); // bagus, rusak
                }
                if (! Schema::hasColumn('return_penjualan_item', 'nomor_seri')) {
                    $table->json('nomor_seri')->nullable()->after('kondisi_barang');
                }
                if (! Schema::hasColumn('return_penjualan_item', 'supplier_id')) {
                    $table->unsignedBigInteger('supplier_id')->nullable()->after('nomor_seri');
                }
                if (! Schema::hasColumn('return_penjualan_item', 'status_karantina')) {
                    $table->string('status_karantina', 30)->default('karantina')->after('supplier_id'); // siap_jual, karantina, sudah_diretur_supplier
                }
                if (! Schema::hasColumn('return_penjualan_item', 'return_pembelian_id')) {
                    $table->unsignedBigInteger('return_pembelian_id')->nullable()->after('status_karantina');
                }
            });
        }

        // 3. Tambah kolom saldo_deposit pada tabel supplier
        if (Schema::hasTable('supplier')) {
            Schema::table('supplier', function (Blueprint $table) {
                if (! Schema::hasColumn('supplier', 'saldo_deposit')) {
                    $table->decimal('saldo_deposit', 15, 2)->default(0)->after('termin_hari');
                }
            });
        }

        // 4. Update tabel return_pembelian agar purchase_order_id nullable
        if (Schema::hasTable('return_pembelian')) {
            Schema::table('return_pembelian', function (Blueprint $table) {
                // Pastikan tipe kolom atau nullable purchase_order_id
                $table->unsignedBigInteger('purchase_order_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('return_penjualan')) {
            Schema::table('return_penjualan', function (Blueprint $table) {
                $table->dropColumn([
                    'tipe_penyelesaian',
                    'tukar_transaksi_id',
                    'selisih_nominal',
                    'foto_bukti',
                    'catatan_qc',
                    'channel',
                ]);
            });
        }

        if (Schema::hasTable('return_penjualan_item')) {
            Schema::table('return_penjualan_item', function (Blueprint $table) {
                $table->dropColumn([
                    'kondisi_barang',
                    'nomor_seri',
                    'supplier_id',
                    'status_karantina',
                    'return_pembelian_id',
                ]);
            });
        }

        if (Schema::hasTable('supplier')) {
            Schema::table('supplier', function (Blueprint $table) {
                $table->dropColumn('saldo_deposit');
            });
        }
    }
};
