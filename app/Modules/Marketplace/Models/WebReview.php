<?php

namespace App\Modules\Marketplace\Models;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Produk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebReview extends Model
{
    protected $table = 'web_reviews';

    protected $fillable = [
        'pelanggan_id',
        'tipe_ulasan',
        'produk_id',
        'transaksi_id',
        'tiket_servis_id',
        'rating',
        'judul_ulasan',
        'isi_ulasan',
        'media_foto',
        'is_verified_purchase',
        'is_approved',
        'is_featured',
        'tanggapan_admin',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'media_foto' => 'array',
        'is_verified_purchase' => 'boolean',
        'is_approved' => 'boolean',
        'is_featured' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'pelanggan_id');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    public function tiketServis(): BelongsTo
    {
        return $this->belongsTo(TiketServis::class, 'tiket_servis_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true)->orderBy('created_at', 'desc');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_approved', true)
            ->where('is_featured', true)
            ->orderBy('created_at', 'desc');
    }

    public function scopeProdukOnly(Builder $query, int $produkId): Builder
    {
        return $query->where('tipe_ulasan', 'produk')->where('produk_id', $produkId);
    }

    public function scopeServisOnly(Builder $query): Builder
    {
        return $query->where('tipe_ulasan', 'servis');
    }
}
