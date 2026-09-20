<?php

namespace App\Modules\Reseller\Models;

use App\Modules\Crm\Models\Pelanggan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pelanggan_id', 'kategori', 'tipe', 'nilai', 'is_active'])]
class SkemaKomisiReseller extends Model
{
    protected $table = 'skema_komisi_reseller';

    protected $casts = [
        'nilai' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }
}