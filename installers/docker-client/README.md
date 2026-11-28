# UTE PARTS ERP — Docker Installer khusus CLIENT

Installer Docker untuk **node client toko** yang berdiri sendiri (offline-first) dan
menarik data awalnya dari cloud **uteparts.id** melalui sinkronisasi.
Folder ini **tidak mengubah** installer lama di `installers/docker/`.

> **Aturan utama: tidak ada `php artisan db:seed` — titik.**
> Installernya hanya menjalankan `migrate`, membuat **satu** akun admin lokal
> dari `ADMIN_EMAIL` / `ADMIN_PASSWORD`, lalu menyiapkan cache, queue worker dan
> scheduler. Master data (produk, kategori, cabang, pelanggan, dll.) datang dari
> cloud lewat `php artisan sync:bootstrap`.

---

## 1. Daftar file

| File | Fungsi |
|---|---|
| `install.sh` | Wizard + build + jalankan container (Linux / VPS) |
| `install.bat` | Wrapper Windows, memanggil `install.ps1` |
| `install.ps1` | Wizard + build + jalankan container (Windows / PowerShell) |
| `Dockerfile` | Image Apache + PHP 8.4 + aset Vite + Compose Stack |
| `docker-compose.yml` | Service `app` (port `80:80`) + `mysql:8.0`, 3 named volume |
| `entrypoint.sh` | Migrate → admin lokal (tanpa seeder) → cache → worker & scheduler |
| `bootstrap-client-admin.php` | Pembuat akun admin lokal yang idempoten (dipanggil entrypoint) |
| `.env.client.example` | Template env **di dalam image** (tanpa rahasia) |
| `.gitignore` | Mencegah `.env.client` ikut ter-commit |

File `.env.client` **dibuat oleh wizard** di folder ini (mode `600` di Linux).
File ini dibaca Compose lewat `env_file` dan **sengaja tidak disalin ke image**
(Dockerfile hanya menyalin path aplikasi, bukan folder `installers/`).

---

## 2. Peran: client vs cloud

| | **client** (default) | **cloud** |
|---|---|---|
| `SYNC_NODE_ROLE` | `primary_store` | `cloud_storefront` |
| `SYNC_CLIENT_MODE` | `true` | `false` |
| `SYNC_REMOTE_URL` | `https://uteparts.id` | kosong (pasif receiver) |
| `SYNC_NODE_ID` | `STORE-01` (bisa diganti) | `CLOUD-01` |
| Database | DB lokal sendiri (volume `client_mysql`) | DB cloud yang menjadi sumber kanonik |
| Data awal | Ditarik via `sync:bootstrap` | Harus diisi dari sumber kanonik/restore (bukan seeder installer ini) |
| Push/pull otomatis | Ya, scheduler `sync:flush` + `sync:pull` (jika remote terisi) | Pasif menerima request sync |

`SYNC_CLIENT_MODE` adalah **penanda installer** (dipakai `entrypoint.sh` untuk
mencetak info peran), bukan bagian dari `config/sync.php`.

Pada node cloud, `SYNC_API_SECRET` **wajib persis sama** dengan yang ada di
setiap client. Tanpa itu, request bootstrap/sync ditolak HTTP 401.

---

## 3. Wizard (yang ditanyakan installer)

1. **Peran** — `client` (default, `1`) atau `cloud` (`2`).
2. **URL cloud** — default `https://uteparts.id` untuk client; boleh kosong untuk cloud.
3. **Node ID** — default `STORE-01` (client) / `CLOUD-01` (cloud).
4. **API secret** — disembunyikan saat diketik; bila kosong akan di-generate
   otomatis (32 byte hex). Salin nilainya ke node lawan bicara.
5. **Email & password admin lokal** — hanya untuk **login awal**, bukan data master.
   Password disembunyikan saat diketik dan di-hash (`Hash::make`) sebelum disimpan.
