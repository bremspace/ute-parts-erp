<?php

namespace App\Console\Commands;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Services\JurnalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [MIGRASI-SID FASE 3 C-08] Jurnal pembuka (opening balance) — saldo awal per akun.
 *
 * SUMBER saldo (final di DB):
 *   - master_kas.saldo_awal        → Kas  (tipe='kas')  & Bank (tipe='bank')
 *   - piutang (jumlah − jumlah_dibayar) → Piutang Usaha
 *   - utang   (jumlah − jumlah_dibayar) → Utang Usaha    (staging 0)
 *   - stok_items.jumlah × produk.harga_beli → Persediaan (harga_beli NULL → 0 + catat)
 *   - Modal (ekuitas) = balancing: Kas + Bank + Piutang + Persediaan − Utang
 *     (bila negatif → MODAL negatif tetap VALID, dicatat di laporan)
 *
 * ENTRI: 1 jurnal, tanggal 2026-01-01, no_jurnal 'SID-OPENING' (deterministik,
 * idempotency guard = no_jurnal unik), sumber 'sid', deskripsi 'PEMBUKAAN SID …',
 * cabang_id 1. Baris nol (Bank 0, dst) TIDAK ditulis sebagai line jurnal
 * (dicatat di laporan saja).
 *
 * MODE:
 *   - default / --dry-run → hitung + laporan SAJA (TANPA perubahan DB)
 *   - --commit            → posting via JurnalService::post (validasi balance +
 *                           idempotency guard). KEPUTUSAN KOMIT = orchestrator,
 *                           fase ini TIDAK dijalankan.
 */
class SidJurnalPembuka extends Command
{
    protected $signature = 'sid:jurnal-pembuka {--dry-run : hitung & laporan saja, tanpa menulis DB} {--commit : posting jurnal pembuka (idempotent)}';

    protected $description = 'FASE 3 (C-08): hitung saldo pembuka SID + jurnal pembuka (default dry-run)';

    private const TANGGAL_OPENING = '2026-01-01';

    private const NO_JURNAL = 'SID-OPENING';

    private const CABANG_ID = 1;

    /** kode akun_coa → kelompok saldo. Semua sudah ada di seeder (26 akun, C-08 cek 09-22). */
    private const AKUN_MAP = [
        '110-01' => 'Kas',         // aset, debit
        '110-02' => 'Bank',        // aset, debit
        '120-01' => 'Piutang Usaha', // aset, debit
        '130-01' => 'Persediaan Barang Dagang', // aset, debit
        '210-01' => 'Utang Usaha', // kewajiban, kredit
        '310-01' => 'Modal Pemilik', // ekuitas, kredit
    ];

