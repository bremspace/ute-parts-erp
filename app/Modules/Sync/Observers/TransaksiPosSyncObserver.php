<?php

namespace App\Modules\Sync\Observers;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Sync\Services\HybridSyncOutboxService;
use Illuminate\Support\Facades\Log;

class TransaksiPosSyncObserver
{
    /**
     * Kirim ringkasan transaksi kasir lokal ke cloud untuk agregasi omzet.
     * Hanya transaksi toko fisik (POS/servis/offline) yang final yang dikirim,
     * agar order marketplace dari cloud tidak dihitung dua kali.
     */
    public function created(Transaksi $transaksi): void
    {
        $this->sync($transaksi);
    }

    public function updated(Transaksi $transaksi): void
    {
        if (! $transaksi->wasChanged('status')) {
            return;
        }

        $this->sync($transaksi);
    }

    protected function sync(Transaksi $transaksi): void
    {
        if (! config('sync.enabled') || config('sync.node_role') !== 'primary_store') {
            return;
        }

        $status = strtolower((string) $transaksi->status);
        if (! in_array($status, ['selesai', 'paid', 'lunas'], true)) {
            return;
        }

        $sumber = strtolower((string) ($transaksi->sumber ?? 'pos'));
        if (str_starts_with($sumber, 'channel:') || in_array($sumber, ['marketplace', 'online'], true)) {
            return;
        }

        try {
            $payload = [
                'id' => $transaksi->id,
                'no_transaksi' => $transaksi->no_transaksi ?? (string) $transaksi->id,
                'nomor_transaksi' => $transaksi->no_transaksi ?? (string) $transaksi->id,
                'source_node' => (string) config('sync.node_id', 'STORE-01'),
                'sumber' => $sumber ?: 'pos',
                'status' => $status,
                'cabang_id' => $transaksi->cabang_id ?? null,
                'kasir_id' => $transaksi->kasir_id ?? null,
                'kasir_nama' => $transaksi->kasir?->name ?? null,
                'pelanggan_id' => $transaksi->pelanggan_id ?? null,
                'pelanggan_nama' => $transaksi->pelanggan?->nama ?? null,
                'subtotal' => (float) ($transaksi->subtotal ?? 0),
                'diskon_total' => (float) (($transaksi->diskon_nominal ?? 0) + ($transaksi->diskon_poin ?? 0) + ($transaksi->diskon_partai ?? 0)),
                'pajak_nominal' => (float) ($transaksi->pajak_nominal ?? $transaksi->ppn_nominal ?? 0),
                'total' => (float) ($transaksi->total_akhir ?? 0),
                'total_akhir' => (float) ($transaksi->total_akhir ?? 0),
                'metode_bayar' => $transaksi->metode_bayar ?? null,
                'jumlah_item' => (int) ($transaksi->items()->count() ?? 0),
                'transaksi_created_at' => optional($transaksi->created_at)->toIso8601String(),
                'paid_at' => optional($transaksi->paid_at)->toIso8601String(),
            ];

            app(HybridSyncOutboxService::class)->enqueue(
                'transaksi.pos',
                $transaksi,
                $payload,
                $transaksi->cabang_id ?? null
            );
        } catch (\Throwable $e) {
            Log::warning('[HybridSync] Observer TransaksiPos gagal: '.$e->getMessage());
        }
    }
}
