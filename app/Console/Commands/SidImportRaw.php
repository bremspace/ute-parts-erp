<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [T-42 fase 1] Import staging RAW data POS SID Retail (dump latest.sql) ke
 * tabel `sid_retail_raw_*` di db_staging.
 *
 * DESIGN:
 * - Parsing TEKS baris-per-baris (fopen + fgets + buffer statement), TIDAK
 *   mengeksekusi file SQL langsung — format dump SID tidak kompatibel dengan
 *   MySQL standard (boolean varchar(5) 'True'/'False', tanggal kosong
 *   1899-12-30, jam string 12-jam, tanpa timestamps/FK).
 * - Idempoten: baris dengan `kode_sumber` yang sudah ada di DB di-skip
 *   (guard DB unique + whereIn per chunk). Jalankan 2x → jumlah tidak dobel.
 * - RAM aman (server 1GB): tidak ada str_getcsv seluruh file; buffer stream
 *   kecil + insert per chunk (default 500 baris/statement).
 * - Dry-run tersedia: `--dry-run` menghitung & menampilkan rencana tanpa
 *   menulis (mandat PRD §4.11 dry-run wajib sebelum commit).
 *
 * Scope fase 1: hanya tabel raw staging. Mapping ke Produk/Pelanggan/Supplier/
 * Transaksi existing dilakukan fase 2 (JANGAN sentuh tabel Ute Parts).
 */
class SidImportRaw extends Command
{
    protected $signature = 'sid:import-raw
        {--file= : Path dump SQL SID Retail (default /root/workspace/ute-pos-main/latest.sql)}
        {--tables= : Daftar tabel target dipisah koma (default: 8 tabel inti)}
        {--all : Import SEMUA tabel dalam dump yang punya data INSERT}
        {--dry-run : Hitung & tampilkan rencana tanpa menulis ke DB}
        {--chunk=500 : Ukuran batch INSERT per statement}';

    protected $description = 'Import staging RAW tabel SID Retail ke sid_retail_raw_* (idempotent, T-42 fase 1)';

    /**
     * Konfigurasi tabel inti: sumber dump -> tabel raw staging.
     *
     * @var array<string, array{source: string[], kunci: string[]|null, tabelSumber: bool}>
     */
    protected array $coreTables = [
        'barang' => ['source' => ['barang'], 'kunci' => ['kode'], 'tabelSumber' => false],
        'pelanggan' => ['source' => ['pelanggan'], 'kunci' => ['kode'], 'tabelSumber' => false],
        'supplier' => ['source' => ['supplier'], 'kunci' => ['kode'], 'tabelSumber' => false],
        'member' => ['source' => ['member'], 'kunci' => ['id_kartu'], 'tabelSumber' => false],
        'servis' => ['source' => ['servis'], 'kunci' => ['kode'], 'tabelSumber' => false],
        'setup_perusahaan' => ['source' => ['setup_perusahaan'], 'kunci' => null, 'tabelSumber' => false], // 1 baris
        'itempenjualan' => ['source' => ['itempenjualan'], 'kunci' => ['kode', 'nourut'], 'tabelSumber' => false],
        // arus_stok asli kosong; data ada di partisi bulanan arus_stok_2_2026..8_2026
        'arus_stok' => ['source' => ['arus_stok', 'arus_stok_2_2026', 'arus_stok_3_2026', 'arus_stok_4_2026', 'arus_stok_5_2026', 'arus_stok_6_2026', 'arus_stok_7_2026', 'arus_stok_8_2026'], 'kunci' => ['kode'], 'tabelSumber' => true],
        // [M-09] tabel header — prasyarat fase 2B/3 (skema raw: 2026_09_22_000200)
        'penjualan' => ['source' => ['penjualan'], 'kunci' => ['kode'], 'tabelSumber' => false],
        'pembelian' => ['source' => ['pembelian'], 'kunci' => ['kode'], 'tabelSumber' => false],
        'itempembelian' => ['source' => ['itempembelian'], 'kunci' => ['kode', 'nourut'], 'tabelSumber' => false],
        'piutang' => ['source' => ['piutang'], 'kunci' => ['kode'], 'tabelSumber' => false],
        'hutang' => ['source' => ['hutang'], 'kunci' => ['kode'], 'tabelSumber' => false],
        'kas' => ['source' => ['kas'], 'kunci' => ['kode'], 'tabelSumber' => false],
        'kas_awal' => ['source' => ['kas_awal'], 'kunci' => ['kode_kas'], 'tabelSumber' => false],
        'header_return_penjualan' => ['source' => ['header_return_penjualan'], 'kunci' => ['kode'], 'tabelSumber' => false],
        'header_return_pembelian' => ['source' => ['header_return_pembelian'], 'kunci' => ['kode'], 'tabelSumber' => false],
        'nomor_seri' => ['source' => ['nomor_seri'], 'kunci' => ['no_faktur'], 'tabelSumber' => false],
        // hrgpergroup: kodebarang TIDAK unik (1 barang di ~4 kdgrouphrg) — komposit wajib agar 15.856 baris harga tier utuh.
        'hrgpergroup' => ['source' => ['hrgpergroup'], 'kunci' => ['kodebarang', 'kdgrouphrg'], 'tabelSumber' => false],
        'grouphrgpelanggan' => ['source' => ['grouphrgpelanggan'], 'kunci' => ['kode'], 'tabelSumber' => false],
        // expired_barang: kode_barang TIDAK unik (batch qty/tgl beda) — komposit kode_barang+qty+tgl_expired+tgl_input; duplikat identik di-skip idempoten.
        'expired_barang' => ['source' => ['expired_barang'], 'kunci' => ['kode_barang', 'qty', 'tgl_expired', 'tgl_input'], 'tabelSumber' => false],
        'komplain' => ['source' => ['komplain'], 'kunci' => ['kode'], 'tabelSumber' => false],
        'koreksi' => ['source' => ['koreksi'], 'kunci' => ['kode'], 'tabelSumber' => false],
        'itemkoreksi' => ['source' => ['itemkoreksi'], 'kunci' => ['kode'], 'tabelSumber' => false],
        // itemservis: kode = kode tiket servis (banyak item/tiket) — komposit kode+kode_barang lossless.
        'itemservis' => ['source' => ['itemservis'], 'kunci' => ['kode', 'kode_barang'], 'tabelSumber' => false],
    ];

