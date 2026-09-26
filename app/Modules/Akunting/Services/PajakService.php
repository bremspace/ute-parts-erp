<?php

namespace App\Modules\Akunting\Services;

use Illuminate\Support\Facades\DB;

/**
 * [T-22][F1-2] Pajak Otomatis (PPN) — service per-cabang.
 * Konfigurasi di tabel `konfigurasi` (kontrak AC F1-2):
 *   - pajak_enabled.{cabangId} / pajak_enabled — opt-in per cabang (fallback: ppn_enabled lama), default false
 *   - ppn_percent.{cabangId} / ppn_percent     — persen PPN (default 11)
 * Perubahan konfigurasi hanya berlaku utk transaksi baru (tidak retroaktif).
 */
class PajakService
{
    /**
     * Hitung nilai PPN untuk DPP (Harga Pokok Penjualan) yang diberikan.
     *
     * @param  ?int  $cabangId  — cabang aktif (session), nullable utk fallback global
     * @param  float  $dpp  — harga di luar pajak (existing "harga" di Transaksi)
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
     * [B-14] Ringkasan penjualan (DPP / PPN / total akhir) — definisi KANONIK
     * yang sama persis dgn POS (`PosController::store()`):
     *
     *   DPP   = subtotal - diskon   (harga di luar pajak / nilai transaksi bruto)
     *   PPN   = DPP × persen        (0 bila pajak nonaktif utk cabang tsb)
     *   Total = DPP + PPN           (nilai yang benar-benar ditagihkan/dibayar)
     *
     * Sifatnya tax-exclusive: `total_akhir` MEMASUKKAN PPN, sedangkan akun
     * pendapatan (410-01) hanya menerima DPP. Tanpa ini, penjualan marketplace
     * membukukan seluruh `total_akhir` ke pendapatan → laporan penjualan
     * marketplace ≠ POS dan PPN tak pernah masuk akun pajak 220-01 (F1-2).
     *
     * Perbedaan konfigurasi tidak retroaktif: nilai dihitung SATU KALI saat
     * order dibuat, lalu disimpan (dpp / pajak_nominal / ppn_nominal) — webhook
     * cukup memisahkannya, tidak menghitung ulang.
     *
     * @return array{dpp:float, ppn_nominal:float, ppn_percent:float, enabled:bool, total_akhir:float}
     */
    public function hitungPenjualan(?int $cabangId, float $subtotal, float $diskon = 0.0): array
    {
        $dpp = max(0.0, round($subtotal - max(0.0, $diskon), 2));
        $hitung = $this->hitung($cabangId, $dpp);
        $ppnNominal = (float) $hitung['ppn_nominal'];

        return [
            'dpp' => $dpp,
            'ppn_nominal' => $ppnNominal,
            'ppn_percent' => (float) $hitung['ppn_percent'],
            'enabled' => (bool) $hitung['enabled'],
            'total_akhir' => round($dpp + $ppnNominal, 2),
        ];
    }

    /**
     * Apakah fitur Pajak Otomatis diaktifkan utk cabang ini?
     * Kunci utama: pajak_enabled.{cabang} / pajak_enabled (kontrak AC F1-2).
     * Fallback: ppn_enabled.{cabang} / ppn_enabled (konfigurasi lama — backward compat).
     */
    public function enabled(?int $cabangId): bool
    {
        $perKey = $cabangId ? "pajak_enabled.{$cabangId}" : 'pajak_enabled';
        $legacyPerKey = $cabangId ? "ppn_enabled.{$cabangId}" : 'ppn_enabled';

        foreach ([$perKey, $legacyPerKey] as $key) {
            $val = DB::table('konfigurasi')->where('kunci', $key)->value('nilai');
            if ($val !== null) {
                $decoded = json_decode((string) $val, true);

                return (bool) ($decoded ?? $val);
            }
        }

        return false; // default false — aman
    }

    /**
     * Persen PPN utk cabang ini (default 11 — kontrak AC F1-2).
     * Kunci: ppn_percent.{cabang} / ppn_percent.
     */
    public function getPercent(?int $cabangId): float
    {
        $key = $cabangId ? "ppn_percent.{$cabangId}" : 'ppn_percent';
        $val = DB::table('konfigurasi')->where('kunci', $key)->value('nilai');
        if ($val === null && $cabangId !== null) {
            $val = DB::table('konfigurasi')->where('kunci', 'ppn_percent')->value('nilai'); // fallback global
        }
        if ($val === null) {
            return 11.0;
        }
        $decoded = json_decode((string) $val, true);
        if (is_array($decoded)) {
            $decoded = $decoded['value'] ?? null;
        }

        return (float) ($decoded ?? $val);
    }

    /**
     * Atur konfigurasi Pajak Otomatis (tulis key baru pajak_enabled + legacy ppn_enabled).
     *
     * @param  ?int  $cabangId  nullable utk set global
     * @param  float  $percent  0-100
     */
    public function set(?int $cabangId, bool $enabled, float $percent): void
    {
        $this->setBool($cabangId, 'pajak_enabled', $enabled);   // kontrak AC F1-2
        $this->setBool($cabangId, 'ppn_enabled', $enabled);     // backward compat
        $this->setFloat($cabangId, 'ppn_percent', $percent);
    }

    /**
     * Baris jurnal untuk PPN Keluaran — akun 220-01 (kontrak AC F1-2).
     * Dipakai di PosKasir & PosController agar konsisten.
     *
     * @param  float  $nominal  nilai PPN Keluaran
     * @param  string  $noJurnal  untuk referensi
     * @return array [['akun_kode'=>'220-01', 'debit'=>0, 'kredit'=>$nominal], ...]
     */
    public function jurnalLines(float $nominal, string $noJurnal, int $cabangId, int $userId): array
    {
        if ($nominal <= 0) {
            return [];
        }

        return [
            ['akun_kode' => '220-01', 'debit' => 0, 'kredit' => $nominal],
        ];
    }

    /**
     * Inisialisasi baris konfigurasi default di tabel `konfigurasi` (idempotent).
     */
    public function seedDefaults(): void
    {
        $defaults = [
            ['kunci' => 'pajak_enabled', 'nilai' => 'false', 'deskripsi' => 'Aktifkan Pajak Otomatis (PPN) global'],
            ['kunci' => 'ppn_enabled', 'nilai' => 'false', 'deskripsi' => 'Aktifkan Pajak Otomatis per cabang'],
            ['kunci' => 'ppn_percent', 'nilai' => '11', 'deskripsi' => 'Persen PPN nasional default'],
        ];

        foreach ($defaults as $d) {
            DB::table('konfigurasi')->updateOrInsert(
                ['kunci' => $d['kunci']],
                ['nilai' => $d['nilai'], 'deskripsi' => $d['deskripsi'], 'created_at' => now(), 'updated_at' => now()]
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
            ['nilai' => (string) $val, 'deskripsi' => null, 'created_at' => now(), 'updated_at' => now()]
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
            ['nilai' => (string) $val, 'deskripsi' => null, 'created_at' => now(), 'updated_at' => now()]
        );
    }
}
