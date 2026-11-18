<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Illuminate\Console\Command;

class UteValidateEnvCommand extends Command
{
    protected $signature = 'ute:validate-env
        {--fix : Otomatis perbaiki baris yang rusak (backup dibuat)}
        {--path= : Path ke berkas .env (default: base_path)}';

    protected $description = 'Validasi berkas .env dapat diparsing Dotenv. Deteksi baris dengan escape sequence tak valid atau shell injection.';

    public function handle(): int
    {
        $envPath = $this->option('path') ? (string) $this->option('path') : base_path('.env');
        $dir = dirname($envPath);
        $file = basename($envPath);

        if (! file_exists($envPath)) {
            $this->error("Berkas .env tidak ditemukan: {$envPath}");

            return self::FAILURE;
        }

        // 1. Coba parse langsung
        try {
            Dotenv::createImmutable($dir, $file)->load();
            $this->info('✓ Berkas .env valid — tidak ada masalah escape sequence.');

            return self::SUCCESS;
        } catch (InvalidFileException $e) {
            $this->error('✗ Berkas .env mengandung sintaks tidak valid:');
            $this->line('  '.$e->getMessage());
        }

        if (! $this->option('fix')) {
            $this->warn('Jalankan dengan --fix untuk memperbaiki otomatis.');

            return self::FAILURE;
        }

        // 2. Mode --fix: Isolasi baris rusak satu per satu
        $this->info('Menjalankan mode perbaikan otomatis...');
        $content = file_get_contents($envPath);
        $lines = explode("\n", $content);
        $safeLines = [];
        $dropped = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                $safeLines[] = $line;

                continue;
            }

            // Deteksi shell injection yang umum
            if (preg_match('/(?:;|\&|\|)\s*(?:d=|echo|curl|sh|bash|chmod|wget)/i', $line) ||
                preg_match('/\\\\;/', $line) ||
                str_contains($line, 'unas-setup.ps1')) {
                $this->warn('  Membuang baris terkorupsi: '.substr($trimmed, 0, 60).'...');
                $dropped[] = $line;

                continue;
            }

            // Perbaiki backslash tak valid di dalam double quotes
            if (preg_match('/^([A-Za-z0-9_]+)="(.*)"$/s', $trimmed, $m)) {
                $key = $m[1];
                $val = $m[2];
                $cleaned = preg_replace('/\\\\([^nrtvf"\\\\$])/', '\\\\\\\\$1', $val);
                if ($cleaned !== $val) {
                    $this->warn("  Memperbaiki escape tak valid pada key: {$key}");
                    $line = "{$key}=\"{$cleaned}\"";
                }
            }

            // Probe baris ini sendiri
            $probe = sys_get_temp_dir().DIRECTORY_SEPARATOR.'.env_probe_'.getmypid();
            file_put_contents($probe, $trimmed."\n");

            try {
                Dotenv::createImmutable(dirname($probe), basename($probe))->load();
                $safeLines[] = $line;
            } catch (InvalidFileException) {
                $this->warn('  Membuang baris tidak bisa diparsing: '.substr($trimmed, 0, 60).'...');
                $dropped[] = $line;
            } finally {
                @unlink($probe);
            }
        }

        // 3. Backup dan tulis ulang
        $backup = $envPath.'.bak.'.date('Ymd_His');
        copy($envPath, $backup);
        $this->line("  Backup tersimpan: {$backup}");

        file_put_contents($envPath, implode("\n", $safeLines));

        if (empty($dropped)) {
            $this->info('✓ Tidak ada baris yang dibuang. Berkas diperbarui.');
        } else {
            $this->warn(count($dropped).' baris rusak dibuang dan berkas direparasi.');
        }

        // 4. Verifikasi akhir
        try {
            Dotenv::createImmutable($dir, $file)->load();
            $this->info('✓ Berkas .env berhasil direparasi dan terverifikasi valid.');

            return self::SUCCESS;
        } catch (InvalidFileException $e) {
            $this->error('✗ Masih ada masalah setelah reparasi: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
