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
        Schema::table('transaksi', function (Blueprint $table) {
            if (! Schema::hasColumn('transaksi', 'durasi_garansi_hari')) {
                $table->integer('durasi_garansi_hari')->default(3)->after('status');
            }
            if (! Schema::hasColumn('transaksi', 'garansi_teks')) {
                $table->string('garansi_teks')->nullable()->after('durasi_garansi_hari');
            }
            if (! Schema::hasColumn('transaksi', 'garansi_berakhir_pada')) {
                $table->date('garansi_berakhir_pada')->nullable()->after('garansi_teks');
            }
            if (! Schema::hasColumn('transaksi', 'catatan_garansi')) {
                $table->text('catatan_garansi')->nullable()->after('garansi_berakhir_pada');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $columns = ['durasi_garansi_hari', 'garansi_teks', 'garansi_berakhir_pada', 'catatan_garansi'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('transaksi', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
