<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * [T-12] rak_id di item transfer (rak tujuan) — rak dipilih di form transfer.
     * [T-14] rak_id di sesi opname + item opname (scope opname per rak/lokasi).
     * [T-14] Akun COA 520-08 Selisih Stok (pasangan 130-01 saat penyesuaian opname).
     */
    public function up(): void
    {
        Schema::table('stok_transfer_item', function (Blueprint $table) {
            $table->foreignId('rak_id')->nullable()->after('sku_variant_id')->constrained('rak')->nullOnDelete();
        });

        Schema::table('stok_opname', function (Blueprint $table) {
            $table->foreignId('rak_id')->nullable()->after('gudang_id')->constrained('rak')->nullOnDelete();
        });

        Schema::table('stok_opname_item', function (Blueprint $table) {
            $table->foreignId('rak_id')->nullable()->after('sku_variant_id')->constrained('rak')->nullOnDelete();
        });

        // Akun penyesuaian stok utk jurnal opname (selisih fisik vs sistem)
        DB::table('akun_coa')->updateOrInsert(
            ['kode' => '520-08'],
            [
                'nama' => 'Selisih Stok (Opname)',
                'tipe' => 'beban',
                'kelompok' => 'beban_operasional',
                'saldo_normal' => 'debit',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('akun_coa')->where('kode', '520-08')->delete();

        Schema::table('stok_opname_item', fn (Blueprint $t) => $t->dropConstrainedForeignId('rak_id'));
        Schema::table('stok_opname', fn (Blueprint $t) => $t->dropConstrainedForeignId('rak_id'));
        Schema::table('stok_transfer_item', fn (Blueprint $t) => $t->dropConstrainedForeignId('rak_id'));
    }
};
