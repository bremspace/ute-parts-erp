<?php

namespace App\Modules\Akunting\Services;

use App\Modules\Akunting\Models\AkunCOA;

/**
 * [B-10e / P2-1] Sumber tunggal aturan validasi baris jurnal.
 *
 * Sebelumnya aturan ini hanya hidup di form UI (Livewire AkuntingDashboard),
 * sedangkan API [API: ACC-04] hanya `exists:akun_coa,kode` dan
 * `JurnalService::post()` hanya mencari kode → jurnal bisa diposting ke akun
 * nonaktif atau ke sisi yang berlawanan dengan saldo normal akun.
 *
 * [B-10g] Tiga pemakai WAJIB memakai kelas ini sebagai SATU-SATUNYA sumber
 * aturan (jangan menyalin aturannya):
 *  - `JurnalService::post()` (semua sumber jurnal),
 *  - `AkuntingController::storeJurnalManual()` [API: ACC-04] via `cekBaris()`,
 *  - `AkuntingDashboard::buildManualJournalValidation()` (form UI) via
 *    `cekBarisPerBaris()` + method pesan yang sama.
 *
 * PEMBAGIAN TANGGUNG JAWAB (sengaja dipisah):
 *  - `cariAktif()` — akun HARUS ada + `is_active` → berlaku untuk SEMUA sumber
 *    jurnal (pos/servis/komisi/opname/pembelian/manual) via JurnalService::post().
 *  - `cekBaris()` — sisi harus sesuai `saldo_normal` + nominal > 0 → khusus
 *    entri MANUAL satu-sisi. Alur otomatis sah melakukan entri kontra-sisi,
 *    mis. Persediaan (aset, saldo normal debit) di-KREDIT saat penjualan barang,
 *    sehingga aturan sisi tidak boleh dipaksakan di service.
 *  - Keseimbangan total debit = total kredit tetap dijaga JurnalService::post()
 *    (tidak diubah di sini); TEKS pesannya didefinisikan di
 *    `pesanBelumBalance()` / `pesanNilaiTransaksiNol()` supaya form UI dan
 *    service tidak berbeda.
 *
 * Semua pesan Bahasa Indonesia (kontrak error UI & API).
 */
class ValidasiBarisJurnal
{
    /** Akun COA ber kode tsb, tanpa pembatasan status aktif. */
    public static function cari(string $kode): ?AkunCOA
    {
        $kode = trim($kode);

        return $kode === '' ? null : AkunCOA::where('kode', $kode)->first();
    }

    /** Akun COA aktif ber kode tsb, atau null bila tidak ada / nonaktif. */
    public static function cariAktif(string $kode): ?AkunCOA
    {
        $akun = static::cari($kode);

        return ($akun && $akun->is_active) ? $akun : null;
    }

    public static function pesanAkunTidakAda(string $kode): string
    {
        return 'Akun COA tidak ditemukan: '.trim($kode);
    }

    public static function pesanAkunNonaktif(AkunCOA $akun): string
    {
        return sprintf(
            'Akun %s — %s sedang nonaktif dan tidak dapat dipakai untuk jurnal baru.',
            $akun->kode,
            $akun->nama
        );
    }

    public static function pesanSisiTidakSesuai(AkunCOA $akun, string $sisi): string
    {
        // Pesan yang sama dengan indikator real-time di form jurnal manual.
        return sprintf(
            'Akun %s — %s tidak dapat dipilih pada sisi %s karena saldo normalnya %s.',
            $akun->kode,
            $akun->nama,
            $sisi,
            $akun->saldo_normal
        );
    }

    public static function pesanNominalNol(AkunCOA $akun): string
    {
        return sprintf(
            'Nominal jurnal pada akun %s harus lebih besar dari nol.',
            $akun->kode
        );
    }

    public static function pesanSatuSisi(AkunCOA $akun): string
    {
        return sprintf(
            'Baris akun %s hanya boleh berisi satu sisi (debit atau kredit), tidak keduanya.',
            $akun->kode
        );
    }

