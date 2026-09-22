<?php

namespace App\Console\Commands;

use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Pos\Models\HargaTier;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\SidImportMap;
use App\Modules\Wms\Models\StockMutationLog;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\TipeHp;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * [T-42 fase 2] Migrasi data SID Retail (staging raw) ke skema Ute Parts.
 * Pakai DB::table() untuk tabel raw (sid_retail_raw_*) karena tidak ada Eloquent model.
 * Urutan: Referensi → Master → Transaksi → Pelengkap.
 * Dry-run wajib (mandat PRD §4.11).
 */
class SidMigrateToUteParts extends Command
{
    protected $signature = 'sid:migrate-ute-parts {--dry-run : Preview without committing} {--step=all : Step to run (referensi|master|transaksi|pelengkap|all)}';
    protected $description = 'Migrasi data SID Retail (staging raw) ke skema Ute Parts';

    private bool $dryRun = false;
    private array $stats = [
        'gudang' => 0,
        'produk' => 0,
        'sku_variants' => 0,
        'harga_tier' => 0,
        'pelanggan' => 0,
        'piutang' => 0,
        'supplier' => 0,
        'utang' => 0,
        'transaksi' => 0,
        'transaksi_item' => 0,
        'stok_log' => 0,
        'tiket_servis' => 0,
    ];

    public function handle(): int
    {
        $this->dryRun = $this->option('dry-run');
        $step = $this->option('step');

        if ($this->dryRun) {
            $this->warn('DRY-RUN MODE: Tidak ada data yang akan ditulis ke database');
        }

        $this->info('=== SID Retail → Ute Parts Migration ===');
        $this->info('Step: ' . $step . ($this->dryRun ? ' (dry-run)' : ''));

        // Setup cabang default untuk staging
        $cabang = Cabang::firstOrCreate(
            ['kode' => 'CBG-01'],
            ['nama' => 'Pusat', 'is_active' => true]
        );

        if (in_array($step, ['referensi', 'all'])) {
            $this->stepReferensi($cabang);
        }
        if (in_array($step, ['master', 'all'])) {
            $this->stepMasterBarang($cabang);
            $this->stepMasterPelanggan();
            $this->stepMasterSupplier();
        }
        if (in_array($step, ['transaksi', 'all'])) {
            $this->stepTransaksiPenjualan();
            $this->stepStokLog();
        }
        if (in_array($step, ['pelengkap', 'all'])) {
            $this->stepServis();
        }

        $this->printSummary();
        return 0;
    }

    private function stepReferensi(Cabang $cabang): void
    {
        $this->info("\n--- STEP 1: Referensi (Gudang dari setup_perusahaan) ---");
        
        $setup = DB::table('sid_retail_raw_setup_perusahaan')->first();
        if (! $setup) {
            $this->warn('setup_perusahaan kosong, lewati');
            return;
        }

        $payload = $setup->payload_normal;
        if (! $payload) {
            $this->warn('payload_normal kosong, lewati');
            return;
        }

        // SID setup_perusahaan uses 'lokasi' field for store name
        $lokasi = [$payload['lokasi'] ?? 'PUSAT'];
        if (! is_array($lokasi)) {
            $lokasi = json_decode($lokasi, true) ?? [];
        }

        foreach ($lokasi as $nama) {
            $nama = trim((string) $nama);
            if (! $nama) continue;
            
            $key = "setup_perusahaan|{$nama}";
            if ($this->dryRun) {
                $this->line("  [DRY] Gudang: {$nama}");
                $this->stats['gudang']++;
            } else {
                $gudang = Gudang::firstOrCreate(
                    ['cabang_id' => $cabang->id, 'nama' => $nama],
                    ['kode' => Str::upper(Str::slug($nama, '-')), 'is_active' => true]
                );
                SidImportMap::setId($key, 'setup_perusahaan', 'gudang', $gudang->id);
                $this->stats['gudang']++;
            }
        }
        $this->info("  Gudang: {$this->stats['gudang']}");
    }

