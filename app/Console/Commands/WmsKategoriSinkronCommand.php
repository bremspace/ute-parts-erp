<?php

namespace App\Console\Commands;

use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\Produk;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class WmsKategoriSinkronCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wms:kategori-sinkron {--dry-run : Tampilkan saja tanpa mengubah data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi dua arah antara kolom kategori_id dan string kategori pada master produk (backfill legacy + reverse sync)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Memulai sinkronisasi kategori produk...'.($dryRun ? ' (DRY-RUN)' : ''));

        // ===== Pass 1: Backfill kategori_id dari string kategori =====
        $pass1 = Produk::query()
            ->whereNull('kategori_id')
            ->whereNotNull('kategori')
            ->where('kategori', '!=', '')
            ->where('kategori', '!=', 'Umum')
            ->get(['id', 'kategori']);

        $createdKategori = 0;
        $linkedProduk = 0;

        foreach ($pass1 as $produk) {
            $nama = trim($produk->kategori);
            if ($nama === '' || $nama === 'Umum') {
                continue;
            }

            $kategori = KategoriProduk::where('nama', $nama)
                ->orWhere('slug', Str::slug($nama))
                ->first();

            if (! $kategori) {
                if (! $dryRun) {
                    $kategori = KategoriProduk::create([
                        'nama' => $nama,
                        'is_active' => true,
                    ]);
                } else {
                    $this->line("  [Backfill] Produk #{$produk->id}: '{$nama}' -> kategori BARU akan dibuat");
                }
                $createdKategori++;
            }

            if ($dryRun) {
                $this->line("  [Backfill] Produk #{$produk->id}: '{$nama}' -> kategori '{$kategori->nama}' (ID: {$kategori->id})");
            } else {
                $produk->update(['kategori_id' => $kategori->id]);
            }
            $linkedProduk++;
        }

        // ===== Pass 2: Reverse sync string kategori dari kategori_id =====
        $pass2 = Produk::query()
            ->whereNotNull('kategori_id')
            ->with('kategoriRelasi')
            ->get(['id', 'kategori_id', 'kategori']);

        $syncedProduk = 0;

        foreach ($pass2 as $produk) {
            $relasi = $produk->kategoriRelasi;
            if (! $relasi) {
                continue;
            }

            $strNow = is_string($produk->kategori) ? trim($produk->kategori) : '';
            if ($strNow === $relasi->nama) {
                continue;
            }

            if ($dryRun) {
                $this->line("  [Reverse] Produk #{$produk->id}: '{$strNow}' -> '{$relasi->nama}'");
            } else {
                $produk->update(['kategori' => $relasi->nama]);
            }
            $syncedProduk++;
        }

        // ===== Ringkasan =====
        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Pass 1 - Produk legacy ditautkan kategori_id', $linkedProduk],
                ['Pass 1 - Kategori baru dibuat', $createdKategori],
                ['Pass 2 - String kategori disinkronkan dari relasi', $syncedProduk],
                ['Mode', $dryRun ? 'DRY-RUN (tidak ada perubahan)' : 'EKSEKUSI'],
            ]
        );

        $this->info($dryRun
            ? 'Dry-run selesai — tidak ada data yang diubah.'
            : 'Sinkronisasi kategori produk selesai diterapkan!');

        return Command::SUCCESS;
    }
}
