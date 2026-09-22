<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;

/**
 * Tier Membership logic (PRD §4.4):
 * - Kenaikan/penurunan tier otomatis dari akumulasi belanja 12 bulan.
 * - Threshold per tier configurable (di tier_memberships.min_belanja_12bulan).
 * - Poin loyalty earn/redeem.
 */
class TierService
{
    /**
     * Rekalkulasi tier semua pelanggan. Dipanggil via scheduled job harian.
     */
    public function recalcSemua(): int
    {
        $updated = 0;
        $tiers = TierMembership::where('is_active', true)->orderBy('min_belanja_12bulan', 'desc')->get();

        Pelanggan::chunkById(200, function ($pelanggan) use ($tiers, &$updated) {
            foreach ($pelanggan as $p) {
                if ($this->recalcSatu($p, $tiers)) {
                    $updated++;
                }
            }
        });

        return $updated;
    }

    public function recalcSatu(Pelanggan $pelanggan, $tiers = null): bool
    {
        $tiers ??= TierMembership::where('is_active', true)
            ->orderBy('min_belanja_12bulan', 'desc')
            ->get();

        $belanja = (float) $pelanggan->total_belanja_12bulan;
        $targetTier = null;

        foreach ($tiers as $tier) {
            if ($belanja >= (float) $tier->min_belanja_12bulan) {
                $targetTier = $tier;
                break;
            }
        }

        $newId = $targetTier?->id;
        if ($pelanggan->tier_membership_id !== $newId) {
            $pelanggan->update(['tier_membership_id' => $newId]);

            return true;
        }

        return false;
    }

    /**
     * Resolusi tier terbaik untuk threshold belanja tertentu (untuk testing).
     */
    public function resolveTierUntukBelanja(float $belanja, $tiers = null): ?TierMembership
    {
        $tiers ??= TierMembership::where('is_active', true)
            ->orderBy('min_belanja_12bulan', 'desc')
            ->get();

        foreach ($tiers as $tier) {
            if ($belanja >= (float) $tier->min_belanja_12bulan) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * Hitung poin yang didapat dari transaksi (sesuai multiplier tier).
     */
    public function hitungPoin(float $nominalBelanja, ?TierMembership $tier = null): int
    {
        $multiplier = $tier ? (float) $tier->poin_multiplier : 1.0;

        return (int) floor(($nominalBelanja / 1000) * $multiplier);
    }
}
