<?php

namespace App\Modules\Reseller\Models;

use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * [F3-8c] Rule komisi multi-aktor (PRD §4.3):
 * aktor karyawan/reseller/agen × trigger penjualan/lead_won/tiket_servis/target_kpi,
 * nominal atau persen, min_amount, cabang_id nullable (null = semua cabang).
 */
#[Fillable([
    'nama', 'aktor_tipe', 'aktor_id', 'trigger_tipe', 'kategori',
    'tipe', 'nilai', 'min_amount', 'cabang_id', 'is_aktif',
])]
class KomisiSkema extends Model
{
    protected $table = 'komisi_skema';

    protected $casts = [
        'nilai' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'is_aktif' => 'boolean',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function komisi(): HasMany
    {
        return $this->hasMany(Komisi::class, 'komisi_skema_id');
    }
}
