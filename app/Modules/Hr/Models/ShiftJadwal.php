<?php

namespace App\Modules\Hr\Models;

use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
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
    use CatatAktivitas;
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

    /**
     * [F1-4] Audit trail roster shift.
     *
     * Roster adalah master jadwal kerja → perubahan shift staff adalah keputusan
     * yang harus bisa diaudit. `cabang_id` ada di tabel sehingga `CatatAktivitas`
     * menyimpan cabang ke activity log (filter per cabang tetap bekerja).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Jadwal Shift');
    }
}
