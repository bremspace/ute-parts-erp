<?php

namespace App\Modules\Rbac\Services;

use App\Models\User;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Rbac\Models\Cabang;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\AkunCoaSeeder;
use Database\Seeders\CabangSeeder;
use Database\Seeders\JenisServisSeeder;
use Database\Seeders\KomisiSkemaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\RoleUserSeeder;
use Database\Seeders\TierMembershipSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Enterprise Database Management Service for Ute Parts.
 * Handles Backup, Restore, Health Check, Auto-Recovery, and Safe Reset.
 */
class DatabaseManagementService
{
    public const BACKUP_DIR = 'app/backups';

    public function __construct(
        protected NotificationService $notifikasi,
        protected AuditService $auditService
    ) {}

    /**
     * Dapatkan path absolut direktori backup server.
     */
    public function getBackupDirectory(): string
    {
        $dir = storage_path(self::BACKUP_DIR);
        File::ensureDirectoryExists($dir, 0755, true);

        return $dir;
    }

    /**
     * List semua file backup yang tersedia di server.
     */
    public function listBackups(): array
    {
        $dir = $this->getBackupDirectory();
        $files = File::glob($dir.DIRECTORY_SEPARATOR.'backup-*.sql.gz') ?: [];

        $result = [];
        foreach ($files as $filePath) {
            $name = basename($filePath);
            $size = File::size($filePath);
            $mtime = File::lastModified($filePath);

            $result[] = [
                'filename' => $name,
                'path' => $filePath,
                'size' => $size,
                'size_formatted' => $this->formatSize($size),
                'created_at' => Carbon::createFromTimestamp($mtime)->isoFormat('D MMMM Y, HH:mm:ss'),
                'timestamp' => $mtime,
            ];
        }

        // Urutkan terbaru di atas
        usort($result, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $result;
    }

    /**
     * Buat backup database (MySQL/MariaDB atau SQLite).
     *
     * @param  string  $destination  'local' (server), 'download', atau 'cloud'
     */
    public function backup(string $destination = 'local', int $keepDays = 7, ?string $cloudDisk = null): array
    {
        $connName = config('database.default');
        $conn = config("database.connections.{$connName}");
        $driver = $conn['driver'] ?? 'mysql';

        $dir = $this->getBackupDirectory();
        $filename = 'backup-'.now()->format('Y-m-d_His').'.sql.gz';
        $targetGz = $dir.DIRECTORY_SEPARATOR.$filename;

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->dumpMysql($conn, $targetGz);
        } elseif ($driver === 'sqlite') {
            $this->dumpSqlite($conn, $targetGz);
        } else {
            throw new RuntimeException("Driver database '{$driver}' tidak didukung untuk backup.");
        }

        $size = File::exists($targetGz) ? File::size($targetGz) : 0;
        $deletedCount = $this->rotateBackups($dir, $keepDays);

        // Upload ke Cloud jika diminta dan disk cloud terkonfigurasi
        $cloudStatus = null;
        if ($destination === 'cloud' && $cloudDisk && config("filesystems.disks.{$cloudDisk}")) {
            try {
                $cloudStream = fopen($targetGz, 'r');
                if ($cloudStream) {
                    Storage::disk($cloudDisk)->put('backups/'.$filename, $cloudStream);
                    fclose($cloudStream);
                    $cloudStatus = "Tersinkronisasi ke {$cloudDisk}";
                }
            } catch (Throwable $e) {
                $cloudStatus = 'Gagal upload cloud: '.$e->getMessage();
            }
        }

        $this->auditService->catat(
            'Database',
            'backup',
            auth()->id() ?? 0,
            "Backup database dibuat: {$filename} ({$this->formatSize($size)})"
        );

        return [
            'success' => true,
            'filename' => $filename,
            'path' => $targetGz,
            'size' => $size,
            'size_formatted' => $this->formatSize($size),
            'deleted_old' => $deletedCount,
            'cloud_status' => $cloudStatus,
        ];
    }

