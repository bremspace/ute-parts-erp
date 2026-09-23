<?php

namespace App\Modules\Crm\Services;

use Illuminate\Support\Facades\DB;

/**
 * [T-22] Konfigurasi operasional (strategi loyalitas) — editable tanpa deploy.
 * Tabel `konfigurasi`: kunci → nilai (JSON utk kompleks).
 * Perubahan hanya berlaku untuk transaksi baru (tidak retroaktif).
 */
class KonfigurasiService
{
    public function get(string $kunci, mixed $default = null): mixed
    {
        $row = DB::table('konfigurasi')->where('kunci', $kunci)->first();
        if (! $row) {
            return $default;
        }

        $val = $row->nilai;
        $decoded = json_decode((string) $val, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
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
