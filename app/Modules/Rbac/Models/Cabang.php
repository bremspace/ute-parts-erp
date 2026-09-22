<?php

namespace App\Modules\Rbac\Models;

use App\Models\User;
use App\Modules\Wms\Models\Gudang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama', 'alamat', 'telepon', 'kode', 'is_active'])]
class Cabang extends Model
{
    protected $table = 'cabang';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function gudang(): HasMany
    {
        return $this->hasMany(Gudang::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_cabang')
            ->withPivot('is_default')
            ->withTimestamps();
    }
}