    /**
     * Restore database dari file .sql.gz lokal server.
     */
    public function restore(string $filePath): array
    {
        if (! File::exists($filePath)) {
            throw new RuntimeException("File backup tidak ditemukan: {$filePath}");
        }

        // 1. Ambil emergency pre-restore snapshot otomatis
        $preBackup = null;
        try {
            $preBackup = $this->backup('local', 30);
        } catch (Throwable $e) {
            // Lanjut jika pre-backup gagal karena situasi darurat
        }

        $connName = config('database.default');
        $conn = config("database.connections.{$connName}");
        $driver = $conn['driver'] ?? 'mysql';

        // 2. Uncompress gzip ke file SQL sementara
        $tempSql = storage_path('app/temp_restore_'.uniqid().'.sql');
        $this->uncompressGzip($filePath, $tempSql);

        try {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $this->executeRestoreMysql($conn, $tempSql);
            } elseif ($driver === 'sqlite') {
                $this->executeRestoreSqlite($conn, $tempSql);
            } else {
                throw new RuntimeException("Driver database '{$driver}' tidak didukung untuk restore.");
            }
        } finally {
            @unlink($tempSql);
        }

        $this->auditService->catat(
            'Database',
            'restore',
            auth()->id() ?? 0,
            'Database dipulihkan dari: '.basename($filePath)
        );

