# Deploy Production — Ute Parts (CyberPanel + OpenLiteSpeed, RAM 1GB)

Panduan deploy end-to-end sesuai PRD §7 (constraint: **1GB RAM**) & §6 (keamanan).
Urut eksekusi — jangan loncat. Kalau suatu langkah error, berhenti dan cek Troubleshooting §10.

---

## 0. Prasyarat Server

| Requirement | Nilai | Cek |
|---|---|---|
| OS | Ubuntu 22.04 LTS / AlmaLinux | `cat /etc/os-release` |
| RAM | ≥ 1GB + **swap 2GB** | `free -h` |
| PHP via CyberPanel | **lsphp85+** (Laravel 13 butuh PHP 8.5+) | Websites → PHP Version |
| Ekstensi PHP wajib | `pdo_mysql, gd, zip, fileinfo, mbstring, openssl, intl, sqlite3` | pakai tombol "PHP Extensions" di CyberPanel |
| MySQL/MariaDB | 10.6+ (bawaan CyberPanel) | `mysql --version` |
| Node 20+ (untuk build asset) | sekali saja di server | `node -v` |
| Git / rsync | untuk upload source | `git --version` |

> ⚠️ **Hanya punya PHP 8.2?** Turunkan ke Laravel 12 (fallback resmi PRD §1) — jangan deploy Laravel 13 di PHP 8.2.

---

## 1. Siapkan Website di CyberPanel (PANEL UI — bukan SSH)

1. **CyberPanel → Websites → Create Website**
   - Domain: `domain.com` (subdomain/dedicated)
   - PHP: **8.5**
   - Package: Home (RAM 1GB)
   - Centang **Create Database** → catat nama DB, user, password yang ditampilkan.

2. **Pastikan PHP extensions** aktif: Website → Manage PHP Extensions → centang semua dari §0.

3. **Buat SSL dulu** (biar APP_URL langsung https): Websites → SSL → **Let's Encrypt** (domain + www).

> ⚠️ **JANGAN pakai user `root` untuk koneksi aplikasi.**
> CyberPanel menampilkan error `access denied for user 'root'` karena root MySQL di server panel **tidak boleh dipakai lewat aplikasi** — kredensial root di CyberPanel biasanya terpisah/hanya via socket.
> → Selalu pakai user database khusus yang dibuat CyberPanel (step 1).

---

## 2. Upload Source Code (SSH)

```bash
# Perbesar RAM untuk composer/npm (1GB ketat)
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# Lokasi: di dalam Website folder CyberPanel, docroot-nya folder `public`
cd /home/<USER>/<website_dir>     # contoh: /home/ute/ute-parts

# Upload via git
git clone https://github.com/bremspace/ute-parts-erp.git .
# ATAU upload manual via rsync dari lokal:
#   rsync -avz --exclude vendor --exclude node_modules --exclude .env --exclude storage/framework/cache/* ./ user@server:/home/ute/ute-parts/

# 2a. Dependency PHP (tanpa dev)
composer install --no-dev --optimize-autoloader

# 2b. Dependency + BUILD asset frontend (WAJIB — tanpa ini halaman tampil polos tak ber-CSS)
npm install --ignore-scripts
npm run build          # menghasilkan public/build/*

# 2c. Storage link utk upload (produk/foto)
php artisan storage:link
```

---

## 3. Konfigurasi `.env` production (SSH)

```bash
cp .env.example .env
php artisan key:generate
```

Isi minimal (sesuaikan nilai dari CyberPanel step 1):

```env
APP_NAME="Ute Parts"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain.com
APP_LOCALE=id

LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<NAMA_DB_DARI_CYBERPANEL>
DB_USERNAME=<USER_DB_DARI_CYBERPANEL>
DB_PASSWORD=<PASSWORD_DB_DARI_CYBERPANEL>

BROADCAST_CONNECTION=log
QUEUE_CONNECTION=database    # RAM 1GB
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true   # WAJIB utk HTTPS — kalau false, cookie login ditolak browser
SESSION_DOMAIN=.domain.com   # samakan dgn APP_URL (dot di depan utk subdomain)

# Sanctum SPA (cookie session)
SANCTUM_STATEFUL_DOMAINS=domain.com

BCRYPT_ROUNDS=10             # hemat CPU di 1GB

# Integrasi eksternal (sandbox dulu!)
DUITKU_SANDBOX=true
DUITKU_MERCHANT_CODE=
DUITKU_API_KEY=
DUITKU_MERCHANT_KEY=
BITESHIP_API_KEY=
```

