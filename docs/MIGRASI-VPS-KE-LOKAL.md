# Panduan Lengkap Migrasi Ute Parts ERP: Dari VPS ke Server Lokal Hingga Online Kembali

Dokumen ini adalah panduan teknis langkah demi langkah untuk memindahkan seluruh sistem **Ute Parts ERP & Marketplace** dari server VPS (OpenLiteSpeed / Nginx / Apache / CyberPanel) ke komputer/server lokal (Desktop/Laptop Linux, macOS, atau Windows WSL2), kemudian mengonlinekannya kembali ke internet agar dapat diakses dari luar jaringan lokal (misal untuk kasir cabang, pelanggan, dan webhook payment gateway).

---

## Daftar Isi
1. [Arsitektur & Kebutuhan Lingkungan Lokal](#1-arsitektur--kebutuhan-lingkungan-lokal)
2. [Tahap 1: Backup & Dump Data dari VPS](#2-tahap-1-backup--dump-data-dari-vps)
3. [Tahap 2: Unduh / Transfer Data ke Mesin Lokal](#3-tahap-2-unduh--transfer-data-ke-mesin-lokal)
4. [Tahap 3: Instalasi Dependensi di Lingkungan Lokal](#4-tahap-3-instalasi-dependensi-di-lingkungan-lokal)
5. [Tahap 4: Restore Database & File Storage di Lokal](#5-tahap-4-restore-database--file-storage-di-lokal)
6. [Tahap 5: Konfigurasi File Lingkungan (.env) Lokal](#6-tahap-5-konfigurasi-file-lingkungan-env-lokal)
7. [Tahap 6: Build Asset Frontend & Cache Warmup](#7-tahap-6-build-asset-frontend--cache-warmup)
8. [Tahap 7: Uji Coba & Verifikasi Lokal](#8-tahap-7-uji-coba--verifikasi-lokal)
9. [Tahap 8: Mengonlinekan Server Lokal Kembali ke Internet](#9-tahap-8-mengonlinekan-server-lokal-kembali-ke-internet)
10. [Tahap 9: Konfigurasi Service Otomatis (Queue Worker & Cron)](#10-tahap-9-konfigurasi-service-otomatis-queue-worker--cron)
11. [Troubleshooting & Checklist Pasca Migrasi](#11-troubleshooting--checklist-pasca-migrasi)

---

## 1. Arsitektur & Kebutuhan Lingkungan Lokal

Ute Parts ERP dibangun dengan stack modern:
- **Framework:** Laravel 13 (PHP 8.3+)
- **Frontend:** TALL Stack (Tailwind CSS v4, Alpine.js, Livewire v3, Vite v8)
- **Database:** MySQL 8.0+ atau MariaDB 10.6+
- **PWA:** Dual PWA Manifest (Storefront `/` & Backoffice `/app/`)
- **Queue & Scheduler:** Driver database dengan queue worker otomatis

### Spesifikasi Rekomendasi Mesin Lokal
- **OS:** Ubuntu 22.04/24.04 LTS, Debian 12, macOS Sonoma/Sequoia, atau Windows 11 dengan WSL2 (Ubuntu).
- **RAM:** Minimal 4 GB (disarankan 8 GB+ untuk dev + database + queue worker).
- **Penyimpanan:** Minimal 10 GB ruang kosong SSD.
- **Koneksi Internet:** Diperlukan untuk instalasi paket dan tunneling online.

---

## 2. Tahap 1: Backup & Dump Data dari VPS

Lakukan login SSH ke VPS lama:

```bash
ssh user@ip-vps-anda
cd /var/www/test.uteparts.id-staging # Sesuaikan direktori proyek di VPS
```

### 1.1 Aktifkan Mode Maintenance (Opsional tapi Disarankan)
Mencegah adanya transaksi baru saat database di-dump:
```bash
php artisan down --secret="migrasi-ute-parts-2026"
```

### 1.2 Export Database MySQL (Dump)
Export seluruh tabel beserta struktur dan datanya:
```bash
# Ganti nama user_db dan nama_db sesuai konfigurasi .env VPS
mysqldump -u root -p uteparts_db --single-transaction --quick --routines --triggers > /tmp/uteparts_backup.sql

# Kompresi file SQL agar transfer lebih cepat dan hemat bandwidth
gzip -9 /tmp/uteparts_backup.sql
# Menghasilkan file: /tmp/uteparts_backup.sql.gz
```

### 1.3 Arsipkan Berkas Media & Uploads (Storage)
File foto produk, lampiran servis, slip bukti pembayaran berada di `storage/app/public`:
```bash
tar -czvf /tmp/uteparts_storage.tar.gz -C storage/app public
```

### 1.4 Simpan File `.env` Produksi VPS
Buat salinan konfigurasi `.env` untuk referensi kredensial Duitku, Biteship, WhatsApp API, dan APP_KEY:
```bash
cp .env /tmp/uteparts_env_vps.txt
```

---

## 3. Tahap 2: Unduh / Transfer Data ke Mesin Lokal

Buka terminal di **komputer lokal** Anda. Buat folder penampungan cadangan:

```bash
mkdir -p ~/migrasi-uteparts
cd ~/migrasi-uteparts

# Unduh arsip database, storage, dan env dari VPS via SCP
scp user@ip-vps-anda:/tmp/uteparts_backup.sql.gz .
scp user@ip-vps-anda:/tmp/uteparts_storage.tar.gz .
scp user@ip-vps-anda:/tmp/uteparts_env_vps.txt .

# Ekstrak file SQL
gunzip uteparts_backup.sql.gz
```

---

## 4. Tahap 3: Instalasi Dependensi di Lingkungan Lokal

### 4.1 Untuk Linux (Ubuntu / Debian / WSL2)

Jalankan perintah berikut untuk menginstal PHP 8.3+, ekstensi yang dibutuhkan, Composer, Node.js, dan MySQL:

```bash
sudo apt update && sudo apt upgrade -y

# 1. Tambahkan repository PHP Sury (jika belum ada)
sudo apt install -y software-properties-common curl ca-certificates gnupg lsb-release
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# 2. Install PHP 8.3 dan ekstensi lengkap
sudo apt install -y php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl \
    php8.3-sqlite3 php8.3-opcache

# 3. Install MySQL Server & Git
sudo apt install -y mysql-server git unzip

# 4. Install Composer (PHP Dependency Manager)
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# 5. Install Node.js 20 LTS & npm
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 4.2 Untuk macOS (menggunakan Homebrew)
```bash
brew update
brew install php@8.3 composer node@20 mysql
brew services start mysql
```

### 4.3 Verifikasi Instalasi Alat
```bash
php -v        # Pastikan >= 8.3
composer -V   # Pastikan versi 2.x
node -v       # Pastikan >= 20.x
npm -v        # Pastikan terinstal
mysql --version
```

---

## 5. Tahap 4: Restore Database & File Storage di Lokal

### 5.1 Clone Kode Repositori atau Salin dari VPS
Jika menggunakan Git:
```bash
cd ~
git clone https://github.com/bremspace/ute-parts-erp.git uteparts-lokal
cd uteparts-lokal
```

### 5.2 Buat Database di MySQL Lokal
Masuk ke terminal MySQL lokal:
```bash
sudo mysql
```
Jalankan query SQL berikut:
```sql
CREATE DATABASE uteparts_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'uteparts_user'@'localhost' IDENTIFIED BY 'PasswordLokal123!';
GRANT ALL PRIVILEGES ON uteparts_db.* TO 'uteparts_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 5.3 Import Database Dump
Import file SQL yang telah diunduh dari VPS:
```bash
mysql -u uteparts_user -p uteparts_db < ~/migrasi-uteparts/uteparts_backup.sql
```

### 5.4 Restore File Storage (Media & Uploads)
Ekstrak folder storage publik:
```bash
tar -xzvf ~/migrasi-uteparts/uteparts_storage.tar.gz -C storage/app/
php artisan storage:link
```

---

## 6. Tahap 5: Konfigurasi File Lingkungan (.env) Lokal

Salin konfigurasi default atau gunakan template:
```bash
cp .env.example .env
```
Buka `.env` menggunakan teks editor (misal `nano .env`) dan sesuaikan parameter berikut:

```dotenv
APP_NAME="Ute Parts ERP"
APP_ENV=production          # Gunakan production atau local
APP_KEY=base64:...          # WAJIB SAMA DENGAN NILAI DI VPS AGAR DATA TERENKRIPSI TIDAK RUSAK!
APP_DEBUG=false             # false jika diakses online publik
APP_URL=http://localhost:8000 # Nanti disesuaikan dengan domain/tunnel

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Konfigurasi Database Lokal
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=uteparts_db
DB_USERNAME=uteparts_user
DB_PASSWORD=PasswordLokal123!

# Antrian (Queue)
QUEUE_CONNECTION=database

# Session & Cache
SESSION_DRIVER=database
CACHE_STORE=database

# Salin kredensial Duitku / Biteship / WA Gateway dari uteparts_env_vps.txt
DUITKU_MERCHANT_CODE=...
DUITKU_API_KEY=...
DUITKU_CALLBACK_URL="${APP_URL}/webhook/duitku"

BITESHIP_API_KEY=...
```

> **PERINGATAN PENTING (`APP_KEY`):** Nilai `APP_KEY` **WAJIB** disalin persis sama dengan file `.env` di VPS. Jika Anda membuat kunci baru (`key:generate`), maka data terenkripsi (seperti kredensial integrasi atau token tersimpan) tidak dapat dibaca kembali.

---

## 7. Tahap 6: Build Asset Frontend & Cache Warmup

Jalankan instalasi dependensi Composer dan Node.js:

```bash
# 1. Install dependensi PHP
composer install --no-dev --optimize-autoloader

# 2. Install dependensi JavaScript / Tailwind / Vite
npm ci || npm install

# 3. Kompilasi asset produksi & Service Worker PWA
npm run build

# 4. Berikan izin tulis direktori cache dan logs
chmod -R 775 storage bootstrap/cache

# 5. Optimasi cache Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 8. Tahap 7: Uji Coba & Verifikasi Lokal

Jalankan web server lokal bawaan PHP untuk pengujian:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Buka browser di komputer Anda:
- **Toko Konsumen / Marketplace:** `http://localhost:8000/`
- **Backoffice ERP:** `http://localhost:8000/app/login`

Login dengan akun yang sudah ada atau akun bawaan:
- Email: `superadmin@uteparts.id`
- Password: `Password123!` (atau password yang telah diubah di VPS)

Pastikan:
1. Data produk, pelanggan, riwayat transaksi, dan jurnal muncul persis seperti di VPS.
2. Foto-foto produk di katalog muncul dengan benar (storage link aktif).
3. Halaman kasir POS dapat dibuka tanpa error.

Tekan `Ctrl + C` pada terminal untuk menghentikan server sementara.

---

## 9. Tahap 8: Mengonlinekan Server Lokal Kembali ke Internet

Terdapat 3 metode untuk membuat server lokal Anda dapat diakses secara online dari mana saja dengan aman:

### METODE 1: Menggunakan Cloudflare Tunnel (Sangat Direkomendasikan ⭐)
**Keunggulan:**
- **Gratis**, aman, tidak perlu IP publik statis, tidak perlu setting port forwarding di router.
- Mendapatkan SSL otomatis (HTTPS).
- Alamat domain kustom Anda (misal: `erp.tokouteparts.com` atau `app.uteparts.id`) dapat diarahkan langsung ke server lokal.

#### Langkah Instalasi Cloudflare Tunnel:
1. Unduh `cloudflared` di mesin lokal:
   ```bash
   # Di Ubuntu/Debian:
   curl -L --output cloudflared.deb https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb
   sudo dpkg -i cloudflared.deb
   ```
2. Login ke akun Cloudflare:
   ```bash
   cloudflared tunnel login
   ```
3. Buat Tunnel:
   ```bash
   cloudflared tunnel create uteparts-local
   ```
4. Konfigurasikan file routing (`~/.cloudflared/config.yml`):
   ```yaml
   tunnel: <TUNNEL_ID_DARI_LANGKAH_3>
   credentials-file: /home/user/.cloudflared/<TUNNEL_ID>.json

   ingress:
     - hostname: app.uteparts.id # Domain yang Anda arahkan di Cloudflare
       service: http://localhost:8000
     - service: http_status:404
   ```
5. Arahkan DNS di Cloudflare:
   ```bash
   cloudflared tunnel route dns uteparts-local app.uteparts.id
   ```
6. Jalankan Cloudflare Tunnel sebagai Service background otomatis:
   ```bash
   sudo cloudflared service install
   sudo systemctl start cloudflared
   sudo systemctl enable cloudflared
   ```
7. Perbarui `APP_URL` di `.env` lokal:
   ```dotenv
   APP_URL=https://app.uteparts.id
   ```
   Lalu refresh cache:
   ```bash
   php artisan config:cache
   ```

---

### METODE 2: Menggunakan Ngrok (Solusi Instan Cepat)
Cocok untuk demonstrasi atau pengujian sementara:

```bash
# Install ngrok
curl -s https://ngrok-agent.s3.amazonaws.com/ngrok.asc | sudo tee /etc/apt/trusted.gpg.d/ngrok.asc >/dev/null
echo "deb https://ngrok-agent.s3.amazonaws.com buster main" | sudo tee /etc/apt/sources.list.d/ngrok.list
sudo apt update && sudo apt install ngrok

# Hubungkan auth token akun ngrok Anda
ngrok config add-authtoken <TOKEN_ANDA>

# Buka akses online ke port 8000
ngrok http 8000
```
Ngrok akan memberikan URL publik HTTPS seperti: `https://xxxx-xx-xx.ngrok-free.app`.
Perbarui `APP_URL` di `.env` lokal sesuai URL tersebut.

---

### METODE 3: Port Forwarding Router + DDNS (Untuk IP Publik Rumah/Kantor)
1. Atur IP komputer lokal menjadi IP statis di jaringan lokal (misal: `192.168.1.100`).
2. Masuk ke halaman admin router/modem (biasanya `192.168.1.1`).
3. Buka menu **Port Forwarding / Virtual Server**:
   - External Port: `80` dan `443`
   - Internal Port: `80` dan `443`
   - Internal IP: `192.168.1.100`
4. Setup Nginx/Apache lokal dengan Let's Encrypt (Certbot) dan pasang Dynamic DNS (No-IP / DuckDNS / Cloudflare DDNS).

---

## 10. Tahap 9: Konfigurasi Service Otomatis (Queue Worker & Cron)

Agar notifikasi WhatsApp/Email, pemrosesan transaksi antrian, dan sinkronisasi stok omnichannel berjalan tanpa henti di server lokal:

### 10.1 Konfigurasi Queue Worker (Supervisor)
Install Supervisor:
```bash
sudo apt install -y supervisor
```

Buat file konfigurasi worker `/etc/supervisor/conf.d/uteparts-worker.conf`:
```ini
[program:uteparts-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /home/user/uteparts-lokal/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=user
numprocs=2
redirect_stderr=true
stdout_logfile=/home/user/uteparts-lokal/storage/logs/worker.log
stopwaitsecs=3600
```
Aktifkan konfigurasi:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start uteparts-worker:*
```

### 10.2 Konfigurasi Laravel Scheduler (Cron Job)
Buka crontab:
```bash
crontab -e
```
Tambahkan baris berikut di paling bawah:
```cron
* * * * * cd /home/user/uteparts-lokal && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

---

## 11. Troubleshooting & Checklist Pasca Migrasi

| Item Pemeriksaan | Perintah / Solusi |
|------------------|-------------------|
| **Koneksi Database Gagal** | Pastikan service MySQL berjalan (`sudo systemctl status mysql`) dan username/password di `.env` sesuai. |
| **Gambar Produk 404** | Jalankan `php artisan storage:link` dan pastikan folder `storage/app/public` memiliki isi file dari VPS. |
| **Error Permission (500)** | Jalankan `sudo chown -R $USER:www-data storage bootstrap/cache` lalu `chmod -R 775 storage bootstrap/cache`. |
| **Webhook Pembayaran Duitku Gagal** | Pastikan URL callback di dashboard Duitku diubah sesuai domain tunnel HTTPS baru (`https://domain-anda/webhook/duitku`). |
| **PWA Kasir Offline / Cache Lama** | Bersihkan Application Cache di browser atau unregister Service Worker lama di DevTools (`F12 -> Application -> Service Workers -> Unregister`), lalu reload. |

Selamat! Sistem Ute Parts ERP kini telah berpindah sepenuhnya ke server lokal dan beroperasi online secara mandiri.