    public function handle(JurnalService $jurnalService): int
    {
        $commit = (bool) $this->option('commit') && ! $this->option('dry-run');

        $this->info($commit
            ? 'MODE: COMMIT — jurnal pembuka akan diposting (idempotent)'
            : 'MODE: DRY-RUN — hanya hitung & laporan, TIDAK ada perubahan DB');

        $saldo = $this->hitungSaldo();

        // --- Cek ketersediaan akun (jangan buat baris — laporkan gap) ---
        $akunAda = [];
        $akunGap = [];
        foreach (array_keys(self::AKUN_MAP) as $kode) {
            $a = AkunCOA::where('kode', $kode)->first();
            if ($a) {
                $akunAda[$kode] = $a->id;
            } else {
                $akunGap[$kode] = self::AKUN_MAP[$kode];
            }
        }

        // --- Susun baris jurnal (lewatkan nilai nol) ---
        $lines = [];
        $lines[] = ['akun_kode' => '110-01', 'debit' => $saldo['kas'], 'kredit' => 0];
        $lines[] = ['akun_kode' => '110-02', 'debit' => $saldo['bank'], 'kredit' => 0];
        $lines[] = ['akun_kode' => '120-01', 'debit' => $saldo['piutang'], 'kredit' => 0];
        $lines[] = ['akun_kode' => '130-01', 'debit' => $saldo['persediaan'], 'kredit' => 0];
        $lines[] = ['akun_kode' => '210-01', 'debit' => 0, 'kredit' => $saldo['utang']];
        $lines[] = ['akun_kode' => '310-01', 'debit' => 0, 'kredit' => $saldo['modal']];
        $lines = array_values(array_filter($lines, fn ($l) => ($l['debit'] ?? 0) > 0 || ($l['kredit'] ?? 0) > 0));

        $totalDebit = array_sum(array_column($lines, 'debit'));
        $totalKredit = array_sum(array_column($lines, 'kredit'));
        $balance = round($totalDebit, 2) === round($totalKredit, 2);

        $this->printRingkasan($saldo, $akunGap, $lines, $totalDebit, $totalKredit, $balance);

        // --- Commit (HANYA bila diminta eksplisit; fase ini orchestrator TIDAK menjalankan) ---
        $commitResult = null;
        if ($commit) {
            if (! $balance) {
                $this->error('ABORT commit: jurnal tidak balance (debit ≠ kredit). Laporan disimpan, tidak ada write.');
            } elseif ($akunGap) {
                $this->error('ABORT commit: ada akun_coa tidak ada -> '.implode(', ', array_keys($akunGap)).'. Buat dulu (keputusan orchestrator).');
            } else {
                try {
                    $commitResult = $jurnalService->post(
                        self::NO_JURNAL,
                        self::TANGGAL_OPENING,
                        'sid',
                        $lines,
                        'PEMBUKAAN SID — saldo awal migrasi retail SID (opening balance, keputusan orchestrator)',
                        self::CABANG_ID,
                        null,
                        'SID-MIGRASI',
                        null,
                    );
                    $this->info("COMMIT OK: {$commitResult[0]->no_jurnal} — ".count($commitResult).' baris diposting');
                } catch (\Throwable $e) {
                    $this->warn('COMMIT DITOLAK: '.$e->getMessage());
                }
            }
        }

        $this->tulisLaporan($saldo, $akunAda, $akunGap, $lines, $totalDebit, $totalKredit, $balance, $commit, $commitResult);

        if (! $balance) {
            return 1;
        }

        return 0;
    }

    /** @return array<string,float|int|string> */
    private function hitungSaldo(): array
    {
        $kas = (float) DB::table('master_kas')->where('tipe', 'kas')->sum('saldo_awal');
        $bank = (float) DB::table('master_kas')->where('tipe', 'bank')->sum('saldo_awal');

        $piutang = (float) DB::table('piutang')
            ->selectRaw('SUM(jumlah - jumlah_dibayar) s')
            ->value('s');
        $piutangBaris = DB::table('piutang')->count();
        $piutangBelumLunas = DB::table('piutang')->where('status', 'belum_lunas')->count();

        $utang = (float) DB::table('utang')
            ->selectRaw('SUM(jumlah - jumlah_dibayar) s')
            ->value('s');
        $utangBaris = DB::table('utang')->count();

        $persediaan = (float) DB::table('stok_items')
            ->leftJoin('produk', 'produk.id', '=', 'stok_items.produk_id')
            ->selectRaw('SUM(stok_items.jumlah * COALESCE(produk.harga_beli, 0)) s')
            ->value('s');

        $persediaanHargaBeliNull = DB::table('stok_items')
            ->leftJoin('produk', 'produk.id', '=', 'stok_items.produk_id')
            ->whereNull('produk.harga_beli')
            ->count();

        $stokBaris = DB::table('stok_items')->count();
        $stokPerGudang = DB::table('stok_items')
            ->select('gudang_id', DB::raw('COUNT(*) c'), DB::raw('SUM(jumlah) qty'))
            ->groupBy('gudang_id')->orderBy('gudang_id')
            ->get()->map(fn ($g) => ['gudang_id' => $g->gudang_id, 'baris' => $g->c, 'qty' => $g->qty])->all();

        $modal = $kas + $bank + $piutang + $persediaan - $utang;

        return [
            'kas' => round($kas, 2),
            'bank' => round($bank, 2),
            'piutang' => round((float) $piutang, 2),
            'piutang_baris' => $piutangBaris,
            'piutang_belum_lunas' => $piutangBelumLunas,
            'utang' => round((float) $utang, 2),
            'utang_baris' => $utangBaris,
            'persediaan' => round($persediaan, 2),
            'persediaan_harga_beli_null' => $persediaanHargaBeliNull,
            'stok_baris' => $stokBaris,
            'stok_per_gudang' => $stokPerGudang,
            'modal' => round($modal, 2),
            'modal_negatif' => $modal < 0,
        ];
    }