6. **Cabang opsional** — kode cabang (default `CBG-01`, ketik `-` untuk tanpa cabang)
   dan nama cabang.

Sebelum build, installer menampilkan **ringkasan konfigurasi** (secret & password
disamarkan) dan meminta konfirmasi. Jika `.env.client` sudah ada, installer
menawarkan untuk memakainya kembali atau mengisi ulang (file lama dibackup dulu).

---

## 4. Cara pakai (copy-paste friendly)

### Linux / VPS

```bash
cd installers/docker-client
chmod +x install.sh
bash install.sh
```

### Windows

Double-click **`install.bat`**, atau:

```powershell
cd installers/docker-client
powershell -NoProfile -ExecutionPolicy Bypass -File .\install.ps1
```

Pilih aksi:

- **[1] Fresh install** — container & volume dihapus, database lokal kosong.
- **[2] Update / Start** — image dibangun ulang, data database tetap.

Akses: `http://localhost` atau `http://IP-LAN` (port host `80`).

---

## 5. Apa yang terjadi saat container start

`entrypoint.sh` melakukan, secara berurutan:

1. Menyiapkan `.env` persisten di `storage/.env` (symlink) dan menghasilkan
   `APP_KEY` sekali saja — tidak berubah saat image di-build ulang.
2. Menunggu MySQL siap (maks. ~120 detik).
3. `php artisan migrate --force`.
4. `php /usr/local/bin/bootstrap-client-admin.php` → **maksimal satu user admin**
   dari `ADMIN_EMAIL`/`ADMIN_PASSWORD`, dibuat hanya bila belum ada.
   Role `super-admin` dan cabang default **hanya dipasang bila sudah tersedia**
   di DB lokal; tidak dibuat oleh installer.
5. `php artisan storage:link`, lalu `config:cache`, `route:cache`, `view:cache`.
   Bila isi `env_file` berubah, cache lama dibuang otomatis (checksum env).
6. Background: `queue:work --queue=default,sync` dan `schedule:work`.

**Tidak ada** `db:seed`, `DatabaseSeeder`, `MasterDataSeeder`, atau seeder lain
di jalur ini.

---

## 6. Bootstrap data dari cloud

Node client harus sudah memiliki `SYNC_ENABLED=true`,
`SYNC_NODE_ROLE=primary_store`, `SYNC_REMOTE_URL=https://uteparts.id`, dan
`SYNC_API_SECRET` yang sama dengan cloud.

```bash
cd installers/docker-client

# Tarik seluruh tabel kanonik dari cloud (sekali saat awal)
docker compose exec app php artisan sync:bootstrap

# Opsional: satu tabel saja / ubah ukuran halaman
docker compose exec app php artisan sync:bootstrap --table=produk --limit=200
```

Perintah ini memanggil `GET /api/internal/sync/bootstrap` di cloud dengan header
bertanda tangan HMAC-SHA256 (`X-Sync-Node`, `X-Sync-Timestamp`,
`X-Sync-Signature`). Endpoint tersebut **hanya merespons dari node
`cloud_storefront`**, jadi pastikan cloud memakai `SYNC_NODE_ROLE=cloud_storefront`
dan secret yang identik.

Data yang ikut adalah tabel kanonik yang memiliki `sync_uuid` (kategori, brand,
kualitas, satuan, tipe HP, produk, varian, konversi, barcode, harga tier,
pelanggan, tier membership, cabang, gudang, supplier, akun COA, jenis servis).
Kolom sensitif sengaja dibuang, jadi `APP_KEY` tiap node tidak perlu sama.

Setelah bootstrap, pasang ulang role/cabang ke admin lokal bila belum terpasang:

```bash
docker compose exec -e ADMIN_FORCE=true app php /usr/local/bin/bootstrap-client-admin.php
```

