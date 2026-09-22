<?php

namespace App\Modules\Wms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['merk', 'model', 'nama', 'is_active'])]
class TipeHp extends Model
{
    protected $table = 'tipe_hp';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function produk(): BelongsToMany
    {
        return $this->belongsToMany(
            Produk::class,
            'kompatibilitas_produk_tipe_hp',
            'tipe_hp_id',
            'produk_id'
        );
    }
}