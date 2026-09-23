<?php

namespace App\Modules\Workflow\Observers;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Support\Facades\Log;

/**
 * [F1-1] Auto-fire approval: diskon besar.
 * Amount = diskon_nominal + (subtotal × diskon_persen / 100).
 * Terdaftar via AppServiceProvider — tanpa menyentuh PosKasir/PosController.
 */
class TransaksiObserver
{
    public function created(Transaksi $transaksi): void
    {
        try {
            $diskon = (float) $transaksi->diskon_nominal
                + round(((float) $transaksi->subtotal * (float) $transaksi->diskon_persen) / 100, 2);

            if ($diskon <= 0) {
                return;
            }

            $userId = ApprovalService::pemohon($transaksi->kasir_id);

            if (! $userId) {
                return;
            }

            app(ApprovalService::class)->ajukan('diskon', $transaksi->id, $transaksi->cabang_id, [
                'amount' => $diskon,
                'no_transaksi' => $transaksi->no_transaksi,
                'subtotal' => (float) $transaksi->subtotal,
                'diskon_persen' => (float) $transaksi->diskon_persen,
                'diskon_nominal' => (float) $transaksi->diskon_nominal,
            ], $userId);
        } catch (\Throwable $e) {
            // Observer tidak boleh mematikan save transaksi
            Log::warning('Auto-fire approval diskon gagal: '.$e->getMessage(), ['transaksi_id' => $transaksi->id]);
        }
    }
}
