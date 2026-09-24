<?php

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [F3-8b] KPI Hasil — dihitung service dari data real (bukan input manual),
 * idempotent per karyawan+metric+periode.
 */
#[Fillable([
    'karyawan_id', 'kpi_metric_id', 'periode', 'nilai_aktual', 'persen_capaian',
])]
class KpiHasil extends Model
{
    use LogsActivity;

    protected $table = 'kpi_hasil';

    protected $casts = [
        'nilai_aktual' => 'decimal:2',
        'persen_capaian' => 'decimal:2',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(KpiMetric::class, 'kpi_metric_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
