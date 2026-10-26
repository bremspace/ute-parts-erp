<?php

namespace App\Modules\Marketplace\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WebBanner extends Model
{
    protected $table = 'web_banners';

    protected $fillable = [
        'judul',
        'badge_label',
        'gambar_desktop',
        'gambar_mobile',
        'target_url',
        'posisi',
        'urutan',
        'tanggal_mulai',
        'tanggal_selesai',
        'is_active',
        'click_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan' => 'integer',
        'click_count' => 'integer',
        'tanggal_mulai' => 'datetime',
        'tanggal_selesai' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('tanggal_mulai')->orWhere('tanggal_mulai', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $now);
            })
            ->orderBy('urutan', 'asc')
            ->orderBy('id', 'asc');
    }

    public function scopePosisi(Builder $query, string $posisi): Builder
    {
        return $query->where('posisi', $posisi);
    }
}