    /** @var array<string, string[]> kolom per tabel sumber (dari CREATE TABLE di dump) */
    private array $columns = [];

    /** @var array<string, array<string, string>> tipe kolom per tabel sumber (utk normalisasi numerik) */
    private array $columnTypes = [];

    /** @var array<string, array<int, array<string, mixed>>> pending baris per tabel target */
    private array $pending = [];

    /** @var array<string, array{insert: int, skip: int}> */
    private array $stats = [];

    private bool $dryRun = false;

    private int $chunk = 500;

    public function handle(): int
    {
        $file = $this->option('file') ?: '/root/workspace/ute-pos-main/latest.sql';
        if (! is_file($file) || ! is_readable($file)) {
            $this->error(sprintf('File dump tidak ditemukan/tidak terbaca: %s', $file));

            return self::FAILURE;
        }

        $this->dryRun = (bool) $this->option('dry-run');
        $this->chunk = max(1, (int) $this->option('chunk'));

        $targets = $this->resolveTargets($this->option('all'), $this->option('tables'));
        if ($targets === []) {
            $this->error('Tidak ada tabel target. Gunakan --tables=... atau --all.');

            return self::FAILURE;
        }

        $this->info(sprintf('File : %s (%.1f MB)', $file, filesize($file) / 1048576));
        $this->info(sprintf('Mode : %s', $this->dryRun ? 'DRY-RUN (tanpa tulis DB)' : 'IMPORT'));
        $this->line(sprintf('Target: %s', implode(', ', array_keys($targets))));

        $start = microtime(true);

        $t0 = time();
        $this->scanTables($file, $targets);
        $this->info(sprintf('Pass 1 skema (CREATE TABLE): %d tabel sumber, %.1fs', count($this->columns), time() - $t0));

        $t0 = time();
        $this->streamStatements($file, $targets);
        $this->info(sprintf('Pass 2 streaming INSERT: %.1fs', time() - $t0));

        foreach (array_keys($targets) as $tabelTarget) {
            $this->flushPending($tabelTarget);
        }

        $this->printReport($targets);

        if (! $this->dryRun) {
            $this->line(sprintf('Peak memory: %.1f MB | Waktu total: %.1fs', memory_get_peak_usage(true) / 1048576, microtime(true) - $start));
        }

        return self::SUCCESS;
    }

