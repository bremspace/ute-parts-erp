<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * [MIGRASI-SID FASE 2A] Backfill master A-02..A-05 (idempotent, non-destruktif).
 *
 * A-02 produk  ← sid_retail_raw_barang
 * A-03 pelanggan ← sid_retail_raw_pelanggan + member merge ← sid_retail_raw_member (+ laporan dedup)
 * A-04 supplier ← sid_retail_raw_supplier
 * A-05 sku_variants.kode_lama ← sid_import_map (barang → sku_variant)
 *
 * Guard: update hanya jika kolom target NULL/kosong (kecuali flag is_migrasi_sid,
 * wajib_serial, kena_pajak, jenis-default). Jalankan 2x → angka sama.
 * Sumber payload JSON dibaca per-chunk (500), tidak dimuat sekaligus.
 */
class SidBackfillMaster2A extends Command
{
    protected $signature = 'sid:backfill-2a {--step=all : produk|pelanggan|supplier|varian|all} {--report-only : hanya tulis laporan, tanpa perubahan DB}';

    protected $description = 'FASE 2A: backfill produk/pelanggan/supplier/sku_variants dari staging SID (idempotent)';

    private array $counts = [
        'a02_produk_diproses' => 0, 'a02_unmapped' => 0,
        'a02_kode_lama' => 0, 'a02_barcode' => 0, 'a02_barcode_alt' => 0,
        'a02_brand_baru' => 0, 'a02_brand_skip' => 0, 'a02_updated' => 0,
        'a03_pelanggan_diproses' => 0, 'a03_unmapped' => 0, 'a03_updated' => 0,
        'a03_member_merge' => 0, 'a03_member_unmatched' => 0,
        'a04_supplier_diproses' => 0, 'a04_unmapped' => 0, 'a04_updated' => 0,
        'a05_sku_filled' => 0, 'a05_sku_sudah' => 0,
    ];

    private array $reportLines = [];

    private array $brandSkip = [];

    private array $memberUnmatched = [];

    private array $unmappedProduk = [];

    private const SENTINEL = ['1899-12-30', '0000-00-00', '1900-01-01', ''];

    public function handle(): int
    {
        $reportOnly = (bool) $this->option('report-only');
        $step = $this->option('step');

        if ($reportOnly) {
            $this->warn('REPORT-ONLY: tidak ada perubahan DB');
        }

        if (in_array($step, ['produk', 'all'])) {
            $this->stepProduk($reportOnly);
        }
        if (in_array($step, ['pelanggan', 'all'])) {
            $this->stepPelanggan($reportOnly);
            $this->stepMember($reportOnly);
        }
        if (in_array($step, ['supplier', 'all'])) {
            $this->stepSupplier($reportOnly);
        }
        if (in_array($step, ['varian', 'all'])) {
            $this->stepVarian($reportOnly);
        }

        $this->laporanDedupPelanggan();
        $this->laporanUnmappedProduk($reportOnly);
        $this->tulisLaporan();
        $this->printSummary();

        return 0;
    }

    // ---------------- A-02 PRODUK ----------------

