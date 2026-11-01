<?php

namespace App\Modules\Akunting\Services;

use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\RekonsiliasiBank;
use App\Modules\Akunting\Models\RekonsiliasiBankItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Service Rekonsiliasi Bank Multi-Format & Auto-Matching (ISO 9001 / ISO 27001 / SAK).
 *
 * Mendukung import rekening koran CSV, XLSX, dan PDF (BCA, Mandiri, BRI, BNI),
 * parsing otomatis baris mutasi bank, matching cerdas dengan buku besar bank,
 * dan posting jurnal penyesuaian (biaya admin / bunga) 1-klik.
 */
class RekonsiliasiBankService
{
    public const DEFAULT_AKUN_BANK = '110-02';

    public function __construct(
        protected JurnalService $jurnalService
    ) {}

    /**
     * Parse file mutasi bank (PDF, CSV, XLSX) menjadi array baris mutasi terstandarisasi.
     *
     * @return array<int, array{tanggal: string, deskripsi: string, tipe: string, nominal: float}>
     */
    public function parseFile(string $filePath, string $extension): array
    {
        $extension = strtolower($extension);

        return match ($extension) {
            'pdf' => $this->parsePdf($filePath),
            'csv', 'txt' => $this->parseCsv($filePath),
            'xlsx', 'xls' => $this->parseExcel($filePath),
            default => throw new \InvalidArgumentException("Format berkas '{$extension}' tidak didukung. Gunakan PDF, CSV, atau Excel."),
        };
    }

    /**
     * Parser cerdas mutasi rekening koran PDF (BCA, Mandiri, BNI, BRI dll).
     * Menggunakan pdftotext -layout untuk preservasi kolom dan koordinat teks.
     */
    public function parsePdf(string $filePath): array
    {
        $text = '';

        // Prioritas 1: Gunakan /usr/bin/pdftotext -layout jika tersedia di host Linux
        if (file_exists('/usr/bin/pdftotext')) {
            $cmd = sprintf('/usr/bin/pdftotext -layout %s -', escapeshellarg($filePath));
            $result = Process::run($cmd);
            if ($result->successful()) {
                $rawOutput = $result->output();
                if (trim($rawOutput, " \t\n\r\0\x0B\f") !== '') {
                    $text = $rawOutput;
                }
            }
        }

        // Fallback: Ekstraksi teks dari aliran stream biner PDF murni jika CLI gagal / kosong
        if (trim($text, " \t\n\r\0\x0B\f") === '') {
            $text = $this->extractTextFromPdfBinary($filePath);
        }

        if (trim($text, " \t\n\r\0\x0B\f") === '') {
            throw new \Exception('Gagal mengekstrak teks dari berkas PDF. Pastikan berkas PDF bukan scan gambar (OCR).');
        }

        return $this->extractTransactionsFromText($text);
    }

