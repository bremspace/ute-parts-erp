<?php

namespace App\Modules\Webhook\Observers;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Webhook\Enums\WebhookEvent;
use App\Modules\Webhook\Services\WebhookDispatcher;

/**
 * [F3-5] Titik kanonik event `transaksi.selesai` — observer model TUNGGAL
 * (meng-cover POS Kasir PosKasir::processTransaction + API POS-01 PosController::store
 *  tanpa hook di 2+ tempat, sesuai aturan "satu titik kanonik").
 */
class TransaksiWebhookObserver
{
    public function created(Transaksi $transaksi): void
    {
        // POS membuat transaksi langsung dgn status 'selesai' (create sekali jadi)
        if ($transaksi->status !== 'selesai') {
            return;
        }

        $this->kirim($transaksi);
    }

    public function updated(Transaksi $transaksi): void
    {
        // Hanya transisi status → 'selesai' (bukan update atribut lain / re-fire)
        if ($transaksi->status !== 'selesai' || ! $transaksi->wasChanged('status')) {
            return;
        }

        $this->kirim($transaksi);
    }

    protected function kirim(Transaksi $transaksi): void
    {
        app(WebhookDispatcher::class)->kirim(
            WebhookEvent::TransaksiSelesai->value,
            [
                'transaksi_id' => (int) $transaksi->id,
                'cabang_id' => $transaksi->cabang_id !== null ? (int) $transaksi->cabang_id : null,
            ],
            $transaksi->cabang_id !== null ? (int) $transaksi->cabang_id : null
        );
    }
}
