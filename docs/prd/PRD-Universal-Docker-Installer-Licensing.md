# Product Requirement Document (PRD)
## Universal Multi-Platform Installer, Offline Hardware-Locked Licensing & Auto-Updater (Windows WSL2/Docker, Linux, VPS, Android Termux — Zero-Server Cost & Production Ready)

- **ID Dokumen**: `PRD-INSTALLER-UNIVERSAL-06`
- **Versi**: 6.0.0
- **Status**: Phase 1 Done, Phase 2 In-Progress
- **Developer Brand**: **Bremspace** (White-Label ERP & POS System)
- **Target Runtime**: PHP 8.5+ (Docker Alpine/Debian base) & PHP 8.4/8.5 (Termux Native/PRoot)
- **Target Platform**: 
  1. PC Windows 10/11 (WSL2 Docker Engine Native — 100% Free/No License Risk, Docker Desktop Fallback)
  2. Linux Desktop & Server (Ubuntu, Debian, Arch via Docker Engine)
  3. VPS Cloud (Ubuntu, Debian, AlmaLinux via Docker Engine)
  4. Android Smartphone / Tablet Kasir (via Termux Standalone Non-Root)
  5. Multi-Device Client (iOS, iPad, Android, POS Terminal via Web Browser PWA)

---

## 1. Executive Summary & Visi Produk

### 1.1 Latar Belakang & White-Label Brand
Aplikasi ini dikembangkan di bawah brand pengembang **Bremspace**. Seluruh dokumen, installer, antarmuka perizinan, dan pesan sistem menggunakan identitas *Bremspace ERP & POS System* tanpa menggunakan merek dagang klien sebelumnya.

### 1.2 Ketentuan Kunci
1. **Windows Docker WSL2 Native (Bebas Biaya Lisensi Docker Desktop)**:
   - Menggunakan fitur resmi Windows 10/11: **WSL2 (Windows Subsystem for Linux)**.
   - Memasang Docker Engine open-source gratis di dalam WSL2 secara otomatis tanpa mewajibkan klien mengunduh Docker Desktop (menghilangkan risiko biaya lisensi komersial Docker Inc. dan menghemat ~2GB RAM).
   - Deteksi otomatis: Jika klien sudah memiliki Docker Desktop aktif, sistem langsung memanfaatkannya sebagai fallback.
2. **Developer Zero-Server Cost**: Kriptografi offline berbasis Hardware ID + 2FA Google Authenticator (TOTP RFC 6238).
3. **Dokumentasi Ramah Klien**: `PANDUAN_INSTALASI.txt` dalam bahasa non-teknis dengan identitas brand Bremspace.
4. **Aman dari Duplikasi**: 1 Kunci Aktivasi hanya berlaku untuk 1 hardware mesin.
5. **Private Repo & Auto-Update**: Klien dapat melakukan update otomatis dari repositori privat menggunakan GitHub Read-Only Token tanpa akses tulis ke akun developer.

---

## 2. Arsitektur Docker Windows: WSL2 Docker Engine vs Fallback

```
[ Klien Menjalankan start-install.bat di Windows ]
                      │
                      ▼
        Apakah Docker Desktop Aktif?
               /              \
        (Ya)  /                \ (Tidak)
             ▼                  ▼
    Gunakan Docker Desktop   Apakah WSL2 sudah aktif?
                             /               \
                       (Ya) /                 \ (Tidak)
                           ▼                   ▼
                Pasang Docker Engine     Aktifkan WSL2 via `wsl --install --no-distribution`
                di Ubuntu WSL2 internal  Instruksikan 1x restart jika diperlukan
                           │
                           ▼
             Jalankan `docker compose up -d` via WSL
```

---

## 3. Spesifikasi Arsitektur Docker (Fase 2)

### 3.1 Layanan Kontainer (`compose.yaml`)
1. **`app` (PHP 8.5+ Alpine)**:
   - Base Image: `php:8.5-alpine` (multi-stage build ultra-ringan).
   - Modul wajib: `pdo_mysql`, `pdo_sqlite`, `intl`, `mbstring`, `gd`, `zip`, `opcache`, `bcmath`, `fileinfo`, `curl`.
   - OPcache diaktifkan penuh, memory limit 512M.
2. **`web` (Nginx Alpine)**:
   - Port default: `1741:1741` (Akses lokal & multi-device LAN).
   - Port web publik: `80:80` dan `443:443` (untuk server VPS online).
3. **`db` (MySQL 8.4 LTS Local Container)**:
   - Image: `mysql:8.4` (atau `mariadb:lts`).
   - Persistensi data: Volume host `./docker/data/mysql:/var/lib/mysql`. Data aman tersimpan di folder lokal host.
   - Opsi SQLite lokal: `./database/database.sqlite` (untuk mesin dengan RAM < 2GB).
4. **`queue` (Background Worker)**:
   - Menjalankan `php artisan queue:work --sleep=3 --tries=3` di container terpisah.

---

## 4. Alur Monetisasi & Distribusi: WhatsApp Handshake (Challenge-Response)

```
[ Klien Menerima File ZIP & Ekstrak ]
  1. Membaca `PANDUAN_INSTALASI.txt`.
  2. Menjalankan installer:
     - Windows : start-install.bat (Auto-WSL2 Docker / Standalone)
     - Linux   : ./install-docker.sh
     - Android : ./install-termux.sh
  3. Skrip membaca Hardware Fingerprint (Motherboard UUID / Machine ID).
  4. Terminal menampilkan "Machine Challenge Code" & link WhatsApp otomatis:
     Contoh: `BREM-9B8A-7C12-88EF`
     Link: https://wa.me/<NO_DEV>?text=Aktivasi+Bremspace+ERP+Kode:+BREM-9B8A-7C12-88EF
           │
           │ (Klien kirim pesan WhatsApp ke Developer)
           ▼
[ Developer Bremspace di Smartphone / Laptop Sendiri (Offline) ]
  1. Menerima chat berisi `BREM-9B8A-7C12-88EF`.
  2. Buka icon PWA "Bremspace Keygen" di layar utama HP developer.
  3. Masukkan Challenge Code klien + 6-digit angka dari Google Authenticator.
  4. Klik "Generate Activation Key" -> menghasilkan `ACT-4921-8830-BC88-99A1`.
  5. Kirim balik kunci ke WhatsApp klien.
           │
           │ (Kirim via WhatsApp)
           ▼
[ Klien Aktivasi ]
  6. Klien menempelkan kunci `ACT-4921-...` ke terminal.
  7. Installer memverifikasi integritas kunci secara lokal terhadap hardware.
  8. Jika cocok: Tersimpan di `storage/license.key` dan server langsung aktif di port 1741.
```

---

## 5. Rencana Fase & Status Eksekusi

| Fase | Fokus | Status |
|---|---|---|
| **Fase 1** | Modul Lisensi Offline, Hardware Identifier, & PWA Developer Keygen | **SELESAI (Verified)** |
| **Fase 2** | Docker Engine Core (PHP 8.5+ Alpine, Nginx, MySQL Lokal, Compose, Entrypoint) | **SEKARANG DIEKSEKUSI** |
| **Fase 3** | Installer Multi-OS (Windows WSL2 Auto-Setup, Linux, VPS) | Berikutnya |
| **Fase 4** | Android Standalone (Termux Non-Root + SQLite) | Berikutnya |
| **Fase 5** | Auto-Updater GitHub CI/CD (Fine-Grained Read-Only Token) | Berikutnya |
| **Fase 6** | Web Setup Wizard & Testing Akhir | Berikutnya |