    private function stepProduk(bool $reportOnly): void
    {
        $this->info("\n--- A-02 Produk backfill ---");

        // peta kode_sumber barang → sku_variant id (hanya entity_type=sku_variant)
        $skuMap = [];
        DB::table('sid_import_map')
            ->where('tabel_sumber', 'barang')
            ->where('entity_type', 'sku_variant')
            ->orderBy('id')
            ->chunkById(1000, function ($chunk) use (&$skuMap) {
                foreach ($chunk as $m) {
                    $skuMap[$m->kode_sumber] = (int) $m->entity_id;
                }
            });

        // sku_variant id → produk_id
        $skuProduk = [];
        DB::table('sku_variants')->select(['id', 'produk_id'])->orderBy('id')->chunk(2000, function ($chunk) use (&$skuProduk) {
            foreach ($chunk as $s) {
                $skuProduk[(int) $s->id] = (int) $s->produk_id;
            }
        });

        // snapshot kolom produk (slim, bukan payload JSON) untuk guard NULL
        $produkPool = [];
        DB::table('produk')
            ->select(['id', 'kode_lama', 'barcode', 'barcode_alt', 'brand_id', 'golongan', 'subgolongan',
                'satuan_beli', 'isi_satuan', 'diskon', 'stok_maksimum', 'stok_warning', 'expired_at',
                'jenis', 'wajib_serial', 'poin', 'komisi_sales', 'kena_pajak', 'nilai_ppn', 'harga_lain', 'is_migrasi_sid'])
            ->orderBy('id')->chunk(2000, function ($chunk) use (&$produkPool) {
                foreach ($chunk as $p) {
                    $produkPool[(int) $p->id] = $p;
                }
            });

        // brands by nama (case-insensitive) — seed M-04
        $brands = [];
        DB::table('brands')->select(['id', 'nama'])->orderBy('id')->chunk(100, function ($chunk) use (&$brands) {
            foreach ($chunk as $b) {
                $brands[mb_strtolower(trim((string) $b->nama))] = (int) $b->id;
            }
        });

        DB::table('sid_retail_raw_barang')->orderBy('id')->chunkById(500, function ($chunk) use ($reportOnly, $skuMap, $skuProduk, &$produkPool, $brands) {
            foreach ($chunk as $raw) {
                $kode = trim((string) $raw->kode_sumber);
                $skuId = $skuMap[$kode] ?? null;
                $produkId = $skuId ? ($skuProduk[$skuId] ?? null) : null;
                if (! $produkId) {
                    $this->counts['a02_unmapped']++;

                    continue;
                }
                $this->counts['a02_produk_diproses']++;

                $p = json_decode($raw->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $cur = $produkPool[$produkId] ?? null;
                if (! $cur) {
                    continue;
                }

                $u = [];

                if ($cur->kode_lama === null) {
                    $u['kode_lama'] = $kode;
                    $this->counts['a02_kode_lama']++;
                }

                $barcode = trim((string) ($p['kode_barcode'] ?? ''));
                if ($barcode !== '' && $cur->barcode === null) {
                    $u['barcode'] = $barcode;
                    $this->counts['a02_barcode']++;
                }

                $alt = [];
                foreach (['kode_barcode2', 'kode_barcode3', 'kode_barcode4'] as $k) {
                    $v = trim((string) ($p[$k] ?? ''));
                    if ($v !== '') {
                        $alt[] = $v;
                    }
                }
                for ($i = 5; $i <= 14; $i++) {
                    $v = trim((string) ($p["barcode{$i}"] ?? ''));
                    if ($v !== '') {
                        $alt[] = $v;
                    }
                }
                if ($alt && $cur->barcode_alt === null) {
                    $u['barcode_alt'] = json_encode(array_values(array_unique($alt)));
                    $this->counts['a02_barcode_alt']++;
                }

                if ($cur->brand_id === null) {
                    $merk = trim((string) ($p['merk'] ?? ''));
                    if ($merk !== '') {
                        $brandId = $brands[mb_strtolower($merk)] ?? null;
                        if ($brandId) {
                            $u['brand_id'] = $brandId;
                            $this->counts['a02_brand_baru']++;
                        } else {
                            $this->counts['a02_brand_skip']++;
                            $this->brandSkip[$merk] = ($this->brandSkip[$merk] ?? 0) + 1;
                        }
                    }
                }

                if ($cur->golongan === null) {
                    $v = trim((string) ($p['golongan'] ?? ''));
                    if ($v !== '') {
                        $u['golongan'] = $v;
                    }
                }
                if ($cur->subgolongan === null) {
                    $v = trim((string) ($p['subgolongan1'] ?? ''));
                    if ($v !== '') {
                        $u['subgolongan'] = $v;
                    }
                }
                if ($cur->satuan_beli === null) {
                    $v = trim((string) ($p['satuanbeli'] ?? ''));
                    if ($v !== '') {
                        $u['satuan_beli'] = $v;
                    }
                }
                if ($cur->isi_satuan === null) {
                    $v = $p['isi'] ?? null;
                    if ($v !== null && $v !== '') {
                        $u['isi_satuan'] = (float) $v;
                    }
                }

                $diskon = (float) ($p['diskon'] ?? 0);
                if ($diskon > 0 && $cur->diskon === null) {
                    $u['diskon'] = $diskon;
                }
                if ($cur->stok_maksimum === null && isset($p['stokmax'])) {
                    $u['stok_maksimum'] = (float) $p['stokmax'];
                }
                if ($cur->stok_warning === null && isset($p['warningstok'])) {
                    $u['stok_warning'] = (float) $p['warningstok'];
                }

                $expired = $this->normalDate($p['expired'] ?? null);
                if ($expired && $cur->expired_at === null) {
                    $u['expired_at'] = $expired;
                }

                // jenis: isi hanya jika belum (masih NULL atau default 'barang')
                $jenisSid = strtoupper(trim((string) ($p['jenis'] ?? '')));
                $paket = filter_var($p['paket'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $jenisBaru = null;
                if ($jenisSid === 'JASA') {
                    $jenisBaru = 'jasa';
                } elseif ($paket) {
                    $jenisBaru = 'paket';
                } elseif ($jenisSid === 'BARANG' || $jenisSid === '') {
                    $jenisBaru = 'barang';
                }
                if ($jenisBaru !== null && ($cur->jenis === null || $cur->jenis === '' || $cur->jenis === 'barang' && $jenisBaru !== 'barang')) {
                    $u['jenis'] = $jenisBaru;
                }

                if (! $cur->wajib_serial) {
                    $sn = filter_var($p['sn'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    if ($sn) {
                        $u['wajib_serial'] = true;
                    }
                }

                if ($cur->poin === null && isset($p['point']) && $p['point'] !== null && $p['point'] !== '') {
                    $u['poin'] = (float) $p['point'];
                }
                if ($cur->komisi_sales === null && isset($p['jum_komisi_sales']) && $p['jum_komisi_sales'] !== null && $p['jum_komisi_sales'] !== '') {
                    $u['komisi_sales'] = (float) $p['jum_komisi_sales'];
                }

                $kenaPajak = filter_var($p['sudah_ppn'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    || trim((string) ($p['pajak'] ?? '')) !== '';
                if ($kenaPajak) {
                    $u['kena_pajak'] = true;
                }
                if ($cur->nilai_ppn === null && isset($p['nilaippn']) && $p['nilaippn'] !== null && $p['nilaippn'] !== '' && $p['nilaippn'] != 0) {
                    $u['nilai_ppn'] = (float) $p['nilaippn'];
                }

                // harga_lain json subset (non-zero)
                $hl = [];
                foreach (['harga_toko2', 'harga_toko3', 'harga_toko4',
                    'harga_partai', 'harga_partai2', 'harga_partai3', 'harga_partai4',
                    'harga_cabang', 'harga_cabang2', 'harga_cabang3', 'harga_cabang4',
                    'harga_karyawan', 'harga_member',
                    'harga_lain', 'harga_lain2', 'harga_lain3', 'harga_lain4'] as $k) {
                    $v = isset($p[$k]) && is_numeric($p[$k]) ? (float) $p[$k] : null;
                    if ($v !== null && $v > 0) {
                        $hl[$k] = $v;
                    }
                }
                if ($hl && $cur->harga_lain === null) {
                    $u['harga_lain'] = json_encode($hl);
                }

                $u['is_migrasi_sid'] = true;

                if (! $u) {
                    continue;
                }
                if (! $reportOnly) {
                    DB::table('produk')->where('id', $produkId)->update($u);
                }
                // refresh pool agar idempotent dalam satu run (baris yang sama tak muncul 2x namun aman)
                $produkPool[$produkId] = (object) array_merge((array) $cur, $u);
                $this->counts['a02_updated']++;
            }
        });
    }

    // ---------------- A-03 PELANGGAN + MEMBER ----------------

    private function stepPelanggan(bool $reportOnly): void
    {
        $this->info("\n--- A-03 Pelanggan backfill ---");

        $map = $this->mapSumber('pelanggan', 'pelanggan');
        $pool = [];
        DB::table('pelanggan')
            ->select(['id', 'kode_lama', 'saldo_piutang', 'max_piutang', 'area', 'rayon', 'kota', 'instansi',
                'bergabung_at', 'diskon_persen', 'sales_nama', 'is_migrasi_sid'])
            ->orderBy('id')->chunk(2000, function ($chunk) use (&$pool) {
                foreach ($chunk as $p) {
                    $pool[(int) $p->id] = $p;
                }
            });

        DB::table('sid_retail_raw_pelanggan')->orderBy('id')->chunkById(200, function ($chunk) use ($reportOnly, $map, &$pool) {
            foreach ($chunk as $raw) {
                $kode = trim((string) $raw->kode_sumber);
                $pelangganId = $map[$kode] ?? null;
                if (! $pelangganId) {
                    $this->counts['a03_unmapped']++;

                    continue;
                }
                $this->counts['a03_pelanggan_diproses']++;

                $p = json_decode($raw->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $cur = $pool[$pelangganId] ?? null;
                if (! $cur) {
                    continue;
                }

                $u = [];
                if ($cur->kode_lama === null) {
                    $u['kode_lama'] = $kode;
                }
                foreach (['area', 'rayon', 'kota', 'instansi'] as $f) {
                    if ($cur->{$f} === null && ! empty($p[$f])) {
                        $u[$f] = trim((string) $p[$f]);
                    }
                }
                $join = $this->normalDate($p['join_date'] ?? null);
                if ($join && $cur->bergabung_at === null) {
                    $u['bergabung_at'] = $join;
                }
                if ($cur->diskon_persen === null && isset($p['diskn_penjualan']) && $p['diskn_penjualan'] !== null && $p['diskn_penjualan'] !== '') {
                    $u['diskon_persen'] = (float) $p['diskn_penjualan'];
                }
                if ($cur->sales_nama === null && ! empty($p['sales'])) {
                    $u['sales_nama'] = trim((string) $p['sales']);
                }
                if ($cur->saldo_piutang === null && isset($p['saldo_piutang']) && $p['saldo_piutang'] !== null && $p['saldo_piutang'] !== '') {
                    $u['saldo_piutang'] = (float) $p['saldo_piutang'];
                }
                if ($cur->max_piutang === null && isset($p['max_piutang']) && $p['max_piutang'] !== null && $p['max_piutang'] !== '') {
                    $u['max_piutang'] = (float) $p['max_piutang'];
                }
                $u['is_migrasi_sid'] = true;

                if (! $reportOnly) {
                    DB::table('pelanggan')->where('id', $pelangganId)->update($u);
                }
                $pool[$pelangganId] = (object) array_merge((array) $cur, $u);
                $this->counts['a03_updated']++;
            }
        });
    }

    private function stepMember(bool $reportOnly): void
    {
        $this->info("\n--- A-03b Member merge (4) ---");

        // peta staging pelanggan: UPPER(nama)|telp → kode (untuk nama+telp match via kode_lama)
        $stagingMap = [];
        DB::table('sid_retail_raw_pelanggan')->orderBy('id')->chunkById(200, function ($chunk) use (&$stagingMap) {
            foreach ($chunk as $r) {
                $p = json_decode($r->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $nama = strtoupper(trim((string) ($p['nama'] ?? '')));
                $telp = trim((string) ($p['telp'] ?? ''));
                if ($nama !== '' && $telp !== '') {
                    $stagingMap[$nama.'|'.$telp] = trim((string) $r->kode_sumber);
                }
            }
        });

        DB::table('sid_retail_raw_member')->orderBy('id')->chunkById(200, function ($chunk) use ($reportOnly, $stagingMap) {
            foreach ($chunk as $raw) {
                $p = json_decode($raw->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $kodeSumber = trim((string) $raw->kode_sumber);
                $idKartu = trim((string) ($p['id_kartu'] ?? $kodeSumber));
                $nama = strtoupper(trim((string) ($p['nama'] ?? '')));
                $telp = trim((string) ($p['no_telp'] ?? ''));

                // kode staging pelanggan yang (nama,telp)-nya sama dengan member
                $stagingKode = ($nama !== '' && $telp !== '') ? ($stagingMap[$nama.'|'.$telp] ?? null) : null;

                // urutan match: kode_member → kode_lama → nama+telp (app) → nama+telp (via staging kode)
                $pelanggan = DB::table('pelanggan')->where('kode_member', $kodeSumber)->first()
                    ?? DB::table('pelanggan')->where('kode_lama', $kodeSumber)->first()
                    ?? ($nama !== '' && $telp !== ''
                        ? DB::table('pelanggan')->whereRaw('UPPER(TRIM(nama)) = ?', [$nama])
                            ->where('telepon', $telp)
                            ->first()
                        : null)
                    ?? ($stagingKode
                        ? DB::table('pelanggan')->where('kode_lama', $stagingKode)->first()
                        : null);

                if (! $pelanggan) {
                    $this->counts['a03_member_unmatched']++;
                    $this->memberUnmatched[] = sprintf('%s (id_kartu=%s, telp=%s)', $p['nama'] ?? '?', $idKartu, $telp ?: '-');

                    continue;
                }
                $this->counts['a03_member_merge']++;

                $u = [];
                if ($pelanggan->kode_member === null) {
                    $u['kode_member'] = $idKartu;
                }
                $noKartu = trim((string) ($p['no_kartu'] ?? ''));
                if ($noKartu !== '' && ($pelanggan->no_kartu === null || $pelanggan->no_kartu === '')) {
                    $u['no_kartu'] = $noKartu;
                }
                $expired = $this->normalDate($p['expired'] ?? null);
                if ($expired && $pelanggan->tier_expired_at === null) {
                    $u['tier_expired_at'] = $expired;
                }
                $point = (float) ($p['point'] ?? 0);
                $poinCurrent = (float) ($pelanggan->poin_loyalty ?? 0);
                if ($point > $poinCurrent) {
                    $u['poin_loyalty'] = $point;
                }
                $u['is_migrasi_sid'] = true;

                if (! $reportOnly) {
                    DB::table('pelanggan')->where('id', $pelanggan->id)->update($u);
                    DB::table('sid_import_map')->updateOrInsert(
                        ['kode_sumber' => $kodeSumber, 'tabel_sumber' => 'member'],
                        ['entity_type' => 'pelanggan', 'entity_id' => $pelanggan->id, 'updated_at' => now(), 'created_at' => now()]
                    );
                }
            }
        });
    }

    // ---------------- A-04 SUPPLIER ----------------

    private function stepSupplier(bool $reportOnly): void
    {
        $this->info("\n--- A-04 Supplier backfill ---");

        $map = $this->mapSumber('supplier', 'supplier');
        $pool = [];
        DB::table('supplier')->select(['id', 'kode_lama', 'npwp', 'saldo_deposit', 'kontak', 'telepon', 'alamat', 'is_active'])
            ->orderBy('id')->chunk(500, function ($chunk) use (&$pool) {
                foreach ($chunk as $s) {
                    $pool[(int) $s->id] = $s;
                }
            });

        DB::table('sid_retail_raw_supplier')->orderBy('id')->chunkById(200, function ($chunk) use ($reportOnly, $map, &$pool) {
            foreach ($chunk as $raw) {
                $kode = trim((string) $raw->kode_sumber);
                $supplierId = $map[$kode] ?? null;
                if (! $supplierId) {
                    $this->counts['a04_unmapped']++;

                    continue;
                }
                $this->counts['a04_supplier_diproses']++;

                $p = json_decode($raw->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $cur = $pool[$supplierId] ?? null;
                if (! $cur) {
                    continue;
                }

                $u = [];
                if ($cur->kode_lama === null) {
                    $u['kode_lama'] = $kode;
                }
                $npwp = trim((string) ($p['no_npwp'] ?? ''));
                if ($npwp !== '' && $cur->npwp === null) {
                    $u['npwp'] = $npwp;
                }
                if ($cur->saldo_deposit === null && isset($p['saldo_deposit']) && $p['saldo_deposit'] !== null && $p['saldo_deposit'] !== '') {
                    $u['saldo_deposit'] = (float) $p['saldo_deposit'];
                }
                if (! $cur->is_active) {
                    $u['is_active'] = true;
                }
                $kontak = trim((string) ($p['contact'] ?? ''));
                if ($cur->kontak === null && $kontak !== '') {
                    $u['kontak'] = $kontak;
                }
                $telp = trim((string) ($p['nomor'] ?? $p['telp'] ?? ''));
                if ($cur->telepon === null && $telp !== '') {
                    $u['telepon'] = $telp;
                }
                $alamat = trim((string) ($p['alamat'] ?? ''));
                if ($cur->alamat === null && $alamat !== '') {
                    $u['alamat'] = $alamat;
                }

                if (! $u) {
                    continue;
                }
                if (! $reportOnly) {
                    DB::table('supplier')->where('id', $supplierId)->update($u);
                }
                $pool[$supplierId] = (object) array_merge((array) $cur, $u);
                $this->counts['a04_updated']++;
            }
        });
    }

    // ---------------- A-05 VARIAN ----------------

    private function stepVarian(bool $reportOnly): void
    {
        $this->info("\n--- A-05 sku_variants.kode_lama ---");

        // kode_lama untuk SEMUA produk yang di-map via sid_import_map (barang → sku_variant)
        DB::table('sid_import_map')
            ->where('tabel_sumber', 'barang')
            ->where('entity_type', 'sku_variant')
            ->orderBy('id')
            ->chunkById(1000, function ($chunk) use ($reportOnly) {
                foreach ($chunk as $m) {
                    $sudah = DB::table('sku_variants')->where('id', $m->entity_id)->whereNotNull('kode_lama')->exists();
                    if ($sudah) {
                        $this->counts['a05_sku_sudah']++;

                        continue;
                    }
                    if (! $reportOnly) {
                        DB::table('sku_variants')->where('id', $m->entity_id)->whereNull('kode_lama')->update(['kode_lama' => $m->kode_sumber]);
                    }
                    $this->counts['a05_sku_filled']++;
                }
            });
    }

    // ---------------- LAPORAN ----------------

    private function laporanDedupPelanggan(): void
    {
        // Dedup (nama, telp) dari staging pelanggan — laporan saja, tanpa merge.
        $rows = [];
        DB::table('sid_retail_raw_pelanggan')->orderBy('id')->chunkById(200, function ($chunk) use (&$rows) {
            foreach ($chunk as $r) {
                $p = json_decode($r->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $rows[] = [
                    'kode' => trim((string) $r->kode_sumber),
                    'nama' => strtoupper(trim((string) ($p['nama'] ?? ''))),
                    'telp' => trim((string) ($p['telp'] ?? '')),
                    'join' => $this->normalDate($p['join_date'] ?? null),
                    'nama_raw' => trim((string) ($p['nama'] ?? '')),
                ];
            }
        });

        $byKey = [];
        foreach ($rows as $r) {
            $byKey[$r['nama'].'|'.$r['telp']][] = $r;
        }
        $groups = 0;
        $groupRows = 0;
        $lines = [];
        foreach ($byKey as $key => $list) {
            if (count($list) < 2) {
                continue;
            }
            $groups++;
            $groupRows += count($list);
            usort($list, function ($a, $b) {
                // join_date terlama di depan; sentinel/null di belakang
                $aj = $a['join'] ?? '9999-12-31';
                $bj = $b['join'] ?? '9999-12-31';

                return strcmp((string) $aj, (string) $bj);
            });
            $canonical = $list[0]['kode'];
            $kodeList = implode(', ', array_column($list, 'kode'));
            $lines[] = sprintf('- **%s** (telp `%s`) — %d baris; kode: %s; canonical: `%s`',
                $list[0]['nama_raw'], $list[0]['telp'], count($list), $kodeList, $canonical);
        }
        $this->reportLines[] = '### Dedup pelanggan (nama, telp) — LAPORAN SAJA (merge = fase berikutnya)';
        $this->reportLines[] = '';
        $this->reportLines[] = "Grup duplikat: **{$groups}**, total baris: **{$groupRows}**";
        $this->reportLines[] = '';
        $this->reportLines = array_merge($this->reportLines, $lines);
        $this->reportLines[] = '';
    }

    private function laporanUnmappedProduk(bool $reportOnly): void
    {
        $mappedIds = DB::table('sid_import_map')->where('tabel_sumber', 'barang')->where('entity_type', 'sku_variant')->pluck('entity_id');
        $unmapped = DB::table('sku_variants')->whereNotIn('sku_variants.id', $mappedIds)
            ->join('produk', 'produk.id', '=', 'sku_variants.produk_id')
            ->select(['sku_variants.id as sku_id', 'sku_variants.produk_id', 'sku_variants.sku', 'produk.nama'])
            ->orderBy('sku_variants.id')->get();

        // nama persis di staging barang
        $set = [];
        DB::table('sid_retail_raw_barang')->orderBy('id')->chunkById(500, function ($chunk) use (&$set) {
            foreach ($chunk as $b) {
                $p = json_decode($b->payload_normal, true);
                if ($p && ! empty($p['nama'])) {
                    $set[trim((string) $p['nama'])] = trim((string) $b->kode_sumber);
                }
            }
        });

        $lines = [];
        foreach ($unmapped as $u) {
            $match = $set[$u->nama] ?? null;
            $lines[] = sprintf('- produk_id=%d nama=`%s` → staging nama-match: %s',
                $u->produk_id, $u->nama, $match ? "ADA (kode {$match})" : 'TIDAK ADA');
            if (! $reportOnly && $match) {
                // catat saja; mapping baru untuk produk app dilakukan di fase berikutnya
                $this->unmappedProduk[] = sprintf('produk_id=%d nama=`%s` match kode `%s`', $u->produk_id, $u->nama, $match);
            } else {
                $this->unmappedProduk[] = sprintf('produk_id=%d nama=`%s` TIDAK ADA di staging', $u->produk_id, $u->nama);
            }
        }
        $this->reportLines[] = '### A-05 — produk app tanpa map SID (6)';
        $this->reportLines[] = '';
        $this->reportLines[] = 'Jumlah produk ter-map via sid_import_map (sku_variant): **'.$mappedIds->count().'**; unmapped: **'.$unmapped->count().'**';
        $this->reportLines[] = '';
        $this->reportLines = array_merge($this->reportLines, $lines);
        $this->reportLines[] = '';
    }

    private function tulisLaporan(): void
    {
        $dir = storage_path('app/migrasi-sid');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir.'/A-02-A-05-laporan-'.date('Ymd-His').'.md';
        $body = '# LAPORAN FASE 2A (A-02..A-05) — '.date('Y-m-d H:i:s')."\n\n"
            ."Perintah: `php artisan sid:backfill-2a` (idempotent, non-destruktif)\n\n"
            ."## Ringkasan\n\n"
            ."- A-02 produk diproses: {$this->counts['a02_produk_diproses']} (unmapped: {$this->counts['a02_unmapped']})\n"
            ."- A-02 kode_lama baru: {$this->counts['a02_kode_lama']}, barcode baru: {$this->counts['a02_barcode']}, barcode_alt baru: {$this->counts['a02_barcode_alt']}\n"
            ."- A-02 brand_id baru: {$this->counts['a02_brand_baru']}, merk tanpa brand (skip): {$this->counts['a02_brand_skip']}\n"
            ."- A-03 pelanggan diproses: {$this->counts['a03_pelanggan_diproses']} (unmapped: {$this->counts['a03_unmapped']})\n"
            ."- A-03 member merge: {$this->counts['a03_member_merge']}, unmatched: {$this->counts['a03_member_unmatched']} (TIDAK dibuat buta)\n"
            ."- A-04 supplier diproses: {$this->counts['a04_supplier_diproses']} (unmapped: {$this->counts['a04_unmapped']})\n"
            ."- A-05 sku_variants kode_lama baru: {$this->counts['a05_sku_filled']}, sudah terisi: {$this->counts['a05_sku_sudah']}\n\n";

        $body .= "## Member tanpa match pelanggan\n\n";
        if ($this->memberUnmatched) {
            foreach ($this->memberUnmatched as $m) {
                $body .= "- {$m}\n";
            }
        } else {
            $body .= "- (tidak ada)\n";
        }
        $body .= "\n## Merk tanpa brand di tabel brands (skip brand_id)\n\n";
        if ($this->brandSkip) {
            foreach ($this->brandSkip as $merk => $n) {
                $body .= "- {$merk} × {$n}\n";
            }
        } else {
            $body .= "- (tidak ada)\n";
        }
        $body .= "\n## Produk app tanpa map SID\n\n";
        foreach ($this->unmappedProduk as $u) {
            $body .= "- {$u}\n";
        }
        $body .= "\n".implode("\n", $this->reportLines)."\n";

        file_put_contents($file, $body);
        $this->info("Laporan: {$file}");
    }

    private function printSummary(): void
    {
        $this->info("\n=== SUMMARY FASE 2A ===");
        foreach ($this->counts as $k => $v) {
            $this->line("  {$k}: {$v}");
        }

        $this->info("\n=== VERIFIKASI (state DB) ===");
        $this->line('  produk kode_lama: '.DB::table('produk')->whereNotNull('kode_lama')->count());
        $this->line('  produk barcode: '.DB::table('produk')->whereNotNull('barcode')->count());
        $this->line('  pelanggan kode_lama: '.DB::table('pelanggan')->whereNotNull('kode_lama')->count());
        $this->line('  pelanggan kode_member: '.DB::table('pelanggan')->whereNotNull('kode_member')->count());
        $this->line('  supplier kode_lama: '.DB::table('supplier')->whereNotNull('kode_lama')->count());
        $this->line('  sku_variants kode_lama: '.DB::table('sku_variants')->whereNotNull('kode_lama')->count());
    }

    // ---------------- UTIL ----------------

    private function mapSumber(string $tabelSumber, string $entityType): array
    {
        $map = [];
        DB::table('sid_import_map')->where('tabel_sumber', $tabelSumber)->where('entity_type', $entityType)
            ->orderBy('id')->chunkById(1000, function ($chunk) use (&$map) {
                foreach ($chunk as $m) {
                    $map[$m->kode_sumber] = (int) $m->entity_id;
                }
            });

        return $map;
    }

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
            $d = Carbon::parse($s);

            return $d->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
