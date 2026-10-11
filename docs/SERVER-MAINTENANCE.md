# Panduan Pemeliharaan Server & Production Ute Parts

Dokumen ini berisi panduan operasional, pemeliharaan rutin, monitoring, dan troubleshooting untuk environment **Production** Ute Parts ERP di VPS Ubuntu.

---

## 1. Spesifikasi Environment Production

- **Server IP:** `66.42.48.27`
- **Docroot Production:** `/var/www/uteparts.id`
- **Domain:** `https://uteparts.id`
- **Web Server:** Nginx (`/etc/nginx/sites-available/uteparts.id`)
- **PHP CLI / FPM:** PHP 8.4 CLI / PHP 8.5-FPM (`/run/php/php8.5-fpm.sock`)
- **Database:** MySQL (`ute_parts_prod`)
- **Queue Worker:** Supervisor (`ute-parts-prod-queue`)
- **Cron Scheduler:** `/etc/cron.d/laravel-ute-parts` (jalan setiap menit)
- **Web User:** `www-data:www-data`

---

## 2. Alur Deployment Otomatis (CI/CD)

Deploy production berjalan secara otomatis menggunakan **GitHub Actions** (`.github/workflows/deploy.yml`):

1. Developer melakukan `push` atau `merge` ke branch `main`.
2. GitHub Actions menjalankan automated tests & build asset frontend.
3. Jika seluruh pengujian lolos, workflow akan login via SSH ke VPS lalu:
   - Tarik update kode terbaru (`git reset --hard origin/main`).
   - Jalankan migrasi database (`php8.4 artisan migrate --force`).
   - Update dependensi & build frontend asset.
   - Cache config, route, dan Blade views.
   - Restart Supervisor queue worker.

> **Catatan:** Pastikan GitHub Repository Secrets berikut terkonfigurasi di repo (**Settings -> Secrets and variables -> Actions**):
> - `VPS_HOST`: `66.42.48.27`
> - `VPS_USER`: `root`
> - `VPS_SSH_PRIVATE_KEY`: Private SSH Key akses VPS
> - `VPS_PORT`: `22`

---

## 3. Perintah Pemeliharaan Harian (Cheat Sheet)

Sebelum menjalankan perintah Artisan di server, selalu masuk ke direktori production:
```bash
cd /var/www/uteparts.id
```

### A. Cache & Optimasi
Jalankan jika ada perubahan konfigurasi `.env` manual atau tampilan tidak ter-refresh:
```bash
# Bersihkan seluruh cache
php8.4 artisan optimize:clear

# Bangun kembali cache produksi (wajib untuk performa cepat)
php8.4 artisan optimize
```

### B. Background Queue Worker (Supervisor)
```bash
# Cek status queue worker
supervisorctl status ute-parts-prod-queue:*

# Restart queue worker (wajib jika ada perbaikan logic job/queue)
supervisorctl restart ute-parts-prod-queue:*

# Cek log output background queue
tail -f storage/logs/queue.log
```

### C. Monitoring Log & Error
```bash
# Pantau log error Laravel secara realtime
tail -f storage/logs/laravel.log

# Pantau log error Nginx
tail -f /var/log/nginx/error.log
```

### D. Mode Perawatan (Maintenance Mode)
Gunakan jika sedang melakukan migrasi besar yang membutuhkan aplikasi berhenti sementara:
```bash
# Aktifkan mode maintenance (dengan secret bypass untuk admin)
php8.4 artisan down --secret="akses-rahasia"
# Akses sementara dapat dibuka via: https://uteparts.id/akses-rahasia

# Matikan mode maintenance (aplikasi aktif kembali)
php8.4 artisan up
```

---

## 4. Backup & Restore Database

### A. Backup Manual
Simpan cadangan database sebelum melakukan perubahan struktural besar:
```bash
mysqldump -u test_uteparts -ptest123 ute_parts_prod > /root/backup_prod_$(date +%F_%H%M%S).sql
```

### B. Restore Database
```bash
mysql -u test_uteparts -ptest123 ute_parts_prod < /root/nama_file_backup.sql
```

---

## 5. Troubleshooting Masalah Umum

### 1. Masalah Permission File (`Permission Denied` saat menulis log/cache)
Jika terjadi error penulisan file oleh web server, reset permission ke `www-data`:
```bash
cd /var/www/uteparts.id
chown -R www-data:www-data storage bootstrap/cache public/build
chmod -R 775 storage bootstrap/cache
```

### 2. Service Restart
```bash
# Nginx
systemctl reload nginx
# atau restart jika reload gagal
systemctl restart nginx

# PHP-FPM
systemctl restart php8.5-fpm

# MySQL
systemctl restart mysql
```

### 3. Server Kehabisan Memori (RAM Penuh)
Cek penggunaan memori dan proses teratas:
```bash
free -h
ps -eo pmem,rss,comm --sort=-rss | head -n 10
```
Jika RAM kritis, bersihkan cache sistem operasi:
```bash
sync && echo 3 > /proc/sys/vm/drop_caches
```
