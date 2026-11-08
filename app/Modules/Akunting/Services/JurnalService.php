<?php

namespace App\Modules\Akunting\Services;

use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\JurnalHeader;
use App\Modules\Akunting\Models\PeriodeAkuntansi;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Double-entry journal service (PRD §4.6).
 * Setiap posting divalidasi: total debit === total kredit.
 * Idempoten per no_jurnal: posting ulang no_jurnal yang sama ditolak.
 *
 * [B-10a / P0-3] Idempotensi dipindah dari "exists() di luar transaction" ke
 * unique index `jurnal_header (cabang_key, no_jurnal)`:
 * - validasi + insert header + insert lines terjadi dalam SATU DB::transaction;
 * - bentrok no_jurnal ditegakkan MESIN DB (bukan checking aplikasi yang race);
 * - counter `generateNoJurnal()` mengunci range header via `lockForUpdate()`.
 *
 * Fallback: ketika `jurnal_header` belum ada (owner belum `php artisan migrate`),
 * guard lama tetap dipakai supaya modul lain tidak ikut mati.
 *
 * [B-10d / P1-3] Setelah commit, satu activity "Jurnal {no} diposting" dicatat
 * pada model JurnalHeader (1 event per post, bukan per baris) — metadata entri
 * logis saja, isi baris jurnal TIDAK ikut dilog.
 */
class JurnalService
{
    public const SUMBER_VALID = ['pos', 'servis', 'servis-bayar', 'pembelian', 'beli', 'komisi', 'opname', 'manual', 'kas', 'channel', 'pembayaran', 'payroll', 'payroll-bayar', 'asset', 'depresiasi', 'disposal', 'import', 'grn', 'stok_awal', 'tutup_buku', 'reversal', 'retur', 'transfer'];

    /**
     * Cache status keberadaan tabel per request (server 1GB RAM: hindari
     * query schema berulang di loop seeder).
     */
    private static ?bool $headerTableReady = null;

    /**
     * Reset cache tabel (dipakai test).
     */
    public static function flushTableCache(): void
    {
        self::$headerTableReady = null;
    }

    private function headerTableReady(): bool
    {
        return self::$headerTableReady ??= Schema::hasTable('jurnal_header');
    }

