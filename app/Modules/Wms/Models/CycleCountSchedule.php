<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [F3-7 / G-18] Jadwal cycle count per cabang — target rak ATAU kategori,
 * frekuensi mingguan/bulanan (hari/jam), sample_size + threshold minor/major.
 * Generate task harian via CycleCountJob (pola schedule F1-6, queue database).
 */
#[Fillable([
    'cabang_id', 'nama', 'tipe_target', 'target_id', 'target_kategori',
    'frekuensi', 'hari', 'jam', 'sample_size',
    'threshold_unit', 'threshold_persen', 'is_aktif', 'last_run_at',
])]
class CycleCountSchedule extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'cycle_count_schedule';

    protected $casts = [
        'is_aktif' => 'boolean',
        'last_run_at' => 'datetime',
        'threshold_persen' => 'float',
        'sample_size' => 'integer',
        'threshold_unit' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Jadwal Cycle Count');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function rak(): BelongsTo
    {
        return $this->belongsTo(Rak::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CycleCountTask::class);
    }

    /**
     * Label target untuk display/audit.
     */
    public function getTargetLabelAttribute(): string
    {
        if ($this->tipe_target === 'rak') {
            $rak = $this->rak;

            return $rak ? 'Rak '.$rak->kode.' — '.$rak->nama : 'Rak #'.$this->target_id;
        }

        return 'Kategori '.($this->target_kategori ?? '-');
    }
}
