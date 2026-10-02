<?php

namespace App\Modules\Hr\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'periode', 'tanggal_mulai', 'tanggal_selesai', 'status', 'catatan',
])]
class PayrollPeriode extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_DIPROSES = 'diproses';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_DIBAYAR = 'dibayar';

    protected $table = 'payroll_periode';

    protected $casts = ['status' => 'string'];

    public function slips(): HasMany
    {
        return $this->hasMany(PayrollSlip::class);
    }

    /**
     * [F1-4] Audit trail periode payroll — pemilik wajib bisa melihat siapa yang
     * membuka/menutup/dua bayar periode (pola: draft → diproses → selesai → dibayar).
     *
     * `catatan` free-text tidak dilog agar isi log tetap ramping.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Periode Payroll');
    }
}
