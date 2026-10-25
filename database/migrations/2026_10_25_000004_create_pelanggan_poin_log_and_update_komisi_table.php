<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pelanggan_poin_log')) {
            Schema::create('pelanggan_poin_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
                $table->foreignId('transaksi_id')->nullable()->constrained('transaksi')->nullOnDelete();
                $table->string('tipe', 30); // earned, redeemed, refund, penyesuaian
                $table->integer('poin'); // positif (masuk) atau negatif (keluar)
                $table->integer('saldo_sebelum');
                $table->integer('saldo_sesudah');
                $table->string('keterangan')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['pelanggan_id', 'created_at']);
                $table->index('transaksi_id');
                $table->index('tipe');
            });
        }

        if (Schema::hasTable('komisi')) {
            Schema::table('komisi', function (Blueprint $table) {
                if (! Schema::hasColumn('komisi', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable()->after('approved_at');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pelanggan_poin_log');

        if (Schema::hasTable('komisi') && Schema::hasColumn('komisi', 'paid_at')) {
            Schema::table('komisi', function (Blueprint $table) {
                $table->dropColumn('paid_at');
            });
        }
    }
};
