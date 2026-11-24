<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Sync\Services\SyncExecutionGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransaksiCloudPosSyncObserver
{
    /**
     * Tangkap pembuatan transaksi kasir darurat di node cloud untuk dikirim ke PC toko saat pull.
     */
    public function created(Transaksi $transaksi): void
    {
        if (SyncExecutionGuard::isSuppressed() || ! config('sync.enabled') || config('sync.node_role') !== 'cloud_storefront') {
            return;
        }

        // Hanya transaksi POS kasir darurat di cloud (bukan order online / e-commerce)
        // Ciri: sumber 'pos', 'kasir', 'toko', atau no_transaksi berawalan 'VPS-'
        $isCloudPos = in_array($transaksi->sumber, ['pos', 'kasir', 'toko'], true)
            || str_starts_with((string) $transaksi->no_transaksi, 'VPS-');

        if (! $isCloudPos) {
            return;
        }

        $enqueueAction = function () use ($transaksi) {
            try {
                $transaksi->refresh();
                $transaksi->loadMissing('items');

                $items = $transaksi->items->map(function ($item) {
                    return [
                        'produk_id' => $item->produk_id,
                        'sku_variant_id' => $item->sku_variant_id,
                        'jumlah' => (float) $item->jumlah,
                        'satuan' => $item->satuan,
                        'konversi' => (float) ($item->konversi ?? 1),
                        'harga_satuan' => (float) $item->harga_satuan,
                        'diskon_nominal' => (float) $item->diskon_nominal,
                        'subtotal' => (float) $item->subtotal,
                        'hpp' => (float) $item->hpp,
                        'catatan' => $item->catatan,
                    ];
                })->all();

                $payload = [
                    'id' => $transaksi->id,
                    'no_transaksi' => $transaksi->no_transaksi,
                    'offline_uuid' => $transaksi->offline_uuid ?: (string) Str::uuid(),
                    'cabang_id' => $transaksi->cabang_id,
                    'kasir_id' => $transaksi->kasir_id,
                    'pelanggan_id' => $transaksi->pelanggan_id,
                    'gudang_id' => $transaksi->gudang_id,
                    'sumber' => $transaksi->sumber ?? 'pos',
                    'subtotal' => (float) $transaksi->subtotal,
                    'diskon_nominal' => (float) $transaksi->diskon_nominal,
                    'pajak_nominal' => (float) $transaksi->pajak_nominal,
                    'total_akhir' => (float) $transaksi->total_akhir,
                    'metode_bayar' => $transaksi->metode_bayar,
                    'jumlah_bayar' => (float) $transaksi->jumlah_bayar,
                    'kembalian' => (float) $transaksi->kembalian,
                    'split_detail' => $transaksi->split_detail,
                    'status' => $transaksi->status,
                    'catatan' => $transaksi->catatan,
                    'items' => $items,
                    'created_at' => $transaksi->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ];

                app(HybridSyncOutboxService::class)->enqueue(
                    'transaksi.pos_cloud',
                    $transaksi,
                    $payload,
                    $transaksi->cabang_id
                );
            } catch (\Throwable $e) {
                Log::warning('[HybridSync] Observer TransaksiCloudPos gagal: '.$e->getMessage());
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($enqueueAction);
        } else {
            $enqueueAction();
        }
    }
}
