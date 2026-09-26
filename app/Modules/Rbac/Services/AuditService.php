<?php

namespace App\Modules\Rbac\Services;

use App\Modules\Rbac\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Audit log (PRD §6): semua perubahan sensitif tercatat:
 * harga, stok manual, approval komisi, override servis, user, tier, COA.
 * who = auth()->id(), what = entitas+aksi+deskripsi, before/after = snapshot.
 *
 * [B-10d / P1-3] Dua deficiency yang ditutup:
 * 1. `audit_logs` tidak punya kolom `cabang_id` → log tidak bisa disaring per
 *    cabang (melanggar aturan scope `cabang_id` di AGENTS.md). Sekarang setiap
 *    baris di-stamp `session('cabang_id')` = cabang aktif; NULL bila tidak ada
 *    sesi (CLI/queue/entitas global).
 * 2. Snapshot before/after sering kosong. Sekarang:
 *    - argumen boleh berupa Eloquent Model / Collection dan otomatis
 *      diserialisasi jadi snapshot array;
 *    - `entitas_id` boleh null → diambil dari primary key model bila ada;
 *    - bila snapshot tetap tidak tersedia, kolom dibiarkan NULL — TIDAK diisi
 *      `{}`. Memalsukan snapshot lebih berbahaya daripada kosong; yang wajib
 *      tetap tercatat adalah aksinya.
 */
class AuditService
{
    /**
     * Kolom yang tidak boleh masuk snapshot `audit_logs` (rahasia/kredensial).
     * Sepadan dengan config('activitylog.default_except_attributes') + tambahan
     * domain (kunci HP, OTP, kode verifikasi).
     *
     * @var string[]
     */
    private const ATTRIBUT_SENSITIF = [
        'password',
        'password_hash',
        'remember_token',
        'token',
        'api_token',
        'secret',
        'two_factor_secret',
        'two_factor_backup_codes',
        'kunci_terenkripsi',
        'kode_verifikasi',
        'otp',
        'pin',
    ];

    /**
     * Catat satu baris audit log.
     *
     * @param  string  $entitas  nama entitas (Produk, AkunCOA, TiketServis, ...)
     * @param  string  $aksi  create, update, approve, reject, override, adjust, delete
     * @param  int|null  $entitasId  null → dicoba dari key model di $sebelum/$sesudah
     * @param  array|object|Collection|Model|null  $sebelum
     * @param  array|object|Collection|Model|null  $sesudah
     */
    public function catat(
        string $entitas,
        string $aksi,
        ?int $entitasId,
        string $deskripsi,
        array|object|null $sebelum = null,
        array|object|null $sesudah = null
    ): AuditLog {
        // Kunci model harus diambil SEBELUM snapshot di-normalisasi jadi array.
        $keySesudah = $this->keyDariModel($sesudah);
        $keySebelum = $this->keyDariModel($sebelum);

        $sebelum = $this->snapshot($sebelum);
        $sesudah = $this->snapshot($sesudah);

        $entitasId ??= $keySesudah ?? $keySebelum;

        // forceFill (bukan create()): kolom `cabang_id` hasil migrasi B-10d
        // belum terdaftar di atribut Fillable model AuditLog, dan model itu
        // berada di luar lane berkas B-10d. Semua kunci di bawah hardcoded
        // (bukan input user), jadi mass-assignment tidak jadi risiko.
        $log = new AuditLog;
        $log->forceFill([
            'user_id' => auth()->id() ?? auth('customer')->id(),
            'cabang_id' => $this->cabangAktif(),
            'entitas' => $entitas,
            'aksi' => $aksi,
            'entitas_id' => $entitasId,
            'deskripsi' => $deskripsi,
            'sebelum' => $sebelum,
            'sesudah' => $sesudah,
            'ip' => request()->ip(),
        ]);
        $log->save();

        return $log;
    }

    /**
     * Normalisasi snapshot (Model/Collection/array/object) → array aman.
     *
     * Mengembalikan NULL (bukan `[]`) bila tidak ada isi — kolom JSON nullable,
     * dan `{}` akan menyesatkan pembaca audit (terlihat seperti "tidak berubah").
     *
     * @return array<string, mixed>|null
     */
    public function snapshot(array|object|null $data): ?array
    {
        if ($data === null) {
            return null;
        }

        if ($data instanceof Model) {
            $data = $data->attributesToArray();
        } elseif ($data instanceof Collection) {
            $data = $data->toArray();
        }

        $snapshot = json_decode(json_encode($data), true);

        if (! is_array($snapshot) || $snapshot === []) {
            return null;
        }

        // Buang field sensitif supaya audit log tidak jadi jalur kebocoran.
        foreach (array_keys($snapshot) as $kolom) {
            if (in_array(strtolower((string) $kolom), self::ATTRIBUT_SENSITIF, true)) {
                unset($snapshot[$kolom]);
            }
        }

        return $snapshot === [] ? null : $snapshot;
    }

    /**
     * Cabang aktif dari session; NULL di CLI/queue/tanpa session.
     */
    private function cabangAktif(): ?int
    {
        $request = request();

        // Fast path: request HTTP yang sudah punya middleware StartSession.
        // Fallback ke session store supaya tetap bekerja di queue job, artisan
        // command, dan test yang menulis session() tanpa request lifecycle.
        $cabangId = $request->hasSession()
            ? $request->session()->get('cabang_id')
            : session('cabang_id');

        return $cabangId === null ? null : (int) $cabangId;
    }

    /**
     * Ambil primary key dari argumen Model (dipakai kalau $entitasId null).
     */
    private function keyDariModel(mixed $data): ?int
    {
        return $data instanceof Model && $data->getKey() !== null
            ? (int) $data->getKey()
            : null;
    }
}
