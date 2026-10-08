<?php

namespace App\Modules\Wms\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RiwayatPerubahanHarga extends Model
{
    protected $table = 'riwayat_perubahan_harga';

    protected $fillable = [
        'produk_id',
        'cabang_id',
        'sumber',
        'referensi_type',
        'referensi_id',
        'harga_lama',
        'harga_baru',
        'selisih',
        'persentase_perubahan',
        'catatan',
        'user_id',
    ];

    protected $casts = [
        'harga_lama' => 'decimal:2',
        'harga_baru' => 'decimal:2',
        'selisih' => 'decimal:2',
        'persentase_perubahan' => 'decimal:2',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function referensi(): MorphTo
    {
        return $this->morphTo();
    }
}
