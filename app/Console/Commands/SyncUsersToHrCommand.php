<?php

namespace App\Console\Commands;

use App\Modules\Hr\Services\UserHrSyncService;
use Illuminate\Console\Command;

class SyncUsersToHrCommand extends Command
{
    protected $signature = 'hr:sync-users';

    protected $description = 'Sinkronisasi seluruh akun user backoffice ke profil data karyawan HR';

    public function handle(UserHrSyncService $syncService): int
    {
        $this->info('Memulai sinkronisasi User ke HR Karyawan...');
        $total = $syncService->syncAllExistingUsers();
        $this->info("Berhasil menyinkronkan {$total} user ke data karyawan HR.");

        return self::SUCCESS;
    }
}
