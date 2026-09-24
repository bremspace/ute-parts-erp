<?php

namespace App\Modules\Hr\Models;

use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [F3-8b] Shift Jadwal (roster) — assignment karyawan ↔ shift per tanggal.
 * Idempotent per cabang+karyawan+tanggal.
 */
#[Fillable([
    'cabang_id', 'karyawan_id', 'shift_id', 'tanggal',
])]
class ShiftJadwal extends Model
{
    use LogsActivity;

    protected $table = 'shift_jadwal';

    // SENGJA tanpa cast tanggal: storage 'Y-m-d' agar konsisten dgn key
    // updateOrCreate (cabang,karyawan,tanggal). Format di view via Carbon::parse().

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
