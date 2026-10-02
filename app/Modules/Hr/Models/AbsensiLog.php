<?php

namespace App\Modules\Hr\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
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
    use CatatAktivitas;
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

    /**
     * [F1-4] Audit trail absensi.
     *
     * SENGJA `logOnly` + `dontLogEmptyChanges()`, bukan `opsilogAktivitas()` polos:
     * tabel ini adalah ledger harian yang ditulis OTOMATIS saat employee check-in
     * (satu baris per karyawan per hari, ±3.000 baris/tahun untuk 10 karyawan).
     * `logAll()` akan membanjiri audit trail dengan baris tanpa nilai audit —
     * persis yang membuat pemilik gagal menemukan perubahan yang penting.
     *
     * Yang TIDAK ikut disalin ke log:
     * - `lokasi` (koordinat GPS) — data pribadi employee dan ditulis ulang tiap
     *   sync dari ponsel, jadi sumber noise terbesar.
     * - `self_photo` — path file, bukan informasi audit.
     * - `catatan` — free-text.
     *
     * Yang tetap tercatat: status (hadir/izin/cuti/terlambat), jam masuk/keluar,
     * dan shift — yaitu koreksi manual HR atas absensi. Koreksi status inilah yang
     * akhirnya memengaruhi besaran gaji, jadi harus bisa diaudit.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'karyawan_id', 'tanggal', 'jam_masuk', 'jam_keluar',
                'shift_id', 'status',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => 'Log Absensi '.self::labelAksiAktivitas($event));
    }
}
