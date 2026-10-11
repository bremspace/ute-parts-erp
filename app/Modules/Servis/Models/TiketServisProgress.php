<?php

namespace App\Modules\Servis\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tiket_servis_id',
    'user_id',
    'persentase',
    'judul',
    'deskripsi',
    'foto',
    'tampilkan_ke_konsumen',
])]
class TiketServisProgress extends Model
{
    protected $table = 'tiket_servis_progress';

    protected $casts = [
        'persentase' => 'integer',
        'foto' => 'array',
        'tampilkan_ke_konsumen' => 'boolean',
    ];

    public function tiketServis(): BelongsTo
    {
        return $this->belongsTo(TiketServis::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
