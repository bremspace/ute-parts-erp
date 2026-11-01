<?php

namespace App\Modules\Akunting\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'cabang_id', 'akun_coa_id', 'nama_bank', 'nomor_rekening',
    'periode_dari', 'periode_sampai', 'nama_file', 'format_file',
    'saldo_awal_bank', 'saldo_akhir_bank', 'saldo_buku_besar', 'selisih',
    'status', 'total_baris_bank', 'total_matched', 'user_id', 'catatan',
])]
class RekonsiliasiBank extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'rekonsiliasi_bank';

    protected $casts = [
        'periode_dari' => 'date',
        'periode_sampai' => 'date',
        'saldo_awal_bank' => 'decimal:2',
        'saldo_akhir_bank' => 'decimal:2',
        'saldo_buku_besar' => 'decimal:2',
        'selisih' => 'decimal:2',
        'total_baris_bank' => 'integer',
        'total_matched' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Rekonsiliasi Bank');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function akun(): BelongsTo
    {
        return $this->belongsTo(AkunCOA::class, 'akun_coa_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RekonsiliasiBankItem::class, 'rekonsiliasi_bank_id');
    }
}
