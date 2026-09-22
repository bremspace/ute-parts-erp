<?php

namespace App\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama', 'kode', 'min_belanja_12bulan', 'diskon_persen', 'poin_multiplier', 'urutan', 'is_active'])]
class TierMembership extends Model
{
    protected $table = 'tier_memberships';

    protected $casts = [
        'is_active' => 'boolean',
        'min_belanja_12bulan' => 'decimal:2',
        'diskon_persen' => 'decimal:2',
        'poin_multiplier' => 'decimal:1',
    ];

    public function pelanggan(): HasMany
    {
        return $this->hasMany(Pelanggan::class, 'tier_membership_id');
    }
}
