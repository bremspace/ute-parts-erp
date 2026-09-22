<?php

namespace App\Modules\Wms\Models;

use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['cabang_id', 'nama', 'kode', 'is_active'])]
class Gudang extends Model
{
    protected $table = 'gudang';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function stokItems(): HasMany
    {
        return $this->hasMany(StokItem::class);
    }
}
