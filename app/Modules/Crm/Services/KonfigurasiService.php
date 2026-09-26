<?php

namespace App\Modules\Crm\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * [T-22] Konfigurasi operasional (strategi loyalitas) — editable tanpa deploy.
 * Tabel `konfigurasi`: kunci → nilai (JSON utk kompleks).
 * Perubahan hanya berlaku untuk transaksi baru (tidak retroaktif).
 */
class KonfigurasiService
{
    /**
     * [B-15d] TTL cache konfigurasi. Nilai konfigurasi jarang berubah (di-set dari
     * halaman Pengaturan / API CRM), jadi 5 menit aman; setiap `set()` wajib
     * forget key terkait (lihat set()) supaya perubahan tetap langsung berlaku.
     */
    private const CACHE_TTL = 300;

    private const CACHE_PREFIX = 'crm-konfigurasi:';

    public function get(string $kunci, mixed $default = null): mixed
    {
        // [B-15d] Cache per-kunci (invalidasi di set()) — 1 query per kunci hilang.
        //
        // Nilai dibungkus array `['nilai' => …]`: `Cache::remember()` memakai
        // `! is_null($value)` sebagai penanda hit, jadi nilai NULL (kunci yang
        // belum pernah disimpan) akan MISS terus-menerus dan query terus kena.
        $hit = Cache::remember(
            self::CACHE_PREFIX.$kunci,
            self::CACHE_TTL,
            fn (): array => ['nilai' => $this->ambilNilai($kunci) ?? $default]
        );

        return $hit['nilai'];
    }

    /**
     * [B-15d] Ambil banyak kunci dalam SATU query (`whereIn`).
     * Dipakai CrmController::config() GET yang butuh 5 kunci sekaligus —
     * sebelumnya 5 query (1 per kunci), sekarang 1.
     *
     * @param  array<int, string>  $kunci
     * @return array<string, mixed> hanya kunci yang ADA di tabel konfigurasi
     */
    public function getMany(array $kunci): array
    {
        $kunci = array_values(array_unique($kunci));
        if ($kunci === []) {
            return [];
        }

        $rows = DB::table('konfigurasi')->whereIn('kunci', $kunci)->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $hasil = [];
        foreach ($rows as $row) {
            $hasil[$row->kunci] = $this->decode((string) $row->nilai);
        }

        return $hasil;
    }

    public function set(string $kunci, mixed $nilai, ?string $deskripsi = null): void
    {
        $existing = DB::table('konfigurasi')->where('kunci', $kunci)->first();
        $now = now();

        if ($existing) {
            DB::table('konfigurasi')->where('kunci', $kunci)->update([
                'nilai' => is_scalar($nilai) ? (string) $nilai : json_encode($nilai),
                'deskripsi' => $deskripsi,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('konfigurasi')->insert([
                'kunci' => $kunci,
                'nilai' => is_scalar($nilai) ? (string) $nilai : json_encode($nilai),
                'deskripsi' => $deskripsi,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // [B-15d] Invalidasi cache WAJIB di titik tulis (satu-satunya jalur tulis).
        Cache::forget(self::CACHE_PREFIX.$kunci);
    }

    /**
     * [B-15d] Baca 1 kunci langsung dari DB (tanpa cache) — dipakai `get()`.
     */
    private function ambilNilai(string $kunci): mixed
    {
        $row = DB::table('konfigurasi')->where('kunci', $kunci)->first();
        if (! $row) {
            return null;
        }

        return $this->decode((string) $row->nilai);
    }

    /**
     * Nilai konfigurasi disimpan sebagai string; string JSON → decode, selain itu
     * dikembalikan apa adanya (paritas dgn perilaku lama).
     */
    private function decode(string $val): mixed
    {
        $decoded = json_decode($val, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
    }

    /**
     * Default strategi (dipakai PricingService/TierService bila config kosong).
     */
    public function defaults(): array
    {
        return [
            'poin_earn_persen' => 5,          // % dari nominal transaksi → poin
            'poin_redeem_rupiah' => 100,       // 1 poin = Rp X diskon
            'diskon_silver' => 3,
            'diskon_gold' => 5,
            'diskon_platinum' => 10,
        ];
    }

    public function poinEarnPersen(): float
    {
        return (float) ($this->get('poin_earn_persen') ?? 0);
    }

    public function poinRedeemRupiah(): float
    {
        return (float) ($this->get('poin_redeem_rupiah') ?? 0);
    }

    /**
     * Apakah Pajak Otomatis diaktifkan di cabang ini?
     * Returning nullable utk backward compatibility.
     */
    public function getPpnEnabled(?int $cabangId = null): ?bool
    {
        $key = $cabangId ? "ppn_enabled.{$cabangId}" : 'ppn_enabled';
        $val = $this->get($key);

        return $val === null ? null : (bool) $val;
    }

    /**
     * Persen PPN utk cabang ini (default 11).
     */
    public function getPpnPercent(?int $cabangId = null): float
    {
        $key = $cabangId ? "ppn_percent.{$cabangId}" : 'ppn_percent';

        return (float) ($this->get($key) ?? 11);
    }

    /**
     * Simpan konfigurasi Pajak Otomatis.
     */
    public function setPpn(?int $cabangId, bool $enabled, float $percent): void
    {
        $this->set("ppn_enabled.{$cabangId}", $enabled, 'Pajak Otomatis diaktifkan di cabang');
        $this->set("ppn_percent.{$cabangId}", $percent, 'Persen PPN per cabang');
    }

    /**
     * Setup konfigurasi PPN default (idempotent).
     */
    public function seedPpnDefaults(): void
    {
        $this->set('ppn_enabled', false, 'Aktifkan Pajak Otomatis per cabang');
        $this->set('ppn_percent', 11, 'Persen PPN nasional default');
    }
}
