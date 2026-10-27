<?php

namespace App\Modules\Crm\Models;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Traits\CatatAktivitas;
use App\Modules\Reseller\Models\Komisi;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['nama', 'username', 'telepon', 'email', 'avatar', 'alamat', 'tanggal_lahir', 'tier_membership_id', 'is_reseller', 'kode_agen', 'referral_kode', 'tipe_konsumen', 'mode_reward_mitra', 'total_belanja_12bulan', 'poin_loyalty', 'saldo_piutang', 'max_piutang', 'password', 'status', 'catatan_approval', 'approved_at', 'approved_by'])]
#[Hidden(['password', 'remember_token'])]
class Pelanggan extends Model implements Authenticatable
{
    use AuthenticatableTrait, CatatAktivitas, LogsActivity, Notifiable;

    protected $table = 'pelanggan';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Pelanggan');
    }

    protected $casts = [
        'tanggal_lahir' => 'date',
        'is_reseller' => 'boolean',
        'tipe_konsumen' => 'string',
        'total_belanja_12bulan' => 'decimal:2',
        'saldo_piutang' => 'decimal:2',
        'max_piutang' => 'decimal:2',
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

    public function downlines(): HasMany
    {
        return $this->hasMany(Pelanggan::class, 'referral_kode', 'kode_agen');
    }

    public function uplineAgen(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'referral_kode', 'kode_agen');
    }

    public function poinLogs(): HasMany
    {
        return $this->hasMany(PelangganPoinLog::class)->latest();
    }
}