        return [
            'success' => true,
            'restored_from' => basename($filePath),
            'pre_restore_backup' => $preBackup['filename'] ?? null,
        ];
    }

    /**
     * Truncate daftar tabel dengan aman (disable FK checks).
     */
    public function truncateTables(array $tables): int
    {
        $driver = DB::getDriverName();
        $count = 0;

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            foreach ($tables as $tbl) {
                if (Schema::hasTable($tbl)) {
                    DB::table($tbl)->truncate();
                    $count++;
                }
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } else {
            // SQLite
            DB::statement('PRAGMA foreign_keys = OFF;');
            foreach ($tables as $tbl) {
                if (Schema::hasTable($tbl)) {
                    DB::table($tbl)->delete();
                    $count++;
                }
            }
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        return $count;
    }

    /**
     * Enterprise Database Reset Suite:
     * - transaksi_only: Kosongkan transaksi, mutasi, keuangan, log (siap go-live / pasca-testing)
     * - master_and_transaksi: Kosongkan transaksi + katalog produk, harga, tier, supplier, pelanggan
     * - factory_reset: Kembalikan persis ke setelan awal pabrik (jalankan ulang seeder bawaan)
     * - full_wipe: Pembersihan total database dengan perlindungan anti-lockout Super Admin
     */
    public function resetDatabase(string $mode = 'transaksi_only', array $options = []): array
    {
        // 1. Mandatory snapshot backup darurat sebelum pembersihan (Enterprise Standard)
        $preBackup = $this->backup('local', 30);

        $transactionTables = [
            'transaksi_item',
            'transaksi',
            'returns_item',
            'returns',
            'kas_sesi',
            'kas_mutasi_laci',
            'kas_matching',
            'tiket_servis_estimasi_item',
            'tiket_servis_item',
            'servis_status_log',
            'servis_sparepart',
            'garansi',
            'tiket_servis',
            'stok_log',
            'stock_mutation_log',
            'stok_transfer_item',
            'stok_transfer',
            'stok_opname_item',
            'stok_opname',
            'nomor_seri_events',
            'cycle_count_items',
            'cycle_counts',
            'purchase_order_items',
            'purchase_orders',
            'grn_items',
            'grn',
            'jurnal_akuntansi',
            'jurnal_header',
            'piutang',
            'utang',
            'komisi',
            'payroll_komisi_detail',
            'hr_payroll_slip',
            'hr_absensi',
            'aset_tetap',
            'kampanye_broadcast',
            'leads',
            'notifikasi_keluar',
            'activity_log',
            'audit_logs',
        ];

        $masterTables = [
            'stok_items',
            'sku_variants',
            'produk',
            'kategori',
            'brand',
            'kualitas',
            'satuan',
            'kondisi',
            'tipe_hp',
            'harga_tier',
            'riwayat_perubahan_harga',
            'supplier_scores',
            'channel_product_mapping',
            'sid_retail_raw_item',
            'sid_retail_raw_header',
            'sid_retail_raw_master',
            'sid_import_map',
            'import_log',
            'saved_reports',
            'pemasok',
            'supplier',
            'pelanggan',
        ];

        $orgTables = [
            'rak',
            'gudang',
            'user_cabang',
            'cabang',
            'user_preferences',
            'device_sessions',
            'personal_access_tokens',
        ];

        $clearedCount = 0;
        $label = '';
        $defaultPassword = null;

        switch ($mode) {
            case 'master_and_transaksi':
                $label = 'Reset Transaksi + Katalog & Pelanggan';
                $clearedCount = $this->truncateTables(array_merge($transactionTables, $masterTables));

                // Pastikan tier membership CRM tetap terdefinisi
                try {
                    (new TierMembershipSeeder)->run();
                } catch (Throwable $e) {
                }

                if (! empty($options['reset_password'])) {
                    $defaultPassword = 'Password123!';
                    User::query()->update(['password' => Hash::make($defaultPassword)]);
                }
                break;

            case 'factory_reset':
                $label = 'Factory Reset (Setelan Pabrik Awal)';
                $clearedCount = $this->truncateTables(array_merge($transactionTables, $masterTables, $orgTables));
                $defaultPassword = 'Password123!';

                // Bersihkan user tambahan selain akun resmi bawaan
                $defaultEmails = [
                    'superadmin@uteparts.id',
                    'owner@uteparts.id',
                    'admintoko@uteparts.id',
                    'kasir@uteparts.id',
                    'teknisi@uteparts.id',
                    'gudang@uteparts.id',
                    'finance@uteparts.id',
                    'marketing@uteparts.id',
                    'hr@uteparts.id',
                ];
                User::whereNotIn('email', $defaultEmails)->delete();
                User::query()->update(['password' => Hash::make($defaultPassword)]);

                // Jalankan seeder default pabrik
                $seeders = [
                    RolesAndPermissionsSeeder::class,
                    CabangSeeder::class,
                    TierMembershipSeeder::class,
                    AdminUserSeeder::class,
                    RoleUserSeeder::class,
                    AkunCoaSeeder::class,
                    JenisServisSeeder::class,
                    KomisiSkemaSeeder::class,
                ];
                foreach ($seeders as $seederClass) {
                    try {
                        (new $seederClass)->run();
                    } catch (Throwable $e) {
                    }
                }

                $activeSuper = User::where('email', 'superadmin@uteparts.id')->first();
                if ($activeSuper) {
                    auth()->login($activeSuper);
                }
                break;

            case 'full_wipe':
                $label = 'Full Wipe (Pembersihan Total Database)';
                $currentUser = auth()->user();
                $savedId = $currentUser?->id;
                $savedEmail = $currentUser?->email ?? 'superadmin@uteparts.id';
                $savedName = $currentUser?->name ?? 'Super Administrator';
                $defaultPassword = 'Password123!';

                // Dapatkan seluruh tabel di database kecuali users
                $allTables = array_merge($transactionTables, $masterTables, $orgTables);
                $clearedCount = $this->truncateTables($allTables);

                // Anti-lockout: Hapus semua user lain, pertahankan akun super admin aktif
                if ($savedId) {
                    User::where('id', '!=', $savedId)->delete();
                    $admin = User::find($savedId);
                    if ($admin) {
                        $admin->update(['password' => Hash::make($defaultPassword), 'is_active' => true]);
                    }
                } else {
                    $admin = User::firstOrCreate(
                        ['email' => $savedEmail],
                        [
                            'name' => $savedName,
                            'phone' => '081234567801',
                            'password' => Hash::make($defaultPassword),
                            'is_active' => true,
                        ]
                    );
                }

                // Pulihkan izin inti dan cabang default
                try {
                    (new RolesAndPermissionsSeeder)->run();
                    $cabang = Cabang::firstOrCreate(
                        ['kode' => 'CBG-01'],
                        ['nama' => 'Cabang Utama', 'is_active' => true]
                    );
                    if ($admin) {
                        $admin->assignRole('super-admin');
                        $admin->cabangs()->syncWithoutDetaching([$cabang->id => ['is_default' => true]]);
                        auth()->login($admin);
                    }
                } catch (Throwable $e) {
                }
                break;

            case 'transaksi_only':
            default:
                $label = 'Reset Data Transaksional (Siap Go-Live)';
                $clearedCount = $this->truncateTables($transactionTables);

                if (($options['zero_stock'] ?? true) === true && Schema::hasTable('stok_items')) {
                    DB::table('stok_items')->update(['jumlah' => 0]);
                }

                if (! empty($options['reset_password'])) {
                    $defaultPassword = 'Password123!';
                    User::query()->update(['password' => Hash::make($defaultPassword)]);
                    try {
                        (new RoleUserSeeder)->run();
                    } catch (Throwable $e) {
                    }
                }
                break;
        }

        $this->auditService->catat(
            'Database',
            'reset',
            auth()->id() ?? 0,
            "{$label} dieksekusi: {$clearedCount} tabel dibersihkan. Pre-backup snapshot: {$preBackup['filename']}."
        );

        return [
            'success' => true,
            'mode' => $mode,
            'mode_label' => $label,
            'cleared_tables' => $clearedCount,
            'pre_reset_backup' => $preBackup['filename'],
            'default_password' => $defaultPassword,
        ];
    }

    /**
     * Safe Reset (Legacy alias): Bersihkan data transaksi, stok, jurnal, tiket servis
     * TETAP PERTAHANKAN akun pengguna & reset passwordnya ke standar.
     */
    public function safeReset(): array
    {
        return $this->resetDatabase('transaksi_only', [
            'zero_stock' => true,
            'reset_password' => true,
        ]);
    }

    /**
     * Cek kesehatan database & Auto-Restore jika terdeteksi crash/hilang.
     */
    public function checkHealthAndAutoRestore(): array
    {
        $status = 'healthy';
        $issues = [];

        try {
            // Probe tabel utama
            DB::table('users')->count();
            DB::table('cabang')->count();
            DB::table('produk')->count();
        } catch (Throwable $e) {
            $status = 'crashed';
            $issues[] = $e->getMessage();
        }

        $autoRestoreResult = null;
        if ($status === 'crashed') {
            // Temukan backup terbaru yang valid
            $backups = $this->listBackups();
            if (! empty($backups)) {
                $latest = $backups[0]['path'];
                try {
                    $autoRestoreResult = $this->restore($latest);
                    $status = 'recovered';
                } catch (Throwable $e) {
                    $autoRestoreResult = ['error' => $e->getMessage()];
                }
            } else {
                $autoRestoreResult = ['error' => 'Tidak ada file backup yang tersedia untuk auto-recovery.'];
            }
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'auto_restore' => $autoRestoreResult,
        ];
    }

    /**
     * Hapus berkas backup tertentu.
     */
    public function deleteBackup(string $filename): bool
    {
        $sanitized = basename($filename);
        $file = $this->getBackupDirectory().DIRECTORY_SEPARATOR.$sanitized;

        if (File::exists($file)) {
            $deleted = File::delete($file);
            if ($deleted) {
                $this->auditService->catat(
                    'Database',
                    'delete_backup',
                    auth()->id() ?? 0,
                    "Berkas backup dihapus: {$sanitized}"
                );
            }

            return $deleted;
        }

        return false;
    }

    // ==========================================
    // Internal Helper Methods
    // ==========================================

    protected function dumpMysql(array $conn, string $targetGz): void
    {
        $mysqldump = (new ExecutableFinder)->find('mysqldump') ?? 'mysqldump';
        $tmpSql = substr($targetGz, 0, -3);

        $args = [
            $mysqldump,
            '--host='.($conn['host'] ?? '127.0.0.1'),
            '--user='.($conn['username'] ?? ''),
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            '--result-file='.$tmpSql,
        ];

        if (! empty($conn['unix_socket'])) {
            $args[] = '--socket='.$conn['unix_socket'];
        } else {
            $args[] = '--port='.($conn['port'] ?? '3306');
        }

        $args[] = (string) $conn['database'];

        try {
            $process = new Process($args, null, ['MYSQL_PWD' => (string) ($conn['password'] ?? '')]);
            $process->setTimeout(3600);
            $process->run();
        } catch (Throwable $e) {
            @unlink($tmpSql);

            throw new RuntimeException('Gagal menjalankan mysqldump: '.$e->getMessage(), 0, $e);
        }

        if (! $process->isSuccessful() || ! is_file($tmpSql) || filesize($tmpSql) === 0) {
            $err = trim($process->getErrorOutput());
            @unlink($tmpSql);

            throw new RuntimeException("mysqldump gagal: {$err}");
        }

        $this->gzipFile($tmpSql, $targetGz);
        @unlink($tmpSql);
    }

    protected function dumpSqlite(array $conn, string $targetGz): void
    {
        $dbPath = $conn['database'] ?? database_path('database.sqlite');
        $tmpSql = substr($targetGz, 0, -3);

        if ($dbPath === ':memory:' || ! is_file($dbPath)) {
            // Untuk in-memory DB saat test, buat skema snapshot via query
            $tables = DB::select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $out = "-- SQLite in-memory snapshot\n";
            foreach ($tables as $t) {
                $out .= $t->sql.";\n";
            }
            File::put($tmpSql, $out);
        } else {
            // Copy file atau dump
            File::copy($dbPath, $tmpSql);
        }

        $this->gzipFile($tmpSql, $targetGz);
        @unlink($tmpSql);
    }

    protected function executeRestoreMysql(array $conn, string $sqlPath): void
    {
        $mysql = (new ExecutableFinder)->find('mysql') ?? 'mysql';

        $args = [
            $mysql,
            '--host='.($conn['host'] ?? '127.0.0.1'),
            '--user='.($conn['username'] ?? ''),
        ];

        if (! empty($conn['unix_socket'])) {
            $args[] = '--socket='.$conn['unix_socket'];
        } else {
            $args[] = '--port='.($conn['port'] ?? '3306');
        }

        $args[] = (string) $conn['database'];

        // Eksekusi stream SQL ke mysql client
        $process = Process::fromShellCommandline(
            implode(' ', array_map('escapeshellarg', $args)).' < '.escapeshellarg($sqlPath),
            null,
            ['MYSQL_PWD' => (string) ($conn['password'] ?? '')]
        );
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Restore MySQL gagal: '.$process->getErrorOutput());
        }
    }

    protected function executeRestoreSqlite(array $conn, string $sqlPath): void
    {
        $dbPath = $conn['database'] ?? database_path('database.sqlite');
        if ($dbPath !== ':memory:') {
            File::copy($sqlPath, $dbPath);
        } else {
            DB::unprepared(File::get($sqlPath));
        }
    }

    protected function gzipFile(string $source, string $dest): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($dest, 'wb9');

        if (! $in || ! $out) {
            throw new RuntimeException('Gagal membuka stream file kompresi.');
        }

        while (! feof($in)) {
            gzwrite($out, fread($in, 8192));
        }

        fclose($in);
        gzclose($out);
    }

    protected function uncompressGzip(string $sourceGz, string $dest): void
    {
        $in = gzopen($sourceGz, 'rb');
        $out = fopen($dest, 'wb');

        if (! $in || ! $out) {
            throw new RuntimeException('Gagal membuka stream file dekompresi.');
        }

        while (! gzeof($in)) {
            fwrite($out, gzread($in, 8192));
        }

        gzclose($in);
        fclose($out);
    }

    protected function rotateBackups(string $dir, int $keepDays): int
    {
        $cutoff = now()->subDays($keepDays)->timestamp;
        $deleted = 0;

        foreach (glob($dir.DIRECTORY_SEPARATOR.'backup-*.sql.gz') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    protected function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
