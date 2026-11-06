# Product Requirement Document (PRD)
## Native Windows Single-Click Desktop Installer (.exe), Embedded MariaDB Portable, FrankPHP Runtime, Edge WebView2 Shell, Auto-Updater & Offline Licensing

- **ID Dokumen**: `PRD-WINDOWS-EXE-BUNDLE-01`
- **Versi**: 1.0.0
- **Status**: APPROVED FOR IMPLEMENTATION
- **Tanggal**: 2026-11-06
- **Brand Produk**: **Bremspace ERP** (White-Label Production Distribution)
- **Target OS**: Windows 10 (Build 1809+), Windows 11 (64-bit Architecture)
- **Core Stack**: Laravel 13 (PHP 8.5+), FrankPHP (Windows x64), MariaDB Community Server Portable (x64), Microsoft Edge WebView2, Inno Setup 6.3+

---

## 1. Executive Summary & Problem Statement

### 1.1. Latar Belakang & Masalah
Sebelumnya, pengoperasian Bremspace ERP di lingkungan Windows mengandalkan Docker Engine melalui WSL2 (`PRD-INSTALLER-UNIVERSAL-06`). Meskipun efisien untuk developer dan server VPS, pada lingkungan operasional toko ritel/kasir POS Windows timbul hambatan operasional:
1. **Kompleksitas Instalasi**: Operator kasir atau pemilik toko non-teknis kesulitan mengaktifkan virtualisasi CPU di BIOS, memasang WSL2, atau mengonfigurasi Docker.
2. **Konsumsi Sumber Daya (Overhead)**: WSL2 + Docker Desktop mengalokasikan memori minimum 2GB–4GB RAM untuk Hyper-V VM, membebani PC kasir dengan RAM 4GB–8GB.
3. **Ketergantungan Eksternal**: Kebutuhan internet berkecepatan tinggi saat setup awal untuk pull image Docker sering terhambat oleh koneksi cabang yang tidak stabil.

### 1.2. Solusi Produk
Menghadirkan installer mandiri **`BremspaceERP-Setup-vX.Y.Z.exe`**:
- **Single-Click Installer**: Wizard standar Windows (Next -> Next -> Finish) tanpa syarat WSL, Docker, atau tools pengembang.
- **Embedded Portable MariaDB**: Engine database MySQL-compatible tanpa instalasi Windows Service (zero conflict dengan XAMPP/Laragon yang sudah ada).
- **FrankPHP Engine**: Runtime Caddy + PHP 8.5 terkompilasi single binary berkecepatan tinggi, konsumsi RAM < 80MB.
- **Edge WebView2 Shell (Native Look & Feel)**: Tampilan aplikasi tampil sebagai jendela desktop mandiri (borderless/kiosk app mode), bukan tab browser biasa.
- **Offline-First & Local DB**: Data tersimpan aman di disk lokal PC, transaksi POS offline instan, terhubung ke outbox sync engine untuk sinkronisasi cloud saat online.
- **Offline Licensing Re-use**: Mengintegrasikan sistem lisensi `HardwareIdentifier` dan `LicenseVerifier` yang telah teruji tanpa modifikasi algoritma core.

---

## 2. Arsitektur Sistem & Topologi Komponen