> ⚠️ **Jangan tinggalkan `DB_CONNECTION=sqlite`** default dari .env.example — server harus MySQL.

---

## 4. Migrasi & Seed database (SSH)

```bash
cd /home/<USER>/<website_dir>

# 4a. Cek koneksi DB DULU (dengan kredensial dari .env):
php -r "new PDO('mysql:host=127.0.0.1;port=3306;dbname=<DB>','<USER>','<PASS>'); echo 'DB OK'.PHP_EOL;"
# kalau error: cek user/password/host — lihat Troubleshooting §10

# 4b. Migrations + seed
php artisan migrate --force
php artisan db:seed --force

# 4c. Cache produksi (config + route + view)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 5. Set Pemilik & Permission (KRITIS)

OpenLiteSpeed menjalankan PHP sebagai user `lsadm`. Semua yang perlu ditulis PHP wajib dirubah pemilik.

```bash
cd /home/<USER>/<website_dir>

chown -R lsadm:lsadm storage bootstrap/cache public/build
chmod -R 775 storage bootstrap/cache
chmod -R 755 public/build

# Seluruh project boleh lsadm (paling sederhana):
chown -R lsadm:lsadm .
```

---

## 6. OpenLiteSpeed — Virtual Host (CyberPanel UI)

Website → **Manajemen VHost** → pastikan:

```
docRoot = /home/<USER>/<website_dir>/public
index  = index.php
enableLSCache = 1 (Web Cache → Dynamic)
```

- **PHP Handler**: set ke `lsphp85` (Websites → PHP → PHP 8.5), LSAPI mode.
- **Symlink di luar docroot**: aktifkan "Follow SymLink" di vhost (utk `public/storage` yang menunjuk ke `storage/app/public`).
- **HTTP → HTTPS redirect**: Website → SSL → "Force HTTPS" / Rewrite ke https.

---

## 7. Queue Worker (Supervisor) — WAJIB (PRD §4.9)

```bash
sudo apt install -y supervisor
```

> ⚠️ **Pakai PHP CLI yang SAMA dengan lsphp85.** CyberPanel menyediakan `lsphp85` di `PATH`; jika `which php` menunjuk versi lain (mis. 8.1 default OS), artisan/queue bisa error versi. Pastikan:
> ```bash
> /usr/local/lsws/lsphp85/bin/php -v   # harus PHP 8.5.x
> # lalu gunakan path itu di semua perintah artisan & supervisor
> ```

File `/etc/supervisor/conf.d/ute-parts.conf`:

```ini
[program:ute-parts-queue]
process_name=%(program_name)s_%(process_num)02d
command=/usr/local/lsws/lsphp85/bin/php /home/<USER>/<website_dir>/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
numprocs=1
user=lsadm
redirect_stderr=true
stdout_logfile=/home/<USER>/<website_dir>/storage/logs/queue.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

---

## 8. Scheduler (tier:recalc harian)

```bash
crontab -e
# tambah (gunakan path PHP CLI lsphp85 yang sama):
* * * * * /usr/local/lsws/lsphp85/bin/php /home/<USER>/<website_dir>/artisan schedule:run >> /dev/null 2>&1
```

---

## 10 Auto-Deploy Otomatis (GitHub → VPS) — DIREKOMENDASIKAN

Setiap push ke `main` → VPS otomatis pull + update + restart queue. **Wajib aman**: script `deploy.sh` melakukan **auto-rollback** ke versi sebelumnya jika ada langkah gagal — site tidak pernah mati lama di VPS.

