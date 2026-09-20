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
        if (!$row) {
            return $default;
        }

        $val = $row->nilai;
        $decoded = json_decode((string) $val, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
    }

    public function set(string $kunci, mixed $nilai, ?string $deskripsi = null): void
    {
        DB::table('konfigurasi')->updateOrInsert(
            ['kunci' => $kunci],
            [
                'nilai' => is_scalar($nilai) ? (string) $nilai : json_encode($nilai),
                'deskripsi' => $deskripsi,
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE((SELECT created_at FROM konfigurasi WHERE kunci = "' . $kunci . '"), "' . now() . '")'),
            ]
        );
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
}