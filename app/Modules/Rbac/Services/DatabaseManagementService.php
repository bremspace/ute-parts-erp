<?php

namespace App\Modules\Rbac\Services;

use App\Models\User;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Rbac\Services\AppUpdateService;
use App\Modules\Rbac\Services\AuditService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\TipeHp;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\KategoriProdukSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\RoleUserSeeder;
use Database\Seeders\TierMembershipSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Enterprise Database Management Service for Ute Parts.
 * Handles Backup, Restore, Health Check, Auto-Recovery, and Safe Reset.
 */
class DatabaseManagementService
{
    public const BACKUP_DIR = 'app/backups';

    public function __construct(
        protected NotificationService $notifikasi,
        protected AuditService $auditService,
        protected AppUpdateService $updateService
    ) {}

    /**
     * Bersihkan seluruh cache server: optimize, application cache, permissions, dan OPcache.
     */
    public function clearServerCache(): array
    {
        // ponytail: artisan optimize:clear + Cache::flush + permission cache covers 99% needs; add targeted redis tagging when distributed cache introduced.
        Artisan::call('optimize:clear');
        Cache::flush();

        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $opcacheReset = false;
        if (function_exists('opcache_reset')) {
            $opcacheReset = (bool) @opcache_reset();
        }

        $this->auditService->catat(
            'ServerCache',
            'clear',
            null,
            'Pembersihan seluruh cache server (optimize:clear, Cache::flush, permission, OPcache)'
        );

        return [
            'success' => true,
            'message' => 'Cache server (aplikasi, rute, view, konfigurasi, hak akses, dan OPcache) berhasil dibersihkan.',
            'opcache' => $opcacheReset,
        ];
    }

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
        $files = array_merge(
            File::glob($dir.DIRECTORY_SEPARATOR.'backup-*.sql.gz') ?: [],
            File::glob($dir.DIRECTORY_SEPARATOR.'snapshot-*.zip') ?: [],
            File::glob($dir.DIRECTORY_SEPARATOR.'upload_*') ?: [],
            File::glob($dir.DIRECTORY_SEPARATOR.'*.sql') ?: []
        );

