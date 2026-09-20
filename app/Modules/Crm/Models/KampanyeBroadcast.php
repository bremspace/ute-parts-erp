<?php

namespace App\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'judul', 'pesan', 'channel', 'segment', 'status', 'dijadwalkan_at',
    'dikirim_at', 'user_id', 'total_target', 'total_terkirim', 'total_gagal'
])]
class KampanyeBroadcast extends Model
{
    protected $table = 'kampanye_broadcast';

    protected $casts = [
        'segment' => 'array',
        'dijadwalkan_at' => 'datetime',
        'dikirim_at' => 'datetime',
    ];

    public function getLogGagalAttribute(): int
    {
        return (int) $this->total_gagal;
    }
}