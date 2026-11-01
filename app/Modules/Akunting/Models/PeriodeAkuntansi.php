<?php

namespace App\Modules\Akunting\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'cabang_id', 'tahun', 'bulan', 'status',
    'closed_at', 'closed_by', 'no_jurnal_penutup', 'catatan',
])]
class PeriodeAkuntansi extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'periode_akuntansi';

    protected $casts = [
        'tahun' => 'integer',
        'bulan' => 'integer',
        'closed_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Periode Akuntansi');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Label nama bulan dalam bahasa Indonesia.
     */
    public function getNamaBulanAttribute(): string
    {
        $bulanMap = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $bulanMap[$this->bulan] ?? (string) $this->bulan;
    }

    public function getLabelPeriodeAttribute(): string
    {
        return "{$this->nama_bulan} {$this->tahun}";
    }
}
