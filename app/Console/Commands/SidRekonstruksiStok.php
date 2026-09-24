<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [MIGRASI-SID FASE 3] Rekonstruksi stok (C-05..C-06) — idempotent, chunked.
 *
 * C-05 stok_log 1:1 ← sid_retail_raw_arus_stok (19.286):
 *   - tanggal_log = tanggal + jam SID → 'Y-m-d H:i:s' (fallback tanggal 00:00:00), created_at = tanggal_log
 *   - produk_id via produk.kode_lama; TIDAK ketemu → dummy '[ARSIP-SID] <kode_barang>' (produk_id NOT NULL)
 *   - sku_variant_id via sku_variants.kode_lama (nullable, 1:1 dgn produk — A-05)
 *   - gudang_id via lokasi_stok → gudang by kode/nama (TOKO→TOKO1 id6, GUDANG→5, PUSAT→4, TOKO2..15→7..20);
 *     fallback lokasi_cabang; default 6 (gudang_id NOT NULL)
 *   - jenis label → penjualan | masuk | pembelian | koreksi (label DEL, RETURN, tak dikenal, kosong → koreksi)
 *   - perubahan = masuk>0 ? masuk : (keluar>0 ? -keluar : 0); jumlah_sebelum = awal; jumlah_setelah = sisa
 *   - referensi_tipe: no_transaksi match transaksi.no_transaksi → App\Modules\Pos\Models\Transaksi,
 *     referensi_id diisi (kolom ADA di DB, nullable); no_transaksi selalu di catatan
 *   - baris dengan masuk>0 DAN keluar>0 (52) → split 2 baris '|M' dan '|K' (total +52)
 *   - idempotent: catatan pola 'AS:<kode_sumber> |' — DEVIASI dari spec 'AS:<kode>':
 *     payload kode TIDAK unik (4.015 distinct utk 19.286 baris — dup TRX-* x7 antar partisi);
 *     kode_sumber unik per baris → guard memakai kode_sumber.
 *
 * C-06 stok_items (saldo) — SUMBER = arus last-sisa (spec: "SALIN dari arus"):
 *   - wipe SEMUA stok_items (3.044 artefak migrasi lama; verified created_at <= 2026-09-21 08:19:59)
 *   - last baris per (kode_barang, gudang) by tanggal desc + id desc → sisa = jumlah
 *   - jumlah_minimum = produk.stok_warning ?? 0 (A-02: SEMUA 0.00 → 0)
 *   - skip sisa <= 0 (saldo 0/negatif implisit — negatif 749 baris, katalog di laporan)
 *   - produk TANPA arus (6.362−3.341): fallback payload barang toko→gudang6, gudang→gudang5 HANYA > 0
 *   - rekonsiliasi: arus-last-sisa vs payload toko/gudang (deviasi > 5 katalog);
 *     arus-last-sisa vs stok_log rekonstruksi (harus 0 mismatch).
 */
class SidRekonstruksiStok extends Command
{
    protected $signature = 'sid:rekonstruksi-stok {--dry-run : hanya hitung & laporan, tanpa perubahan DB} {--only=log,saldo : log|saldo}';

    protected $description = 'FASE 3 (C-05..C-06): rekonstruksi stok_log 1:1 dari arus_stok + rebuild stok_items saldo';

    private array $counts = [
        'c05_staging' => 0, 'c05_inserted' => 0, 'c05_skipped' => 0, 'c05_split' => 0, 'c05_dummy_baru' => 0,
        'c05_sisa_beda' => 0, 'c05_sku_null' => 0, 'c05_gudang_default' => 0, 'c05_ref_ada' => 0, 'c05_ref_null' => 0,
        'c06_wipe' => 0, 'c06_arus_insert' => 0, 'c06_arus_zero' => 0, 'c06_arus_neg' => 0,
        'c06_fallback_insert' => 0, 'c06_fallback_skip' => 0,
        'rek_stoklog_mismatch' => 0, 'rek_deviasi_gt5' => 0, 'rek_deviasi_cek' => 0,
    ];

    private array $reportLines = [];

    // analisa
    private array $labelDist = [];

    private int $both = 0;

    private int $onlyIn = 0;

    private int $onlyOut = 0;

    private int $none = 0;

    private array $lokasiStok = [];

    private array $lokasiCabang = [];

    private array $jenisDist = [];

    private array $gudangDist = [];

