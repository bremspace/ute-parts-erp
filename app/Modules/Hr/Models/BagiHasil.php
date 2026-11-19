<?php

namespace App\Modules\Hr\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use App\Modules\Servis\Models\TiketServis;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * [BH] Bagi hasil jasa per tiket servis — 1 tiket maksimal 1 baris (unique
 * `tiket_servis_id`), dibuat saat tiket selesai, dicairkan lewat payout periode.
 */
#[Fillable([
    'no_bagi_hasil', 'tiket_servis_id', 'karyawan_id', 'teknisi_id', 'cabang_id',
    'periode', 'total_jasa', 'persen', 'nominal', 'status', 'paid_at', 'keterangan',
])]
class BagiHasil extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_CAIR = 'cair';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $table = 'bagi_hasil';

    protected $casts = [
        'total_jasa' => 'decimal:2',
        'persen' => 'decimal:2',
        'nominal' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Karyawan, $this>
     */
    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    /**
     * @return BelongsTo<TiketServis, $this>
     */
    public function tiketServis(): BelongsTo
    {
        return $this->belongsTo(TiketServis::class);
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    /**
     * [F1-4] Audit trail bagi hasil — cabang_id ada di tabel.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Bagi Hasil Teknisi');
    }
}
