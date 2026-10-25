# Ute Parts ERP & Marketplace

Sistem ERP & Omnichannel modern untuk bisnis sparepart & servis elektronik/gadget/motor: Point of Sale (POS) kasir offline-ready, manajemen work-order servis, marketplace konsumen, reseller & komisi multi-aktor, HR & payroll, akunting double-entry, inventori gudang (WMS), serta approval workflow berjenjang — terintegrasi penuh dalam satu platform modular.

---

## Daftar Isi
- [Teknologi & Arsitektur](#teknologi--arsitektur)
- [Modul & Fitur Utama](#modul--fitur-utama)
- [Dual PWA Multi-Scope](#dual-pwa-multi-scope-pwa-konsumen-vs-backoffice)
- [Kebutuhan Sistem](#kebutuhan-sistem)
- [Instalasi Dependensi Sistem Operasi](#instalasi-dependensi-sistem-operasi)
  - [Ubuntu / Debian / WSL2](#1-ubuntu--debian--wsl2)
  - [macOS](#2-macos-homebrew)
  - [Windows](#3-windows)
- [Instalasi Proyek (Langkah Demi Langkah)](#instalasi-proyek-langkah-demi-langkah)
- [Menjalankan Aplikasi di Lingkungan Dev](#menjalankan-aplikasi-di-lingkungan-dev)
- [Akun Bawaan Backoffice](#akun-bawaan-backoffice-default-credentials)
- [Perintah QA & Testing](#perintah-qa--testing)
- [Panduan Migrasi Server](#panduan-migrasi-server)
- [Lisensi](#lisensi)

---

## Teknologi & Arsitektur

- **Backend:** Laravel 13 (PHP 8.3+)
- **Frontend:** TALL Stack — Tailwind CSS v4, Alpine.js, Livewire v3, Vite v8
- **Database:** MySQL 8.0+ / MariaDB 10.6+ (Production), SQLite in-memory (Unit & Feature Tests)
- **Desain Sistem:** "Ute Prism" (Glassmorphism, tabular-nums, palette electric indigo `--up-primary` & radiant orange `--up-accent`)
- **PWA:** Vite Plugin PWA dengan Service Worker Workbox, Dual Multi-Scope Manifest, Screen Wake Lock API
- **Queue:** Database driver (asinkron untuk notifikasi, laporan, dan email)

---

## Modul & Fitur Utama

Aplikasi terdiri dari 15 modul domain terisolasi di `app/Modules/`:

| Modul | Direktori | Kemampuan Utama |
|---|---|---|
| **POS Kasir** | `app/Modules/Pos` | Kasir sentuh & scanner barcode kamera, hold/park transaksi, split payment (tunai, transfer, QRIS, piutang), sesi buka/tutup kas, printer thermal ESC/POS, dan toggle Screen Wake Lock. |
| **Servis** | `app/Modules/Servis` | Work order, tracking tiket servis konsumen via token/QR, state machine status pengerjaan, manajemen teknisi & pemakaian sparepart, approval token estimasi biaya. |
| **Gudang (WMS)** | `app/Modules/Wms` | Manajemen produk & varian SKU, mutasi stok antar cabang, cycle count/opname, penerimaan barang PO (GRN) dengan scanner barcode, tracking nomor seri (SN), ekspor pricelist per tier harga, serta **Bulk Import & Auto-Mapping Foto Produk** (ekstraksi nama file/folder/ZIP ke SKU, kompresi WebP otomatis, dual-engine GD/Imagick). |
| **Marketplace** | `app/Modules/Marketplace` | Katalog toko publik mobile-first, keranjang belanja, checkout Duitku payment gateway, kurir ongkir otomatis Biteship, tracking pesanan. |
| **Reseller & Komisi** | `app/Modules/Reseller` | Portal reseller, perhitungan komisi otomatis multi-aktor (reseller, sales internal, teknisi), riwayat pencairan saldo. |
| **Akunting** | `app/Modules/Akunting` | Chart of Accounts (COA), auto-jurnal transaksi kasir/servis/pembelian, buku besar, neraca, laba-rugi, depresiasi aset otomatis. |
| **CRM** | `app/Modules/Crm` | Data pelanggan, tier membership (Retail, Silver, Gold, Platinum), sales pipeline & lead tracking, riwayat service HP pelanggan. |
| **HR & Payroll** | `app/Modules/Hr` | Data karyawan, absensi shift berbasis cabang, KPI teknisi/staff, slip gaji payroll dengan potongan/tunjangan & approval bertingkat. |
| **Workflow** | `app/Modules/Workflow` | Engine persetujuan multi-level untuk PO, GRN selisih, cycle count, dan payroll; inbox approval terpusat. |
| **Platform** | `Dashboard`, `Rbac`, `Report` | Role & permission berbasis Spatie dengan filter cabang ketat (`cabang_id`), dashboard widget interaktif, drilldown report viewer ekspor data terkurasi. |
| **Integrasi** | `Notifikasi`, `Omnichannel`, `Webhook` | WhatsApp Gateway queue, sinkronisasi marketplace Shopee API, outbound webhook. |

---

## Dual PWA Multi-Scope (PWA Konsumen vs Backoffice)

Ute Parts mendukung arsitektur **Multi-Scope Progressive Web App**:
1. **PWA Toko Konsumen (`/manifest.json`)**:
   - Scope: `/` | Start URL: `/`
   - Ditujukan untuk pembeli dan pelanggan toko untuk mencari katalog sparepart dan tracking servis HP.
2. **PWA Backoffice ERP (`/manifest-backoffice.json`)**:
   - Scope: `/app/` | Start URL: `/app/dashboard` (atau `/app/pos`)
   - Dilengkapi icon badge "ERP", shortcut operasional (Kasir POS, Gudang WMS, Servis), serta utilitas **Screen Wake Lock API** agar layar kasir tidak mati/terkunci otomatis saat transaksi.
   - Staf dapat menginstal PWA Toko dan PWA ERP berdampingan di satu perangkat Android/iOS/Desktop.

---

## Kebutuhan Sistem

- **PHP:** Versi 8.3 atau 8.4
  - Ekstensi wajib: `bcmath`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `pdo_sqlite`, `tokenizer`, `xml`, `zip`.
  - Ekstensi pemrosesan gambar mutakhir (disarankan): `imagick` & paket sistem `imagemagick libheif-examples` (untuk decoding foto HEIC Apple iOS, AVIF, TIFF, BMP, PSD, SVG, dan RAW).
- **Database:** MySQL 8.0+, MariaDB 10.6+, atau PostgreSQL 15+ (SQLite didukung untuk testing).
- **Composer:** Versi 2.x
- **Node.js:** Versi 20.x LTS atau lebih baru, beserta **npm**.
- **Memory (RAM):** Minimal 1 GB (server/VPS dengan swap 2GB), disarankan 2–4 GB untuk lingkungan produksi optimal.

---

## Instalasi Dependensi Sistem Operasi

### 1. Ubuntu / Debian / WSL2

```bash
# Update paket sistem
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip ca-certificates lsb-release

# Tambahkan repository PHP Sury
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.4 dan ekstensi yang dibutuhkan
sudo apt install -y php8.4-cli php8.4-fpm php8.4-mysql php8.4-sqlite3 \
    php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-gd \
    php8.4-bcmath php8.4-intl php8.4-opcache php8.4-imagick \
    imagemagick libheif-examples supervisor logrotate

# Install MySQL Server
sudo apt install -y mysql-server

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js 20 LTS & npm
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 2. macOS (Homebrew)

```bash
brew update
brew install php@8.3 composer node@20 mysql
brew services start mysql
```

### 3. Windows
Gunakan **WSL2 (Windows Subsystem for Linux)** dengan distro **Ubuntu 24.04 LTS** (ikuti panduan Ubuntu di atas), atau gunakan environment stack **Laragon** dengan PHP 8.3, Node.js 20, dan MySQL 8.

---

## Instalasi Proyek (Langkah Demi Langkah)

### 1. Clone Repositori
```bash
git clone https://github.com/bremspace/ute-parts-erp.git
cd ute-parts-erp
```

### 2. Install Dependensi PHP
```bash
composer install
```

### 3. Salin Konfigurasi Environment
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Konfigurasi Database
Buka file `.env`, sesuaikan kredensial database Anda:
```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=uteparts_db
DB_USERNAME=root
DB_PASSWORD=rahasia_anda
```

Buat database di MySQL jika belum ada:
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS uteparts_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 5. Jalankan Migrasi & Database Seeder
Perintah ini akan membuat skema tabel, akun default seluruh role, hak akses Spatie, data cabang awal, dan Chart of Accounts (COA):
```bash
php artisan migrate --seed
```

### 6. Hubungkan Berkas Storage Publik
```bash
php artisan storage:link
```

### 7. Atur Hak Akses Direktori (Linux / macOS / VPS)
```bash
chmod -R 775 storage bootstrap/cache
# Jika di server production:
# sudo chown -R www-data:www-data storage bootstrap/cache
```

### 8. Install Dependensi Frontend & Kompilasi Asset
```bash
npm install
npm run build
```

---

## Menjalankan Aplikasi di Lingkungan Dev

Buka dua tab terminal terpisah:

**Terminal 1 — Server Laravel & Antrian:**
```bash
php artisan serve
```
*(Opsional untuk menjalankan pemrosesan antrian background job)*:
```bash
php artisan queue:listen --tries=3
```

**Terminal 2 — Vite Hot Module Reloading (HMR):**
```bash
npm run dev
```

Akses aplikasi melalui browser:
- **Toko Konsumen / Marketplace:** `http://localhost:8000/`
- **Backoffice ERP:** `http://localhost:8000/app/login`
- **Layar Kasir POS:** `http://localhost:8000/app/pos`

---

## Akun Bawaan Backoffice (Default Credentials)

Semua akun default menggunakan password: `Password123!`

Akses login: `http://localhost:8000/app/login`

| Role | Nama Akun | Email | Hak Akses Utama |
|---|---|---|---|
| `super-admin` | Super Admin UteParts | `superadmin@uteparts.id` | Akses penuh seluruh sistem, pengaturan cabang & RBAC |
| `owner` | Owner UteParts | `owner@uteparts.id` | Akses eksekutif laporan laba rugi, neraca, performa cabang |
| `admin-toko` | Admin Toko UteParts | `admintoko@uteparts.id` | Operasional cabang: POS, Gudang, Servis, CRM |
| `kasir` | Kasir UteParts | `kasir@uteparts.id` | Layar kasir POS, sesi kas harian, cek katalog sparepart |
| `teknisi` | Teknisi UteParts | `teknisi@uteparts.id` | Kanban board servis, diagnosa HP, pasang part & estimasi |
| `staff-gudang` | Staff Gudang UteParts | `gudang@uteparts.id` | Penerimaan barang PO (GRN), cycle count, transfer stok |
| `finance` | Finance UteParts | `finance@uteparts.id` | Akunting, COA, jurnal, persetujuan payroll & komisi |
| `marketing` | Marketing UteParts | `marketing@uteparts.id` | CRM lead pipeline, broadcast promo, membership tier |
| `kelola-hr` | HR Manager UteParts | `hr@uteparts.id` | Manajemen karyawan, jadwal shift, KPI, proses payroll |

> **Perhatian Keamanan:** Segera ganti password bawaan setelah deploy ke lingkungan produksi.

---

## Perintah QA & Testing

Test suite dijalankan menggunakan **PHPUnit 12** pada database SQLite in-memory:

```bash
# Menjalankan smoke test seluruh halaman
php artisan test --filter=SmokeSemuaHalamanTest

# Menjalankan pengujian fungsional POS & Stok
php artisan test --filter="PosBayarServisIntegrationTest|PosStokSinkronTest"

# Menjalankan pengujian nomor seri (SN)
php artisan test --filter=SerialNumberTest

# Pipeline QA otomatis (Pint, Larastan Level 6, Deptrac, Composer Audit)
./scripts/qa-auto.sh

# Laravel Pint Formatter
./vendor/bin/pint
```

---

## Opsi One-Click Installer (Instalasi Cepat)

Untuk mempercepat deployment di server atau komputer baru tanpa konfigurasi manual, tersedia metode One-Click Installer:

### 1. One-Click Launcher Ekstraksi ZIP (Windows / Linux / macOS)
Cukup ekstrak file ZIP proyek, lalu klik file launcher sesuai sistem operasi Anda:
- **Windows:** Klik 2x file `start-install.bat`
- **Linux / macOS:** Jalankan `./start-install.sh` (atau klik kanan -> *Run*)

*Skrip launcher otomatis mendeteksi PHP lokal (PATH, Laragon, XAMPP), memastikan dependensi vendor lengkap, memilih port lokal bebas (8000+), langsung meluncurkan web browser ke GUI installer `http://localhost:PORT/install.php`, dan menjalankan server.*

---

### 2. Standalone VPS / Server Ubuntu (Nginx + PHP 8.4 + Supervisor)
Jalankan satu baris perintah sebagai `root` di VPS / server lokal Ubuntu 22.04 / 24.04:
```bash
sudo bash install-standalone.sh yourdomain.com
# atau menggunakan IP Publik langsung:
# sudo bash install-standalone.sh 103.xxx.xxx.xxx
```
*Skrip otomatis mengonfigurasi swap 2GB, Nginx, PHP 8.4-FPM, MySQL, Supervisor queue worker, cron, build asset, dan SSL Let's Encrypt.*

---

### 3. VPS dengan CyberPanel / OpenLiteSpeed
Jika server menggunakan CyberPanel:
```bash
sudo bash install-ute.sh yourdomain.com
```

---

### 4. Web-based Graphical Installer (Via Browser)
Buka browser dan akses URL installer visual:
```
https://yourdomain.com/install.php
```
*GUI installer Ute Prism menyediakan 4 langkah interaktif ramah pengguna:*
1. **Diagnostik Kesehatan Server:** Deteksi otomatis OS, RAM, Swap, Disk, ekstensi PHP (termasuk Imagick/GD untuk gambar multi-format), izin folder, dan tools sistem.
2. **Koneksi Database MySQL:** Uji koneksi realtime dengan 1 tombol dan opsi pembuatan database otomatis.
3. **Bisnis & Git CI/CD:** Form kredensial `.env` tanpa koding (Duitku, Biteship, WA Gateway, serta webhook auto-deploy `POST /webhook/git-deploy`).
4. **AI Development Node:** Pilihan instalasi otomatis OpenCode CLI, service 9router di port 20128, codebase-memory-mcp, serta AGENTS.md & skills sebagai node maintenance mandiri di server baru.

---

## Otomasi Git CI/CD (Webhook Auto-Deploy)

Aplikasi telah dilengkapi endpoint webhook aman untuk auto-deploy saat Anda push ke branch `main`:
- **Endpoint:** `POST /webhook/git-deploy`
- **Keamanan:** Verifikasi tanda tangan GitHub HMAC-SHA256 (`X-Hub-Signature-256`) atau token `GIT_DEPLOY_SECRET`.
- **Aksi Otomatis:** Saat menerima webhook valid, server otomatis menjalankan `git pull`, `php artisan migrate --force`, dan `php artisan optimize`.

---

## Panduan Migrasi Server

Aplikasi ini 100% cloud-ready dan portable, siap dipindahkan ke **VPS baru yang lebih proper** maupun **Server Fisik Lokal (On-Premise) yang memiliki IP Publik**:

### 1. Migrasi ke VPS Baru (Ubuntu + Nginx + PHP-FPM 8.4 + SSL)
Gunakan panduan deploy produksi tanpa panel (standalone VPS dengan supervisord & OPcache):
📖 **[Panduan Lengkap Deploy Production ke VPS Baru](docs/DEPLOY.md)**

### 2. Migrasi ke Server Lokal (Kantor / Toko) dengan IP Publik atau Cloudflare Tunnel
Gunakan panduan pemindahan data lengkap dari backup database, transfer media, restore, hingga konfigurasi IP publik / tunneling:
📖 **[Panduan Lengkap Migrasi ke Server Lokal Hingga Online](docs/MIGRASI-VPS-KE-LOKAL.md)**

### Checklist Kesiapan Migrasi (Quick Checklist)
1. **Database Dump:** `mysqldump -u root -p uteparts_db --single-transaction --quick > backup.sql`
2. **Media Storage:** Arsipkan folder fisik `storage/app/public/` (`tar -czvf storage_media.tar.gz -C storage/app public`).
3. **Konfigurasi Target:** Salin `.env`, sesuaikan `APP_URL`, `DB_*`, dan kredensial Duitku/Biteship.
4. **Symlink Storage:** Jalankan `php artisan storage:link` di server baru.
5. **Worker & Scheduler:** Pasang daemon Supervisor untuk queue (`php artisan queue:work --tries=3`) dan cron scheduler (`* * * * * php /path-to-app/artisan schedule:run >> /dev/null 2>&1`).
6. **Optimasi Produksi:** `php artisan config:cache && php artisan route:cache && php artisan view:cache`

---

## Lisensi

Aplikasi ini dilisensikan di bawah lisensi [MIT](LICENSE).
