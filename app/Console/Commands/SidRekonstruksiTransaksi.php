<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [MIGRASI-SID FASE 3] Rekonstruksi transaksi (C-02..C-04) — idempotent, chunked.
 *
 * C-02 CLEANUP artefak migrasi lama (hanya baris R43-/map transaksi/stok_log artefak):
 *   - transaksi_item WHERE transaksi_id IN (R43-*)  → target 10.273 (aktual 28.491, audit 09-22)
 *   - transaksi      WHERE no_transaksi LIKE 'R43-%' → target 18.786
 *   - sid_import_map WHERE entity_type='transaksi'   → target 6.262 (stale pointer artefak)
 *   - stok_log       SEMUA (120.167 artefak; guard: max(created_at) < '2026-09-21 12:00:00',
 *     bila ada baris >= itu → JANGAN hapus, lapor)
 *   - harga_tier TIDAK disentuh (19.104 = 6.368 produk × 3 tier — BUKAN artefak, §2.0 item 6
 *     sudah diverifikasi; rekonstruksi dari hrgpergroup = fase lanjutan terpisah).
 *
 * C-03 REKONSTRUKSI transaksi ← sid_retail_raw_penjualan (6.262 header):
 *   no_transaksi=kode, cabang_id=1, sumber='pos', jenis='penjualan',
 *   tanggal_transaksi = tanggal+jam SID (jam 12 jam '11:39:33 AM' aman via strtotime),
 *   created_at=tanggal_transaksi (historis), updated_at=now, pelanggan via kode_lama →
 *   nama → NULL, kasir_id=NULL, gudang_id dari lokasistok item pertama faktur
 *   (header penjualan TIDAK punya kolom lokasistok — source = item), metode_bayar mapping
 *   visa/card/cashout, status lunas, sid_detail subset, is_migrasi_sid=true,
 *   sid_import_map upsert (kode_sumber=kode, tabel_sumber='penjualan', entity_type='transaksi').
 *
 * C-04 transaksi_item ← sid_retail_raw_itempenjualan (10.273) group by payload.kode:
 *   produk_id via kode_lama; TIDAK ketemu → dummy produk '[ARSIP-SID] <nama>' (produk_id NOT
 *   NULL di DB), sku_variant_id via kode_lama (nullable), is_migrasi_sid=true.
 *
 * Guard idempotent: no_transaksi unique → skip bila sudah ada; item di-skip bila
 * transaksi sudah punya item. 2x run → 0 insert run kedua.
 */
class SidRekonstruksiTransaksi extends Command
{
    protected $signature = 'sid:rekonstruksi-transaksi {--dry-run : hanya hitung & laporan, tanpa perubahan DB} {--only=cleanup,transaksi : cleanup|transaksi}';

    protected $description = 'FASE 3 (C-02..C-04): cleanup artefak + rekonstruksi transaksi/item dari header penjualan SID';

    private array $counts = [
        'c02_ti_ada' => 0, 'c02_ti_delete' => 0,
        'c02_t_ada' => 0, 'c02_t_delete' => 0,
        'c02_map_ada' => 0, 'c02_map_delete' => 0,
        'c02_stok_ada' => 0, 'c02_stok_max' => '', 'c02_stok_delete' => 0, 'c02_stok_blocked' => 0,
        'c03_staging' => 0, 'c03_inserted' => 0, 'c03_skipped' => 0,
        'c03_pelanggan_kode' => 0, 'c03_pelanggan_nama' => 0, 'c03_pelanggan_null' => 0,
        'c03_pelanggan_nama_unmatched' => 0, 'c03_gudang_null' => 0,
        'c03_map_upsert' => 0, 'c03_tanggal_null' => 0,
        'c04_staging' => 0, 'c04_inserted' => 0, 'c04_skipped_faktur' => 0,
        'c04_orphan_faktur' => 0, 'c04_dummy_produk_baru' => 0, 'c04_sku_null' => 0,
        'rek_faktur' => 0, 'rek_deviasi' => 0, 'rek_tanpa_item' => 0,
    ];

    private array $reportLines = [];

