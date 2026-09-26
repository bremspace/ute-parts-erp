<?php

namespace App\Modules\Servis\Models;

use App\Models\User;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'tiket_servis_id', 'status_dari', 'status_ke', 'user_id', 'aksi', 'alasan',
])]
class ServisStatusLog extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'servis_status_log';

    /**
     * [B-10d / P1-3] Audit trail transisi status tiket servis.
     *
     * `logOnly` — HANYA kolom yang sensitif: perpindahan status, jenis aksi
     * (transisi/approve/reject/override) dan alasan override. Baris ini adalah
     * bukti jejak state machine (PRD §4.3) yang wajib bisa diaudit.
     *
     * Tabel `servis_status_log` tidak punya kolom `cabang_id`, jadi
     * `AktivitasCabang` tidak bisa me-stamp cabang di sini (NULL = global) —
     * scoping cabang tetap ditegakkan di layer query tiket, bukan di log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['tiket_servis_id', 'status_dari', 'status_ke', 'aksi', 'alasan'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => 'Riwayat Status Servis '.self::labelAksiAktivitas($event));
    }

    public function tiketServis(): BelongsTo
    {
        return $this->belongsTo(TiketServis::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
