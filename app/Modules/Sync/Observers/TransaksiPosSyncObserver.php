<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Sync\Services\HybridSyncOutboxService;
use App\Modules\Sync\Services\SyncExecutionGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransaksiPosSyncObserver
{
    /**
     * Tangkap transaksi POS di node lokal untuk di-enqueue ke outbox sync.
     *
     * Payload memuat dua hal sekaligus:
     * - rekap lengkap (items, HPP, split) untuk jalur rekap di cloud;
     * - ringkasan omzet (source_node, kasir/pelanggan nama, jumlah_item,
     *   diskon_total, paid_at) untuk tabel hybrid_sync_pos_transaksi.
     */
    public function created(Transaksi $transaksi): void
    {
        if (SyncExecutionGuard::isSuppressed() || ! config('sync.enabled') || config('sync.node_role') !== 'primary_store') {
            return;
        }

        // Hanya transaksi toko/kasir. Order marketplace & channel lain milik cloud.
        $sumber = strtolower((string) ($transaksi->sumber ?? 'pos'));
        if ($transaksi->sumber !== null && ! in_array($sumber, ['pos', 'kasir', 'toko'], true)) {
            return;
        }

        // Tunggu hingga transaksi database selesai di-commit agar seluruh TransaksiItem terisi
        $enqueueAction = function () use ($transaksi, $sumber) {
            try {
                $transaksi->refresh();
                $transaksi->loadMissing('items');
                $transaksi->loadMissing('kasir');
                $transaksi->loadMissing('pelanggan');

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

                $noTransaksi = $transaksi->no_transaksi ?? (string) $transaksi->id;

                $payload = [
                    'id' => $transaksi->id,
                    'no_transaksi' => $noTransaksi,
                    'nomor_transaksi' => $noTransaksi,
                    'offline_uuid' => $transaksi->offline_uuid ?: (string) Str::uuid(),
                    'source_node' => (string) config('sync.node_id', 'STORE-01'),
                    'sumber' => $sumber,
                    'status' => strtolower((string) $transaksi->status),
                    'cabang_id' => $transaksi->cabang_id,
                    'kasir_id' => $transaksi->kasir_id,
                    'kasir_nama' => $transaksi->kasir?->name ?? null,
                    'pelanggan_id' => $transaksi->pelanggan_id,
                    'pelanggan_nama' => $transaksi->pelanggan?->nama ?? null,
                    'gudang_id' => $transaksi->gudang_id,
                    'subtotal' => (float) $transaksi->subtotal,
                    'diskon_nominal' => (float) $transaksi->diskon_nominal,
                    'diskon_total' => (float) (($transaksi->diskon_nominal ?? 0) + ($transaksi->diskon_poin ?? 0) + ($transaksi->diskon_partai ?? 0)),
                    'pajak_nominal' => (float) ($transaksi->pajak_nominal ?? $transaksi->ppn_nominal ?? 0),
                    'total_akhir' => (float) $transaksi->total_akhir,
                    'total' => (float) $transaksi->total_akhir,
                    'metode_bayar' => $transaksi->metode_bayar,
                    'jumlah_bayar' => (float) $transaksi->jumlah_bayar,
                    'kembalian' => (float) $transaksi->kembalian,
                    'split_detail' => $transaksi->split_detail,
                    'catatan' => $transaksi->catatan,
                    'jumlah_item' => (int) $transaksi->items->count(),
                    'created_at' => $transaksi->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'transaksi_created_at' => $transaksi->created_at?->toIso8601String(),
                    'paid_at' => $transaksi->paid_at?->toIso8601String(),
                    'items' => $items,
                ];

                app(HybridSyncOutboxService::class)->enqueue(
                    'transaksi.pos',
                    $transaksi,
                    $payload,
                    $transaksi->cabang_id
                );
            } catch (\Throwable $e) {
                Log::warning('[HybridSync] Observer TransaksiPos gagal: '.$e->getMessage());
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($enqueueAction);
        } else {
            $enqueueAction();
        }
    }
}
