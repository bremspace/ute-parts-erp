<?php

namespace App\Modules\Akunting\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\AsetTetap;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use Illuminate\Support\Carbon;

/**
 * [F3-3] Depresiasi aset tetap — garis lurus, posting HANYA via JurnalService::post().
 *
 * Jurnal bulanan per aset per periode (idempoten — dobel run aman):
 *   Debit  530-01 Beban Depresiasi
 *   Kredit 130-02 Akumulasi Depresiasi
 *
 * Jurnal disposal (write-off) aset:
 *   Debit  130-02 Akumulasi Depresiasi  (akumulasi berjalan)
 *   Debit  <akun kerugian>              (sisa buku)
 *   Kredit 160-01 Aktiva Tetap          (harga perolehan)
 *
 * Idempoten: (1) guard kolom aset_tetap.depresiasi_terakhir_bulan (YYYY-MM),
 * (2) cek jurnal existing no_jurnal deterministik per aset+periode.
 * Berhenti saat status fully_dep / disposal (tidak diproses sama sekali).
 *
 * Deviasi: COA retail existing tidak punya akun "Rugi Pelepasan Aset" khusus →
 * kerugian disposal memakai akun beban yang ada (520-05 Beban Lain-lain,
 * fallback 530-01) sesuai instruksi PRD — dicatat di sini & di report.
 */
class DepresiasiService
{
    public const AKUN_DEPRESIASI = '530-01';

    public const AKUN_AKUMULASI = '130-02';

    public const AKUN_ASET = '160-01';

    public function __construct(
        protected JurnalService $jurnalService
    ) {}

