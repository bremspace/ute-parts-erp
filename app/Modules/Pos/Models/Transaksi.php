<?php

namespace App\Modules\Pos\Models;

use App\Models\User;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use App\Modules\Wms\Models\Gudang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'no_transaksi', 'cabang_id', 'kasir_id', 'pelanggan_id', 'gudang_id',
    'sumber', 'subtotal', 'diskon_persen', 'diskon_nominal', 'dpp', 'pajak_nominal', 'ppn_nominal',
    'total_akhir', 'metode_bayar', 'jumlah_bayar', 'kembalian', 'split_detail',
    'status', 'catatan',
    // [B-10f] Kolom payment Duitku (migrasi 2026_09_16_000027) TIDAK ADA di
    // tabel payment terpisah — semuanya langsung di tabel `transaksi`. Ketiganya
    // sebelumnya tertinggal dari daftar fillable sehingga Eloquent SILENTLY
    // membuang nilainya (webhook PaymentController + seeder demo): paid_at hilang
    // diam-diam, payment_reference/payment_url juga. Sumber kebenaran paid_at
    // tetap `transaksi.paid_at` (tidak ada duplikasi ke tabel lain).
    'payment_reference', 'payment_url', 'paid_at',
])]
class Transaksi extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'transaksi';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Transaksi');
    }

    protected $casts = [
        'subtotal' => 'decimal:2',
        'diskon_persen' => 'decimal:2',
        'diskon_nominal' => 'decimal:2',
        'dpp' => 'decimal:2',
        'pajak_nominal' => 'decimal:2',
        'ppn_nominal' => 'decimal:2',
        'total_akhir' => 'decimal:2',
        'jumlah_bayar' => 'decimal:2',
        'kembalian' => 'decimal:2',
        'split_detail' => 'array',
        // [B-10f] paid_at kini benar-benar tersimpan (sebelumnya dibuang fillable)
        'paid_at' => 'datetime',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function kasir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kasir_id');
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function gudang(): BelongsTo
    {
        return $this->belongsTo(Gudang::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransaksiItem::class);
    }
}