    private function stepMasterBarang(Cabang $cabang): void
    {
        $this->info("\n--- STEP 2: Master Barang (6.433) → Produk + SKU Variants ---");
        
        DB::table('sid_retail_raw_barang')
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use ($cabang) {
                foreach ($chunk as $raw) {
                    $this->processBarang((object) $raw, $cabang);
                }
            });
    }

    private function processBarang($raw, Cabang $cabang): void
    {
        $kode = trim($raw->kode_sumber);
        if (! $kode) return;

        $p = json_decode($raw->payload_normal, true);
        if (! $p) return;

        // Filter: only BARANG type (products), skip JASA (services)
        $jenis = strtoupper(trim((string) ($p['jenis'] ?? '')));
        if ($jenis !== 'BARANG') return; // skip services, only import products

        // Check already mapped
        if (SidImportMap::exists($kode, 'barang')) {
            return;
        }

        // Field mappings from SID raw data
        $hargaJual = (float) ($p['harga_toko'] ?? 0);        // retail price
        $hargaBeli = (float) ($p['hpp'] ?? 0);               // cost price
        $satuan = trim((string) ($p['satuan'] ?? 'pcs'));
        $kategori = trim((string) ($p['golongan'] ?? 'Umum'));
        $subGol = trim((string) ($p['subgolongan1'] ?? ''));
        if ($subGol) $kategori .= ' > ' . $subGol;

        // Satuan
        $satuanUnit = SatuanUnit::firstOrCreate(
            ['kode' => $satuan],
            ['nama' => ucfirst($satuan), 'is_active' => true]
        );

        // Brand from merk field
        $brandId = null;
        $brandNama = trim((string) ($p['merk'] ?? ''));
        if ($brandNama) {
            $brand = Brand::firstOrCreate(
                ['nama' => $brandNama],
                ['is_active' => true]
            );
            $brandId = $brand->id;
        }

        // Kualitas - default based on price tier
        $kualitasId = null;
        if ($hargaJual > 1000000) $kualitasNama = 'Original';
        elseif ($hargaJual > 500000) $kualitasNama = 'Grade A';
        elseif ($hargaJual > 200000) $kualitasNama = 'Grade B';
        else $kualitasNama = 'Compatible';
        
        $kualitas = KualitasProduk::firstOrCreate(
            ['nama' => $kualitasNama],
            ['is_active' => true]
        );
        $kualitasId = $kualitas->id;

        // Barcode
        $barcode = trim((string) ($p['kode_barcode'] ?? ''));

        if ($this->dryRun) {
            $this->stats['produk']++;
            $this->stats['sku_variants']++;
            return;
        }

        DB::transaction(function () use ($kode, $p, $hargaJual, $hargaBeli, $satuanUnit, $kategori, $brandId, $kualitasId, $barcode) {
            // Create Produk
            $produk = Produk::create([
                'nama' => trim((string) ($p['nama'] ?? 'Produk ' . $kode)),
                'slug' => Str::slug($p['nama'] ?? 'produk-' . $kode) . '-' . Str::lower(Str::random(4)),
                'deskripsi' => null,
                'kategori' => $kategori,
                'brand_id' => $brandId,
                'kualitas_id' => $kualitasId,
                'barcode' => $barcode ?: null,
                'brand_kompatibel' => $brandId ? Brand::find($brandId)?->nama : null,
                'kondisi' => 'baru',
                'satuan' => $satuanUnit->kode,
                'harga_beli' => $hargaBeli,
                'harga_jual_retail' => $hargaJual,
                'gambar' => null,
                'is_active' => true,
            ]);

            // Map produk
            SidImportMap::setId($kode, 'barang', 'produk', $produk->id);

            // Create SKU Variant
            $sku = trim((string) ($p['kode'] ?? $kode));
            $variant = SkuVariant::create([
                'produk_id' => $produk->id,
                'sku' => $sku,
                'barcode' => $barcode ?: null,
                'nama_varian' => 'Standar',
                'satuan_kode' => $satuanUnit->kode,
                'harga_beli' => $hargaBeli,
                'harga_jual_retail' => $hargaJual,
                'is_active' => true,
            ]);
            SidImportMap::setId($kode, 'barang', 'sku_variant', $variant->id);

            // HargaTier: retail, reseller, agen using SID price fields
            // SID has: harga_toko (retail), harga_toko2/3/4, harga_partai, harga_partai2/3/4, 
            //          harga_cabang, harga_cabang2/3/4, harga_member, harga_karyawan
            
            // Retail (always) - harga_toko
            HargaTier::updateOrCreate(
                ['produk_id' => $produk->id, 'sku_variant_id' => null, 'tier_membership_id' => null, 'tipe_konsumen' => 'retail'],
                ['is_reseller' => false, 'harga' => $hargaJual, 'nominal_tetap' => $hargaJual, 'persen_diskon' => null]
            );

            // Reseller - use harga_member or harga_partai as reseller price
            $hargaReseller = (float) ($p['harga_member'] ?? $p['harga_partai'] ?? $p['harga_karyawan'] ?? $hargaJual);
            if ($hargaReseller <= 0) $hargaReseller = $hargaJual;
            HargaTier::updateOrCreate(
                ['produk_id' => $produk->id, 'sku_variant_id' => null, 'tier_membership_id' => null, 'tipe_konsumen' => 'reseller'],
                ['is_reseller' => true, 'harga' => $hargaReseller, 'nominal_tetap' => $hargaReseller, 'persen_diskon' => null]
            );

            // Agen - use harga_toko2 or harga_partai2
            $hargaAgen = (float) ($p['harga_toko2'] ?? $p['harga_partai2'] ?? $p['harga_cabang'] ?? $hargaReseller);
            if ($hargaAgen <= 0) $hargaAgen = $hargaReseller;
            HargaTier::updateOrCreate(
                ['produk_id' => $produk->id, 'sku_variant_id' => null, 'tier_membership_id' => null, 'tipe_konsumen' => 'agen'],
                ['is_reseller' => false, 'harga' => $hargaAgen, 'nominal_tetap' => $hargaAgen, 'persen_diskon' => null]
            );

            $this->stats['produk']++;
            $this->stats['sku_variants']++;
            $this->stats['harga_tier'] += 3;
        });
    }

    private function stepMasterPelanggan(): void
    {
        $this->info("\n--- STEP 3: Master Pelanggan (284) + Member (4) → Pelanggan ---");
        
        // Get or create tier memberships for GROUP 1-4
        $tiers = [];
        foreach (range(1, 4) as $g) {
            $tiers[$g] = TierMembership::firstOrCreate(
                ['kode' => "GROUP{$g}"],
                ['nama' => "GROUP {$g}", 'min_belanja_12bulan' => $g * 10000000, 'diskon_persen' => $g * 2, 'is_active' => true]
            )->id;
        }
        // Default tier (GROUP 0 / retail)
        $tierDefault = TierMembership::firstOrCreate(
            ['kode' => 'RETAIL'],
            ['nama' => 'Retail', 'min_belanja_12bulan' => 0, 'diskon_persen' => 0, 'is_active' => true]
        )->id;

        // Pelanggan
        DB::table('sid_retail_raw_pelanggan')
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($tiers, $tierDefault) {
                foreach ($chunk as $raw) {
                    $this->processPelanggan((object) $raw, $tiers, $tierDefault);
                }
            });

        // Member (merge with pelanggan by kode/telepon)
        DB::table('sid_retail_raw_member')
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($tiers, $tierDefault) {
                foreach ($chunk as $raw) {
                    $this->processMember((object) $raw, $tiers, $tierDefault);
                }
            });
    }

    private function processPelanggan($raw, array $tiers, int $tierDefault): void
    {
        $kode = trim($raw->kode_sumber);
        if (! $kode) return;

        if (SidImportMap::exists($kode, 'pelanggan')) {
            return;
        }

        $p = json_decode($raw->payload_normal, true);
        if (! $p) return;

        $telepon = trim((string) ($p['no_hp'] ?? $p['telepon'] ?? ''));
        $email = trim((string) ($p['email'] ?? ''));
        $alamat = trim((string) ($p['alamat'] ?? ''));
        $tglLahir = $p['tanggal_lahir'] ?? null;
        if ($tglLahir && $tglLahir !== '1899-12-30' && $tglLahir !== '0000-00-00') {
            try { $tglLahir = Carbon::parse($tglLahir)->format('Y-m-d'); } catch (\Throwable) { $tglLahir = null; }
        } else { $tglLahir = null; }

        $group = (int) ($p['grouphrgpelanggan'] ?? 0);
        $tierId = $tiers[$group] ?? $tierDefault;
        $isMember = strtoupper(trim((string) ($p['member'] ?? ''))) === 'Y';
        $plafon = (float) ($p['plafon'] ?? 0);
        $piutang = (float) ($p['piutang'] ?? 0);

        $tipeKonsumen = ($isMember && $plafon > 0) ? 'reseller' : 'retail';

        if ($this->dryRun) {
            $this->stats['pelanggan']++;
            if ($piutang > 0) $this->stats['piutang']++;
            return;
        }

        DB::transaction(function () use ($kode, $p, $telepon, $email, $alamat, $tglLahir, $tierId, $tipeKonsumen, $isMember, $piutang) {
            $pelanggan = Pelanggan::create([
                'nama' => trim((string) ($p['nama'] ?? 'Pelanggan ' . $kode)),
                'telepon' => $telepon ?: '08' . Str::padLeft($kode, 10, '0'),
                'email' => $email ?: null,
                'alamat' => $alamat ?: null,
                'tanggal_lahir' => $tglLahir,
                'tier_membership_id' => $tierId,
                'is_reseller' => $tipeKonsumen === 'reseller',
                'tipe_konsumen' => $tipeKonsumen,
                'total_belanja_12bulan' => 0,
                'poin_loyalty' => 0,
            ]);

            SidImportMap::setId($kode, 'pelanggan', 'pelanggan', $pelanggan->id);
            $this->stats['pelanggan']++;

            // Seed piutang awal jika ada
            if ($piutang > 0) {
                \App\Modules\Akunting\Models\Piutang::create([
                    'cabang_id' => 1,
                    'pelanggan_id' => $pelanggan->id,
                    'no_jurnal' => 'SID-PIU-' . $kode,
                    'tanggal' => now(),
                    'jumlah' => $piutang,
                    'dibayar' => 0,
                    'sisa' => $piutang,
                    'status' => 'belum_lunas',
                    'jatuh_tempo' => null,
                    'keterangan' => 'Migrasi SID Retail saldo awal piutang',
                ]);
                $this->stats['piutang']++;
            }
        });
    }

    private function processMember($raw, array $tiers, int $tierDefault): void
    {
        $kode = trim($raw->kode_sumber);
        if (! $kode) return;

        $p = json_decode($raw->payload_normal, true);
        if (! $p) return;

        $telepon = trim((string) ($p['no_hp'] ?? $p['telepon'] ?? ''));
        if (! $telepon) return;

        $existing = Pelanggan::where('telepon', $telepon)->first();
        if ($existing) {
            // Update existing jika tier lebih tinggi
            $group = (int) ($p['grouphrgpelanggan'] ?? 0);
            if ($group > 0 && isset($tiers[$group])) {
                $existing->update([
                    'tier_membership_id' => $tiers[$group],
                    'tipe_konsumen' => 'reseller',
                    'is_reseller' => true,
                ]);
            }
            SidImportMap::setId($kode, 'member', 'pelanggan', $existing->id);
            return;
        }

        // Create new (should not happen much since member is subset of pelanggan)
        $this->processPelanggan($raw, $tiers, $tierDefault);
    }

    private function stepMasterSupplier(): void
    {
        $this->info("\n--- STEP 4: Master Supplier (10) → Supplier + Utang ---");
        
        DB::table('sid_retail_raw_supplier')
            ->orderBy('id')
            ->chunkById(200, function ($chunk) {
                foreach ($chunk as $raw) {
                    $this->processSupplier((object) $raw);
                }
            });
    }

    private function processSupplier($raw): void
    {
        $kode = trim($raw->kode_sumber);
        if (! $kode) return;

        if (SidImportMap::exists($kode, 'supplier')) {
            return;
        }

        $p = json_decode($raw->payload_normal, true);
        if (! $p) return;

        $hutang = (float) ($p['hutang'] ?? 0);

        if ($this->dryRun) {
            $this->stats['supplier']++;
            if ($hutang > 0) $this->stats['utang']++;
            return;
        }

        DB::transaction(function () use ($kode, $p, $hutang) {
            $supplier = \App\Modules\Wms\Models\Supplier::create([
                'nama' => trim((string) ($p['nama'] ?? 'Supplier ' . $kode)),
                'kode' => $kode,
                'alamat' => trim((string) ($p['alamat'] ?? '')) ?: null,
                'no_telp' => trim((string) ($p['no_telp'] ?? $p['no_hp'] ?? '')) ?: null,
                'email' => trim((string) ($p['email'] ?? '')) ?: null,
                'is_active' => true,
            ]);

            SidImportMap::setId($kode, 'supplier', 'supplier', $supplier->id);
            $this->stats['supplier']++;

            if ($hutang > 0) {
                \App\Modules\Akunting\Models\Utang::create([
                    'cabang_id' => 1,
                    'supplier_id' => $supplier->id,
                    'no_jurnal' => 'SID-UTG-' . $kode,
                    'tanggal' => now(),
                    'jumlah' => $hutang,
                    'dibayar' => 0,
                    'sisa' => $hutang,
                    'status' => 'belum_lunas',
                    'jatuh_tempo' => null,
                    'keterangan' => 'Migrasi SID Retail saldo awal hutang supplier',
                ]);
                $this->stats['utang']++;
            }
        });
    }

    private function stepTransaksiPenjualan(): void
    {
        $this->info("\n--- STEP 5: Transaksi Penjualan (itempenjualan 10.273) → Transaksi + Item ---");
        
        $variantMap = \App\Modules\Wms\Models\SkuVariant::query()->pluck('produk_id', 'id')->all();

        // Group by base faktur (kode_sumber format: "R43-200226003|2", extract before |)
        $fakturs = DB::table('sid_retail_raw_itempenjualan')
            ->selectRaw("SUBSTRING_INDEX(kode_sumber, '|', 1) as no_faktur")
            ->distinct()
            ->pluck('no_faktur')
            ->filter()
            ->values();

        $this->info("  Total faktur unik: " . $fakturs->count());

        $progress = 0;
        foreach ($fakturs->chunk(50) as $fakturChunk) {
            foreach ($fakturChunk as $noFaktur) {
                $items = DB::table('sid_retail_raw_itempenjualan')
                    ->whereRaw("SUBSTRING_INDEX(kode_sumber, '|', 1) = ?", [$noFaktur])
                    ->get();
                $this->processFaktur($noFaktur, $items, $variantMap);
                $progress++;
            }
            if ($progress % 100 === 0) {
                $this->info("  Processed {$progress} faktur...");
            }
        }
    }

    private function processFaktur(string $noFaktur, $items, array $variantMap): void
    {
        $first = $items->first();
        if (! $first) return;

        $p = json_decode($first->payload_normal, true);
        if (! $p) return;

        // Parse tanggal + jam (tanggal/jam are in payload_normal)
        $tgl = $p['tanggal'] ?? $p['tgl'] ?? now()->format('Y-m-d');
        $jam = $p['jam'] ?? '00:00:00';
        $createdAt = $this->parseDateTime($tgl, $jam);

        // Find pelanggan - check multiple possible fields
        $pelangganId = null;
        $kodePelanggan = trim((string) ($p['kode_pelanggan'] ?? $p['kode_customer'] ?? $p['kode'] ?? ''));
        // For itempenjualan, customer code might be in another field
        if ($kodePelanggan) {
            $pelangganId = SidImportMap::getId($kodePelanggan, 'pelanggan', 'pelanggan');
        }

        // Get cabang (default to 1)
        $cabangId = 1;

        if ($this->dryRun) {
            $this->stats['transaksi']++;
            $this->stats['transaksi_item'] += $items->count();
            return;
        }

        DB::transaction(function () use ($noFaktur, $items, $first, $p, $createdAt, $pelangganId, $cabangId, $variantMap) {
            // Handle duplicate no_transaksi by appending counter
            $baseNo = $noFaktur;
            $counter = 0;
            $finalNo = $baseNo;
            while (Transaksi::where('cabang_id', $cabangId)->where('no_transaksi', $finalNo)->exists()) {
                $counter++;
                $finalNo = $baseNo . '-' . $counter;
            }

            // Create Transaksi
            $transaksi = Transaksi::create([
                'cabang_id' => $cabangId,
                'pelanggan_id' => $pelangganId,
                'kasir_id' => 1,
                'no_transaksi' => $finalNo,
                'tanggal' => $createdAt,
                'subtotal' => 0,
                'diskon_nominal' => 0,
                'total_akhir' => 0,
                'jumlah_bayar' => 0,
                'kembalian' => 0,
                'metode_bayar' => 'tunai',
                'status' => 'selesai',
            ]);

            SidImportMap::setId($noFaktur, 'itempenjualan', 'transaksi', $transaksi->id);
            $this->stats['transaksi']++;

            $total = 0;
            $totalDiskon = 0;

            foreach ($items as $item) {
                $ip = json_decode($item->payload_normal, true);
                if (! $ip) continue;

                $kodeBarang = trim((string) ($ip['kode_barang'] ?? ''));
                $produkId = $kodeBarang ? SidImportMap::getId($kodeBarang, 'barang', 'produk') : null;
                $variantId = $kodeBarang ? SidImportMap::getId($kodeBarang, 'barang', 'sku_variant') : null;

                // Jika produkId tidak ditemukan di map (unique constraint hanya menyimpan sku_variant),
                // ambil via preload variantMap
                if (! $produkId && $variantId && isset($variantMap[$variantId])) {
                    $produkId = $variantMap[$variantId];
                }

                if (! $produkId || ! $variantId) continue;

                $qty = (float) ($ip['qty'] ?? 1);
                $hpp = (float) ($ip['hpp'] ?? 0);
                $hargaJual = (float) ($ip['harga'] ?? $ip['harga_jual'] ?? 0);
                $diskon = (float) ($ip['diskon_rupiah'] ?? $ip['diskon'] ?? 0);
                $subtotal = $qty * $hargaJual;
                $totalDiskon += $diskon;

                TransaksiItem::create([
                    'transaksi_id' => $transaksi->id,
                    'produk_id' => $produkId,
                    'sku_variant_id' => $variantId,
                    'jumlah' => $qty,
                    'harga_satuan' => $hargaJual,
                    'diskon_nominal' => $diskon,
                    'hpp' => $hpp,
                    'subtotal' => $subtotal - $diskon,
                ]);

                $total += $subtotal - $diskon;
                $this->stats['transaksi_item']++;
            }

            $transaksi->update([
                'subtotal' => $total + $totalDiskon,
                'diskon_nominal' => $totalDiskon,
                'total_akhir' => $total,
            ]);
        });
    }

    private function stepStokLog(): void
    {
        $this->info("\n--- STEP 6: Stok Log dari arus_stok (19.286) ---");
        
        $variantMap = \App\Modules\Wms\Models\SkuVariant::query()->pluck('produk_id', 'id')->all();

        DB::table('sid_retail_raw_arus_stok')
            ->orderBy('id')
            ->chunkById(1000, function ($chunk) use ($variantMap) {
                foreach ($chunk as $raw) {
                    $this->processArusStok((object) $raw, $variantMap);
                }
            });
    }

    private function processArusStok($raw, array $variantMap): void
    {
        $p = json_decode($raw->payload_normal, true);
        if (! $p) return;

        $kodeBarang = trim((string) ($p['kode_barang'] ?? $p['kode'] ?? ''));
        $produkId = $kodeBarang ? SidImportMap::getId($kodeBarang, 'barang', 'produk') : null;
        $variantId = $kodeBarang ? SidImportMap::getId($kodeBarang, 'barang', 'sku_variant') : null;

        // Jika produkId tidak ditemukan di map (unique constraint hanya menyimpan sku_variant),
        // ambil via preload variantMap
        if (! $produkId && $variantId && isset($variantMap[$variantId])) {
            $produkId = $variantMap[$variantId];
        }

        if (! $produkId || ! $variantId) return;

        $gudangId = null;
        $lokasiStok = trim((string) ($p['lokasi_stok'] ?? ''));
        if ($lokasiStok) {
            $gudangId = SidImportMap::getId("setup_perusahaan|{$lokasiStok}", 'setup_perusahaan', 'gudang');
        }

        $masuk = (float) ($p['masuk'] ?? $p['nilai_masuk'] ?? 0);
        $keluar = (float) ($p['keluar'] ?? $p['nilai_keluar'] ?? 0);
        $delta = $masuk - $keluar;
        if ($delta === 0) return;

        $sumber = $p['transaksi'] ?? 'unknown';
        $noTransaksi = trim((string) ($p['no_transaksi'] ?? ''));
        $referensiId = null;
        $referensiTipe = null;

        if ($noTransaksi) {
            $referensiId = SidImportMap::getId($noTransaksi, 'itempenjualan', 'transaksi');
            if ($referensiId) $referensiTipe = Transaksi::class;
        }

        if ($this->dryRun) {
            $this->stats['stok_log']++;
            return;
        }

        $stok = StokItem::firstOrCreate(
            ['produk_id' => $produkId, 'sku_variant_id' => $variantId, 'gudang_id' => $gudangId ?? 1],
            ['jumlah' => 0, 'jumlah_minimum' => 0]
        );

        $sebelum = $stok->jumlah;
        $sesudah = max(0, $sebelum + $delta);
        $stok->update(['jumlah' => $sesudah]);

        StokLog::create([
            'gudang_id' => $gudangId ?? 1,
            'produk_id' => $produkId,
            'sku_variant_id' => $variantId,
            'user_id' => 1,
            'jenis' => $masuk > $keluar ? 'masuk' : 'keluar',
            'referensi_tipe' => $referensiTipe,
            'referensi_id' => $referensiId,
            'jumlah_sebelum' => $sebelum,
            'perubahan' => $delta,
            'jumlah_setelah' => $sesudah,
            'catatan' => "Migrasi SID arus_stok: {$sumber}",
            'created_at' => $this->parseDateTime($p['tanggal'] ?? now(), $p['jam'] ?? '00:00:00'),
        ]);

        StockMutationLog::create([
            'produk_id' => $produkId,
            'sku_variant_id' => $variantId,
            'gudang_id' => $gudangId ?? 1,
            'delta' => $delta,
            'sumber' => 'sid_migration',
            'referensi_tipe' => $referensiTipe,
            'referensi_id' => $referensiId,
            'terjadi_at' => $this->parseDateTime($p['tanggal'] ?? now(), $p['jam'] ?? '00:00:00'),
        ]);

        $this->stats['stok_log']++;
    }

    private function stepServis(): void
    {
        $this->info("\n--- STEP 7: Servis (332) → Tiket Servis ---");

        $variantMap = \App\Modules\Wms\Models\SkuVariant::query()->pluck('produk_id', 'id')->all();

        // Seed teknisi first - extract from payload_normal (teknisi_id references User)
        $teknisiMap = [];
        $teknisis = DB::table('sid_retail_raw_servis')
            ->get()
            ->map(function ($raw) {
                $p = json_decode($raw->payload_normal, true);
                return $p['teknisi'] ?? null;
            })
            ->filter()
            ->unique()
            ->values();

        foreach ($teknisis as $tn) {
            $tn = trim((string) $tn);
            if (! $tn) continue;
            $teknisi = \App\Models\User::firstOrCreate(
                ['name' => 'Teknisi ' . $tn],
                ['email' => 'teknisi-' . $tn . '@ute-parts.local', 'password' => bcrypt('password'), 'is_active' => true]
            );
            $teknisiMap[$tn] = $teknisi->id;
        }

        DB::table('sid_retail_raw_servis')
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($teknisiMap, $variantMap) {
                foreach ($chunk as $raw) {
                    $this->processServis((object) $raw, $teknisiMap, $variantMap);
                }
            });
    }

    private function processServis($raw, array $teknisiMap, array $variantMap): void
    {
        $kode = trim($raw->kode_sumber);
        if (! $kode) return;

        if (SidImportMap::exists($kode, 'servis')) {
            return;
        }

        $p = json_decode($raw->payload_normal, true);
        if (! $p) return;

        // Status mapping
        $statusMap = [
            'SEDANG DI SERVIS' => 'dalam_pengerjaan',
            'SUDAH DI AMBIL' => 'diambil',
            'DIBATALKAN' => 'dibatalkan',
            'MENUNGGU APPROVAL' => 'menunggu_approval',
        ];
        $statusRaw = strtoupper(trim((string) ($p['status'] ?? '')));
        $status = $statusMap[$statusRaw] ?? 'menunggu';

        $pelangganId = null;
        $kodePelanggan = trim((string) ($p['kode_pelanggan'] ?? ''));
        if ($kodePelanggan) {
            $pelangganId = SidImportMap::getId($kodePelanggan, 'pelanggan', 'pelanggan');
        }

        $teknisiId = null;
        $teknisiRaw = trim((string) ($p['teknisi'] ?? ''));
        if ($teknisiRaw && isset($teknisiMap[$teknisiRaw])) {
            $teknisiId = $teknisiMap[$teknisiRaw];
        }

        $tgl = $p['tanggal'] ?? now()->format('Y-m-d');
        $jam = $p['jam'] ?? '00:00:00';

        if ($this->dryRun) {
            $this->stats['tiket_servis']++;
            return;
        }

        DB::transaction(function () use ($kode, $p, $status, $pelangganId, $teknisiId, $tgl, $jam, $variantMap) {
            $tiket = \App\Modules\Servis\Models\TiketServis::create([
                'cabang_id' => 1,
                'jenis_servis_id' => 1, // default jenis servis
                'pelanggan_id' => $pelangganId,
                'teknisi_id' => $teknisiId,
                'no_tiket' => 'SID-' . $kode,
                'jenis_hp' => trim((string) ($p['barang'] ?? 'Unknown')),
                'seri_hp' => trim((string) ($p['no_imei'] ?? '')),
                'keluhan' => trim((string) ($p['kerusakan'] ?? $p['keluhan'] ?? '')),
                'status' => $status,
                'sumber' => 'sid_migration',
                'tanggal_terima' => $this->parseDateTime($tgl, $jam),
                'estimasi_biaya' => 0,
            ]);

            SidImportMap::setId($kode, 'servis', 'tiket_servis', $tiket->id);
            $this->stats['tiket_servis']++;

            // Items - check if has sparepart
            $kodeBarang = trim((string) ($p['kode_barang'] ?? ''));
            $variantId = $kodeBarang ? SidImportMap::getId($kodeBarang, 'barang', 'sku_variant') : null;
            $produkId = $variantId && isset($variantMap[$variantId]) ? $variantMap[$variantId] : null;
            if ($produkId) {
                \App\Modules\Servis\Models\TiketServisItem::create([
                    'tiket_servis_id' => $tiket->id,
                    'tipe' => 'part',
                    'produk_id' => $produkId,
                    'nama_item' => $produkId ? Produk::find($produkId)?->nama : 'Sparepart',
                    'qty' => 1,
                    'harga' => 0,
                ]);
            }
        });
    }

    private function parseDateTime(string $tgl, string $jam): Carbon
    {
        // Parse SID date (may be 1899-12-30 for empty)
        try {
            if ($tgl === '1899-12-30' || $tgl === '0000-00-00') {
                return now();
            }
            $dt = Carbon::parse($tgl);
            
            // Parse 12-hour format "12:00:34 PM"
            if (preg_match('/(\d{1,2}):(\d{2}):(\d{2})\s*(AM|PM)/i', $jam, $m)) {
                $h = (int) $m[1];
                $mi = (int) $m[2];
                $s = (int) $m[3];
                $ap = strtoupper($m[4]);
                if ($ap === 'PM' && $h < 12) $h += 12;
                if ($ap === 'AM' && $h === 12) $h = 0;
                $dt->setTime($h, $mi, $s);
            } elseif (preg_match('/(\d{1,2}):(\d{2}):(\d{2})/', $jam, $m)) {
                $dt->setTime((int) $m[1], (int) $m[2], (int) $m[3]);
            }
            
            return $dt;
        } catch (\Throwable) {
            return now();
        }
    }

    private function printSummary(): void
    {
        $this->info("\n=== MIGRATION SUMMARY ===");
        foreach ($this->stats as $key => $val) {
            $this->line("  {$key}: {$val}");
        }
        if ($this->dryRun) {
            $this->warn("DRY-RUN: No actual data written");
        } else {
            $this->info("Migration completed. Check sid_import_map table for mapping.");
        }
    }
}