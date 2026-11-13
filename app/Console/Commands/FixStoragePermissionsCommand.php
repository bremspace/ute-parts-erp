<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class FixStoragePermissionsCommand extends Command
{
    protected $signature = 'ute:fix-permissions
        {--force : Jalankan tanpa konfirmasi}';

    protected $description = 'Perbaiki permission storage/framework & bootstrap/cache agar www-data bisa menulis (mencegah 500 utime failed)';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $confirm = $this->confirm('Perintah ini akan mengubah ownership ke www-data:www-data dan permission 775/664. Lanjutkan?');
            if (! $confirm) {
                $this->warn('Dibatalkan.');

                return self::SUCCESS;
            }
        }

        $paths = [
            storage_path('framework/views'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        $fixed = 0;
        foreach ($paths as $path) {
            if (! File::isDirectory($path)) {
                $this->warn("Direktori tidak ditemukan: {$path}");

                continue;
            }

            $this->fixDirectory($path);
            $fixed++;
        }

        if ($fixed > 0) {
            $this->info("Berhasil memperbaiki permission pada {$fixed} direktori.");
            $this->line('Sekarang web server (www-data) bisa menulis cache/view tanpa error 500.');
        } else {
            $this->warn('Tidak ada direktori yang diperbaiki.');
        }

        return self::SUCCESS;
    }

    protected function fixDirectory(string $path): void
    {
        // chown/chgrp ke www-data (butuh root/sudo)
        @chown($path, 'www-data');
        @chgrp($path, 'www-data');
        @chmod($path, 0775);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            @chown($item->getPathname(), 'www-data');
            @chgrp($item->getPathname(), 'www-data');
            @chmod($item->getPathname(), $item->isDir() ? 0775 : 0664);
        }
    }
}
