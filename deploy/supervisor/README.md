# Supervisor — ute-parts-queue

Konfigurasi worker antrean (`queue:work`) untuk Ute Parts.

> **Jangan pernah meng-hardcode path server di file `.conf`.**
> Repository ini dipakai beberapa server dengan lokasi berbeda. Semua path di
> `ute-parts-queue.conf` ditulis sebagai `%(ENV_UTE_PATH)s` / `%(ENV_UTE_PHP)s`
> supaya path ditentukan per-server lewat environment, bukan lewat `git`.

---

## 1. Kenapa file ini perlu diperbaiki (insiden yang sudah terjadi)

`/etc/supervisor/conf.d/ute-parts-queue.conf` menunjuk direktori yang tidak ada
(`/var/www/test.uteparts.id`), sehingga:

```
$ supervisorctl status ute-parts-queue
ute-parts-queue: ERROR (FATAL)
  (spawn error: error=2, No such file or directory)
```

Supervisor **tidak bisa menjalankan worker sama sekali**, tapi tidak ada yang
melihat eksplisit. Akibatnya job menumpuk:

```sql
select count(*) from jobs;            -- 53 job pending "KirimNotifikasiJob"
select count(*) from failed_jobs;     -- 0 (job tidak pernah dicoba, jadi gagal)
```

Pola ini adalah gejala umum: `jobs` berisi banyak, `failed_jobs` kosong, dan
`supervisorctl status` menunjukkan `FATAL`. Semua notifikasi WA/email
ikut tertahan karena `NotificationService` dispatch lewat queue (`database`
driver) — bukan sync.

Template repo punya masalah yang sama, hanya dengan path berbeda
(`/var/www/ute-parts`), jadi template ikut diperbaiki: sekarang path diambil
dari environment variable.

---

## 2. Isi konfigurasi (ringkas)

| Setting | Nilai | Alasan |
|---|---|---|
| `numprocs` | `1` | VPS RAM 1GB juga hosting PHP-FPM + web |
| `command` | `queue:work` | `database` driver, bukan Redis (butuh RAM) |
| `--sleep=3` | 3 detik | jeda saat antrean kosong, jangan burn CPU |
| `--tries=3` | 3 | percobaan sebelum masuk `failed_jobs` |
| `--timeout=60` | 60 detik | satu job dibatasi 60 detik |
| `--max-time=3600` | 1 jam | keluar sendiri tiap jam → `autorestart`, RAM tidak menumpuk |
| `--max-jobs=1000` | 1000 | keluar setelah 1000 job, lalu di-restart |
| `--memory=256` | 256MB | keluar bila RAM proses tembus batas yang aman |
| `stopwaitsecs=70` | 70 | **harus > `--timeout`** supaya job sempat di-*graceful* saat stop |
| `stdout_logfile` | `storage/logs/queue-worker.log` | rotasi 10MB × 3 |
| `stopasgroup` / `killasgroup` | `true` | orphan child = kebocoran RAM di server 1GB |

---

## 3. Cara install

```sh
# 1) Determine path & binary PHP yang benar di server ini
cd /var/www/nama-project && ls artisan        # harus ada
find /usr -name "php" -path "*/bin/*" 2>/dev/null | head -1

# 2) Copy template
sudo cp deploy/supervisor/ute-parts-queue.conf /etc/supervisor/conf.d/

# 3) Ganti placeholder dengan nilai server ini
sudo sed -i \
  -e 's#UTE_PATH="/var/www/nama-project"#UTE_PATH="/var/www/nama-project-asli"#' \
  -e 's#UTE_PHP="/usr/bin/php8.3"#UTE_PHP="/path/php/asli"#' \
  -e 's#^user=.*#user=www-data#' \
  /etc/supervisor/conf.d/ute-parts-queue.conf

# 4) Pastikan user bisa menulis log
sudo chown -R www-data:www-data /var/www/nama-project/storage
sudo chmod -R ug+rwX,o-rwx /var/www/nama-project/storage

# 5) Aktifkan
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status ute-parts-queue     # wajib RUNNING
```

Alternatif **tidak menyimpan path sama sekali** di file `.conf`: taruh
environment di file milikmu sendiri di `/etc/supervisor/conf.d/`:

```ini
# /etc/supervisor/conf.d/ute-parts-env.conf
[supervisord]
environment=UTE_PATH="/var/www/nama-project",UTE_PHP="/usr/bin/php8.3"
```

`%(ENV_UTE_PATH)s` lalu mengambilnya secara otomatis.

> Variabel yang tidak ada **membuat supervisor menolak start**. Itu memang
> perilaku yang diinginkan: gagal keras dan terlihat, bukan worker yang diam-diam
> jalan di path yang salah.

