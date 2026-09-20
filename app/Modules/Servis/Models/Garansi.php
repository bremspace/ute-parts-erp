<?php

namespace App\Modules\Servis\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tiket_servis_id', 'durasi_hari', 'tanggal_mulai', 'tanggal_berakhir', 'keterangan',
])]
class Garansi extends Model
{
    protected $table = 'garansi';

    protected $casts = [
        'durasi_hari' => 'integer',
        'tanggal_mulai' => 'date',
        'tanggal_berakhir' => 'date',
    ];

    public function tiketServis(): BelongsTo
    {
        return $this->belongsTo(TiketServis::class);
    }

    public function getActiveAttribute(): bool
    {
        return now()->between($this->tanggal_mulai, $this->tanggal_berakhir);
    }
}
