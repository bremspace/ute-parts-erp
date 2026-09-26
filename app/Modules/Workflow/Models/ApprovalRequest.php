<?php

namespace App\Modules\Workflow\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'approval_rule_id',
    'entity_type',
    'entity_id',
    'cabang_id',
    'payload_json',
    'status',
    'requested_by',
    'actioned_by',
    'actioned_at',
    'catatan',
    'approver_role',
])]
class ApprovalRequest extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'approval_requests';

    /**
     * [B-10d / P1-3] Audit trail approval workflow.
     *
     * `logOnly` — HANYA kolom status & scope. `payload_json` SENGAJA TIDAK
     * dilog: isinya snapshot entitas yang bisa sensitif (gaji, komisi, data
     * pelanggan) dan ukurannya bisa besar.
     *
     * `cabang_id` ikut dilog + di-stamp ke kolom activity_log.cabang_id oleh
     * `CatatAktivitas` (AktivitasCabang membaca atribut row) sehingga approval
     * cabang lain tidak terlihat saat filter riwayat.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'cabang_id', 'entity_type', 'entity_id', 'approval_rule_id',
                'status', 'actioned_by', 'actioned_at', 'approver_role', 'catatan',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => 'Permintaan Approval '.self::labelAksiAktivitas($event));
    }

    protected $casts = [
        'payload_json' => 'array',
        'actioned_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class, 'approval_rule_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }

    /**
     * Proses approval via service (role/permission check + multi-level chain).
     *
     * @param  string  $action  approved | rejected | disetujui | ditolak
     */
    public function proses(string $action, int $actionedBy, ?string $catatan = null): self
    {
        return app(ApprovalService::class)
            ->proses($this->id, $action, $actionedBy, $catatan);
    }
}
