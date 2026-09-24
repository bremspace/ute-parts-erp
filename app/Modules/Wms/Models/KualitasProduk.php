<?php

namespace App\Modules\Wms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama', 'keterangan', 'is_active'])]
class KualitasProduk extends Model
{
    protected $table = 'kualitas_produk';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function produk(): HasMany
    {
        return $this->hasMany(Produk::class);
    }
}
