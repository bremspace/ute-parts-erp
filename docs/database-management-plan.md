# Rencana Implementasi: Management Backup, Restore, dan Reset Data (Enterprise Ute Parts)

Dokumen ini adalah spesifikasi teknis dan rencana implementasi modul **Database Management (Backup, Restore, Reset Data, dan Crash Auto-Recovery)** pada aplikasi Ute Parts untuk server on-premise/dedicated PC server.

---

## 1. Tujuan & Kebutuhan Bisnis
1. **Hak Akses Khusus**: Hanya akun ber-role `super-admin` dan `owner` yang diizinkan mengelola modul ini.
2. **Fleksibilitas Backup**:
   - Backup lokal server (`storage/app/backups/`).
   - Unduh langsung ke perangkat lokal (laptop/PC klien Owner atau Super-Admin).
   - Sinkronisasi cloud (Google Drive / S3 compatible via storage disk).
3. **Penyelamatan Darurat (Crash Auto-Recovery)**:
   - Deteksi otomatis tabel rusak / korup / database crash.
   - Deteksi berkas backup terbaru yang valid & auto-restore otomatis untuk menjaga uptime sistem.
4. **Pembersihan Data Aman (Safe Data Reset)**:
   - Data akun default (`superadmin@uteparts.id`, `owner@uteparts.id`, `kasir@uteparts.id`, dll) tetap dipertahankan.
   - Kata sandi akun default direset kembali ke standar pabrik (`Password123!`).
   - Data transaksional (POS, Servis, Mutasi Stok, Jurnal) dikosongkan secara bersih tanpa merusak struktur relasi master.
5. **Keamanan & Guardrails**:
   - Konfirmasi password login saat ini (`current_password`).
   - Konfirmasi teks verifikasi ganda (misal: `SAYA SETUJU RESET DATA`).
   - Otomatis snapshot darurat sebelum operasi restore atau reset dijalankan.
   - Audit log immutable mencatat aktivitas database management.

---

## 2. Rincian Komponen Teknis

### A. Backend Service
- **File**: `app/Modules/Rbac/Services/DatabaseManagementService.php`
  - `backup(string $destination = 'local', int $keepDays = 7): array`
  - `restore(string $filePath): bool`
  - `reset(string $confirmationText, string $currentPassword): array`
  - `healthCheckAndAutoRestore(): array`
  - `listBackups(): array`
  - `deleteBackup(string $filename): bool`

### B. Artisan Console Commands
1. `app/Console/Commands/UteBackupCommand.php` (penyempurnaan dukungan cloud/download & metadata).
2. `app/Console/Commands/UteRestoreCommand.php` (`php artisan ute:restore {file?}`).
3. `app/Console/Commands/UteResetCommand.php` (`php artisan ute:reset --force`).
4. `app/Console/Commands/UteDbHealthCheckCommand.php` (`php artisan ute:db-health-check --auto-restore`).

### C. Livewire & UI Backoffice
- **File Komponen**: `app/Modules/Rbac/Livewire/DatabaseManagement.php`
- **File Blade**: `resources/views/modules/rbac/livewire/database-management.blade.php`
- **Route & Navigasi**: Tab **"Database"** di `/app/pengaturan` (khusus role `super-admin` & `owner`).
- **Fitur Antarmuka**:
  1. Card Status Database & Kesehatan Tabel (Live Health Badge).
  2. Aksi Cepat: Tombol "Backup Sekarang" (opsi simpan server / langsung download ke perangkat).
  3. Form Upload File Backup (`.sql.gz`) dari laptop lokal untuk di-restore ke server.
  4. Tabel Riwayat Backup di Server (Ukuran, Tanggal, Tombol Download, Tombol Restore, Tombol Hapus).
  5. Pengaturan Auto-Backup & Integrasi Cloud (Google Drive / S3).
  6. Panel Darurat "Reset Data Transaksi" dengan Modal Konfirmasi Password + Teks Validasi.

---

## 3. Checklist Eksekusi (T-XX)

- [x] **T-01** — Buat dokumen spesifikasi teknis `docs/database-management-plan.md`.
- [x] **T-02** — Implementasikan `DatabaseManagementService` (backup, restore, reset data aman, health-check, list backup).
- [x] **T-03** — Buat Artisan Commands `ute:restore`, `ute:reset`, dan `ute:db-health-check`.
- [x] **T-04** — Daftarkan command di kernel / scheduler sistem.
- [x] **T-05** — Buat komponen Livewire `DatabaseManagement` dan view Blade dengan desain Ute Prism (responsif desktop & mobile).
- [x] **T-06** — Integrasikan tab "Database" ke dalam menu `/app/pengaturan` dengan pembatasan hak akses role `super-admin` dan `owner`.
- [x] **T-07** — Tulis unit & feature test untuk validasi keamanan, reset data akun default, dan restore.
- [x] **T-08** — Jalankan pengujian menyeluruh (PHPUnit & Pint), pastikan tidak ada regresi sistem.
