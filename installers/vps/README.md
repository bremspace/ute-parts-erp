# Installer VPS Baru — UTEParts ERP

Installer produksi **copy–paste** untuk Ubuntu 24.04 LTS.

Yang dipasang:

| Komponen | Versi / pilihan |
|---|---|
| OS | Ubuntu 24.04 LTS |
| Web server | Nginx + HTTP/2 |
| PHP | PHP-FPM 8.4 (fallback 8.3) + OPcache + imagick |
| Database | MariaDB 11.4 LTS (tuning otomatis) |
| Cache/session/queue | Redis 7 + `phpredis` |
| Queue worker | Supervisor, 2 proses |
| Scheduler | cron tiap menit |
| HTTPS | Let's Encrypt + auto renew |
| Firewall | UFW + fail2ban |
| OpenWA | opsional, Docker Compose + reverse proxy WebSocket |
| Backup | harian 02.15, retention 7 hari |
| Helper | `ute-deploy`, `ute-backup`, `ute-healthcheck` |

---

## 1. Sebelum jalankan

### DNS
Arahkan A record ke IP VPS:

```text
tokobaru.com      -> IP_VPS
www.tokobaru.com  -> IP_VPS   (opsional, akan ditanya installer)
wa.tokobaru.com   -> IP_VPS   (kalau OpenWA dipasang di VPS yang sama)
```

Port **80** dan **443** harus terbuka dari internet.

### VPS minimum
- 4 vCPU
- 8 GB RAM
- 80 GB NVMe

Kalau OpenWA satu VPS dengan ERP, sebaiknya 16 GB RAM.

---

## 2. Jalankan installer ERP

```bash
git clone https://github.com/bremspace/ute-parts-erp.git /tmp/ute-installer
cd "/tmp/ute-installer/installer vps new"
sudo bash install.sh
```

Installer akan bertanya:
- domain ERP;
- apakah pakai `www`;
- email Let's Encrypt;
- path aplikasi;
- repo & branch;
- subdomain OpenWA;
- timezone;
- apakah OpenWA dipasang di VPS yang sama.

Semua password/key digenerate otomatis dan disimpan di:

```text
/root/ute-install-credentials.txt
```

Jangan simpan file itu di tempat publik.

---

## 3. Kalau OpenWA di VPS terpisah

Di **VPS OpenWA**, jalankan:

```bash
git clone https://github.com/bremspace/ute-parts-erp.git /tmp/ute-installer
cd "/tmp/ute-installer/installer vps new"
sudo bash install-openwa-only.sh
```

Lalu di ERP isi:

| Field | Isi |
|---|---|
| Provider | OpenWA (Self-Hosted Gateway) |
| URL | `https://wa.DOMAIN_ANDA/api` |
| Token | API key dari `/root/openwa-credentials.txt` |
| ID Sesi | UUID dari dashboard OpenWA |

---

## 4. Akses IP & domain

Installer otomatis mendeteksi IP publik VPS dan membuat server block Nginx untuk:

| Akses | URL | Keterangan |
|---|---|---|
| Domain ERP | `https://DOMAIN` | HTTPS, redirect otomatis dari HTTP |
| IP ERP (HTTP) | `http://IP_VPS` | langsung ke aplikasi, tanpa redirect |
| IP ERP (HTTPS) | `https://IP_VPS` | sertifikat self-signed, NAT harus forward 443 |
| Domain OpenWA | `https://wa.DOMAIN` | reverse proxy + WebSocket |
| IP OpenWA | `http://IP_VPS` / `https://IP_VPS` | saat memakai `install-openwa-only.sh` |

Catatan NAT MikroTik:
- `external_port → VPS:80` untuk akses HTTP lewat IP.
- `external_port → VPS:443` kalau ingin akses HTTPS lewat IP.
- Satu port external tidak bisa sekaligus dipakai HTTP dan HTTPS; pakai dua port berbeda kalau keduanya dibutuhkan.

## 5. Setelah installer selesai (OpenWA)

Buka dashboard WA:

```text
https://wa.tokobaru.com
```

Lalu:

1. Login dengan API key.
2. Menu **Sesi → toko-utama → Start**.
3. Scan QR dengan WhatsApp.
4. Tunggu status **READY**.
5. **Cara tercepat:** di ERP buka **Pengaturan → Integrasi → WhatsApp Gateway**, pilih Provider **OpenWA**, lalu tempel **Master Admin Key** pada panel **Setup Otomatis 1-Klik**. ERP akan membuat sesi + API key operator + menyimpannya terenkripsi. QR bisa langsung discan dari panel ERP.
6. Cara manual juga bisa: isi URL `http://127.0.0.1:2785/api` (satu VPS) atau `https://wa.domain/api` (terpisah), Token = API key, ID Sesi = UUID.
7. Klik **Uji Coba WhatsApp**.

