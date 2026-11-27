# Panduan Pemasangan & Setup OpenWA (WhatsApp Gateway Self-Hosted)

Panduan lengkap instalasi, integrasi UI 1-klik, queue worker, dan troubleshooting gateway **OpenWA** untuk notifikasi WhatsApp otomatis pada **Bremspace ERP**.

> [!WARNING]
> **Disclaimer Resmi — Risiko Pemblokiran Nomor:**
> OpenWA menggunakan library reverse-engineered (`whatsapp-web.js` / `baileys`), **bukan WhatsApp Cloud API resmi Meta**. Selalu gunakan **nomor WhatsApp khusus bisnis** (bukan nomor pribadi/utama), terapkan warm-up pengiriman di hari-hari awal, dan hindari broadcast massal ke kontak baru tanpa jeda.

---

## 1. Arsitektur Singkat

```text
[Event ERP: Invoice / Servis / Broadcast / Approval]
        │
        ▼ (dispatch ke database queue — non-blocking)
[Supervisor Queue Worker] ──► [KirimNotifikasiJob]
                                     │
                                     ▼ (HTTP internal + X-API-Key)
                            [WhatsAppGatewayService]
                                     │
                                     ▼
                     http://127.0.0.1:2785/api (Loopback)
                            [Container openwa-api] ──► WhatsApp Web
```

- **Server-side proxy (`OpenWaManagerService`):** Key Admin hanya dipakai sekali di browser saat tombol 1-klik diklik untuk membuat Operator API Key ber-scope satu sesi (`role: operator`), lalu dihapus dari state UI dan disimpan terenkripsi di database ERP.
- **Bind loopback-only:** Kontainer OpenWA hanya mendengar di `127.0.0.1:2785` sehingga tidak terekspos ke internet publik.

---

## 2. Kebutuhan Server & Sizing RAM

File compose berada di `deploy/openwa/docker-compose.yml` dengan default dari `deploy/openwa/.env.example`:

| Engine (`ENGINE_TYPE`) | Perkiraan RAM | Catatan |
|---|---|---|
| `whatsapp-web.js` (Default) | ±300–750 MB / sesi (`OPENWA_MEM_LIMIT=1500m`) | Memakai Chromium headless, risiko ban lebih rendah. Cocok untuk VPS >= 4 GB RAM. |
| `baileys` | ±30–80 MB / sesi (`OPENWA_MEM_LIMIT=512m`) | Tanpa Chromium, sangat ringan untuk VPS RAM 1–2 GB, namun lebih sensitif terhadap fingerprinting WhatsApp. |

---

## 3. Langkah 1 — Instalasi Docker & Jalankan Kontainer OpenWA

### A. Pastikan Docker & Compose v2 terpasang
Jika `docker compose version` belum tersedia di VPS, jalankan script helper dari root repository:

```bash
sudo bash deploy/openwa/install-compose.sh
```

### B. Salin `.env` & jalankan kontainer
```bash
cd deploy/openwa
cp .env.example .env
```

Sesuaikan `deploy/openwa/.env` bila diperlukan (misal ganti `ENGINE_TYPE=baileys` & `OPENWA_MEM_LIMIT=512m` jika VPS RAM terbatas), lalu jalankan:

```bash
docker compose up -d
```

Tunggu **15–30 detik** hingga healthcheck selesai (`Up (healthy)`):

```bash
docker compose ps
docker compose logs -f --tail 50 openwa-api
```

> [!NOTE]
> Jika menjalankan `cat /app/data/.api-key` terlalu cepat sebelum startup selesai, file `.api-key` belum terbuat (`No such file or directory`). Pastikan status container sudah `(healthy)` terlebih dahulu.

---

## 4. Langkah 2 — Ambil Master Admin Key

Ambil Master Admin Key (`owa_k1_...`) dengan tambahan newline bersih agar aman disalin dari SSH terminal desktop maupun HP:

```bash
docker exec openwa-api sh -c 'cat /app/data/.api-key; echo ""'
```

Salin seluruh karakter mulai dari `owa_k1_...` sampai akhir tanpa spasi atau prompt terminal (`root@...`).

---

## 5. Langkah 3 — Setup 1-Klik di UI Bremspace ERP

Tidak perlu membuka dashboard web OpenWA via SSH tunnel. Semua konfigurasi dilakukan langsung dari Backoffice ERP:

1. Login ke Backoffice (`/app`), buka menu **Pengaturan → Gateway & Integrasi → WhatsApp Gateway**.
2. Pada dropdown **Provider Gateway**, pilih **`OpenWA (Self-Hosted Gateway)`**.
3. Pastikan **API Endpoint URL** terisi `http://127.0.0.1:2785/api`.
4. Di kotak **Setup Otomatis 1-Klik OpenWA**:
   - Tempel **Master Admin Key** (`owa_k1_...`) dari langkah 4.
   - Klik **Hubungkan & Buat Sesi Otomatis**.
   - ERP secara otomatis:
     1. Memeriksa kesiapan `/api/health/ready`,
     2. Membuat sesi OpenWA dan menyimpan **UUID Sesi** (`sessionId`),
     3. Menyalakan engine sesi & mencetak **Operator API Key** ber-scope sesi tersebut,
     4. Menyimpan kredensial terenkripsi di database lalu mengosongkan input Master Admin Key.