    /**
     * Post entri jurnal double-entry.
     *
     * @param  string  $noJurnal  unique journal number
     * @param  \DateTimeInterface|null  $tanggal
     * @param  string  $sumber  pos|servis|komisi|opname|pembelian|manual
     * @param  array  $lines  [['akun_kode' => '110-01', 'debit' => 1000, 'kredit' => 0], ...]
     *                        minimal satu line debit dan satu line kredit
     * @param  string|null  $idempotencyKey  [B-10a] kunci anti-pembayaran-ganda;
     *                                       di-scope per cabang lewat unique index.
     *
     * @throws \Exception jika balance tidak seimbang atau no_jurnal duplikat
     */
    public function post(
        string $noJurnal,
        $tanggal,
        string $sumber,
        array $lines,
        ?string $deskripsi = null,
        ?int $cabangId = null,
        ?int $userId = null,
        ?string $referensiTipe = null,
        ?int $referensiId = null,
        ?string $idempotencyKey = null,
        string $statusApproval = 'disetujui',
        ?string $lampiranPath = null,
        ?string $lampiranNama = null
    ): array {
        if (! in_array($sumber, self::SUMBER_VALID, true)) {
            throw new \InvalidArgumentException("Sumber jurnal '{$sumber}' tidak valid");
        }

        $this->cekPeriodeTerbuka($tanggal, $cabangId, $sumber);

        if (empty($lines)) {
            throw new \Exception('Jurnal membutuhkan minimal 1 baris entri');
        }

        $totalDebit = 0.0;
        $totalKredit = 0.0;

        $normalized = [];
        foreach ($lines as $line) {
            $kode = (string) ($line['akun_kode'] ?? '');

            // [B-10e / P2-1] Akun WAJIB ada DAN aktif untuk semua sumber jurnal
            // (pos/servis/komisi/opname/pembelian/manual) — sebelumnya hanya
            // dicari kode sehingga jurnal bisa masuk ke akun nonaktif.
            // Sumber aturan: ValidasiBarisJurnal (dipakai juga API ACC-04).
            $akun = ValidasiBarisJurnal::cariAktif($kode);
            if (! $akun) {
                $nonaktif = ValidasiBarisJurnal::cari($kode);

                throw new \Exception($nonaktif
                    ? ValidasiBarisJurnal::pesanAkunNonaktif($nonaktif)
                    : ValidasiBarisJurnal::pesanAkunTidakAda($kode));
            }

            $debit = round((float) ($line['debit'] ?? 0), 2);
            $kredit = round((float) ($line['kredit'] ?? 0), 2);
            $totalDebit += $debit;
            $totalKredit += $kredit;

            $normalized[] = [
                'no_jurnal' => $noJurnal,
                'tanggal' => $tanggal ?? now(),
                'cabang_id' => $cabangId,
                'akun_coa_id' => $akun->id,
                'sumber' => $sumber,
                'deskripsi' => $deskripsi ?? "Jurnal {$sumber} {$noJurnal}",
                'debit' => $debit,
                'kredit' => $kredit,
                'referensi_tipe' => $referensiTipe,
                'referensi_id' => $referensiId,
                'user_id' => $userId,
            ];
        }

        // Double-entry balance check
        if (round($totalDebit, 2) !== round($totalKredit, 2)) {
            throw new \Exception(
                'Jurnal tidak balance: debit Rp '.number_format($totalDebit, 2).
                ' ≠ kredit Rp '.number_format($totalKredit, 2)
            );
        }

        if ($totalDebit <= 0) {
            throw new \Exception('Jurnal harus memiliki nilai transaksi > 0');
        }

        $totalDebit = round($totalDebit, 2);
        $totalKredit = round($totalKredit, 2);

        // [P0-3] Satu transaction: klaim header (idempotensi) → insert lines.
        $created = DB::transaction(function () use (
            $normalized, $noJurnal, $tanggal, $sumber, $deskripsi, $cabangId, $userId,
            $referensiTipe, $referensiId, $idempotencyKey, $totalDebit, $totalKredit,
            $statusApproval, $lampiranPath, $lampiranNama
        ) {
            $this->klaimHeader(
                noJurnal: $noJurnal,
                tanggal: $tanggal,
                sumber: $sumber,
                deskripsi: $deskripsi,
                cabangId: $cabangId,
                userId: $userId,
                referensiTipe: $referensiTipe,
                referensiId: $referensiId,
                idempotencyKey: $idempotencyKey,
                totalDebit: $totalDebit,
                totalKredit: $totalKredit,
                jumlahBaris: count($normalized),
                statusApproval: $statusApproval,
                lampiranPath: $lampiranPath,
                lampiranNama: $lampiranNama
            );

            $created = [];
            foreach ($normalized as $row) {
                $created[] = JurnalAkuntansi::create($row);
            }

            return $created;
        }, 3);

        // [B-10d / P1-3] Event "satu entri jurnal logis berhasil diposting".
        // DI LUAR transaction supaya activity log hanya tercatat kalau jurnal
        // benar-benar COMMIT (1 baris per post, BUKAN 1 baris per line jurnal).
        $this->catatJurnalDiposting(
            noJurnal: $noJurnal,
            tanggal: $tanggal,
            cabangId: $cabangId,
            sumber: $sumber,
            jumlahBaris: count($normalized),
            totalDebit: $totalDebit,
            totalKredit: $totalKredit,
            referensiTipe: $referensiTipe,
            referensiId: $referensiId,
        );

        return $created;
    }

