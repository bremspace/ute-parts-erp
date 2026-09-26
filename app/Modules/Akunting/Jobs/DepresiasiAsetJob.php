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
        public ?string $periode = null,
        /**
         * [B-15c] Scope cabang opsional. null = semua cabang (jadwal bulanan, default
         * lama). Diisi dari tombol "Jalankan Depresiasi" (AsetRegister) supaya jurnal
         * TIDAK pernah bocor ke cabang lain.
         */
        public ?int $cabangId = null
    ) {}

    /**
     * @return array{diproses: int, dilewati: int, total: float, periode: string}
     */
    public function handle(DepresiasiService $service, NotificationService $notifikasi): array
    {
        // Default: bulan sebelumnya (jadwal monthlyOn tgl 1 → proses bulan lalu)
        $periode = $this->periode ?? now()->startOfMonth()->subMonth()->format('Y-m');

        $hasil = $service->prosesPeriode($periode, $this->cabangId);

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

    /**
     * [B-10d] Notifikasi bila job gagal total (tries habis) — depresiasi yang
     * gagal diam-diam berarti aset tidak disusut & laporan aset menyesatkan.
     */
    public function failed(?\Throwable $e): void
    {
        $periode = $this->periode ?? now()->startOfMonth()->subMonth()->format('Y-m');

        report($e);

        app(NotificationService::class)->kirim(
            'inapp',
            null,
            'Depresiasi Aset Bulanan Gagal',
            "Depresiasi aset periode {$periode} gagal: ".($e?->getMessage() ?: 'penyebab tidak diketahui').
            '. Jurnal depresiasi belum diposting — perlu diperiksa manual.',
            [
                'type' => 'error',
                'periode' => $periode,
            ]
        );
    }
}
