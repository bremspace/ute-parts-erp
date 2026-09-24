<?php

namespace App\Modules\Akunting\Jobs;

use App\Modules\Akunting\Services\DepresiasiService;
use App\Modules\Notifikasi\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * [F3-3] Job BULANAN depresiasi aset tetap — dijadwalkan via bootstrap/app.php
 * (schedule → dispatch ke queue, database driver + Supervisor; TIDAK sync di request).
 *
 * Default periode = bulan sebelumnya (jalankan tgl 1 pagi utk bulan lalu).
 * Idempoten per periode: aman dijalankan dobel — 1 jurnal per aset per bulan
 * (guard DepresiasiService: depresiasi_terakhir_bulan + cek jurnal existing).
 *
 * Jurnal: Debit 530-01 Beban Depresiasi / Kredit 130-02 Akumulasi Depresiasi.
 * Notifikasi in-app via NotificationService (queue) bila ada aset diproses.
 */
class DepresiasiAsetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public ?string $periode = null
    ) {}

    /**
     * @return array{diproses: int, dilewati: int, total: float, periode: string}
     */
    public function handle(DepresiasiService $service, NotificationService $notifikasi): array
    {
        // Default: bulan sebelumnya (jadwal monthlyOn tgl 1 → proses bulan lalu)
        $periode = $this->periode ?? now()->startOfMonth()->subMonth()->format('Y-m');

        $hasil = $service->prosesPeriode($periode);

        if ($hasil['diproses'] > 0) {
            $notifikasi->kirim(
                'inapp',
                null,
                'Jurnal Depresiasi Aset Bulanan',
                "Depresiasi aset periode {$periode}: {$hasil['diproses']} aset, total beban Rp ".
                number_format($hasil['total'], 2, ',', '.').
                '. Jurnal 530-01 / 130-02 sudah diposting.',
                [
                    'type' => 'success',
                    'periode' => $periode,
                    'diproses' => $hasil['diproses'],
                    'total' => $hasil['total'],
                ]
            );
        }

        return $hasil;
    }
}
