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
| **Gudang (WMS)** | `app/Modules/Wms` | Manajemen produk & varian SKU, mutasi stok antar cabang, cycle count/opname, penerimaan barang PO (GRN) dengan scanner barcode, tracking nomor seri (SN), dan ekspor pricelist per tier harga. |
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
- **Database:** MySQL 8.0+, MariaDB 10.6+, atau PostgreSQL 15+ (SQLite didukung untuk testing).
- **Composer:** Versi 2.x
- **Node.js:** Versi 20.x LTS atau lebih baru, beserta **npm**.
- **Memory (RAM):** Minimal 1 GB (server/VPS), disarankan 2–4 GB untuk lingkungan pengembangan.

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

# Install PHP 8.3 dan ekstensi yang dibutuhkan
sudo apt install -y php8.3-cli php8.3-fpm php8.3-mysql php8.3-sqlite3 \
    php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd \
    php8.3-bcmath php8.3-intl php8.3-opcache

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

## Panduan Migrasi Server

Ingin memindahkan sistem dari server VPS kembali ke komputer/server lokal (kantor/toko) lalu meng-onlinekannya kembali?
Panduan teknis lengkap dari tahap backup database, transfer file media, restore di lokal, hingga tunneling online via Cloudflare Tunnel / Ngrok tersedia di:

📖 **[Panduan Lengkap Migrasi VPS ke Lokal Hingga Online](docs/MIGRASI-VPS-KE-LOKAL.md)**

---

## Lisensi

Aplikasi ini dilisensikan di bawah lisensi [MIT](LICENSE).
