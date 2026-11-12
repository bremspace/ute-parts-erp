<?php

namespace App\Console\Commands;

use App\Modules\Rbac\Services\DatabaseManagementService;
use Illuminate\Console\Command;
use Throwable;

class UteRestoreCommand extends Command
{
    protected $signature = 'ute:restore
        {file? : Nama berkas backup .sql.gz di storage/app/backups atau path absolut}
        {--target-db= : Target nama database MySQL (misal uteparts_test_restore untuk inspeksi tanpa menimpa database utama)}
        {--latest : Otomatis pulihkan dari file backup paling mutakhir}
        {--force : Lewati konfirmasi manual}';

    protected $description = 'Restore database Ute Parts dari berkas backup (.sql.gz)';

    public function handle(DatabaseManagementService $dbService): int
    {
        $fileArg = $this->argument('file');
        $targetDb = $this->option('target-db');
        $isLatest = $this->option('latest');
        $force = $this->option('force');

        $targetPath = null;

        if ($isLatest) {
            $backups = $dbService->listBackups();
            if (empty($backups)) {
                $this->error('Tidak ada berkas backup yang ditemukan di direktori server.');

                return self::FAILURE;
            }
            $targetPath = $backups[0]['path'];
            $this->info("Memilih berkas backup terbaru: {$backups[0]['filename']}");
        } elseif ($fileArg) {
            if (file_exists($fileArg)) {
                $targetPath = $fileArg;
            } else {
                $possible = $dbService->getBackupDirectory().DIRECTORY_SEPARATOR.basename($fileArg);
                if (file_exists($possible)) {
                    $targetPath = $possible;
                } else {
                    $this->error("Berkas backup tidak ditemukan: {$fileArg}");

                    return self::FAILURE;
                }
            }
        } else {
            $backups = $dbService->listBackups();
            if (empty($backups)) {
                $this->error('Tidak ada berkas backup yang tersedia.');

                return self::FAILURE;
            }

            $choices = array_map(fn ($b) => "{$b['filename']} ({$b['size_formatted']} - {$b['created_at']})", $backups);
            $chosen = $this->choice('Pilih berkas backup yang ingin dipulihkan:', $choices);
            $index = array_search($chosen, $choices, true);
            $targetPath = $backups[$index]['path'];
        }

        if (! $force) {
            $msg = $targetDb
                ? "Restore file '".basename($targetPath)."' ke database target '{$targetDb}' (database utama aman). Lanjutkan?"
                : "PERINGATAN: Memulihkan database dari '".basename($targetPath)."' akan menimpa data yang ada saat ini. Lanjutkan?";
            $confirm = $this->confirm($msg, (bool) $targetDb);
            if (! $confirm) {
                $this->warn('Operasi restore dibatalkan.');

                return self::SUCCESS;
            }
        }

        $this->info('Memulai proses restore database...');

        try {
            $result = $dbService->restore($targetPath, $targetDb);
            $this->info("Restore berhasil dari berkas: {$result['restored_from']} ke database '{$result['target_database']}'");
            if (! empty($result['app_key_synced'])) {
                $this->info('✓ Kunci enkripsi APP_KEY di .env berhasil disinkronkan otomatis dari snapshot manifest.');
            }
            if (! empty($result['media_restored'])) {
                $this->info('✓ Seluruh berkas media storage/app/public berhasil diekstrak & symlink diperbarui.');
            }
            if (! empty($result['pre_restore_backup'])) {
                $this->comment("Snapshot darurat pra-restore disimpan di: {$result['pre_restore_backup']}");
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Gagal melakukan restore: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
