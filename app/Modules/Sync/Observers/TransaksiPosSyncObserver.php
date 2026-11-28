<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Sync\Observers\Concerns\SyncsModelEvents;

class TransaksiPosSyncObserver
{
    use SyncsModelEvents;

    private const FINAL_STATUSES = ['selesai', 'lunas', 'paid', 'diproses'];

    private const EXCLUDED_SUMBER = ['marketplace', 'pos_cloud'];

    public function created(Transaksi $transaksi): void
    {
        $sumber = strtolower((string) ($transaksi->sumber ?? 'pos'));
        $status = strtolower((string) $transaksi->status);

        if (in_array($sumber, self::EXCLUDED_SUMBER, true) || str_starts_with((string) $transaksi->no_transaksi, 'VPS-')) {
            return;
        }

        if (! in_array($status, self::FINAL_STATUSES, true)) {
            return;
        }

        $this->enqueueModelEvent(
            $transaksi,
            'transaksi.pos',
            fn (Transaksi $model): array => $this->buildTransaksiPosPayload($model, poinHandledAtSource: true),
            cabangId: $transaksi->cabang_id,
            logLabel: 'TransaksiPosSyncObserver'
        );
    }

    public function updated(Transaksi $transaksi): void
    {
        $sumber = strtolower((string) ($transaksi->sumber ?? 'pos'));
        if (in_array($sumber, self::EXCLUDED_SUMBER, true) || str_starts_with((string) $transaksi->no_transaksi, 'VPS-')) {
            return;
        }

        if (! $transaksi->wasChanged('status')) {
            return;
        }

        $oldStatus = strtolower((string) $transaksi->getOriginal('status'));
        $newStatus = strtolower((string) $transaksi->status);

        if ($newStatus === 'void' && $oldStatus !== 'void') {
            $this->enqueueModelEvent(
                $transaksi,
                'transaksi.void',
                fn (Transaksi $model): array => [
                    'id' => $model->id,
                    'no_transaksi' => $model->no_transaksi,
                    'offline_uuid' => $model->offline_uuid,
                    'cabang_id' => $model->cabang_id,
                    'sumber' => $model->sumber,
                    'void_alasan' => $model->void_alasan,
                    'void_at' => $model->void_at?->toIso8601String(),
                    'void_by_id' => $model->void_by_id,
                ],
                cabangId: $transaksi->cabang_id,
                logLabel: 'TransaksiPosSyncObserver(void)'
            );

            return;
        }

        if (! in_array($oldStatus, self::FINAL_STATUSES, true) && in_array($newStatus, self::FINAL_STATUSES, true)) {
            $this->enqueueModelEvent(
                $transaksi,
                'transaksi.pos',
                fn (Transaksi $model): array => $this->buildTransaksiPosPayload($model, poinHandledAtSource: true),
                cabangId: $transaksi->cabang_id,
                logLabel: 'TransaksiPosSyncObserver(promote)'
            );
        }
    }
}