    /**
     * [B-10d / P1-3] Catat activity "jurnal diposting" pada model JurnalHeader.
     *
     * Dipanggil SETELAH `DB::transaction` commit. Sengaja TIDAK log isi baris
     * jurnal (akun/debit/kredit/deskripsi baris bisa memuat data sensitif &
     * boros — JurnalAkuntansi yang individual sudah ter-log terpisah).
     * Properties hanya metadata entri logis: nomor, tanggal, cabang, jumlah
     * baris, total debit/kredit, dan referensi asal.
     */
    private function catatJurnalDiposting(
        string $noJurnal,
        $tanggal,
        ?int $cabangId,
        string $sumber,
        int $jumlahBaris,
        float $totalDebit,
        float $totalKredit,
        ?string $referensiTipe,
        ?int $referensiId,
    ): void {
        // Tanpa tabel header (owner belum migrate) tidak ada subject untuk
        // event ini; jurnal tetap tercatat penuh di jurnal_akuntansi.
        if (! $this->headerTableReady()) {
            return;
        }

        $header = JurnalHeader::query()
            ->where('no_jurnal', $noJurnal)
            ->where('cabang_key', (int) ($cabangId ?? 0))
            ->first();

        if (! $header) {
            return;
        }

        activity()
            ->performedOn($header)
            // Event 'created' agar label UI riwayat (AktivitasLog::aksi_label)
            // tetap "Dibuat" & bisa difilter dropdown aksi.
            ->event('created')
            ->withProperties([
                'no_jurnal' => $noJurnal,
                'tanggal' => ($tanggal instanceof \DateTimeInterface ? $tanggal->format('Y-m-d H:i:s') : (string) $tanggal),
                'cabang_id' => $cabangId,
                'sumber' => $sumber,
                'jumlah_baris' => $jumlahBaris,
                'total_debit' => $totalDebit,
                'total_kredit' => $totalKredit,
                'referensi_tipe' => $referensiTipe,
                'referensi_id' => $referensiId,
            ])
            ->log(sprintf(
                'Jurnal %s diposting — %d baris, total debit Rp %s',
                $noJurnal,
                $jumlahBaris,
                number_format($totalDebit, 0, ',', '.')
            ));
    }

    /**
     * [P0-3] Klaim nomor jurnal pada tabel header (idempotensi atomic).
     *
     * Melempar \Exception (Indonesia) bila no_jurnal atau idempotencyKey sudah
     * dipakai cabang ini. Bentrokan unique index dari DB Translated ke pesan
     * yang sama dengan guard lama agar call site & test tidak berubah kontrak.
     */
    private function klaimHeader(
        string $noJurnal,
        $tanggal,
        string $sumber,
        ?string $deskripsi,
        ?int $cabangId,
        ?int $userId,
        ?string $referensiTipe,
        ?int $referensiId,
        ?string $idempotencyKey,
        float $totalDebit,
        float $totalKredit,
        int $jumlahBaris,
        string $statusApproval = 'disetujui',
        ?string $lampiranPath = null,
        ?string $lampiranNama = null
    ): void {
        $sudahAda = JurnalAkuntansi::where('no_jurnal', $noJurnal)->exists();

        if (! $this->headerTableReady()) {
            // Migrasi belum dijalankan: guard legacy (non-atomic, tapi tidak mematikan).
            if ($sudahAda) {
                throw new \Exception("Jurnal {$noJurnal} sudah pernah diposting (idempotency guard)");
            }

            return;
        }

        if ($sudahAda) {
            throw new \Exception("Jurnal {$noJurnal} sudah pernah diposting (idempotency guard)");
        }

        try {
            JurnalHeader::create([
                'no_jurnal' => $noJurnal,
                'cabang_id' => $cabangId,
                'cabang_key' => (int) ($cabangId ?? 0),
                'tanggal' => $tanggal ?? now(),
                'sumber' => $sumber,
                'deskripsi' => $deskripsi ?? "Jurnal {$sumber} {$noJurnal}",
                'total_debit' => $totalDebit,
                'total_kredit' => $totalKredit,
                'jumlah_baris' => $jumlahBaris,
                'referensi_tipe' => $referensiTipe,
                'referensi_id' => $referensiId,
                'idempotency_key' => $idempotencyKey,
                'user_id' => $userId,
                'status_approval' => $statusApproval,
                'lampiran_path' => $lampiranPath,
                'lampiran_nama' => $lampiranNama,
            ]);
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            $pesan = $idempotencyKey !== null && str_contains(strtolower($e->getMessage()), 'idempotency')
                ? 'Pembayaran dengan kunci ini sudah pernah dicatat (idempotency guard).'
                : "Jurnal {$noJurnal} sudah pernah diposting (idempotency guard)";

            throw new \Exception($pesan, 0, $e);
        }
    }

