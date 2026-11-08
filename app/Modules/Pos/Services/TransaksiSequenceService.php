<?php

namespace App\Modules\Pos\Services;

use App\Modules\Pos\Models\Transaksi;
use Illuminate\Support\Facades\DB;

/**
 * [Phase 1 / T-02] Atomic sequential invoice number generator.
 * Eliminates concurrency race conditions between parallel cashiers/checkouts.
 */
class TransaksiSequenceService
{
    /**
     * Generate sequential, collision-free transaction number per branch and date.
     * Uses pessimistic lock and existence verification to ensure atomic serializability.
     * Format: {prefix}-C{cabang_id:02d}-{YYYYMMDD}-{seq:04d}
     */
    public function generateNoTransaksi(int $cabangId, string $prefixTag = 'TRX'): string
    {
        $generate = function () use ($cabangId, $prefixTag) {
            $today = now()->format('Ymd');
            $prefix = sprintf('%s-C%02d-%s', strtoupper($prefixTag), $cabangId, $today);
            $like = $prefix.'-%';

            $maxSeq = Transaksi::where('cabang_id', $cabangId)
                ->where('no_transaksi', 'like', $like)
                ->lockForUpdate()
                ->pluck('no_transaksi')
                ->map(function ($no) {
                    $parts = explode('-', (string) $no);

                    return (int) end($parts);
                })
                ->max() ?? 0;

            $nextSeq = $maxSeq + 1;
            $candidate = sprintf('%s-%04d', $prefix, $nextSeq);

            // Resiliency fallback: ensure unique if gap exists
            while (Transaksi::where('no_transaksi', $candidate)->exists()) {
                $nextSeq++;
                $candidate = sprintf('%s-%04d', $prefix, $nextSeq);
            }

            return $candidate;
        };

        return DB::transactionLevel() > 0 ? $generate() : DB::transaction($generate, 3);
    }
}
