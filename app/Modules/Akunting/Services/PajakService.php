<?php

namespace App\Modules\Akunting\Services;

use Illuminate\Support\Facades\DB;

/**
 * [T-22] Pajak Otomatis (PPN) — service rekonsiliasi per-cabang.
 * Konfigurasi disimpan di tabel `konfigurasi`:
 *   - ppn_enabled (boolean) — opt-in per cabang, default false
 *   - ppn_percent (float) — persen (default 11)
 *   Perubahan konfigurasi hanya berlaku utk transaksi baru (tidak retroaktif).
 */
class PajakService
{
    /**
     * Hitung nilai PPN untuk DPP (Harga Pokok Penjualan) yang diberikan.
     *
     * @param  ?int  $cabangId  — cabang aktif (session), nullable utk fallback global
     * @param  float $dpp        — harga di luar pajak (existing "harga" di Transaksi)
     * @return array ['ppn_percent'=>float, 'ppn_nominal'=>float, 'dpp'=>float, 'enabled'=>bool]
     */
    public function hitung(?int $cabangId, float $dpp): array
    {
        $enabled = $this->enabled($cabangId);
        $percent = $enabled ? $this->getPercent($cabangId) : 0.0;
        $nominal = $enabled ? round($dpp * $percent / 100, 2) : 0.0;

        return [
            'ppn_percent' => $percent,
            'ppn_nominal' => $nominal,
            'dpp' => $dpp,
            'enabled' => $enabled,
        ];
    }

    /**
     * Apakah fitur Pajak Otomatis diaktifkan utk cabang ini?
     */
    public function enabled(?int $cabangId): bool
    {
        $key = $cabangId ? "ppn_enabled.{$cabangId}" : 'ppn_enabled';
        $val = DB::table('konfigurasi')->where('kunci', $key)->value('nilai');
        if ($val === null) {
            return false; // default false — aman
        }
        $decoded = json_decode((string) $val, true);
        return (bool) ($decoded ?? $val);
    }

    /**
     * Ambil persen PPN utk cabang (default 11).
     */
    public function getPercent(?int $cabangId): float
    {
        $key = $cabangId ? "ppn_percent.{$cabangId}" : 'ppn_percent';
        $val = DB::table('konfigurasi')->where('kunci', $key)->value('nilai');
        if ($val === null) {
            return 11.0; // default nasional
        }
        $decoded = json_decode((string) $val, true);
        $num = is_array($decoded) ? ($decoded['value'] ?? 0) : $val;
        return (float) $num;
    }

    /**
     * Atur konfigurasi Pajak Otomatis.
     *
     * @param  ?int  $cabangId  nullable utk set global (belum digunakan saat ini)
     * @param  bool  $enabled
     * @param  float $percent  0-100
     */
    public function set(?int $cabangId, bool $enabled, float $percent): void
    {
        $this->setBool($cabangId, 'ppn_enabled', $enabled);
        $this->setFloat($cabangId, 'ppn_percent', $percent);
    }

    /**
     * Baris jurnal untuk PPN Keluaran.
     * Dipakai di PosController & ServisController agar konsisten.
     *
     * @param  float $nominal               nilai PPN Keluaran
     * @param  string $noJurnal             untuk referensi
     * @param  int   $cabangId
     * @param  int   $userId
     * @return array [['akun_kode'=>'220-02', 'debit'=>0, 'kredit'=>$nominal], ...]
     */
    public function jurnalLines(float $nominal, string $noJurnal, int $cabangId, int $userId): array
    {
        if ($nominal <= 0) {
            return [];
        }
        // Akun PPN Keluaran (buat migrasi seeder baru bila belum ada)
        return [
            ['akun_kode' => '220-02', 'debit' => 0, 'kredit' => $nominal],
        ];
    }

    /**
     * Inisialisasi baris konfigurasi default di tabel `konfigurasi`.
     * Harus dijalankan sekali via migration seeder (idempotent).
     */
    public function seedDefaults(): void
    {
        $defaults = [
            ['kunci' => 'ppn_enabled', 'nilai' => 'false', 'deskripsi' => 'Aktifkan Pajak Otomatis per cabang'],
            ['kunci' => 'ppn_percent', 'nilai' => '11', 'deskripsi' => 'Persen PPN nasional default'],
        ];

        foreach ($defaults as $d) {
            DB::table('konfigurasi')->updateOrInsert(
                ['kunci' => $d['kunci']],
                ['nilai' => $d['nilai'], 'deskripsi' => $d['deskripsi'], 'updated_at' => now()]
            );
        }
    }

    /**
     * Simpan nilai boolean untuk cabang.
     */
    private function setBool(?int $cabangId, string $base, bool $val): void
    {
        $kunci = $cabangId ? "{$base}.{$cabangId}" : $base;
        DB::table('konfigurasi')->updateOrInsert(
            ['kunci' => $kunci],
            ['nilai' => (string) $val, 'deskripsi' => null, 'updated_at' => now()]
        );
    }

    /**
     * Simpan nilai float untuk cabang.
     */
    private function setFloat(?int $cabangId, string $base, float $val): void
    {
        $kunci = $cabangId ? "{$base}.{$cabangId}" : $base;
        DB::table('konfigurasi')->updateOrInsert(
            ['kunci' => $kunci],
            ['nilai' => (string) $val, 'deskripsi' => null, 'updated_at' => now()]
        );
    }
}