# Deploy Production — Ute Parts ERP (VPS Mandiri: Ubuntu + Nginx + PHP-FPM)

Panduan deploy end-to-end untuk **VPS yang dikelola sendiri, tanpa panel** (tanpa CyberPanel/cPanel).
Sesuai PRD §7 (constraint RAM 1GB) & §6 (keamanan).

> **Kamu sedang di server ini?** Yang perlu diketahui: server ini adalah **staging**, bukan production.
> Production = VPS baru yang kamu setup sendiri lewat panduan ini.

Urut eksekusi — **jangan loncat**. Kalau suatu langkah error, berhenti dan cek [Troubleshooting](#12-troubleshooting).

---

## Daftar Isi

| # | Bagian |
|---|--------|
| 0 | [Prasyarat Server](#0-prasyarat-server) |
| 1 | [Install Paket](#1-install-paket) |
| 2 | [Setup Database](#2-setup-database) |
| 3 | [Clone Project & Permission](#3-clone-project--permission) |
| 4 | [Konfigurasi `.env`](#4-konfigurasi-env-production) |
| 5 | [Migration & User Admin](#5-migration--user-admin) |
| 6 | [Config Nginx](#6-config-nginx) |
| 7 | [SSL Let's Encrypt](#7-ssl-lets-encrypt) |
| 8 | [Queue Worker (Supervisor)](#8-queue-worker-supervisor) |
| 9 | [Cron Scheduler](#9-cron-scheduler) |
| 10 | [Auto-Deploy GitHub Actions](#10-auto-deploy-github-actions) |
| 11 | [Smoke Test](#11-smoke-test-setelah-go-live) |
| 12 | [Troubleshooting](#12-troubleshooting) |
| 13 | [Optimasi RAM 1GB](#13-optimasi-ram-1gb) |
| 14 | [Backup Harian](#14-backup-harian-wajib) |

---

## 0. Prasyarat Server

| Requirement | Nilai | Cara cek |
|---|---|---|
| OS | Ubuntu 22.04 / 24.04 LTS | `cat /etc/os-release` |
| RAM | ≥ 1GB + **swap 2GB** | `free -h` |
| PHP | **8.4** (FPM + CLI) | `php -v` |
| Ekstensi PHP | `pdo_mysql gd zip fileinfo mbstring openssl intl sqlite3 bcmath` | `php -m` |
| MySQL/MariaDB | 10.6+ | `mysql --version` |
| Node | 20+ (build asset frontend) | `node -v` |
| Git | upload source | `git --version` |

> **Kenapa PHP 8.4, bukan 8.5?** `composer.json` mensyaratkan `^8.3`, jadi 8.5 boleh. Tapi 8.4 adalah
> yang paling proven dan paling hemat resource — aman untuk production. Naik ke 8.5 nanti kalau sudah mantap.

> **Swap itu wajib, bukan opsional.** Tanpa swap, `composer install` dan `npm run build` akan
> kena OOM kill di RAM 1GB. Ini penyebab paling umum deploy gagal.

---

## 1. Install Paket

Sekali saja, di VPS baru:

```bash
sudo apt update && sudo apt upgrade -y

sudo apt install -y \
    nginx mysql-server \
    php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath \
    git unzip curl certbot python3-certbot-nginx \
    supervisor logrotate

# Composer
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# Node 20+ (butuh untuk build CSS/JS)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Swap 2GB — WAJIB untuk RAM 1GB
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fetc
```

Verifikasi:
```bash
php -v && node -v && composer -V && swapon --show
```

---

## 2. Setup Database

```bash
sudo mysql
```

```sql
CREATE DATABASE ute_parts_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'uteparts'@'localhost' IDENTIFIED BY 'PASSWORD_YANG_KUAT';
GRANT ALL PRIVILEGES ON ute_parts_prod.* TO 'uteparts'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> **Jangan pakai user `root` MySQL untuk aplikasi.** Buat user khusus seperti di atas.
> User `root` sering tidak bisa diakses dari aplikasi web (setting socket/host terpisah).

---

## 3. Clone Project & Permission

```bash
sudo mkdir -p /var/www/ute-parts
sudo chown -R $USER:$USER /var/www/ute-parts
cd /var/www/ute-parts

git clone https://github.com/bremspace/ute-parts-erp.git .

composer install --no-dev --optimize-autoloader
npm ci && npm run build          # WAJIB — tanpa ini halaman tanpa CSS
php artisan storage:link         # symlink upload foto produk/unit

# Permission: PHP-FPM jalan sebagai www-data
sudo chown -R www-data:www-data storage bootstrap/cache public/build
sudo chmod -R 775 storage bootstrap/cache
```

> **`npm run build` itu wajib.** Layout memakai `@vite([...])` yang butuh file hashed di
> `public/build`. Kalau dilewatkan, halaman terbuka tapi **tapi tanpa CSS sama sekali**.

### Struktur folder

```
/var/www/ute-parts              ← folder project
/var/www/ute-parts/public       ← DOCUMENT ROOT Nginx (HANYA folder ini terekspos web)
```

---

## 4. Konfigurasi `.env` Production

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

```dotenv
APP_NAME="Ute Parts"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://uteparts.id
APP_LOCALE=id
APP_FALLBACK_LOCALE=en

LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ute_parts_prod
DB_USERNAME=uteparts
DB_PASSWORD=PASSWORD_YANG_KUAT

BROADCAST_CONNECTION=log
QUEUE_CONNECTION=database    # RAM kecil — jangan Redis dulu
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true   # WAJIB utk HTTPS, kalau false cookie login ditolak browser
SESSION_DOMAIN=.uteparts.id
SANCTUM_STATEFUL_DOMAINS=uteparts.id

BCRYPT_ROUNDS=10             # hemat CPU di RAM 1GB

# ─── Integrasi Eksternal ───────────────────────────────────────────
# WAJIB: flip ke false + key ASLI sebelum go-live menerima pembayaran nyata.
# Kalau masih true, semua transaksi production dialihkan ke Duitku sandbox
# (uang fiktif, webhook tidak diteruskan ke sistem live).
DUITKU_SANDBOX=false
DUITKU_MERCHANT_CODE=<kode merchant asli>
DUITKU_API_KEY=<api key asli>
DUITKU_MERCHANT_KEY=<key asli>
BITESHIP_API_KEY=<api key biteship asli>
```

> **Jangan tinggalkan `DB_CONNECTION=sqlite`** — itu default `.env.example` untuk development.

---

## 5. Migration & User Admin

```bash
cd /var/www/ute-parts

# 5a. Cek koneksi DB DULU (dengan kredensial dari .env):
php -r "new PDO('mysql:host=127.0.0.1;port=3306;dbname=ute_parts_prod','uteparts','PASSWORD'); echo 'DB OK\n';"
# error? cek user/password/host — lihat Troubleshooting §12

# 5b. Migration — INI YANG AMAN, selalu boleh jalan
php artisan migrate --force

# 5c. Seed HANYA permission/role (WAJIB di go-live pertama).
#     Tabel permission kosong = semua route RBAC akan 403.
php artisan db:seed --class=RolesAndPermissionsSeeder --force
```

> **JANGAN pernah `php artisan db:seed --force` (tanpa `--class`) di production.**
>
> Perintah itu menjalankan `DatabaseSeeder` → `IntegrationSeeder`, yang mengisi **data
> demo/fiktif**: produk, pelanggan, transaksi POS, tiket servis, PO, payroll, dan
> **9 user demo** (`super-admin@uteparts.test` s/d `kelola-hr@uteparts.test`,
> password `password123`). Database asli akan tercemar data palsu yang tampak realistis.
>
> Seeder demo hanya untuk development & review di server staging.

### Buat user admin pertama

Setelah permission di-seed, buat satu user super-admin:

```bash
php artisan tinker
```

```php
$u = App\Models\User::create([
    'name'      => 'Super Admin',
    'email'     => 'admin@uteparts.id',
    'password'  => bcrypt('PASSWORD_YANG_KUAT'),
    'is_active' => true,
]);
$u->assignRole('super-admin');
```

Bila sempat terlanjur ter-seed user demo, bersihkan:

```php
App\Models\User::where('email', 'like', '%@uteparts.test')->delete();
```

---

## 6. Config Nginx

```bash
sudo nano /etc/nginx/sites-available/uteparts
```

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name uteparts.id www.uteparts.id;

    root /var/www/ute-parts/public;
    index index.php;
    charset utf-8;

    # WAJIB: upload foto unit servis & import produk bisa berukuran besar
    client_max_body_size 64m;
    server_tokens off;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param HTTPS on;
        fastcgi_read_timeout 300;   # QA tools & import bisa lama
    }

    # Proteksi file sensitif
    location ~ /\.(?!well-known).* { deny all; }
    location ~* \.(env|log|sql|md)$ { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/uteparts /etc/nginx/sites-enabled/
sudo nginx -t                      # harus "syntax is ok / test is successful"
sudo systemctl reload nginx
```

> **Penting:** document root WAJIB `<project>/public`, **bukan** folder project.
> Kalau docroot = folder project, file `.env` bisa diakses langsung via browser — kebocoran
> password database & API key.

---

## 7. SSL Let's Encrypt

```bash
sudo certbot --nginx -d uteparts.id -d www.uteparts.id
```

Certbot otomatis meng-edit config Nginx + renewal via cron.

Test renewal:
```bash
sudo certbot renew --dry-run
```

---

## 8. Queue Worker (Supervisor)

**Wajib.** Tanpa ini, transaksi POS tetap jalan tapi notifikasi & outbound webhook tidak terkirim.

```bash
sudo nano /etc/supervisor/conf.d/ute-parts-queue.conf
```

```ini
[program:ute-parts-queue]
command=/usr/bin/php8.4 /var/www/ute-parts/artisan queue:work --sleep=3 --tries=3
directory=/var/www/ute-parts
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/ute-parts/storage/logs/worker.log
stopwaitsecs=3600
killasgroup=true
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status ute-parts-queue      # harus RUNNING
```

Atau pakai file yang sudah disediakan repo:

```bash
cp deploy/supervisor/ute-parts-queue.conf /etc/supervisor/conf.d/

# PENTING: bila lokasi project-mu BEDA dari /var/www/ute-parts, sesuaikan DULU:
sed -i 's#/var/www/ute-parts#/path/kamu#g; s#/usr/bin/php8.4#/usr/bin/php#g' \
  /etc/supervisor/conf.d/ute-parts-queue.conf

sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status ute-parts-queue      # harus RUNNING
```

---

## 9. Cron Scheduler

```bash
sudo crontab -e
```

```cron
* * * * * cd /var/www/ute-parts && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1
```

Dipakai untuk job terjadwal (recalc tier harian, dll). Cek daftar jadwal:
```bash
cd /var/www/ute-parts && php artisan schedule:list
```

---

## 10. Auto-Deploy (GitHub Actions)

Setiap push ke `main` → VPS otomatis pull, update, restart queue.
`deploy.sh` melakukan **auto-rollback** ke versi sebelumnya bila ada langkah gagal — site tidak pernah mati lama.

### 10.1 Fitur
- Trigger: push ke `main` + tombol manual di tab Actions
- Urutan: pull → composer → migrate → npm build → cache → chown → restart queue
- **Rollback otomatis**: bila composer/migrate/build/cache gagal → `git reset --hard` ke commit lama + restore build lama + cache clear
- Log di `/tmp/ute-deploy.log` + status di tab Actions
- `deploy.sh` **mendeteksi user web otomatis** (`www-data` untuk VPS Nginx/Apache, `lsadm` untuk CyberPanel) — jadi script yang sama jalan di keduanya

### 10.2 Setup SSH key (sekali, di VPS)
```bash
mkdir -p ~/.ssh && chmod 700 ~/.ssh
ssh-keygen -t ed25519 -f ~/.ssh/ute_deploy -N "" -C "github-actions"
cat ~/.ssh/ute_deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys

# Tampilkan private key (salin ke GitHub secret)
cat ~/.ssh/ute_deploy
```

### 10.3 GitHub Secrets (Settings → Secrets and variables → Actions)

| Secret | Isi | Wajib? |
|--------|-----|--------|
| `VPS_HOST` | IP/domain VPS | ✅ |
| `VPS_USER` | user SSH (mis. `root` atau `ubuntu`) | ✅ |
| `VPS_SSH_PRIVATE_KEY` | isi private key dari `cat ~/.ssh/ute_deploy` | ✅ |
| `VPS_PORT` | port SSH, default 22 | opsional |
| `VPS_PATH` | path project, mis. `/var/www/ute-parts` | ✅ |
| `PHP_BIN` | path PHP CLI, mis. `/usr/bin/php8.4` | ✅ |
| `QUEUE_NAME` | nama program supervisor, default `ute-parts-queue` | opsional |

> **`VPS_PATH` dan `PHP_BIN` WAJIB diisi.** Nilai default di workflow masih menunjuk path
> CyberPanel (`/home/ute/ute-parts`, `lsphp85`) — kalau tidak di-override, deploy ke VPS
> biasa akan gagal dengan "folder deploy tidak ditemukan".

### 10.4 Cara kerja
1. Workflow mengunduh `deploy.sh` dari repo ke `/tmp/ute-deploy.sh`, lalu eksekusi di VPS via SSH
2. Script menyimpan hash commit lama + **backup `public/build`** sebelum bekerja
3. Bila gagal → fungsi `rollback()` kembalikan semuanya ke commit lama
4. `.env` **tidak pernah tersentuh** (ada di `.gitignore`, tidak pulled dari GitHub)

> **Keamanan**: private key hanya di GitHub Secrets, tidak pernah masuk repo.

### 10.5 Deploy manual (tanpa GitHub Actions)
```bash
cd /var/www/ute-parts
DEPLOY_PATH=/var/www/ute-parts PHP_BIN=/usr/bin/php8.4 bash deploy.sh
```

### 10.6 Update manual paling sederhana
```bash
cd /var/www/ute-parts
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
sudo supervisorctl restart ute-parts-queue
```

---

## 11. Smoke Test Setelah Go-Live

```bash
BASE=https://uteparts.id

# 1. Health route
curl -s $BASE/up -o /dev/null -w "health=%{http_code}\n"           # harus 200

# 2. Halaman tanpa login
curl -s $BASE/shop -o /dev/null -w "shop=%{http_code}\n"           # harus 200
curl -s $BASE/app/login -o /dev/null -w "login-page=%{http_code}\n" # harus 200

# 3. Asset benar-benar ada? (kalau 0 = belum npm run build)
curl -s $BASE/app/login -H "Accept: text/html" | grep -c "/build/assets"   # harus > 0

# 4. Login post (harus 302; 419 = CSRF/cookie HTTPS bermasalah)
curl -s -c c.txt -b c.txt -X POST $BASE/app/login \
  -d "email=admin@uteparts.id&password=PASSWORD" \
  -o /dev/null -w "login-post=%{http_code}\n" -L

# 5. Queue
sudo supervisorctl status ute-parts-queue     # harus RUNNING

# 6. Round-trip data
cd /var/www/ute-parts
php artisan tinker --execute="echo App\\Modules\\Rbac\\Models\\Cabang::count().' cabang';"
```

---

## 12. Troubleshooting

| Gejala | Penyebab | Solusi |
|---|---|---|
| `Access denied for user 'uteparts'@'localhost'` | kredensial di `.env` tidak match | cek `cat .env \| grep DB_`; `php artisan config:clear`; test PDO §5a |
| Halaman tanpa CSS | `public/build` tidak ada / belum `npm run build` | `npm ci && npm run build`; `sudo chown -R www-data:www-data public/build` |
| Login post → 419 | cookie HTTPS mati / `SESSION_DOMAIN` salah | `SESSION_SECURE_COOKIE=true`; `SESSION_DOMAIN=.uteparts.id`; `php artisan optimize:clear` |
| 500 semua halaman | permission storage / cache stale | `sudo chown -R www-data:www-data storage bootstrap/cache`; `php artisan optimize:clear` |
| 404 asset `build/xxx` | docroot Nginx tidak ke `public` | pastikan `root /var/www/ute-parts/public;` |
| 502 Bad Gateway | PHP-FPM mati | `sudo systemctl status php8.4-fpm && sudo systemctl restart php8.4-fpm` |
| 403 di semua route | tabel permission kosong | `php artisan db:seed --class=RolesAndPermissionsSeeder --force` |
| Upload produk/foto gagal | body size Nginx kurang | `client_max_body_size 64m;` → `sudo nginx -t && sudo systemctl reload nginx` |
| Notifikasi/webhook tidak jalan | queue worker mati | `sudo supervisorctl status ute-parts-queue`; `journalctl -u supervisor` |
| Session logout terus | `SESSION_SECURE_COOKIE=true` tapi belum HTTPS, atau domain tidak sama | samakan `APP_URL` & `SESSION_DOMAIN` |
| `Migrate: table already exists` | seeder/upgrade sebelumnya | pastikan `.env` benar; jangan drop tabel tak sengaja |
| `SQLSTATE[HY000] [2002]` timeout | MySQL tidak jalan | `sudo systemctl status mysql && sudo systemctl start mysql` |
| `SQLSTATE[HY000] [2000]` mysqlnd old auth | MariaDB lama, password hash lawas | `ALTER USER 'uteparts'@'localhost' IDENTIFIED WITH mysql_native_password BY '...';` |
| Composer/npm OOM atau "Killed" | swap belum aktif | `swapon --show`; kalau kosong: `sudo swapon /swapfile` |
| Artisan/queue `PHP version ... does not satisfy` | CLI PHP beda dengan FPM | pastikan `PHP_BIN=/usr/bin/php8.4` konsisten di semua tempat |
| Export Excel error | ekstensi `gd`/`zip` belum ada | `sudo apt install php8.4-gd php8.4-zip && sudo systemctl restart php8.4-fpm` |
| Slow / timeout | OPcache off / MySQL buffer terlalu besar | lihat [§13](#13-optimasi-ram-1gb) |
| Auto-deploy: "Folder deploy tidak ditemukan" | `VPS_PATH` secret masih default CyberPanel | set `VPS_PATH=/var/www/ute-parts` di GitHub Secrets |
| Auto-deploy: `chown` gagal | user web tidak terdeteksi | set `WEB_USER=www-data` |

---

## 13. Optimasi RAM 1GB

### OPcache

```bash
sudo nano /etc/php/8.4/fpm/conf.d/99-ute-opcache.ini
```

```ini
opcache.enable=1
opcache.memory_consumption=96
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0      # production; set 1 saat deploy lalu kembali 0
```

CLI PHP juga perlu (dipakai artisan/queue):
```bash
sudo nano /etc/php/8.4/cli/conf.d/99-ute-opcache.ini
```

```ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=96
```

```bash
sudo systemctl restart php8.4-fpm
```

> Kalau deploy webhook/queue gagal dengan error opcode, set `opcache.validate_timestamps=1` sementara,
> deploy, lalu kembalikan ke `0`.

### MySQL tuning

```bash
sudo nano /etc/mysql/mysql.conf.d/ute-parts.cnf
```

```ini
[mysqld]
innodb_buffer_pool_size=256M
innodb_log_file_size=64M
max_connections=50
```

```bash
sudo systemctl restart mysql
```

### Batasi memori PHP-FPM

```bash
sudo nano /etc/php/8.4/fpm/pool.d/ute-parts.conf
```

```ini
[ute-parts]
user = www-data
group = www-data
listen = /run/php/php8.4-fpm.sock
pm = dynamic
pm.max_children = 4
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500
php_admin_value[memory_limit] = 256M
```

```bash
sudo systemctl restart php8.4-fpm
```

---

## 14. Backup Harian (Wajib)

### Backup database + file upload

```bash
sudo nano /etc/cron.daily/ute-parts-backup
```

```bash
#!/bin/bash
set -e
STAMP=$(date +%F)
DEST=/var/backups/ute-parts
mkdir -p "$DEST"

# Dump database
mysqldump -u uteparts -p'PASSWORD' ute_parts_prod | gzip > "$DEST/db-$STAMP.sql.gz"

# File upload (foto produk, foto unit servis)
tar czf "$DEST/storage-$STAMP.tar.gz" -C /var/www/ute-parts storage/app/public

# Simpan 14 hari
find "$DEST" -type f -mtime +14 -delete
```

```bash
sudo chmod +x /etc/cron.daily/ute-parts-backup
```

### Rotasi log Laravel

```bash
cp deploy/logrotate/ute-parts.conf /etc/logrotate.d/ute-parts

# Bila lokasi project-mu berbeda:
sed -i 's#/var/www/ute-parts#/path/kamu#g' /etc/logrotate.d/ute-parts

logrotate -d /etc/logrotate.d/ute-parts   # dry-run verifikasi
```

### Restore (kalau perlu)
```bash
gunzip -c /var/backups/ute-parts/db-2026-09-25.sql.gz | sudo mysql -u uteparts -p ute_parts_prod
tar xzf /var/backups/ute-parts/storage-2026-09-25.tar.gz -C /var/www/ute-parts
```

---

## Ringkasan Urutan Eksekusi

Sekali jalan, berurutan:

```bash
# 1. Paket + swap
sudo apt install -y nginx mysql-server php8.4-fpm php8.4-{cli,mysql,mbstring,xml,curl,zip,gd,bcmath} git certbot python3-certbot-nginx supervisor
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fetc

# 2. Database
sudo mysql -e "CREATE DATABASE ute_parts_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'uteparts'@'localhost' IDENTIFIED BY 'PASSWORD';
GRANT ALL ON ute_parts_prod.* TO 'uteparts'@'localhost';"

# 3. Project
sudo mkdir -p /var/www/ute-parts && cd /var/www/ute-parts
git clone https://github.com/bremspace/ute-parts-erp.git .
composer install --no-dev --optimize-autoloader
npm ci && npm run build && php artisan storage:link

# 4. Env + database
cp .env.example .env && php artisan key:generate        # lalu edit .env
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
# + buat user admin pertama via tinker (§5)

# 5. Permission + cache
sudo chown -R www-data:www-data storage bootstrap/cache public/build
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 6. Nginx + SSL
sudo ln -s /etc/nginx/sites-available/uteparts /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d uteparts.id -d www.uteparts.id

# 7. Queue + cron
cp deploy/supervisor/ute-parts-queue.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
(sudo crontab) * * * * * cd /var/www/ute-parts && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1

# 8. Smoke test
curl -s https://uteparts.id/up -o /dev/null -w "%{http_code}\n"   # 200
```

---

## TL;DR

| Kebutuhan | Nilai |
|---|---|
| Folder project | `/var/www/ute-parts` |
| Document root Nginx | `/var/www/ute-parts/public` |
| User PHP-FPM | `www-data` |
| PHP | 8.4 |
| Node | 20+ |
| Perintah QA lokal | `php scripts/qa-auto.php` (tidak perlu di production) |
| Update berikutnya | `bash deploy.sh` atau `git pull && composer install && npm run build` |