    /**
     * [B-10g] Pesan jurnal belum balance.
     *
     * TEKS SENGAJA IDENTIK dengan pesan yang ditthrow `JurnalService::post()`
     * supaya pesan di form dashboard, API [API: ACC-04] (400 dari service) dan
     * service tidak berbeda. JurnalService tidak boleh diubah di lane ini —
     * kesamaan teks dijaga regression test (`JurnalValidasiDashboardVsApiTest`).
     */
    public static function pesanBelumBalance(float $totalDebit, float $totalKredit): string
    {
        return 'Jurnal tidak balance: debit Rp '.number_format($totalDebit, 2)
            .' ≠ kredit Rp '.number_format($totalKredit, 2);
    }

    /**
     * [B-10g] Pesan jurnal balance tapi bernilai nol — identik dgn pesan
     * `JurnalService::post()` ("Jurnal harus memiliki nilai transaksi > 0").
     */
    public static function pesanNilaiTransaksiNol(): string
    {
        return 'Jurnal harus memiliki nilai transaksi > 0';
    }

    /**
     * Validasi entri jurnal manual satu-sisi (API ACC-04).
     *
     * Aturan per baris: akun ada → akun aktif → nominal > 0 → satu sisi →
     * sisi sesuai saldo_normal akun. Baris dengan debit & kredit sekaligus
     * (entri kontra-sisi) DITOLAK untuk jurnal manual.
     *
     * @param  array<int,array<string,mixed>>  $lines  [['akun_kode' => '110-01', 'debit' => 1000, 'kredit' => 0], ...]
     * @return array<int,string> daftar pesan galat (Indonesia), urut baris
     */
    public static function cekBaris(array $lines): array
    {
        $galat = [];
        foreach (static::cekBarisPerBaris($lines) as $perBaris) {
            foreach ($perBaris as $item) {
                $galat[] = $item['pesan'];
            }
        }

        return $galat;
    }

    /**
     * [B-10g] Sama persis dengan `cekBaris()`, tapi dikelompokkan per indeks
     * baris (0-based) beserta field form yang salah. Dipakai form jurnal
     * manual Livewire supaya pesan bisa ditempel di input akun / nominal yang
     * benar, sementara daftar pesan datar tetap sama dengan API.
     *
     * @param  array<int,mixed>  $lines  baris jurnal; tiap baris berisi array
     *                                   ['akun_kode' => '110-01', 'debit' => 1000, 'kredit' => 0]
     * @return array<int,array<int,array{field: string, pesan: string}>>
     *                                                                   indeks baris => daftar {field: 'akun_kode'|'jumlah'|'sisi', pesan: string}
     */
    public static function cekBarisPerBaris(array $lines): array
    {
        $hasil = [];

        foreach (array_values($lines) as $index => $baris) {
            $line = is_array($baris) ? $baris : [];
            $kode = trim((string) ($line['akun_kode'] ?? ''));
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $kredit = round((float) ($line['kredit'] ?? 0), 2);
            $galat = [];

            // Kode kosong sudah tertangkap aturan `required` pada request.
            if ($kode === '') {
                $hasil[$index] = $galat;

                continue;
            }

            $akun = static::cari($kode);
            if (! $akun) {
                $galat[] = ['field' => 'akun_kode', 'pesan' => static::pesanAkunTidakAda($kode)];
                $hasil[$index] = $galat;

                continue;
            }

            if (! $akun->is_active) {
                $galat[] = ['field' => 'akun_kode', 'pesan' => static::pesanAkunNonaktif($akun)];
                $hasil[$index] = $galat;

                continue;
            }

            if (max($debit, $kredit) <= 0) {
                $galat[] = ['field' => 'jumlah', 'pesan' => static::pesanNominalNol($akun)];
                $hasil[$index] = $galat;

                continue;
            }

            if (($debit > 0) === ($kredit > 0)) {
                $galat[] = ['field' => 'sisi', 'pesan' => static::pesanSatuSisi($akun)];
                $hasil[$index] = $galat;

                continue;
            }

            $sisi = $debit > 0 ? 'debit' : 'kredit';
            if ($akun->saldo_normal !== $sisi) {
                $galat[] = ['field' => 'akun_kode', 'pesan' => static::pesanSisiTidakSesuai($akun, $sisi)];
            }

            $hasil[$index] = $galat;
        }

        return $hasil;
    }
}