```
┌────────────────────────────────────────────────────────────────────────┐
│                      WINDOWS 10/11 OPERATING SYSTEM                    │
│                                                                        │
│   ┌────────────────────────────────────────────────────────────────┐   │
│   │               Bremspace Desktop App Shell                      │   │
│   │       (Microsoft Edge WebView2 --app=http://127.0.0.1:1741)    │   │
│   └───────────────────────────────┬────────────────────────────────┘   │
│                                   │ HTTP Request                       │
│                                   ▼                                    │
│   ┌────────────────────────────────────────────────────────────────┐   │
│   │     Bremspace Process Supervisor / Launcher (launcher.exe)     │   │
│   │   [Job Object Guard: Auto-kill process tree on window exit]    │   │
│   │                                                                │   │
│   │   ┌───────────────────────────┐    ┌───────────────────────┐   │   │
│   │   │  FrankPHP Server (x64)    │    │ MariaDB Portable(x64) │   │   │
│   │   │  Port: 127.0.0.1:1741     │    │ Port: 127.0.0.1:13307 │   │   │
│   │   │  PHP 8.5 + OPcache        │◄──►│ Custom my.ini         │   │   │
│   │   │  Laravel 13 Application   │    │ Data: %LOCALAPPDATA%  │   │   │
│   │   └───────────────────────────┘    └───────────────────────┘   │   │
│   └───────────────────────────────┬────────────────────────────────┘   │
│                                   │                                    │
│   ┌───────────────────────────────┴────────────────────────────────┐   │
│   │ Persistensi & Keamanan Data (%LOCALAPPDATA%\BremspaceERP)      │   │
│   │ ├── db_data/ (Tabel MariaDB InnoDB, ibdata, binlogs)           │   │
│   │ ├── storage/ (Logs, SQLite cache, sessions, uploads)          │   │
│   │ ├── config/ (license.key terenkripsi, local .env)              │   │
│   │ └── backups/ (Snapshot database otomatis harian)               │   │
│   └────────────────────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Matriks Celah Bug, Analisis Risiko & Solusi Pasti (Vulnerability & Mitigation)

Setiap implementasi desktop memiliki perangkap teknis spesifik OS Windows. Berikut adalah identifikasi celah kritis beserta solusi permanennya:

| No | Komponen | Celah Bug / Potensi Kegagalan | Akar Masalah (Root Cause) | Solusi Pasti (Hardened Fix) |
|---|---|---|---|---|
| **BUG-01** | **MariaDB Port** | Port collision: MariaDB gagal start karena port default 3306 sudah dipakai service XAMPP, MySQL lama, atau Laragon di PC kasir. | Konflik binding socket TCP `0.0.0.0:3306`. | **Dynamic Port Auto-Negotiation**: Default port dipindah ke `13307`. Saat startup, Launcher memverifikasi ketersediaan port via socket check. Jika 13307 terisi, launcher auto-pindah ke port bebas berikutnya (13308–13320) dan menulis nilai `DB_PORT` secara dinamis ke `.env.local` sebelum FrankPHP boot. |
| **BUG-02** | **Update Aplikasi** | Data hilang saat update: Installer versi baru menimpa seluruh folder aplikasi, menghapus database klien (`data/` ter-reset). | Folder database ditaruh di dalam direktori instalasi `C:\Program Files\BremspaceERP`. | **Strict Separation of Code vs State**: Direktori program murni read-only di `%ProgramFiles%\BremspaceERP` (atau `%LOCALAPPDATA%\Programs`). Seluruh data aktif database, license, dan storage disimpan terisolasi di `%LOCALAPPDATA%\BremspaceERP\db_data`. Inno Setup diinstruksikan `Flags: uninsneveruninstall` pada folder data tersebut. |
| **BUG-03** | **Power Failure / Crash** | Kerusakan tabel database (InnoDB Crash) saat PC kasir dimatikan mendadak, listrik padam, atau blue screen (BSOD). | MariaDB write buffer tertunda (dirty pages) belum di-flush ke disk. | 1. Set `innodb_flush_log_at_trx_commit = 1` dan `sync_binlog = 1` di `my.ini`.<br>2. Set `innodb_doublewrite = 1`.<br>3. Launcher mendaftarkan Windows Shutdown Hook (`WM_QUERYENDSESSION` / `WM_ENDSESSION`) untuk mengeksekusi `mysqladmin.exe shutdown` secara bersih dalam rentang timeout 5 detik. |
| **BUG-04** | **Process Lifecycle** | Zombie Process: Pengguna menutup jendela WebView2, namun `frankphp.exe` dan `mysqld.exe` tetap berjalan di background Task Manager, mengunci file dan port. | Proses child berdiri sendiri (orphan) saat parent UI dimatikan tanpa cleanup. | **Windows Job Objects with `JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE`**: Launcher mendaftarkan child process (`frankphp.exe`, `mysqld.exe`, `msedge.exe`) ke dalam satu Windows Job Object kernel. Jika process launcher mati (apapun alasannya, termasuk force-kill), kernel Windows otomatis membunuh seluruh child process seketika. |
| **BUG-05** | **UAC & Hak Akses** | Error `Permission Denied` pada folder `storage/logs` dan `bootstrap/cache` jika aplikasi dipasang di `C:\Program Files`. | Windows Vista+ melarang proses non-admin menulis ke folder `C:\Program Files`. | Setup installer mode default: **User-Level Installation** (direkomendasikan: `%LOCALAPPDATA%\Programs\BremspaceERP`) yang **tidak memerlukan hak Administrator**. Jika admin memilih install system-wide, folder storage di-*symlink* / diarahkan via env `APP_STORAGE_PATH=%LOCALAPPDATA%\BremspaceERP\storage`. |
| **BUG-06** | **Antivirus Alert** | False Positive: Windows Defender / SmartScreen memblokir installer atau mencurigai `launcher.exe` sebagai malware tak dikenal. | Executable tidak memiliki sertifikat digital (Code Signing) dan memicu heuristik unpacking. | 1. Executable dikompilasi dari source Go/Rust bersih tanpa UPX packer mencurigakan.<br>2. Penyertaan manifest Windows resmi (`requestedExecutionLevel level="asInvoker"`).<br>3. Otomatisasi script pendaftaran *Exclusion Path* Windows Defender via PowerShell installer untuk folder kerja BremspaceERP. |
| **BUG-07** | **WebView2 Availability** | Mesin Windows 10 lama belum memiliki Microsoft Edge WebView2 Runtime, menyebabkan launcher gagal menampilkan window. | Windows 10 versi sebelum 2004 tidak menyertakan WebView2 secara default. | Inno Setup mendeteksi key registry WebView2 (`HKLM\SOFTWARE\WOW6432Node\Microsoft\EdgeUpdate\Clients\{F3017226-2347-4E85-B68D-21075F5CD674}`). Jika belum ada, installer otomatis mengunduh & memasang `MicrosoftEdgeWebview2Setup.exe` (Evergreen Bootstrapper) secara silent sebelum instalasi selesai. |
| **BUG-08** | **Sleep / Resume Drop** | Database Connection Lost (Error `2006 MySQL server has gone away`) saat laptop/PC kasir dibangunkan dari mode Sleep/Hibernate. | Socket TCP Windows ter-reset saat suspend ACPI, namun koneksi PDO PHP dalam persistent pool tetap mengira socket masih hidup. | Di `config/database.php` MySQL driver: set PDO attribute `PDO::ATTR_PERSISTENT => false` khusus runtime desktop, dan tambahkan retry logic pada middleware database untuk auto-reconnect saat state jaringan internal kembali aktif. |
| **BUG-09** | **Multi-Instance Collision** | Kasir mengklik icon desktop berkali-kali, menjalankan 2 instance aplikasi secara bersamaan yang berebut port dan file database. | Tidak adanya pengecekan single-instance mutex. | Launcher menginisialisasi **Named Mutex Windows** (`Global\BremspaceERP_SingleInstance_Mutex`). Jika mutex sudah dipegang instance pertama, instance kedua cukup mengirim sinyal fokus ke window WebView2 yang sudah ada (`SetForegroundWindow`), lalu instance kedua exit seketika. |
| **BUG-10** | **File Locking on Auto-Update** | Auto-update gagal mengganti file aplikasi karena file `.exe` atau `.dll` sedang aktif digunakan (Error `Access is denied`). | File binary Windows terkunci secara eksklusif oleh proses yang sedang berjalan. | Menggunakan strategi **Staging Shadow Update**: File versi baru didownload ke folder `%LOCALAPPDATA%\BremspaceERP\pending_update\`. Sebuah helper uninstaller/updater kecil (`updater.exe`) dijalankan di luar proses utama, mematikan launcher, melakukan atomic rename/copy, lalu merestart launcher. |
| **BUG-11** | **Manipulasi Jam Sistem** | Fraud tanggal/waktu sistem (mengundurkan kalender Windows) untuk memanipulasi masa aktif lisensi offline atau manipulasi laporan transaksi POS. | Ketergantungan murni pada `time()` sistem lokal tanpa validasi monotonik. | **Monotonic Clock & High-Water Mark Validation**: Sistem mencatat `last_known_timestamp` terenkripsi di database dan registry setiap kali transaksi dibuat. Jika waktu sistem sekarang < `last_known_timestamp` (selisih > 30 menit), sistem mengunci aplikasi ke status `TIME_TAMPER_DETECTED` dan mewajibkan koneksi online untuk kalibrasi NTP. |

---

## 4. Rincian Arsitektur Bundling & Komponen

### 4.1. Direktori Distribusi (Build Output Structure)
```
BremspaceERP_Portable_Payload/
├── launcher.exe                # Go/Rust lightweight process manager & mutex
├── updater.exe                 # Background binary updater helper
├── app/                        # Production build Laravel (no .git, no tests, no devdeps)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── public/                 # Web root (Vite assets terkompilasi)
│   ├── resources/
│   ├── routes/
│   ├── vendor/                 # Composer optimized autoloader
│   ├── artisan
│   └── .env.desktop.template
├── engine/
│   ├── frankphp.exe            # FrankPHP Windows x64 binary
│   ├── Caddyfile.desktop       # Konfigurasi Caddy embedded
│   └── php.ini                 # Konfigurasi PHP (OPcache ON, memory_limit 512M)
├── db_engine/
│   ├── bin/
│   │   ├── mysqld.exe          # MariaDB Portable server binary
│   │   ├── mysqladmin.exe      # Shutdown & ping utility
│   │   └── mysql.exe           # CLI utility
│   ├── share/                  # Charsets & error messages
│   └── my.ini.template         # Template konfigurasi database
└── webview/
    └── MicrosoftEdgeWebview2Setup.exe # Fallback bootstrapper (jika offline)
