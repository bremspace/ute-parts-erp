<?php

namespace App\Modules\Pos\Models;

use App\Modules\Crm\Models\TierMembership;
use App\Modules\Wms\Models\Produk;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['produk_id', 'tier_membership_id', 'is_reseller', 'harga'])]
class HargaTier extends Model
{
    protected $table = 'harga_tier';

    protected $casts = [
        'is_reseller' => 'boolean',
        'harga' => 'decimal:2',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    public function tierMembership(): BelongsTo
    {
        return $this->belongsTo(TierMembership::class);
    }
}
