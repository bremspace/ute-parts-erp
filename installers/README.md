# Panduan Installer & Launcher — Bremspace ERP

Seluruh file installer, launcher lokal, dan skrip deployment VPS kini dirapikan dalam satu folder `installers/` agar tidak membingungkan.

---

## Pilih Cepat (Mau Pasang di Mana?)

| Target / Kebutuhan | Platform | File yang Dijalankan |
|---|---|---|
| **Docker (Rekomendasi Lokal & Cloud)** | Windows | `installers/docker/install.bat` (atau `install.ps1`) |
| **Docker (Rekomendasi Lokal & Cloud)** | Linux / macOS / VPS | `bash installers/docker/install.sh` |
| **PC Kasir Windows (Tanpa Docker)** | Windows 10/11 | `installers/local/windows/start-install.bat` |
| **PC Kasir Windows (Unas 1-Click)** | Windows 10/11 | `installers/local/windows/unas.bat` |
| **PC Toko Hybrid Sync (XAMPP/Laragon/PHP)** | Windows | `installers/local/windows/setup-local-store-node.bat` |
| **PC Toko Hybrid Sync (Native PHP)** | Linux / macOS | `bash installers/local/linux/setup-local-store-node.sh` |
| **HP Android (Tanpa Root)** | Android Termux | `bash installers/local/android-termux/start-termux.sh` |
| **VPS Produksi Baru (Full Stack + SSL + OpenWA)** | Ubuntu 24.04 LTS | `sudo bash installers/vps/install.sh` |
| **VPS Khusus OpenWA (Terpisah)** | Ubuntu 24.04 LTS | `sudo bash installers/vps/install-openwa-only.sh` |
| **VPS Produksi (TUI Interaktif Alternatif)** | Ubuntu 22.04 / 24.04 | `sudo bash installers/vps/start-install-vps.sh` |

---

## Struktur Folder `installers/`

```text
installers/
├── README.md                          # Panduan ini
├── docker/                            # 1. Instalasi berbasis Docker (lintas OS)
├── local/                             # 2. Instalasi & launcher lokal (PC kasir / dev)
│   ├── windows/                       #    - Khusus Windows (.bat, .ps1, .vbs)
│   ├── linux/                         #    - Khusus Linux & macOS (.sh)
│   └── android-termux/                #    - Khusus HP Android via Termux (.sh)
├── vps/                               # 3. Installer, deploy & recovery VPS produksi
└── archive/                           # 4. Skrip lama/legacy (sudah digantikan)
```

---

## Penjelasan Tiap Folder & File

### 1. `installers/docker/` — Instalasi via Docker (Paling Bersih)
Cocok untuk PC kasir Windows maupun server Linux/VPS yang sudah terpasang Docker. Tidak perlu instal PHP, Composer, atau MySQL secara manual.

- `install.bat` — Klik ganda di Windows untuk menjalankan installer Docker.
- `install.ps1` — Skrip PowerShell utama untuk Docker Windows (pilih Fresh/Update, Git/Lokal, buka port LAN 80).
- `install.sh` — Skrip installer Docker untuk Linux, macOS, dan VPS.
- `Dockerfile`, `docker-compose.yml`, `entrypoint.sh`, `.env.docker`, `.dockerignore` — Definisi image, container `app` + `mysql`, queue worker, dan scheduler.
- `README.txt` — Catatan teknis Docker (reset volume, restore snapshot `.zip`, akses LAN).

### 2. `installers/local/` — Instalasi & Launcher Lokal (Non-Docker)