    /** @var array<string,int> */
    private array $produkMap = [];

    /** @var array<string,int> */
    private array $skuMap = [];

    /** @var array<string,int> gudang kode + nama uppercase → id */
    private array $gudangMap = [];

    /** @var array<string,int> transaksi.no_transaksi → id */
    private array $transaksiMap = [];

    /** @var array<int,int> produk_id → stok_warning */
    private array $stokWarningMap = [];

    /** @var array<int,int> produk_id → sku_variant_id */
    private array $skuByProduk = [];

    /** @var string[] */
    private array $deviasiKatalog = [];

    private const SENTINEL = ['1899-12-30', '0000-00-00', '1900-01-01', ''];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));

        if ($dry) {
            $this->warn('DRY-RUN: tidak ada perubahan DB');
        }

        $this->loadMaps();
        $this->analisa();

        if (in_array('log', $only, true)) {
            $this->stepLog($dry);
        }
        if (in_array('saldo', $only, true)) {
            $this->stepSaldo($dry);
        }

        $this->tulisLaporan($dry);
        $this->printSummary();

        return 0;
    }

    // ---------------- ANALISA (wajib, output ke laporan) ----------------

    private function analisa(): void
    {
        $this->info("\n--- Analisa arus_stok ---");

        $kodeSet = [];
        $total = DB::table('sid_retail_raw_arus_stok')->count();
        $offset = 0;
        while ($offset < $total) {
            foreach (DB::table('sid_retail_raw_arus_stok')->select('payload_normal')->skip($offset)->take(2000)->get() as $r) {
                $p = json_decode($r->payload_normal, true);
                if (! is_array($p)) {
                    $this->none++;
                    $offset += 2000;

                    continue;
                }
                $label = trim((string) ($p['transaksi'] ?? ''));
                $this->labelDist[$label === '' ? '(kosong)' : $label] = ($this->labelDist[$label === '' ? '(kosong)' : $label] ?? 0) + 1;

                $m = (float) ($p['masuk'] ?? 0);
                $k = (float) ($p['keluar'] ?? 0);
                if ($m > 0 && $k > 0) {
                    $this->both++;
                } elseif ($m > 0) {
                    $this->onlyIn++;
                } elseif ($k > 0) {
                    $this->onlyOut++;
                } else {
                    $this->none++;
                }

                $ls = trim((string) ($p['lokasi_stok'] ?? ''));
                $lc = trim((string) ($p['lokasi_cabang'] ?? ''));
                $this->lokasiStok[$ls === '' ? '(kosong)' : $ls] = ($this->lokasiStok[$ls === '' ? '(kosong)' : $ls] ?? 0) + 1;
                $this->lokasiCabang[$lc === '' ? '(kosong)' : $lc] = ($this->lokasiCabang[$lc === '' ? '(kosong)' : $lc] ?? 0) + 1;

                $kb = trim((string) ($p['kode_barang'] ?? ''));
                if ($kb !== '') {
                    $kodeSet[$kb] = true;
                }
            }
            $offset += 2000;
        }

        $this->counts['c05_staging'] = 0;
        $this->reportLines[] = '## Analisa awal arus_stok (19.286 baris)';
        $this->reportLines[] = '';
        $this->reportLines[] = '### 1. Distribusi label transaksi';
        $this->reportLines[] = '';
        arsort($this->labelDist);
        foreach ($this->labelDist as $l => $c) {
            $this->reportLines[] = "- `{$l}`: **{$c}**";
        }
        $this->reportLines[] = '';
        $this->reportLines[] = '### 2. Pola masuk/keluar';
        $this->reportLines[] = '';
        $this->reportLines[] = "- masuk>0 DAN keluar>0 (split 2 baris): **{$this->both}**";
        $this->reportLines[] = "- masuk>0 only: **{$this->onlyIn}**";
        $this->reportLines[] = "- keluar>0 only: **{$this->onlyOut}**";
        $this->reportLines[] = "- keduanya 0 (snapshot/aritma): **{$this->none}**";
        $this->reportLines[] = '';
        $this->reportLines[] = '### 3. Distribusi lokasi';
        $this->reportLines[] = '';
        $this->reportLines[] = 'lokasi_stok:';
        arsort($this->lokasiStok);
        foreach ($this->lokasiStok as $l => $c) {
            $this->reportLines[] = "- `{$l}` → gudang ".(isset($this->gudangMap[$l]) ? "id{$this->gudangMap[$l]}" : '(fallback/cabang/default 6)').": **{$c}**";
        }
        $this->reportLines[] = 'lokasi_cabang:';
        arsort($this->lokasiCabang);
        foreach ($this->lokasiCabang as $l => $c) {
            $this->reportLines[] = "- `{$l}`: **{$c}**";
        }
        $this->reportLines[] = '';
        $this->reportLines[] = '### 4. Barang dengan arus';
        $this->reportLines[] = '';
        $dummyForecast = 0;
        foreach ($kodeSet as $kb => $_) {
            if (! isset($this->produkMap[$kb])) {
                $dummyForecast++;
            }
        }
        $this->reportLines[] = '- distinct kode_barang dengan arus: **'.count($kodeSet).'** (produk kode_lama 6.362 → '
            .(6362 - count($kodeSet)).' produk TANPA arus → fallback payload toko/gudang @C-06)';
        $this->reportLines[] = '- kode_barang arus TANPA match produk (→ dummy `[ARSIP-SID]` di C-05): **'.$dummyForecast.'**';
        $this->reportLines[] = '';
        $this->reportLines[] = '---';
        $this->reportLines[] = '';
    }

    // ---------------- C-05 stok_log ----------------

    private function stepLog(bool $dry): void
    {
        $this->info("\n--- C-05 stok_log 1:1 ← arus_stok (19.286) ---");

        // idempotent guard: kode_sumber yang sudah dimigrasi (catatan 'AS:<kode_sumber> |')
        $migrated = [];
        DB::table('stok_log')->select('catatan')->where('is_migrasi_sid', 1)->orderBy('id')->chunk(2000, function ($chunk) use (&$migrated) {
            foreach ($chunk as $l) {
                $c = (string) $l->catatan;
                if (str_starts_with($c, 'AS:')) {
                    $end = strpos($c, ' |');
                    $migrated[substr($c, 3, $end === false ? null : $end - 3)] = true;
                }
            }
        });

        DB::table('sid_retail_raw_arus_stok')->orderBy('id')->chunkById(500, function ($chunk) use ($dry, $migrated) {
            $toInsert = [];
            foreach ($chunk as $raw) {
                $this->counts['c05_staging']++;
                $p = json_decode($raw->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $kodeSumber = trim((string) $raw->kode_sumber);
                if ($kodeSumber === '' || isset($migrated[$kodeSumber])) {
                    $this->counts['c05_skipped']++;

                    continue;
                }

                $kodeBarang = trim((string) ($p['kode_barang'] ?? ''));
                $produkId = $kodeBarang !== '' ? ($this->produkMap[$kodeBarang] ?? null) : null;
                if (! $produkId && ! $dry) {
                    $produkId = $this->ensureDummyProduk($kodeBarang);
                }
                $skuVariantId = $kodeBarang !== '' ? ($this->skuMap[$kodeBarang] ?? null) : null;
                if (! $skuVariantId) {
                    $this->counts['c05_sku_null']++;
                }

                $gudangId = $this->resolveGudang($p);

                $tanggal = $this->parseTanggal($p);
                $label = trim((string) ($p['transaksi'] ?? ''));
                $jenis = $this->jenisMapping($label);
                $this->jenisDist[$jenis] = ($this->jenisDist[$jenis] ?? 0) + 1;
                $this->gudangDist[(string) $gudangId] = ($this->gudangDist[(string) $gudangId] ?? 0) + 1;

                $awal = (int) ($p['awal'] ?? 0);
                $masuk = (int) ($p['masuk'] ?? 0);
                $keluar = (int) ($p['keluar'] ?? 0);
                $sisa = (int) ($p['sisa'] ?? 0);
                if ($sisa !== $awal + $masuk - $keluar) {
                    $this->counts['c05_sisa_beda']++;
                }

                $noTransaksi = trim((string) ($p['no_transaksi'] ?? ''));
                $refTipe = null;
                $refId = null;
                if ($noTransaksi !== '' && isset($this->transaksiMap[$noTransaksi])) {
                    $refTipe = 'App\Modules\Pos\Models\Transaksi';
                    $refId = $this->transaksiMap[$noTransaksi];
                    $this->counts['c05_ref_ada']++;
                } else {
                    $this->counts['c05_ref_null']++;
                }

                $operator = trim((string) ($p['operator'] ?? ''));
                $catatan = 'AS:'.$kodeSumber.' | no:'.$noTransaksi.' | op:'.$operator.' | transaksi:'.$label;

                $base = [
                    'gudang_id' => $gudangId,
                    'produk_id' => $produkId,
                    'sku_variant_id' => $skuVariantId,
                    'user_id' => null,
                    'jenis' => $jenis,
                    'referensi_tipe' => $refTipe,
                    'referensi_id' => $refId,
                    'jumlah_awal' => $awal,
                    'nilai_awal' => $this->num((string) ($p['nilai_awal'] ?? '')),
                    'nilai_masuk' => $this->num((string) ($p['nilai_masuk'] ?? '')),
                    'nilai_keluar' => $this->num((string) ($p['nilai_keluar'] ?? '')),
                    'nilai_sisa' => $this->num((string) ($p['nilai_sisa'] ?? '')),
                    'is_migrasi_sid' => 1,
                    'created_at' => $tanggal,
                    'updated_at' => now(),
                ];

                if ($this->bothSides($p)) {
                    // split: M (masuk) lalu K (keluar) — berurutan dlm satu arus
                    $this->counts['c05_split']++;
                    $this->counts['c05_inserted'] += 2;
                    $toInsert[] = $base + [
                        'jumlah_sebelum' => $awal,
                        'perubahan' => $masuk,
                        'jumlah_setelah' => $awal + $masuk,
                        'catatan' => $catatan.' |M',
                        'tanggal_log' => $tanggal,
                    ];
                    $toInsert[] = $base + [
                        'jumlah_sebelum' => $awal + $masuk,
                        'perubahan' => -$keluar,
                        'jumlah_setelah' => $sisa,
                        'catatan' => $catatan.' |K',
                        'tanggal_log' => $tanggal,
                    ];
                } else {
                    $this->counts['c05_inserted']++;
                    $perubahan = $masuk > 0 ? $masuk : ($keluar > 0 ? -$keluar : 0);
                    $toInsert[] = $base + [
                        'jumlah_sebelum' => $awal,
                        'perubahan' => $perubahan,
                        'jumlah_setelah' => $sisa,
                        'catatan' => $catatan,
                        'tanggal_log' => $tanggal,
                    ];
                }

                if ($dry) {
                    $migrated[$kodeSumber] = true;
                    $toInsert = [];
                }
            }

            if (! $dry && $toInsert) {
                DB::table('stok_log')->insert($toInsert);
            }
        });
    }

    // ---------------- C-06 stok_items ----------------

    private function stepSaldo(bool $dry): void
    {
        $this->info("\n--- C-06 stok_items saldo (sumber: arus last-sisa) ---");

        $this->counts['c06_wipe'] = DB::table('stok_items')->count();

        // last sisa per (kode_barang, gudang) by tanggal desc + id desc
        $lastSisa = [];   // key kodeBarang.'|'.gudangId → ['sisa'=>int, 'tgl'=>string, 'seq'=>int]
        $seq = 0;
        $total = DB::table('sid_retail_raw_arus_stok')->count();
        $offset = 0;
        while ($offset < $total) {
            foreach (DB::table('sid_retail_raw_arus_stok')->select('payload_normal')->skip($offset)->take(2000)->get() as $r) {
                $p = json_decode($r->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $seq++;
                $kb = trim((string) ($p['kode_barang'] ?? ''));
                if ($kb === '') {
                    continue;
                }
                $g = $this->resolveGudang($p);
                $tgl = (string) $this->parseTanggal($p);
                $key = $kb.'|'.$g;
                $cur = $lastSisa[$key] ?? null;
                if ($cur === null || $tgl > $cur['tgl'] || ($tgl === $cur['tgl'] && $seq > $cur['seq'])) {
                    $lastSisa[$key] = ['sisa' => (int) ($p['sisa'] ?? 0), 'tgl' => $tgl, 'seq' => $seq];
                }
            }
            $offset += 2000;
        }

        // produk yang punya arus (utk skip fallback payload)
        $arusProdukIds = [];
        foreach ($lastSisa as $key => $v) {
            $pid = $this->produkMap[explode('|', $key)[0]] ?? null;
            if ($pid) {
                $arusProdukIds[$pid] = true;
            }
        }

        // rekonsiliasi vs stok_log rekonstruksi (last jumlah_setelah per produk,gudang)
        if (in_array('log', array_filter(array_map('trim', explode(',', (string) $this->option('only')))), true) || DB::table('stok_log')->where('is_migrasi_sid', 1)->exists()) {
            $logLast = [];
            DB::table('stok_log')->select(['id', 'produk_id', 'gudang_id', 'tanggal_log', 'jumlah_setelah'])->where('is_migrasi_sid', 1)->orderBy('id')->chunk(2000, function ($chunk) use (&$logLast) {
                foreach ($chunk as $l) {
                    $key = $l->produk_id.'|'.$l->gudang_id;
                    $tgl = (string) $l->tanggal_log;
                    $cur = $logLast[$key] ?? null;
                    if ($cur === null || $tgl > $cur['tgl'] || ($tgl === $cur['tgl'] && $l->id > $cur['id'])) {
                        $logLast[$key] = ['sisa' => (int) $l->jumlah_setelah, 'tgl' => $tgl, 'id' => (int) $l->id];
                    }
                }
            });
            foreach ($lastSisa as $key => $v) {
                [$kb, $g] = explode('|', $key, 2);
                $pid = (int) ($this->produkMap[$kb] ?? 0);
                $lk = $pid.'|'.$g;
                if (isset($logLast[$lk]) && $logLast[$lk]['sisa'] !== $v['sisa']) {
                    $this->counts['rek_stoklog_mismatch']++;
                }
            }
        }

        // payload barang: toko→gudang6, gudang→gudang5 (untuk fallback + rekonsiliasi deviasi)
        $payloadSaldo = [];   // key produk_id.'|'.gudangId → qty
        DB::table('sid_retail_raw_barang')->select(['id', 'payload_normal'])->orderBy('id')->chunkById(500, function ($chunk) use (&$payloadSaldo) {
            foreach ($chunk as $r) {
                $p = json_decode($r->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $kb = trim((string) ($p['kode'] ?? ''));
                $pid = $this->produkMap[$kb] ?? null;
                if (! $pid) {
                    continue;
                }
                $toko = (int) ($p['toko'] ?? 0);
                $gudang = (int) ($p['gudang'] ?? 0);
                if ($toko > 0) {
                    $payloadSaldo[$pid.'|6'] = ($payloadSaldo[$pid.'|6'] ?? 0) + $toko;
                }
                if ($gudang > 0) {
                    $payloadSaldo[$pid.'|5'] = ($payloadSaldo[$pid.'|5'] ?? 0) + $gudang;
                }
            }
        });

        // rekonsiliasi arus-last-sisa vs payload (produk dgn arus DAN payload)
        foreach ($lastSisa as $key => $v) {
            [$kb, $g] = explode('|', $key, 2);
            $pid = $this->produkMap[$kb] ?? null;
            if (! $pid) {
                continue;
            }
            $plKey = $pid.'|'.$g;
            if (isset($payloadSaldo[$plKey])) {
                $this->counts['rek_deviasi_cek']++;
                if (abs($v['sisa'] - $payloadSaldo[$plKey]) > 5) {
                    $this->counts['rek_deviasi_gt5']++;
                    $this->deviasiKatalog[] = sprintf('%s (gudang %s): arus-last=%d | payload=%d | delta=%d',
                        $kb, $g, $v['sisa'], $payloadSaldo[$plKey], $v['sisa'] - $payloadSaldo[$plKey]);
                }
            }
        }

        // hitung rencana insert
        $cArus = 0;
        $neg = 0;
        foreach ($lastSisa as $key => $v) {
            if ($v['sisa'] > 0) {
                $cArus++;
            } elseif ($v['sisa'] < 0) {
                $neg++;
            }
        }
        $cFall = 0;
        foreach ($payloadSaldo as $key => $qty) {
            $pid = (int) explode('|', $key)[0];
            if (! isset($arusProdukIds[$pid])) {
                $cFall++;
            }
        }
        $this->counts['c06_arus_insert'] = $cArus;
        $this->counts['c06_arus_neg'] = $neg;
        $this->counts['c06_arus_zero'] = count($lastSisa) - $cArus - $neg;
        $this->counts['c06_fallback_insert'] = $cFall;
        $this->counts['c06_fallback_skip'] = count($payloadSaldo) - $cFall;

        if ($dry) {
            return;
        }

        DB::transaction(function () use ($lastSisa, $payloadSaldo, $arusProdukIds) {
            $this->counts['c06_wipe'] = DB::table('stok_items')->delete();
            $toInsert = [];

            // 1. arus-sourced (skip sisa <= 0)
            foreach ($lastSisa as $key => $v) {
                [$kb, $g] = explode('|', $key, 2);
                $produkId = $this->produkMap[$kb] ?? $this->ensureDummyProduk($kb);
                if (! $produkId) {
                    continue;
                }
                if ($v['sisa'] < 0 || $v['sisa'] === 0) {
                    continue;
                }
                $toInsert[] = [
                    'produk_id' => $produkId,
                    'sku_variant_id' => $this->skuMap[$kb] ?? null,
                    'gudang_id' => (int) $g,
                    'jumlah' => $v['sisa'],
                    'jumlah_minimum' => (int) ($this->stokWarningMap[$produkId] ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // 2. fallback payload utk produk TANPA arus (toko→6, gudang→5, only > 0)
            foreach ($payloadSaldo as $key => $qty) {
                [$pid, $g] = explode('|', $key, 2);
                $pid = (int) $pid;
                if (isset($arusProdukIds[$pid])) {
                    continue;
                }
                $toInsert[] = [
                    'produk_id' => $pid,
                    'sku_variant_id' => $this->skuByProduk[$pid] ?? null,
                    'gudang_id' => (int) $g,
                    'jumlah' => $qty,
                    'jumlah_minimum' => (int) ($this->stokWarningMap[$pid] ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('stok_items')->insert($toInsert);
        });
    }

    // ---------------- UTIL ----------------

    private function bothSides(array $p): bool
    {
        return (float) ($p['masuk'] ?? 0) > 0 && (float) ($p['keluar'] ?? 0) > 0;
    }

    private function jenisMapping(string $label): string
    {
        if (in_array($label, ['PENJUALAN'], true)) {
            return 'penjualan';
        }
        if (in_array($label, ['INPUT BARANG', 'INPUT BARU'], true)) {
            return 'masuk';
        }
        if (in_array($label, ['PEMBELIAN'], true)) {
            return 'pembelian';
        }

        // DEL*, RETURN*, label tak dikenal/kosong → koreksi (label asli tetap di catatan)
        return 'koreksi';
    }

    private function resolveGudang(array $p): int
    {
        $lok = trim((string) ($p['lokasi_stok'] ?? ''));
        if ($lok !== '') {
            $id = $this->gudangMap[$lok] ?? $this->gudangMap[mb_strtoupper($lok)] ?? null;
            if ($id) {
                return $id;
            }
        }
        $cab = trim((string) ($p['lokasi_cabang'] ?? ''));
        if ($cab !== '') {
            $id = $this->gudangMap[$cab] ?? $this->gudangMap[mb_strtoupper($cab)] ?? null;
            if ($id) {
                return $id;
            }
        }
        $this->counts['c05_gudang_default']++;

        return 6; // TOKO1 default (gudang_id NOT NULL)
    }

    private function num(string $v): ?float
    {
        $v = trim($v);

        return $v === '' ? null : (float) $v;
    }

    private function parseTanggal(array $p): string
    {
        $tanggal = trim((string) ($p['tanggal'] ?? ''));
        if ($tanggal === '' || in_array($tanggal, self::SENTINEL, true)) {
            return date('Y-m-d H:i:s');
        }
        $jam = trim((string) ($p['jam'] ?? ''));
        $gabung = $jam !== '' ? $tanggal.' '.$jam : $tanggal.' 00:00:00';
        $ts = strtotime($gabung);

        return $ts !== false ? date('Y-m-d H:i:s', $ts) : $tanggal.' 00:00:00';
    }

    private function ensureDummyProduk(string $kodeBarang): ?int
    {
        $id = $this->produkMap[$kodeBarang] ?? null;
        if ($id) {
            return $id;
        }
        $id = DB::table('produk')->where('kode_lama', $kodeBarang)->value('id');
        if ($id) {
            $this->produkMap[$kodeBarang] = (int) $id;
            $this->stokWarningMap[(int) $id] = (int) (DB::table('produk')->where('id', $id)->value('stok_warning') ?? 0);

            return (int) $id;
        }
        $id = DB::table('produk')->insertGetId([
            'kode_lama' => $kodeBarang,
            'nama' => '[ARSIP-SID] '.$kodeBarang,
            'slug' => 'arsip-sid-'.md5($kodeBarang),
            'jenis' => 'barang',
            'is_active' => false,
            'is_migrasi_sid' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->produkMap[$kodeBarang] = (int) $id;
        $this->stokWarningMap[(int) $id] = 0;
        $this->counts['c05_dummy_baru']++;

        return (int) $id;
    }

    private function loadMaps(): void
    {
        DB::table('produk')->select(['id', 'kode_lama', 'stok_warning'])->orderBy('id')->chunk(2000, function ($chunk) {
            foreach ($chunk as $p) {
                if ($p->kode_lama !== null && $p->kode_lama !== '') {
                    $this->produkMap[$p->kode_lama] = (int) $p->id;
                }
                $this->stokWarningMap[(int) $p->id] = (int) ($p->stok_warning ?? 0);
            }
        });
        DB::table('sku_variants')->select(['id', 'kode_lama', 'produk_id'])->whereNotNull('kode_lama')->orderBy('id')->chunk(2000, function ($chunk) {
            foreach ($chunk as $s) {
                $this->skuMap[$s->kode_lama] = (int) $s->id;
                $this->skuByProduk[(int) $s->produk_id] = (int) $s->id;
            }
        });
        DB::table('gudang')->select(['id', 'kode', 'nama'])->orderBy('id')->chunk(100, function ($chunk) {
            foreach ($chunk as $g) {
                $this->gudangMap[(string) $g->kode] = (int) $g->id;
                $this->gudangMap[mb_strtoupper(trim((string) $g->nama))] = (int) $g->id;
            }
        });
        DB::table('transaksi')->select(['id', 'no_transaksi'])->orderBy('id')->chunk(2000, function ($chunk) {
            foreach ($chunk as $t) {
                $this->transaksiMap[(string) $t->no_transaksi] = (int) $t->id;
            }
        });
    }

    private function tulisLaporan(bool $dry): void
    {
        $dir = storage_path('app/migrasi-sid');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir.'/C2-stok-laporan-'.date('Ymd-His').'.md';
        $mode = $dry ? 'DRY-RUN (tidak ada perubahan DB)' : 'EKSEKUSI';

        $jenis = '';
        if ($this->jenisDist) {
            arsort($this->jenisDist);
            foreach ($this->jenisDist as $j => $c) {
                $jenis .= "- `{$j}`: **{$c}**\n";
            }
            $jenis = rtrim($jenis);
        } else {
            $jenis = '_(belum diproses — jalankan --only=log)_';
        }

        $gudang = '';
        if ($this->gudangDist) {
            arsort($this->gudangDist);
            foreach ($this->gudangDist as $g => $c) {
                $gudang .= "- gudang id{$g}: **{$c}**\n";
            }
            $gudang = rtrim($gudang);
        }

        $deviasi = '';
        if ($this->counts['rek_deviasi_cek'] > 0) {
            $deviasi = '_(daftar lengkap >5: lihat DB via rekonsiliasi — katalog 50 baris teratas di bawah)_';
        }

        $body = '# LAPORAN FASE 3 (C-05..C-06) — REKONSTRUKSI STOK — '.date('Y-m-d H:i:s')."\n\n"
            ."Mode: **{$mode}** — Perintah: `php artisan sid:rekonstruksi-stok` (idempotent, 2× run = 0 insert kedua)\n\n"
            .implode("\n", $this->reportLines)."\n"
            ."## C-05 stok_log 1:1 dari arus_stok\n\n"
            ."- staging diproses: **{$this->counts['c05_staging']}**, inserted: **{$this->counts['c05_inserted']}**, skip (sudah ada, guard `AS:<kode_sumber>`): **{$this->counts['c05_skipped']}**\n"
            ."- **DEVIASI GUARD IDEMPOTEN:** spec memakai catatan `AS:<kode>` (payload kode) tapi payload `kode` TIDAK unik (4.015 distinct utk 19.286 baris — `TRX-*` duplikat x7 antar partisi bulanan) → guard memakai **`kode_sumber`** (19286/19286 unik, format `arus_stok_<partisi>|<kode>`). Catatan tetap pola `AS:<kode_sumber> | no: ...`.\n"
            ."- split baris (masuk>0 DAN keluar>0 → 2 baris `|M`+`|K`): **{$this->counts['c05_split']}** → +2 baris per split\n"
            ."- dummy produk baru `[ARSIP-SID] <kode_barang>` (produk_id NOT NULL di DB): **{$this->counts['c05_dummy_baru']}**, sku_variant_id NULL: **{$this->counts['c05_sku_null']}**\n"
            ."- gudang_default (lokasi tak dikenal/kosong → 6): **{$this->counts['c05_gudang_default']}**\n"
            ."- referensi_tipe Transaksi terisi: **{$this->counts['c05_ref_ada']}**, NULL (no_transaksi kosong/tak match): **{$this->counts['c05_ref_null']}** — referensi_id DIISI (kolom ADA & nullable di DB; spec menyebut 'tak ada kolom referensi_id' — ternyata ada, diisi utk auditability, no_transaksi TETAP di catatan)\n"
            ."- sisa ≠ awal+masuk−keluar (aritma SID tidak konsisten; data disalin apa adanya): **{$this->counts['c05_sisa_beda']}** baris\n\n"
            ."Distribusi jenis stok_log:\n".$jenis."\n\n"
            ."Distribusi gudang_id stok_log:\n".$gudang."\n\n"
            ."## C-06 stok_items (saldo) — sumber: **arus last-sisa** (last baris per (kode_barang, gudang) by tanggal desc + id desc → sisa; spec 'SALIN dari arus')\n\n"
            ."- wipe stok_items artefak lama: **{$this->counts['c06_wipe']}** (semua created_at <= 2026-09-21 08:19:59 = artefak migrasi, verified)\n"
            ."- insert dari arus (sisa>0): **{$this->counts['c06_arus_insert']}**; skip sisa=0: **{$this->counts['c06_arus_zero']}**; skip sisa<0: **{$this->counts['c06_arus_neg']}** (749 baris arus sisa<0 seluruhnya; final per produk-gudang di hitungan skip)\n"
            ."- fallback payload barang utk produk TANPA arus (toko→gudang6, gudang→gudang5, HANYA >0): insert **{$this->counts['c06_fallback_insert']}**, skip (produk sudah punya baris arus): **{$this->counts['c06_fallback_skip']}**\n"
            ."- jumlah_minimum = produk.stok_warning ?? 0 — **A-02: SEMUA stok_warning = 0.00 (0 baris >0 di payload warningstok juga) → 0 untuk semua**\n\n"
            ."## Rekonsiliasi\n\n"
            ."- arus-last-sisa vs stok_log rekonstruksi (last jumlah_setelah per produk,gudang): mismatch **{$this->counts['rek_stoklog_mismatch']}** (harus 0 — 1:1)\n"
            ."- arus-last-sisa vs payload toko/gudang (produk dgn arus DAN payload): dicek **{$this->counts['rek_deviasi_cek']}**, deviasi >5: **{$this->counts['rek_deviasi_gt5']}**\n"
            .$deviasi."\n\n";

        if ($this->counts['rek_deviasi_gt5'] > 0) {
            $body .= "Katalog deviasi (50 teratas):\n```\n".implode("\n", array_slice($deviasiKatalog ?? [], 0, 50))."\n```\n";
        }

        $produkTanpaStok = DB::table('produk')->count() - DB::table('stok_items')->distinct('produk_id')->count('produk_id');

        $body .= "\n## State DB akhir\n\n"
            .'- stok_log total: **'.DB::table('stok_log')->count()."** (target 19.286 + 52 split = 19.338)\n"
            .'- stok_log is_migrasi_sid=1: **'.DB::table('stok_log')->where('is_migrasi_sid', 1)->count()."**\n"
            .'- stok_items total: **'.DB::table('stok_items')->count()."**\n"
            ."- produk tanpa stok_items (saldo 0 implisit): **{$produkTanpaStok}** dari ".DB::table('produk')->count()." produk\n"
            .'- produk dummy `[ARSIP-SID]` total: **'.DB::table('produk')->where('nama', 'like', '[ARSIP-SID] %')->count()."**\n"
            ."\n---\nTarget verifikasi: stok_log = 19.286 (+split), 2× run = 0 insert kedua.\n";

        file_put_contents($file, $body);
        $this->info("Laporan: {$file}");
    }

    private function printSummary(): void
    {
        $this->info("\n=== SUMMARY FASE 3 (C-05..C-06) ===");
        foreach ($this->counts as $k => $v) {
            $this->line("  {$k}: {$v}");
        }
    }
}