    /**
     * Proses depresiasi satu periode (YYYY-MM) — 1 jurnal per aset per periode.
     *
     * @param  string  $periode  format 'YYYY-MM'
     * @param  int|null  $cabangId  null = semua cabang (diproses per cabang aset)
     * @return array{diproses: int, dilewati: int, total: float, periode: string}
     *
     * @throws \Exception format periode tidak valid
     */
    public function prosesPeriode(string $periode, ?int $cabangId = null, ?int $userId = null): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode)) {
            throw new \Exception('Format periode tidak valid — gunakan format YYYY-MM (contoh: 2026-09)');
        }

        $akhirPeriode = Carbon::parse($periode.'-01')->startOfMonth()->endOfMonth();

        $query = AsetTetap::query()
            ->where('status', 'aktif')
            ->orderBy('id');

        if ($cabangId !== null) {
            $query->where('cabang_id', $cabangId);
        }

        $hasil = ['diproses' => 0, 'dilewati' => 0, 'total' => 0.0, 'periode' => $periode];

        foreach ($query->get() as $aset) {
            // Guard 1: sudah diproses periode ini (atau periode lebih baru)
            if ($aset->depresiasi_terakhir_bulan !== null && $aset->depresiasi_terakhir_bulan >= $periode) {
                $hasil['dilewati']++;

                continue;
            }

            // Aset belum dimiliki pada periode ini
            if ($aset->tanggal_perolehan->copy()->startOfMonth()->gt($akhirPeriode)) {
                $hasil['dilewati']++;

                continue;
            }

            $noJurnal = $this->noJurnalDepresiasi($aset, $periode);

            // Guard 2: jurnal existing sumber aset + periode (belt & braces)
            if (JurnalAkuntansi::where('no_jurnal', $noJurnal)->exists()) {
                $aset->update(['depresiasi_terakhir_bulan' => $periode]);
                $hasil['dilewati']++;

                continue;
            }

            $nominal = $this->hitungNominal($aset, $periode);

            if ($nominal <= 0) {
                // Tidak ada sisa buku → tandai habis tanpa jurnal (jurnal wajib > 0)
                $aset->update([
                    'akumulasi_depresiasi' => $aset->harga_perolehan,
                    'depresiasi_terakhir_bulan' => $periode,
                    'status' => 'fully_dep',
                ]);
                $hasil['dilewati']++;

                continue;
            }

            $this->jurnalService->post(
                $noJurnal,
                $akhirPeriode,
                'depresiasi',
                [
                    ['akun_kode' => self::AKUN_DEPRESIASI, 'debit' => $nominal, 'kredit' => 0],
                    ['akun_kode' => self::AKUN_AKUMULASI, 'debit' => 0, 'kredit' => $nominal],
                ],
                "Depresiasi aset {$aset->nama} periode {$periode}",
                (int) $aset->cabang_id,
                $userId,
                'aset_tetap',
                $aset->id
            );

            $akumulasi = round((float) $aset->akumulasi_depresiasi + $nominal, 2);
            $sisaBaru = round((float) $aset->harga_perolehan - $akumulasi, 2);

            $aset->update([
                'akumulasi_depresiasi' => min($akumulasi, (float) $aset->harga_perolehan),
                'depresiasi_terakhir_bulan' => $periode,
                'status' => $sisaBaru > 0 ? 'aktif' : 'fully_dep',
            ]);

            $hasil['diproses']++;
            $hasil['total'] = round($hasil['total'] + $nominal, 2);
        }

        return $hasil;
    }

    /**
     * Nominal depresiasi satu periode (garis lurus).
     *
     * - Periode normal: harga_perolehan / umur_bulan (dibatasi sisa buku).
     * - Periode ke-umur_bulan ke atas (termasuk aset lama yang belum pernah
     *   diposting = catch-up satu kali): seluruh sisa buku → akumulasi = harga,
     *   status fully_dep, berhenti total.
     */
    public function hitungNominal(AsetTetap $aset, string $periode): float
    {
        $harga = (float) $aset->harga_perolehan;
        $umur = (int) $aset->umur_bulan;
        $sisa = round($harga - (float) $aset->akumulasi_depresiasi, 2);

        if ($umur <= 0 || $harga <= 0 || $sisa <= 0) {
            return 0.0;
        }

        $bulanPerolehan = $aset->tanggal_perolehan->copy()->startOfMonth();
        $bulanPeriode = Carbon::parse($periode.'-01')->startOfMonth();
        // Indeks bulan ke-1 = bulan perolehan; abs() jaga-jaga bila periode mundur.
        $indeks = (int) round(abs($bulanPerolehan->diffInMonths($bulanPeriode))) + 1;

        // Bulan terakhir umur (atau lewat) → seluruh sisa buku (plug pembulatan / catch-up)
        if ($indeks >= $umur) {
            return $sisa;
        }

        return min(round($harga / $umur, 2), $sisa);
    }

    /**
     * Write-off aset (disposal): hapus dari buku + catat sisa buku sebagai beban.
     *
     * @return array{no_jurnal: string, harga: float, akumulasi: float, kerugian: float, akun_kerugian: string}
     *
     * @throws \Exception aset sudah pernah disposal
     */
    public function dispose(AsetTetap $aset, ?int $userId = null): array
    {
        if ($aset->status === 'disposal') {
            throw new \Exception("Aset \"{$aset->nama}\" sudah pernah didisposal — tidak boleh write-off dua kali.");
        }

        $harga = round((float) $aset->harga_perolehan, 2);
        $akumulasi = round((float) $aset->akumulasi_depresiasi, 2);
        $sisa = round($harga - $akumulasi, 2);

        if ($harga <= 0) {
            throw new \Exception('Harga perolehan aset tidak valid — disposal dibatalkan.');
        }

        $lines = [];

        if ($akumulasi > 0) {
            $lines[] = ['akun_kode' => self::AKUN_AKUMULASI, 'debit' => $akumulasi, 'kredit' => 0];
        }

        $akunKerugian = $this->akunKerugian();

        if ($sisa > 0) {
            $lines[] = ['akun_kode' => $akunKerugian, 'debit' => $sisa, 'kredit' => 0];
        }

        $lines[] = ['akun_kode' => self::AKUN_ASET, 'debit' => 0, 'kredit' => $harga];

        $noJurnal = sprintf('JRL-DSP-%s-%04d', $aset->cabang_id ?? 'X', $aset->id);

        $this->jurnalService->post(
            $noJurnal,
            now(),
            'disposal',
            $lines,
            "Disposal aset {$aset->nama} (write-off)",
            (int) $aset->cabang_id,
            $userId,
            'aset_tetap',
            $aset->id
        );

        $aset->update(['status' => 'disposal']);

        return [
            'no_jurnal' => $noJurnal,
            'harga' => $harga,
            'akumulasi' => $akumulasi,
            'kerugian' => $sisa,
            'akun_kerugian' => $akunKerugian,
        ];
    }

    /**
     * Akun kerugian pelepasan aset — tidak ada akun khusus di COA retail existing.
     * Urutan: akun beban bernama rugi/kehilangan (bila suatu saat ditambahkan) →
     * 520-05 Beban Lain-lain → 530-01 Beban Depresiasi (fallback sesuai PRD).
     *
     * DEVIASI DICATAT: pemakaian beban umum menggantikan "Rugi Pelepasan Aset".
     */
    public function akunKerugian(): string
    {
        $khusus = AkunCOA::where('tipe', 'beban')
            ->where('is_active', true)
            ->get()
            ->first(fn (AkunCOA $a) => (bool) preg_match('/rugi|kehilangan|pelepasan|dispasal/i', $a->nama));

        if ($khusus) {
            return $khusus->kode;
        }

        if (AkunCOA::where('kode', '520-05')->exists()) {
            return '520-05';
        }

        return self::AKUN_DEPRESIASI;
    }

    /** No jurnal deterministik per aset + periode (idempotency key). */
    public function noJurnalDepresiasi(AsetTetap $aset, string $periode): string
    {
        return sprintf(
            'JRL-DEP-%s-%s-%04d',
            $aset->cabang_id ?? 'X',
            str_replace('-', '', $periode),
            $aset->id
        );
    }
}
