<?php

namespace App\Modules\Wms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['kode', 'nama', 'is_active'])]
class SatuanUnit extends Model
{
    protected $table = 'satuan_unit';

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
