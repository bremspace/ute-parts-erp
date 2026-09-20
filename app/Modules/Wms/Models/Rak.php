<?php

namespace App\Modules\Wms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['gudang_id', 'nama', 'kode', 'zona', 'is_active'])]
class Rak extends Model
{
    protected $table = 'rak';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }
}