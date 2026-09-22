<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [MIGRASI-SID FASE 2B] Referensi kecil (idempotent, non-destruktif, chunked).
 *
 * B-01 piutang            ← sid_retail_raw_piutang (11)
 * B-02 purchase_order     ← sid_retail_raw_pembelian (277) + itempembelian (1.073)
 *      (utang staging = 0 baris → SKIP, catat di laporan)
 * B-04 return_penjualan   ← sid_retail_raw_header_return_penjualan (142)
 *      return_pembelian   ← sid_retail_raw_header_return_pembelian (1)
 *
 * Guard idempotent: upsert by kode_lama (piutang/PO/item) dan no_return (return).
 * Jalankan 2x → angka sama. Sumber payload JSON dibaca per-chunk (500).
 *
 * CATATAN SKEMA (deviasi dari spec — DB aktual):
 * - piutang.status enum = belum_lunas/sebagian/lunas (TIDAK ada 'terbuka') → 'belum_lunas'.
 * - piutang.pelanggan_id NOT NULL → baris tanpa match pelanggan di-SKIP (tidak diinsert),
 *   bukan null + catatan. Semua 11 baris resolve (verifikasi 2026-09-22).
 * - purchase_order.supplier_id & gudang_tujuan_id NOT NULL → supplier unmapped = SKIP PO;
 *   gudang_tujuan dari payload.lokasistok (semua 'TOKO' → gudang nama 'TOKO' = TOKO1).
 * - purchase_order.status enum = draft/dikirim/diterima/dibatalkan (tanpa 'lunas') →
 *   'diterima' (final; lunas=true), 'dikirim' bila lunas=false.
 * - purchase_order_item.produk_id NOT NULL → kode_barang tanpa match produk dibuatkan
 *   produk dummy '[ARSIP-SID] …' (is_active=false, pola §4.4), item.kode_lama tetap
 *   kode_sumber untuk backfill setelah A-02 complete. (6 kode: 5341, 7337, 030585,
 *   041401, BSL-012652, MEETOO-1)
 * - piutang/purchase_order/purchase_order_item TIDAK punya kolom is_migrasi_sid
 *   (M-01..M-03 hanya menambahkannya ke 8 tabel lain) → flag tidak diset di sini.
 * - item.kode_lama = kode_sumber ('<kode>|<nourut>') — payload.kode = kode header
 *   (tidak unik per item, bentrok unique).
 */
class SidMigrateFase2B extends Command
{
    protected $signature = 'sid:migrate-2b {--dry-run : hanya hitung & laporan, tanpa perubahan DB} {--only=piutang,po,return : piutang,po,return}';

    protected $description = 'FASE 2B: piutang/PO(+item)/return dari staging SID (idempotent)';

    private array $counts = [
        'b01_staging' => 0, 'b01_inserted' => 0, 'b01_skipped_pelanggan' => 0, 'b01_kas_unmapped' => 0,
        'b02_po_staging' => 0, 'b02_po_inserted' => 0, 'b02_po_skip_supplier' => 0, 'b02_gudang_fallback' => 0,
        'b02_item_staging' => 0, 'b02_item_inserted' => 0, 'b02_dummy_produk_baru' => 0, 'b02_produk_unmapped' => 0,
        'b04_retjual_staging' => 0, 'b04_retjual_inserted' => 0, 'b04_retjual_pelanggan_unmapped' => 0,
        'b04_retbeli_staging' => 0, 'b04_retbeli_inserted' => 0, 'b04_retbeli_supplier_unmapped' => 0,
    ];

    private array $unmappedPelanggan = [];
    private array $unmappedSupplier = [];
    private array $unmappedProduk = [];
    private array $kasUnmapped = [];
    private array $gudangFallback = [];

    private const SENTINEL = ['1899-12-30', '0000-00-00', '1900-01-01', ''];