    /** @param array<string,float|int|string|array> $saldo */
    private function printRingkasan(array $saldo, array $akunGap, array $lines, float $totalDebit, float $totalKredit, bool $balance): void
    {
        $this->line("\n=== SALDO PEMBUKA (SID) ===");
        foreach (['kas' => 'Kas', 'bank' => 'Bank', 'piutang' => 'Piutang Usaha', 'persediaan' => 'Persediaan', 'utang' => 'Utang Usaha', 'modal' => 'Modal (balancing)'] as $k => $label) {
            $this->line(sprintf('%-20s %15s', $label, number_format((float) $saldo[$k], 2, ',', '.')));
        }
        if ($saldo['modal_negatif']) {
            $this->warn('⚠️ MODAL NEGATIF — valid (balancing), dicatat di laporan');
        }
        $this->line("\n=== BARIS JURNAL (akan diposting / hasil) ===");
        foreach ($lines as $l) {
            $this->line(sprintf('  %-8s D %15s | K %15s', $l['akun_kode'],
                number_format((float) $l['debit'], 2, ',', '.'), number_format((float) $l['kredit'], 2, ',', '.')));
        }
        $this->line(sprintf('  TOTAL    D %15s | K %15s  %s', number_format($totalDebit, 2, ',', '.'),
            number_format($totalKredit, 2, ',', '.'), $balance ? '✅ BALANCE' : '❌ TIDAK BALANCE'));
        if ($akunGap) {
            $this->warn('Akun TIDAK ada di akun_coa (perlu dibuat, keputusan orchestrator): '.implode(', ', array_map(fn ($k, $n) => "$k $n", array_keys($akunGap), $akunGap)));
        }
    }

