<?php

namespace App\Modules\Akunting\Models;

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'cabang_id', 'user_id', 'akun_id', 'tanggal',
    'saldo_sistem', 'saldo_fisik', 'selisih', 'status',
    'rincian_pecahan', 'catatan', 'jurnal_id',
])]
class KasMatching extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'kas_matching';

    protected $casts = [
        'tanggal' => 'date',
        'saldo_sistem' => 'decimal:2',
        'saldo_fisik' => 'decimal:2',
        'selisih' => 'decimal:2',
        'rincian_pecahan' => 'array',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function akun(): BelongsTo
    {
        return $this->belongsTo(AkunCOA::class, 'akun_id');
    }

    public function jurnal(): BelongsTo
    {
        return $this->belongsTo(JurnalAkuntansi::class, 'jurnal_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Matching Kas Real');
    }
}
