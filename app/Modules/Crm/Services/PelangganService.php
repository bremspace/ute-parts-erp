<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

/**
 * [T-36] Single source of truth untuk pembuatan pelanggan (CRM-06, POS quick-add, Servis).
 * Aturan validasi + default tier terendah + is_reseller=false dipusatkan di sini agar
 * semua entry point berperilaku identik.
 *
 * [T-44] resolveHarga() — SATU sumber kebenaran harga jual produk per pelanggan.
 * Semua jalur harga (POS API, POS Livewire, Marketplace, PRICING-01) mengarah ke sini
 * (PricingService mendelegasikan ke method ini).
 *
 * Prioritas resolusi (tidak mengubah harga existing):
 *  1. HargaTier baru eksak  : (produk, sku_variant?, tier pelanggan, tipe_konsumen) → nominal_tetap | harga_jual*(1-persen/100)
 *  2. Legacy reseller       : harga_tier.is_reseller=1 → `harga` (backward-compatible)
 *  3. Legacy tier override  : harga_tier.tier_membership_id = tier pelanggan → `harga`
 *  4. Diskon tier CRM       : tierMembership.diskon_persen → harga_jual*(1-diskon/100)
 *  5. HargaTier tipe-default: (produk, tier NULL, tipe_konsumen) → nominal_tetap | persen_diskon
 *  6. Fallback              : harga_jual_retail (variant lebih dulu)
 */