```

### 4.2. Konfigurasi `my.ini` MariaDB Portable (Optimal untuk Desktop)
```ini
[mysqld]
bind-address = 127.0.0.1
port = 13307
socket = "mariadb_bremspace.sock"
pid-file = "mariadb_bremspace.pid"
basedir = "%ENGINE_DIR%"
datadir = "%DATA_DIR%"
tmpdir = "%TMP_DIR%"

# Storage Engine & Reliability (Anti-Corruption)
default_storage_engine = InnoDB
innodb_file_per_table = 1
innodb_flush_log_at_trx_commit = 1
sync_binlog = 1
innodb_doublewrite = 1
innodb_buffer_pool_size = 128M
innodb_log_file_size = 32M

# Resource Optimization untuk PC Kasir (RAM Hemat)
max_connections = 25
key_buffer_size = 16M
table_open_cache = 400
performance_schema = OFF
skip-character-set-client-handshake
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# Disable external networking security
skip-name-resolve
```

### 4.3. Konfigurasi `Caddyfile.desktop` (FrankPHP)
```caddy
{
    admin off
    auto_https off
    log {
        output discard
    }
}

:1741 {
    bind 127.0.0.1
    root * "%APP_DIR%/public"
    encode zstd gzip

    # Serve static assets directly
    file_server

    # Execute Laravel via FrankPHP worker
    php_server {
        resolve_root_symlink false
    }
}
```

---

## 5. Alur Hidup Aplikasi (Application Lifecycle & Supervision)

```
                     [ User Double-Clicks Desktop Icon ]
                                      │
                                      ▼
                      [ launcher.exe Execution ]
                                      │
               ┌──────────────────────┴──────────────────────┐
               │ 1. Check Named Mutex                        │
               │    Already Running? -> Focus Window & Exit  │
               └──────────────────────┬──────────────────────┘
                                      ▼
               ┌─────────────────────────────────────────────┐
               │ 2. Create Windows Job Object                │
               │    Set JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE   │
               └──────────────────────┬──────────────────────┘
                                      ▼
               ┌─────────────────────────────────────────────┐
               │ 3. Check & Initialize Data Dir              │
               │    If first run: execute mysql_install_db   │
               │    Check port 13307; negotiate if occupied  │
               └──────────────────────┬──────────────────────┘
                                      ▼
               ┌─────────────────────────────────────────────┐
               │ 4. Spawn mysqld.exe (MariaDB)               │
               │    Assign to Job Object; wait for socket ping│
               └──────────────────────┬──────────────────────┘
                                      ▼
               ┌─────────────────────────────────────────────┐
               │ 5. First-Run / Migration Check              │
               │    Execute `php artisan migrate --force`    │
               │    Verify offline license (license.key)     │
               └──────────────────────┬──────────────────────┘
                                      ▼
               ┌─────────────────────────────────────────────┐
               │ 6. Spawn frankphp.exe (Web Engine)          │
               │    Assign to Job Object; bind 127.0.0.1:1741│
               └──────────────────────┬──────────────────────┘
                                      ▼
               ┌─────────────────────────────────────────────┐
               │ 7. Launch MS Edge WebView2 Shell Window     │
               │    --app=http://127.0.0.1:1741              │
               │    --window-size=1280,800                   │
               └──────────────────────┬──────────────────────┘
                                      │
                        (User Operates POS / ERP)
                                      │
                                      ▼
                     [ User Closes Window / Shutdown ]
                                      │
               ┌─────────────────────────────────────────────┐
               │ 8. Clean Teardown Hook                      │
               │    - Graceful mysqladmin shutdown (max 4s)  │
               │    - Close Job Object (Kernel kills any     │
               │      remaining child processes instantly)   │
               └─────────────────────────────────────────────┘
