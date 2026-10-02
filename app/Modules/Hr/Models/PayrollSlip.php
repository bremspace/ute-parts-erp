<?php

namespace App\Modules\Hr\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'payroll_periode_id', 'karyawan_id', 'gaji_pokok', 'total_tunjangan',
    'total_potongan', 'total_komisi', 'total_gaji', 'rincian', 'jurnal_id',
])]
class PayrollSlip extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'payroll_slip';

    protected $casts = [
        'gaji_pokok' => 'decimal:2', 'total_tunjangan' => 'decimal:2',
        'total_potongan' => 'decimal:2', 'total_komisi' => 'decimal:2',
        'total_gaji' => 'decimal:2', 'rincian' => 'array',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriode::class, 'payroll_periode_id');
    }

    public function komisiDetails(): HasMany
    {
        return $this->hasMany(PayrollKomisiDetail::class, 'slip_id');
    }

    /**
     * [F1-4] Audit trail slip gaji per karyawan.
     *
     * `logOnly` — HANYA kolom yang relevan untuk audit. `rincian` (JSON rincian
     * komponen) sengaja DIBUANG: isinya mengulang `KaryawanKomponenGaji` +
     * `PayrollKomisiDetail` yang sudah punya log sendiri, dan blob-nya bisa
     * berukuran KB sehingga boros di server 1GB. Pemilik yang perlu tahu
     * "gaji karyawan ini berubah dari berapa ke berapa" sudah terlayani oleh
     * kolom total di bawah.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'payroll_periode_id', 'karyawan_id', 'gaji_pokok', 'total_tunjangan',
                'total_potongan', 'total_komisi', 'total_gaji', 'jurnal_id', 'status',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => 'Slip Gaji '.self::labelAksiAktivitas($event));
    }
}