    /**
     * Deteksi pelanggaran unique index lintas driver (MySQL 1062 / SQLite 19).
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        if ((string) $e->getCode() !== '23000') {
            return str_contains(strtolower($e->getMessage()), 'unique');
        }

        return true;
    }

    /**
     * Generate nomor jurnal unik per hari.
     *
     * [P0-3] Counter dibaca di dalam transaction dengan `lockForUpdate()` pada
     * range header cabang tsb, lalu next = max(suffix) + 1. Nomor jurnal lama
     * tidak pernah diubah — hanya dijamin tidak bentrok.
     */
    public function generateNoJurnal(string $sumber, ?int $cabangId = null): string
    {
        $prefix = strtoupper(substr($sumber, 0, 3));
        $date = now()->format('Ymd');
        // LIKE harus menyertakan cabang agar counter per cabang tidak berbenturan
        $like = sprintf('JRL-%s-%s-%s-%%', $prefix, $cabangId ?? 'X', $date);

        if (! $this->headerTableReady()) {
            $count = JurnalAkuntansi::where('no_jurnal', 'like', $like)->count() + 1;

            return sprintf('JRL-%s-%s-%s-%04d', $prefix, $cabangId ?? 'X', $date, $count);
        }

        return DB::transaction(function () use ($prefix, $cabangId, $date, $like) {
            $terpakai = JurnalHeader::where('cabang_key', (int) ($cabangId ?? 0))
                ->where('no_jurnal', 'like', $like)
                ->lockForUpdate()
                ->pluck('no_jurnal')
                ->map(fn ($no) => (int) substr((string) $no, -4))
                ->all();

            $next = ($terpakai ? max($terpakai) : 0) + 1;

            return sprintf('JRL-%s-%s-%s-%04d', $prefix, $cabangId ?? 'X', $date, $next);
        }, 3);
    }

