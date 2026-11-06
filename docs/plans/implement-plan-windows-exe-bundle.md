# Implement Plan: Native Windows Desktop Bundle (.exe)

Status: COMPLETED  
Dokumen Acuan: `docs/prd/PRD-Windows-Desktop-Bundle.md`  

---

## Task & Acceptance Criteria

### Fase 1: Scaffolding Direktori & Konfigurasi Runtime Portable
- [x] T-01 — Buat struktur direktori `tools/windows/` (`config/`, `scripts/`, `payload/`)
- [x] T-02 — Buat template `tools/windows/config/my.ini.template` dengan tuning desktop (port 13307, InnoDB durable, low RAM)
- [x] T-03 — Buat template `tools/windows/config/Caddyfile.desktop` untuk Caddy FastCGI web server
- [x] T-04 — Buat template `tools/windows/config/php.desktop.ini` dengan OPcache dan konfigurasi desktop
- [x] T-05 — Buat template `tools/windows/config/.env.desktop.template` khusus environment Windows Standalone
- [x] T-06 — Buat script `tools/windows/scripts/download-binaries.sh` dan `tools/windows/scripts/download-binaries.ps1` untuk download portable PHP 8.4 NTS, Caddy, MariaDB x64, dan WebView2

### Fase 2: Process Supervisor & Launcher Engine
- [x] T-07 — Buat source code launcher (`tools/windows/launcher/`) dengan implementasi Windows Named Mutex (Single Instance)
- [x] T-08 — Implementasikan Windows Job Object `JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE` untuk auto-kill child process (Zero Zombie Process)
- [x] T-09 — Implementasikan Dynamic Port Auto-Negotiation (Port 13307 -> 13357, App Port 1741 -> 1791)
- [x] T-10 — Implementasikan Graceful Shutdown Handler (`mysqladmin shutdown`) dan Edge WebView2 Shell invocation

### Fase 3: Installer Inno Setup & Packaging Workflow
- [x] T-11 — Buat skrip Inno Setup `tools/windows/installer.iss` dengan deteksi WebView2 Runtime & isolasi folder data `%LOCALAPPDATA%`
- [x] T-12 — Buat workflow CI/CD `.github/workflows/build-windows-exe.yml` untuk compile dan release installer `.exe` otomatis

### Fase 4: Integrasi Lisensi & Testing
- [x] T-13 — Verifikasi kompatibilitas path lisensi `%LOCALAPPDATA%\BremspaceERP\config\license.key` pada `EnsureLicenseValid.php`
- [x] T-14 — Validasi automated workflow GitHub Actions menghasilkan installer `BremspaceERP-Setup-v1.0.0.exe` (51MB) sukses 100%

---

## Catatan Verifikasi
- T-01: Direktori `tools/windows/` dan subfoldernya terbuat dengan permission yang benar.
- T-02: `my.ini.template` memuat direktif anti-corruption (`innodb_flush_log_at_trx_commit = 1`, `sync_binlog = 1`) dan path placeholder `%DATA_DIR%`.
- T-03: `Caddyfile.desktop` memuat direktif port 1741, bind 127.0.0.1, file_server, dan php_server FrankPHP.
- T-04: `php.desktop.ini` memuat ekstensi PDO MySQL, curl, mbstring, gd, opcache, dan memory_limit 512M.
- T-05: `.env.desktop.template` memuat konfigurasi `DB_PORT=13307`, `DB_CONNECTION=mysql`, `APP_ENV=production`.
- T-06: Script download binaries executable dan terverifikasi URL sumber rilis resmi MariaDB & FrankPHP.
