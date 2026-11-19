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
    'kode', 'nama', 'tipe', 'kelompok', 'saldo_normal', 'is_kontra', 'parent_id', 'is_active',
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
                'kode', 'nama', 'tipe', 'kelompok', 'saldo_normal', 'is_kontra', 'parent_id', 'is_active',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event) => 'Akun COA '.self::labelAksiAktivitas($event));
    }

    protected $casts = [
        'is_active' => 'boolean',
        'is_kontra' => 'boolean',
        'parent_id' => 'integer',
    ];

    /**
     * [F-01] Akun COA yang sudah memiliki baris jurnal TIDAK boleh dihapus:
     * FK jurnal_akuntansi.akun_coa_id semula cascadeOnDelete, sehingga
     * delete akun akan memusnahkan jejak audit jurnal (data loss, neraca rusak).
     * Guard ini melindungi semua jalur Eloquent (UI, API, migrasi down()).
     * Koreksi/penonaktifan akun berposting: nonaktifkan (is_active=false),
     * bukan hapus — sama dengan kebijakan hapusCoa di AkuntingDashboard.
     * Proteksi level DB (raw query builder) ditegakkan FK restrictOnDelete
     * pada migrasi 2026_11_20_000002.
     */
    protected static function booted(): void
    {
        static::deleting(function (AkunCOA $akun): void {
            if ($akun->jurnal()->exists()) {
                throw new \DomainException("Akun COA {$akun->kode} tidak dapat dihapus karena sudah memiliki baris jurnal (jejak audit tidak boleh musnah). Nonaktifkan akun alih-alih menghapus.");
            }
        });

        /*
         * [F-09] Akun yang sudah memiliki baris jurnal: klasifikasi (kode,
         * tipe, saldo_normal, is_kontra) TERKUNCI. Laporan historis membaca
         * tipe/saldo normal terkini saat query — mengubahnya menulis ulang
         * penyajian semua laporan masa lalu secara retroaktif (melanggar
         * PSAK 25/IAS 8: reklasifikasi harus prospektif/terdokumentasi, bukan
         * rewrite diam-diam). is_kontra ikut dikunci karena menentukan netting
         * laporan (ExportLaporanService::saldoLaporan). Nama/kelompok/status
         * aktif tetap boleh diubah (ter-log via LogsActivity). Reklasifikasi
         * akun berposting wajib lewat jurnal reklasifikasi. Guard di model =
         * semua jalur Eloquent (UI, API, caller lain) terlindungi. Koreksi
         * data terencana (mis. migration F-06/F-07) memakai raw DB secara
         * sengaja dan hanya boleh dilakukan bila laporan terbukti invarian.
         */
        static::updating(function (AkunCOA $akun): void {
            if ($akun->isDirty(['kode', 'tipe', 'saldo_normal', 'is_kontra']) && $akun->jurnal()->exists()) {
                $kodeAsli = (string) $akun->getOriginal('kode');
                throw new \DomainException("Akun COA {$kodeAsli} sudah memiliki baris jurnal: kode, tipe, saldo_normal, dan is_kontra tidak dapat diubah (reklasifikasi retroaktif laporan historis). Hanya nama/kelompok/status aktif yang boleh diubah; reklasifikasi wajib lewat jurnal reklasifikasi.");
            }
        });
    }

    /**
     * [F-08] Pola kode COA standar (PRD §4.6): ^[1-5]xx-xx —
     * 1xx aset, 2xx kewajiban, 3xx ekuitas, 4xx pendapatan, 5xx beban.
     * Kode di luar skema (mis. nomor rekening bank '907024506') ditolak.
     */
    public const POLA_KODE_COA = '/^[1-5]\d{2}-\d{2}$/';

    /**
     * [F-08] Saldo normal wajar per tipe (kerangka konseptual/PSAK):
     * aset & beban = debit; kewajiban, ekuitas, pendapatan = kredit.
     */
    public static function saldoNormalWajar(string $tipe): string
    {
        return in_array($tipe, ['aset', 'beban'], true) ? 'debit' : 'kredit';
    }

    /**
     * [F-08] Konsistensi tipe ↔ saldo_normal ↔ is_kontra untuk CRUD COA.
     * Akun biasa: saldo_normal WAJIB = saldoNormalWajar(tipe). Akun kontra
     * (is_kontra=true, eksplisit): saldo_normal WAJIB kebalikan dari wajar —
     * kontra-pendapatan debit, kontra-aset kredit, dst. Penyimpangan tanpa
     * flag = hack netting liar (akar F-06/F-07) dan ditolak.
     *
     * @return string|null pesan galat, atau null bila konsisten
     */
    public static function cekKonsistensiKlasifikasi(string $tipe, string $saldoNormal, bool $isKontra): ?string
    {
        $wajar = self::saldoNormalWajar($tipe);

        if ($isKontra) {
            $harus = $wajar === 'debit' ? 'kredit' : 'debit';

            return $saldoNormal === $harus ? null
                : "Akun kontra (is_kontra) bertipe {$tipe} wajib bersaldo_normal {$harus} (kebalikan saldo normal induk); diberikan {$saldoNormal}.";
        }

        return $saldoNormal === $wajar ? null
            : "saldo_normal {$saldoNormal} tidak sesuai tipe {$tipe} (normal {$wajar}). Akun kontra harus ditandai is_kontra=true secara eksplisit.";
    }

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
