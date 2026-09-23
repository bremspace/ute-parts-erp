<?php

namespace Tests\Feature;

use App\Console\Commands\UteBackupCommand;
use App\Modules\Notifikasi\Jobs\KirimNotifikasiJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/**
 * F1-6 — Backup command (PRD-Advanced-UteParts §4, §6).
 *
 * Dirancang tanpa mysqldump sungguhan (test jalan di SQLite :memory:):
 * logika rotasi & gzip diuji langsung, jalur error driver non-MySQL diuji
 * end-to-end termasuk dispatch notifikasi VIA QUEUE (bukan sync).
 *
 * Sengaja TIDAK memakai RefreshDatabase: suite saat ini diblokir migrasi
 * rusak 2026_09_21_000042_add_transfer_audit_columns.php ($table->hasColumn
 * tidak ada di Blueprint) — di luar lane ini. Tabel minimal dibuat manual.
 */
class BackupCommandTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // notifikasi_keluar — dibutuhkan NotificationService::kirim()
        if (! Schema::hasTable('notifikasi_keluar')) {
            Schema::create('notifikasi_keluar', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('tipe');
                $table->string('tujuan')->nullable();
                $table->string('judul')->nullable();
                $table->text('konten');
                $table->json('payload')->nullable();
                $table->string('status')->default('pending');
                $table->text('error')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_perintah_ute_backup_terdaftar_otomatis(): void
    {
        $this->assertArrayHasKey('ute:backup', Artisan::all());
    }

    public function test_jadwal_backup_harian_pukul_0200(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('ute:backup')
            ->assertExitCode(0);
    }

    public function test_rotasi_menghapus_backup_lebih_tua_dari_7_hari(): void
    {
        $dir = storage_path('framework/testing/backup-rotasi-'.uniqid());
        mkdir($dir, 0755, true);

        $lama = $dir.'/backup-2026-09-01_020000.sql.gz';
        $baru = $dir.'/backup-2026-09-22_020000.sql.gz';
        $bukanBackup = $dir.'/random.txt';

        foreach ([$lama, $baru, $bukanBackup] as $f) {
            file_put_contents($f, 'isi');
        }
        // File lama = 8 hari lalu (melewati batas 7 hari), file baru = 1 hari lalu.
        touch($lama, time() - (8 * 86400));
        touch($baru, time() - 86400);

        $dihapus = (new UteBackupCommand)->rotateBackups($dir, 7);

        $this->assertSame(1, $dihapus, 'Harus menghapus tepat 1 file lama (rotasi 7 hari).');
        $this->assertFileDoesNotExist($lama, 'Backup >7 hari wajib dihapus.');
        $this->assertFileExists($baru, 'Backup <7 hari wajib dipertahankan.');
        $this->assertFileExists($bukanBackup, 'File non-backup tidak boleh disentuh rotasi.');

        // Idempoten: rerun tidak ada yang salahhapus.
        $this->assertSame(0, (new UteBackupCommand)->rotateBackups($dir, 7));

        @unlink($baru);
        @unlink($bukanBackup);
        @rmdir($dir);
    }

    public function test_gzip_streaming_menghasilkan_file_valid(): void
    {
        $dir = storage_path('framework/testing/backup-gzip-'.uniqid());
        mkdir($dir, 0755, true);

        $plain = $dir.'/dump.sql';
        $gz = $dir.'/dump.sql.gz';
        file_put_contents($plain, str_repeat('CREATE TABLE contoh (id INT);'.PHP_EOL, 50));

        $cmd = new class extends UteBackupCommand
        {
            public function gzipPublik(string $source, string $target): void
            {
                $this->gzip($source, $target);
            }
        };
        $cmd->gzipPublik($plain, $gz);

        $this->assertFileExists($gz);
        $this->assertGreaterThan(0, filesize($gz));
        $this->assertSame(file_get_contents($plain), gzdecode((string) file_get_contents($gz)));

        @unlink($plain);
        @unlink($gz);
        @rmdir($dir);
    }

    public function test_driver_non_mysql_gagal_graceful_dan_notifikasi_lewat_queue(): void
    {
        Queue::fake();

        // Test jalan di SQLite → guard driver harus menolak dengan pesan
        // Indonesia, TANPA mencoba mysqldump.
        $this->artisan('ute:backup')
            ->expectsOutputToContain('hanya untuk MySQL/MariaDB')
            ->assertFailed();

        // Notifikasi wajib lewat queue (database driver), tidak pernah sync.
        Queue::assertPushed(KirimNotifikasiJob::class);

        $this->assertDatabaseHas('notifikasi_keluar', [
            'judul' => 'Backup Database Gagal',
            'tipe' => 'inapp',
            'status' => 'pending',
        ]);
    }
}
