# Deploy Production — Ute Parts (CyberPanel + OpenLiteSpeed, RAM 1GB)

Panduan deploy sesuai PRD §7 (constraint keras: **1GB RAM**) & §6 (keamanan).

## 0. Prasyarat Server

| Requirement | Nilai | Cek |
|---|---|---|
| OS | Ubuntu 22.04 LTS / AlmaLinux | `cat /etc/os-release` |
| RAM | ≥ 1GB + **swap 2GB** | `free -h` |
| PHP | **8.3+ wajib** (Laravel 13 drop 8.1/8.2) | `lsphp83` di CyberPanel |
| Ekstensi PHP | `pdo_mysql, gd, zip, fileinfo, mbstring, openssl, intl` | wajib utk Excel/font/gambar |
| MySQL | 8.0 / MariaDB 10.6+ | `mysql --version` |
| Node (build sekali) | 20+ | build done di CI/lokal, upload `public/build` |

> ⚠️ **Jika hosting cuma PHP 8.2** → turunkan ke Laravel 12 (fallback resmi PRD §1), API identik di level yang dipakai project.

---

## 1. Persiapan Server (sekali)

```bash
# Swap 2GB (mitigasi OOM saat migrasi/queue)
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# OPcache (sangat penting utk RAM 1GB)
# CyberPanel → Websites → PHP Extensions / php.ini 8.3:
#   opcache.enable=1
#   opcache.memory_consumption=96
#   opcache.interned_strings_buffer=8
#   opcache.max_accelerated_files=10000
#   opcache.validate_timestamps=0        # production; toggle 1 saat deploy
#   opcache.revalidate_freq=0
```

## 2. Upload & Setup Aplikasi

```bash
cd /home/<user>/ute-parts   # docroot CyberPanel

# Struktur: app di subfolder, public/ = docroot
# CyberPanel Website → docroot → pilih folder dengan `public`
# (ganti DocumentRoot ke ute-parts/public)
```

### Konfigurasi `.env` production (kunci)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ute_parts
DB_USERNAME=ute_parts
DB_PASSWORD=STRONG_PASSWORD

# RAM 1GB: cache & queue ringan
CACHE_STORE=database        # atau file — hindari Redis stamina 1GB
QUEUE_CONNECTION=database   # + Supervisor (bawah)
SESSION_DRIVER=database

# Integrasi eksternal (sandbox dulu)
DUITKU_SANDBOX=true
DUITKU_MERCHANT_CODE=...
DUITKU_API_KEY=...
DUITKU_MERCHANT_KEY=...
BITESHIP_API_KEY=...
# SHOPEE_PARTNER_ID=... / SHOPEE_PARTNER_KEY=... / SHOPEE_SHOP_ID=...  (phase deploy omnichannel)
```

### Install & Migrate

```bash
cd ute-parts
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

> **Set pemilik files**: `chown -R lsadm:lsadm /home/<user>/ute-parts` (OpenLiteSpeed user) — `storage/` & `bootstrap/cache/` wajib writable.

## 3. OpenLiteSpeed — Virtual Host

CyberPanel → Websites → **Manajemen VHost** → edit:

```
docRoot = /home/<user>/ute-parts/public
enableLSCache = 1
// context utk storage (block akses file raw)
// rewrites: `try_files $uri $uri/ /index.php?$query_string;`
```

- **PHP**: LoginSolo → pilih **lsphp83** (2–3 worker via LSAPI — PRD §7)
- **HTTPS wajib** (§6): CyberPanel → SSL → Let's Encrypt (domain + www)
- Header keamanan di vhost:
  ```
  X-Content-Type-Options: nosniff
  X-Frame-Options: SAMEORIGIN
  Referrer-Policy: strict-origin-when-cross-origin
  ```

## 4. Launcher: Queue Worker (Supervisor) — WAJIB

PRD §4.9: notifikasi/payment wajib queue, **dilarang sync di request**.

```bash
sudo apt install supervisor
```

`/etc/supervisor/conf.d/ute-parts.conf`:

```ini
[program:ute-parts-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /home/<user>/ute-parts/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
numprocs=1                # RAM 1GB: 1 worker cukup
user=lsadm
redirect_stderr=true
stdout_logfile=/home/<user>/ute-parts/storage/logs/queue.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status
```

## 5. Scheduler (tier:recalc harian)

```bash
crontab -e
# tiap menit panggil Laravel scheduler (aman, hanya eksekusi tugas terjadwal)
* * * * * cd /home/<user>/ute-parts && php artisan schedule:run >> /dev/null 2>&1
```

## 6. Backup Harian (wajib sebelum go-live, §6)

CyberPanel → **Backup** > Website Backup (fleksibel, cron). Tambah dump DB:

```bash
# /etc/cron.daily/ute-parts-backup
#!/bin/bash
BACKUP_DIR=/home/backups
mkdir -p "$BACKUP_DIR"
mysqldump -u ute_parts -p'...' ute_parts | gzip > "$BACKUP_DIR/db-$(date +\%F).sql.gz"
# retensi 7 hari (PRD §6)
find "$BACKUP_DIR" -name 'db-*.sql.gz' -mtime +7 -delete
chmod +x /etc/cron.daily/ute-parts-backup
```

## 7. Optimasi MySQL 1GB (PRD §7)

di `/etc/mysql/mysql.conf.d/ute-parts.cnf`:

```ini
[mysqld]
innodb_buffer_pool_size=256M      # ≤ 256MB utk RAM 1GB
innodb_log_file_size=64M
query_cache_type=0                 # mariadb ≥10.1 tidak perlu
max_connections=50
```

## 8. Post-deploy Smoke Test

```bash
# 1. Health
curl -s https://domain.com/up | head -1        # → "ok" (route health Laravel)
# 2. Login backoffice
curl -s -X POST https://domain.com/app/login -d "email=admin@uteparts.com&password=..." -L -o /dev/null -w "%{http_code}"
# 3. Marketplace
curl -s https://domain.com/shop -o /dev/null -w "%{http_code}"   # 200
# 4. Queue worker jalan
php artisan queue:monitor || supervisorctl status
# 5. Webhook Duitku (sandbox) — kirim callback palsu, cek idempotency
curl -s -X POST https://domain.com/webhook/duitku -H 'Content-Type: application/json' \
  -d '{"merchantCode":"...","amount":0,"merchantOrderId":"x","signature":"invalid"}'
# → token signature invalid (400), BUKAN crash
# 6. Unit test produksi rolldown (optional)
cd ute-parts && php artisan test --env=production 2>/dev/null || true
```

## 9. Checklist Go-Live (PRD §11 DoD)

- [ ] HTTPS Let's Encrypt aktif, HTTP di-redirect
- [ ] `APP_DEBUG=false`
- [ ] OPcache aktif (96MB), validate_timestamps=0
- [ ] Supervisor queue jalan, 1 worker
- [ ] Scheduler cron aktif (`tier:recalc` 01:00)
- [ ] Backup DB harian + retensi 7 hari terbukti restore
- [ ] Duitku **sandbox** lolos UAT → baru pindah key production
- [ ] Biteship key production di .env
- [ ] Test Playwright/viewport 375/768/1440 di domain live
- [ ] Rate limit login teruji (10x/menit per IP)

## Troubleshooting Produksi

| Gejala | Penyebab umum | Solusi |
|---|---|---|
| 500 tiap request | cache stale / permission storage | `php artisan optimize:clear`; `chown -R lsadm:lsadm storage` |
| Login lambat | bcrypt rounds tinggi | atur `BCRYPT_ROUNDS=10` (bukan 12) utk 1GB |
| Queue tidak jalan | Supervisor crash after OOM | tambah `memory_limit=-1` di php.ini worker; naikkan swap |
| Form tidak submit (419) | cache session config | `php artisan config:clear` setelah deploy |
| Excel import error | ext-gd/zip kurang | aktifkan di CyberPanel PHP extensions |
| Webhook 419 | CSRF belum dikecualikan | pastikan route `/webhook/*` pakai `withoutMiddleware(ValidateCsrfToken)` (sudah default di `routes/web.php`) |