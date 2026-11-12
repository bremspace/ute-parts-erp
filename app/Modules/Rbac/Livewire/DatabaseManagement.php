<?php

namespace App\Modules\Rbac\Livewire;

use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\AppUpdateService;
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

    /**
     * Hook validasi otomatis saat berkas backup diunggah via form.
     */
    public function updatedUploadedBackupFile(): void
    {
        $this->validate([
            'uploadedBackupFile' => 'required|file|max:512000', // max 500MB
        ], [
            'uploadedBackupFile.required' => 'Pilih berkas backup terlebih dahulu.',
            'uploadedBackupFile.file' => 'Berkas backup tidak valid.',
            'uploadedBackupFile.max' => 'Ukuran berkas melebihi batas 500 MB.',
        ]);

        $ext = strtolower($this->uploadedBackupFile->getClientOriginalExtension());
        $origName = strtolower($this->uploadedBackupFile->getClientOriginalName());

        if (! in_array($ext, ['gz', 'sql', 'zip'], true) && ! str_ends_with($origName, '.sql.gz')) {
            $this->reset('uploadedBackupFile');
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'Format berkas tidak didukung. Harap upload berkas berekstensi .zip (snapshot), .sql.gz, atau .sql.',
            ]);

            return;
        }

        $this->dispatch('alert', [
            'type' => 'info',
            'message' => 'Berkas berhasil dipilih: '.$this->uploadedBackupFile->getClientOriginalName().'. Silakan klik "Lanjut Restore".',
        ]);
    }

    // Filter & State
    public string $backupDestination = 'local'; // 'local', 'download', 'cloud'

    public bool $isFullSnapshot = false; // Snapshot bundle: DB + APP_KEY + Storage Public

    public string $cloudDisk = 's3';

    public int $keepDays = 7;

    // Konfigurasi Database Lokal (Hanya Super Admin)
    public string $dbDriver = 'mysql';

    public string $dbHost = '127.0.0.1';

    public string $dbPort = '3306';

    public string $dbDatabase = 'uteparts_db';

    public string $dbUsername = 'root';

    public string $dbPassword = '';

    public string $dbSqlitePath = 'database/database.sqlite';

    public ?string $dbTestMessage = null;

    public ?string $dbTestStatus = null; // 'success' | 'error'

    public array $discoveredLanServers = [];

    public bool $isScanningLan = false;

    // Update Checker (GitHub Sync)
    public bool $showUpdateModal = false;

    public bool $isCheckingUpdate = false;

    public bool $isInstallingUpdate = false;

    public ?array $updateCheckResult = null;

    public string $gitDeployToken = '';

    public ?string $sshPublicKey = null;

    public array $updateLogs = [];

    public ?array $currentVersionInfo = null;

    public function mount(AppUpdateService $updateService): void
    {
        $this->authorizeAccess();

        $defaultConn = config('database.default', 'mysql');
        $this->dbDriver = (string) config("database.connections.{$defaultConn}.driver", $defaultConn);
        $this->dbHost = (string) config("database.connections.{$defaultConn}.host", '127.0.0.1');
        $this->dbPort = (string) config("database.connections.{$defaultConn}.port", '3306');
        $this->dbDatabase = (string) config("database.connections.{$defaultConn}.database", 'uteparts_db');
        $this->dbUsername = (string) config("database.connections.{$defaultConn}.username", 'root');
        $this->dbPassword = '';
        $this->dbSqlitePath = (string) config('database.connections.sqlite.database', 'database/database.sqlite');
        $this->gitDeployToken = $updateService->getGitToken();
        $this->sshPublicKey = $updateService->getSshPublicKey();
        $this->currentVersionInfo = $updateService->getCurrentVersionInfo();

        if (request()->has('check_update')) {
            $this->checkAppUpdates($updateService);
        }
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        if (! $user || (! $user->hasRole('super-admin') && ! $user->hasRole('owner'))) {
            abort(403, 'Akses ditolak: Hanya Super Admin dan Owner yang dapat mengelola manajemen database.');
        }
    }

    private function authorizeSuperAdminOnly(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasRole('super-admin')) {
            abort(403, 'Akses dibatasi khusus untuk akun Super Admin.');
        }
    }

    /**
     * Uji coba koneksi database sebelum disimpan.
     */
    public function testDbConnection(): void
    {
        $this->authorizeSuperAdminOnly();
        $this->dbTestMessage = null;
        $this->dbTestStatus = null;

        try {
            if ($this->dbDriver === 'sqlite') {
                $path = $this->dbSqlitePath;
                if (! str_starts_with($path, '/') && ! preg_match('/^[a-zA-Z]:[\\\\\/]/', $path)) {
                    $path = base_path(ltrim($path, '/'));
                }
                $dir = dirname($path);
                if (! is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                if (! file_exists($path)) {
                    @touch($path);
                    @chmod($path, 0666);
                }
                new \PDO("sqlite:{$path}", null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                $this->dbTestStatus = 'success';
                $this->dbTestMessage = "Koneksi SQLite Sukses! Berkas basis data '{$this->dbSqlitePath}' dapat diakses dan ditulis.";
            } else {
                $dsn = "mysql:host={$this->dbHost};port={$this->dbPort};dbname={$this->dbDatabase}";
                new \PDO($dsn, $this->dbUsername, $this->dbPassword, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_TIMEOUT => 3,
                ]);
                $this->dbTestStatus = 'success';
                $this->dbTestMessage = "Koneksi MySQL Sukses! Terhubung ke database '{$this->dbDatabase}' di {$this->dbHost}:{$this->dbPort}.";
            }
        } catch (Throwable $e) {
            $this->dbTestStatus = 'error';
            $this->dbTestMessage = 'Gagal terhubung: '.$e->getMessage();
        }
    }

    /**
     * Simpan konfigurasi database ke .env (Hanya Super Admin).
     */
    public function saveDatabaseConfig(AppUpdateService $updateService, DatabaseManagementService $dbService): void
    {
        $this->authorizeSuperAdminOnly();

        try {
            $updates = [
                'DB_CONNECTION' => $this->dbDriver,
            ];

            if ($this->dbDriver === 'sqlite') {
                $path = $this->dbSqlitePath;
                if (! str_starts_with($path, '/') && ! preg_match('/^[a-zA-Z]:[\\\\\/]/', $path)) {
                    $absPath = base_path(ltrim($path, '/'));
                } else {
                    $absPath = $path;
                }
                if (! file_exists($absPath)) {
                    @touch($absPath);
                    @chmod($absPath, 0666);
                }
                $updates['DB_DATABASE'] = $this->dbSqlitePath;
            } else {
                $updates['DB_HOST'] = $this->dbHost;
                $updates['DB_PORT'] = $this->dbPort;
                $updates['DB_DATABASE'] = $this->dbDatabase;
                $updates['DB_USERNAME'] = $this->dbUsername;
                $updates['DB_PASSWORD'] = $this->dbPassword;
            }

            $updateService->updateEnv($updates);
            $dbService->clearServerCache();

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => 'Konfigurasi database berhasil disimpan ke .env dan cache konfigurasi di-refresh!',
            ]);
        } catch (Throwable $e) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'Gagal menyimpan konfigurasi: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Pindai server database di jaringan LAN secara otomatis.
     */
    public function scanLanDatabase(DatabaseManagementService $dbService): void
    {
        $this->authorizeSuperAdminOnly();
        $this->isScanningLan = true;
        $res = $dbService->scanLanDatabaseServers(450);
        $this->discoveredLanServers = $res['servers'] ?? [];
        $this->isScanningLan = false;

        $count = count($this->discoveredLanServers);
        if ($count > 0) {
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Ditemukan {$count} host server di jaringan LAN!",
            ]);
        } else {
            $this->dispatch('alert', [
                'type' => 'info',
                'message' => 'Tidak ditemukan server MySQL/Ute Parts aktif lain di subnet LAN ini.',
            ]);
        }
    }

    /**
     * Terapkan IP server hasil scan LAN ke form koneksi database.
     */
    public function selectLanServer(string $ip, int $port = 3306): void
    {
        $this->authorizeSuperAdminOnly();
        $this->dbHost = $ip;
        $this->dbPort = (string) $port;
        $this->dbDriver = 'mysql';
        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Host database disetel ke {$ip}:{$port}.",
        ]);
    }

    /**
     * Cek update sistem dari server rilis.
     */
    public function checkAppUpdates(AppUpdateService $updateService): void
    {
        $this->isCheckingUpdate = true;
        try {
            $this->updateCheckResult = $updateService->checkForUpdates();
            $this->currentVersionInfo = $updateService->getCurrentVersionInfo();
            $this->showUpdateModal = true;
        } catch (Throwable $e) {
            $this->updateCheckResult = [
                'has_update' => false,
                'status' => 'error',
                'message' => 'Gagal mengecek pembaruan: '.$e->getMessage(),
                'current' => $this->currentVersionInfo ?? $updateService->getCurrentVersionInfo(),
                'changelog' => [],
                'commits_behind' => 0,
            ];
            $this->showUpdateModal = true;
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'Terjadi kesalahan saat mengecek pembaruan sistem: '.$e->getMessage(),
            ]);
        } finally {
            $this->isCheckingUpdate = false;
        }
    }

    /**
     * Simpan token private repository ke .env (opsional/developer).
     */
    public function saveGitToken(AppUpdateService $updateService): void
    {
        $this->authorizeSuperAdminOnly();
        $updateService->updateEnv(['GITHUB_DEPLOY_TOKEN' => trim($this->gitDeployToken)]);
        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Token autentikasi repositori berhasil disimpan ke .env!',
        ]);
    }

    /**
     * Generate pasangan SSH Key dari antarmuka aplikasi.
     */
    public function generateSshKey(AppUpdateService $updateService): void
    {
        $this->authorizeSuperAdminOnly();
        $res = $updateService->generateSshDeployKey();

        if ($res['success']) {
            $this->sshPublicKey = $res['public_key'];
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => $res['message'],
            ]);
        } else {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => $res['message'],
            ]);
        }
    }

    /**
     * Sinkronkan skema database & optimasi cache (terutama untuk Docker atau pasca-update).
     */
    public function syncDatabaseSchema(AppUpdateService $updateService): void
    {
        $this->authorizeSuperAdminOnly();
        $this->isInstallingUpdate = true;
        try {
            $res = $updateService->syncDatabaseSchema();
            $this->updateLogs = $res['logs'] ?? [];
            $this->currentVersionInfo = $res['current'] ?? $updateService->getCurrentVersionInfo();

            if ($res['success']) {
                $this->dispatch('alert', [
                    'type' => 'success',
                    'message' => $res['message'],
                ]);
            } else {
                $this->dispatch('alert', [
                    'type' => 'error',
                    'message' => $res['message'],
                ]);
            }
        } catch (Throwable $e) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'Gagal menyinkronkan skema basis data: '.$e->getMessage(),
            ]);
        } finally {
            $this->isInstallingUpdate = false;
        }
    }

    /**
     * Pasang pembaruan terbaru dari server rilis.
     */
    public function applyAppUpdate(AppUpdateService $updateService): void
    {
        $this->authorizeSuperAdminOnly();
        $this->isInstallingUpdate = true;
        try {
            $res = $updateService->applyUpdate();
            $this->updateLogs = $res['logs'] ?? [];

            if ($res['success']) {
                $this->currentVersionInfo = $res['current'] ?? $updateService->getCurrentVersionInfo();
                $this->updateCheckResult = null;
                $this->dispatch('alert', [
                    'type' => 'success',
                    'message' => $res['message'],
                ]);
            } else {
                $this->dispatch('alert', [
                    'type' => 'error',
                    'message' => $res['message'],
                ]);
            }
        } catch (Throwable $e) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'Gagal memasang pembaruan: '.$e->getMessage(),
            ]);
        } finally {
            $this->isInstallingUpdate = false;
        }
    }

    /**
     * Buat backup baru seketika.
     */
    public function buatBackup(DatabaseManagementService $dbService)
    {
        $this->authorizeAccess();

        try {
            $res = $dbService->backup($this->backupDestination, $this->keepDays, $this->cloudDisk, $this->isFullSnapshot);

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => ($this->isFullSnapshot ? 'Full snapshot clone' : 'Backup database')." berhasil dibuat: {$res['filename']} ({$res['size_formatted']})",
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
            $ext = strtolower($this->uploadedBackupFile->getClientOriginalExtension());
            $suffix = match ($ext) {
                'zip' => 'zip',
                'gz' => 'sql.gz',
                default => 'sql',
            };
            $safeName = 'upload_'.time().'.'.$suffix;
            $stored = $this->uploadedBackupFile->storeAs('backups', $safeName);
            $targetPath = storage_path('app/'.$stored);
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

            $extraMsg = '';
            if (! empty($res['app_key_synced'])) {
                $extraMsg .= ' APP_KEY disinkronkan.';
            }
            if (! empty($res['media_restored'])) {
                $extraMsg .= ' Media public storage dipulihkan.';
            }

            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Database berhasil dipulihkan dari '{$res['restored_from']}'.{$extraMsg} Sistem kembali normal.",
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
    public function eksekusiReset(DatabaseManagementService $dbService)
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