### Fitur
- Trigger: push/main + tombol manual di GitHub Actions.
- Langkah: pull → composer → migrate → npm build → cache → chown lsadm → restart queue.
- **Rollback otomatis**: bila composer/migrate/build/cache gagal → `git reset --hard` ke commit lama + restore build lama + cache clear → site live lagi.
- Log di `/tmp/ute-deploy.log`, status tampil di tab Actions.

### Setup sekali (VPS — SSH)
```bash
# 1. Pastikan project sudah ada & environment production OK (bagian 2-8 di bawah)
# 2. Siapkan SSH key khusus deploy di VPS
mkdir -p ~/.ssh && chmod 700 ~/.ssh
ssh-keygen -t ed25519 -f ~/.ssh/ute_deploy -N "" -C "github-actions"
cat ~/.ssh/ute_deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys

# 3. Tampilkan private key (salin ke GitHub secret nanti):
cat ~/.ssh/ute_deploy
```

### Setup GitHub (sekali — tab Settings → Secrets and variables → Actions)
| Secret | Isi |
|---|---|
| `VPS_HOST` | IP/domain VPS (mis. `203.0.113.10`) |
| `VPS_USER` | user SSH (mis. `root`) |
| `VPS_SSH_PRIVATE_KEY` | isi private key dari `cat ~/.ssh/ute_deploy` |
| `VPS_PORT` | opsional, default 22 |
| `VPS_PATH` | path project di VPS (mis. `/home/ute/ute-parts`) |
| `PHP_BIN` | opsional; default `/usr/local/lsws/lsphp85/bin/php` |
| `QUEUE_NAME` | opsional; default `ute-parts-queue` |

### Cara kerja
1. Unduh `deploy.sh` dari repo ini ke `/tmp/ute-deploy.sh`, lalu eksekusi di VPS.
2. Script menyimpan hash commit lama & **backup `public/build`** sebelum bekerja.
3. Bila ada kegagalan → fungsi `rollback()` mengembalikan semuanya ke commit lama.

> 🔒 **Keamanan**: private key hanya di GitHub secrets, tidak pernah masuk repo. `deploy.sh` hanya update kode — `.env` (ada di VPS) **tidak pernah tersentuh** karena di-`.gitignore`.

---

## 11 Smoke Test Setelah Go-Live

```bash
BASE=https://domain.com

# 1. Health route
curl -s $BASE/up -o /dev/null -w "health=%{http_code}\n"          # 200

# 2. Marketplace (tanpa login)
curl -s $BASE/shop -o /dev/null -w "shop=%{http_code}\n"          # 200
curl -s $BASE/app/login -o /dev/null -w "login-page=%{http_code}\n"# 200

# 3. Login post (harus 302 → /app/pos); 419 = CSRF/cookie https bermasalah
curl -s -c c.txt -b c.txt -X POST $BASE/app/login \
  -d "email=admin@test.uteparts.id&password=password" \
  -o /dev/null -w "login-post=%{http_code}\n" -L

# 4. Beta user yang tidak diblock:
curl -s $BASE/shop -H "Accept: text/html" | grep -c "/build/assets"   # asset CSS ada?
curl -s $BASE/app/login -H "Accept: text/html" | grep -c "glass-panel"# layout muncul?

# 5. Queue
supervisorctl status ute-parts-queue        # RUNNING

# 6. Round-trip data
cd /home/<USER>/<website_dir>
php artisan tinker --execute="echo App\\Modules\\Rbac\\Models\\Cabang::count().' cabang';"
```

---

## 12 Troubleshooting Produksi

