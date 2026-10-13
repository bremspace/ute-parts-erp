<?php

namespace App\Console\Commands;

use App\Modules\Rbac\Services\DatabaseManagementService;
use Illuminate\Console\Command;
use Throwable;

class UteResetCommand extends Command
{
    protected $signature = 'ute:reset
        {--force : Lewati konfirmasi interaktif (hanya untuk otomasi aman)}';

    protected $description = 'Safe Reset Database: Kosongkan data transaksional, pertahankan akun default & reset password ke default.';

    public function handle(DatabaseManagementService $dbService): int
    {
        $force = $this->option('force');

        if (! $force) {
            $this->warn('====================================================================');
            $this->warn('                  PERINGATAN SAFE RESET DATA                        ');
            $this->warn('====================================================================');
            $this->line('Operasi ini akan MENGHAPUS SEMUA:');
            $this->line('- Transaksi penjualan & kasir shift');
            $this->line('- Tiket servis, estimasi, dan sparepart');
            $this->line('- Mutasi stok fisik (stok kembali ke 0)');
            $this->line('- Jurnal akuntansi, piutang, dan utang');
            $this->line('');
            $this->line('DATA YANG AMAN & DIPERTAHANKAN:');
            $this->line('- Akun resmi (Super Admin, Owner, Kasir, Admin Toko, Teknisi, dll)');
            $this->line('- Password akun default direset ke standar: "Password123!"');
            $this->line('- Role & Permission (RBAC) serta struktur Cabang & COA');
            $this->warn('====================================================================');

            $input = $this->ask('Ketik "SAYA SETUJU RESET DATA" untuk melanjutkan:');
            if ($input !== 'SAYA SETUJU RESET DATA') {
                $this->error('Teks konfirmasi salah. Operasi reset dibatalkan demi keamanan.');

                return self::FAILURE;
            }
        }

        $this->info('Membuat backup snapshot pra-reset dan mengosongkan data transaksional...');

        try {
            $res = $dbService->safeReset();
            $this->info('Safe Reset data berhasil diselesaikan.');
            $this->line("- Jumlah tabel transaksional dibersihkan: {$res['cleared_tables']}");
            $this->line("- Snapshot cadangan sebelum reset: {$res['pre_reset_backup']}");
            $this->line("- Password akun default direset ke: {$res['default_password']}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Gagal melakukan reset: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
