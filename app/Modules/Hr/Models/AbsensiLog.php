<?php

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [F3-8b] Absensi Log — idempotent per karyawan+tanggal.
 * Status: hadir / terlambat / absen / izin / cuti.
 */
#[Fillable([
    'karyawan_id', 'tanggal', 'jam_masuk', 'jam_keluar', 'shift_id',
    'status', 'catatan', 'self_photo', 'lokasi',
])]
class AbsensiLog extends Model
{
    use LogsActivity;

    protected $table = 'absensi_log';

    // SENGJA tanpa cast tanggal: storage tetap 'Y-m-d' agar
    // firstOrNew/updateOrCreate keyed by tanggal bisa match query.
    // Format Carbon dilakukan di view via Carbon::parse().

    public const STATUS_HADIR = 'hadir';

    public const STATUS_TERLAMBAT = 'terlambat';

    public const STATUS_ABSEN = 'absen';

    public const STATUS_IZIN = 'izin';

    public const STATUS_CUTI = 'cuti';

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