---

## 4. Cara cek bahwa worker hidup

```sh
# a) Status proses — harus RUNNING, bukan FATAL / STOPPED
sudo supervisorctl status ute-parts-queue

# b) Log worker (paling cepat memastikan job memang diproses)
tail -f /var/www/nama-project/storage/logs/queue-worker.log

# c) Jumlah antrean — harus turun atau tetap, tidak naik terus
cd /var/www/nama-project
php artisan queue:monitor default --max=100     # Laravel 11+; timeout bila antrean macet
php artisan tinker --execute='echo DB::table("jobs")->count();'
php artisan tinker --execute='echo DB::table("failed_jobs")->count();'

# d) Beri satu job nyata sebagai uji
php artisan queue:work --once      # jalan manual sekali, harus job-nya hilang
```

Kalau `php artisan queue:monitor` belum ada di versi Laravel yang dipakai,
pakai `queue:work --once` atau query `jobs` langsung lewat `tinker`.

---

## 5. Diagnosa: job tetap `pending`

Lakukan **berurutan** — hentikan di masalah pertama yang ditemukan.

### 5.1 Worker mati / tidak pernah start
```sh
sudo supervisorctl status ute-parts-queue
tail -n 50 /var/log/supervisor/supervisord.log
sudo supervisorctl tail -100 ute-parts-queue stderr
```
- `ERROR (FATAL)` + `spawn error: error=2` → path `UTE_PATH` atau `UTE_PHP`
  salah/tidak ada. Cek `ls "$(path)/artisan"`.
- `ERROR (FATAL)` + `Could not chdir` → `directory` tidak ada.
- `BACKOFF` → worker start lalu mati dalam <5 detik; pesan error-nya di
  `queue-worker.log`.
- `EXITED` terus → `supervisorctl restart ute-parts-queue` setelah perbaiki.

### 5.2 Path salah (penyebab insiden ini)
```sh
grep -E 'UTE_PATH|UTE_PHP|directory' /etc/supervisor/conf.d/ute-parts-queue.conf
ls -l /var/www/nama-project/artisan
```
Kalau `artisan` tidak ada di direktori itu, path-nya salah.

### 5.3 `DB_CONNECTION` worker tidak cocok dengan web
Worker punya working directory sendiri, jadi `DB_*` dari `.env` harus terbaca.
Uji dari direktori yang **sama persis** dengan `directory=` di konfigurasi:
```sh
cd /var/www/nama-project
php artisan tinker --execute='echo config("database.default"), " / ", DB::connection()->getDatabaseName(), PHP_EOL;'
```
Kalau nama database/host berbeda dari yang dilihat web → job masuk ke tabel
`jobs` yang salah dan "menghilang" tanpa pernah diproses. Gejalanya khas:
`jobs` di DB lama tetap membengkak, di DB baru kosong.

### 5.4 `queue:work` memang tidak jalan
```sh
sudo supervisorctl restart ute-parts-queue
ps aux | grep -c '[q]ueue:work'
```
`0` → worker tidak ada. Also cek tidak ada `queue:pause`:
```sh
php artisan queue:resume default     # perintah: queue:resume / queue:continue
```

### 5.5 Permission log
```sh
sudo -u www-data touch /var/www/nama-project/storage/logs/queue-worker.log
ls -ld /var/www/nama-project/storage/logs
```
`Permission denied` saat start → worker FATAL. `supervisorctl` berjalan sebagai
root, jadi percobaan harus **sSebagai user yang sama** dengan `user=` di conf.

### 5.6 Job-nya sendiri yang macet / timeout
```sh
php artisan queue:failed
php artisan queue:retry all
tail -n 200 storage/logs/laravel.log
```
`--timeout=60` yang terlalu kecil untuk job tertentu akan terlihat sebagai
`JobTimeoutException` di `failed_jobs`, bukan sebagai antrean macet.

### 5.7 Cache konfigurasi basi
Kalau `config:cache` dibangun di environment lain:
```sh
php artisan config:clear && php artisan config:cache
sudo supervisorctl restart ute-parts-queue
```

---

## 6. Perintah harian yang aman

```sh
sudo supervisorctl restart ute-parts-queue    # setelah deploy kode
php artisan queue:restart                    # elegant: worker selesai job lalu exit,
                                            # supervisor yang menyalakannya lagi
php artisan queue:work --once                # one-off, jangan dipakai di produksi
```

`queue:restart` lebih halus daripada `supervisorctl restart` karena job yang
sedang berjalan dibiarkan selesai.
