<?php

namespace App\Modules\Pos\Services;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;

/**
 * Resolusi harga POS / Marketplace / PRICING-01.
 *
 * [T-44] Method ini DELEGASI ke PelangganService::resolveHarga() — satu sumber
 * kebenaran harga (harga_tier per tipe_konsumen + tier membership + harga jual).
 * Bentuk respons dipertahankan identik agar seluruh pemakaian existing
 * (PosController, PosKasir, ShopController) tidak berubah.
 */
class PricingService
{
    /**
     * Resolves final price and breakdown based on customer tier / consumer type.
     * PRD §4.2 — prioritas lihat PelangganService::resolveHarga().
     *
     * @return array{harga: float, harga_dasar: float, diskon_nominal: float, alasan: string, tier: string|null}
     */
    public function resolve(Produk $produk, ?Pelanggan $pelanggan = null, ?SkuVariant $variant = null): array
    {
        return app(PelangganService::class)->resolveHarga($produk, $pelanggan, $variant);
    }
}
