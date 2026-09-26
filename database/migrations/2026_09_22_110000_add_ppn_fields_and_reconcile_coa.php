<?php

use App\Modules\Akunting\Models\AkunCOA;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Support\ActivityLogger;

return new class extends Migration
{
    /**
     * [F1-2] Pajak Otomatis:
     * - transaksi: kolom ppn_nominal + dpp (idempotent, default 0, backfill 0)
     * - COA: selaraskan 220-01 = "PPN Keluaran" (kontrak AC), tambah 110-03 "PPN Masukan"
     * - konfigurasi: key global pajak_enabled (selain ppn_enabled lama)
     */
    public function up(): void
    {
        // 1. Kolom transaksi (idempotent — hadapi MySQL & SQLite :memory:)
        if (! Schema::hasColumn('transaksi', 'ppn_nominal')) {
            Schema::table('transaksi', function (Blueprint $table) {
                $table->decimal('ppn_nominal', 15, 2)->default(0)->after('pajak_nominal');
            });
        }
        if (! Schema::hasColumn('transaksi', 'dpp')) {
            Schema::table('transaksi', function (Blueprint $table) {
                $table->decimal('dpp', 15, 2)->default(0)->after('diskon_nominal');
            });
        }
        // Backfill baris lama → 0 (decimal default sudah 0 utk baris baru; update eksplisit utk yang ada)
        DB::table('transaksi')->whereNull('ppn_nominal')->update(['ppn_nominal' => 0]);
        DB::table('transaksi')->whereNull('dpp')->update(['dpp' => 0]);

        // 2. COA 220-01 = PPN Keluaran (AC F1-2). Bila masih "Pajak Dibayar Dimuka" → rename (kontrak PRD).
        // 2b. 220-02 (legacy) → rename bila duplikat nama dgn 220-01.
        // 3. COA 110-03 = PPN Masukan (aset, debit) — sumber rekap masukan e-Faktur.
        //
        // [B-10f]-activity-log: AkunCOA memakai LogsActivity, sedangkan tabel
        // `activity_log` baru dibuat di migrasi 2026_09_23_010150 (LEBIH LAMBAT
        // dari migrasi ini) → tanpa penonaktifan, `migrate`/`migrate:fresh`
        // gagal "no such table: activity_log". Migrasi tidak menulis activity log.
        app(ActivityLogger::class)->withoutLogging(function () {
            $akun22001 = AkunCOA::where('kode', '220-01')->first();
            if (! $akun22001) {
                AkunCOA::firstOrCreate(
                    ['kode' => '220-01'],
                    [
                        'nama' => 'PPN Keluaran',
                        'tipe' => 'kewajiban',
                        'kelompok' => 'pajak',
                        'saldo_normal' => 'kredit',
                        'is_active' => true,
                    ]
                );
            } elseif ($akun22001->nama === 'Pajak Dibayar Dimuka') {
                $akun22001->update(['nama' => 'PPN Keluaran']);
            }

            $akun22002 = AkunCOA::where('kode', '220-02')->first();
            if ($akun22002 && $akun22002->nama === 'PPN Keluaran') {
                $akun22002->update(['nama' => 'PPN Keluaran (Legacy 220-02)']);
            }

            AkunCOA::firstOrCreate(
                ['kode' => '110-03'],
                [
                    'nama' => 'PPN Masukan',
                    'tipe' => 'aset',
                    'kelompok' => 'pajak',
                    'saldo_normal' => 'debit',
                    'is_active' => true,
                ]
            );
        });

        // 4. konfigurasi: key global pajak_enabled (kontrak AC; ppn_enabled lama tetap dipakai sbg fallback)
        $now = now();
        DB::table('konfigurasi')->updateOrInsert(
            ['kunci' => 'pajak_enabled'],
            ['nilai' => 'false', 'deskripsi' => 'Aktifkan Pajak Otomatis (PPN) global', 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        if (Schema::hasColumn('transaksi', 'ppn_nominal')) {
            Schema::table('transaksi', function (Blueprint $table) {
                $table->dropColumn('ppn_nominal');
            });
        }
        if (Schema::hasColumn('transaksi', 'dpp')) {
            Schema::table('transaksi', function (Blueprint $table) {
                $table->dropColumn('dpp');
            });
        }

        DB::table('konfigurasi')->where('kunci', 'pajak_enabled')->delete();
        app(ActivityLogger::class)->withoutLogging(
            fn () => AkunCOA::where('kode', '110-03')->delete()
        );
    }
};