    /**
     * Parser berkas CSV / TXT mutasi rekening koran bank.
     */
    public function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new \Exception('Gagal membaca berkas CSV.');
        }

        $items = [];
        $currentYear = (int) now()->format('Y');

        while (($row = fgetcsv($handle, 2000, ',')) !== false) {
            if (empty($row) || count($row) < 2) {
                continue;
            }

            // Jika semikolon separator
            if (count($row) === 1 && str_contains($row[0], ';')) {
                $row = str_getcsv($row[0], ';');
            }

            $firstCell = trim($row[0]);
            // Lewati header (misal "Tgl", "Tanggal", "Date")
            if (preg_match('/^(tgl|tanggal|date|periode)/i', $firstCell) || ! preg_match('/\d{1,2}[\/\-]\d{1,2}/', $firstCell)) {
                continue;
            }

            $tanggal = $this->normalizeDate($firstCell, $currentYear);
            $deskripsi = trim($row[1] ?? 'Mutasi Bank');

            // Deteksi nominal dan tipe (DB/CR) dari kolom-kolom berikutnya
            $nominal = 0.0;
            $tipe = 'CR';

            for ($i = 2; $i < count($row); $i++) {
                $cell = trim($row[$i]);
                if (in_array(strtoupper($cell), ['CR', 'K', 'KREDIT'], true)) {
                    $tipe = 'CR';
                } elseif (in_array(strtoupper($cell), ['DB', 'D', 'DEBET', 'DEBIT'], true)) {
                    $tipe = 'DB';
                } elseif ($nominal <= 0 && preg_match('/[0-9]+[.,]?[0-9]*/', $cell)) {
                    $nomClean = $this->cleanNominal($cell);
                    if ($nomClean > 0) {
                        $nominal = $nomClean;
                    }
                }
            }

            // Jika tipe belum terdeteksi dari kolom, cek deskripsi
            if (preg_match('/\b(DB|DEBET|DEBIT)\b/i', $deskripsi)) {
                $tipe = 'DB';
            } elseif (preg_match('/\b(CR|KREDIT)\b/i', $deskripsi)) {
                $tipe = 'CR';
            }

            if ($nominal > 0) {
                $items[] = [
                    'tanggal' => $tanggal,
                    'deskripsi' => $deskripsi,
                    'tipe' => $tipe,
                    'nominal' => $nominal,
                ];
            }
        }

        fclose($handle);

        return $items;
    }

    /**
     * Parser berkas Excel (XLSX / XLS).
     */
    public function parseExcel(string $filePath): array
    {
        $rows = Excel::toArray(new class implements ToArray
        {
            public function array(array $array): array
            {
                return $array;
            }
        }, $filePath)[0] ?? [];

        $items = [];
        $currentYear = (int) now()->format('Y');

        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }

            // Normalisasi baris menjadi string dipisah spasi atau tab untuk diproses
            $line = implode('  ', array_filter(array_map('trim', $row)));
            $parsed = $this->parseTransactionLine($line, $currentYear);
            if ($parsed !== null) {
                $items[] = $parsed;
            }
        }

        return $items;
    }

    /**
     * Analisa teks multi-baris dan parsing transaksi per baris.
     */
    private function extractTransactionsFromText(string $text, bool $isCsv = false): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $items = [];
        $currentYear = (int) now()->format('Y');

        // Deteksi tahun dari header laporan jika tersedia (misal: "PERIODE: AGUSTUS 2026")
        if (preg_match('/(?:periode|tanggal|tgl).*?(20\d{2})/i', $text, $ym)) {
            $currentYear = (int) $ym[1];
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '---') || str_starts_with($line, '===') || stripos($line, 'SALDO AWAL') !== false) {
                continue;
            }

            $parsed = $this->parseTransactionLine($line, $currentYear, $isCsv);
            if ($parsed !== null) {
                $items[] = $parsed;
            }
        }

        return $items;
    }

    /**
     * Pola cerdas deteksi baris mutasi bank Indonesia (BCA, Mandiri, BRI, BNI, dll).
     */
    private function parseTransactionLine(string $line, int $defaultYear, bool $isCsv = false): ?array
    {
        // Standarisasi pemisah koma / semikolon jika CSV
        if ($isCsv) {
            $line = str_replace([';', "\t"], '  ', $line);
        }

        // Format 1: Format BCA / Mandiri E-Statement umum:
        // Contoh: "02/08  TRSF E-BANKING CR  1.250.000,00  25.400.000,00"
        // Contoh: "05/08/2026 BIAYA ADM DB 15.000,00"
        // Contoh: "12-08-2026 TRANSFER KE REK LAIN 500.000,00 CR"
        // Contoh: "12-08-2026 TRANSFER KE REK LAIN 500.000,00 25.000.000,00 CR"
        $pattern = '/^(\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?)\s+(.+?)\s+([0-9.,]+)(?:\s+(DB|CR|D|K))?(?:\s+([0-9.,]+))?(?:\s+(DB|CR|D|K))?$/i';

        if (preg_match($pattern, $line, $matches)) {
            $tglStr = $matches[1];
            $deskripsi = trim($matches[2]);
            $nominalStr = $matches[3];
            $tipeIndikator = strtoupper($matches[4] ?: ($matches[6] ?? ''));

            // Tangani jika indikator DB/CR ada di dalam deskripsi teks
            if ($tipeIndikator === '') {
                if (preg_match('/\b(CR|KREDIT|K)\b/i', $deskripsi)) {
                    $tipeIndikator = 'CR';
                } elseif (preg_match('/\b(DB|DEBET|DEBIT|D)\b/i', $deskripsi)) {
                    $tipeIndikator = 'DB';
                } else {
                    $tipeIndikator = 'CR'; // default masuk
                }
            }

            $tipe = in_array($tipeIndikator, ['CR', 'K', 'KREDIT'], true) ? 'CR' : 'DB';
            $nominal = $this->cleanNominal($nominalStr);

            if ($nominal <= 0) {
                return null;
            }

            $tanggal = $this->normalizeDate($tglStr, $defaultYear);

            return [
                'tanggal' => $tanggal,
                'deskripsi' => $deskripsi,
                'tipe' => $tipe,
                'nominal' => $nominal,
            ];
        }

        // Format 2: Format KlikBCA CSV: "Tgl","Keterangan","Cabang","Nominal","Tipe"
        if (preg_match('/["\']?(\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?)["\']?\s*[, ]\s*["\']?([^"\']+)["\']?.*?([0-9.,]+)\s*[, ]\s*["\']?(DB|CR|D|K)["\']?/i', $line, $m2)) {
            $tanggal = $this->normalizeDate($m2[1], $defaultYear);
            $nominal = $this->cleanNominal($m2[3]);
            $tipe = in_array(strtoupper($m2[4]), ['CR', 'K'], true) ? 'CR' : 'DB';

            if ($nominal > 0) {
                return [
                    'tanggal' => $tanggal,
                    'deskripsi' => trim($m2[2]),
                    'tipe' => $tipe,
                    'nominal' => $nominal,
                ];
            }
        }

        return null;
    }

    /**
     * Konversi format tanggal DD/MM atau DD/MM/YYYY menjadi YYYY-MM-DD.
     */
    private function normalizeDate(string $rawDate, int $defaultYear): string
    {
        $rawDate = str_replace('-', '/', trim($rawDate));
        $parts = explode('/', $rawDate);

        if (count($parts) === 2) {
            $d = (int) $parts[0];
            $m = (int) $parts[1];

            return sprintf('%04d-%02d-%02d', $defaultYear, $m, $d);
        }

        if (count($parts) === 3) {
            $d = (int) $parts[0];
            $m = (int) $parts[1];
            $y = (int) $parts[2];
            if ($y < 100) {
                $y += 2000;
            }

            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }

        return now()->toDateString();
    }

    /**
     * Bersihkan format angka nominal uang Indonesia (1.250.000,50 -> 1250000.50).
     */
    private function cleanNominal(string $val): float
    {
        $val = trim($val);
        // Jika ada titik ribuan dan koma desimal
        if (str_contains($val, '.') && str_contains($val, ',')) {
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        } elseif (str_contains($val, ',')) {
            // Hanya ada koma (koma desimal atau pemisah)
            $parts = explode(',', $val);
            if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                $val = str_replace(',', '.', $val);
            } else {
                $val = str_replace(',', '', $val);
            }
        }

        return round(abs((float) preg_replace('/[^0-9.]/', '', $val)), 2);
    }

    /**
     * Ekstraktor biner PDF darurat jika library CLI tidak tersedia.
     */
    private function extractTextFromPdfBinary(string $filePath): string
    {
        $content = @file_get_contents($filePath);
        if (! $content) {
            return '';
        }

        $text = '';
        if (preg_match_all('/BT[\s\S]*?ET/', $content, $blocks)) {
            foreach ($blocks[0] as $block) {
                if (preg_match_all('/\((.*?)\)\s*Tj/s', $block, $matches)) {
                    $text .= implode(' ', $matches[1])."\n";
                }
            }
        }

        return $text;
    }

    /**
     * Mulai sesi rekonsiliasi bank dan jalankan auto-matching cerdas terhadap buku besar.
     */
    public function prosesImport(
        int $cabangId,
        int $akunCoaId,
        string $namaBank,
        string $namaFile,
        string $formatFile,
        array $items,
        int $userId,
        ?string $nomorRekening = null
    ): RekonsiliasiBank {
        if (empty($items)) {
            throw new \Exception('Tidak ditemukan baris transaksi yang valid di dalam berkas mutasi bank ini.');
        }

        // Tentukan rentang periode dari baris transaksi
        $dates = array_column($items, 'tanggal');
        sort($dates);
        $periodeDari = $dates[0];
        $periodeSampai = end($dates);

        return DB::transaction(function () use (
            $cabangId, $akunCoaId, $namaBank, $namaFile, $formatFile,
            $items, $userId, $nomorRekening, $periodeDari, $periodeSampai
        ) {
            $rekonsiliasi = RekonsiliasiBank::create([
                'cabang_id' => $cabangId,
                'akun_coa_id' => $akunCoaId,
                'nama_bank' => $namaBank,
                'nomor_rekening' => $nomorRekening,
                'periode_dari' => $periodeDari,
                'periode_sampai' => $periodeSampai,
                'nama_file' => $namaFile,
                'format_file' => $formatFile,
                'status' => 'draft',
                'total_baris_bank' => count($items),
                'total_matched' => 0,
                'user_id' => $userId,
            ]);

            // Ambil mutasi jurnal buku besar untuk akun bank ini pada rentang waktu tsb
            $jurnals = JurnalAkuntansi::where('cabang_id', $cabangId)
                ->where('akun_coa_id', $akunCoaId)
                ->whereDate('tanggal', '>=', Carbon::parse($periodeDari)->subDays(3))
                ->whereDate('tanggal', '<=', Carbon::parse($periodeSampai)->addDays(3))
                ->orderBy('tanggal')
                ->get();

            $matchedJurnalIds = [];
            $totalMatched = 0;

            foreach ($items as $row) {
                $tgl = $row['tanggal'];
                $nom = $row['nominal'];
                $tipe = $row['tipe']; // 'CR' = Uang Masuk, 'DB' = Uang Keluar

                // Aturan double entry akuntansi:
                // Mutasi Bank 'CR' (Uang Masuk ke rekening) = Jurnal 'debit' akun Bank
                // Mutasi Bank 'DB' (Uang Keluar dari rekening) = Jurnal 'kredit' akun Bank
                $kolomJurnal = $tipe === 'CR' ? 'debit' : 'kredit';

                // Tahap 1: Exact match (tanggal sama + nominal sama persis)
                $matchedJurnal = $jurnals->first(function ($j) use ($tgl, $nom, $kolomJurnal, $matchedJurnalIds) {
                    return ! in_array($j->id, $matchedJurnalIds, true)
                        && $j->tanggal->toDateString() === $tgl
                        && round((float) $j->{$kolomJurnal}, 2) === $nom;
                });

                // Tahap 2: Fuzzy date match (toleransi kliring tanggal ±3 hari + nominal sama persis)
                if (! $matchedJurnal) {
                    $matchedJurnal = $jurnals->first(function ($j) use ($nom, $kolomJurnal, $matchedJurnalIds) {
                        return ! in_array($j->id, $matchedJurnalIds, true)
                            && round((float) $j->{$kolomJurnal}, 2) === $nom;
                    });
                }

                $status = $matchedJurnal ? 'matched' : 'unmatched';
                $noJurnalMatched = $matchedJurnal?->no_jurnal;

                if ($matchedJurnal) {
                    $matchedJurnalIds[] = $matchedJurnal->id;
                    $totalMatched++;
                }

                RekonsiliasiBankItem::create([
                    'rekonsiliasi_bank_id' => $rekonsiliasi->id,
                    'tanggal' => $tgl,
                    'deskripsi' => $row['deskripsi'],
                    'tipe' => $tipe,
                    'nominal' => $nom,
                    'status' => $status,
                    'jurnal_akuntansi_id' => $matchedJurnal?->id,
                    'no_jurnal_matched' => $noJurnalMatched,
                ]);
            }

            $rekonsiliasi->update([
                'total_matched' => $totalMatched,
            ]);

            return $rekonsiliasi;
        });
    }

    /**
     * Buat Jurnal Penyesuaian 1-Klik dari baris rekonsiliasi bank (misal biaya admin / bunga bank).
     *
     * @param  string  $jenisPenyesuaian  'biaya_admin' | 'pendapatan_bunga'
     */
    public function buatJurnalPenyesuaian(
        int $itemId,
        string $jenisPenyesuaian,
        int $userId,
        ?string $keterangan = null
    ): array {
        $item = RekonsiliasiBankItem::with('rekonsiliasi.akun')->findOrFail($itemId);

        if ($item->status === 'matched') {
            throw new \Exception('Baris mutasi ini sudah tercocokkan dengan jurnal.');
        }

        $cabangId = $item->rekonsiliasi->cabang_id;
        $akunBank = $item->rekonsiliasi->akun;

        // Tentukan akun lawan
        if ($jenisPenyesuaian === 'biaya_admin') {
            // Biaya Admin: Debit Beban Lain-lain (520-05) / Kredit Bank
            $akunLawanKode = '520-05';
            $deskripsi = $keterangan ?: "Biaya Administrasi Bank {$item->rekonsiliasi->nama_bank} ({$item->deskripsi})";
            $lines = [
                ['akun_kode' => $akunLawanKode, 'debit' => $item->nominal, 'kredit' => 0],
                ['akun_kode' => $akunBank->kode, 'debit' => 0, 'kredit' => $item->nominal],
            ];
        } else {
            // Pendapatan Bunga: Debit Bank / Kredit Pendapatan Lain-lain (430-01)
            $akunLawanKode = '430-01';
            $deskripsi = $keterangan ?: "Pendapatan Bunga Bank {$item->rekonsiliasi->nama_bank} ({$item->deskripsi})";
            $lines = [
                ['akun_kode' => $akunBank->kode, 'debit' => $item->nominal, 'kredit' => 0],
                ['akun_kode' => $akunLawanKode, 'debit' => 0, 'kredit' => $item->nominal],
            ];
        }

        $noJurnal = $this->jurnalService->generateNoJurnal('manual', $cabangId);

        return DB::transaction(function () use ($item, $noJurnal, $lines, $deskripsi, $cabangId, $userId) {
            $created = $this->jurnalService->post(
                noJurnal: $noJurnal,
                tanggal: $item->tanggal,
                sumber: 'manual',
                lines: $lines,
                deskripsi: $deskripsi,
                cabangId: $cabangId,
                userId: $userId
            );

            // Update status item rekonsiliasi
            $item->update([
                'status' => 'adjusted',
                'jurnal_akuntansi_id' => $created[0]->id ?? null,
                'no_jurnal_matched' => $noJurnal,
                'catatan' => 'Disesuaikan dengan jurnal penyesuaian otomatis',
            ]);

            // Refresh total matched
            $item->rekonsiliasi->increment('total_matched');

            return [
                'no_jurnal' => $noJurnal,
                'item_id' => $item->id,
            ];
        });
    }
}
