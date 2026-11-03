# Product Requirement Document (PRD)
## Universal Multi-Platform Installer, Offline Hardware-Locked Licensing & Auto-Updater (Windows, Linux, VPS, Android Termux — Zero-Server Cost & Production Ready)

- **ID Dokumen**: `PRD-INSTALLER-UNIVERSAL-05`
- **Versi**: 5.0.0
- **Status**: Ready for Implementation
- **Target Runtime**: PHP 8.5+ (Docker Alpine/Debian base) & PHP 8.4/8.5 (Termux Native/PRoot)
- **Target Platform**: 
  1. PC Windows 10/11 (Localhost / LAN Kasir via Docker)
  2. Linux Desktop & Server (Ubuntu, Debian, Arch via Docker)
  3. VPS Cloud (Ubuntu, Debian, AlmaLinux via Docker)
  4. Android Smartphone / Tablet Kasir (via Termux Standalone Non-Root)
  5. Multi-Device Client (iOS, iPad, Android, POS Terminal via Web Browser PWA)

---

## 1. Executive Summary & Visi Produk

### 1.1 Latar Belakang
Aplikasi ERP & POS UteParts dibangun di atas Laravel 13, Livewire 3, dan Tailwind 4. Distribusi komersial memerlukan sistem yang melindungi hak cipta pengembang, tidak membutuhkan biaya server lisensi, mudah diinstal oleh orang awam melalui file ZIP, dan tetap mendukung pembaruan kode otomatis (CI/CD) dari repositori privat.

### 1.2 Ketentuan Kunci
1. **Developer Zero-Server Cost**: Menggunakan enkripsi offline berbasis Hardware ID + Google Authenticator (TOTP RFC 6238).
2. **Setup 2FA Developer**: Tool developer menyertakan generator QR Code otomatis untuk pairing instan ke aplikasi Google Authenticator sekali seumur hidup.
3. **Dokumentasi Ramah Klien**: Di dalam paket ZIP klien disediakan `PANDUAN_INSTALASI.txt` dalam bahasa non-teknis.
4. **Aman dari Duplikasi**: 1 Kunci Aktivasi hanya berlaku untuk 1 motherboard/hardware mesin.
5. **Private Repo & Auto-Update**: Klien dapat melakukan update otomatis dari repositori privat menggunakan GitHub Read-Only Token tanpa akses tulis ke akun developer.

---

## 2. Pendaftaran Awal 2FA Developer (Google Authenticator Setup)

### 2.1 Alur Pairing Sekali Seumur Hidup
1. Developer membuka file `tools/license/developer-keygen.html` di browser HP atau laptop.
2. Tool menyediakan tab khusus: **"Setup 2FA Developer"**.
3. Layar menampilkan:
   - **QR Code Interaktif** berstandar `otpauth://totp/UteParts-Licensing?...`.
   - **Secret Seed Base32** (kode teks cadangan).
4. Developer membuka aplikasi **Google Authenticator** di smartphone:
   - Tekan icon `+` $\rightarrow$ pilih *"Scan a QR code"*.
   - Arahkan kamera ke layar. Akun `UteParts ERP Licensing` langsung terdaftar dan mulai menghasilkan 6-digit angka setiap 30 detik.
5. Secret Seed ini disimpan secara persisten di `localStorage` browser developer (dan dapat di-backup).

---

## 3. Alur Monetisasi & Distribusi: WhatsApp Handshake (Challenge-Response)

```
[ Klien Menerima File ZIP & Ekstrak ]
  1. Membaca `PANDUAN_INSTALASI.txt`.
  2. Menjalankan installer:
     - Windows : start-install.bat
     - Linux   : ./install-docker.sh
     - Android : ./install-termux.sh
  3. Skrip membaca Hardware Fingerprint (Motherboard UUID / Machine ID).
  4. Terminal menampilkan "Machine Challenge Code" & link WhatsApp otomatis:
     Contoh: `UTE-9B8A-7C12-88EF`
     Link: https://wa.me/<NO_DEV>?text=Aktivasi+ERP+Kode:+UTE-9B8A-7C12-88EF
           │
           │ (Klien kirim pesan WhatsApp ke Developer)
           ▼
[ Developer di Smartphone / Laptop Sendiri (Offline) ]
  1. Menerima chat berisi `UTE-9B8A-7C12-88EF`.
  2. Buka icon PWA "UteParts Keygen" di layar utama HP developer.
  3. Masukkan:
     - Machine Challenge Code klien
     - 6-digit angka dari Google Authenticator saat itu
  4. Klik "Generate Activation Key" $\rightarrow$ menghasilkan: `ACT-4921-8830-BC88-99A1`.
  5. Kirim balik kunci ke WhatsApp klien.
           │
           │ (Kirim via WhatsApp)
           ▼
[ Klien Aktivasi ]
  6. Klien menempelkan kunci `ACT-4921-...` ke terminal.
  7. Installer memverifikasi integritas kunci secara lokal terhadap hardware.
  8. Jika cocok: Tersimpan di `storage/license.key` dan server langsung aktif di port 1741.
  9. Jika file dipindahkan ke PC lain: Hardware tidak cocok $\rightarrow$ sistem terkunci otomatis.
```

