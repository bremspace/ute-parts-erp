<?php

namespace App\Modules\Hr\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use App\Traits\BelongsToCabang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [BH] Periode payout bagi hasil teknisi — siklus bulanan:
 * draft → (hitung) → selesai (jurnal accrual diposting) → dibayar (jurnal kas).
 */
#[Fillable([
    'periode', 'cabang_id', 'tanggal_mulai', 'tanggal_selesai', 'status',
    'total', 'jumlah_tiket', 'no_jurnal_accrue', 'no_jurnal_bayar', 'paid_at', 'catatan',
])]
class BagiHasilPeriode extends Model
{
    use BelongsToCabang;
    use CatatAktivitas;
    use LogsActivity;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_DIBAYAR = 'dibayar';

    protected $table = 'bagi_hasil_periode';

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'total' => 'decimal:2',
        'jumlah_tiket' => 'integer',
        'paid_at' => 'datetime',
        'status' => 'string',
    ];

    /**
     * [F1-4] Audit trail periode bagi hasil — siapa menyetujui/mencairkan.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Periode Bagi Hasil');
    }
}
