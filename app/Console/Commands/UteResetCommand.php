<?php

namespace App\Console\Commands;

use App\Modules\Rbac\Services\DatabaseManagementService;
use Illuminate\Console\Command;
use Throwable;

class UteResetCommand extends Command
{
    protected $signature = 'ute:reset
        {--mode=transaksi_only : Mode reset: transaksi_only, master_and_transaksi, factory_reset, full_wipe}
        {--force : Lewati konfirmasi interaktif (hanya untuk otomasi aman)}';

    protected $description = 'Enterprise Reset Database: Kosongkan transaksi, master, atau factory reset standar.';

    public function handle(DatabaseManagementService $dbService): int
    {
        $mode = (string) $this->option('mode');
        $force = (bool) $this->option('force');

        if (! in_array($mode, ['transaksi_only', 'master_and_transaksi', 'factory_reset', 'full_wipe'], true)) {
            $this->error("Mode '{$mode}' tidak valid. Pilihan: transaksi_only, master_and_transaksi, factory_reset, full_wipe.");

            return self::FAILURE;
        }

        $confirmationPhrases = [
            'transaksi_only' => 'SAYA SETUJU RESET DATA',
            'master_and_transaksi' => 'RESET TRANSAKSI DAN MASTER',
            'factory_reset' => 'RESET PABRIK',
            'full_wipe' => 'HAPUS TOTAL SEMUA DATA',
        ];

        $expectedPhrase = $confirmationPhrases[$mode];

        if (! $force) {
            $this->warn('====================================================================');
            $this->warn("                  PERINGATAN RESET DATABASE ({$mode})               ");
            $this->warn('====================================================================');
            if ($mode === 'factory_reset') {
                $this->line('Operasi FACTORY RESET akan MENGHAPUS SEMUA DATA transaksi & katalog custom,');
                $this->line('lalu memulihkan kondisi sistem persis ke standar bersih siap pakai (DatabaseSeeder).');
            } else {
                $this->line('Operasi ini akan membersihkan data sesuai mode yang dipilih.');
            }
            $this->warn('====================================================================');

            $input = $this->ask("Ketik \"{$expectedPhrase}\" untuk melanjutkan:");
            if (trim((string) $input) !== $expectedPhrase) {
                $this->error('Teks konfirmasi salah. Operasi reset dibatalkan.');

                return self::FAILURE;
            }
        }

        $this->info("Menjalankan reset database mode: {$mode}...");

        try {
            $res = ($mode === 'transaksi_only')
                ? $dbService->safeReset()
                : $dbService->resetDatabase($mode);

            $this->info('Reset database berhasil diselesaikan.');
            $this->line("- Label: {$res['label']}");
            $this->line("- Jumlah tabel dibersihkan: {$res['cleared_tables']}");
            $this->line("- Snapshot cadangan sebelum reset: {$res['pre_reset_backup']}");
            if (! empty($res['default_password'])) {
                $this->line("- Password akun operasional: {$res['default_password']}");
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Gagal melakukan reset: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
