<?php

namespace App\Modules\Notifikasi\Services;

use App\Modules\Notifikasi\Jobs\KirimNotifikasiJob;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use Illuminate\Support\Facades\Queue;

class NotificationService
{
    /**
     * Dispatch notifikasi via queue (database driver).
     * Channel: wa, email, inapp — provider WA keputusan user (Fonnte/Wablas placeholder siap).
     *
     * @param  string  $tipe  wa | email | inapp
     * @param  string|null  $tujuan  nomor HP / email
     */
    public function kirim(
        string $tipe,
        ?string $tujuan,
        string $judul,
        string $konten,
        array $payload = []
    ): NotifikasiKeluar {
        $log = NotifikasiKeluar::create([
            'tipe' => $tipe,
            'tujuan' => $tujuan,
            'judul' => $judul,
            'konten' => $konten,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        // Dispatch via queue — database driver + Supervisor (PRD §4.9 §7)
        Queue::later(0, new KirimNotifikasiJob($log->id));

        return $log;
    }
}
