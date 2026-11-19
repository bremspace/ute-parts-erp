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
    public function resolve(Produk $produk, ?Pelanggan $pelanggan = null, ?SkuVariant $variant = null, ?string $overrideModeReward = null): array
    {
        return app(PelangganService::class)->resolveHarga($produk, $pelanggan, $variant, $overrideModeReward);
    }

    /**
     * [SECURITY] Resolusi harga server-side per satuan terpilih (pcs/box/dll).
     * Harga SELALU diturunkan dari data produk (daftar satuan + pricing tier),
     * TIDAK PERNAH dari input client — mencegah manipulasi harga checkout.
     * Satu sumber kebenaran dipakai CartService (tampilan) & OrderService (tagihan),
     * sehingga nominal cart dan checkout tidak pernah berbeda.
     *
     * Harga dikembalikan per satuan DASAR (base unit); untuk satuan konversi (mis. box)
     * harga = harga_jual satuan / faktor_konversi. Satuan tak dikenal -> harga dasar.
     *
     * @return array{harga: float, faktor_konversi: float, satuan: string, pricing: array}
     */
    public function resolveHargaSatuan(Produk $produk, ?Pelanggan $pelanggan = null, ?SkuVariant $variant = null, ?string $satuan = null): array
    {
        $pricing = $this->resolve($produk, $pelanggan, $variant);
        $baseSatuan = $produk->satuan ?: 'pcs';
        $cleanSatuan = $satuan !== null ? trim($satuan) : '';
        $faktor = 1.0;
        $hargaPerBase = (float) $pricing['harga'];

        if ($cleanSatuan !== '' && strcasecmp($cleanSatuan, $baseSatuan) !== 0) {
            foreach ($produk->getDaftarSatuan() as $s) {
                if (strcasecmp($s['satuan_kode'], $cleanSatuan) === 0) {
                    $faktor = (float) ($s['faktor_konversi'] ?? 1.0);
                    if (! empty($s['harga_jual'])) {
                        $hargaPerBase = (float) $s['harga_jual'] / max(0.0001, $faktor);
                    }
                    break;
                }
            }
        }

        return [
            'harga' => $hargaPerBase,
            'faktor_konversi' => $faktor,
            'satuan' => $cleanSatuan !== '' ? $cleanSatuan : $baseSatuan,
            'pricing' => $pricing,
        ];
    }
}
