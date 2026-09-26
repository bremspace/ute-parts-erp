<?php

namespace App\Modules\Akunting\Services;

use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * [B-10a / P0-1] Pencatatan pembayaran Piutang (AR) / Utang (AP).
 *
 * Pra-sebelumnya `AkuntingController::bayarPiutang/bayarUtang` dan
 * `AkuntingDashboard::bayarPiutang/bayarUtang` melakukan:
 *   1. update `jumlah_dibayar` + status  → SUDAH TER-COMMIT
 *   2. post jurnal  → jika gagal, exception hanya di-`Log::warning()`
 * Hasilnya: subledger dan jurnal bisa berbeda (jurnal hilang tanpa rollback),
 * dan versi Livewire sama sekali tidak mem-post jurnal.
 *
 * Service ini jadi SATU sumber kebenaran:
 * - `lockForUpdate()` baris piutang/utang → tidak ada lost update / over-bayar
 *   saat dua pembayaran bersamaan;
 * - validasi sisa + cabang (cross-branch = 404, bukan kebocoran data);
 * - update subledger DAN `JurnalService::post()` dalam SATU `DB::transaction`
 *   → jurnal gagal = subledger rollback (fail-closed);
 * - `referensi_tipe`/`referensi_id` menunjuk baris pembayaran untuk rekonsiliasi;
 * - `idempotencyKey` opsional menolak pembayaran kembar (double submit / retry).
 *
 * Pesan error seluruhnya Bahasa Indonesia.
 */
class PembayaranSubledgerService
{
    public function __construct(
        protected JurnalService $jurnalService
    ) {}

    /**
     * Terima pembayaran piutang (AR): Debit Kas 110-01 / Kredit Piutang 120-01.
     *
     * @return array{model: Piutang, no_jurnal: string, jumlah: float, status: string}
     *
     * @throws ModelNotFoundException baris tidak ada / bukan milik cabang ini
     * @throws \Exception sisa tidak cukup / jurnal gagal (transaksi rollback)
     */
    public function bayarPiutang(
        int $piutangId,
        float $jumlah,
        ?int $cabangId = null,
        ?int $userId = null,
        ?string $idempotencyKey = null
    ): array {
        $jumlah = round($jumlah, 2);

        if ($jumlah <= 0) {
            throw new \Exception('Nominal pembayaran harus lebih besar dari nol.');
        }

        $cabangId = $this->wajibkanCabang($cabangId);

        return DB::transaction(function () use ($piutangId, $jumlah, $cabangId, $userId, $idempotencyKey) {
            $piutang = Piutang::whereKey($piutangId)->lockForUpdate()->first();

            // Cross-branch / tidak ditemukan diperlakukan sama: 404 (tidak bocorkan keberadaan)
            if (! $piutang || (int) $piutang->cabang_id !== $cabangId) {
                $e = new ModelNotFoundException;
                $e->setModel(Piutang::class, [$piutangId]);

                throw $e;
            }

            $dibayar = round((float) $piutang->jumlah_dibayar + $jumlah, 2);

            if ($dibayar > (float) $piutang->jumlah + 0.001) {
                throw new \Exception('Pembayaran melebihi sisa piutang');
            }

            $status = $dibayar >= (float) $piutang->jumlah ? 'lunas' : 'sebagian';

            $noJurnal = $this->jurnalService->generateNoJurnal('pembayaran', $cabangId);

            $this->jurnalService->post(
                $noJurnal,
                now(),
                'manual',
                [
                    ['akun_kode' => '110-01', 'debit' => $jumlah, 'kredit' => 0],   // Kas masuk
                    ['akun_kode' => '120-01', 'debit' => 0, 'kredit' => $jumlah],  // Piutang turun
                ],
                "Penerimaan piutang {$piutang->no_piutang}",
                $cabangId,
                $userId,
                Piutang::class,
                (int) $piutang->id,
                $idempotencyKey
            );

            $piutang->update([
                'jumlah_dibayar' => $dibayar,
                'status' => $status,
            ]);

            return [
                'model' => $piutang->fresh(),
                'no_jurnal' => $noJurnal,
                'jumlah' => $jumlah,
                'status' => $status,
            ];
        }, 3);
    }

    /**
     * Bayar utang (AP): Debit Utang 210-01/210-03 / Kredit Kas 110-01.
     *
     * @return array{model: Utang, no_jurnal: string, jumlah: float, status: string}
     *
     * @throws ModelNotFoundException baris tidak ada / bukan milik cabang ini
     * @throws \Exception sisa tidak cukup / jurnal gagal (transaksi rollback)
     */
    public function bayarUtang(
        int $utangId,
        float $jumlah,
        ?int $cabangId = null,
        ?int $userId = null,
        ?string $idempotencyKey = null
    ): array {
        $jumlah = round($jumlah, 2);

        if ($jumlah <= 0) {
            throw new \Exception('Nominal pembayaran harus lebih besar dari nol.');
        }

        $cabangId = $this->wajibkanCabang($cabangId);

        return DB::transaction(function () use ($utangId, $jumlah, $cabangId, $userId, $idempotencyKey) {
            $utang = Utang::whereKey($utangId)->lockForUpdate()->first();

            if (! $utang || (int) $utang->cabang_id !== $cabangId) {
                $e = new ModelNotFoundException;
                $e->setModel(Utang::class, [$utangId]);

                throw $e;
            }

            $dibayar = round((float) $utang->jumlah_dibayar + $jumlah, 2);

            if ($dibayar > (float) $utang->jumlah + 0.001) {
                throw new \Exception('Pembayaran melebihi sisa utang');
            }

            $status = $dibayar >= (float) $utang->jumlah ? 'lunas' : 'sebagian';

            // Utang komisi reseller memakai akun 210-03 (kontra Pendapatan)
            $akunUtang = $utang->referensi_tipe === 'komisi' ? '210-03' : '210-01';

            $noJurnal = $this->jurnalService->generateNoJurnal('pembayaran', $cabangId);

            $this->jurnalService->post(
                $noJurnal,
                now(),
                'manual',
                [
                    ['akun_kode' => $akunUtang, 'debit' => $jumlah, 'kredit' => 0],
                    ['akun_kode' => '110-01', 'debit' => 0, 'kredit' => $jumlah],
                ],
                "Pembayaran utang {$utang->no_utang}",
                $cabangId,
                $userId,
                Utang::class,
                (int) $utang->id,
                $idempotencyKey
            );

            $utang->update([
                'jumlah_dibayar' => $dibayar,
                'status' => $status,
            ]);

            return [
                'model' => $utang->fresh(),
                'no_jurnal' => $noJurnal,
                'jumlah' => $jumlah,
                'status' => $status,
            ];
        }, 3);
    }

    /**
     * Cabang wajib ada: tanpa scope cabang, AR/AP tidak boleh bisa dibayar
     * (fail-closed) alih-alih diam-diam memakai "semua cabang".
     */
    private function wajibkanCabang(?int $cabangId): int
    {
        $cabangId = (int) ($cabangId ?? 0);

        if ($cabangId <= 0) {
            throw new \Exception('Cabang aktif belum dipilih. Pilih cabang sebelum mencatat pembayaran.');
        }

        return $cabangId;
    }
}
