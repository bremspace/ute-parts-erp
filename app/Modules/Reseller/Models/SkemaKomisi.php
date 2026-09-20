<?php

namespace App\Modules\Reseller\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'nama', 'kategori', 'tipe', 'nilai', 'is_active',
])]
class SkemaKomisi extends Model
{
    protected $table = 'skema_komisi';

    protected $casts = [
        'nilai' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function komisi()
    {
        return $this->hasMany(Komisi::class);
    }
}
