<?php

namespace App\Modules\Wms\Services;

use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use Illuminate\Support\Facades\DB;

/**
 * Reusable stock deduction + audit log (PRD StokLog).
 * Dipakai POS, transfer, servis, dan pembayaran marketplace lunas.
 */
class StokDeductionService
{
    /**
     * Kurangi stok produk di sebuah gudang, catat StokLog.
     *
     * @throws \Exception jika stok tidak mencukupi
     */
    public function kurangi(
        int $produkId,
        ?int $skuVariantId,
        int $gudangId,
        int $qty,
        string $jenis,
        string $referensiTipe,
        ?int $referensiId,
        ?int $userId,
        ?string $catatan = null
    ): StokItem {
        if ($qty <= 0) {
            throw new \Exception('Kuantitas stok harus > 0');
        }

        $stok = StokItem::where('produk_id', $produkId)
            ->where('sku_variant_id', $skuVariantId)
            ->where('gudang_id', $gudangId)
            ->lockForUpdate()
            ->first();

        if (!$stok || $stok->jumlah < $qty) {
            throw new \Exception("Stok tidak mencukupi (produk ID {$produkId}, tersedia: " . ($stok?->jumlah ?? 0) . ")");
        }

        $sebelum = $stok->jumlah;
        $setelah = $sebelum - $qty;
        $stok->update(['jumlah' => $setelah]);

        StokLog::create([
            'gudang_id'      => $gudangId,
            'produk_id'      => $produkId,
            'sku_variant_id' => $skuVariantId,
            'user_id'        => $userId,
            'jenis'          => $jenis,
            'referensi_tipe' => $referensiTipe,
            'referensi_id'   => $referensiId,
            'jumlah_sebelum' => $sebelum,
            'perubahan'      => -$qty,
            'jumlah_setelah' => $setelah,
            'catatan'        => $catatan,
        ]);

        return $stok;
    }

    /**
     * Kurangi stok dari gudang yang memiliki cukup — fallback multi-gudang per cabang.
     * Untuk order marketplace: pecah pengurangan ke beberapa gudang bila perlu.
     *
     * @return array list StokLog terbuat
     */
    public function kurangiDariGudangTersedia(
        int $produkId,
        ?int $skuVariantId,
        int $qty,
        int $cabangId,
        string $jenis,
        string $referensiTipe,
        ?int $referensiId,
        ?int $userId,
        ?string $catatan = null
    ): array {
        $gudangIds = \App\Modules\Wms\Models\Gudang::where('cabang_id', $cabangId)->pluck('id');
        $tersisa = $qty;
        $logs = [];

        DB::transaction(function () use ($produkId, $skuVariantId, $cabangId, $qty, $jenis, $referensiTipe, $referensiId, $userId, $catatan, $gudangIds, &$tersisa, &$logs) {
            // Prioritaskan gudang dengan stok cukup paling besar terlebih dahulu
            $stoks = StokItem::where('produk_id', $produkId)
                ->whereIn('gudang_id', $gudangIds)
                ->where('jumlah', '>', 0)
                ->orderByDesc('jumlah')
                ->get();

            foreach ($stoks as $stok) {
                if ($tersisa <= 0) {
                    break;
                }

                $ambil = min($stok->jumlah, $tersisa);
                $log = $this->kurangi(
                    $produkId,
                    $skuVariantId,
                    $stok->gudang_id,
                    $ambil,
                    $jenis,
                    $referensiTipe,
                    $referensiId,
                    $userId,
                    $catatan
                );
                $logs[] = $log;
                $tersisa -= $ambil;
            }

            if ($tersisa > 0) {
                throw new \Exception("Stok tidak mencukupi di cabang (kurang {$tersisa} unit)");
            }
        });

        return $logs;
    }
}