    /**
     * Tentukan tabel target: eksplisit (--tables), --all, atau 8 tabel inti.
     *
     * @return array<string, array{source: string[], kunci: string[]|null, tabelSumber: bool}>
     */
    private function resolveTargets(bool $all, ?string $tables): array
    {
        if ($all) {
            $out = [];
            foreach (array_keys($this->coreTables) as $t) {
                $out[$t] = $this->coreTables[$t];
            }

            return $out;
        }

        if ($tables) {
            $out = [];
            foreach (explode(',', $tables) as $nama) {
                $nama = trim($nama);
                if ($nama === '') {
                    continue;
                }
                if (! isset($this->coreTables[$nama])) {
                    // tabel non-inti dilayani generik (kunci = hash konten)
                    $out[$nama] = ['source' => [$nama], 'kunci' => ['__hash__'], 'tabelSumber' => false];
                } else {
                    $out[$nama] = $this->coreTables[$nama];
                }
            }

            return $out;
        }

        return $this->coreTables;
    }

    /**
     * Pass 1: ekstrak kolom per tabel sumber dari statement CREATE TABLE
     * (streaming baris-per-baris, statement CREATE selalu satu baris fisik).
     */
    private function scanTables(string $file, array $targets): void
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            $this->error('Gagal membuka file dump.');

