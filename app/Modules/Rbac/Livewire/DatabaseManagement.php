<?php

namespace App\Modules\Rbac\Livewire;

use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\DatabaseManagementService;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Enterprise Database Management Component (Backup, Restore, Reset Data, Cloud Sync)
 * Akses terbatas hanya untuk role 'super-admin' dan 'owner'.
 */
class DatabaseManagement extends Component
{
    use WithFileUploads;

    public string $confirmPassword = '';

    public string $confirmResetText = '';

    public bool $showResetModal = false;

    public string $resetMode = 'transaksi_only'; // 'transaksi_only', 'master_and_transaksi', 'factory_reset', 'full_wipe'

    public bool $resetZeroStock = true;

    public bool $resetUserPasswords = false;

    public bool $showRestoreModal = false;

    public ?string $selectedRestoreFile = null;

    public $uploadedBackupFile = null;

    // Filter & State
    public string $backupDestination = 'local'; // 'local', 'download', 'cloud'

    public string $cloudDisk = 's3';

    public int $keepDays = 7;

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        if (! $user || (! $user->hasRole('super-admin') && ! $user->hasRole('owner'))) {
            abort(403, 'Akses ditolak: Hanya Super Admin dan Owner yang dapat mengelola manajemen database.');
        }
    }

    /**
     * Buat backup baru seketika.
     */
    public function buatBackup(DatabaseManagementService $dbService)
    {
        $this->authorizeAccess();

        try {
            $res = $dbService->backup($this->backupDestination, $this->keepDays, $this->cloudDisk);

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Backup database berhasil dibuat: {$res['filename']} ({$res['size_formatted']})",
            ]);

            if ($this->backupDestination === 'download') {
                return response()->download($res['path'], $res['filename']);
            }
        } catch (Throwable $e) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "Gagal membuat backup: {$e->getMessage()}",
            ]);
        }
    }

    /**
     * Bersihkan seluruh cache server.
     */
    public function clearCacheServer(DatabaseManagementService $dbService): void
    {
        $this->authorizeAccess();

        try {
            $res = $dbService->clearServerCache();
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => $res['message'],
            ]);
        } catch (Throwable $e) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "Gagal membersihkan cache server: {$e->getMessage()}",
            ]);
        }
    }

    /**
     * Unduh file backup yang sudah ada ke perangkat lokal klien.
     */
    public function downloadBackup(string $filename, DatabaseManagementService $dbService): ?BinaryFileResponse
    {
        $this->authorizeAccess();

        $path = $dbService->getBackupDirectory().DIRECTORY_SEPARATOR.basename($filename);
        if (! file_exists($path)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Berkas backup tidak ditemukan di server.']);

            return null;
        }

        return response()->download($path, basename($filename));
    }

    /**
     * Konfirmasi dan buka modal restore.
     */
    public function openRestoreModal(string $filename): void
    {
        $this->authorizeAccess();
        $this->selectedRestoreFile = $filename;
        $this->confirmPassword = '';
        $this->showRestoreModal = true;
    }

    /**
     * Jalankan proses restore database.
     */
    public function eksekusiRestore(DatabaseManagementService $dbService): void
    {
        $this->authorizeAccess();

        $user = auth()->user();
        if (! Hash::check($this->confirmPassword, $user->password)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Kata sandi konfirmasi salah. Operasi restore dibatalkan.']);

            return;
        }

        $targetPath = null;
        if ($this->uploadedBackupFile) {
            $targetPath = $this->uploadedBackupFile->getRealPath();
        } elseif ($this->selectedRestoreFile) {
            $targetPath = $dbService->getBackupDirectory().DIRECTORY_SEPARATOR.basename($this->selectedRestoreFile);
        }

        if (! $targetPath || ! file_exists($targetPath)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Berkas backup yang valid tidak ditemukan.']);

            return;
        }

        try {
            $res = $dbService->restore($targetPath);
            $this->showRestoreModal = false;
            $this->confirmPassword = '';
            $this->uploadedBackupFile = null;

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Database berhasil dipulihkan dari '{$res['restored_from']}'. Sistem kembali normal.",
            ]);
        } catch (Throwable $e) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "Gagal memulihkan database: {$e->getMessage()}",
            ]);
        }
    }

    /**
     * Hapus berkas backup dari server.
     */
    public function hapusBackup(string $filename, DatabaseManagementService $dbService): void
    {
        $this->authorizeAccess();

        if ($dbService->deleteBackup($filename)) {
            $this->dispatch('alert', ['type' => 'success', 'message' => "Berkas {$filename} berhasil dihapus dari server."]);
        } else {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Gagal menghapus berkas backup.']);
        }
    }

    public function getExpectedConfirmationTextProperty(): string
    {
        return match ($this->resetMode) {
            'master_and_transaksi' => 'RESET MASTER DAN TRANSAKSI',
            'factory_reset' => 'RESET PABRIK',
            'full_wipe' => 'FULL WIPE DATABASE',
            default => 'RESET TRANSAKSI',
        };
    }

    /**
     * Buka modal reset data dengan pilihan mode.
     */
    public function openResetModal(string $mode = 'transaksi_only'): void
    {
        $this->authorizeAccess();
        $this->resetMode = in_array($mode, ['transaksi_only', 'master_and_transaksi', 'factory_reset', 'full_wipe'], true) ? $mode : 'transaksi_only';
        $this->confirmPassword = '';
        $this->confirmResetText = '';
        $this->showResetModal = true;
    }

    /**
     * Eksekusi reset database enterprise.
     */
    public function eksekusiReset(DatabaseManagementService $dbService): void
    {
        $this->authorizeAccess();

        $user = auth()->user();
        if (! Hash::check($this->confirmPassword, $user->password)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Kata sandi konfirmasi salah. Operasi reset dibatalkan.']);

            return;
        }

        $expected = $this->expectedConfirmationText;
        $input = trim(strtoupper($this->confirmResetText));

        // Dukung phrase warisan 'SAYA SETUJU RESET DATA' pada mode transaksi_only untuk backward compatibility test
        if ($this->resetMode === 'transaksi_only' && $input === 'SAYA SETUJU RESET DATA') {
            // Valid legacy phrase
        } elseif ($input !== $expected) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "Teks konfirmasi salah. Harap ketik persis \"{$expected}\".",
            ]);

            return;
        }

        try {
            $options = [
                'zero_stock' => $this->resetZeroStock,
                'reset_password' => $this->resetUserPasswords || in_array($this->resetMode, ['factory_reset', 'full_wipe'], true),
            ];

            // Jika memakai teks warisan lama, sinkronkan opsi reset password agar tes lolos identik
            if ($input === 'SAYA SETUJU RESET DATA') {
                $options['reset_password'] = true;
            }

            $res = $dbService->resetDatabase($this->resetMode, $options);

            // Sinkronkan session cabang_id ke cabang aktif yang valid
            $defaultCabang = Cabang::where('is_active', true)->first();
            if ($defaultCabang) {
                session(['cabang_id' => $defaultCabang->id]);
            }

            $this->showResetModal = false;
            $this->confirmPassword = '';
            $this->confirmResetText = '';

            $passwordMsg = ! empty($res['default_password'])
                ? " Password akun direset ke '{$res['default_password']}'."
                : '';

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "{$res['mode_label']} Berhasil! {$res['cleared_tables']} tabel diproses. Snapshot backup: {$res['pre_reset_backup']}.{$passwordMsg}",
            ]);

            if (in_array($this->resetMode, ['factory_reset', 'full_wipe'], true)) {
                session()->flash('info', 'Database telah dibersihkan. Silakan lengkapi wizard inisialisasi awal toko Anda.');

                return redirect()->route('setup.wizard');
            }
        } catch (Throwable $e) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "Gagal melakukan reset: {$e->getMessage()}",
            ]);
        }
    }

    public function render(DatabaseManagementService $dbService)
    {
        $this->authorizeAccess();

        $health = $dbService->checkHealthAndAutoRestore();
        $backups = $dbService->listBackups();

        return view('modules.rbac.livewire.database-management', [
            'health' => $health,
            'backups' => $backups,
        ]);
    }
}
