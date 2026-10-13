<?php

namespace App\Console\Commands;

use App\Modules\Rbac\Services\DatabaseManagementService;
use Illuminate\Console\Command;

class UteDbHealthCheckCommand extends Command
{
    protected $signature = 'ute:db-health-check
        {--auto-restore : Otomatis pulihkan dari backup terbaru jika terdeteksi crash}';

    protected $description = 'Health probe database Ute Parts dengan kapabilitas auto-recovery saat crash';

    public function handle(DatabaseManagementService $dbService): int
    {
        $autoRestore = $this->option('auto-restore');

        $this->line('Menjalankan uji integritas database...');

        if ($autoRestore) {
            $result = $dbService->checkHealthAndAutoRestore();
            if ($result['status'] === 'healthy') {
                $this->info('Status Database: SEHAT (Koneksi & tabel inti normal).');

                return self::SUCCESS;
            } elseif ($result['status'] === 'recovered') {
                $this->warn('Status Database: PULIH DARI CRASH! Auto-recovery berhasil dijalankan dari backup.');

                return self::SUCCESS;
            } else {
                $this->error('Status Database: CRASHED! Gagal memulihkan otomatis.');
                foreach ($result['issues'] as $issue) {
                    $this->error("- {$issue}");
                }

                return self::FAILURE;
            }
        }

        $check = $dbService->checkHealthAndAutoRestore();
        if ($check['status'] === 'healthy') {
            $this->info('Status Database: SEHAT (Koneksi & tabel inti normal).');

            return self::SUCCESS;
        }

        $this->error('Status Database: BERMASALAH/CRASHED!');
        foreach ($check['issues'] as $issue) {
            $this->error("- {$issue}");
        }

        return self::FAILURE;
    }
}
