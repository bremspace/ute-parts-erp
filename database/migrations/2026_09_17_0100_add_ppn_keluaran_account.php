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
        // Tambahkan akun PPN Keluaran (220-02) jika belum ada.
        // Idempotent: gunakan firstOrCreate berdasarkan kode.
        $inserted = false;
        $exists = \App\Modules\Akunting\Models\AkunCOA::where('kode', '220-02')->exists();
        if (! $exists) {
            \App\Modules\Akunting\Models\AkunCOA::firstOrCreate(
                ['kode' => '220-02'],
                [
                    'nama' => 'PPN Keluaran',
                    'tipe' => 'kewajiban',
                    'kelompok' => 'pajak',
                    'saldo_normal' => 'kredit',
                    'is_active' => true,
                ]
            );
            $inserted = true;
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
        \App\Modules\Akunting\Models\AkunCOA::where('kode', '220-02')->delete();
    }
};