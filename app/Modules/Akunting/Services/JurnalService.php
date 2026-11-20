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
    public const SUMBER_VALID = ['pos', 'servis', 'servis-bayar', 'pembelian', 'beli', 'komisi', 'bagi_hasil', 'opname', 'manual', 'kas', 'channel', 'pembayaran', 'payroll', 'payroll-bayar', 'asset', 'depresiasi', 'disposal', 'import', 'grn', 'stok_awal', 'tutup_buku', 'reversal', 'retur', 'transfer'];

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

        // [F-12] Double-entry wajib minimal 2 baris meaningful: jurnal 1 baris
        // D=K>0 (net nol) menampilkan mutasi gross palsu di buku besar.
        if (count($lines) < 2) {
            throw new \Exception('Jurnal membutuhkan minimal 2 baris entri (double-entry).');
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

            // [F-11] Nominal negatif = mutasi terselubung ke sisi lawan;
            // post() adalah trust boundary SEMUA modul internal.
            if ($debit < 0 || $kredit < 0) {
                throw new \Exception(ValidasiBarisJurnal::pesanNominalNegatif($akun));
            }

            // [F-12] Baris 0-0 tidak meaningful — dibuang saat normalisasi
            // (alur otomatis sah, mis. payroll, mengirim baris potongan/komisi
            // bernilai nol). Buku besar hanya menyimpan baris bermakna; jumlah
            // baris meaningful dicek setelah loop (minimal 2).
            if ($debit == 0.0 && $kredit == 0.0) {
                continue;
            }

            $totalDebit += $debit;
            $totalKredit += $kredit;

            $barisDasar = [
                'no_jurnal' => $noJurnal,
                'tanggal' => $tanggal ?? now(),
                'cabang_id' => $cabangId,
                // [F-18] Kunci isolasi header↔lines per cabang (cabang_id ?? 0,
                // selaras dengan jurnal_header.cabang_key).
                'cabang_key' => (int) ($cabangId ?? 0),
                'akun_coa_id' => $akun->id,
                'sumber' => $sumber,
                'deskripsi' => $deskripsi ?? "Jurnal {$sumber} {$noJurnal}",
                'referensi_tipe' => $referensiTipe,
                'referensi_id' => $referensiId,
                'user_id' => $userId,
            ];

            // [F-12] Baris dua-sisi (D>0 & K>0) dipecah menjadi dua baris
            // satu-sisi saat normalisasi — total tetap, buku besar hanya
            // berisi baris satu-sisi (konvensi double-entry). Alur otomatis
            // yang mengirim baris dua-sisi tetap sah (behavior-preserving).
            if ($debit > 0 && $kredit > 0) {
                $normalized[] = [...$barisDasar, 'debit' => $debit, 'kredit' => 0.0];
                $normalized[] = [...$barisDasar, 'debit' => 0.0, 'kredit' => $kredit];
            } else {
                $normalized[] = [...$barisDasar, 'debit' => $debit, 'kredit' => $kredit];
            }
        }

        // [F-12] Setelah normalisasi (baris 0-0 dibuang, dua-sisi dipecah)
        // wajib tersisa minimal 2 baris meaningful — jurnal net-nol terselubung
        // (mis. 2 baris input tetapi salah satunya 0-0) tetap ditolak.
        if (count($normalized) < 2) {
            throw new \Exception('Jurnal membutuhkan minimal 2 baris entri (double-entry).');
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
        // [F-16] Scope guard per cabang — selaras dengan unique index
        // (cabang_key, no_jurnal): nomor sama di cabang berbeda adalah sah,
        // bukan duplikat bisnis (sebelumnya false-positive lintas cabang).
        $sudahAda = JurnalAkuntansi::where('no_jurnal', $noJurnal)
            ->where('cabang_id', $cabangId)
            ->exists();

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
     * [F-17] Deteksi pelanggaran unique index lintas driver. SQLSTATE 23000
     * mencakup SEMUA integrity constraint violation (unique, FK, NOT NULL) —
     * hanya unique yang boleh diterjemahkan ke pesan idempotency; FK/NOT NULL
     * harus melempar exception asli agar penyebab tidak tersembunyi.
     * MySQL: error 1062 (duplicate entry). SQLite: "UNIQUE constraint failed".
     * PostgreSQL: "duplicate key value violates unique constraint".
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return true;
        }

        $pesan = strtolower($e->getMessage());

        return str_contains($pesan, 'unique constraint failed')
            || str_contains($pesan, 'duplicate entry')
            || str_contains($pesan, 'duplicate key');
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
     * [F-13] Tanpa bypass: sumber 'tutup_buku' TIDAK membebaskan cek periode —
     * jalur sah TutupBukuService memposting penutup SEBELUM periode di-set
     * closed, dan reopen men-set open SEBELUM reversal, sehingga keduanya tetap
     * lolos. Jurnal tanpa cabang (null/<=0) dikenai cek periode GLOBAL: bila
     * ada periode closed untuk bulan tsb di cabang mana pun, posting ditolak
     * (fail-closed — kontrol tutup buku berlaku universal). Parameter $sumber
     * dipertahankan untuk kompatibilitas pemanggil, tidak lagi membebaskan cek.
     *
     * @throws \Exception jika periode akuntansi tanggal tsb berstatus 'closed'
     */
    public function cekPeriodeTerbuka($tanggal, ?int $cabangId, string $sumber = ''): void
    {
        if (! Schema::hasTable('periode_akuntansi')) {
            return;
        }

        $parsed = $tanggal instanceof \DateTimeInterface
            ? Carbon::instance($tanggal)
            : Carbon::parse($tanggal ?? now());

        $tahun = (int) $parsed->format('Y');
        $bulan = (int) $parsed->format('n');

        $query = PeriodeAkuntansi::where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->where('status', 'closed');
        if ($cabangId !== null && $cabangId > 0) {
            $query->where('cabang_id', $cabangId);
        }
        $tertutup = $query->exists();

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
     * [F-03] Baca + cek header terjadi DI DALAM transaksi dengan lockForUpdate
     * pada header asal (dua reversal concurrent ter-serialisasi), diperkuat
     * unique index (cabang_key, reversal_of_no_jurnal) sebagai jaring DB.
     * Jurnal tanpa header (legacy) ditolak — tidak ada tempat menandai
     * is_reversed sehingga reversal berulang tidak bisa dicegah.
     *
     * [F-04] Jurnal penutup (sumber tutup_buku) TIDAK boleh dibalik lewat sini;
     * pembalikan penutup hanya lewat reopen periode resmi (reversalJurnalPenutup).
     *
     * [F-05] $tanggal opsional: default now() (storno berjalan); reopen periode
     * memakai tanggal akhir bulan yang dibuka via reversalJurnalPenutup().
     *
     * @throws \Exception jika jurnal tidak ditemukan, sudah di-reversed, atau periode ditutup
     */
    public function reversalJurnal(string $noJurnalAsal, int $cabangId, ?int $userId, string $alasan, ?\DateTimeInterface $tanggal = null): array
    {
        return $this->prosesReversal($noJurnalAsal, $cabangId, $userId, $alasan, $tanggal, false);
    }

    /**
     * [F-04 / F-05] Reversal KHUSUS jurnal penutup — satu-satunya pintu legal
     * membalik ayat tutup buku, hanya dipanggil TutupBukuService::bukaBukuKembali
     * setelah periode di-set open, dengan tanggal = akhir bulan yang direopen
     * (bukan now()) agar laporan periode yang dibuka benar-benar pulih.
     */
    public function reversalJurnalPenutup(string $noJurnalAsal, int $cabangId, ?int $userId, string $alasan, \DateTimeInterface $tanggal): array
    {
        return $this->prosesReversal($noJurnalAsal, $cabangId, $userId, $alasan, $tanggal, true);
    }

    private function prosesReversal(string $noJurnalAsal, int $cabangId, ?int $userId, string $alasan, ?\DateTimeInterface $tanggal, bool $dariReopenPeriode): array
    {
        $alasan = trim($alasan);
        if ($alasan === '') {
            throw new \InvalidArgumentException('Alasan pembalikan jurnal (reversal) wajib diisi untuk kepatuhan audit ISO.');
        }

        $tanggalReversal = $tanggal ?? now();
        $this->cekPeriodeTerbuka($tanggalReversal, $cabangId, 'reversal');

        $linesAsal = JurnalAkuntansi::where('no_jurnal', $noJurnalAsal)
            ->where('cabang_id', $cabangId)
            ->with('akun')
            ->get();

        if ($linesAsal->isEmpty()) {
            throw new \Exception("Jurnal dengan nomor '{$noJurnalAsal}' tidak ditemukan pada cabang ini.");
        }

        if (! $this->headerTableReady()) {
            throw new \Exception("Jurnal {$noJurnalAsal} tidak dapat dibalik: tabel header jurnal belum tersedia. Jalankan migrasi jurnal_header terlebih dahulu.");
        }

        $reversalLines = [];
        foreach ($linesAsal as $line) {
            $reversalLines[] = [
                'akun_kode' => $line->akun?->kode,
                'debit' => (float) $line->kredit, // Tukar D <-> K
                'kredit' => (float) $line->debit,
            ];
        }

        return DB::transaction(function () use ($noJurnalAsal, $linesAsal, $reversalLines, $cabangId, $userId, $alasan, $tanggalReversal, $dariReopenPeriode) {
            // [F-03] Lock header asal DI DALAM transaksi: request reversal
            // concurrent menunggu di sini, lalu membaca is_reversed terbaru
            // (cek di luar transaksi sebelumnya bisa basi → double reversal).
            $header = JurnalHeader::where('no_jurnal', $noJurnalAsal)
                ->where('cabang_key', $cabangId)
                ->lockForUpdate()
                ->first();

            if (! $header) {
                throw new \Exception("Jurnal {$noJurnalAsal} tidak memiliki header jurnal (data legacy) sehingga tidak dapat dibalik. Lakukan backfill header terlebih dahulu.");
            }

            if ($header->is_reversed) {
                throw new \Exception("Jurnal {$noJurnalAsal} sudah pernah dibalik sebelumnya oleh jurnal {$header->reversed_by_no_jurnal}.");
            }

            if ($header->sumber === 'reversal') {
                throw new \Exception("Jurnal dengan sumber 'reversal' tidak dapat dibalik ulang.");
            }

            // [F-04] Penutup hanya boleh dibalik lewat reopen periode resmi.
            if ($header->sumber === 'tutup_buku' && ! $dariReopenPeriode) {
                throw new \Exception('Jurnal penutup (tutup buku) tidak dapat dibalik melalui reversal biasa. Gunakan fitur Buka Kembali Periode (reopen) yang terotorisasi.');
            }

            $noReversal = $this->generateNoJurnal('reversal', $cabangId);

            $this->post(
                noJurnal: $noReversal,
                tanggal: $tanggalReversal,
                sumber: 'reversal',
                lines: $reversalLines,
                deskripsi: "Pembalik (Reversal) Jurnal {$noJurnalAsal}: {$alasan}",
                cabangId: $cabangId,
                userId: $userId,
                referensiTipe: 'jurnal_header',
                referensiId: $header->id
            );

            // Perbarui metadata reversal pada header asal
            $header->update([
                'is_reversed' => true,
                'reversed_by_no_jurnal' => $noReversal,
                'reversed_at' => now(),
                'reversed_by_user_id' => $userId,
            ]);

            // Tautkan referensi pada header jurnal reversal baru.
            // [F-03] Unique (cabang_key, reversal_of_no_jurnal) = jaring DB:
            // bila state aplikasi somehow basi, reversal kedua tetap ditolak
            // dan seluruh transaksi ini rollback (tidak ada pembalik ganda).
            try {
                JurnalHeader::where('no_jurnal', $noReversal)
                    ->where('cabang_key', $cabangId)
                    ->update([
                        'reversal_of_no_jurnal' => $noJurnalAsal,
                    ]);
            } catch (QueryException $e) {
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }

                throw new \Exception("Jurnal {$noJurnalAsal} sudah pernah dibalik sebelumnya (guard unique reversal).", 0, $e);
            }

            activity()
                ->performedOn($header)
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
        }, 3);
    }
}