    /** @var array<string,int> kode_lama pelanggan → id */
    private array $pelangganMap = [];
    /** @var array<string,int> kode_lama supplier → id */
    private array $supplierMap = [];
    /** @var array<string,int> kode_lama produk → id */
    private array $produkMap = [];
    /** @var array<string,int> kode_lama sku_variants → id */
    private array $skuMap = [];
    /** @var array<string,int> master_kas.kode → id */
    private array $kasMap = [];
    /** @var array<string,int> gudang kode/nama → id */
    private array $gudangMap = [];
    /** @var array<string,int> gudang id by nama-lower */
    private array $gudangByNama = [];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));

        if ($dry) {
            $this->warn('DRY-RUN: tidak ada perubahan DB');
        }

        $this->loadMaps();
        $this->loadGudang();

        if (in_array('piutang', $only, true)) {
            $this->stepPiutang($dry);
        }
        if (in_array('po', $only, true)) {
            $this->stepPurchaseOrder($dry);
        }
        if (in_array('return', $only, true)) {
            $this->stepReturn($dry);
        }

        $this->tulisLaporan($dry);
        $this->printSummary();

        return 0;
    }

    // ---------------- B-01 PIUTANG ----------------

    private function stepPiutang(bool $dry): void
    {
        $this->info("\n--- B-01 Piutang (11) ---");

        DB::table('sid_retail_raw_piutang')->orderBy('id')->chunkById(50, function ($chunk) use ($dry) {
            foreach ($chunk as $raw) {
                $this->counts['b01_staging']++;
                $p = json_decode($raw->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $kode = trim((string) ($p['kode'] ?? $raw->kode_sumber));
                $pelangganKode = trim((string) ($p['pelanggan'] ?? ''));
                $pelangganId = $pelangganKode !== '' ? ($this->pelangganMap[$pelangganKode] ?? null) : null;

                if (! $pelangganId) {
                    // pelanggan_id NOT NULL → skip, catat
                    $this->counts['b01_skipped_pelanggan']++;
                    $this->unmappedPelanggan[] = sprintf('piutang %s → pelanggan kode `%s`', $kode, $pelangganKode ?: '(kosong)');
                    continue;
                }

                $kasId = null;
                $kodeKas = trim((string) ($p['kode_kas'] ?? ''));
                if ($kodeKas !== '') {
                    $kasId = $this->kasMap[$kodeKas] ?? null;
                    if (! $kasId) {
                        $this->counts['b01_kas_unmapped']++;
                        $this->kasUnmapped[] = "{$kode} → kode_kas `{$kodeKas}`";
                    }
                }

                $jumlah = (float) ($p['jumlah'] ?? 0);
                $ket = trim((string) ($p['ket'] ?? ''));
                $tanggal = $this->normalDate($p['tanggal'] ?? null);
                $keterangan = $ket !== '' ? $ket.' (SID: '.$tanggal.')' : ($tanggal ? 'SID: '.$tanggal : null);

                $data = [
                    'no_piutang' => 'PIU-'.$kode,
                    'pelanggan_id' => $pelangganId,
                    'jumlah' => $jumlah,
                    'jumlah_dibayar' => 0,
                    'sisa' => $jumlah,
                    'status' => 'belum_lunas', // enum app; 'terbuka' tidak ada
                    'kas_id' => $kasId,
                    'keterangan' => $keterangan,
                    'cabang_id' => 1, // CBG-01
                    'updated_at' => now(),
                ];

                if (! $dry) {
                    $row = DB::table('piutang')->where('kode_lama', $kode)->first();
                    if ($row) {
                        DB::table('piutang')->where('id', $row->id)->update($data);
                    } else {
                        DB::table('piutang')->insert(array_merge($data, [
                            'kode_lama' => $kode,
                            'created_at' => now(),
                        ]));
                        $this->counts['b01_inserted']++;
                    }
                } else {
                    $exists = DB::table('piutang')->where('kode_lama', $kode)->exists();
                    if (! $exists) {
                        $this->counts['b01_inserted']++;
                    }
                }
            }
        });
    }

    // ---------------- B-02 PURCHASE ORDER + ITEM ----------------

    private function stepPurchaseOrder(bool $dry): void
    {
        $this->info("\n--- B-02 Purchase Order + Item (277 / 1.073) ---");

        $hutangCount = DB::table('sid_retail_raw_hutang')->count();
        $this->info("  hutang staging: {$hutangCount} baris → SKIP utang (0)");

        DB::table('sid_retail_raw_pembelian')->orderBy('id')->chunkById(100, function ($chunk) use ($dry) {
            foreach ($chunk as $raw) {
                $this->counts['b02_po_staging']++;
                $p = json_decode($raw->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $kode = trim((string) ($p['kode'] ?? $raw->kode_sumber));
                $supplierKode = trim((string) ($p['supplier'] ?? ''));
                $supplierId = $supplierKode !== '' ? ($this->supplierMap[$supplierKode] ?? null) : null;

                if (! $supplierId) {
                    // supplier_id NOT NULL → skip PO + item, catat
                    $this->counts['b02_po_skip_supplier']++;
                    $this->unmappedSupplier[] = sprintf('PO %s → supplier kode `%s`', $kode, $supplierKode ?: '(kosong)');
                    continue;
                }

                $gudangId = $this->resolveGudang($p['lokasistok'] ?? 'TOKO');
                if (! $gudangId) {
                    $this->counts['b02_gudang_fallback']++;
                }

                $jumlah = (float) ($p['jumlah'] ?? 0);
                $hutang = (float) ($p['hutang'] ?? 0);
                $lunas = filter_var($p['lunas'] ?? true, FILTER_VALIDATE_BOOLEAN);
                $jt = (int) ($p['jt'] ?? 0);
                $visa = strtoupper(trim((string) ($p['visa'] ?? '')));
                $tanggal = $this->normalDate($p['tanggal'] ?? null);
                $ket = trim((string) ($p['keterangan'] ?? ''));

                $catatan = trim($ket.' (SID: '.$tanggal.')');
                if ($jt > 0) {
                    $catatan = trim($catatan.'; jatuh tempo '.$jt.' hari (SID)');
                }

                $data = [
                    'no_po' => 'PO-SID-'.$kode,
                    'supplier_id' => $supplierId,
                    'gudang_tujuan_id' => $gudangId,
                    'status' => $lunas ? 'diterima' : 'dikirim', // enum app; tidak ada 'lunas'
                    'metode_bayar' => (str_contains($visa, 'KREDIT') || str_contains($visa, 'KARTU')) ? 'kredit' : 'tunai',
                    'jatuh_tempo' => null, // jt = int hari, bukan tanggal
                    'total' => $jumlah,
                    'total_dibayar' => max(0, $jumlah - $hutang),
                    'catatan' => $catatan ?: null,
                    'updated_at' => now(),
                ];

                $poId = null;
                if (! $dry) {
                    $row = DB::table('purchase_order')->where('kode_lama', $kode)->first();
                    if ($row) {
                        DB::table('purchase_order')->where('id', $row->id)->update($data);
                        $poId = $row->id;
                    } else {
                        $poId = DB::table('purchase_order')->insertGetId(array_merge($data, [
                            'kode_lama' => $kode,
                            'created_at' => now(),
                        ]));
                        $this->counts['b02_po_inserted']++;
                    }
                } else {
                    $poId = DB::table('purchase_order')->where('kode_lama', $kode)->value('id');
                    if (! $poId) {
                        $this->counts['b02_po_inserted']++;
                    }
                }

                if ($poId || $dry) {
                    // dry-run: hitung estimasi item walau PO belum ada di DB
                    $this->stepPurchaseOrderItems($kode, $poId ?? 0, $dry);
                }
            }
        });
    }

    private function stepPurchaseOrderItems(string $poKode, int $poId, bool $dry): void
    {
        DB::table('sid_retail_raw_itempembelian')
            ->where('kode_sumber', 'like', $poKode.'|%')
            ->orWhere('payload_normal', 'like', '%"kode":"'.$poKode.'"%')
            ->orderBy('id')->chunkById(300, function ($chunk) use ($poId, $dry) {
                foreach ($chunk as $raw) {
                    $p = json_decode($raw->payload_normal, true);
                    if (! is_array($p)) {
                        continue;
                    }
                    // item.kode_lama = kode_sumber ('<kode>|<nourut>') — unik per item
                    $itemKode = trim((string) $raw->kode_sumber);
                    $kodeBarang = trim((string) ($p['kode_barang'] ?? ''));

                    $produkId = $kodeBarang !== '' ? ($this->produkMap[$kodeBarang] ?? null) : null;
                    $skuVariantId = $kodeBarang !== '' ? ($this->skuMap[$kodeBarang] ?? null) : null;

                    if (! $produkId && ! $dry) {
                        // produk_id NOT NULL → dummy produk (pola §4.4), backfill via kode_lama setelah A-02
                        $produkId = $this->ensureDummyProduk($kodeBarang, trim((string) ($p['nama_barang'] ?? '')));
                    }
                    if (! $produkId) {
                        $this->counts['b02_produk_unmapped']++;
                        if (! in_array($kodeBarang, $this->unmappedProduk, true)) {
                            $this->unmappedProduk[] = $kodeBarang;
                        }
                        continue;
                    }
                    $this->counts['b02_item_staging']++;

                    $data = [
                        'purchase_order_id' => $poId,
                        'produk_id' => $produkId,
                        'sku_variant_id' => $skuVariantId,
                        'harga_beli' => (float) ($p['harga_beli'] ?? 0),
                        'jumlah' => (int) ($p['qty'] ?? 0),
                        'subtotal' => (float) ($p['subtotal'] ?? 0),
                        'updated_at' => now(),
                    ];

                    if ($dry) {
                        $exists = DB::table('purchase_order_item')->where('kode_lama', $itemKode)->exists();
                        if (! $exists) {
                            $this->counts['b02_item_inserted']++;
                        }
                        continue;
                    }
                    $row = DB::table('purchase_order_item')->where('kode_lama', $itemKode)->first();
                    if ($row) {
                        DB::table('purchase_order_item')->where('id', $row->id)->update($data);
                    } else {
                        DB::table('purchase_order_item')->insert(array_merge($data, [
                            'kode_lama' => $itemKode,
                            'created_at' => now(),
                        ]));
                        $this->counts['b02_item_inserted']++;
                    }
                }
            });
    }

    private function ensureDummyProduk(string $kodeBarang, string $namaBarang): ?int
    {
        $id = $this->produkMap[$kodeBarang] ?? null;
        if ($id) {
            return $id;
        }
        // guard race/rerun: mungkin sudah dibuat run sebelumnya tapi belum masuk map
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
        $this->counts['b02_dummy_produk_baru']++;

        return (int) $id;
    }

    // ---------------- B-04 RETURN ----------------

    private function stepReturn(bool $dry): void
    {
        $this->info("\n--- B-04 Return (penjualan 142 / pembelian 1) ---");
        $this->stepReturnPenjualan($dry);
        $this->stepReturnPembelian($dry);
    }

    private function stepReturnPenjualan(bool $dry): void
    {
        DB::table('sid_retail_raw_header_return_penjualan')->orderBy('id')->chunkById(100, function ($chunk) use ($dry) {
            foreach ($chunk as $raw) {
                $this->counts['b04_retjual_staging']++;
                $p = json_decode($raw->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $kode = trim((string) ($p['kode'] ?? $raw->kode_sumber));
                $tanggal = $this->normalDate($p['tanggal'] ?? null);

                // field 'pelanggan' = kode SID bila ada; 'namapelanggan' = nama (sampel: 1/142 pakai nama)
                $pelangganId = null;
                $kodePlg = trim((string) ($p['pelanggan'] ?? ''));
                if ($kodePlg !== '') {
                    $pelangganId = $this->pelangganMap[$kodePlg] ?? null;
                }
                if (! $pelangganId) {
                    $namaPlg = trim((string) ($p['namapelanggan'] ?? ''));
                    if ($namaPlg !== '') {
                        // exact dulu, lalu contains (nama app bisa lebih pendek: 'RIZKI STORE' → 'RIZKI')
                        $pelangganId = DB::table('pelanggan')->whereRaw('UPPER(TRIM(nama)) = ?', [mb_strtoupper($namaPlg)])->value('id');
                        if (! $pelangganId) {
                            $pelangganId = DB::table('pelanggan')
                                ->whereRaw('UPPER(?) LIKE CONCAT("%", UPPER(TRIM(nama)), "%")', [$namaPlg])
                                ->orderBy('id')->value('id');
                        }
                    }
                }
                if (! $pelangganId) {
                    $this->counts['b04_retjual_pelanggan_unmapped']++;
                    $this->unmappedPelanggan[] = sprintf('return penjualan %s → pelanggan `%s`/`%s`', $kode, $kodePlg ?: '-', $namaPlg ?? '-');
                }

                $data = [
                    'no_return' => 'RSJ-'.$kode,
                    'transaksi_id' => null, // backfill fase C-03 setelah rekonstruksi transaksi
                    'pelanggan_id' => $pelangganId,
                    'tanggal' => $tanggal,
                    'jumlah' => 0, // header tanpa jumlah; item return tidak di-staging
                    'status' => 'selesai',
                    'alasan' => 'Migrasi SID — item return belum tersedia di dump',
                    'is_migrasi_sid' => true,
                    'updated_at' => now(),
                ];

                if (! $dry) {
                    $row = DB::table('return_penjualan')->where('no_return', $data['no_return'])->first();
                    if ($row) {
                        DB::table('return_penjualan')->where('id', $row->id)->update($data);
                    } else {
                        DB::table('return_penjualan')->insert(array_merge($data, ['created_at' => now()]));
                        $this->counts['b04_retjual_inserted']++;
                    }
                } elseif (! DB::table('return_penjualan')->where('no_return', $data['no_return'])->exists()) {
                    $this->counts['b04_retjual_inserted']++;
                }
            }
        });
    }

    private function stepReturnPembelian(bool $dry): void
    {
        DB::table('sid_retail_raw_header_return_pembelian')->orderBy('id')->chunkById(100, function ($chunk) use ($dry) {
            foreach ($chunk as $raw) {
                $this->counts['b04_retbeli_staging']++;
                $p = json_decode($raw->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $kode = trim((string) ($p['kode'] ?? $raw->kode_sumber));
                $tanggal = $this->normalDate($p['tanggal'] ?? null);

                $supplierKode = trim((string) ($p['supplier'] ?? ''));
                $supplierId = $supplierKode !== '' ? ($this->supplierMap[$supplierKode] ?? null) : null;
                if (! $supplierId) {
                    $this->counts['b04_retbeli_supplier_unmapped']++;
                    $this->unmappedSupplier[] = sprintf('return pembelian %s → supplier `%s`', $kode, $supplierKode ?: '-');
                }

                $data = [
                    'no_return' => 'RSB-'.$kode,
                    'purchase_order_id' => null, // backfill fase C-03 setelah rekonstruksi PO
                    'supplier_id' => $supplierId,
                    'tanggal' => $tanggal,
                    'jumlah' => (float) ($p['jumlah'] ?? 0),
                    'status' => 'selesai',
                    'alasan' => 'Migrasi SID — item return belum tersedia di dump',
                    'is_migrasi_sid' => true,
                    'updated_at' => now(),
                ];

                if (! $dry) {
                    $row = DB::table('return_pembelian')->where('no_return', $data['no_return'])->first();
                    if ($row) {
                        DB::table('return_pembelian')->where('id', $row->id)->update($data);
                    } else {
                        DB::table('return_pembelian')->insert(array_merge($data, ['created_at' => now()]));
                        $this->counts['b04_retbeli_inserted']++;
                    }
                } elseif (! DB::table('return_pembelian')->where('no_return', $data['no_return'])->exists()) {
                    $this->counts['b04_retbeli_inserted']++;
                }
            }
        });
    }

    // ---------------- MAPS ----------------

    private function loadMaps(): void
    {
        DB::table('pelanggan')->select(['id', 'kode_lama'])->whereNotNull('kode_lama')->orderBy('id')->chunk(500, function ($ch) {
            foreach ($ch as $r) {
                $this->pelangganMap[$r->kode_lama] = (int) $r->id;
            }
        });
        DB::table('supplier')->select(['id', 'kode_lama'])->whereNotNull('kode_lama')->orderBy('id')->chunk(500, function ($ch) {
            foreach ($ch as $r) {
                $this->supplierMap[$r->kode_lama] = (int) $r->id;
            }
        });
        DB::table('produk')->select(['id', 'kode_lama'])->whereNotNull('kode_lama')->orderBy('id')->chunk(500, function ($ch) {
            foreach ($ch as $r) {
                $this->produkMap[$r->kode_lama] = (int) $r->id;
            }
        });
        DB::table('sku_variants')->select(['id', 'kode_lama'])->whereNotNull('kode_lama')->orderBy('id')->chunk(500, function ($ch) {
            foreach ($ch as $r) {
                $this->skuMap[$r->kode_lama] = (int) $r->id;
            }
        });
        DB::table('master_kas')->select(['id', 'kode'])->orderBy('id')->chunk(100, function ($ch) {
            foreach ($ch as $r) {
                $this->kasMap[$r->kode] = (int) $r->id;
            }
        });
    }

    private function loadGudang(): void
    {
        DB::table('gudang')->select(['id', 'kode', 'nama'])->orderBy('id')->chunk(100, function ($ch) {
            foreach ($ch as $r) {
                $this->gudangMap[$r->kode] = (int) $r->id;
                $this->gudangByNama[mb_strtolower(trim((string) $r->nama))] = (int) $r->id;
            }
        });
    }

    /** lokasistok SID (TOKO/GUDANG/TOKOn) → gudang app; fallback kode GUDANG. */
    private function resolveGudang(mixed $lokasi): ?int
    {
        $lokasi = trim((string) ($lokasi ?? ''));
        $id = null;
        if ($lokasi !== '') {
            $id = $this->gudangMap[$lokasi] ?? null;
            if (! $id) {
                $id = $this->gudangByNama[mb_strtolower($lokasi)] ?? null;
            }
            if (! $id) {
                // TOKO n → TOKOn / TOKOn → TOKO n: coba awalan
                foreach ($this->gudangMap as $kode => $gId) {
                    if ($lokasi === 'TOKO' && str_starts_with($kode, 'TOKO')) {
                        $id = $gId;
                        break;
                    }
                    if (preg_match('/^TOKO(\d+)$/', $lokasi, $m) && $kode === 'TOKO'.(int) $m[1]) {
                        $id = $gId;
                        break;
                    }
                }
            }
        }
        if (! $id) {
            $id = $this->gudangMap['GUDANG'] ?? null;
            if ($id) {
                $this->counts['b02_gudang_fallback']++;
                $this->gudangFallback[$lokasi] = ($this->gudangFallback[$lokasi] ?? 0) + 1;
            }
        }

        return $id;
    }

    // ---------------- LAPORAN ----------------

    private function tulisLaporan(bool $dry): void
    {
        $dir = storage_path('app/migrasi-sid');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir.'/B-fase2B-laporan-'.date('Ymd-His').'.md';

        $body = "# LAPORAN FASE 2B (B-01, B-02, B-04) — ".date('Y-m-d H:i:s')."\n\n"
            . 'Perintah: `php artisan sid:migrate-2b'.($dry ? ' --dry-run' : '')."` (idempotent, non-destruktif, chunked)\n\n"
            . "## Ringkasan\n\n"
            . "- B-01 piutang staging: {$this->counts['b01_staging']}, inserted: {$this->counts['b01_inserted']}, skip (pelanggan unmapped): {$this->counts['b01_skipped_pelanggan']}, kas unmapped: {$this->counts['b01_kas_unmapped']}\n"
            . "- B-02 PO staging: {$this->counts['b02_po_staging']}, inserted: {$this->counts['b02_po_inserted']}, skip (supplier unmapped): {$this->counts['b02_po_skip_supplier']}\n"
            . "- B-02 item staging: {$this->counts['b02_item_staging']}, inserted: {$this->counts['b02_item_inserted']}, dummy produk baru: {$this->counts['b02_dummy_produk_baru']}, produk unmapped (skip item): {$this->counts['b02_produk_unmapped']}\n"
            . "- B-04 return penjualan staging: {$this->counts['b04_retjual_staging']}, inserted: {$this->counts['b04_retjual_inserted']}, pelanggan unmapped: {$this->counts['b04_retjual_pelanggan_unmapped']}\n"
            . "- B-04 return pembelian staging: {$this->counts['b04_retbeli_staging']}, inserted: {$this->counts['b04_retbeli_inserted']}, supplier unmapped: {$this->counts['b04_retbeli_supplier_unmapped']}\n\n"
            . "## B-02 catatan (utang & skema)\n\n"
            . "- hutang staging = 0 baris → UTANG DI-SKIP (B-02 utang tidak ada data).\n"
            . "- piutang.status enum app = `belum_lunas/sebagian/lunas` (TIDAK ada 'terbuka') → pakai `belum_lunas`.\n"
            . "- piutang.pelanggan_id / purchase_order.supplier_id / purchase_order_item.produk_id = NOT NULL di DB → unmapped: piutang/PO di-skip, item pakai produk dummy `[ARSIP-SID] …` (pola §4.4, is_active=false, backfill via kode_lama setelah A-02 complete).\n"
            . "- purchase_order.status enum app = `draft/dikirim/diterima/dibatalkan` (tanpa 'lunas') → `diterima` (lunas=true), `dikirim` (lunas=false).\n"
            . "- purchase_order.gudang_tujuan_id NOT NULL → dari payload.lokasistok (semua `TOKO` → gudang nama TOKO = TOKO1).\n"
            . "- item.kode_lama = kode_sumber `<kode pembelian>|<nourut>` (payload.kode = kode header, tidak unik per item).\n"
            . "- piutang/purchase_order/purchase_order_item TIDAK punya kolom `is_migrasi_sid` (M-01..M-03) → flag tidak diset.\n"
            . "- total_dibayar = jumlah - hutang; jatuh_tempo = null (payload.jt = int hari → dicatat di catatan).\n\n"
            . "## Unmapped pelanggan (NULL / skip)\n\n"
            . $this->listLines($this->unmappedPelanggan)
            . "## Unmapped supplier (skip / null)\n\n"
            . $this->listLines($this->unmappedSupplier)
            . "## Produk tidak ada di produk.kode_lama (dummy dibuat bila item diproses)\n\n"
            . $this->listLines($this->unmappedProduk)
            . "## Kas unmapped (kode_kas tanpa master_kas)\n\n"
            . $this->listLines($this->kasUnmapped)
            . "## Gudang fallback (lokasistok → GUDANG)\n\n"
            . $this->listLines(array_map(fn ($k, $v) => "`{$k}` ×{$v}", array_keys($this->gudangFallback), array_values($this->gudangFallback)))
            . "## Skipped (per instruksi, TIDAK dieksekusi)\n\n"
            . "- B-03 nomor_seri_produk ← nomor_seri (staging = 0 baris)\n"
            . "- B-05 expired_barang → batch expiry (app tak punya batch; produk.expired_at sudah diisi @A-02)\n"
            . "- B-06 koreksi (1 baris, historis → snapshot stok_opname, bukan prioritas)\n\n";

        file_put_contents($file, $body);
        $this->info("Laporan: {$file}");
    }

    private function listLines(array $lines): string
    {
        if (! $lines) {
            return "- (tidak ada)\n\n";
        }

        return implode("\n", array_map(fn ($l) => "- {$l}", $lines))."\n\n";
    }

    private function printSummary(): void
    {
        $this->info("\n=== SUMMARY FASE 2B ===");
        foreach ($this->counts as $k => $v) {
            $this->line("  {$k}: {$v}");
        }

        $this->info("\n=== VERIFIKASI (state DB) ===");
        $this->line('  piutang total: '.DB::table('piutang')->count());
        $this->line('  piutang kode_lama: '.DB::table('piutang')->whereNotNull('kode_lama')->count());
        $this->line('  purchase_order total: '.DB::table('purchase_order')->count());
        $this->line('  purchase_order kode_lama: '.DB::table('purchase_order')->whereNotNull('kode_lama')->count());
        $this->line('  purchase_order_item total: '.DB::table('purchase_order_item')->count());
        $this->line('  purchase_order_item kode_lama: '.DB::table('purchase_order_item')->whereNotNull('kode_lama')->count());
        $this->line('  return_penjualan total: '.DB::table('return_penjualan')->count());
        $this->line('  return_pembelian total: '.DB::table('return_pembelian')->count());
    }

    // ---------------- UTIL ----------------

    private function normalDate(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);
        if (in_array($s, self::SENTINEL, true)) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}