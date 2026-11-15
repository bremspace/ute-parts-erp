<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voucher goodwill: kompensasi yang TIDAK mengurangi tagihan tiket asal,
 * melainkan diterbitkan sebagai voucher yang dipakai otomatis pada servis
 * berikutnya pelanggan yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 40)->unique();
                $table->foreignId('pelanggan_id')->nullable()->index();
                $table->foreignId('tiket_servis_id')->nullable();
                $table->decimal('amount', 12, 2);
                $table->decimal('sisa', 12, 2);
                $table->string('status', 20)->default('aktif')->index(); // aktif | terpakai | expired
                $table->timestamp('expired_at')->nullable();
                $table->timestamp('used_at')->nullable();
                $table->foreignId('used_tiket_id')->nullable();
                $table->string('catatan')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('tiket_servis', 'goodwill_voucher')) {
            Schema::table('tiket_servis', function (Blueprint $table) {
                $table->boolean('goodwill_voucher')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');

        if (Schema::hasColumn('tiket_servis', 'goodwill_voucher')) {
            Schema::table('tiket_servis', function (Blueprint $table) {
                $table->dropColumn('goodwill_voucher');
            });
        }
    }
};
