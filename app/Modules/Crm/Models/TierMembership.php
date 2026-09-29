<?php

namespace App\Modules\Crm\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['nama', 'kode', 'min_belanja_12bulan', 'diskon_persen', 'poin_multiplier', 'urutan', 'is_active'])]
class TierMembership extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'tier_memberships';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Tier Membership');
    }

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
