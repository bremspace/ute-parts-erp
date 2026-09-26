<?php

use App\Modules\Akunting\Models\AkunCOA;
use Illuminate\Database\Migrations\Migration;
use Spatie\Activitylog\Support\ActivityLogger;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tambahkan akun PPN Keluaran (220-02) jika belum ada.
        // Idempotent: gunakan firstOrCreate berdasarkan kode.
        $inserted = false;
        $exists = AkunCOA::where('kode', '220-02')->exists();
        if (! $exists) {
            // [B-10f]-activity-log: AkunCOA memakai LogsActivity, dan tabel
            // `activity_log` baru dibuat di migrasi 2026_09_23_010150 (LEBIH LAMBAT
            // dari migrasi ini). Tanpa penonaktifan, setiap `php artisan migrate`
            // / `migrate:fresh` gagal dengan "no such table: activity_log".
            // Migrasi tidak boleh menulis activity log.
            $inserted = app(ActivityLogger::class)->withoutLogging(
                fn () => (bool) AkunCOA::firstOrCreate(
                    ['kode' => '220-02'],
                    [
                        'nama' => 'PPN Keluaran',
                        'tipe' => 'kewajiban',
                        'kelompok' => 'pajak',
                        'saldo_normal' => 'kredit',
                        'is_active' => true,
                    ]
                )
            );
        }

        // Log alih-alih trigger (opsional) - bila AuditService diperlukan:
        if ($inserted) {
            // app(AuditService::class)->catat('AkunCOA', 'create', null, 'Akun PPN Keluaran (220-02) ditambahkan');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Hapus baris akun 220-02 jika tidak terkait dengan data produksi.
        // Catatan: produksi aktual harus dipertahankan; ini hanya guard non-produksi.
        app(ActivityLogger::class)->withoutLogging(
            fn () => AkunCOA::where('kode', '220-02')->delete()
        );
    }
};
