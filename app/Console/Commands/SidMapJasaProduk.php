<?php

namespace App\Console\Commands;

use App\Modules\Servis\Models\JenisServis;
use App\Modules\Wms\Models\Produk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SidMapJasaProduk extends Command
{
    protected $signature = 'sid:map-jasa-produk';

    protected $description = 'Map SID JASA items to JenisServis + mark produk harga 0 as harga_fleksibel (idempotent)';

    public function handle(): int
    {
        $this->info('=== SID Map JASA → JenisServis + Produk harga_fleksibel ===');
        $baru = 0;
        $skip = 0;

        // (a) JASA items dari sid_retail_raw_barang (jenis='JASA') yang BELUM di-map ke JenisServis
        // Map via sid_import_map dengan tabel_sumber='barang', entity_type='jenis_servis'
        $this->info('(a) Memproses item JASA dari sid_retail_raw_barang → JenisServis...');
        $jasaItems = DB::table('sid_retail_raw_barang')
            ->whereRaw('UPPER(JSON_UNQUOTE(JSON_EXTRACT(payload_normal, "$.jenis"))) = ?', ['JASA'])
            ->whereNotIn('kode_sumber', function ($q) {
                $q->select('kode_sumber')
                    ->from('sid_import_map')
                    ->where('tabel_sumber', 'barang')
                    ->where('entity_type', 'jenis_servis');
            })
            ->get();

        foreach ($jasaItems as $raw) {
            $p = json_decode($raw->payload_normal, true);
            $kode = $p['kode'] ?? $raw->kode_sumber;
            $nama = $p['nama'] ?? 'JASA';
            $kategori = $p['kategori'] ?? 'Umum';

            // Cek apakah JenisServis dengan kode ini sudah ada
            $jenis = JenisServis::where('kode', $kode)->first();
            if ($jenis) {
                $mapExists = DB::table('sid_import_map')
                    ->where('kode_sumber', $kode)
                    ->where('tabel_sumber', 'barang')
                    ->where('entity_type', 'jenis_servis')
                    ->exists();
                if (! $mapExists) {
                    DB::table('sid_import_map')->insert([
                        'kode_sumber' => $kode,
                        'tabel_sumber' => 'barang',
                        'entity_type' => 'jenis_servis',
                        'entity_id' => $jenis->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->line("  Map existing: {$nama} ({$kode}) -> jenis_servis #{$jenis->id}");
                } else {
                    $skip++;
                }

                continue;
            }

            // Buat JenisServis baru
            $jenis = JenisServis::create([
                'nama' => $nama,
                'kode' => $kode,
                'kategori' => Str::slug($kategori),
                'estimasi_durasi' => 120, // default 2 jam
                'durasi_garansi_hari' => 30,
                'butuh_part' => true, // default butuh sparepart
                'is_part_original' => false,
                'is_active' => true,
            ]);

            // Catat mapping
            DB::table('sid_import_map')->insert([
                'kode_sumber' => $kode,
                'tabel_sumber' => 'barang',
                'entity_type' => 'jenis_servis',
                'entity_id' => $jenis->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $baru++;
            $this->line("  Baru: {$nama} ({$kode}) -> jenis_servis #{$jenis->id}");
        }

        // (b) Produk migrasi SID dengan harga_jual_retail 0/null → set harga_fleksibel = true
        $this->info('(b) Update produk migrasi SID harga 0/null → harga_fleksibel...');
        $updated = Produk::where('is_migrasi_sid', 1)
            ->where(function ($q) {
                $q->whereNull('harga_jual_retail')
                    ->orWhere('harga_jual_retail', 0);
            })
            ->where('harga_fleksibel', 0)
            ->update(['harga_fleksibel' => 1]);

        $this->info("  Updated: {$updated} produk");

        // (c) Ringkasan
        $this->newLine();
        $this->info('=== RINGKASAN ===');
        $this->info("JASA baru (JenisServis): {$baru}");
        $this->info("JASA skip (existing): {$skip}");
        $this->info("Produk harga 0 → fleksibel: {$updated}");
        $this->info('Total perubahan: '.($baru + $updated));

        return self::SUCCESS;
    }
}
