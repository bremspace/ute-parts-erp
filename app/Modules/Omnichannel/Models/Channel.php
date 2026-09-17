<?php

namespace App\Modules\Omnichannel\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'nama', 'platform', 'status', 'kredensial', 'last_sync_at', 'last_sync_status', 'is_active'
])]
class Channel extends Model
{
    protected $casts = [
        // Enkripsi kredensial OAuth di DB (PRD §6): token tersimpan terenkripsi, tidak plaintext
        'kredensial' => 'encrypted:array',
        'last_sync_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function productMappings()
    {
        return $this->hasMany(ChannelProductMapping::class);
    }

    public function orders()
    {
        return $this->hasMany(ChannelOrder::class);
    }
}