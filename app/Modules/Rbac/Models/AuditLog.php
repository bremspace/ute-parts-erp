<?php

namespace App\Modules\Rbac\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'entitas', 'aksi', 'entitas_id', 'deskripsi', 'sebelum', 'sesudah', 'ip',
    // [B-10d] jejak audit log kini membawa cabang (migrasi 2026_09_25_040000)
    'cabang_id',
])]
class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $casts = [
        'sebelum' => 'array',
        'sesudah' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
