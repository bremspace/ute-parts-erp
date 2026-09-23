<?php

namespace App\Modules\Workflow\Observers;

use App\Modules\Wms\Models\ReturnPembelian;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Support\Facades\Log;

/**
 * [F1-1] Auto-fire approval: retur pembelian (ke supplier) > threshold.
 * Data migrasi SID (is_migrasi_sid) dilewati — import mentah pakai DB::table (tanpa event).
 */
class ReturnPembelianObserver
{
    public function created(ReturnPembelian $retur): void
    {
        if ($retur->is_migrasi_sid || (float) $retur->jumlah <= 0) {
            return;
        }

        try {
            $userId = ApprovalService::pemohon();

            if (! $userId) {
                return;
            }

            // Retur pembelian tidak punya cabang_id → derive via PO → gudang tujuan
            $cabangId = $retur->purchaseOrder?->gudangTujuan()->value('cabang_id');

            app(ApprovalService::class)->ajukan('retur_pembelian', $retur->id, $cabangId, [
                'amount' => (float) $retur->jumlah,
                'no_return' => $retur->no_return,
                'tipe' => 'pembelian',
                'purchase_order_id' => $retur->purchase_order_id,
                'supplier_id' => $retur->supplier_id,
                'alasan' => $retur->alasan,
            ], $userId);
        } catch (\Throwable $e) {
            Log::warning('Auto-fire approval retur pembelian gagal: '.$e->getMessage(), ['retur_id' => $retur->id]);
        }
    }
}