5. **Tautkan Nomor WhatsApp:**
   - **Cara 1 (Scan QR Code):** Klik **Muat / Refresh QR**, lalu buka WhatsApp di HP (**Perangkat Tertaut → Tautkan Perangkat**) dan scan QR di layar.
   - **Cara 2 (Kode Pairing 8 Karakter):** Pilih tab/input pairing code, masukkan nomor WA (contoh `0812...`), klik minta kode pairing, lalu masukkan kode 8 karakter tersebut di WhatsApp HP.
6. Setelah status sesi berubah menjadi **Terhubung & Siap (`WORKING`)**, klik **Simpan Konfigurasi**, masukkan nomor tujuan test di kolom **Uji Coba Kirim Pesan**, lalu klik **Kirim Pesan Uji Coba**.

> [!IMPORTANT]
> OpenWA memakai **UUID `sessionId`** pada path API (`/api/sessions/{uuid}/...`), bukan nama tampilan sesi. Gunakan tombol **Setup Otomatis 1-Klik** agar field UUID Sesi dan Operator Key terisi presisi.

---

## 6. Fallback Konfigurasi `.env` (Opsional)

Jika ingin menyetel konfigurasi default di `.env` root Laravel (terpakai bila UI ERP belum menyimpan pengaturan WA):

```env
WA_OPENWA_URL=http://127.0.0.1:2785/api
WA_OPENWA_API_KEY=<api-key-operator>
WA_OPENWA_SESSION=<uuid-sesi-dari-openwa>
WA_OPENWA_TIMEOUT=20
```

Pastikan tidak menjalankan `php artisan config:clear` yang lupa dijalankan setelah mengubah `.env`.

---

## 7. Langkah 4 — Pastikan Queue Worker Supervisor Berjalan

Notifikasi WA ERP dikirim secara asynchronous lewat `KirimNotifikasiJob` (driver `database`). Jika queue worker berhenti, notifikasi menumpuk di tabel `jobs`.

Periksa status worker:

```bash
sudo supervisorctl status
```

Jika belum terpasang, ikuti konfigurasi pada `deploy/supervisor/ute-parts-queue.conf` (`php artisan queue:work database ...`). Bila worker berstatus `FATAL Exited too quickly`:

1. Cek log supervisor di `/var/log/supervisor/` atau log yang dikonfigurasi di `.conf`.
2. Pastikan user worker (mis. `www-data`) memiliki izin tulis ke `storage/` dan `bootstrap/cache/`:
   ```bash
   sudo chown -R www-data:www-data /var/www/ute-parts-erp/storage /var/www/ute-parts-erp/bootstrap/cache
   ```
3. Restart worker:
   ```bash
   sudo supervisorctl restart uteparts-worker:*
   ```

---

## 8. Aturan Warm-Up & Keamanan Operasional

1. **Hormati jadwal pacing bawaan (`deploy/openwa/.env.example`):**
   - `SIMULATE_TYPING=true`
   - `SEND_PACING_WARMUP_SCHEDULE=20,40,80,160,320,640,1000`
   - `SEND_PACING_COLD_DAILY_CAP=5,10,20,40,60,80,100`
   Jangan kirim broadcast promo ke ratusan kontak baru pada hari pertama nomor ditautkan.
2. **Jangan sering logout & relink sesi:** Linking berulang dapat memicu pemeriksaan passkey atau pembatasan sementara WhatsApp.
3. **Backup data sesi:** Volume Docker `openwa_openwa-data` berisi session auth & API key OpenWA. Simpan backup-nya di lokasi terenkripsi terpisah dari backup publik.
4. **Rollback instan:** Jika OpenWA bermasalah, cukup ubah Provider Gateway di UI ERP ke Fonnte/Wablas/Custom dan klik Simpan — sistem ERP tetap berjalan normal tanpa downtime.

---

## 9. Troubleshooting Cepat

| Gejala / Error | Penyebab Umum | Solusi |
|---|---|---|
| `cat: /app/data/.api-key: No such file or directory` | Container baru menyala & masih inisialisasi SQLite/migrations. | Tunggu `docker compose ps` menunjukkan status `(healthy)`, lalu ulangi command ambil key. |
| `API key ditolak (HTTP 401)` saat 1-klik setup / cek sesi | Key terpotong saat copy, mengandung karakter kontrol SSH mobile/Termux, spasi/newline nyasar, atau mengisi Master Key ke kolom Operator Key. | Ambil ulang via `docker exec openwa-api sh -c 'cat /app/data/.api-key; echo ""'`, tempel bersih di panel **Setup Otomatis 1-Klik** (ERP otomatis membersihkan karakter tak terlihat/whitespace). |
| QR Code tidak muncul / status stuck `STARTING` | Chromium kekurangan RAM (`mem_limit` terlalu kecil) atau sesi sebelumnya rusak. | Cek `docker compose logs --tail 80 openwa-api`; pastikan minimal RAM cukup atau beralih sementara ke `ENGINE_TYPE=baileys`. |
| UI menampilkan pesan terkirim antre tetapi WA tidak masuk | `uteparts-worker` Supervisor mati / `FATAL`. | Jalankan `sudo supervisorctl status` dan `sudo supervisorctl restart uteparts-worker:*`, pastikan tidak ada error permission pada `storage/logs/`. |
| Ingin berhenti sementara dari OpenWA | Container down atau nomor terkena pembatasan. | Hentikan kontainer dengan `cd deploy/openwa && docker compose stop` dan ganti provider WA di Pengaturan ERP. |
