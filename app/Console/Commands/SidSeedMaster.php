<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [MIGRASI-SID fase 1, M-04..M-07] Seed master referensi dari staging raw:
 * - M-04 brands         <- barang.merk (dedup case-insensitive) + map produk.brand_id
 * - M-05 gudang         <- setup_perusahaan (gudang + toko1..toko15)
 * - M-06 tier           <- grouphrgpelanggan (SKIP bila staging belum ada)
 * - M-07 profil toko    <- setup_perusahaan -> konfigurasi (prefix profil.*, hpp.*, faktur.*)
 *
 * IDEMPOTEN: updateOrCreate by natural key — jalankan 2x hasil sama,
 * tanpa menyentuh data operasional app. Dry-run wajib (mandat PRD §4.11).
 */
class SidSeedMaster extends Command
{
    protected $signature = 'sid:seed-master
        {--dry-run : Preview tanpa menulis DB}
        {--only= : Filter seed (koma): brands,gudang,tier,profil}';

    protected $description = 'Seed master referensi SID Retail (brands, gudang, tier, profil toko) — idempotent';

    private bool $dryRun = false;

    /** @var array<string,int> */
    private array $stats = [
        'brands' => 0,
        'brand_map' => 0,
        'gudang' => 0,
        'tier' => 0,
        'profil' => 0,
    ];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $only = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('only')))));
        $run = fn (string $name, callable $fn) => ($only === [] || in_array($name, $only, true));

        $this->info('=== Seed Master SID → Ute Parts ('.($this->dryRun ? 'DRY-RUN' : 'EKSEKUSI').') ===');

        if ($run('brands', fn () => null)) {
            $this->seedBrands();
        }
        if ($run('gudang', fn () => null)) {
            $this->seedGudang();
        }
        if ($run('tier', fn () => null)) {
            $this->seedTier();
        }
        if ($run('profil', fn () => null)) {
            $this->seedProfil();
        }

        $this->newLine();
        foreach ($this->stats as $key => $val) {
            $this->line(sprintf('  %-12s: %d', $key, $val));
        }
        if ($this->dryRun) {
            $this->warn('DRY-RUN — tidak ada data ditulis ke DB.');
        }

        return self::SUCCESS;
    }

    /** M-04: brands dari barang.merk + map produk.brand_id. */
    private function seedBrands(): void
    {
        $this->info("\n--- M-04: Brands dari barang.merk ---");

        // Map case-insensitive brand existing + yang dibuat di run ini.
        $map = [];
        foreach (DB::table('brands')->get(['id', 'nama']) as $b) {
            $map[strtolower(trim((string) $b->nama))] ??= (int) $b->id;
        }

        // Distinct merk non-empty (canonical = casing pertama yang ditemukan).
        $merkSet = [];
        DB::table('sid_retail_raw_barang')->orderBy('id')->chunkById(500, function ($chunk) use (&$merkSet) {
            foreach ($chunk as $raw) {
                $p = json_decode($raw->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $merk = trim((string) ($p['merk'] ?? ''));
                if ($merk === '') {
                    continue;
                }
                $merkSet[strtolower($merk)] ??= $merk;
            }
        });

        $now = now();
        $brandIdByMerk = []; // lower(merk) => brand id
        foreach ($merkSet as $lower => $canonical) {
            if (isset($map[$lower])) {
                $brandIdByMerk[$lower] = $map[$lower];
                // Pastikan keterangan/tanda migrasi seragam (idempoten).
                if (! $this->dryRun) {
                    DB::table('brands')->where('id', $map[$lower])
                        ->where(fn ($q) => $q->whereNull('keterangan')->orWhere('keterangan', '<>', 'Migrasi SID'))
                        ->update(['keterangan' => 'Migrasi SID']);
                }

                continue;
            }
            $this->stats['brands']++;
            if ($this->dryRun) {
                $this->line(sprintf('  [DRY] Brand baru: %s', $canonical));
                $brandIdByMerk[$lower] = null;

                continue;
            }
            $id = DB::table('brands')->insertGetId([
                'nama' => $canonical,
                'keterangan' => 'Migrasi SID',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $map[$lower] = $id;
            $brandIdByMerk[$lower] = $id;
        }

        if ($this->dryRun) {
            $this->line('  (brand_map diff dihitung pada eksekusi nyata)');

            return;
        }

        // Map produk.brand_id: cocok via kode_lama bila sudah terisi (A-02), fallback
        // sid_import_map entity sku_variant → sku_variants.produk_id (kode_lama belum
        // di-backfill fase ini; map barang hanya menyimpan entity terakhir = sku_variant).
        // Hanya brand_id kosong yang diisi.
        $produkByKode = DB::table('produk')->whereNotNull('kode_lama')->pluck('id', 'kode_lama')->all();
        $variantByKode = DB::table('sid_import_map')
            ->where('tabel_sumber', 'barang')
            ->where('entity_type', 'sku_variant')
            ->pluck('entity_id', 'kode_sumber')
            ->all();
        $produkByVariant = DB::table('sku_variants')->pluck('produk_id', 'id')->all();
        foreach ($variantByKode as $kode => $variantId) {
            $produkByKode[$kode] ??= $produkByVariant[$variantId] ?? null;
        }

        $updates = [];
        DB::table('sid_retail_raw_barang')->orderBy('id')->chunkById(500, function ($chunk) use (&$updates, $brandIdByMerk, $produkByKode) {
            foreach ($chunk as $raw) {
                $p = json_decode($raw->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $merk = trim((string) ($p['merk'] ?? ''));
                if ($merk === '') {
                    continue;
                }
                $brandId = $brandIdByMerk[strtolower($merk)] ?? null;
                if (! $brandId) {
                    continue;
                }
                $kode = (string) ($p['kode'] ?? trim((string) $raw->kode_sumber));
                $produkId = $produkByKode[$kode] ?? null;
                if ($produkId) {
                    $updates[$produkId] = $brandId;
                }
            }
        });

        foreach (array_chunk($updates, 500, true) as $chunkMap) {
            foreach ($chunkMap as $produkId => $brandId) {
                $this->stats['brand_map'] += DB::table('produk')
                    ->where('id', $produkId)
                    ->whereNull('brand_id')
                    ->update(['brand_id' => $brandId]);
            }
        }
        $this->line(sprintf('  brand_id baru terisi: %d (total terisi: %d)', $this->stats['brand_map'], DB::table('produk')->whereNotNull('brand_id')->count()));
    }

    /** M-05: gudang per cabang default dari setup_perusahaan (gudang + toko1..15). */
    private function seedGudang(): void
    {
        $this->info("\n--- M-05: Gudang per cabang default dari setup_perusahaan ---");

        $cabang = DB::table('cabang')->orderBy('id')->first();
        if (! $cabang) {
            $this->warn('Tidak ada cabang — skip gudang.');

            return;
        }
        $cabangId = (int) $cabang->id;

        $setup = DB::table('sid_retail_raw_setup_perusahaan')->first();
        $p = $setup ? json_decode($setup->payload_normal, true) : null;
        if (! is_array($p)) {
            $this->warn('setup_perusahaan kosong/tidak valid — skip gudang.');

            return;
        }

        $lokasi = ['gudang' => 'GUDANG'];
        for ($i = 1; $i <= 15; $i++) {
            $lokasi['toko'.$i] = 'TOKO'.$i;
        }

        foreach ($lokasi as $key => $kode) {
            if ($this->isEmpty($p[$key] ?? null)) {
                continue;
            }
            $nama = trim((string) $p[$key]);
            $this->stats['gudang']++;
            if ($this->dryRun) {
                $this->line(sprintf('  [DRY] Gudang %s (%s)', $kode, $nama));

                continue;
            }
            $now = now();
            DB::table('gudang')->updateOrInsert(
                ['cabang_id' => $cabangId, 'kode' => $kode],
                ['nama' => $nama, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }
        $this->line(sprintf('  Cabang default: %s (id %d)', $cabang->kode, $cabangId));
    }

    /** M-06: tier_memberships dari grouphrgpelanggan — SKIP bila staging belum ada. */
    private function seedTier(): void
    {
        $this->info("\n--- M-06: Tier memberships dari grouphrgpelanggan ---");

        if (! Schema::hasTable('sid_retail_raw_grouphrgpelanggan')) {
            $this->warn('Staging sid_retail_raw_grouphrgpelanggan BELUM ada — tier di-skip (ditangani lane staging lain).');

            return;
        }

        $urutan = (int) DB::table('tier_memberships')->count();
        DB::table('sid_retail_raw_grouphrgpelanggan')->orderBy('id')->chunkById(200, function ($chunk) use (&$urutan) {
            foreach ($chunk as $raw) {
                $p = json_decode($raw->payload_normal, true);
                if (! is_array($p)) {
                    continue;
                }
                $kode = trim((string) ($p['kode'] ?? $p['kdgrouphrg'] ?? $raw->kode_sumber));
                $nama = trim((string) ($p['nama'] ?? $kode));
                if ($kode === '' || $nama === '') {
                    continue;
                }
                $urutan++;
                $this->stats['tier']++;
                if ($this->dryRun) {
                    $this->line(sprintf('  [DRY] Tier HRG-%s (%s)', $kode, $nama));

                    continue;
                }
                $now = now();
                DB::table('tier_memberships')->updateOrInsert(
                    ['kode' => 'HRG-'.$kode],
                    [
                        'nama' => $nama,
                        'min_belanja_12bulan' => 0,
                        'diskon_persen' => 0,
                        'poin_multiplier' => 1,
                        'urutan' => $urutan,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        });
    }

    /** M-07: profil toko setup_perusahaan → konfigurasi (upsert by kunci). */
    private function seedProfil(): void
    {
        $this->info("\n--- M-07: Profil toko dari setup_perusahaan → konfigurasi ---");

        $setup = DB::table('sid_retail_raw_setup_perusahaan')->first();
        $p = $setup ? json_decode($setup->payload_normal, true) : null;
        if (! is_array($p)) {
            $this->warn('setup_perusahaan kosong/tidak valid — skip profil.');

            return;
        }

        $kunciMap = [
            'profil.nama_toko' => 'nama',
            'profil.alamat' => 'alamat',
            'profil.telp' => 'telp',
            'profil.fax' => 'fax',
            'profil.kota' => 'kota',
            'profil.propinsi' => 'propinsi',
            'profil.negara' => 'negara',
            'profil.logo' => 'logo',
            'profil.kode_lokasi' => 'kode_lokasi',
            'profil.footer_faktur' => 'footer_faktur',
            'hpp.metode' => 'metode_hpp',
            'faktur.format' => 'formatfaktur',
            'faktur.digit' => 'formatfaktur_digit',
            'faktur.kode_penjualan' => 'kd_penjualan_toko',
            'faktur.kode_po' => 'kd_po',
        ];

        $deskripsi = [
            'profil.nama_toko' => 'Nama toko (migrasi SID)',
            'hpp.metode' => 'Metode HPP (migrasi SID)',
            'faktur.format' => 'Format nomor faktur (migrasi SID)',
            'faktur.digit' => 'Digit nomor faktur (migrasi SID)',
            'faktur.kode_penjualan' => 'Kode prefix faktur penjualan (migrasi SID)',
            'faktur.kode_po' => 'Kode prefix PO (migrasi SID)',
        ];

        foreach ($kunciMap as $kunci => $srcKey) {
            $val = $p[$srcKey] ?? null;
            if ($this->isEmpty($val) && $srcKey === 'metode_hpp') {
                $val = $p['metodehpp'] ?? null; // fallback nama kolom lama
            }
            if ($this->isEmpty($val)) {
                continue;
            }
            $nilai = $this->normalizeValue($val);
            $this->stats['profil']++;
            if ($this->dryRun) {
                $this->line(sprintf('  [DRY] %s = %s', $kunci, $nilai));

                continue;
            }
            $now = now();
            DB::table('konfigurasi')->updateOrInsert(
                ['kunci' => $kunci],
                ['nilai' => $nilai, 'deskripsi' => $deskripsi[$kunci] ?? 'Profil toko (migrasi SID)', 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    /** Nilai SID yang dianggap kosong: null, false, 0/0.00, '', 'False' (case-insensitive). */
    private function isEmpty(mixed $v): bool
    {
        if ($v === null) {
            return true;
        }
        if (is_bool($v)) {
            return ! $v;
        }
        if (is_numeric($v) && (float) $v == 0) {
            return true;
        }
        $s = trim((string) $v);

        return $s === '' || in_array(strtolower($s), ['false', '0', '0.0', '0.00'], true);
    }

    /** Normalisasi nilai utk kolom string konfigurasi (bool → '1'). */
    private function normalizeValue(mixed $v): string
    {
        if (is_bool($v)) {
            return '1';
        }
        if (is_numeric($v)) {
            return (string) $v;
        }
        $s = trim((string) $v);

        return strtolower($s) === 'true' ? '1' : $s;
    }
}
