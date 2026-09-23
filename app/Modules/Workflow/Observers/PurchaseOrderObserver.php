<?php

namespace App\Modules\Workflow\Observers;

use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Support\Facades\Log;

/**
 * [F1-1] Auto-fire approval: PO > threshold.
 * Terdaftar via AppServiceProvider — terlepas dari komponen (Livewire/Controller)
 * yang menyimpan PO, observer tetap menyala.
 */
class PurchaseOrderObserver
{
    public function saved(PurchaseOrder $po): void
    {
        if (! $po->wasRecentlyCreated && ! $po->wasChanged('total')) {
            return;
        }

        if ((float) $po->total <= 0 || in_array($po->status, ['dibatalkan', 'usulan'], true)) {
            return;
        }

        try {
            $userId = ApprovalService::pemohon();

            if (! $userId) {
                return;
            }

            // PO tidak punya cabang_id → derive via gudang tujuan
            $cabangId = $po->gudangTujuan()->value('cabang_id');

            app(ApprovalService::class)->ajukan('po', $po->id, $cabangId, [
                'amount' => (float) $po->total,
                'no_po' => $po->no_po,
                'status' => $po->status,
                'supplier_id' => $po->supplier_id,
            ], $userId);
        } catch (\Throwable $e) {
            // Observer tidak boleh mematikan save PO
            Log::warning('Auto-fire approval PO gagal: '.$e->getMessage(), ['po_id' => $po->id]);
        }
    }
}
