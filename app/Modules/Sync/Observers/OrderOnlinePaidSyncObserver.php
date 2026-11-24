<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;

class OrderOnlinePaidSyncObserver
{
    use SyncsModelEvents;

    private const PAID_STATUSES = ['paid', 'lunas', 'selesai'];

    public function created(Transaksi $transaksi): void
    {
        if (config('sync.node_role') !== 'cloud_storefront') {
            return;
        }

        if (strtolower((string) $transaksi->sumber) !== 'marketplace') {
            return;
        }

        $status = strtolower((string) $transaksi->status);
        if (! in_array($status, self::PAID_STATUSES, true)) {
            return;
        }

        $this->enqueueOrderPaid($transaksi);
    }

    public function updated(Transaksi $transaksi): void
    {
        if (config('sync.node_role') !== 'cloud_storefront') {
            return;
        }

        if (strtolower((string) $transaksi->sumber) !== 'marketplace') {
            return;
        }

        if (! $transaksi->wasChanged('status')) {
            return;
        }

        $oldStatus = strtolower((string) $transaksi->getOriginal('status'));
        $newStatus = strtolower((string) $transaksi->status);

        // Hanya kirim event sekali saat transisi unpaid -> paid/lunas/selesai
        // agar lunas -> selesai saat pengiriman tidak mengirim order.online_paid ganda.
        if (in_array($oldStatus, self::PAID_STATUSES, true)) {
            return;
        }

        if (! in_array($newStatus, self::PAID_STATUSES, true)) {
            return;
        }

        $this->enqueueOrderPaid($transaksi);
    }

    private function enqueueOrderPaid(Transaksi $transaksi): void
    {
        $this->enqueueModelEvent(
            $transaksi,
            'order.online_paid',
            fn (Transaksi $model): array => $this->buildTransaksiPosPayload($model, poinHandledAtSource: true),
            cabangId: $transaksi->cabang_id,
            logLabel: 'OrderOnlinePaidSyncObserver'
        );
    }
}