    /**
     * @param  array<string,float|int|string|array>  $saldo
     * @param  array<string,int>  $akunAda
     * @param  array<string,string>  $akunGap
     * @param  array<int,array<string,float|int|string>>  $lines
     */
    private function tulisLaporan(array $saldo, array $akunAda, array $akunGap, array $lines, float $totalDebit, float $totalKredit, bool $balance, bool $commit, ?array $commitResult): void
    {
        $dir = storage_path('app/migrasi-sid');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir.'/C8-jurnal-pembuka-laporan-'.date('Ymd-His').'.md';
        $mode = $commit ? 'COMMIT' : ($this->option('dry-run') ? 'DRY-RUN' : 'DRY-RUN (default)');

        $fmt = fn (float $v) => number_format($v, 2, ',', '.');

        $body = '# LAPORAN FASE 3 (C-08) — JURNAL PEMBUKA SID — '.date('Y-m-d H:i:s')."\n\n"
            ."Mode: **{$mode}** — Perintah: `php artisan sid:jurnal-pembuka` (default dry-run; `--commit` = posting, idempotent by no_jurnal)\n"
            ."Status: **command siap, dry-run selesai, keputusan commit di tangan orchestrator** (C-08 checkbox tetap `- [ ]`)\n\n"
            ."## 1. Saldo pembuka per sumber\n\n"
            ."| Komponen | Sumber | Nilai |\n|---|---|---|\n"
            ."| Kas | `master_kas` tipe=kas (KT 99.818.660, KQ 0) | {$fmt((float) $saldo['kas'])} |\n"
            ."| Bank | `master_kas` tipe=bank (BCA/BRI/BNI 0) | {$fmt((float) $saldo['bank'])} |\n"
            ."| Piutang Usaha | `piutang` Σ(jumlah − jumlah_dibayar) — {$saldo['piutang_baris']} baris, {$saldo['piutang_belum_lunas']} belum_lunas | {$fmt((float) $saldo['piutang'])} |\n"
            ."| Persediaan | Σ `stok_items.jumlah` × `produk.harga_beli` — {$saldo['stok_baris']} baris stok | {$fmt((float) $saldo['persediaan'])} |\n"
            ."| Utang Usaha | `utang` Σ(jumlah − jumlah_dibayar) — {$saldo['utang_baris']} baris | {$fmt((float) $saldo['utang'])} |\n"
            ."| **Modal (balancing)** | Kas + Bank + Piutang + Persediaan − Utang | **{$fmt((float) $saldo['modal'])}** |\n\n"
            .($saldo['modal_negatif'] ? "> ⚠️ **MODAL NEGATIF** — valid (balancing hasil = negatif), tetap dicatat sebagai kredit negatif/posisi defisit modal. Keputusan akuntansi di tangan orchestrator.\n\n" : '')
            ."## 2. Entri jurnal pembuka\n\n"
            .'- Tanggal: `'.self::TANGGAL_OPENING."` (di pilih 2026-01-01 — sebelum transaksi historis min 2026-02-14; alternatif MIN(tanggal_transaksi) dicatat di catatan verifikasi)\n"
            .'- no_jurnal: `'.self::NO_JURNAL."` (deterministik → idempotent; rerun `--commit` ditolak guard duplikat)\n"
            .'- sumber: `sid`, referensi_tipe: `SID-MIGRASI`, cabang_id: '.self::CABANG_ID."\n"
            ."- deskripsi: `PEMBUKAAN SID — saldo awal migrasi retail SID (opening balance)` (kolom `is_migrasi_sid` TIDAK ada di jurnal_akuntansi → dicatat di deskripsi)\n"
            ."- Baris nilai 0 (Bank 0, dst) TIDAK ditulis sebagai line jurnal (dilaporkan di sini saja)\n\n"
            ."| Akun (kode) | Nama | Debit | Kredit |\n|---|---|---:|---:|\n";

        foreach ($lines as $l) {
            $kode = $l['akun_kode'];
            $nama = self::AKUN_MAP[$kode].(isset($akunAda[$kode]) ? '' : ' (GAP)');
            $body .= "| {$kode} | {$nama} | ".($l['debit'] > 0 ? $fmt((float) $l['debit']).' | 0,00' : '0,00 | '.$fmt((float) $l['kredit']))." |\n";
        }
        $body .= "| **TOTAL** | | **{$fmt($totalDebit)}** | **{$fmt($totalKredit)}** |\n\n"
            .'**Balancing check:** debit '.($balance ? '=' : '≠').' kredit → '.($balance ? '✅ BALANCE ('.round($totalDebit, 2).' = '.round($totalKredit, 2).')' : '❌ TIDAK BALANCE')."\n\n"
            ."## 3. Akun COA — ketersediaan\n\n"
            ."| Kode | Nama yang dibutuhkan | Status |\n|---|---|---|\n";
        foreach (self::AKUN_MAP as $kode => $nama) {
            $status = isset($akunAda[$kode]) ? "ADA (id {$akunAda[$kode]})" : '**TIDAK ADA — GAP (perlu dibuat, keputusan orchestrator)**';
            $body .= "| {$kode} | {$nama} | {$status} |\n";
        }
        $body .= "\nTotal akun_coa existing: **".AkunCOA::count()."**.\n\n"
            ."## 4. Catatan / deviasi\n\n"
            ."- Stok: {$saldo['stok_baris']} baris stok_items; per gudang:\n";
        foreach ($saldo['stok_per_gudang'] as $g) {
            $body .= "  - gudang_id {$g['gudang_id']}: {$g['baris']} baris, qty {$g['qty']}\n";
        }
        $body .= "- **produk.harga_beli NULL** (dihitung 0): **{$saldo['persediaan_harga_beli_null']}** baris stok_items\n"
            ."- Piutang: 11 baris semua belum_lunas, jumlah_dibayar 0 (B-01 laporan 0 pelunasan di dump) — Σ 3.001.000,00\n"
            ."- Utang: {$saldo['utang_baris']} baris (staging hutang = 0, B-02 SKIP) → 0,00\n"
            ."- Deviasi skema: `jurnal_akuntansi` tidak punya kolom `is_migrasi_sid` → identitas migrasi dicatat di `deskripsi`\n"
            ."- Alternatif tanggal: MIN(tanggal_transaksi) transaksi = 2026-02-14 09:17:05; dipilih 2026-01-01 agar entri pembuka mendahului seluruh transaksi historis\n\n";

        if ($commit) {
            $body .= "## 5. Status commit\n\n";
            if ($commitResult) {
                $body .= "- ✅ POSTED: `{$commitResult[0]->no_jurnal}`, ".count($commitResult).' baris (id '.implode(',', array_column($commitResult, 'id')).")\n";
            } else {
                $body .= "- ❌ GAGAL / tidak diposting (lihat output console)\n";
            }
        } else {
            $body .= "## 5. Status commit\n\n- **Belum diposting** — DRY-RUN. `php artisan sid:jurnal-pembuka --commit` oleh orchestrator setelah review. Keputusan akuntansi: di tangan orchestrator.\n";
        }

        file_put_contents($file, $body);
        $this->info('Laporan: '.$file);
    }
}
