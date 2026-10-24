<?php

namespace App\Modules\Marketplace\Console\Commands;

use App\Modules\Marketplace\Services\DuitkuPaymentProcessor;
use App\Modules\Marketplace\Services\DuitkuService;
use App\Modules\Pos\Models\Transaksi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * [T-03] Rekonsiliasi & ekspirasi order marketplace Duitku (jadwal harian).
 *
 * Scan transaksi `menunggu_pembayaran` yang tidak bergerak lebih lama dari
 * `DUITKU_EXPIRY_PERIOD` (menit), lalu:
 *  - Sudah punya invoice (payment_reference terisi) → tanya Duitku
 *    `POST /transactionStatus`:
 *      statusCode 00 (sukses) → JALUR SAMA dgn webhook lunas
 *        (DuitkuPaymentProcessor::prosesLunas — stok/jurnal/komisi idempoten)
 *      statusCode 02 (batal) → tandai kadaluarsa
 *      statusCode 01 (pending) / unknown + sudah lewat expiry → kadaluarsa
 *      error jaringan       → SKIP (coba lagi run berikutnya)
 *  - Belum pernah buat invoice → tandai kadaluarsa langsung.
 *
 * Notifikasi pelanggan tetap lewat queue (NotificationService → KirimNotifikasiJob).
 * Pemakaian: php artisan duitku:rekon
 */
class DuitkuRekonCommand extends Command
{
    protected $signature = 'duitku:rekon {--limit=200 : Maksimal transaksi per run}';

    protected $description = 'Rekonsiliasi status & ekspirasi order Duitku (menunggu_pembayaran lewat expiryPeriod)';

    public function handle(DuitkuService $duitku, DuitkuPaymentProcessor $processor): int
    {
        if (! $duitku->isConfigured()) {
            $this->warn('Duitku belum dikonfigurasi — lewati rekonsiliasi.');

            return self::SUCCESS;
        }

        $expiryMinutes = (int) config('duitku.expiry_period', 1440);
        $cutoff = now()->subMinutes($expiryMinutes);
        $now = now();

        // Global lintas cabang: tiap order diproses dgn cabang_id-nya sendiri
        // di dalam processor (aman untuk scheduler tanpa konteks session).
        $transaksis = Transaksi::query()
            ->where('status', 'menunggu_pembayaran')
            ->where(function ($q) use ($now, $cutoff) {
                $q->where(function ($sub) use ($now) {
                    $sub->whereNotNull('expires_at')
                        ->where('expires_at', '<=', $now);
                })->orWhere(function ($sub) use ($cutoff) {
                    $sub->whereNull('expires_at')
                        ->where('updated_at', '<', $cutoff);
                });
            })
            ->orderBy('updated_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($transaksis->isEmpty()) {
            $this->info('Tidak ada order menunggu_pembayaran yang lewat expiry.');

            return self::SUCCESS;
        }

        $lunas = 0;
        $kadaluarsa = 0;
        $skip = 0;

        foreach ($transaksis as $t) {
            $no = $t->no_transaksi;

            // Belum pernah buat invoice → tidak bisa dibayar → kadaluarsa
            if (! $t->payment_reference) {
                $hasil = $processor->prosesKadaluarsa($no);
                if ($hasil['kode'] === 'sukses') {
                    $kadaluarsa++;
                    $this->line("{$no} → kadaluarsa (belum pernah buat invoice).");
                } else {
                    $skip++;
                    Log::warning('[Duitku-rekon] Gagal proses kadaluarsa', ['order' => $no, 'kode' => $hasil['kode'] ?? null]);
                }

                continue;
            }

            $status = $duitku->transactionStatus($no);

            if ($status['status'] === 'error') {
                // Jaringan/API down — jangan putuskan; coba lagi run berikutnya
                $skip++;
                $this->warn("{$no} → SKIP (transactionStatus tidak terjangkau).");

                continue;
            }

            if ($status['status'] === 'sukses') {
                // Jalur sama dgn webhook lunas (prosesLunas idempoten)
                $amount = $status['amount'] ?? (int) round((float) $t->total_akhir);
                $hasil = $processor->prosesLunas($no, (int) $amount, $status['reference']);
                if ($hasil['kode'] === 'sukses') {
                    $lunas++;
                    $this->info("{$no} → LUNAS (terkonfirmasi Duitku, diproses penuh).");
                } elseif ($hasil['kode'] === 'sudah_diproses') {
                    $skip++;
                    $this->line("{$no} → sudah diproses (idempotent skip).");
                } elseif ($hasil['kode'] === 'nominal_tidak_cocok') {
                    $skip++;
                    $this->error("{$no} → NOMINAL TIDAK COCOK (tagihan {$hasil['expected']}, Duitku {$hasil['diterima']}) — rekonsiliasi manual!");
                    Log::error('[Duitku-rekon] Nominal tidak cocok — manual', [
                        'order' => $no, 'expected' => $hasil['expected'], 'diterima' => $hasil['diterima'],
                    ]);
                } else {
                    $skip++;
                    $this->error("{$no} → gagal diproses (lihat log).");
                }

                continue;
            }

            // pending (01) / batal (02) / unknown + sudah lewat expiry → kadaluarsa
            $hasil = $processor->prosesKadaluarsa($no);
            if ($hasil['kode'] === 'sukses') {
                $kadaluarsa++;
                $this->line("{$no} → kadaluarsa (status Duitku: {$status['status']}).");
            } else {
                $skip++;
                Log::warning('[Duitku-rekon] Gagal proses kadaluarsa', ['order' => $no, 'kode' => $hasil['kode'] ?? null]);
            }
        }

        $this->info("Selesai: {$lunas} lunas, {$kadaluarsa} kadaluarsa, {$skip} dilewati (dari ".count($transaksis).').');

        return self::SUCCESS;
    }
}
