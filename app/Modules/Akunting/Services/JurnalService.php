<?php

namespace App\Modules\Akunting\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use Illuminate\Support\Facades\DB;

/**
 * Double-entry journal service (PRD §4.6).
 * Setiap posting divalidasi: total debit === total kredit.
 * Idempoten per no_jurnal: posting ulang no_jurnal yang sama ditolak.
 */
class JurnalService
{
    /**
     * Post entri jurnal double-entry.
     *
     * @param  string  $noJurnal  unique journal number
     * @param  \DateTimeInterface|null  $tanggal
     * @param  string  $sumber  pos|servis|komisi|opname|pembelian|manual
     * @param  array  $lines  [['akun_kode' => '110-01', 'debit' => 1000, 'kredit' => 0], ...]
     *                        minimal satu line debit dan satu line kredit
     *
     * @throws \Exception jika balance tidak seimbang atau no_jurnal duplikat
     */
    public function post(
        string $noJurnal,
        $tanggal,
        string $sumber,
        array $lines,
        ?string $deskripsi = null,
        ?int $cabangId = null,
        ?int $userId = null,
        ?string $referensiTipe = null,
        ?int $referensiId = null
    ): array {
        if (empty($lines)) {
            throw new \Exception('Jurnal membutuhkan minimal 1 baris entri');
        }

        // Idempotency: no_jurnal unik
        if (JurnalAkuntansi::where('no_jurnal', $noJurnal)->exists()) {
            throw new \Exception("Jurnal {$noJurnal} sudah pernah diposting (idempotency guard)");
        }

        $totalDebit = 0.0;
        $totalKredit = 0.0;

        $normalized = [];
        foreach ($lines as $line) {
            $akun = AkunCOA::where('kode', $line['akun_kode'] ?? '')->first();
            if (! $akun) {
                throw new \Exception("Akun COA tidak ditemukan: {$line['akun_kode']}");
            }

            $debit = round((float) ($line['debit'] ?? 0), 2);
            $kredit = round((float) ($line['kredit'] ?? 0), 2);
            $totalDebit += $debit;
            $totalKredit += $kredit;

            $normalized[] = [
                'no_jurnal' => $noJurnal,
                'tanggal' => $tanggal ?? now(),
                'cabang_id' => $cabangId,
                'akun_coa_id' => $akun->id,
                'sumber' => $sumber,
                'deskripsi' => $deskripsi ?? "Jurnal {$sumber} {$noJurnal}",
                'debit' => $debit,
                'kredit' => $kredit,
                'referensi_tipe' => $referensiTipe,
                'referensi_id' => $referensiId,
                'user_id' => $userId,
            ];
        }

        // Double-entry balance check
        if (round($totalDebit, 2) !== round($totalKredit, 2)) {
            throw new \Exception(
                'Jurnal tidak balance: debit Rp '.number_format($totalDebit, 2).
                ' ≠ kredit Rp '.number_format($totalKredit, 2)
            );
        }

        if ($totalDebit <= 0) {
            throw new \Exception('Jurnal harus memiliki nilai transaksi > 0');
        }

        return DB::transaction(function () use ($normalized) {
            $created = [];
            foreach ($normalized as $row) {
                $created[] = JurnalAkuntansi::create($row);
            }

            return $created;
        });
    }

    /**
     * Generate nomor jurnal unik per hari.
     */
    public function generateNoJurnal(string $sumber, ?int $cabangId = null): string
    {
        $prefix = strtoupper(substr($sumber, 0, 3));
        $date = now()->format('Ymd');
        // LIKE harus menyertakan cabang agar counter per cabang tidak berbenturan
        $like = sprintf('JRL-%s-%s-%s-%%', $prefix, $cabangId ?? 'X', $date);
        $count = JurnalAkuntansi::where('no_jurnal', 'like', $like)->count() + 1;

        return sprintf('JRL-%s-%s-%s-%04d', $prefix, $cabangId ?? 'X', $date, $count);
    }
}
