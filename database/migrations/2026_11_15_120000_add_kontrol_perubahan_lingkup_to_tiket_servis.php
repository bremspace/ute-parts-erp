<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [BH/ISO] Kontrol perubahan lingkup servis + pemrosesan stok part estimasi.
 *
 * - `revisi_lingkup_*`: jejak persetujuan perubahan (tambah/ubah/kurang part &
 *   jasa) di luar estimasi yang sudah disetujui pelanggan. Hash mengikat isi
 *   perubahan, jadi perubahan baru memerlukan persetujuan baru.
 * - `stok_estimasi_*`: part estimasi yang dipakai tetap memotong stok meski
 *   tidak dicatat ulang sebagai item pekerjaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiket_servis', function (Blueprint $table) {
            if (! Schema::hasColumn('tiket_servis', 'revisi_lingkup_hash')) {
                $table->string('revisi_lingkup_hash', 64)->nullable();
            }
            if (! Schema::hasColumn('tiket_servis', 'revisi_lingkup_diajukan_hash')) {
                $table->string('revisi_lingkup_diajukan_hash', 64)->nullable();
            }
            if (! Schema::hasColumn('tiket_servis', 'revisi_lingkup_disetujui_at')) {
                $table->timestamp('revisi_lingkup_disetujui_at')->nullable();
            }
            if (! Schema::hasColumn('tiket_servis', 'revisi_lingkup_diajukan_at')) {
                $table->timestamp('revisi_lingkup_diajukan_at')->nullable();
            }
            if (! Schema::hasColumn('tiket_servis', 'revisi_lingkup_snapshot')) {
                $table->text('revisi_lingkup_snapshot')->nullable();
            }
            if (! Schema::hasColumn('tiket_servis', 'stok_estimasi_diproses')) {
                $table->boolean('stok_estimasi_diproses')->default(false);
            }
            if (! Schema::hasColumn('tiket_servis', 'stok_estimasi_catatan')) {
                $table->string('stok_estimasi_catatan')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tiket_servis', function (Blueprint $table) {
            $table->dropColumn([
                'revisi_lingkup_hash',
                'revisi_lingkup_diajukan_hash',
                'revisi_lingkup_disetujui_at',
                'revisi_lingkup_diajukan_at',
                'revisi_lingkup_snapshot',
                'stok_estimasi_diproses',
                'stok_estimasi_catatan',
            ]);
        });
    }
};