| Gejala | Penyebab | Solusi |
|---|---|---|
| **`Access denied for user 'root'@'localhost'`** | memakai user root; atau user DB di .env tidak match | (a) buat DB+user khusus via CyberPanel; (b) cek `.env` di **server** (`cat .env \| grep DB`); (c) `php artisan config:clear` (cadangan config lama); (d) test PDO §4a |
| Halaman tampil tanpa CSS | `public/build` tidak ada / belum `npm run build` | `npm install && npm run build`; `chown -R lsadm:lsadm public/build` |
| Login post → 419 | cookie HTTPS mati / SESSION_DOMAIN salah / cache config lama | `SESSION_SECURE_COOKIE=true`; `SESSION_DOMAIN=.domain.com`; `php artisan optimize:clear` |
| 500 semua halaman | permission storage / cache stale | `chown -R lsadm:lsadm storage bootstrap/cache`; `php artisan optimize:clear` |
| 404 Asset `build/xxx` | vhost docroot tidak mengarah ke `public` | pastikan docRoot = .../public |
| Login berhasil tapi redirect muter | APP_URL tanpa https / `SESSION_DOMAIN` beda | samakan APP_URL `https://domain.com`; tolong reload cookie |
| Migrate: table exists | seeder/upgrade | pastikan `.env` benar; jangan drop tabel tak sengaja |
| `SQLSTATE[HY000] [2000]` (mysqlnd old auth) | MariaDB lama pakai password hash lawas | `ALTER USER '<user>'@'localhost' IDENTIFIED WITH mysql_native_password BY '<pass>';` |
| `SQLSTATE[42000] 1115 Unknown character set utf8mb4` | MySQL/MariaDB lawas tanpa utf8mb4 | tambah di `.env`: `DB_CHARSET=utf8` + `DB_COLLATION=utf8_unicode_ci` |
| Artisan/queue error `PHP version ... does not satisfy` | CLI `php` beda dari lsphp85 | gunakan `/usr/local/lsws/lsphp85/bin/php` untuk semua perintah |
| Queue tidak jalan | Supervisor gagal/OOM | `supervisorctl status`; tambah `memory_limit=-1` utk CLI; periksa swap |
| Webhook Duitku 419 | CSRF tidak dikecualikan (seharusnya sudah default) | cek `routes/web.php` `withoutMiddleware(ValidateCsrfToken)` |
| Export Excel error | ekstensi `gd`/`zip` off | aktivkan PHP Extensions di CyberPanel |
| Lambat / OOM | OPcache off / MySQL buffer besar | §11 + PRD §7 |

---

## 13 Optimasi RAM 1GB (PRD §7)

### OPcache (CyberPanel → PHP 8.5 → PHP.ini)

```ini
opcache.enable=1
opcache.memory_consumption=96
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0      # production; set 1 saat deploy lalu kembali 0
```

### MySQL tuning (`/etc/mysql/mysql.conf.d/ute-parts.cnf` lalu `sudo systemctl restart mysql`)

```ini
[mysqld]
innodb_buffer_pool_size=256M
innodb_log_file_size=64M
max_connections=50
```

---

## 14 Backup Harian (wajib sebelum go-live, §6)

CyberPanel → **Backup** > Website Backup (engine bawaan). Tambah dump DB khusus (bukan root):

```bash
# /etc/cron.daily/ute-parts-backup
#!/bin/bash
mkdir -p /home/backups
mysqldump --single-transaction -u <USER_DB> -p'<PASS_DB>' <NAMA_DB> | gzip > /home/backups/db-$(date +\%F).sql.gz
find /home/backups -name 'db-*.sql.gz' -mtime +7 -delete
chmod +x /etc/cron.daily/ute-parts-backup
```

---

## 15 Checklist Go-Live (PRD §11 DoD)

- [ ] HTTPS Let's Encrypt aktif, HTTP di-redirect
- [ ] `APP_DEBUG=false`, `APP_URL=https://...`
- [ ] DB user khusus (BUKAN root) aktif & migrasi sukses
- [ ] `public/build` ter-build, layout ber-CSS di browser
- [ ] OPcache 96MB, `validate_timestamps=0`
- [ ] Supervisor queue 1 worker RUNNING
- [ ] Scheduler cron berjalan (`schedule:run` tiap menit)
- [ ] Backup DB harian + retensi 7 hari telah diuji-restore
- [ ] Session cookie HTTPS (`SESSION_SECURE_COOKIE=true`) dan login web dgn CSRF sukses
- [ ] Duitku sandbox lolos UAT → baru key produksi
- [ ] Test responsif 375 / 768 / 1440 di domain live
- [ ] Rate limit login teruji (10x/menit)
