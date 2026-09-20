<?php

namespace App\Modules\Akunting\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'kode', 'nama', 'tipe', 'kelompok', 'saldo_normal', 'parent_id', 'is_active',
])]
class AkunCOA extends Model
{
    protected $table = 'akun_coa';

    protected $casts = [
        'is_active' => 'boolean',
        'parent_id' => 'integer',
    ];

    public function jurnal(): HasMany
    {
        return $this->hasMany(JurnalAkuntansi::class, 'akun_coa_id');
    }

    public function getSaldoAttribute(): float
    {
        $debit = $this->jurnal()->sum('debit');
        $kredit = $this->jurnal()->sum('kredit');

        return $this->saldo_normal === 'debit'
            ? (float) $debit - (float) $kredit
            : (float) $kredit - (float) $debit;
    }
}
