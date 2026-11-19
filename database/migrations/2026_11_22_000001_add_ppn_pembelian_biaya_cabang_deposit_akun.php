<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [H8/F-32] Komponen PPN masukan pada PO & GRN (snapshot saat dokumen dibuat,
 * tidak retroaktif): dpp = nilai neto persediaan, ppn_nominal = PPN masukan
 * yang dapat dikreditkan (akun 110-03), ppn_percent = tarif saat transaksi.
 *
 * [H9] Biaya weighted-average per cabang (produk_biaya_cabang) — average
 * per cabang tidak lagi mengkontaminasi master global.
 *
 * [H13] Akun 120-05 Deposit Supplier (aset) agar deposit supplier on-books
 * (Dr 120-05 / Cr 210-01 saat alokasi klaim retur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_order', 'dpp')) {
                $table->decimal('dpp', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('purchase_order', 'ppn_nominal')) {
                $table->decimal('ppn_nominal', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('purchase_order', 'ppn_percent')) {
                $table->decimal('ppn_percent', 5, 2)->default(0);
            }
        });

        Schema::table('grn', function (Blueprint $table) {
            if (! Schema::hasColumn('grn', 'dpp')) {
                $table->decimal('dpp', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('grn', 'ppn_nominal')) {
                $table->decimal('ppn_nominal', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('grn', 'ppn_percent')) {
                $table->decimal('ppn_percent', 5, 2)->default(0);
            }
        });

        if (! Schema::hasTable('produk_biaya_cabang')) {
            Schema::create('produk_biaya_cabang', function (Blueprint $table) {
                $table->id();
                $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
                $table->foreignId('cabang_id')->constrained('cabang')->cascadeOnDelete();
                $table->decimal('harga_beli', 15, 2)->default(0);
                $table->timestamps();
                $table->unique(['produk_id', 'cabang_id']);
            });
        }

        // Akun deposit supplier — idempoten terhadap instalasi yang sudah punya.
        if (Schema::hasTable('akun_coa') && ! DB::table('akun_coa')->where('kode', '120-05')->exists()) {
            DB::table('akun_coa')->insert([
                'kode' => '120-05',
                'nama' => 'Deposit Supplier',
                'tipe' => 'aset',
                'kelompok' => 'deposit',
                'saldo_normal' => 'debit',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('produk_biaya_cabang');

        foreach (['purchase_order', 'grn'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) use ($tabel) {
                foreach (['dpp', 'ppn_nominal', 'ppn_percent'] as $kolom) {
                    if (Schema::hasColumn($tabel, $kolom)) {
                        $table->dropColumn($kolom);
                    }
                }
            });
        }
    }
};
