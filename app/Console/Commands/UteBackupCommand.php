<?php

namespace App\Console\Commands;

use App\Modules\Notifikasi\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * F1-6 — Backup database harian: mysqldump (kredensial dari config/database.php)
 * → gzip → storage/app/backups/, rotasi simpan 7 hari.
 *
 * Dijadwalkan 02:00 via bootstrap/app.php withSchedule.
 * Notifikasi hasil selalu lewat NotificationService (queue, tidak pernah sinkron).
 */
class UteBackupCommand extends Command
{
    protected $signature = 'ute:backup
        {--connection= : Koneksi database di config/database.php (default: koneksi aktif)}
        {--keep-days=7 : Hari penyimpanan backup sebelum dirotasi}';

    protected $description = 'Backup database MySQL ke gzip di storage/app/backups (rotasi 7 hari) — F1-6';

    public function handle(NotificationService $notifikasi): int
    {
        $connectionName = (string) ($this->option('connection') ?: config('database.default'));
        $conn = config("database.connections.{$connectionName}");

        if (! is_array($conn) || ! in_array($conn['driver'] ?? '', ['mysql', 'mariadb'], true)) {
            return $this->gagal($notifikasi, sprintf(
                'Koneksi "%s" (driver "%s") tidak didukung — ute:backup hanya untuk MySQL/MariaDB.',
                $connectionName,
                is_array($conn) ? ($conn['driver'] ?? 'tidak diketahui') : 'tidak ditemukan',
            ));
        }

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir, 0755, true);

        $target = $dir.DIRECTORY_SEPARATOR.'backup-'.now()->format('Y-m-d_His').'.sql.gz';

        try {
            $this->dump($conn, $target);

            $hapus = $this->rotateBackups($dir, max(1, (int) $this->option('keep-days')));
            $ukuran = is_file($target) ? (int) filesize($target) : 0;

            // Notifikasi via queue — jangan biarkan kegagalan notif mengubah
            // status backup yang sebenarnya berhasil.
            try {
                $notifikasi->kirim(
                    'inapp',
                    null,
                    'Backup Database Berhasil',
                    sprintf(
                        'Backup database berhasil: %s (%s). Rotasi: %d file lama dihapus.',
                        basename($target),
                        $this->formatUkuran($ukuran),
                        $hapus,
                    ),
                    ['file' => basename($target), 'ukuran' => $ukuran, 'dihapus' => $hapus],
                );
            } catch (Throwable $e) {
                report($e);
            }

            $this->info(sprintf('Backup berhasil: %s (%s)', basename($target), $this->formatUkuran($ukuran)));

            return self::SUCCESS;
        } catch (Throwable $e) {
            // Bersihkan file parsial agar rerun tidak menumpuk sampah.
            @unlink($target);

            return $this->gagal($notifikasi, $e->getMessage());
        }
    }

    /**
     * Eksekusi mysqldump → file SQL mentah → stream gzip (RAM aman, 8KB chunk).
     *
     * @param  array<string, mixed>  $conn  konfigurasi koneksi dari config/database.php
     */
    protected function dump(array $conn, string $targetGz): void
    {
        $mysqldump = (new ExecutableFinder)->find('mysqldump') ?? 'mysqldump';
        $tmp = str_ends_with($targetGz, '.gz') ? substr($targetGz, 0, -3) : $targetGz.'.sql';

        $args = [
            $mysqldump,
            '--host='.($conn['host'] ?? '127.0.0.1'),
            '--user='.($conn['username'] ?? ''),
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            '--result-file='.$tmp,
        ];

        if (! empty($conn['unix_socket'])) {
            $args[] = '--socket='.$conn['unix_socket'];
        } else {
            $args[] = '--port='.($conn['port'] ?? '3306');
        }

        $args[] = (string) $conn['database'];

        try {
            // MYSQL_PWD agar password tidak terekspos di `ps` (proses argv).
            $process = new Process($args, null, ['MYSQL_PWD' => (string) ($conn['password'] ?? '')]);
            $process->setTimeout(3600);
            $process->run();
        } catch (Throwable $e) {
            @unlink($tmp);

            throw new RuntimeException('Gagal menjalankan mysqldump: '.$e->getMessage(), 0, $e);
        }

        if (! $process->isSuccessful()) {
            $stderr = trim($process->getErrorOutput());
            @unlink($tmp);

            throw new RuntimeException(sprintf(
                'mysqldump gagal (exit %d): %s',
                $process->getExitCode() ?? -1,
                $stderr !== '' ? mb_substr($stderr, 0, 500) : 'tidak ada detail error',
            ));
        }

        if (! is_file($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);

            throw new RuntimeException('File dump kosong — mysqldump tidak menghasilkan data.');
        }

        $this->gzip($tmp, $targetGz);
        @unlink($tmp);
    }

    /** Kompresi streaming file mentah ke .gz (tidak memuat seluruh file di RAM). */
    protected function gzip(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        if ($in === false) {
            throw new RuntimeException("Tidak bisa membuka file dump: {$source}");
        }

        $out = gzopen($target, 'wb');
        if ($out === false) {
            fclose($in);

            throw new RuntimeException("Tidak bisa membuat file gzip: {$target}");
        }

        try {
            while (! feof($in)) {
                $chunk = fread($in, 8192);
                if ($chunk === false) {
                    throw new RuntimeException("Gagal membaca file dump: {$source}");
                }
                if ($chunk === '') {
                    break;
                }
                gzwrite($out, $chunk);
            }
        } finally {
            fclose($in);
            gzclose($out);
        }
    }

    /**
     * Rotasi: hapus backup-*.sql.gz yang lebih tua dari $keepDays hari.
     * Idempoten — rerun tanpa file lama = no-op.
     */
    public function rotateBackups(string $dir, int $keepDays = 7): int
    {
        if (! is_dir($dir)) {
            return 0;
        }

        $cutoff = time() - ($keepDays * 86400);
        $dihapus = 0;
        clearstatcache(true);

        foreach (glob($dir.DIRECTORY_SEPARATOR.'backup-*.sql.gz') ?: [] as $file) {
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $cutoff && @unlink($file)) {
                $dihapus++;
            }
        }

        return $dihapus;
    }

    /** Gagal: pesan Indonesia ke konsol + notifikasi queue (tidak pernah sinkron). */
    protected function gagal(NotificationService $notifikasi, string $pesan): int
    {
        $this->error($pesan);

        try {
            $notifikasi->kirim(
                'inapp',
                null,
                'Backup Database Gagal',
                $pesan,
                ['error' => $pesan],
            );
        } catch (Throwable $e) {
            report($e);
        }

        return self::FAILURE;
    }

    protected function formatUkuran(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
