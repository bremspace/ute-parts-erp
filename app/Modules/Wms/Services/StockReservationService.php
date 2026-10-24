<?php

namespace App\Modules\Wms\Services;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokTransfer;
use Illuminate\Support\Facades\DB;

/**
 * StockReservationService — sentralisasi kalkulasi reservasi stok marketplace & stok tersedia cabang/gudang.
 */
class StockReservationService
{
    /**
     * Hitung total reservasi aktif marketplace untuk kombinasi produk, varian, dan cabang.
     * Status menunggu_pembayaran dan belum expired (expires_at > now() atau fallback updated_at jika expires_at null).
     *
     * @param  int|null  $excludeTransaksiId  ID transaksi yang dikecualikan (misal transaksi itu sendiri saat bayar)
     */
    public function getActiveReservation(
        int $cabangId,
        int $produkId,
        ?int $skuVariantId = null,
        ?int $excludeTransaksiId = null
    ): int {
        $now = now();
        $fallbackCutoff = now()->subMinutes((int) config('marketplace.reservation_minutes', 60));

        $query = DB::table('transaksi_item')
            ->join('transaksi', 'transaksi.id', '=', 'transaksi_item.transaksi_id')
            ->where('transaksi.cabang_id', $cabangId)
            ->where('transaksi.sumber', 'marketplace')
            ->where('transaksi.status', 'menunggu_pembayaran')
            ->where('transaksi_item.produk_id', $produkId)
            ->where(function ($q) use ($now, $fallbackCutoff) {
                $q->where(function ($sub) use ($now) {
                    $sub->whereNotNull('transaksi.expires_at')
                        ->where('transaksi.expires_at', '>', $now);
                })->orWhere(function ($sub) use ($fallbackCutoff) {
                    $sub->whereNull('transaksi.expires_at')
                        ->where('transaksi.updated_at', '>', $fallbackCutoff);
                });
            });

        if ($skuVariantId !== null) {
            $query->where('transaksi_item.sku_variant_id', $skuVariantId);
        }

        if ($excludeTransaksiId !== null) {
            $query->where('transaksi.id', '!=', $excludeTransaksiId);
        }

        // Hitung total kuantitas dasar (jumlah * konversi)
        $total = $query->sum(DB::raw('transaksi_item.jumlah * COALESCE(transaksi_item.konversi, 1)'));

        return (int) round((float) $total);
    }

    /**
     * Hitung reservasi aktif marketplace per gudang tertentu.
     * Menggunakan proporsi atau alokasi cabang.
     * Karena order marketplace diikat ke cabang_id, reservasi marketplace berlaku di level cabang.
     * Untuk gudang tertentu, reservasi aktif cabang mengurangi stok yang dapat dijual di cabang tsb.
     */
    public function getActiveReservationForCabang(
        int $cabangId,
        int $produkId,
        ?int $skuVariantId = null,
        ?int $excludeTransaksiId = null
    ): int {
        return $this->getActiveReservation($cabangId, $produkId, $skuVariantId, $excludeTransaksiId);
    }

    /**
     * Kunci baris stok untuk gudang-gudang pada cabang, dan hitung stok tersedia bersih.
     * Stok tersedia = Stok fisik - Pending transfer lock - Active online reservations.
     */
    public function getAvailableStockForOrder(
        int $cabangId,
        int $produkId,
        ?int $skuVariantId = null,
        bool $lock = false
    ): int {
        $gudangIds = Gudang::where('cabang_id', $cabangId)->pluck('id');

        $query = StokItem::whereIn('gudang_id', $gudangIds)
            ->where('produk_id', $produkId);

        if ($skuVariantId !== null) {
            $query->where('sku_variant_id', $skuVariantId);
        }

        if ($lock) {
            $stokItems = $query->lockForUpdate()->get();
        } else {
            $stokItems = $query->get();
        }

        $physicalStock = (int) $stokItems->sum('jumlah');

        // Pending transfer lock
        $transferLocked = 0;
        foreach ($stokItems as $item) {
            $transferLocked += StokTransfer::pendingLockedFor($item);
        }

        // Active marketplace reservation
        $reserved = $this->getActiveReservation($cabangId, $produkId, $skuVariantId);

        return max(0, $physicalStock - $transferLocked - $reserved);
    }
}