#### `installers/local/windows/` (Khusus Windows)
- `start-install.bat` (dan alias `install.bat`) — **Installer utama 1-klik Windows**. Otomatis cek environment, download PHP portable jika belum ada, setup DB, migrasi, dan jalankan server di port `1741`.
- `unas.bat` — **Installer 1-klik edisi Unas** (memanggil `unas-setup.ps1` dengan elevasi Administrator).
- `setup-local-store-node.bat` — Setup cepat PC toko Windows (mode Offline-First / Hybrid Sync) jika PHP sudah terpasang di PATH.
- `start_app.bat` — Menyalakan server lokal (`localhost:1741`), database portable, queue worker, scheduler, dan catch-up sync di background.
- `stop-server.bat` — Menghentikan server lokal, Docker container, queue worker, scheduler, dan database portable.
- `update-system.bat` — Memperbarui aplikasi (Docker pull/migrate atau `SystemUpdater.php`).
- `install.ps1`, `unas-setup.ps1`, `setup-windows-php.ps1` — Engine PowerShell yang dipanggil oleh file `.bat` di atas.
- `run-server-background.vbs`, `run-command-background.vbs` — Helper VBScript agar PHP server & worker berjalan di background tanpa jendela CMD terbuka.

#### `installers/local/linux/` (Khusus Linux & macOS)
- `setup-local-store-node.sh` — Setup cepat PC toko Linux/macOS (mode Offline-First / Hybrid Sync).
- `start_app.sh` — Menyalakan server lokal (`localhost:1741`), queue worker, dan Cloudflare Tunnel jika dikonfigurasi.
- `stop-server.sh` — Menghentikan server lokal, Docker container, dan queue worker.
- `update-system.sh` — Memperbarui aplikasi (Docker atau `SystemUpdater.php`).

#### `installers/local/android-termux/` (Khusus Android Termux)
- `start-termux.sh` — Launcher 1-klik di Termux (memanggil `install-termux.sh`).
- `install-termux.sh` — Memasang PHP + SQLite di Termux, verifikasi lisensi, dan menyalakan server lokal di port `1741`.

### 3. `installers/vps/` — VPS Produksi & Maintenance
- `README.md` — Panduan lengkap setup VPS baru (DNS, spesifikasi minimum, OpenWA, kredensial).
- `install.sh` — **Installer VPS utama (Ubuntu 24.04 LTS)**: Nginx, PHP-FPM 8.4, MariaDB 11.4, Redis 7, Supervisor, Cron, SSL Let's Encrypt, UFW/fail2ban, dan opsional OpenWA.
- `install-openwa-only.sh` — Pasang WhatsApp Gateway (OpenWA) di VPS terpisah.
- `start-install-vps.sh` — Alternatif installer VPS dengan TUI interaktif (Nginx + PHP 8.4 + MySQL 8.0 + Supervisor).
- `vps-install.sh` — Skrip ringkas setup VPS dasar.
- `deploy.sh` — Skrip deployment produksi dengan backup `public/build` dan auto-rollback jika gagal.
- `emergency-restore.sh` — Pemulihan darurat database MySQL dari snapshot `.sql.gz` terbaru di `storage/app/backups/`.
- `disable-staging-ai.sh` & `restore-ai-stack.sh` — Nonaktifkan/pulihkan stack AI dev di server staging.
- `config/` & `scripts/` — Template konfigurasi OpenWA, `deploy.sh.tpl`, dan library bash `lib.sh` milik `install.sh`.

### 4. `installers/archive/` — Skrip Lama (Legacy)
Berisi skrip-skrip versi terdahulu (`install-standalone.sh`, `install-ute.sh`, `setup-vps.sh`, `ute.bat`, `ute.sh`, `restore-*`, `clean-omo*`). Disimpan hanya sebagai arsip historis — **jangan gunakan untuk instalasi baru**.

---

## Catatan Folder Terkait di Luar `installers/`
Agar tidak tertukar:
- `tools/windows/`, `tools/linux/`, `tools/macos/` — Skrip **build/packaging** untuk GitHub Actions (membuat `.exe`, `.deb`/`.tar.gz`, `.dmg`). Bukan untuk dijalankan langsung oleh pengguna akhir.
- `deploy/` — File konfigurasi layanan server (`supervisor/`, `logrotate/`, `openwa/`).
- `scripts/ai/` — Launcher alat bantu AI developer (`ai.sh`, `ai.bat`, `opencode.sh`, `opencode.bat`).
- `scripts/pasang-bre.sh` — Setup environment AI agent harness (`bash scripts/pasang-bre.sh`).
