<?php

namespace App\Modules\Servis\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama', 'kode', 'durasi_garansi_hari', 'is_part_original', 'is_active'])]
class JenisServis extends Model
{
    protected $table = 'jenis_servis';

    protected $casts = [
        'durasi_garansi_hari' => 'integer',
        'is_part_original' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tiketServis(): HasMany
    {
        return $this->hasMany(TiketServis::class);
    }
}