            return;
        }

        while (($line = fgets($handle)) !== false) {
            if (! str_contains($line, 'CREATE TABLE `')) {
                continue;
            }
            preg_match_all('/CREATE TABLE `([a-z0-9_]+)`\s*\((.*?)\);/', $line, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $tabel = $m[1];
                preg_match_all('/`([a-z0-9_]+)`\s+([a-z0-9]+(?:\([^)]*\))?)/', $m[2], $cm, PREG_SET_ORDER);
                $this->columns[$tabel] = [];
                $this->columnTypes[$tabel] = [];
                foreach ($cm as $c) {
                    $this->columns[$tabel][] = $c[1];
                    $this->columnTypes[$tabel][$c[1]] = strtolower($c[2]);
                }
            }
        }
        fclose($handle);
    }

    /**
     * Pass 2: streaming statement INSERT, parse baris, staging chunk per tabel.
     *
     * @param  array<string, array{source: string[], kunci: string[]|null, tabelSumber: bool}>  $targets
     */
    private function streamStatements(string $file, array $targets): void
    {
        $buffer = '';
        $pos = 0;
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return;
        }

        while (true) {
            // compact buffer yang sudah diproses
            if ($pos > 1048576) {
                $buffer = substr($buffer, $pos);
                $pos = 0;
            }
            // pastikan cukup window utk statement berikutnya (max ~27KB/garis)
            if ($pos + 65536 > strlen($buffer) && ! feof($handle)) {
                $chunk = fread($handle, 1048576);
                if ($chunk === false) {
                    break;
                }
                $buffer .= $chunk;

                continue;
            }
            if ($pos >= strlen($buffer)) {
                break; // EOF & buffer habis
            }

            if (! preg_match('/\GINSERT INTO ([a-z0-9_]+)\s+VALUES/i', $buffer, $m, 0, $pos)) {
                // File dump memakai separator `\r\r` (CR), BUKAN `\n` — sebuah
                // statement bisa berbagi "garis" fisik dengan SQL lain
                // (CREATE/ALTER) sehingga lompat-ke-baris berikutnya TIDAK valid.
                // Cari marker INSERT berikutnya di mana pun di buffer.
                $cand = $this->nextInsertMarker($buffer, $pos);
                if ($cand === null) {
                    if (! feof($handle)) {
                        $chunk = fread($handle, 1048576);
                        if ($chunk === false || $chunk === '') {
                            break;
                        }
                        $buffer .= $chunk;

                        continue;
                    }
                    break; // EOF & tidak ada statement lagi
                }
                $pos = $cand;

                continue;
            }

            $tabelSumber = $m[1];
            $valuesStart = $pos + strlen($m[0]);
            $end = $this->statementEnd($buffer, $valuesStart);
            if ($end === null) {
                // statement belum lengkap (baris lanjutan) — baca lebih banyak data
                if (feof($handle)) {
                    $this->warn(sprintf('Statement INSERT %s terpotong di akhir file — dilewati.', $tabelSumber));
                    break;
                }
                $chunk = fread($handle, 1048576);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $buffer .= $chunk;

                continue;
            }

            $stmt = substr($buffer, $pos, $end - $pos + 1);
            $pos = $end + 1;

            $tabelTarget = $this->targetForSource($tabelSumber, $targets);
            if ($tabelTarget === null) {
                continue;
            }
            $this->processStatement($tabelTarget, $tabelSumber, $stmt, $targets[$tabelTarget]);
        }

        fclose($handle);
    }

    /** Cari source -> target; kembalikan nama tabel target atau null jika bukan target. */
    private function targetForSource(string $tabelSumber, array $targets): ?string
    {
        foreach ($targets as $tabelTarget => $cfg) {
            if (in_array($tabelSumber, $cfg['source'], true)) {
                return $tabelTarget;
            }
        }

        return null;
    }

    /**
     * Cari posisi "INSERT INTO" berikutnya di buffer yang merupakan AWAL
     * statement sungguhan. Statement asli selalu didahului `\r`, `\n`, atau
     * `;` (dump memakai separator `\r\r`; 1146/1146 terverifikasi). Kemunculan
     * lain dianggap di dalam nilai string → dilewati.
     */
    private function nextInsertMarker(string $buffer, int $from): ?int
    {
        $pos = $from;
        while (($c = stripos($buffer, 'INSERT INTO', $pos)) !== false) {
            $prev = $c > 0 ? $buffer[$c - 1] : "\n";
            if ($prev === "\r" || $prev === "\n" || $prev === ';') {
                return $c;
            }
            $pos = $c + 1;
        }

        return null;
    }

    /**
     * Tentukan akhir statement INSERT: posisi ';' setelah `)` penutup baris
     * terakhir (baris di level paren 0, bukan dibungkus paren luar).
     * Mengembalikan null jika statement belum lengkap di buffer.
     */
    private function statementEnd(string $buffer, int $from): ?int
    {
        $n = strlen($buffer);
        $depth = 0;
        $inStr = null;
        for ($i = $from; $i < $n; $i++) {
            $c = $buffer[$i];
            if ($inStr !== null) {
                if ($c === $inStr) {
                    if ($i + 1 < $n && $buffer[$i + 1] === $inStr) { // escape "" / ''
                        $i++;

                        continue;
                    }
                    $inStr = null;
                }

                continue;
            }
            if ($c === '"' || $c === "'") {
                $inStr = $c;

                continue;
            }
            if ($c === '(') {
                $depth++;

                continue;
            }
            if ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    // akhir baris: setelahnya harus ';' utk menutup statement.
                    // Jika bukan ';' (mis. ',' baris lanjutan) → LANJUT scan,
                    // jangan return null (null = buffer habis, butuh data lagi).
                    $j = $i + 1;
                    while ($j < $n && ($buffer[$j] === ' ' || $buffer[$j] === "\t" || $buffer[$j] === "\r" || $buffer[$j] === "\n")) {
                        $j++;
                    }
                    if ($j < $n && $buffer[$j] === ';') {
                        return $j;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Proses satu statement INSERT: split baris, normalisasi, staging.
     *
     * @param  array{source: string[], kunci: string[]|null, tabelSumber: bool}  $cfg
     */
    private function processStatement(string $tabelTarget, string $tabelSumber, string $stmt, array $cfg): void
    {
        $body = preg_replace('/^INSERT INTO [a-z0-9_]+\s+VALUES\s*/i', '', $stmt);
        $cols = $this->columns[$tabelSumber] ?? null;
        if ($cols === null) {
            $this->warn(sprintf('Kolom %s tidak ditemukan — statement dilewati.', $tabelSumber));

            return;
        }

        foreach ($this->splitRows($body) as $rowRaw) {
            if (count($rowRaw) !== count($cols)) {
                $this->error(sprintf(
                    'BARIS TIDAK COCOK %s: %d nilai vs %d kolom → hentikan (anti korupsi data).',
                    $tabelSumber,
                    count($rowRaw),
                    count($cols)
                ));

                return; // berhenti aman, jangan simpan baris salah
            }

            $row = [];
            $rowNormal = [];
            foreach ($cols as $i => $col) {
                $raw = $rowRaw[$i];
                $row[$col] = rawIsNull($raw) ? null : $raw;
                $rowNormal[$col] = $this->normalizeValue($row[$col], $this->columnType($tabelSumber, $col));
            }

            $kodeSumber = $this->buildKodeSumber($tabelTarget, $tabelSumber, $rowNormal, $cfg);

            $this->pending[$tabelTarget][] = [
                'kode_sumber' => $kodeSumber,
                'tabel_sumber' => $cfg['tabelSumber'] ? $tabelSumber : null,
                'payload_json' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                'payload_normal' => json_encode($rowNormal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $this->stats[$tabelTarget]['read'] = ($this->stats[$tabelTarget]['read'] ?? 0) + 1;

            if (count($this->pending[$tabelTarget]) >= $this->chunk) {
                $this->flushPending($tabelTarget);
            }
        }
    }

    /** Split region VALUES menjadi baris-baris (list field mentah). */
    private function splitRows(string $region): array
    {
        $rows = [];
        $cur = null;
        $field = '';
        $depth = 0;
        $inStr = null;
        $n = strlen($region);

        for ($i = 0; $i < $n; $i++) {
            $c = $region[$i];

            if ($inStr !== null) {
                if ($c === $inStr) {
                    if ($i + 1 < $n && $region[$i + 1] === $inStr) {
                        $field .= $c;
                        $i++;

                        continue;
                    }
                    $inStr = null;
                } else {
                    $field .= $c;
                }

                continue;
            }

            if ($c === '"' || $c === "'") {
                $inStr = $c;

                continue;
            }

            if ($depth === 0) {
                if ($c === '(') {
                    $depth = 1;
                    $cur = [];
                    $field = '';
                } elseif ($c === ';') {
                    break;
                }

                continue;
            }

            // depth === 1 (di dalam baris)
            if ($c === ',') {
                $cur[] = $field;
                $field = '';

                continue;
            }
            if ($c === ')') {
                $cur[] = $field;
                $rows[] = $cur;
                $depth = 0;
                $cur = null;
                if ($i + 1 < $n && $region[$i + 1] === ';') {
                    break;
                }

                continue;
            }
            $field .= $c;
        }

        return $rows;
    }

    /** Tipe kolom (utk normalisasi numerik) — diisi saat scanTables dari CREATE TABLE dump. */
    private function columnType(string $tabelSumber, string $col): string
    {
        return $this->columnTypes[$tabelSumber][$col] ?? '';
    }

    /**
     * Normalisasi nilai SID → nilai normal utk fase 2:
     * NULL / 'True'/'False' → boolean / 1899-12-30 & 0000-00-00 → null /
     * jam 12-jam ('12:00:34 PM') → 24-jam / string numerik → int|float.
     */
    private function normalizeValue(mixed $raw, string $type = ''): mixed
    {
        if ($raw === null) {
            return null;
        }

        if (preg_match('/^(true|false)$/i', (string) $raw)) {
            return strtolower((string) $raw) === 'true';
        }

        $s = (string) $raw;
        if ($s === '1899-12-30' || $s === '0000-00-00' || $s === '0000-00-00 00:00:00') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})\s*([AP]M)$/i', $s, $m)) {
            $h = (int) $m[1];
            $ap = strtoupper($m[4]);
            if ($ap === 'PM' && $h !== 12) {
                $h += 12;
            }
            if ($ap === 'AM' && $h === 12) {
                $h = 0;
            }

            return sprintf('%02d:%02d:%02d', $h, (int) $m[2], (int) $m[3]);
        }

        if (str_starts_with($type, 'decimal') || str_starts_with($type, 'float') || str_starts_with($type, 'double')) {
            return is_numeric($s) ? (float) $s : $s;
        }
        if (str_starts_with($type, 'int') || str_starts_with($type, 'tinyint') || str_starts_with($type, 'bigint')) {
            return is_numeric($s) ? (int) $s : $s;
        }

        return $s;
    }

    /** Bangun kode_sumber unik dari kolom kunci (atau hash konten utk generik / arus_stok). */
    private function buildKodeSumber(string $tabelTarget, string $tabelSumber, array $rowNormal, array $cfg): string
    {
        if ($cfg['kunci'] === ['__hash__']) {
            return md5((string) json_encode($rowNormal));
        }
        if ($cfg['kunci'] === null) {
            return strtoupper($tabelTarget); // tabel 1 baris (setup_perusahaan)
        }

        $parts = [];
        foreach ($cfg['kunci'] as $col) {
            $parts[] = (string) ($rowNormal[$col] ?? '');
        }
        $kode = implode('|', $parts);

        return $cfg['tabelSumber'] ? $tabelSumber.'|'.$kode : $kode;
    }

    /** Flush pending rows tabel target ke DB (idempoten: skip kode_sumber existing). */
    private function flushPending(string $tabelTarget): void
    {
        $rows = $this->pending[$tabelTarget] ?? [];
        $this->pending[$tabelTarget] = [];
        if ($rows === [] || $this->dryRun) {
            return;
        }

        $tabel = 'sid_retail_raw_'.$tabelTarget;

        foreach (array_chunk($rows, $this->chunk) as $chunk) {
            // dedup kode_sumber DALAM SATU CHUNK — dump SID bisa memuat
            // baris duplikat (mis. expired_barang kode_barang '3333' qty=1 ×6);
            // guard idempoten existing hanya cek terhadap DB, bukan sesama chunk.
            $seen = [];
            $chunk = array_values(array_filter($chunk, function ($r) use (&$seen) {
                if (isset($seen[$r['kode_sumber']])) {
                    return false;
                }
                $seen[$r['kode_sumber']] = true;

                return true;
            }));

            DB::transaction(function () use ($chunk, $tabelTarget, $tabel) {
                $keys = array_column($chunk, 'kode_sumber');
                $exists = DB::table($tabel)->whereIn('kode_sumber', $keys)->pluck('kode_sumber')->flip();

                $insert = [];
                foreach ($chunk as $r) {
                    if (isset($exists[$r['kode_sumber']])) {
                        $this->stats[$tabelTarget]['skip'] = ($this->stats[$tabelTarget]['skip'] ?? 0) + 1;

                        continue;
                    }
                    $insert[] = $r;
                }

                if ($insert !== []) {
                    DB::table($tabel)->insert($insert);
                    $this->stats[$tabelTarget]['insert'] = ($this->stats[$tabelTarget]['insert'] ?? 0) + count($insert);
                }
            });
        }
    }

    /** Cetak laporan per tabel (read/insert/skip + total di DB). */
    private function printReport(array $targets): void
    {
        $this->newLine();
        $this->line(str_pad('TABEL RAW', 34).str_pad('DIBACA', 10).str_pad('INSERT', 10).'SKIP');
        $this->line(str_repeat('-', 72));

        $totRead = $totInsert = $totSkip = 0;
        foreach (array_keys($targets) as $tabelTarget) {
            $target = 'sid_retail_raw_'.$tabelTarget;
            $read = $this->stats[$tabelTarget]['read'] ?? 0;
            $insert = $this->stats[$tabelTarget]['insert'] ?? 0;
            $skip = $this->stats[$tabelTarget]['skip'] ?? 0;
            if ($this->dryRun) {
                $dbTotal = '-';
            } else {
                $dbTotal = number_format((int) DB::table($target)->count());
            }
            $this->line(sprintf('%-33s %9s %10d %9d   (total di DB: %s)', $target, number_format($read), $insert, $skip, $dbTotal));
            $totRead += $read;
            $totInsert += $insert;
            $totSkip += $skip;
        }

        $this->line(str_repeat('-', 72));
        $this->line(sprintf('TOTAL: %d baris dibaca, %d baru di-insert, %d di-skip (idempoten)', $totRead, $totInsert, $totSkip));
        if ($this->dryRun) {
            $this->comment('DRY-RUN — tidak ada data ditulis ke DB.');
        }
    }
}

/** helper global — NULL mentah dari dump */
if (! function_exists('rawIsNull')) {
    function rawIsNull(?string $raw): bool
    {
        return $raw === null || strtoupper(trim((string) $raw)) === 'NULL';
    }
}