Catatan lanjutan juga ditulis di:

```text
/root/ute-openwa-next.txt
```

---

## 6. Perintah harian

```bash
ute-healthcheck     # cek service, DB, Redis, HTTP, HTTPS
ute-deploy          # update kode dari origin/main + migrate + cache + restart worker
ute-backup          # backup manual (database + storage + .env + OpenWA)
```

Cron otomatis:

- scheduler Laravel: tiap menit;
- backup: tiap hari jam 02.15;
- sertifikat: auto renew oleh certbot systemd timer.

---

## 7. Backup & restore

Backup tersimpan di:

```text
/var/backups/ute-parts/ute-YYYY-MM-DD_HHMMSS.tar.gz
```

Isi:
- `database.sql`
- `storage/`
- `env.production`
- `openwa-data/` (kalau OpenWA lokal)

Restore manual (contoh):

```bash
cd /tmp
tar -xzf /var/backups/ute-parts/ute-XXXX.tar.gz
MYSQL_PWD='password_db' mariadb -uuteparts uteparts_db < database.sql
cp env.production /var/www/ute-parts-erp/.env
cp -a storage/. /var/www/ute-parts-erp/storage/
```

Kalau pakai rclone, set `RCLONE_REMOTE` di `/etc/environment` atau cron, lalu backup otomatis ter-upload.

---

## 8. Struktur folder

```text
installer vps new/
├── README.md
├── install.sh                       # installer ERP + opsional OpenWA
├── install-openwa-only.sh           # installer OpenWA standalone
├── config/
│   ├── env-production.env
│   ├── nginx-ute.conf.tpl
│   ├── nginx-openwa.conf.tpl
│   ├── nginx-openwa-ip.conf.tpl
│   ├── php-fpm-www.conf.tpl
│   ├── php-opcache.ini
│   ├── mariadb-tuning.cnf.tpl
│   ├── supervisor-ute.conf.tpl
│   ├── cron-ute.tpl
│   └── openwa/
│       ├── docker-compose.yml
│       └── .env.tpl
└── scripts/
    ├── lib.sh
    ├── backup.sh.tpl
    ├── deploy.sh.tpl
    └── healthcheck.sh.tpl
```

---

## 9. Catatan penting

- Installer **tidak** menghapus database yang sudah ada; ia hanya membuat database/user jika belum ada.
- `.env` lama akan ditimpa, tetapi installer menyimpan kredensial baru di `/root/ute-install-credentials.txt`.
- Upload backup/restore via UI sudah diizinkan sampai **2 GB** (Nginx `2100m`, PHP `post_max_size=2100M`, Livewire `2048000` KB).
- Backup UI disimpan di `storage/app/backups` dan sudah dimiliki `www-data`. Tool `mysqldump`/`mariadb-dump` dan `mysql`/`mariadb` ikut terpasang.
- `opcache.validate_timestamps=0` — setiap deploy harus lewat `ute-deploy` agar cache diperbarui.
- Jangan expose port `2785` langsung ke internet. Pakai reverse proxy HTTPS.
- Untuk OpenWA dengan banyak sesi, pertimbangkan engine `baileys` agar lebih ringan.

---

## 10. Troubleshooting cepat

| Gejala | Cek |
|---|---|
| Login sukses tapi 419 Page Expired | `SESSION_SECURE_COOKIE=false` di `.env`, lalu `ute-deploy` + hapus cookie browser. Ini terjadi kalau akses lewat IP/HTTP karena cookie Secure tidak tersimpan. |
| Backup database gagal | `ute-healthcheck` → cek binary `mysqldump`/`mariadb-dump`. Pastikan `storage/app/backups` writable `www-data`. |
| Upload restore gagal / terpotong | Cek `client_max_body_size 2100m`, PHP `post_max_size=2100M`, dan `LIVEWIRE_UPLOAD_MAX_KB=2048000`. |
| Nginx 502 | `systemctl status php*-fpm`, `ute-healthcheck` |
| Queue tidak jalan | `supervisorctl status`, `supervisorctl restart ute:*` |
| WA timeout | pastikan URL dari ERP bisa di-curl dari **server ERP** |
| QR stuck | cek WebSocket proxy Nginx `Upgrade`/`Connection` |
| Certbot gagal | pastikan DNS sudah pointing & port 80 terbuka |
| DB slow | lihat `/etc/mysql/mariadb.conf.d/99-ute-tuning.cnf` |

---

Singkatnya: clone repo, masuk ke folder `installer vps new`, jalankan `sudo bash install.sh`, jawab pertanyaan, lalu scan QR OpenWA dan isi kredensialnya di ERP.
