<?php

namespace App\Modules\Servis\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tiket_servis_id', 'status_dari', 'status_ke', 'user_id', 'aksi', 'alasan',
])]
class ServisStatusLog extends Model
{
    protected $table = 'servis_status_log';

    public function tiketServis(): BelongsTo
    {
        return $this->belongsTo(TiketServis::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