> `ADMIN_FORCE=true` juga menyelaraskan password dengan `ADMIN_PASSWORD`
> di `.env.client`. Gunakan sekali lalu hilangkan jika tidak ingin password
> ditimpa tiap restart.

Scheduler otomatis menjalankan `sync:flush --batch` dan `sync:pull` setiap
menit pada client yang memiliki `SYNC_REMOTE_URL`.

---

## 7. Cek status sinkronisasi

Buka **`/app/sync`** setelah login sebagai admin lokal (halaman ringkasan
sinkronisasi pada build yang menyediakannya). Versi aplikasi saat ini memakai
endpoint JSON di balik halaman tersebut:

```text
http://localhost/app/sync/status?probe=1
```

Endpoint ini menampilkan `enabled`, `node_role`, `node_id`, jumlah outbox
`pending / processing / failed / synced`, `last_synced_at`, `remote_configured`,
`remote_reachable`, dan `cloud_host`. Tombol trigger di UI memanggil
`POST /app/sync/trigger` (flush + pull).

Cara cepat lewat CLI:

```bash
docker compose logs -f app
# baris "[client-admin] ..." = hasil pembuatan admin lokal
docker compose exec app php artisan sync:pull --all
docker compose exec app php artisan sync:flush --batch
```

---

## 8. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| `Gagal mengambil daftar tabel bootstrap` / 401 | `SYNC_API_SECRET` beda dengan cloud, atau cloud bukan `cloud_storefront`. Samakan secret (case-sensitive) lalu `docker compose up -d --force-recreate app`. |
| `SYNC_REMOTE_URL belum dikonfigurasi` | Isi URL cloud di wizard / `.env.client`, lalu recreate container. |
| Bootstrap lambat / timeout | Jaringan ke `uteparts.id` terblokir atau proxy. Cek `curl -I https://uteparts.id/api/internal/sync/health` dari host dan dari container. |
| Admin tidak bisa masuk / langsung ke Setup Wizard | Itu alur UI aplikasi (`app_setup_completed` belum true), bukan seeder installer. Selesaikan wizard UI dengan kredensial admin lokal, atau setelah data cloud hadir jalankan perintah `ADMIN_FORCE=true` di atas. |
| Admin ada tapi tidak punya cabang/role | Role & cabang belum ada di DB lokal saat admin dibuat. Setelah `sync:bootstrap` (cabang ikut tersinkron), jalankan `bootstrap-client-admin.php` dengan `ADMIN_FORCE=true`. Role `super-admin` dibuat oleh alur inisialisasi aplikasi, bukan installer. |
| `Database not ready` | MySQL belum selesai init. Tunggu atau cek `docker compose logs mysql`; jangan hapus volume `client_mysql` kecuali ingin data kosong. |
| Port 80 bentrok | Hentikan service yang memakai port 80 atau ubah mapping di `docker-compose.yml` (mis. `8080:80`), lalu sesuaikan `APP_URL`. |
| Perubahan `.env.client` tidak efek | `docker compose up -d --force-recreate app` (entrypoint membuang cache via checksum env). |
| `The MAC is invalid` setelah restore snapshot | Samakan `APP_KEY` di `storage/.env` dengan APP_KEY sumber, lalu `docker compose exec app php artisan optimize:clear && docker compose exec app php artisan optimize`, hapus cookie browser, login ulang. |
| Queue/scheduler tidak jalan | `docker compose exec app tail -f storage/logs/queue-worker.log` dan `storage/logs/scheduler.log`. Pastikan `QUEUE_CONNECTION=database`. |

---

## 9. Catatan keamanan

- `.env.client` berisi secret & password: jangan di-commit, jangan dibagikan.
- Simpan backup `.env.client` di lokasi aman; tanpa itu node baru tidak bisa
  diverifikasi cloud.
- Untuk produksi/Internet, gunakan reverse proxy HTTPS di depan port 80 dan set
  `APP_URL=https://...` di `.env.client` bila diperlukan.
