<?php

namespace App\Console\Commands;

use App\Modules\Pos\Models\ReturnPenjualanItem;
use App\Modules\Servis\Models\TiketServis;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * [Phase 4 / T-12] Extract base64 encoded images from relational tables to storage disk.
 * Drops database row payload size and eliminates MySQL sort_buffer 1038 issues.
 */
class MigrateBase64ImagesCommand extends Command
{
    protected $signature = 'storage:migrate-base64-images';

    protected $description = 'Extract base64 data URLs in relational tables to Storage files';

    public function handle(): int
    {
        $this->info('Starting base64 image migration to disk storage...');

        $disk = Storage::disk('public');
        $migratedTiket = 0;
        $migratedRetur = 0;

        // 1. Tiket Servis
        TiketServis::whereNotNull('foto_unit')->chunkById(50, function ($tikets) use ($disk, &$migratedTiket) {
            foreach ($tikets as $tiket) {
                $fotoList = $tiket->foto_unit;
                if (! is_array($fotoList)) {
                    continue;
                }

                $updated = false;
                $newFotoList = [];

                foreach ($fotoList as $foto) {
                    if (is_string($foto) && str_starts_with($foto, 'data:image/')) {
                        $path = $this->saveBase64ToDisk($foto, 'tiket-servis', $disk);
                        if ($path) {
                            $newFotoList[] = $path;
                            $updated = true;
                        } else {
                            $newFotoList[] = $foto;
                        }
                    } else {
                        $newFotoList[] = $foto;
                    }
                }

                if ($updated) {
                    $tiket->update(['foto_unit' => $newFotoList]);
                    $migratedTiket++;
                }
            }
        });

        // 2. Return Penjualan Item
        ReturnPenjualanItem::whereNotNull('foto_bukti')->chunkById(50, function ($items) use ($disk, &$migratedRetur) {
            foreach ($items as $item) {
                $fotoList = $item->foto_bukti;
                if (! is_array($fotoList)) {
                    continue;
                }

                $updated = false;
                $newFotoList = [];

                foreach ($fotoList as $foto) {
                    if (is_string($foto) && str_starts_with($foto, 'data:image/')) {
                        $path = $this->saveBase64ToDisk($foto, 'retur-penjualan', $disk);
                        if ($path) {
                            $newFotoList[] = $path;
                            $updated = true;
                        } else {
                            $newFotoList[] = $foto;
                        }
                    } else {
                        $newFotoList[] = $foto;
                    }
                }

                if ($updated) {
                    $item->update(['foto_bukti' => $newFotoList]);
                    $migratedRetur++;
                }
            }
        });

        $this->info("Migration completed! Tiket Servis updated: {$migratedTiket}, Retur items updated: {$migratedRetur}");

        return 0;
    }

    protected function saveBase64ToDisk(string $base64, string $subfolder, $disk): ?string
    {
        try {
            if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
                $extension = strtolower($matches[1]);
                if ($extension === 'jpeg') {
                    $extension = 'jpg';
                }
                $data = substr($base64, strpos($base64, ',') + 1);
                $decoded = base64_decode($data);

                if ($decoded === false) {
                    return null;
                }

                $filename = $subfolder.'/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;
                $disk->put($filename, $decoded);

                return $filename;
            }
        } catch (\Throwable $e) {
            $this->error("Failed to decode image: {$e->getMessage()}");
        }

        return null;
    }
}