```

---

## 6. Integrasi Sistem Lisensi Offline (`EnsureLicenseValid`)

Sistem lisensi yang telah berjalan (`scripts/license/HardwareIdentifier.php` dan `scripts/license/LicenseVerifier.php`) dipertahankan sepenuhnya tanpa memecah konsistensi logika:
1. **Hardware Fingerprint**: Di Windows, `HardwareIdentifier.php` memanggil PowerShell untuk mendapatkan `Win32_ComputerSystemProduct.UUID` yang telah dienkripsi dengan SHA-256 + Salt.
2. **Path Penempatan File Lisensi**:
   - Runtime desktop mendefinisikan environment variable `BREMSPACE_LICENSE_PATH` yang menunjuk ke `%LOCALAPPDATA%\BremspaceERP\config\license.key`.
   - `EnsureLicenseValid.php` membaca path kustom tersebut jika tersedia, dengan fallback ke `storage_path('license.key')`.
3. **Aktivasi Pertama**:
   - Jika `license.key` belum ada atau tidak valid, aplikasi secara otomatis menampilkan layar aktivasi WhatsApp Handshake (`PRD-INSTALLER-UNIVERSAL-06 §4`).
   - Kasir/pemilik toko mengirimkan Machine Challenge Code ke WhatsApp Developer, menerima Activation Key, dan memasukkannya langsung di layar desktop.

---

## 7. Mekanisme Auto-Updater Lintas Platform & Sinkronisasi Repo

### 7.1. Single Repository CI/CD Architecture
Repository tetap tunggal (`bremspace/ute-parts-erp`). Workflow GitHub Actions (`.github/workflows/release-windows.yml`):
1. **Trigger**: Saat release tag baru di-push (contoh: `v1.3.0`).
2. **Build Environment**: `windows-latest` runner.
3. **Langkah Kompilasi**:
   - Checkout repository.
   - Setup PHP 8.5, Composer install (`--no-dev --optimize-autoloader`).
   - Setup Node.js, `npm ci && npm run build`.
   - Download binary FrankPHP x64 dan MariaDB Portable zip resmi.
   - Kompilasi Inno Setup Compiler (`iscc build/windows/installer.iss`).
   - Generate SHA-256 checksums dan metadata rilis `latest-windows.json`.
   - Upload artefak installer (`BremspaceERP-Setup-v1.3.0.exe`) ke GitHub Releases.

### 7.2. Client Auto-Update Protocol
1. **Background Polling**: Setiap kali terhubung ke internet, aplikasi memanggil `GET https://releases.bremspace.id/api/v1/desktop/latest-windows.json` (maksimal 1x per 24 jam).
2. **Deteksi Versi**: Membandingkan versi terpasang (`config('app.version')`) dengan `latest_version`.
3. **Download di Latar Belakang**: File updater diunduh ke folder `%LOCALAPPDATA%\BremspaceERP\updates\`.
4. **Verifikasi Hash**: Launcher memvalidasi signature SHA-256 sebelum mengizinkan eksekusi.
5. **Penerapan Update**:
   - Notifikasi halus di header POS: *"Pembaruan sistem v1.3.0 siap dipasang. [Restart untuk Menerapkan]"*.
   - Saat diklik: Launcher menjalankan `updater.exe /SILENT`, menutup aplikasi utama, memperbarui file program, menjalankan migrasi database (`php artisan migrate --force`), dan me-restart aplikasi dalam hitungan detik.

---

## 8. Spesifikasi Installer Inno Setup (`installer.iss`)

- **Nama Aplikasi**: Bremspace ERP
- **Versi**: Sesuai Git Tag
- **Publisher**: Bremspace
- **Default Installation Path**: `{localappdata}\Programs\BremspaceERP` (Menghindari kebutuhan Administrator UAC).
- **Data Path**: `{localappdata}\BremspaceERP\data` (Permanen, tidak dihapus saat uninstall kecuali dicentang khusus oleh user).
- **Shortcuts**: Desktop Icon + Start Menu Group.
- **Prerequisites Action**:
  - Deteksi dan auto-install WebView2 Runtime jika belum ada.
  - Setup file `.env.production` lokal dengan `APP_KEY` unik yang di-generate saat instalasi pertama.

---

## 9. Rencana Eksekusi & Tahapan Implementasi (Implementation Roadmap)

| Fase | Target Deliverable | Estimasi | Checklist Kunci |
|---|---|---|---|
| **Fase 1** | **Packaging Script & Directory Scaffolding** | Hari 1 | - Struktur folder packaging `tools/windows/`<br>- Skrip download portable binaries (MariaDB x64, FrankPHP x64)<br>- Konfigurasi template `my.ini` dan `Caddyfile.desktop` |
| **Fase 2** | **Supervisor / Launcher Engine (Job Object & Port Logic)** | Hari 2 | - Kompilasi `launcher.exe` dengan Named Mutex & Job Object<br>- Dynamic Port Checker (13307 fallback)<br>- Clean shutdown hook (`mysqladmin shutdown`) |
| **Fase 3** | **Script Inno Setup (`installer.iss`) & WebView2 Shell** | Hari 3 | - Pembuatan skrip Inno Setup lengkap dengan deteksi WebView2<br>- Shortcut creation & uninstaller isolation data |
| **Fase 4** | **Testing Celah Bug & Integrasi Lisensi / DB** | Hari 4 | - Pengujian simulasi power off / kill process (anti zombie)<br>- Pengujian migrasi DB & pemisahan folder data<br>- Validasi aktivasi lisensi offline via `HardwareIdentifier` |
| **Fase 5** | **GitHub Actions CI/CD Pipeline & Auto-Updater** | Hari 5 | - Skrip CI/CD `.github/workflows/release-windows.yml`<br>- Endpoint checker versi & silent updater integration |

---

## 10. Acceptance Criteria (Definisi Selesai)
1. [ ] File installer `BremspaceERP-Setup.exe` dapat diinstal di PC Windows 10/11 bersih (tanpa Docker, tanpa XAMPP, tanpa PHP terpasang) hanya dengan klik Next sampai selesai.
2. [ ] Aplikasi terbuka sebagai window desktop mandiri via WebView2 dalam waktu startup < 4 detik.
3. [ ] Tidak ada konflik port meskipun PC target sudah menjalankan XAMPP/MySQL di port 3306.
4. [ ] Seluruh data transaksi, pelanggan, dan stok tersimpan di folder data terisolasi dan tidak terhapus saat aplikasi diperbarui ke versi baru.
5. [ ] Jika window aplikasi ditutup atau di-force end di Task Manager, tidak ada proses `mysqld.exe` atau `frankphp.exe` yang tersisa (Zero Zombie Process).
6. [ ] Lisensi offline bawaan membaca Hardware ID Windows secara akurat dan memvalidasi `license.key` lokal dengan sempurna.
7. [ ] Transaksi kasir POS dapat berjalan mulus 100% tanpa koneksi internet.
