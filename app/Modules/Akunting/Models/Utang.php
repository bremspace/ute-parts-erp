<?php

namespace App\Modules\Akunting\Models;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'no_utang', 'referensi_tipe', 'referensi_id', 'pelanggan_id', 'cabang_id',
    'kreditor_nama', 'jumlah', 'jumlah_dibayar', 'jatuh_tempo', 'status', 'keterangan',
])]
class Utang extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'utang';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Utang');
    }

    protected $casts = [
        'jumlah' => 'decimal:2',
        'jumlah_dibayar' => 'decimal:2',
        'jatuh_tempo' => 'date',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function getSisaAttribute(): float
    {
        return max(0, (float) $this->jumlah - (float) $this->jumlah_dibayar);
    }
}
