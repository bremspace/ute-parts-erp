<?php

namespace App\Modules\Hr\Models;

use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [BH] Aturan bagi hasil jasa teknisi non-gaji.
 *
 * Prioritas pencocokan (lihat BagiHasilService::ruleUntuk):
 *   1. rule per teknisi (karyawan_id terisi)
 *   2. rule per cabang  (karyawan_id null, cabang_id terisi)
 *   3. rule global     (keduanya null)
 */
#[Fillable([
    'nama', 'cabang_id', 'karyawan_id', 'persen', 'min_jasa', 'is_aktif',
])]
class BagiHasilRule extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'bagi_hasil_rules';

    protected $casts = [
        'persen' => 'decimal:2',
        'min_jasa' => 'decimal:2',
        'is_aktif' => 'boolean',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_aktif', true);
    }

    /**
     * [F1-4] Audit trail aturan bagi hasil — cabang_id ada di tabel sehingga
     * perubahan persen per cabang tidak bocor ke cabang lain.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Aturan Bagi Hasil');
    }
}
