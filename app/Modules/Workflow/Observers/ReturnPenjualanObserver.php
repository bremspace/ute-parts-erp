<?php

namespace App\Modules\Workflow\Observers;

use App\Modules\Pos\Models\ReturnPenjualan;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Support\Facades\Log;

/**
 * [F1-1] Auto-fire approval: retur penjualan > threshold.
 * Data migrasi SID (is_migrasi_sid) dilewati — import mentah pakai DB::table (tanpa event).
 */
class ReturnPenjualanObserver
{
    public function created(ReturnPenjualan $retur): void
    {
        if ($retur->is_migrasi_sid || (float) $retur->jumlah <= 0) {
            return;
        }

        try {
            $userId = ApprovalService::pemohon($retur->transaksi?->kasir_id);

            if (! $userId) {
                return;
            }

            app(ApprovalService::class)->ajukan('retur', $retur->id, $retur->transaksi?->cabang_id, [
                'amount' => (float) $retur->jumlah,
                'no_return' => $retur->no_return,
                'tipe' => 'penjualan',
                'transaksi_id' => $retur->transaksi_id,
                'alasan' => $retur->alasan,
            ], $userId);
        } catch (\Throwable $e) {
            Log::warning('Auto-fire approval retur penjualan gagal: '.$e->getMessage(), ['retur_id' => $retur->id]);
        }
    }
}
