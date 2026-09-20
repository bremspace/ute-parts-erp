<?php

namespace App\Modules\Crm\Models;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Reseller\Models\Komisi;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

#[Fillable(['nama', 'telepon', 'email', 'alamat', 'tanggal_lahir', 'tier_membership_id', 'is_reseller', 'total_belanja_12bulan', 'poin_loyalty', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Pelanggan extends Model implements Authenticatable
{
    use AuthenticatableTrait, Notifiable;

    protected $table = 'pelanggan';

    protected $casts = [
        'tanggal_lahir' => 'date',
        'is_reseller' => 'boolean',
        'total_belanja_12bulan' => 'decimal:2',
        'poin_loyalty' => 'integer',
        'password' => 'hashed',
    ];

    public function tierMembership(): BelongsTo
    {
        return $this->belongsTo(TierMembership::class);
    }

    public function komisi(): HasMany
    {
        return $this->hasMany(Komisi::class);
    }

    public function transaksi(): HasMany
    {
        return $this->hasMany(Transaksi::class);
    }
}
