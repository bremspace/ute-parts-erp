<?php

namespace App\Modules\Notifikasi\Models;

use App\Modules\Crm\Models\KampanyeBroadcast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tipe', 'tujuan', 'judul', 'konten', 'payload', 'status', 'error',
    'kampanye_broadcast_id',
])]
class NotifikasiKeluar extends Model
{
    protected $table = 'notifikasi_keluar';

    protected $casts = [
        'payload' => 'array',
    ];

    public function kampanyeBroadcast(): BelongsTo
    {
        return $this->belongsTo(KampanyeBroadcast::class, 'kampanye_broadcast_id');
    }
}
