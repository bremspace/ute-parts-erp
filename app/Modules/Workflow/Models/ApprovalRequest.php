<?php

namespace App\Modules\Workflow\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Workflow\Services\ApprovalService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    protected $table = 'approval_requests';

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
