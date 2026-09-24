<?php

namespace App\Modules\Wms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama', 'keterangan', 'is_active'])]
class Brand extends Model
{
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function produk(): HasMany
    {
        return $this->hasMany(Produk::class);
    }
}