class PelangganService
{
    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:255',
            'telepon' => 'required|string|max:20|unique:pelanggan,telepon',
            'email' => 'nullable|email|unique:pelanggan,email',
            'alamat' => 'nullable|string',
            'tanggal_lahir' => 'nullable|date',
            'is_reseller' => 'sometimes|boolean',
            'tipe_konsumen' => 'sometimes|nullable|in:retail,reseller,agen',
            'tier_membership_id' => 'sometimes|nullable|exists:tier_memberships,id',
        ];
    }

    public function create(array $data): Pelanggan
    {
        $validated = Validator::make($data, $this->rules())->validate();

        $tierTerendah = TierMembership::where('is_active', true)
            ->orderBy('min_belanja_12bulan')
            ->first();

        $isReseller = (bool) ($validated['is_reseller'] ?? false);
        $tipeKonsumen = $validated['tipe_konsumen'] ?? ($isReseller ? 'reseller' : 'retail');

        $pelanggan = Pelanggan::create([
            'nama' => $validated['nama'],
            'telepon' => $validated['telepon'],
            'email' => $validated['email'] ?? null,
            'alamat' => $validated['alamat'] ?? null,
            'tanggal_lahir' => $validated['tanggal_lahir'] ?? null,
            'tier_membership_id' => $validated['tier_membership_id'] ?? $tierTerendah?->id,
            'is_reseller' => $isReseller,
            'tipe_konsumen' => $tipeKonsumen,
            'total_belanja_12bulan' => 0,
            'poin_loyalty' => 0,
        ]);

        return $pelanggan->load('tierMembership');
    }

    /**
     * Tipe konsumen efektif pelanggan (retail|reseller|agen).
     * Kolom baru `tipe_konsumen` (default retail); fallback legacy is_reseller.
     */
    public function tipeKonsumen(?Pelanggan $pelanggan): string
    {
        if (! $pelanggan) {
            return 'retail';
        }
        $tipe = $pelanggan->tipe_konsumen;
        if (! $tipe) {
            $tipe = $pelanggan->is_reseller ? 'reseller' : 'retail';
        }

        return in_array($tipe, ['retail', 'reseller', 'agen'], true) ? $tipe : 'retail';
    }

    /**
     * Resolusi harga final per produk + pelanggan (SATU SUMBER KEBENARAN).
     *
     * @return array{harga: float, harga_dasar: float, diskon_nominal: float, alasan: string, tier: string|null}
     */
    public function resolveHarga(Produk $produk, ?Pelanggan $pelanggan = null, ?SkuVariant $variant = null): array
    {
        $hargaDasar = $variant && $variant->harga_jual_retail !== null
            ? (float) $variant->harga_jual_retail
            : (float) $produk->harga_jual_retail;

        if (! $pelanggan) {
            return [
                'harga' => $hargaDasar,
                'harga_dasar' => $hargaDasar,
                'diskon_nominal' => 0.0,
                'alasan' => 'Harga Retail Standar',
                'tier' => null,
            ];
        }

        $hasDb = Model::getConnectionResolver() !== null;
        $rows = $this->rowsHargaTier($produk, $variant, $hasDb);
        $tipe = $this->tipeKonsumen($pelanggan);

        // 1. HargaTier baru — eksak (produk [+ varian], tier pelanggan, tipe_konsumen)
        if ($pelanggan->tier_membership_id) {
            $eksak = $this->filterRows($rows, tierId: $pelanggan->tier_membership_id, tipe: $tipe)
                ->first(fn ($r) => $r->nominal_tetap !== null || $r->persen_diskon !== null);
            if ($eksak) {
                return $this->hitungDariTierBaru($eksak, $hargaDasar, 'Harga Khusus Tier ', $pelanggan->tierMembership?->nama);
            }
        }

        // 2. Legacy reseller (harga_tier.is_reseller=1 → `harga`), hanya bila pelanggan reseller
        if ($pelanggan->is_reseller) {
            $reseller = $rows->first(fn ($r) => $r->is_reseller === true && $r->tier_membership_id === null);
            if ($reseller && $reseller->harga !== null) {
                $final = (float) $reseller->harga;
                // Baris legacy yang sudah di-seed nominal_tetap (placeholder) tidak boleh
                // mengalahkan harga legacy — `harga` adalah sumber untuk jalur legacy.
                if ($final > 0) {
                    return [
                        'harga' => $final,
                        'harga_dasar' => $hargaDasar,
                        'diskon_nominal' => max(0, $hargaDasar - $final),
                        'alasan' => 'Harga Khusus Reseller',
                        'tier' => 'Reseller',
                    ];
                }
            }
        }

        // 3. Legacy tier override (harga_tier.tier_membership_id = tier pelanggan → `harga`)
        if ($pelanggan->tier_membership_id) {
            $tierOverride = $this->filterRows($rows, tierId: $pelanggan->tier_membership_id)
                ->first(fn ($r) => $r->harga !== null && $r->nominal_tetap === null && $r->persen_diskon === null);
            if ($tierOverride) {
                $final = (float) $tierOverride->harga;

                return [
                    'harga' => $final,
                    'harga_dasar' => $hargaDasar,
                    'diskon_nominal' => max(0, $hargaDasar - $final),
                    'alasan' => 'Harga Khusus Tier '.($pelanggan->tierMembership?->nama ?? ''),
                    'tier' => $pelanggan->tierMembership?->nama,
                ];
            }
        }

        // 4. Diskon tier CRM (diskon_persen) — perilaku existing dipertahankan
        $tier = $pelanggan->relationLoaded('tierMembership')
            ? $pelanggan->tierMembership
            : ($hasDb ? $pelanggan->tierMembership : null);
        if ($tier && (float) $tier->diskon_persen > 0) {
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

        // 5. HargaTier tipe-default (produk, tier NULL, tipe_konsumen) — [T-44]
        $tipeDefault = $this->filterRows($rows, tierId: null, tipe: $tipe)
            ->first(fn ($r) => ($r->nominal_tetap !== null || $r->persen_diskon !== null)
                && $r->is_reseller !== true); // baris legacy reseller = bukan harga tipe-default
        if ($tipeDefault) {
            // Nominal placeholder hasil seed = harga_jual → sama dengan fallback; tetap dievaluasi
            // agar struktur harga per tipe_konsumen konsisten (harga tidak berubah vs perilaku lama).
            return $this->hitungDariTierBaru($tipeDefault, $hargaDasar, 'Harga Khusus ', null);
        }

        // 6. Fallback retail
        return [
            'harga' => $hargaDasar,
            'harga_dasar' => $hargaDasar,
            'diskon_nominal' => 0.0,
            'alasan' => 'Harga Retail Standar',
            'tier' => null,
        ];
    }

    /**
     * Kumpulkan baris HargaTier produk (via relasi bila sudah di-load, else DB bila tersedia).
     */
    protected function rowsHargaTier(Produk $produk, ?SkuVariant $variant, bool $hasDb): Collection
    {
        $rows = collect();
        if ($produk->relationLoaded('hargaTier')) {
            $rows = $produk->hargaTier;
        } elseif ($hasDb && $produk->id) {
            $query = HargaTier::where('produk_id', $produk->id);
            if ($variant && $variant->id) {
                $query->where(function ($q) use ($variant) {
                    $q->whereNull('sku_variant_id')->orWhere('sku_variant_id', $variant->id);
                });
            }
            $rows = $query->get();
        }
        if ($variant && $variant->id && $rows->count()) {
            // Prioritaskan baris spesifik varian di atas baris produk-umum
            $spesifik = $rows->where('sku_variant_id', $variant->id)->values();
            $umum = $rows->whereNull('sku_variant_id')->values();

            return $spesifik->count() ? $spesifik->merge($umum) : $rows;
        }

        return $rows;
    }

    protected function filterRows(Collection $rows, ?int $tierId = null, ?string $tipe = null): Collection
    {
        return $rows->filter(function ($r) use ($tierId, $tipe) {
            // tier: tierId null → hanya baris tanpa tier (tipe-default); non-null → eksak
            $tierMatch = $tierId === null
                ? ($r->tier_membership_id === null)
                : ((int) $r->tier_membership_id === $tierId);
            $tipeMatch = $tipe === null || ($r->tipe_konsumen ?: 'retail') === $tipe;

            return $tierMatch && $tipeMatch;
        });
    }

    protected function hitungDariTierBaru(HargaTier $row, float $hargaDasar, string $alasanPrefix, ?string $tierNama): array
    {
        if ($row->nominal_tetap !== null) {
            $final = (float) $row->nominal_tetap;
            $alasan = $alasanPrefix.($tierNama ?? $this->tipeLabel($row->tipe_konsumen));
            $tier = $tierNama ?? ($row->tipe_konsumen === 'reseller' ? 'Reseller' : null);
        } else {
            $final = round($hargaDasar * (1 - (float) ($row->persen_diskon ?? 0) / 100), 2);
            $alasan = $alasanPrefix.($tierNama ?? $this->tipeLabel($row->tipe_konsumen));
            $tier = $tierNama ?? ($row->tipe_konsumen === 'reseller' ? 'Reseller' : null);
        }

        return [
            'harga' => max(0, $final),
            'harga_dasar' => $hargaDasar,
            'diskon_nominal' => max(0, $hargaDasar - max(0, $final)),
            'alasan' => $alasan,
            'tier' => $tier,
        ];
    }

    protected function tipeLabel(?string $tipe): string
    {
        return match ($tipe) {
            'reseller' => 'Reseller',
            'agen' => 'Agen',
            default => 'Retail',
        };
    }
}
