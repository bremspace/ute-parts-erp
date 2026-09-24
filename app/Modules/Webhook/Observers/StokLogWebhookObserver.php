<?php

namespace App\Modules\Webhook\Observers;

use App\Modules\Webhook\Enums\WebhookEvent;
use App\Modules\Webhook\Services\WebhookDispatcher;
use App\Modules\Wms\Models\StokLog;
use Closure;

/**
 * [F3-5] Titik kanonik event `stok.berubah` — observer pada StokLog (log operasional
 * yang dicatat di SEMUA jalur perubahan stok: POS, WMS, servis, GRN, opname, transfer).
 * Satu hook menggantikan menyentuh 10+ titik pembuatan StokLog manual.
 *
 * Cabang di-resolve LAZY (Closure) — hanya query gudang bila ada endpoint berlangganan.
 */
class StokLogWebhookObserver
{
    public function created(StokLog $stokLog): void
    {
        app(WebhookDispatcher::class)->kirim(
            WebhookEvent::StokBerubah->value,
            ['stok_log_id' => (int) $stokLog->id],
            function () use ($stokLog): ?int {
                $cabangId = $stokLog->gudang?->cabang_id;

                return $cabangId !== null ? (int) $cabangId : null;
            }
        );
    }
}
