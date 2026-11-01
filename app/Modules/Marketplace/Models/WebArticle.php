<?php

namespace App\Modules\Marketplace\Models;

use App\Models\User;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Wms\Models\Produk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class WebArticle extends Model
{
    protected $table = 'web_articles';

    protected $fillable = [
        'judul',
        'slug',
        'ringkasan',
        'konten_lengkap',
        'kategori',
        'tipe_media_cover',
        'cover_image',
        'cover_video',
        'youtube_url',
        'youtube_video_id',
        'produk_terkait_ids',
        'rekomendasi_servis_id',
        'meta_title',
        'meta_description',
        'penulis_id',
        'is_published',
        'published_at',
        'views_count',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'views_count' => 'integer',
        'produk_terkait_ids' => 'array',
    ];

    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penulis_id');
    }

    public function rekomendasiServis(): BelongsTo
    {
        return $this->belongsTo(JenisServis::class, 'rekomendasi_servis_id');
    }

    public function getProdukTerkaitProperty(): Collection
    {
        if (empty($this->produk_terkait_ids) || ! is_array($this->produk_terkait_ids)) {
            return collect();
        }

        return Produk::whereIn('id', $this->produk_terkait_ids)
            ->where('is_active', true)
            ->get();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function (Builder $q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->orderBy('published_at', 'desc')
            ->orderBy('id', 'desc');
    }

    public function scopeKategori(Builder $query, string $kategori): Builder
    {
        return $query->where('kategori', $kategori);
    }

    /**
     * Ekstrak YouTube ID dari berbagai format URL (watch, share youtu.be, embed).
     */
    public static function extractYouTubeId(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $pattern = '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i';
        if (preg_match($pattern, $url, $match)) {
            return $match[1];
        }

        return null;
    }
}
