<?php

namespace App\Modules\Workflow\Models;

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
        return $this->belongsTo(ApprovalRule::class);
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Rbac\Models\Cabang::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by');
    }

    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'actioned_by');
    }
}