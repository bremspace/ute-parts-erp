<?php

namespace App\Modules\Akunting\Models;

use App\Modules\Rbac\Traits\CatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'kode', 'nama', 'tipe', 'kelompok', 'saldo_normal', 'parent_id', 'is_active',
])]
class AkunCOA extends Model
{
    use CatatAktivitas;
    use LogsActivity;

    protected $table = 'akun_coa';

    /**
     * [B-10d / P1-3] Audit trail akun COA.
     *
     * `logOnly` — HANYA kolom yang Sensitive (definisi akun, saldo normal,
     * status aktif). Akun COA master GLOBAL (tanpa cabang_id) sehingga tidak
     * ada data cabang yang bocor lewat log.
     *
     * `logOnlyDirty` + `dontLogEmptyChanges()` — hemat storage di server 1GB:
     * update yang tidak menyentuh kolom di atas tidak menulis baris log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'kode', 'nama', 'tipe', 'kelompok', 'saldo_normal', 'parent_id', 'is_active',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => 'Akun COA '.self::labelAksiAktivitas($event));
    }

    protected $casts = [
        'is_active' => 'boolean',
        'parent_id' => 'integer',
    ];

    public function jurnal(): HasMany
    {
        return $this->hasMany(JurnalAkuntansi::class, 'akun_coa_id');
    }

    /**
     * [B-15d] Saldo akun (debit - kredit, atau sebaliknya utk saldo_normal kredit).
     *
     * DEPRECATED untuk query — accessor ini butuh 1 query SUM per akun; dipakai
     * di dalam loop = N+1. TIDAK ada pemanggil di kode saat ini; sengaja tidak
     * dihapus supaya API accessor tidak putus, tapi JANGAN dipakai lagi di
     * view/query. Untuk daftar akun (COA tree, laporan, dropdown) pakai
     * `AkunCOA::withSaldo()` — 1 query untuk semua akun (tanpa N+1).
     *
     * Bila hasil query-nya sudah lewat `withSaldo()` (atribut `saldo_debit` /
     * `saldo_kredit` ter-load), accessor ini 0 query — jadi tetap aman
     * dipanggil di loop selama asal datanya dari `withSaldo()`.
     *
     * @deprecated [B-15d] Ganti dgn `AkunCOA::withSaldo()` (withSum) utk query.
     */
    public function getSaldoAttribute(): float
    {
        if ($this->sudahPakaiWithSaldo()) {
            return $this->saldoDariSum();
        }

        $sum = $this->jurnal()
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(kredit), 0) as total_kredit')
            ->first();

        $debit = (float) ($sum->total_debit ?? 0);
        $kredit = (float) ($sum->total_kredit ?? 0);

        return $this->saldo_normal === 'debit'
            ? $debit - $kredit
            : $kredit - $debit;
    }

    /**
     * [B-15d] Query siap pakai: muat saldo (SUM debit & kredit) untuk semua akun
     * dalam 1 query lewat `withSum` — respek `saldo_normal` saat dibaca via
     * atribut `saldo` atau `$akun->saldo`.
     *
     * Contoh: `AkunCOA::withSaldo()->orderBy('kode')->get()`.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithSaldo(Builder $query): Builder
    {
        return $query
            ->withSum('jurnal as saldo_debit', 'debit')
            ->withSum('jurnal as saldo_kredit', 'kredit');
    }

    /**
     * True bila hasil query sudah membawa agregat from `withSaldo()`.
     */
    protected function sudahPakaiWithSaldo(): bool
    {
        return array_key_exists('saldo_debit', $this->attributes)
            || array_key_exists('saldo_kredit', $this->attributes);
    }

    /**
     * Hitung saldo dari agregat yang sudah ter-load (0 query).
     */
    protected function saldoDariSum(): float
    {
        $debit = (float) ($this->attributes['saldo_debit'] ?? 0);
        $kredit = (float) ($this->attributes['saldo_kredit'] ?? 0);

        return $this->saldo_normal === 'debit'
            ? $debit - $kredit
            : $kredit - $debit;
    }
}