    private array $pelangganNamaUnmatched = [];

    private array $deviasiSamples = [];

    private array $sampleTransaksi = [];

    private const SENTINEL = ['1899-12-30', '0000-00-00', '1900-01-01', ''];

    /** @var array<string,int> */
    private array $pelangganMap = [];

    /** @var array<string,int> nama UPPER(TRIM) → id (first wins) */
    private array $pelangganNamaMap = [];

    /** @var array<string,int> gudang kode → id */
    private array $gudangMap = [];

    /** @var array<string,string> faktur kode → lokasistok item pertama */
    private array $lokasiPerFaktur = [];

    /** @var array<string,int> kode_lama produk → id */
    private array $produkMap = [];

    /** @var array<string,int> kode_lama sku_variants → id */
    private array $skuMap = [];

    /** @var array<string,array{id:int,tanggal:?string,has_items:bool}> no_transaksi → info */
    private array $transaksiMap = [];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));

        if ($dry) {
            $this->warn('DRY-RUN: tidak ada perubahan DB');
        }

        if (in_array('cleanup', $only, true)) {
            $this->stepCleanup($dry);
        }
        if (in_array('transaksi', $only, true)) {
            $this->loadMaps();
            $this->stepTransaksi($dry);
            $this->loadTransaksiItems();
            $this->stepTransaksiItem($dry);
            $this->stepRekonsiliasi();
            $this->stepSampleTransaksi();
        }

        $this->tulisLaporan($dry);
        $this->printSummary();

        return 0;
    }

    // ---------------- C-02 CLEANUP ----------------

    private function stepCleanup(bool $dry): void
    {
        $this->info("\n--- C-02 Cleanup artefak (hanya baris migrasi lama) ---");

        // artefak = transaksi R43-* TANPA tanggal_transaksi (migrasi lama: created_at=waktu import,
        // tanggal_transaksi NULL). Baris hasil rekonstruksi C-03 SEMUA punya tanggal_transaksi →
        // rerun cleanup = 0 delete (idempotent, tidak menimpa data rekonstruksi).
        $artifact = DB::table('transaksi')->where('no_transaksi', 'like', 'R43-%')->whereNull('tanggal_transaksi');

        $this->counts['c02_ti_ada'] = (clone $artifact)->join('transaksi_item', 'transaksi_item.transaksi_id', '=', 'transaksi.id')->count();
        $this->counts['c02_t_ada'] = (clone $artifact)->count();
        $this->counts['c02_map_ada'] = DB::table('sid_import_map')->where('entity_type', 'transaksi')->count();
        $this->counts['c02_stok_ada'] = DB::table('stok_log')->count();
        $this->counts['c02_stok_max'] = (string) (DB::table('stok_log')->max('created_at') ?? '');

        $guard = $this->counts['c02_stok_max'] !== ''
            && strtotime($this->counts['c02_stok_max']) >= strtotime('2026-09-21 12:00:00');

        // dump count per tahap SEBELUM delete
        $this->reportLines[] = '### C-02 — Count sebelum delete';
        $this->reportLines[] = '';
        $this->reportLines[] = "- transaksi_item (transaksi R43-* artefak): **{$this->counts['c02_ti_ada']}**";
        $this->reportLines[] = "- transaksi R43-* artefak: **{$this->counts['c02_t_ada']}**";
        $this->reportLines[] = "- sid_import_map entity_type='transaksi': **{$this->counts['c02_map_ada']}** (hapus hanya yang stale → entity_id tidak ada di transaksi)";
        $this->reportLines[] = "- stok_log total: **{$this->counts['c02_stok_ada']}** (max created_at: `{$this->counts['c02_stok_max']}`; guard < 2026-09-21 12:00:00 → ".($guard ? 'GAGAL — JANGAN HAPUS' : 'AMAN').')';
        $this->reportLines[] = '';

        if ($dry) {
            $this->counts['c02_ti_delete'] = $this->counts['c02_ti_ada'];
            $this->counts['c02_t_delete'] = $this->counts['c02_t_ada'];
            $this->counts['c02_map_delete'] = DB::table('sid_import_map')->where('entity_type', 'transaksi')
                ->whereNotIn('entity_id', DB::table('transaksi')->select('id'))->count();
            $this->counts['c02_stok_delete'] = $guard ? 0 : $this->counts['c02_stok_ada'];

            return;
        }

        // 1+2. item & transaksi artefak
        DB::transaction(function () use ($artifact) {
            $sub = (clone $artifact)->pluck('id');
            $this->counts['c02_ti_delete'] = DB::table('transaksi_item')->whereIn('transaksi_id', $sub)->delete();
            $this->counts['c02_t_delete'] = (clone $artifact)->delete();
            // 3. map stale (entity_id tidak lagi menunjuk transaksi apa pun)
            $this->counts['c02_map_delete'] = DB::table('sid_import_map')->where('entity_type', 'transaksi')
                ->whereNotIn('entity_id', DB::table('transaksi')->select('id'))->delete();
        });

        // 4. stok_log SEMUA — hanya bila guard lolos
        if ($guard) {
            $this->counts['c02_stok_blocked'] = 1;
            $this->warn('BLOKIR: stok_log punya baris >= 2026-09-21 12:00:00 — tidak dihapus. Lihat laporan.');
        } else {
            DB::transaction(function () {
                $this->counts['c02_stok_delete'] = DB::table('stok_log')->delete();
            });
        }
    }

    // ---------------- C-03 TRANSAKSI ----------------

    private function stepTransaksi(bool $dry): void
    {
        $this->info("\n--- C-03 Rekonstruksi transaksi ← penjualan (6.262) ---");

        // map existing (idempotent guard: no_transaksi sudah ada → skip)
        DB::table('transaksi')->select(['id', 'no_transaksi', 'tanggal_transaksi'])
            ->orderBy('id')->chunk(2000, function ($chunk) {
                foreach ($chunk as $t) {
                    $this->transaksiMap[$t->no_transaksi] = [
                        'id' => (int) $t->id,
                        'tanggal' => $t->tanggal_transaksi,
                        'has_items' => false,
                    ];
                }
            });

        DB::table('sid_retail_raw_penjualan')->orderBy('id')->chunkById(100, function ($chunk) use ($dry) {
            foreach ($chunk as $raw) {
                $this->counts['c03_staging']++;
                $p = json_decode($raw->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $kode = trim((string) ($p['kode'] ?? $raw->kode_sumber));
                if (isset($this->transaksiMap[$kode])) {
                    $this->counts['c03_skipped']++;

                    continue;
                }

                $tanggal = $this->parseTanggalTransaksi($p);
                if ($tanggal === null) {
                    $this->counts['c03_tanggal_null']++;
                }

                // pelanggan: kode_lama → nama → NULL
                $pelangganKode = trim((string) ($p['pelanggan'] ?? ''));
                $namaPelanggan = trim((string) ($p['nama_pelanggan'] ?? ''));
                $pelangganId = null;
                if ($pelangganKode !== '') {
                    $pelangganId = $this->pelangganMap[$pelangganKode] ?? null;
                    if ($pelangganId) {
                        $this->counts['c03_pelanggan_kode']++;
                    }
                }
                if (! $pelangganId && $namaPelanggan !== '' && mb_strtoupper($namaPelanggan) !== 'PELANGGAN UMUM') {
                    $pelangganId = $this->pelangganNamaMap[mb_strtoupper($namaPelanggan)] ?? null;
                    if ($pelangganId) {
                        $this->counts['c03_pelanggan_nama']++;
                    } else {
                        $this->counts['c03_pelanggan_nama_unmatched']++;
                        $this->pelangganNamaUnmatched[$namaPelanggan] = ($this->pelangganNamaUnmatched[$namaPelanggan] ?? 0) + 1;
                    }
                }
                if (! $pelangganId) {
                    $this->counts['c03_pelanggan_null']++;
                }

                // gudang: lokasistok item pertama faktur (header penjualan tidak punya kolom lokasistok)
                $lokasi = trim((string) ($this->lokasiPerFaktur[$kode] ?? ''));
                $gudangId = $lokasi !== '' ? ($this->gudangMap[$lokasi] ?? $this->gudangMap[mb_strtoupper($lokasi)] ?? null) : null;
                if (! $gudangId) {
                    $this->counts['c03_gudang_null']++;
                }

                $data = [
                    'no_transaksi' => $kode,
                    'cabang_id' => 1,
                    'sumber' => 'pos',
                    'jenis' => 'penjualan',
                    'tanggal_transaksi' => $tanggal,
                    'pelanggan_id' => $pelangganId,
                    'kasir_id' => null,
                    'gudang_id' => $gudangId,
                    'subtotal' => (float) ($p['subtotal'] ?? 0),
                    'diskon_persen' => (float) ($p['diskon'] ?? 0),
                    'diskon_nominal' => (float) ($p['diskon_rupiah'] ?? 0),
                    'pajak_nominal' => (float) ($p['tax_rupiah'] ?? 0),
                    'total_akhir' => (float) ($p['jumlah'] ?? 0),
                    'metode_bayar' => $this->metodeBayar($p),
                    'jumlah_bayar' => (float) ($p['bayar'] ?? 0),
                    'kembalian' => (float) ($p['kembali'] ?? 0),
                    'status' => $this->statusLunas($p),
                    'catatan' => $this->emptyToNull(trim((string) ($p['keterangan'] ?? ''))),
                    'sid_detail' => $this->sidDetail($p, $lokasi),
                    'is_migrasi_sid' => true,
                    'created_at' => $tanggal ?? now(),
                    'updated_at' => now(),
                ];

                if ($dry) {
                    $this->counts['c03_inserted']++;
                    $this->transaksiMap[$kode] = ['id' => 0, 'tanggal' => $tanggal, 'has_items' => false];

                    continue;
                }

                DB::transaction(function () use ($data, $kode, &$transaksiId) {
                    $transaksiId = DB::table('transaksi')->insertGetId($data);
                    DB::table('sid_import_map')->updateOrInsert(
                        ['kode_sumber' => $kode, 'tabel_sumber' => 'penjualan'],
                        ['entity_type' => 'transaksi', 'entity_id' => $transaksiId, 'updated_at' => now(), 'created_at' => now()]
                    );
                });
                $this->counts['c03_inserted']++;
                $this->counts['c03_map_upsert']++;
                $this->transaksiMap[$kode] = ['id' => (int) $transaksiId, 'tanggal' => $data['tanggal_transaksi'], 'has_items' => false];
            }
        });
    }

    // ---------------- C-04 TRANSAKSI_ITEM ----------------

    private function stepTransaksiItem(bool $dry): void
    {
        $this->info("\n--- C-04 transaksi_item ← itempenjualan (10.273) ---");

        DB::table('sid_retail_raw_itempenjualan')->orderBy('id')->chunkById(300, function ($chunk) use ($dry) {
            foreach ($chunk as $raw) {
                $this->counts['c04_staging']++;
                $p = json_decode($raw->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $kode = trim((string) ($p['kode'] ?? ''));
                $info = $this->transaksiMap[$kode] ?? null;
                if (! $info) {
                    $this->counts['c04_orphan_faktur']++;

                    continue;
                }
                if ($info['has_items']) {
                    $this->counts['c04_skipped_faktur']++;

                    continue;
                }

                $kodeBarang = trim((string) ($p['kode_barang'] ?? ''));
                $namaBarang = trim((string) ($p['nama_barang'] ?? ''));
                $produkId = $kodeBarang !== '' ? ($this->produkMap[$kodeBarang] ?? null) : null;
                if (! $produkId && ! $dry) {
                    $produkId = $this->ensureDummyProduk($kodeBarang, $namaBarang);
                }
                $skuVariantId = $kodeBarang !== '' ? ($this->skuMap[$kodeBarang] ?? null) : null;
                if (! $skuVariantId) {
                    $this->counts['c04_sku_null']++;
                }

                $data = [
                    'transaksi_id' => $info['id'],
                    'produk_id' => $produkId,
                    'sku_variant_id' => $skuVariantId,
                    'jumlah' => (int) ($p['qty'] ?? 0),
                    'harga_satuan' => (float) ($p['harga'] ?? 0),
                    'diskon_persen' => (float) ($p['diskon'] ?? 0),
                    'diskon_nominal' => (float) ($p['diskon_rupiah'] ?? 0),
                    'subtotal' => (float) ($p['subtotal'] ?? 0),
                    'hpp' => (float) ($p['hpp'] ?? 0),
                    'is_migrasi_sid' => true,
                    'created_at' => $info['tanggal'] ?? now(),
                    'updated_at' => now(),
                ];

                if ($dry) {
                    $this->counts['c04_inserted']++;

                    continue;
                }
                DB::table('transaksi_item')->insert($data);
                $this->counts['c04_inserted']++;
            }
        });
    }

    private function stepRekonsiliasi(): void
    {
        $this->info("\n--- Rekonsiliasi per faktur (Σ item.subtotal vs header.jumlah) ---");

        $rows = DB::table('transaksi as t')
            ->leftJoin(DB::raw('(SELECT transaksi_id, SUM(subtotal) s, COUNT(*) n FROM transaksi_item GROUP BY transaksi_id) as ag'),
                'ag.transaksi_id', '=', 't.id')
            ->where('t.is_migrasi_sid', true)
            ->select(['t.id', 't.no_transaksi', 't.total_akhir', 'ag.s as item_sum', 'ag.n as item_cnt'])
            ->orderBy('t.id')->get();

        $deviasi = 0;
        $tanpaItem = 0;
        foreach ($rows as $r) {
            $this->counts['rek_faktur']++;
            $sum = (float) ($r->item_sum ?? 0);
            if ($r->item_cnt === null) {
                $tanpaItem++;

                continue;
            }
            if (abs($sum - (float) $r->total_akhir) > 0.01) {
                $deviasi++;
                $this->deviasiSamples[] = sprintf('%s: header=%s | Σitem=%s | diff=%s',
                    $r->no_transaksi, rtrim(rtrim(number_format((float) $r->total_akhir, 2), '0'), '.'), $sum,
                    rtrim(rtrim(number_format(abs($sum - (float) $r->total_akhir), 2), '0'), '.'));
            }
        }
        $this->counts['rek_deviasi'] = $deviasi;
        $this->counts['rek_tanpa_item'] = $tanpaItem;
    }

    private function stepSampleTransaksi(): void
    {
        $samples = DB::table('transaksi')->where('is_migrasi_sid', true)
            ->whereNotNull('sid_detail')->inRandomOrder()->limit(3)->get();
        foreach ($samples as $t) {
            $items = DB::table('transaksi_item')->where('transaksi_id', $t->id)->get();
            $this->sampleTransaksi[] = [
                'no_transaksi' => $t->no_transaksi,
                'tanggal_transaksi' => $t->tanggal_transaksi,
                'pelanggan_id' => $t->pelanggan_id,
                'status' => $t->status,
                'metode_bayar' => $t->metode_bayar,
                'total_akhir' => (float) $t->total_akhir,
                'sid_detail' => json_decode((string) $t->sid_detail, true),
                'items' => $items->map(fn ($i) => ['produk_id' => $i->produk_id, 'jumlah' => $i->jumlah, 'harga_satuan' => (float) $i->harga_satuan, 'subtotal' => (float) $i->subtotal])->all(),
            ];
        }
    }

    // ---------------- UTIL ----------------

    private function metodeBayar(array $p): string
    {
        // spec C-03: visa non-kosong → 'kartu'; card → 'kartu'; cashout → 'tunai'; default 'tunai'
        if (trim((string) ($p['visa'] ?? '')) !== '') {
            return 'kartu';
        }
        if ((float) ($p['card'] ?? 0) > 0) {
            return 'kartu';
        }
        if ((float) ($p['cashout'] ?? 0) > 0) {
            return 'tunai';
        }

        return 'tunai';
    }

    private function statusLunas(array $p): string
    {
        $lunas = $p['lunas'] ?? null;
        if ($lunas === true || strtolower((string) $lunas) === 'true') {
            return 'lunas';
        }

        return 'belum_lunas';
    }

    private function parseTanggalTransaksi(array $p): ?string
    {
        $tanggal = trim((string) ($p['tanggal'] ?? ''));
        if ($tanggal === '' || in_array($tanggal, self::SENTINEL, true)) {
            return null;
        }
        $jam = trim((string) ($p['jam'] ?? ''));
        $gabung = $jam !== '' ? $tanggal.' '.$jam : $tanggal.' 00:00:00';
        $ts = strtotime($gabung);

        return $ts !== false ? date('Y-m-d H:i:s', $ts) : $tanggal.' 00:00:00';
    }

    private function sidDetail(array $p, string $lokasi): ?string
    {
        $sub = [];
        foreach (['jenis', 'operator', 'kasir', 'sales', 'spg', 'shif', 'po', 'pr',
            'no_faktur_pajak', 'kode_kas', 'status_pengiriman', 'biayakirim', 'jasakirim',
            'member', 'piutang'] as $k) {
            $v = $p[$k] ?? null;
            if ($v === null || (is_string($v) && trim($v) === '')) {
                continue;
            }
            $sub[$k === 'jenis' ? 'jenis_sid' : $k] = $v;
        }
        if ($lokasi !== '') {
            $sub['lokasistok'] = $lokasi;
        }
        if (! $sub) {
            return null;
        }

        return json_encode($sub, JSON_UNESCAPED_UNICODE);
    }

    private function ensureDummyProduk(string $kodeBarang, string $namaBarang): ?int
    {
        $id = $this->produkMap[$kodeBarang] ?? null;
        if ($id) {
            return $id;
        }
        $id = DB::table('produk')->where('kode_lama', $kodeBarang)->value('id');
        if ($id) {
            $this->produkMap[$kodeBarang] = (int) $id;

            return (int) $id;
        }
        $nama = $namaBarang !== '' ? $namaBarang : $kodeBarang;
        $id = DB::table('produk')->insertGetId([
            'kode_lama' => $kodeBarang,
            'nama' => '[ARSIP-SID] '.$nama,
            'slug' => 'arsip-sid-'.md5($kodeBarang),
            'jenis' => 'barang',
            'is_active' => false,
            'is_migrasi_sid' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->produkMap[$kodeBarang] = (int) $id;
        $this->counts['c04_dummy_produk_baru']++;

        return (int) $id;
    }

    private function loadMaps(): void
    {
        // pelanggan: kode_lama → id + nama → id
        DB::table('pelanggan')->select(['id', 'kode_lama', 'nama'])->orderBy('id')->chunk(500, function ($chunk) {
            foreach ($chunk as $r) {
                if ($r->kode_lama !== null && $r->kode_lama !== '') {
                    $this->pelangganMap[$r->kode_lama] = (int) $r->id;
                }
                $namaKey = mb_strtoupper(trim((string) $r->nama));
                if ($namaKey !== '' && ! isset($this->pelangganNamaMap[$namaKey])) {
                    $this->pelangganNamaMap[$namaKey] = (int) $r->id;
                }
            }
        });

        // gudang by kode
        DB::table('gudang')->select(['id', 'kode', 'nama'])->orderBy('id')->chunk(100, function ($chunk) {
            foreach ($chunk as $g) {
                $this->gudangMap[(string) $g->kode] = (int) $g->id;
                $this->gudangMap[mb_strtoupper(trim((string) $g->nama))] = (int) $g->id;
            }
        });

        // lokasistok per faktur ← item pertama (header penjualan tidak punya kolom lokasistok)
        DB::table('sid_retail_raw_itempenjualan')->orderBy('id')->chunkById(500, function ($chunk) {
            foreach ($chunk as $r) {
                $p = json_decode($r->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $kode = trim((string) ($p['kode'] ?? ''));
                if ($kode === '' || isset($this->lokasiPerFaktur[$kode])) {
                    continue;
                }
                $lok = trim((string) ($p['lokasistok'] ?? ''));
                if ($lok !== '') {
                    $this->lokasiPerFaktur[$kode] = $lok;
                }
            }
        });

        // produk + sku_variants by kode_lama
        DB::table('produk')->select(['id', 'kode_lama'])->whereNotNull('kode_lama')->orderBy('id')->chunk(2000, function ($chunk) {
            foreach ($chunk as $p) {
                $this->produkMap[$p->kode_lama] = (int) $p->id;
            }
        });
        DB::table('sku_variants')->select(['id', 'kode_lama'])->whereNotNull('kode_lama')->orderBy('id')->chunk(2000, function ($chunk) {
            foreach ($chunk as $s) {
                $this->skuMap[$s->kode_lama] = (int) $s->id;
            }
        });
    }

    private function loadTransaksiItems(): void
    {
        // tandai transaksi yang sudah punya item (idempotent guard C-04: skip faktur ber-item)
        $ids = DB::table('transaksi_item')->distinct()->pluck('transaksi_id')->all();
        $set = array_fill_keys($ids, true);
        foreach ($this->transaksiMap as $k => &$info) {
            if (isset($set[$info['id']])) {
                $info['has_items'] = true;
            }
        }
        unset($info);
    }

    private function emptyToNull(?string $v): ?string
    {
        return $v === null || $v === '' ? null : $v;
    }

    private function tulisLaporan(bool $dry): void
    {
        $dir = storage_path('app/migrasi-sid');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir.'/C1-rekonstruksi-laporan-'.date('Ymd-His').'.md';
        $mode = $dry ? 'DRY-RUN (tidak ada perubahan DB)' : 'EKSEKUSI';

        $body = '# LAPORAN FASE 3 (C-02..C-04) — REKONSTRUKSI TRANSAKSI — '.date('Y-m-d H:i:s')."\n\n"
            ."Mode: **{$mode}** — Perintah: `php artisan sid:rekonstruksi-transaksi` (idempotent, 2× run = sama)\n\n"
            ."## C-02 Cleanup artefak\n\n"
            ."- transaksi_item (transaksi R43-*): ada **{$this->counts['c02_ti_ada']}** → dihapus **{$this->counts['c02_ti_delete']}**\n"
            ."- transaksi R43-*: ada **{$this->counts['c02_t_ada']}** → dihapus **{$this->counts['c02_t_delete']}**\n"
            ."- sid_import_map entity_type='transaksi': ada **{$this->counts['c02_map_ada']}** → dihapus **{$this->counts['c02_map_delete']}**\n"
            ."- stok_log: ada **{$this->counts['c02_stok_ada']}** (max created_at `{$this->counts['c02_stok_max']}`) → dihapus **{$this->counts['c02_stok_delete']}**"
            .($this->counts['c02_stok_blocked'] ? ' ⚠️ **BLOKIR (ada baris >= 2026-09-21 12:00:00), tidak dihapus**' : '')."\n"
            ."- harga_tier: **TIDAK disentuh** — 19.104 = 6.368 produk × 3 tier (harga_toko/partai/cabang), BUKAN artefak arus-stok (§2.0 item 6 diverifikasi). Rekonstruksi dari `hrgpergroup` = fase lanjutan terpisah.\n\n"
            ."## C-03 Transaksi\n\n"
            ."- staging diproses: **{$this->counts['c03_staging']}**, inserted: **{$this->counts['c03_inserted']}**, skip (sudah ada): **{$this->counts['c03_skipped']}**\n"
            ."- pelanggan by kode_lama: **{$this->counts['c03_pelanggan_kode']}**, by nama: **{$this->counts['c03_pelanggan_nama']}**, NULL: **{$this->counts['c03_pelanggan_null']}**, nama unmatched (NULL): **{$this->counts['c03_pelanggan_nama_unmatched']}**\n"
            ."- tanggal_transaksi NULL (tanggal sentinel/kosong): **{$this->counts['c03_tanggal_null']}**\n"
            ."- gudang_id NULL (lokasistok tidak dikenal): **{$this->counts['c03_gudang_null']}** (source lokasistok = item pertama faktur — header penjualan tidak punya kolom lokasistok)\n"
            ."- sid_import_map upsert: **{$this->counts['c03_map_upsert']}**\n"
            ."- **DEVIASI CATATAN metode_bayar:** SEMUA 6.262 header punya `visa='UANG PAS'` (non-kosong) → mapping spec memberikan `metode_bayar='kartu'` untuk semua transaksi. Bila seharusnya 'tunai', ganti mapping di `metodeBayar()` lalu jalankan UPDATE massal (bukan rerun — rerun hanya skip).\n\n"
            ."## C-04 transaksi_item\n\n"
            ."- staging diproses: **{$this->counts['c04_staging']}**, inserted: **{$this->counts['c04_inserted']}**, skip (faktur sudah ber-item): **{$this->counts['c04_skipped_faktur']}**, orphan faktur (header tidak ada): **{$this->counts['c04_orphan_faktur']}**\n"
            ."- dummy produk baru `[ARSIP-SID]`: **{$this->counts['c04_dummy_produk_baru']}** (produk_id NOT NULL di DB → kode_barang tanpa match produk dibuat dummy, pola §4.4), sku_variant_id NULL: **{$this->counts['c04_sku_null']}**\n\n"
            ."## Rekonsiliasi per faktur (Σ item.subtotal vs header.jumlah)\n\n"
            ."- faktur dicek: **{$this->counts['rek_faktur']}**, menyimpang (>0.01): **{$this->counts['rek_deviasi']}**, tanpa item: **{$this->counts['rek_tanpa_item']}**\n"
            ."- katalog deviasi (wajib — TIDAK diperbaiki otomatis, catatan untuk fase verifikasi):\n\n";

        if ($this->deviasiSamples) {
            $samples = array_slice($this->deviasiSamples, 0, 50);
            $body .= "```\n".implode("\n", $samples)."\n```\n";
            if (count($this->deviasiSamples) > 50) {
                $body .= "\n_(+ ".(count($this->deviasiSamples) - 50)." faktur lain menyimpang — lihat DB via Σ subtotal vs total_akhir)_\n";
            }
        } else {
            $body .= "_(tidak ada)_\n";
        }

        $body .= "\n## Sample 3 transaksi (sid_detail, tanggal_transaksi, item)\n\n";
        foreach ($this->sampleTransaksi as $s) {
            $body .= "- **{$s['no_transaksi']}** — tanggal: `{$s['tanggal_transaksi']}`, pelanggan_id: ".($s['pelanggan_id'] ?? 'NULL')
                .", status: `{$s['status']}`, metode_bayar: `{$s['metode_bayar']}`, total: {$s['total_akhir']}\n"
                .'  - sid_detail: `'.json_encode($s['sid_detail'], JSON_UNESCAPED_UNICODE)."`\n"
                .'  - items: '.json_encode($s['items'], JSON_UNESCAPED_UNICODE)."\n";
        }
        if (! $this->sampleTransaksi) {
            $body .= "_(tidak ada — belum ada transaksi migrasi)_\n";
        }

        $body .= "\n## State DB akhir\n\n"
            .'- transaksi total: **'.DB::table('transaksi')->count()."** (target 6.262 + 1 operasional = 6.263)\n"
            .'- transaksi R43-* (migrasi): **'.DB::table('transaksi')->where('no_transaksi', 'like', 'R43-%')->count()."**\n"
            .'- transaksi item total: **'.DB::table('transaksi_item')->count()."** (target 10.273 + 1 operasional = 10.274)\n"
            .'- produk dummy `[ARSIP-SID]` total: **'.DB::table('produk')->where('nama', 'like', '[ARSIP-SID] %')->count()."**\n";

        $body .= "\n".implode("\n", $this->reportLines)."\n";

        file_put_contents($file, $body);
        $this->info("Laporan: {$file}");
    }

    private function printSummary(): void
    {
        $this->info("\n=== SUMMARY FASE 3 (C-02..C-04) ===");
        foreach ($this->counts as $k => $v) {
            $this->line("  {$k}: {$v}");
        }
    }
}
