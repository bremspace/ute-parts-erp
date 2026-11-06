# Product Requirement Document (PRD)
## Komersialisasi On-Premise Multi-OS: Proteksi Source Code, Distribusi Standalone, Jaringan Multi-Perangkat (LAN/WiFi), & Integrasi Custom Domain

- **ID Dokumen**: `PRD-ONPREMISE-DISTRIBUTION-01`
- **Versi**: 1.0.0
- **Status**: DRAFT FOR ARCHITECTURAL REVIEW
- **Tanggal**: 2026-11-06
- **Brand Produk**: **Bremspace ERP** (Commercial Offline-First & Online-Ready Edition)
- **Target OS**: 
  - **Windows**: Windows 10 (Build 1809+) & Windows 11 (x64) via Single-Click `.exe` Installer
  - **Linux**: Ubuntu 22.04+/Debian 12/CentOS Stream (x64) via AppImage / Self-Contained Binary
  - **macOS**: macOS 12 Monterey+ (Apple Silicon M1/M2/M3/M4 & Intel x64) via `.dmg` Bundle
- **Target Pengguna**: Pemilik Toko Ritel, Bengkel, Grosir, UMKM yang membeli lisensi mandiri (On-Premise) tanpa biaya bulanan wajib cloud, namun tetap membutuhkan fitur lengkap (Web Konsumen, POS Kasir Multi-Device, Backoffice, Custom Domain).

---

## 1. Executive Summary & Visi Produk

### 1.1. Latar Belakang Komersial
Bremspace ERP dirancang untuk dijual sebagai produk software mandiri (packaged product / per-license sale):
1. **Instalasi Lokal Mandiri**: Klien menginstal aplikasi di perangkat fisik mereka (PC kasir, laptop owner, atau mini PC server toko). Semua database dan file operasional berada di disk lokal klien agar dapat beroperasi penuh secara offline tanpa internet.
2. **Kekhawatiran Kebocoran Kekayaan Intelektual (Source Code / IP)**: Laravel dan PHP secara alamiah adalah skrip teks terbuka (*plaintext*). Jika didistribusikan langsung ke PC klien tanpa proteksi, klien dapat dengan mudah mengintip logika bisnis, algoritma komisi, mem-bypass verifikasi lisensi, atau menggandakan software tanpa izin (pembajakan).
3. **Kebutuhan Akses Multi-Device & Online**: Meskipun berjalan di PC lokal toko, sistem harus dapat:
   - Diakses dari smartphone, tablet, dan PC staf lain di jaringan WiFi/LAN toko yang sama (tanpa perlu install aplikasi di setiap gadget).
   - Memiliki **Storefront Web Konsumen** yang bisa diakses oleh pelanggan dari luar toko melalui internet.
   - Menggunakan **Domain Sendiri (Custom Domain)** milik klien (cth: `tokoberkah.com` atau `pos.tokoberkah.id`) dengan SSL HTTPS otomatis tanpa memerlukan IP publik statis atau konfigurasi router/NAT yang rumit.

### 1.2. Status Eksisting Arsitektur (Sudah Ada vs Kebutuhan Baru)
Audit menyeluruh menunjukkan fondasi proyek sudah sangat solid (~75% telah terimplementasi):
- ✅ **Runtime Desktop Windows**: Bundling Inno Setup, Go Launcher Mutex, PHP 8.4 NTS, Caddy Server, MariaDB 11.4 Portable, dan Edge WebView2 Shell sudah selesai dan diverifikasi di CI/CD (`.github/workflows/build-windows-exe.yml`).
- ✅ **Sistem Lisensi Offline**: `HardwareIdentifier.php` dan `LicenseVerifier.php` berbasis SHA-256 machine hash sudah terpasang dan aktif di middleware `EnsureLicenseValid.php`.
- ✅ **Manajemen Jaringan & Domain**: `NetworkManagementService.php` dan `SettingsRbac.php` sudah memiliki deteksi IP LAN, konfigurasi Cloudflare Tunnel, dan validasi domain kustom.
- ✅ **Auto-Update Klien**: `AppUpdateService.php` sudah mampu melakukan sinkronisasi patch via GitHub REST API tanpa merusak database lokal.

