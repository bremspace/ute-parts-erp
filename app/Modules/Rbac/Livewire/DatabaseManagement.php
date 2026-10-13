<?php

namespace App\Modules\Rbac\Livewire;

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

    /**
     * Buka modal reset data.
     */
    public function openResetModal(): void
    {
        $this->authorizeAccess();
        $this->confirmPassword = '';
        $this->confirmResetText = '';
        $this->showResetModal = true;
    }

    /**
     * Eksekusi safe reset database.
     */
    public function eksekusiReset(DatabaseManagementService $dbService): void
    {
        $this->authorizeAccess();

        $user = auth()->user();
        if (! Hash::check($this->confirmPassword, $user->password)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Kata sandi konfirmasi salah. Operasi reset dibatalkan.']);

            return;
        }

        if (trim($this->confirmResetText) !== 'SAYA SETUJU RESET DATA') {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Teks konfirmasi salah. Harap ketik persis "SAYA SETUJU RESET DATA".']);

            return;
        }

        try {
            $res = $dbService->safeReset();
            $this->showResetModal = false;
            $this->confirmPassword = '';
            $this->confirmResetText = '';

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Safe Reset Berhasil! {$res['cleared_tables']} tabel transaksi dibersihkan. Akun pengguna tetap utuh & password direset ke '{$res['default_password']}'.",
            ]);
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