---

## 4. Paket Rilis Klien & Berkas Panduan (PANDUAN_INSTALASI.txt)

Di dalam file ZIP rilis yang diunduh klien:
```
UteParts-ERP-Release.zip
├── PANDUAN_INSTALASI.txt              # Buku panduan bahasa Indonesia ramah orang awam
├── start-install.bat                  # 1-Klik Launcher Windows
├── start-install.sh                   # 1-Klik Launcher Linux / VPS
├── start-termux.sh                    # 1-Klik Launcher Android Termux
├── compose.yaml                       # Konfigurasi container Docker
├── Dockerfile                         # Engine runtime PHP 8.5+ Alpine
├── docker/                            # Nginx & PHP configurations
├── scripts/license/                   # Modul pemeriksa lisensi hardware
└── app, resources, public, routes     # Source code aplikasi (tanpa .git sensitif)
```

---

## 5. Spesifikasi Teknis Platform

| Platform | Runtime | Database | Karakteristik |
|---|---|---|---|
| **Windows 10/11** | Docker Desktop (PHP 8.5+ Alpine) | MySQL Container / SQLite | Setup terpandu WSL2, auto-restart port 1741 |
| **Linux Desktop/Server** | Docker Engine (PHP 8.5+ Alpine) | MySQL Container / SQLite | Auto-install 100% via get.docker.com |
| **VPS Cloud** | Docker Engine (PHP 8.5+ Alpine) | MySQL Container / SQLite | Auto-swap 2GB untuk VPS 1GB RAM, auto SSL |
| **Android Smartphone** | Termux Standalone (PHP 8.4/8.5) | SQLite Lokal | Non-root, hemat memori, auto-launch Chrome |
| **Client Devices (iOS/Tab)** | Web Browser (PWA) | Menginduk ke Server | Add to Home Screen, tanpa instalasi engine |

---

## 6. Auto-Update CI/CD Berbasis GitHub Read-Only Token

- Aplikasi dilengkapi modul `SystemUpdater`:
  1. Terhubung ke GitHub API privat menggunakan Fine-Grained Token (Read-Only).
  2. Mengecek apakah ada commit / rilis baru di cabang `main`.
  3. Klien mengklik tombol **"Perbarui Sistem"** di antarmuka web.
  4. Lisensi diverifikasi ulang $\rightarrow$ kode ditarik $\rightarrow$ `artisan migrate --force` $\rightarrow$ `optimize:clear`.
  5. Data transaksi, pengaturan toko, dan lisensi tidak tertimpa.

---

## 7. Rencana Deliverables Fase 1 (Siap Dikerjakan)

1. **`tools/license/developer-keygen.html`**:
   - Single-file PWA offline dengan antarmuka elegan.
   - Tab 1: Setup 2FA Google Authenticator (Generate QR Code & Secret Seed).
   - Tab 2: Generator Lisensi (Input Machine ID + Input 6-Digit Google Auth $\rightarrow$ Output Activation Key).
2. **`scripts/license/HardwareIdentifier.php`**:
   - Ekstraktor identitas hardware unik (Motherboard UUID di Windows, Machine-ID di Linux, Android ID di Termux).
3. **`scripts/license/LicenseVerifier.php`**:
   - Validator kriptografis offline di komputer klien.
4. **`PANDUAN_INSTALASI.txt`**:
   - Panduan instalasi bahasa Indonesia non-teknis yang siap disertakan dalam paket ZIP klien.