**Yang Perlu Disempurnakan & Ditambahkan (Delta Scope):**
1. **Pipeline Enkripsi Bytecode / Hardening Source Code**: Mencegah dekompilasi dan pembacaan kode PHP di sisi klien.
2. **Multi-Device Binding (LAN/WiFi 0.0.0.0)**: Membuka binding web server lokal ke seluruh antarmuka jaringan dan menambahkan generator QR Code di dashboard/launcher.
3. **Zero-Config Custom Domain & Tunneling**: Mengintegrasikan daemon reverse-tunnel otomatis agar custom domain milik klien langsung aktif ke PC lokal dengan instruksi CNAME otomatis.
4. **Distribusi Linux & macOS**: Memperluas skema packaging ke format AppImage / DMG dengan proteksi serupa.

---

## 2. Arsitektur Distribusi & Keamanan

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                   CLIENT HARDWARE (LOCAL PC)                           │
│                                                                                        │
│   ┌────────────────────────────────────────────────────────────────────────────────┐   │
│   │                        Bremspace Launcher & Supervisor                         │   │
│   │               (Go Binary - Mutex, Anti-Zombie, Tunnel Daemon, LAN IP)          │   │
│   └───────────────┬───────────────────────────────┬────────────────────────────────┘   │
│                   │                               │                                    │
│   ┌───────────────▼──────────────┐ ┌──────────────▼──────────────┐                     │
│   │    Local Caddy Web Server    │ │   MariaDB Portable (13307)  │                     │
│   │    Bind: 0.0.0.0:1741        │ │   Data: %LOCALAPPDATA%      │                     │
│   └───────────────┬──────────────┘ └─────────────────────────────┘                     │
│                   │ FastCGI                                                            │
│   ┌───────────────▼────────────────────────────────────────────────────────────────┐   │
│   │           PHP 8.4 Runtime Engine + Loader Ekstensi Terenkripsi                 │   │
│   │   ┌────────────────────────────────────────────────────────────────────────┐   │   │
│   │   │ ENCRYPTED BYTECODE PAYLOAD (SourceGuardian / ionCube / Native DLL)    │   │   │
│   │   │ ├── app/ (Logika Bisnis & Controllers: Binary Terenkripsi)             │   │   │
│   │   │ ├── routes/ & bootstrap/ (Terenkripsi, Terikat Hardware ID)            │   │   │
│   │   │ └── core_engine.dll (Lisensi & Signature: Native Go/Rust Machine Code) │   │   │
│   │   └────────────────────────────────────────────────────────────────────────┘   │   │
│   └────────────────────────────────────────────────────────────────────────────────┘   │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
               ┌────────────────────────────┴───────────────────────────┐
               │                                                        │
               ▼                                                        ▼
