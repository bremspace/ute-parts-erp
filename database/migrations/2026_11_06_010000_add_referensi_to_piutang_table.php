<?php

use App\Modules\Akunting\Models\Piutang;
use App\Modules\Servis\Models\TiketServis;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [P1-3/T-03] Referensi polimorfik eksplisit pada subledger piutang.
 *
 * Latar: subledger AR sebelumnya dicocokkan dengan
 * `WHERE keterangan LIKE '%{no_tiket}%'` — baris piutang lain yang
 * kebetulan menyebut no tiket yang sama bisa salah di-lunaskan.
 * Tabel `utang` sudah memakai pola `referensi_tipe`/`referensi_id`:
 * kini piutang mengikuti pola yang sama (non-destructive; kolom nullable
 * supaya PO/kasbon piutang lain tetap sah tanpa referensi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('piutang', function (Blueprint $table) {
            if (! Schema::hasColumn('piutang', 'referensi_tipe')) {
                $table->string('referensi_tipe')->nullable()->after('keterangan');
                $table->unsignedBigInteger('referensi_id')->nullable()->after('referensi_tipe');
                $table->index(['referensi_tipe', 'referensi_id']);
            }
        });

        // Backfill best-effort: baris piutang legacy yang keterangan-nya
        // mengandung EXACTLY satu nomor tiket servis yang valid di-link.
        // Baris ambigu (multi-match / tiket tidak ada) dibiarkan NULL —
        // jalur fallback LIKE di service tetap menjangkau mereka.
        if (! Schema::hasTable('tiket_servis')) {
            return;
        }

        Piutang::whereNull('referensi_tipe')
            ->where('keterangan', 'like', '%SRV-%')
            ->orderBy('id')
            ->each(function (Piutang $piutang): void {
                if (preg_match('/SRV-(?:C\d{2}|ONL)-\d{8}-\d{4}/', (string) $piutang->keterangan, $m) !== 1) {
                    return;
                }

                $candId = TiketServis::where('no_tiket', $m[0])->pluck('id');

                if ($candId->count() !== 1) {
                    return; // ambigu / tidak ditemukan → biarkan NULL
                }

                $piutang->forceFill([
                    'referensi_tipe' => TiketServis::class,
                    'referensi_id' => $candId->first(),
                ])->save();
            });
    }

    public function down(): void
    {
        Schema::table('piutang', function (Blueprint $table) {
            if (Schema::hasColumn('piutang', 'referensi_tipe')) {
                $table->dropIndex(['referensi_tipe', 'referensi_id']);
                $table->dropColumn(['referensi_tipe', 'referensi_id']);
            }
        });
    }
};
