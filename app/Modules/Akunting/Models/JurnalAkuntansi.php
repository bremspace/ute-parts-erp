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
    'no_jurnal', 'tanggal', 'cabang_id', 'akun_coa_id', 'sumber',
    'deskripsi', 'debit', 'kredit', 'referensi_tipe', 'referensi_id', 'user_id',
])]
class JurnalAkuntansi extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'jurnal_akuntansi';

    public function getActivitylogOptions(): LogOptions
    {
        return $this->opsilogAktivitas('Jurnal');
    }

    protected $casts = [
        'tanggal' => 'datetime',
        'debit' => 'decimal:2',
        'kredit' => 'decimal:2',
    ];

    public function akun(): BelongsTo
    {
        return $this->belongsTo(AkunCOA::class, 'akun_coa_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function header(): BelongsTo
    {
        return $this->belongsTo(JurnalHeader::class, 'no_jurnal', 'no_jurnal');
    }

    /**
     * [ISO 9001 / ISO 27001] Integritas Jurnal Akuntansi: baris jurnal bersifat
     * immutable (tidak dapat diubah atau dihapus). Koreksi wajib melalui mekanisme
     * Jurnal Pembalik (Reversal / Storno).
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \DomainException('Baris jurnal akuntansi bersifat immutable (tidak dapat diubah) demi kepatuhan ISO/audit. Gunakan jurnal pembalik (reversal) untuk koreksi.');
        });

        static::deleting(function () {
            throw new \DomainException('Baris jurnal akuntansi bersifat immutable (tidak dapat dihapus) demi kepatuhan ISO/audit. Gunakan jurnal pembalik (reversal) untuk pembatalan.');
        });
    }
}
