<?php

namespace App\Modules\Pos\Services;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Database\Eloquent\Model;

class PricingService
{
    /**
     * Resolves final price and breakdown based on customer tier / reseller status.
     * PRD §4.2 Priority:
     * 1. Reseller override (if customer is reseller & override exists)
     * 2. Tier membership price / discount
     * 3. Retail default price
     *
     * @return array{harga: float, harga_dasar: float, diskon_nominal: float, alasan: string, tier: string|null}
     */
    public function resolve(Produk $produk, ?Pelanggan $pelanggan = null, ?SkuVariant $variant = null): array
    {
        $hargaDasar = $variant && $variant->harga_jual_retail !== null
            ? (float) $variant->harga_jual_retail
            : (float) $produk->harga_jual_retail;

        if (!$pelanggan) {
            return [
                'harga' => $hargaDasar,
                'harga_dasar' => $hargaDasar,
                'diskon_nominal' => 0.0,
                'alasan' => 'Harga Retail Standar',
                'tier' => null,
            ];
        }

        $hasDb = Model::getConnectionResolver() !== null;

        // 1. Reseller check
        if ($pelanggan->is_reseller) {
            $hargaReseller = null;
            if ($produk->relationLoaded('hargaTier')) {
                $hargaReseller = $produk->hargaTier->firstWhere('is_reseller', true);
            } elseif ($hasDb && $produk->id) {
                $hargaReseller = HargaTier::where('produk_id', $produk->id)
                    ->where('is_reseller', true)
                    ->first();
            }

            if ($hargaReseller) {
                $finalHarga = (float) $hargaReseller->harga;
                return [
                    'harga' => $finalHarga,
                    'harga_dasar' => $hargaDasar,
                    'diskon_nominal' => max(0, $hargaDasar - $finalHarga),
                    'alasan' => 'Harga Khusus Reseller',
                    'tier' => 'Reseller',
                ];
            }
        }

        // 2. Tier Membership check
        if ($pelanggan->tier_membership_id && $pelanggan->tierMembership) {
            $tier = $pelanggan->tierMembership;

            // Check explicit override in harga_tier
            $tierOverride = null;
            if ($produk->relationLoaded('hargaTier')) {
                $tierOverride = $produk->hargaTier->firstWhere('tier_membership_id', $tier->id);
            } elseif ($hasDb && $produk->id) {
                $tierOverride = HargaTier::where('produk_id', $produk->id)
                    ->where('tier_membership_id', $tier->id)
                    ->first();
            }

            if ($tierOverride) {
                $finalHarga = (float) $tierOverride->harga;
                return [
                    'harga' => $finalHarga,
                    'harga_dasar' => $hargaDasar,
                    'diskon_nominal' => max(0, $hargaDasar - $finalHarga),
                    'alasan' => "Harga Khusus Tier {$tier->nama}",
                    'tier' => $tier->nama,
                ];
            }

            // If no explicit tier override, apply percentage discount from tier
            if ($tier->diskon_persen > 0) {
                $diskonNominal = round(($hargaDasar * (float) $tier->diskon_persen) / 100, 2);
                $finalHarga = max(0, $hargaDasar - $diskonNominal);
                return [
                    'harga' => $finalHarga,
                    'harga_dasar' => $hargaDasar,
                    'diskon_nominal' => $diskonNominal,
                    'alasan' => "Diskon {$tier->diskon_persen}% Tier {$tier->nama}",
                    'tier' => $tier->nama,
                ];
            }
        }

        // 3. Fallback to default retail
        return [
            'harga' => $hargaDasar,
            'harga_dasar' => $hargaDasar,
            'diskon_nominal' => 0.0,
            'alasan' => 'Harga Retail Standar',
            'tier' => null,
        ];
    }
}
