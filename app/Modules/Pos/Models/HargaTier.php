<?php

namespace App\Modules\Pos\Models;

use App\Modules\Crm\Models\TierMembership;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [T-44] Harga tier produk — perluasan skema lama.
 * Skema baru: per (produk_id [atau sku_variant_id], tier_membership_id, tipe_konsumen)
 * tersedia nominal_tetap ATAU persen_diskon. Kolom legacy (harga, is_reseller)
 * tetap dipertahankan untuk backward-compatible.
 */
#[Fillable([
    'produk_id', 'sku_variant_id', 'tier_membership_id', 'is_reseller',
    'tipe_konsumen', 'harga', 'persen_diskon', 'nominal_tetap',
])]
class HargaTier extends Model
{
    protected $table = 'harga_tier';

    protected $casts = [
        'is_reseller' => 'boolean',
        'harga' => 'decimal:2',
        'persen_diskon' => 'decimal:2',
        'nominal_tetap' => 'decimal:2',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function skuVariant(): BelongsTo
    {
        return $this->belongsTo(SkuVariant::class);
    }

    public function tierMembership(): BelongsTo
    {
        return $this->belongsTo(TierMembership::class);
    }
}
