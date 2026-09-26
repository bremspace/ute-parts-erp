<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiket_servis', function (Blueprint $table) {
            if (! Schema::hasColumn('tiket_servis', 'status_pembayaran')) {
                $table->enum('status_pembayaran', ['belum_bayar', 'lunas'])->default('belum_bayar')->index();
            }
            if (! Schema::hasColumn('tiket_servis', 'tanggal_bayar')) {
                $table->timestamp('tanggal_bayar')->nullable();
            }
            if (! Schema::hasColumn('tiket_servis', 'metode_pembayaran')) {
                $table->string('metode_pembayaran', 50)->nullable();
            }
            if (! Schema::hasColumn('tiket_servis', 'no_jurnal_bayar')) {
                $table->string('no_jurnal_bayar')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tiket_servis', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('tiket_servis', 'status_pembayaran')) {
                $columnsToDrop[] = 'status_pembayaran';
            }
            if (Schema::hasColumn('tiket_servis', 'metode_pembayaran')) {
                $columnsToDrop[] = 'metode_pembayaran';
            }
            if (Schema::hasColumn('tiket_servis', 'no_jurnal_bayar')) {
                $columnsToDrop[] = 'no_jurnal_bayar';
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