┌──────────────────────────────┐                         ┌──────────────────────────────┐
│  Jaringan Lokal (LAN / WiFi) │                         │  Jaringan Internet / Online  │
│  - HP / Tablet Kasir         │                         │  - Storefront Web Konsumen   │
│  - Dashboard Owner di Laptop │                         │  - Custom Domain Klien       │
│  URL: http://192.168.1.50:1741                         │  URL: https://tokobudi.com   │
└──────────────────────────────┘                         └──────────────────────────────┘
```

---

## 3. Matriks Celah Keamanan SC & Mitigasi Pasti (Anti-Tamper & Anti-Theft)

| ID | Celah Keamanan / Risiko | Akar Masalah | Solusi Pasti & Implementasi Teknis |
|---|---|---|---|
| **SEC-01** | **Inspeksi Source Code PHP** | File `.php` berupa teks terbuka di folder instalasi klien. Klien/teknisi dapat mengintip algoritma dan struktur database. | **Bytecode Encryption (SourceGuardian / ionCube)**: Pada pipeline CI/CD rilis, seluruh file PHP di folder `app/`, `routes/`, dan `config/` dikompilasi menjadi bytecode terenkripsi. File teks digantikan oleh binary acak yang hanya bisa dieksekusi dengan loader resmi. |
| **SEC-02** | **Bypass Verifikasi Lisensi (Patching Code)** | Klien mengedit `EnsureLicenseValid.php` dan mengubah `return $next($request)` secara langsung untuk mematikan pengecekan lisensi. | **Native FFI License Validator**: Logika validasi tanda tangan lisensi RSA/Ed25519 dan verifikasi hardware dipindahkan ke file biner mesin terkompilasi (`core_engine.dll` di Windows, `.so` di Linux, `.dylib` di Mac). PHP memanggil fungsi validasi lewat ekstensi PHP FFI. Jika binary FFI dimanipulasi, checksum internal gagal dan aplikasi menolak start. |
| **SEC-03** | **Duplikasi Aplikasi ke PC Lain (Cloning Disk)** | Klien menyalin folder aplikasi atau mengkloning disk ke komputer cabang lain tanpa membeli lisensi tambahan. | **Hardware ID Lock**: Kunci lisensi mengikat hash spesifik komponen keras: <br>- Windows: `Win32_ComputerSystemProduct.UUID` + Motherboard Serial<br>- Linux: `/etc/machine-id` + CPU Serial<br>- macOS: `IOPlatformUUID` via `ioreg`<br>File lisensi dienkripsi dengan public key server dan didekripsi menggunakan challenge code perangkat. Jika dipindah, aplikasi langsung terkunci. |
| **SEC-04** | **Pencurian Basis Data Pelanggan & Transaksi** | File MariaDB `.ibd` disalin langsung oleh pihak yang memiliki akses fisik ke komputer. | **InnoDB Tablespace Encryption**: Mengaktifkan modul enkripsi bawaan MariaDB (`file_key_management`) di `my.ini` dengan key rotasi lokal yang terikat ke UUID mesin. |
| **SEC-05** | **Bocoran File Konfigurasi Sensitif (.env)** | Kunci API (Duitku, Biteship, WhatsApp gateway) di `.env` terlihat oleh pengguna PC. | **Encrypted Environment Store**: String kredensial sensitif dienkripsi di tingkat database menggunakan Laravel Encrypter (`APP_KEY` hardcoded/obfuscated di launcher biner), bukan disimpan dalam bentuk plaintext di disk. |
| **SEC-06** | **Manipulasi Waktu Sistem (Rollback Clock)** | Memundurkan tanggal OS Windows/Linux untuk memperpanjang masa aktif trial atau lisensi periodik. | **Monotonic Timestamp High-Water Mark**: Sistem merekam timestamp transaksi terakhir (`last_known_timestamp`) di DB terenkripsi. Jika `current_time < last_known_timestamp`, sistem menolak transaksi dan meminta kalibrasi waktu via NTP internet. |

---

## 4. Akses Multi-Device Jaringan Lokal (LAN / Wi-Fi Toko)

### 4.1. Kebutuhan Operasional
PC kasir utama bertindak sebagai "Server Lokal Toko". Pelayan restoran, kasir kedua, atau staf gudang harus bisa membuka aplikasi dari HP, tablet Android/iPad, atau laptop lain tanpa perlu menginstal aplikasi.

### 4.2. Spesifikasi Implementasi
1. **Binding Antarmuka Jaringan Caddy**:
   - `Caddyfile.desktop` diubah dari:
     ```caddy
     :{{APP_PORT}} {
         bind 127.0.0.1
     ```
     menjadi:
     ```caddy
     :{{APP_PORT}} {
         bind 0.0.0.0
     ```
2. **Auto-Detection IP Lokal**:
   - Saat `launcher.exe` dijalankan, sistem secara otomatis mendeteksi IP lokal mesin (misal: `192.168.1.50` atau `10.0.0.15`).
   - `launcher.exe` secara otomatis menambahkan rule Firewall Windows (`netsh advfirewall firewall add rule name="BremspaceERP" dir=in action=allow protocol=TCP localport=1741`) pada saat instalasi awal.
3. **QR Code Sharing di Layar Utama**:
   - Pada pojok atas Backoffice dan jendela info Launcher, tampil tombol: **"Buka di HP/Tablet"**.
   - Saat diklik, muncul pop-up berisi **QR Code** yang memuat URL: `http://192.168.1.50:1741/app`.
   - Staf toko cukup membuka kamera smartphone mereka dan memindai QR Code untuk langsung login.

---

## 5. Integrasi Custom Domain Klien (Akses Online Tanpa IP Publik)

### 5.1. Masalah Konektivitas Internet Toko
Sebagian besar toko ritel menggunakan internet rumahan (IndiHome, FirstMedia, MyRepublic, Starlink, atau modem seluler) yang:
- Memiliki **IP Dinamis** (berubah setiap restart).
- Berada di balik **CGNAT** (tidak bisa melakukan port-forwarding di router).
- Memerlukan konfigurasi yang sepenuhnya otomatis tanpa menyusahkan pemilik toko.

### 5.2. Solusi: Asisten Pointing & Cloudflare Tunnel Terintegrasi
Fitur ini memperluas fungsi `NetworkManagementService.php` yang sudah ada:

#### Alur Pengguna (User Flow):
1. **Pendaftaran Domain di Backoffice**:
   - Pemilik toko membuka menu **Pengaturan** -> **Jaringan & Domain**.
   - Mengisi nama domain yang telah mereka beli: `pos.tokoberkah.com`.
2. **Petunjuk Otomatis (DNS Assistant)**:
   - Sistem menghasilkan identitas tunnel unik untuk instalasi toko tersebut.
   - Sistem menampilkan kartu panduan ringkas:
     > **Langkah Pointing Domain Anda:**
     > 1. Buka dashboard tempat Anda membeli domain (Niagahoster, Cloudflare, Domainesia, dll).
     > 2. Tambahkan 1 DNS Record berikut:
     >    - **Type**: `CNAME`
     >    - **Name / Subdomain**: `pos`
     >    - **Target / Value**: `tunnel-xyz123.bremspace.id` (atau Cloudflare Tunnel endpoint).
3. **Verifikasi Otomatis (1-Klik)**:
   - Pengguna menekan tombol **"Verifikasi Pointing Domain"**.
   - Sistem di background mengeksekusi `dns_get_record()` untuk mengecek apakah CNAME sudah mengarah dengan benar.
   - Jika valid: sistem otomatis mengaktifkan daemon `cloudflared` di launcher PC lokal dan mengubah `APP_URL` ke `https://pos.tokoberkah.com`.
4. **Otomatisasi SSL HTTPS**:
   - Sertifikat SSL aktif otomatis di level tunnel edge (gratis selamanya, gembok hijau, aman untuk Webhook pembayaran Duitku dan transaksi Web Konsumen).

### 5.3. Pemisahan Jalur Akses (Routing Web Konsumen vs Backoffice)
- **Akses Publik (Storefront)**: `https://pos.tokoberkah.com/` -> Menampilkan katalog produk, keranjang belanja, cek pesanan servis, dan form kontak konsumen.
- **Akses Staf (Backoffice)**: `https://pos.tokoberkah.com/app` -> Memerlukan autentikasi pengguna, otorisasi RBAC Spatie, dan opsi pembatasan IP / 2FA.

---

## 6. Strategi Rilis Multi-OS (Windows, Linux, macOS)

### 6.1. Windows Standalone (`.exe`) — Status: SUDAH IMPLEMENTED
- **Installer**: Inno Setup (`BremspaceERP-Setup-vX.Y.Z.exe`).
- **Engine**: Embedded MariaDB Portable (port 13307), PHP 8.4 NTS, Caddy FastCGI, Go Launcher.
- **Shell**: Microsoft Edge WebView2 Evergreen (kiosk / single window mode).

### 6.2. Linux Standalone (AppImage / Standalone Folder)
- **Target**: Ubuntu 22.04+, Debian 12, Linux Mint, POSBox Linux.
- **Engine**: PHP 8.4 CLI/FPM static binary + MariaDB embedded + Caddy Linux x64.
- **Packaging**: AppImage mandiri atau arsip `.tar.gz` dengan skrip systemd unit untuk auto-start saat PC booting.
- **Machine ID**: Memanfaatkan `/etc/machine-id` yang di-hash bersama salt lisensi.

### 6.3. macOS Standalone (`.app` / `.dmg`)
- **Target**: macOS 12+ (Apple Silicon M1/M2/M3/M4 & Intel).
- **Engine**: PHP 8.4 NTS Darwin + MariaDB Portable macOS + Caddy Darwin.
- **Shell**: Native WebKit Wrapper via Go (`webview_darwin`) atau PWA Chrome/Safari app mode.
- **Machine ID**: Memanfaatkan `IOPlatformUUID` via query `ioreg -rd1 -c IOPlatformExpertDevice`.

---

## 7. Rencana Kerja Implementasi & Roadmap Bertahap

| Fase | Fokus Pekerjaan | Deliverables & Acceptance Criteria |
|---|---|---|
| **Fase 1** | **Multi-Device LAN Binding & QR Code** | - Update `Caddyfile.desktop` bind ke `0.0.0.0`.<br>- Tambahkan widget "Buka di Smartphone / Tablet" dengan QR Code di Backoffice.<br>- Verifikasi akses via HP/tablet di jaringan WiFi lokal yang sama. |
| **Fase 2** | **Asisten Pointing Custom Domain & Tunnel Service** | - Sempurnakan UI tab Jaringan di `SettingsRbac.php` dengan wizard CNAME & DNS verifier.<br>- Integrasikan binary `cloudflared` ke dalam installer payload dan supervisor launcher.<br>- Verifikasi pointing domain kustom dan auto SSL HTTPS. |
| **Fase 3** | **Pipeline Enkripsi Bytecode (Proteksi SC)** | - Konfigurasi build step enkripsi PHP (SourceGuardian / ionCube) di GitHub Actions CI/CD.<br>- Buat fallback core licensing DLL (Go/Rust via PHP FFI) untuk proteksi anti-bypass.<br>- Validasi bahwa file di folder `app/` yang terpasang di klien tidak terbaca teksnya namun berfungsi 100% normal. |
| **Fase 4** | **Packaging Standalone Linux & macOS** | - Buat skrip build untuk bundle Linux (AppImage) dan macOS (`.dmg`).<br>- Uji coba aktivasi lisensi hardware pada mesin Linux & Mac.<br>- Rilis binary di GitHub Releases bersama paket Windows. |

---

## 8. Verifikasi & Definisi Keberhasilan (Definition of Done)

1. **Proteksi Kode**: File di dalam direktori instalasi klien tidak mengandung kode sumber PHP yang dapat dibaca manusia (tidak dapat diedit dengan text editor biasa).
2. **Kemandirian Operasional**: Aplikasi dapat berjalan penuh saat kabel LAN dicabut / tanpa internet (transaksi kasir, cetak struk thermal, cek stok, backup database).
3. **Multi-Perangkat**: Kasir dan staf dapat membuka sistem dari smartphone Android / iOS di jaringan WiFi toko secara responsif.
4. **Custom Domain**: Domain milik klien dapat dihubungkan ke PC toko lokal dalam waktu kurang dari 5 menit menggunakan asisten DNS CNAME terintegrasi.
5. **Zero Zombie Process**: Saat aplikasi ditutup, semua proses pendukung (PHP, Caddy, MariaDB, Tunnel) mati sempurna tanpa meninggalkan proses liar di memori.