        $result = [];
        foreach (array_unique($files) as $filePath) {
            $name = basename($filePath);
            $size = File::size($filePath);
            $mtime = File::lastModified($filePath);

            $result[] = [
                'filename' => $name,
                'path' => $filePath,
                'is_snapshot' => str_ends_with($name, '.zip'),
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
     * @param  bool    $fullSnapshot Apakah membundel storage/app/public + manifest APP_KEY
     */
    public function backup(string $destination = 'local', int $keepDays = 7, ?string $cloudDisk = null, bool $fullSnapshot = false): array
    {
        $connName = config('database.default');
        $conn = config("database.connections.{$connName}");
        $driver = $conn['driver'] ?? 'mysql';

        $dir = $this->getBackupDirectory();
        $timestamp = now()->format('Y-m-d_His');
        $dbGzName = 'backup-'.$timestamp.'.sql.gz';
        $targetGz = $dir.DIRECTORY_SEPARATOR.$dbGzName;

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->dumpMysql($conn, $targetGz);
        } elseif ($driver === 'sqlite') {
            $this->dumpSqlite($conn, $targetGz);
        } else {
            throw new RuntimeException("Driver database '{$driver}' tidak didukung untuk backup.");
        }

        $finalFile = $targetGz;
        $finalFilename = $dbGzName;

        if ($fullSnapshot) {
            $zipName = 'snapshot-'.$timestamp.'.zip';
            $zipPath = $dir.DIRECTORY_SEPARATOR.$zipName;
            $this->createFullSnapshotZip($targetGz, $zipPath);
            @unlink($targetGz);
            $finalFile = $zipPath;
            $finalFilename = $zipName;
        }

        $size = File::exists($finalFile) ? File::size($finalFile) : 0;
        $deletedCount = $this->rotateBackups($dir, $keepDays);

        // Upload ke Cloud jika diminta dan disk cloud terkonfigurasi
        $cloudStatus = null;
        if ($destination === 'cloud' && $cloudDisk && config("filesystems.disks.{$cloudDisk}")) {
            try {
                $cloudStream = fopen($finalFile, 'r');
                if ($cloudStream) {
                    Storage::disk($cloudDisk)->put('backups/'.$finalFilename, $cloudStream);
                    fclose($cloudStream);
                    $cloudStatus = "Tersinkronisasi ke {$cloudDisk}";
                }
            } catch (Throwable $e) {
                $cloudStatus = 'Gagal upload cloud: '.$e->getMessage();
            }
        }

        try {
            $this->auditService->catat(
                'Database',
                'backup',
                auth()->id() ?? 0,
                ($fullSnapshot ? 'Full snapshot clone' : 'Backup database')." dibuat: {$finalFilename} ({$this->formatSize($size)})"
            );
        } catch (Throwable $e) {
            Log::warning('Gagal mencatat audit log backup: '.$e->getMessage());
        }

        return [
            'success' => true,
            'filename' => $finalFilename,
            'path' => $finalFile,
            'is_snapshot' => $fullSnapshot,
            'size' => $size,
            'size_formatted' => $this->formatSize($size),
            'deleted_old' => $deletedCount,
            'cloud_status' => $cloudStatus,
        ];
    }

    /**
     * Restore database dari file .sql.gz, .sql, atau .zip (Full Snapshot) lokal server.
     */
    public function restore(string $filePath, ?string $targetDatabase = null): array
    {
        @set_time_limit(1800);
        @ini_set('memory_limit', '512M');

        if (! File::exists($filePath)) {
            throw new RuntimeException("File backup tidak ditemukan: {$filePath}");
        }

        $connName = config('database.default');
        $conn = config("database.connections.{$connName}");
        $driver = $conn['driver'] ?? 'mysql';

        $isCustomTarget = ! empty($targetDatabase) && $targetDatabase !== ($conn['database'] ?? null);
        if (! empty($targetDatabase)) {
            $conn['database'] = $targetDatabase;
        }

        // 1. Ambil emergency pre-restore snapshot otomatis (hanya jika menimpa database utama)
        $preBackup = null;
        if (! $isCustomTarget) {
            try {
                $preBackup = $this->backup('local', 30, null, false);
            } catch (Throwable $e) {
                // Lanjut jika pre-backup gagal karena situasi darurat
            }
        }

        $isZip = str_ends_with(strtolower($filePath), '.zip');
        $extractedSql = null;
        $tempExtractDir = null;
        $appKeySynced = false;
        $mediaRestored = false;

        if ($isZip) {
            $tempExtractDir = storage_path('app/temp_zip_'.uniqid());
            File::ensureDirectoryExists($tempExtractDir);

            $zip = new ZipArchive;
            if ($zip->open($filePath) !== true) {
                throw new RuntimeException("Gagal membuka arsip snapshot ZIP: {$filePath}");
            }
            $zip->extractTo($tempExtractDir);
            $zip->close();

            // Cek manifest.json
            $manifestPath = $tempExtractDir.DIRECTORY_SEPARATOR.'manifest.json';
            if (File::exists($manifestPath)) {
                $manifest = json_decode(File::get($manifestPath), true) ?: [];
                if (! empty($manifest['app_key']) && ! $isCustomTarget) {
                    $currentKey = config('app.key');
                    if ($currentKey !== $manifest['app_key']) {
                        $this->updateService->updateEnv(['APP_KEY' => $manifest['app_key']]);
                        $appKeySynced = true;
                    }
                }
            }

            // Restore media storage/app/public jika ada
            $mediaTar = $tempExtractDir.DIRECTORY_SEPARATOR.'storage_public.tar.gz';
            if (File::exists($mediaTar) && ! $isCustomTarget) {
                $publicStorageDir = storage_path('app/public');
                File::ensureDirectoryExists($publicStorageDir);
                
                // Gunakan tar atau php fallback
                $tar = (new ExecutableFinder)->find('tar') ?? 'tar';
                $proc = Process::fromShellCommandline(
                    escapeshellarg($tar).' -xzf '.escapeshellarg($mediaTar).' -C '.escapeshellarg($publicStorageDir)
                );
                $proc->run();
                if ($proc->isSuccessful()) {
                    $mediaRestored = true;
                    Artisan::call('storage:link');
                }
            }

            // Temukan database.sql.gz
            $candidateDb = $tempExtractDir.DIRECTORY_SEPARATOR.'database.sql.gz';
            if (File::exists($candidateDb)) {
                $extractedSql = $candidateDb;
            } else {
                $found = glob($tempExtractDir.DIRECTORY_SEPARATOR.'*.sql.gz') ?: glob($tempExtractDir.DIRECTORY_SEPARATOR.'*.sql');
                if (! empty($found)) {
                    $extractedSql = $found[0];
                }
            }

            if (! $extractedSql || ! File::exists($extractedSql)) {
                File::deleteDirectory($tempExtractDir);
                throw new RuntimeException("Arsip ZIP tidak berisi berkas database (.sql / .sql.gz).");
            }

            $effectiveSqlFile = $extractedSql;
        } else {
            $effectiveSqlFile = $filePath;
        }

        // 2. Uncompress gzip ke file SQL sementara jika terkompresi
        $tempSql = storage_path('app/temp_restore_'.uniqid().'.sql');
        $isGz = str_ends_with(strtolower($effectiveSqlFile), '.gz');

        if ($isGz) {
            $this->uncompressGzip($effectiveSqlFile, $tempSql);
        } else {
            File::copy($effectiveSqlFile, $tempSql);
        }

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
            if ($tempExtractDir && File::isDirectory($tempExtractDir)) {
                File::deleteDirectory($tempExtractDir);
            }
        }

        $note = 'Database dipulihkan dari: '.basename($filePath).($isCustomTarget ? " (Target DB: {$targetDatabase})" : '');
        if ($appKeySynced) {
            $note .= ' [APP_KEY berhasil disinkronkan]';
        }
        if ($mediaRestored) {
            $note .= ' [Media public storage dipulihkan]';
        }

        $this->auditService->catat(
            'Database',
            'restore',
            auth()->id() ?? 0,
            $note
        );

        return [
            'success' => true,
            'restored_from' => basename($filePath),
            'target_database' => $conn['database'],
            'is_snapshot' => $isZip,
            'app_key_synced' => $appKeySynced,
            'media_restored' => $mediaRestored,
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
            'return_penjualan_item',
            'return_penjualan',
            'return_pembelian_item',
            'return_pembelian',
            'pembayaran_supplier',
            'pengiriman',
            'channel_orders',
            'returns_item',
            'returns',
            'kas_sesi',
            'kas_mutasi_laci',
            'kas_matching',
            'tiket_servis_estimasi_item',
            'tiket_servis_item',
            'tiket_servis_progress',
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
            'nomor_seri_produk',
            'cycle_count_items',
            'cycle_counts',
            'cycle_count_task',
            'cycle_count_schedule',
            'purchase_order_item',
            'purchase_order_items',
            'purchase_order',
            'purchase_orders',
            'grn_items',
            'grn',
            'jurnal_akuntansi',
            'jurnal_header',
            'piutang',
            'utang',
            'komisi',
            'skema_komisi',
            'skema_komisi_reseller',
            'komisi_teknisi_rules',
            'payroll_komisi_detail',
            'payroll_slip',
            'payroll_periode',
            'karyawan_komponen_gaji',
            'karyawan',
            'hr_payroll_slip',
            'hr_absensi',
            'absensi_log',
            'shift_jadwal',
            'kpi_hasil',
            'aset_tetap',
            'kampanye_broadcast',
            'leads',
            'komplain_pelanggan',
            'notifikasi_keluar',
            'notifications',
            'approval_requests',
            'webhook_delivery',
            'supplier_saldo_mutasi',
            'pelanggan_poin_log',
            'activity_log',
            'audit_logs',
        ];

        $masterTables = [
            'stok_items',
            'stok_item',
            'sku_variants',
            'produk_barcodes',
            'produk',
            'produk_satuan_konversi',
            'kompatibilitas_antar_produk',
            'kompatibilitas_produk_tipe_hp',
            'kategori_produk',
            'kategori',
            'brands',
            'brand',
            'kualitas_produk',
            'kualitas',
            'satuan_unit',
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
            'sid_retail_raw_arus_stok',
            'sid_retail_raw_barang',
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
            'qris_cabang',
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

                // Pastikan tier membership CRM dan master default dipulihkan
                try {
                    (new TierMembershipSeeder)->run();
                    (new KategoriProdukSeeder)->run();
                    (new MasterDataSeeder)->run();
                } catch (Throwable $e) {
                }

                $this->ensureDefaultMasterAttributes();

                if (! empty($options['reset_password'])) {
                    $defaultPassword = 'Password123!';
                    User::query()->update(['password' => Hash::make($defaultPassword)]);
                }
                break;

            case 'factory_reset':
                $label = 'Factory Reset (Setelan Pabrik Awal)';
                $clearedCount = $this->truncateTables(array_merge($transactionTables, $masterTables, $orgTables));
                $defaultPassword = 'Password123!';

                // Pulihkan setelan default tier membership
                try {
                    (new TierMembershipSeeder)->run();
                } catch (Throwable $e) {
                }

                // Bersihkan user tambahan selain akun resmi bawaan & reset 2FA
                $defaultEmails = [
                    'superadmin@bremspace.id',
                    'super-admin@bremspace.id',
                    'owner@bremspace.id',
                    'admintoko@bremspace.id',
                    'kasir@bremspace.id',
                    'teknisi@bremspace.id',
                    'gudang@bremspace.id',
                    'finance@bremspace.id',
                    'marketing@bremspace.id',
                    'hr@bremspace.id',
                    'superadmin@uteparts.id',
                    'super-admin@uteparts.id',
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
                User::query()->update([
                    'password' => Hash::make($defaultPassword),
                    'two_factor_secret' => null,
                    'two_factor_confirmed_at' => null,
                    'two_factor_backup_codes' => null,
                ]);

                // Jalankan seeder default pabrik (DatabaseSeeder standar zero dummy)
                $seeders = [
                    DatabaseSeeder::class,
                ];
                foreach ($seeders as $seederClass) {
                    try {
                        (new $seederClass)->run();
                    } catch (Throwable $e) {
                    }
                }

                // Jaminan cabang utama, gudang default, rak default & relasi pivot seluruh akun bawaan
                $cabangUtama = Cabang::firstOrCreate(
                    ['kode' => 'CBG-01'],
                    ['nama' => 'Cabang Pusat', 'is_active' => true]
                );
                $this->ensureDefaultWarehouseAndShelf($cabangUtama->id);
                $this->ensureDefaultMasterAttributes();
                foreach (User::all() as $u) {
                    if (! $u->cabangs()->where('cabang_id', $cabangUtama->id)->exists()) {
                        $u->cabangs()->attach($cabangUtama->id, ['is_default' => true]);
                    }
                }

                app(PermissionRegistrar::class)->forgetCachedPermissions();

                try {
                    app(KonfigurasiService::class)->set('app_setup_completed', false, 'Setup awal toko perlu dijalankan');
                } catch (Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Gagal set app_setup_completed di factory_reset: '.$e->getMessage());
                }

                $activeSuper = User::where('email', 'superadmin@bremspace.id')->first()
                    ?? User::where('email', 'superadmin@uteparts.id')->first();
                if ($activeSuper) {
                    auth()->login($activeSuper);
                }
                break;

            case 'full_wipe':
                $label = 'Full Wipe (Pembersihan Total Database)';
                $currentUser = auth()->user();
                $savedId = $currentUser?->id;
                $savedEmail = $currentUser?->email ?? 'superadmin@bremspace.id';
                $savedName = $currentUser?->name ?? 'Super Administrator';
                $defaultPassword = 'Password123!';

                // Tangkap role yang dimiliki akun saat ini untuk dipertahankan
                $savedRoles = $currentUser ? $currentUser->getRoleNames()->toArray() : [];
                if (empty($savedRoles)) {
                    $savedRoles = ['super-admin'];
                }

                // Dapatkan seluruh tabel di database kecuali users
                $allTables = array_merge($transactionTables, $masterTables, $orgTables);
                $clearedCount = $this->truncateTables($allTables);

                // Anti-lockout: Hapus semua user lain, pertahankan akun super admin / owner aktif & reset 2FA
                if ($savedId) {
                    User::where('id', '!=', $savedId)->delete();
                    $admin = User::find($savedId);
                    if ($admin) {
                        $admin->update([
                            'password' => Hash::make($defaultPassword),
                            'is_active' => true,
                            'two_factor_secret' => null,
                            'two_factor_confirmed_at' => null,
                            'two_factor_backup_codes' => null,
                        ]);
                    }
                } else {
                    $admin = User::firstOrCreate(
                        ['email' => $savedEmail],
                        [
                            'name' => $savedName,
                            'phone' => '081234567801',
                            'password' => Hash::make($defaultPassword),
                            'is_active' => true,
                            'two_factor_secret' => null,
                            'two_factor_confirmed_at' => null,
                            'two_factor_backup_codes' => null,
                        ]
                    );
                }

                // Pulihkan izin inti dan cabang default
                try {
                    (new RolesAndPermissionsSeeder)->run();
                    (new AkunCoaSeeder)->run();
                    (new TierMembershipSeeder)->run();
                    (new MasterDataSeeder)->run();
                    (new KategoriProdukSeeder)->run();
                    $cabang = Cabang::firstOrCreate(
                        ['kode' => 'CBG-01'],
                        ['nama' => 'Cabang Pusat', 'is_active' => true]
                    );
                    $this->ensureDefaultWarehouseAndShelf($cabang->id);
                    $this->ensureDefaultMasterAttributes();
                    if ($admin) {
                        $admin->syncRoles($savedRoles);
                        $admin->cabangs()->syncWithoutDetaching([$cabang->id => ['is_default' => true]]);
                        auth()->login($admin);
                    }
                } catch (Throwable $e) {
                }

                app(PermissionRegistrar::class)->forgetCachedPermissions();

                try {
                    app(KonfigurasiService::class)->set('app_setup_completed', false, 'Setup awal toko perlu dijalankan');
                } catch (Throwable) {
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
                    User::query()->update([
                        'password' => Hash::make($defaultPassword),
                        'two_factor_secret' => null,
                        'two_factor_confirmed_at' => null,
                        'two_factor_backup_codes' => null,
                    ]);
                    try {
                        (new RoleUserSeeder)->run();
                    } catch (Throwable $e) {
                    }
                }
                break;
        }

        try {
            $this->auditService->catat(
                'Database',
                'reset',
                auth()->id() ?? 0,
                "{$label} dieksekusi: {$clearedCount} tabel dibersihkan. Pre-backup snapshot: {$preBackup['filename']}."
            );
        } catch (Throwable $e) {
            Log::warning('Gagal mencatat audit log reset: '.$e->getMessage());
        }

        return [
            'success' => true,
            'mode' => $mode,
            'label' => $label,
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
     * Auto-provisioning gudang & rak default untuk cabang (Anti-Lockout & Self-Healing Pasca Reset).
     */
    public function ensureDefaultWarehouseAndShelf(int $cabangId): array
    {
        $gudang = Gudang::where('cabang_id', $cabangId)->where('is_active', true)->first();
        if (! $gudang) {
            $baseKode = $cabangId > 1 ? "GDG-C{$cabangId}-01" : 'GDG-01';
            $gudang = Gudang::firstOrCreate(
                ['kode' => $baseKode],
                ['cabang_id' => $cabangId, 'nama' => 'Gudang Utama', 'is_active' => true]
            );
        }

        $rak = Rak::where('gudang_id', $gudang->id)->where('is_active', true)->first();
        if (! $rak) {
            $baseRak = "RAK-DEF-G{$gudang->id}";
            $rak = Rak::firstOrCreate(
                ['kode' => $baseRak],
                ['gudang_id' => $gudang->id, 'nama' => 'Rak Utama', 'is_active' => true]
            );
        }

        return ['gudang' => $gudang, 'rak' => $rak];
    }

    /**
     * Auto-provisioning atribut master produk & servis default sistem.
     * Menjamin atribut default permanen tidak hilang bahkan saat full_wipe atau factory reset.
     */
    public function ensureDefaultMasterAttributes(): void
    {
        try {
            KategoriProduk::getDefault();
            Brand::getDefault();
            KualitasProduk::getDefault();
            SatuanUnit::getDefault();
            TipeHp::getDefault();
            JenisServis::getDefault();

            // Pastikan kondisi produk default 'baru' selalu terdaftar di konfigurasi
            $konfig = app(KonfigurasiService::class);
            $kondisiList = $konfig->get('master_produk_kondisi_list', []);
            $hasBaru = false;
            foreach ($kondisiList as &$k) {
                if (($k['kode'] ?? '') === 'baru') {
                    $k['is_default'] = true;
                    $hasBaru = true;
                    break;
                }
            }
            if (! $hasBaru) {
                array_unshift($kondisiList, [
                    'kode' => 'baru',
                    'nama' => 'Baru',
                    'warna' => 'emerald',
                    'hex' => '#10b981',
                    'keterangan' => 'Kondisi default sistem',
                    'is_active' => true,
                    'is_default' => true,
                ]);
            }
            $konfig->set('master_produk_kondisi_list', array_values($kondisiList));
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal ensureDefaultMasterAttributes: '.$e->getMessage());
        }
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
            $this->ensureDefaultMasterAttributes();
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
            '--max-allowed-packet=128M',
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
            '--max-allowed-packet=128M',
            '--net-buffer-length=16384',
            '--init-command=SET foreign_key_checks=0; SET unique_checks=0; SET autocommit=1;',
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
            $err = trim($process->getErrorOutput());
            if ($err === '') {
                $err = trim($process->getOutput());
            }
            throw new RuntimeException('Restore MySQL gagal (exit '.$process->getExitCode().'): '.($err !== '' ? $err : 'unknown error'));
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

        $patterns = [
            $dir.DIRECTORY_SEPARATOR.'backup-*.sql.gz',
            $dir.DIRECTORY_SEPARATOR.'snapshot-*.zip',
        ];

        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                if (filemtime($file) < $cutoff) {
                    if (@unlink($file)) {
                        $deleted++;
                    }
                }
            }
        }

        return $deleted;
    }

    /**
     * Bungkus file database .sql.gz, folder storage/app/public, dan manifest.json ke dalam single ZIP.
     */
    protected function createFullSnapshotZip(string $sqlGzPath, string $targetZip): void
    {
        $zip = new ZipArchive;
        if ($zip->open($targetZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Gagal membuat arsip snapshot: {$targetZip}");
        }

        // 1. Masukkan database dump
        $zip->addFile($sqlGzPath, 'database.sql.gz');

        // 2. Buat arsip storage/app/public
        $publicStorageDir = storage_path('app/public');
        $tarPath = storage_path('app/temp_media_'.uniqid().'.tar.gz');
        if (File::isDirectory($publicStorageDir)) {
            $tar = (new ExecutableFinder)->find('tar') ?? 'tar';
            $proc = Process::fromShellCommandline(
                escapeshellarg($tar).' -czf '.escapeshellarg($tarPath).' -C '.escapeshellarg($publicStorageDir).' .'
            );
            $proc->run();
            if ($proc->isSuccessful() && File::exists($tarPath)) {
                $zip->addFile($tarPath, 'storage_public.tar.gz');
            }
        }

        // 3. Manifest metadata
        $manifest = [
            'app_name' => config('app.name'),
            'app_key' => config('app.key'),
            'created_at' => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database_driver' => config('database.default'),
            'type' => 'full_clone_snapshot',
        ];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $zip->close();
        @unlink($tarPath);
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

    /**
     * Pindai subnet LAN lokal untuk menemukan host MySQL atau Ute Parts Server secara otomatis.
     */
    public function scanLanDatabaseServers(int $timeoutMs = 450): array
    {
        $localIps = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $out = shell_exec('ipconfig');
            if (preg_match_all('/IPv4 Address[.\s]*:\s*([0-9.]+)/i', (string) $out, $m)) {
                $localIps = array_filter($m[1], fn ($ip) => $ip !== '127.0.0.1' && ! str_starts_with($ip, '169.254.'));
            }
        } else {
            $out = shell_exec("hostname -I 2>/dev/null || ip -4 addr show 2>/dev/null | grep -oP '(?<=inet\\s)\\d+(\\.\\d+){3}'");
            if ($out) {
                $parts = preg_split('/\s+/', trim($out));
                $localIps = array_filter($parts, fn ($ip) => ! empty($ip) && $ip !== '127.0.0.1' && ! str_starts_with($ip, '127.') && ! str_starts_with($ip, '169.254.'));
            }
        }

        if (empty($localIps) && ! empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '127.0.0.1') {
            $localIps[] = $_SERVER['SERVER_ADDR'];
        }

        $subnets = [];
        foreach ($localIps as $lip) {
            $p = explode('.', $lip);
            if (count($p) === 4) {
                $subnets[$p[0].'.'.$p[1].'.'.$p[2].'.'] = true;
            }
        }

        if (empty($subnets)) {
            $subnets['192.168.1.'] = true;
            $subnets['192.168.0.'] = true;
        }

        $found = [];
        foreach (array_keys($subnets) as $prefix) {
            $sockets = [];
            for ($i = 1; $i <= 254; $i++) {
                $target = $prefix.$i;
                $s3306 = @stream_socket_client("tcp://{$target}:3306", $errno, $errstr, 0.04, STREAM_CLIENT_ASYNC_CONNECT);
                if ($s3306) {
                    stream_set_blocking($s3306, false);
                    $sockets["{$target}:3306"] = $s3306;
                }
                $s1741 = @stream_socket_client("tcp://{$target}:1741", $errno, $errstr, 0.04, STREAM_CLIENT_ASYNC_CONNECT);
                if ($s1741) {
                    stream_set_blocking($s1741, false);
                    $sockets["{$target}:1741"] = $s1741;
                }
            }

            $w = array_values($sockets);
            $r = [];
            $e = [];
            if (! empty($w)) {
                $changed = @stream_select($r, $w, $e, 0, $timeoutMs * 1000);
                if ($changed > 0) {
                    foreach ($w as $sock) {
                        $key = array_search($sock, $sockets, true);
                        if ($key !== false) {
                            [$tip, $tport] = explode(':', $key);
                            $found[$tip] = [
                                'ip' => $tip,
                                'port' => (int) $tport,
                                'type' => $tport === '3306' ? 'MySQL Database Server' : 'Ute Parts Web Server (Port 1741)',
                            ];
                        }
                    }
                }
            }

            foreach ($sockets as $s) {
                @fclose($s);
            }
        }

        return [
            'local_ips' => array_values(array_unique($localIps)),
            'servers' => array_values($found),
            'count' => count($found),
        ];
    }
}
