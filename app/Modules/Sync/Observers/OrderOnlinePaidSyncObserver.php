<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Sync\Services\HybridSyncOutboxService;
use Illuminate\Support\Facades\Log;

class OrderOnlinePaidSyncObserver
{
    public function updated(Transaksi $transaksi): void
    {
        if (! config('sync.enabled') || config('sync.node_role') !== 'cloud_storefront') {
            return;
        }

        // Cek apakah status berubah menjadi paid / lunas / selesai
        if (! $transaksi->wasChanged('status')) {
            return;
        }

        $newStatus = strtolower((string) $transaksi->status);
        if (! in_array($newStatus, ['paid', 'lunas', 'selesai'], true)) {
            return;
        }

        try {
            $payload = [
                'order_id' => $transaksi->id,
                'nomor_order' => $transaksi->no_transaksi ?? (string) $transaksi->id,
                'total' => $transaksi->total_akhir ?? 0,
                'cabang_id' => $transaksi->cabang_id ?? null,
                'items' => $transaksi->items->map(function (TransaksiItem $item): array {
                    return [
                        'produk_id' => $item->produk_id,
                        'qty' => $item->jumlah,
                        'harga' => $item->harga_satuan,
                    ];
                })->toArray(),
            ];

            app(HybridSyncOutboxService::class)->enqueue(
                'order.online_paid',
                $transaksi,
                $payload,
                $transaksi->cabang_id ?? null
            );
        } catch (\Throwable $e) {
            Log::warning('[HybridSync] Observer OrderOnlinePaid gagal: '.$e->getMessage());
        }
    }
}
