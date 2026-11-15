<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flag cepat "perubahan lingkup menunggu persetujuan" di tiket.
 *
 * Daftar tiket tertahan di papan servis sebelumnya menghitung diff item
 * (estimasi vs pekerjaan) untuk SEMUA tiket aktif pada setiap render Livewire
 * — mahal dan membuat UI lambat. Flag ini diisi saat item berubah dan dipakai
 * untuk daftar/UI; guard 'selesai'/'bayar' tetap memakai perhitungan akurat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tiket_servis', 'revisi_lingkup_pending')) {
            Schema::table('tiket_servis', function (Blueprint $table) {
                $table->boolean('revisi_lingkup_pending')->default(false)->index();
            });
        }

        // Backfill tiket lama: perubahan sempat diajukan tapi belum pernah
        // disetujui (hash persetujuan masih kosong).
        DB::table('tiket_servis')
            ->whereNull('revisi_lingkup_hash')
            ->whereNotNull('revisi_lingkup_diajukan_hash')
            ->whereIn('status', ['disetujui', 'dikerjakan', 'qc'])
            ->update(['revisi_lingkup_pending' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('tiket_servis', 'revisi_lingkup_pending')) {
            Schema::table('tiket_servis', function (Blueprint $table) {
                $table->dropIndex('tiket_servis_revisi_lingkup_pending_index');
                $table->dropColumn('revisi_lingkup_pending');
            });
        }
    }
};
