<?php

namespace App\Modules\Crm\Console\Commands;

use App\Modules\Crm\Models\KampanyeBroadcast;
use App\Modules\Crm\Services\BroadcastService;
use Illuminate\Console\Command;

/**
 * [T-23] Eksekusi kampanye broadcast berstatus 'terjadwal' yang jatuh temponya <= now.
 * Kirim via BroadcastService (channel wa/email/inapp, personalisasi {nama}/{tier}),
 * update status + log per penerima (notifikasi_keluar.kampanye_broadcast_id).
 * JANGAN kirim sync di request — dipanggil via scheduler (tiap menit).
 */
class EksekusiBroadcastTerjadwal extends Command
{
    protected $signature = 'crm:broadcast-terjadwal';

    protected $description = 'Eksekusi kampanye broadcast terjadwal yang jatuh tempo (T-23)';

    public function handle(BroadcastService $broadcast): int
    {
        $kampanye = KampanyeBroadcast::where('status', 'terjadwal')
            ->where('dijadwalkan_at', '<=', now())
            ->get();

        if ($kampanye->isEmpty()) {
            $this->info('Tidak ada kampanye terjadwal yang jatuh tempo.');

            return self::SUCCESS;
        }

        foreach ($kampanye as $k) {
            $broadcast->kirimSekarang($k);
        }

        $this->info("Broadcast terjadwal dieksekusi: {$kampanye->count()} kampanye");

        return self::SUCCESS;
    }
}
