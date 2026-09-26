<?php

namespace App\Modules\Crm\Models;

use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'judul', 'pesan', 'channel', 'segment', 'status', 'dijadwalkan_at',
    'dikirim_at', 'user_id', 'total_target', 'total_terkirim', 'total_gagal',
])]
class KampanyeBroadcast extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'kampanye_broadcast';

    /**
     * [B-10d / P1-3] Audit trail kampanye broadcast.
     *
     * `logOnly` — HANYA kolom operasional (judul, channel, status, jadwal,
     * penghitung hasil kirim). `pesan` SENGAJA TIDAK pernah masuk activity log
     * (isi pesan bisa memuat data promo/pelanggan) dan `segment` JSON juga
     * tidak dilog (bisa besar + memuat filter pelanggan).
     *
     * `dontLogEmptyChanges()` — eksekusi campaign menyetel `status` & penghitung
     * satu kali per campaign, jadi tidak ada log kosong yang memboroskan storage.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'judul', 'channel', 'status', 'dijadwalkan_at', 'dikirim_at',
                'total_target', 'total_terkirim', 'total_gagal',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => 'Kampanye Broadcast '.self::labelAksiAktivitas($event));
    }

    protected $casts = [
        'segment' => 'array',
        'dijadwalkan_at' => 'datetime',
        'dikirim_at' => 'datetime',
    ];

    public function notifikasi(): HasMany
    {
        return $this->hasMany(NotifikasiKeluar::class, 'kampanye_broadcast_id');
    }

    public function getLogGagalAttribute(): int
    {
        return (int) $this->total_gagal;
    }
}