    /**
     * [ISO/Audit] Guard periode buku: tolak posting bila periode sudah ditutup.
     *
     * @throws \Exception jika periode akuntansi tanggal tsb berstatus 'closed'
     */
    public function cekPeriodeTerbuka($tanggal, ?int $cabangId, string $sumber = ''): void
    {
        if ($sumber === 'tutup_buku' || $cabangId === null || $cabangId <= 0) {
            return;
        }

        if (! Schema::hasTable('periode_akuntansi')) {
            return;
        }

        $parsed = $tanggal instanceof \DateTimeInterface
            ? Carbon::instance($tanggal)
            : Carbon::parse($tanggal ?? now());

        $tahun = (int) $parsed->format('Y');
        $bulan = (int) $parsed->format('n');

        $tertutup = PeriodeAkuntansi::where('cabang_id', $cabangId)
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->where('status', 'closed')
            ->exists();

        if ($tertutup) {
            $namaBulan = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
            ][$bulan] ?? (string) $bulan;

            throw new \Exception("Periode akuntansi {$namaBulan} {$tahun} sudah ditutup (tutup buku). Transaksi tidak dapat diposting.");
        }
    }

    /**
     * [ISO/Audit] Jurnal Pembalik (Storno / Reversal 1-Klik).
     *
     * Membalik entri jurnal yang salah dengan menukar posisi Debit dan Kredit,
     * menandai jurnal asal sebagai `is_reversed = true`, menautkan nomor referensi,
     * dan mencatat activity log audit.
     *
     * @throws \Exception jika jurnal tidak ditemukan, sudah di-reversed, atau periode ditutup
     */
    public function reversalJurnal(string $noJurnalAsal, int $cabangId, int $userId, string $alasan): array
    {
        $alasan = trim($alasan);
        if ($alasan === '') {
            throw new \InvalidArgumentException('Alasan pembalikan jurnal (reversal) wajib diisi untuk kepatuhan audit ISO.');
        }

        $this->cekPeriodeTerbuka(now(), $cabangId, 'reversal');

        $linesAsal = JurnalAkuntansi::where('no_jurnal', $noJurnalAsal)
            ->where('cabang_id', $cabangId)
            ->with('akun')
            ->get();

        if ($linesAsal->isEmpty()) {
            throw new \Exception("Jurnal dengan nomor '{$noJurnalAsal}' tidak ditemukan pada cabang ini.");
        }

        $header = JurnalHeader::where('no_jurnal', $noJurnalAsal)
            ->where('cabang_key', $cabangId)
            ->first();

        if ($header?->is_reversed) {
            throw new \Exception("Jurnal {$noJurnalAsal} sudah pernah dibalik sebelumnya oleh jurnal {$header->reversed_by_no_jurnal}.");
        }

        if ($header?->sumber === 'reversal') {
            throw new \Exception("Jurnal dengan sumber 'reversal' tidak dapat dibalik ulang.");
        }

        $reversalLines = [];
        foreach ($linesAsal as $line) {
            $reversalLines[] = [
                'akun_kode' => $line->akun?->kode,
                'debit' => (float) $line->kredit, // Tukar D <-> K
                'kredit' => (float) $line->debit,
            ];
        }

        $noReversal = $this->generateNoJurnal('reversal', $cabangId);

        return DB::transaction(function () use ($noReversal, $noJurnalAsal, $header, $linesAsal, $reversalLines, $cabangId, $userId, $alasan) {
            $this->post(
                noJurnal: $noReversal,
                tanggal: now(),
                sumber: 'reversal',
                lines: $reversalLines,
                deskripsi: "Pembalik (Reversal) Jurnal {$noJurnalAsal}: {$alasan}",
                cabangId: $cabangId,
                userId: $userId,
                referensiTipe: 'jurnal_header',
                referensiId: $header?->id
            );

            // Perbarui metadata reversal pada header asal
            if ($header) {
                $header->update([
                    'is_reversed' => true,
                    'reversed_by_no_jurnal' => $noReversal,
                    'reversed_at' => now(),
                    'reversed_by_user_id' => $userId,
                ]);
            }

            // Tautkan referensi pada header jurnal reversal baru
            JurnalHeader::where('no_jurnal', $noReversal)
                ->where('cabang_key', $cabangId)
                ->update([
                    'reversal_of_no_jurnal' => $noJurnalAsal,
                ]);

            activity()
                ->performedOn($header ?: $linesAsal->first())
                ->event('reversed')
                ->withProperties([
                    'no_jurnal_asal' => $noJurnalAsal,
                    'no_jurnal_reversal' => $noReversal,
                    'cabang_id' => $cabangId,
                    'user_id' => $userId,
                    'alasan' => $alasan,
                ])
                ->log("Jurnal {$noJurnalAsal} dibalik (reversal) oleh jurnal {$noReversal}. Alasan: {$alasan}");

            return [
                'no_jurnal_reversal' => $noReversal,
                'jurnal_asal' => $noJurnalAsal,
                'total_debit' => $linesAsal->sum('kredit'),
                'total_kredit' => $linesAsal->sum('debit'),
            ];
        });
    }
}
