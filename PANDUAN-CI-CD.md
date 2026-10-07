# Panduan Setup CI/CD GitHub Actions (Test Otomatis & Auto-Deploy VPS Root)

File workflow utama terletak di: `.github/workflows/deploy.yml`

---

## 1. Bagaimana Sistem CI/CD Ini Bekerja?

Pipeline terbagi menjadi 2 tahapan berurutan:

```
Push / Merge ke branch 'main'
             │
             ▼
   [ TAHAP 1: CI (Test) ]
   ├── Setup PHP 8.4 & Node.js 20
   ├── npm ci & build assets
   ├── composer install
   └── php artisan test
             │
             ├── ❌ JIKA TEST GAGAL:
             │    ├── Pipeline BERHENTI seketika.
             │    ├── Auto-Deploy TIDAK AKAN DIJALANKAN.
             │    └── Server Production 100% AMAN dari bug/kode rusak.
             │
             └── ✅ JIKA TEST BERHASIL:
                  ▼
         [ TAHAP 2: CD (Deploy) ]
         ├── Akses SSH ke VPS root
         ├── git reset --hard origin/main
         ├── composer install --no-dev & migrate DB
         ├── npm build & update cache
         └── restart worker queue (supervisor)
```

---

## 2. Langkah Pengisian GitHub Repository Secrets

Agar GitHub Actions bisa mengakses server VPS untuk deploy otomatis, tambahkan kredensial berikut di repository GitHub:

1. Buka repository: **https://github.com/bremspace/ute-parts-erp**
2. Klik tab **Settings** (di pojok kanan atas repo).
3. Di menu sebelah kiri, buka **Secrets and variables** -> klik **Actions**.
4. Klik tombol **New repository secret** untuk setiap entri di bawah ini:

| Nama Secret | Nilai (Value) | Keterangan |
| :--- | :--- | :--- |
| **`VPS_HOST`** | `66.42.48.27` | IP Publik VPS target |
| **`VPS_USER`** | `root` | User server tujuan eksekusi |
| **`VPS_PORT`** | `22` | Port SSH server (default 22) |
| **`VPS_PATH`** | `/var/www/test.uteparts.id-staging` | Path direktori aplikasi di VPS |
| **`VPS_SSH_PRIVATE_KEY`** | *(Lihat Cara Buat Kunci di Bawah)* | Private Key SSH untuk login tanpa password |

---

## 3. Cara Menyiapkan Kunci SSH (`VPS_SSH_PRIVATE_KEY`)

Jika belum memiliki SSH Key khusus GitHub Actions di VPS:

1. Login ke server VPS via SSH sebagai `root`.
2. Jalankan perintah pembuatan key baru:
   ```bash
   ssh-keygen -t ed25519 -C "github-actions-deploy" -f /root/.ssh/github_actions -N ""
   ```
3. Daftarkan public key ke daftar authorized keys server:
   ```bash
   cat /root/.ssh/github_actions.pub >> /root/.ssh/authorized_keys
   chmod 600 /root/.ssh/authorized_keys
   ```
4. Tampilkan private key dan salin seluruh teksnya:
   ```bash
   cat /root/.ssh/github_actions
   ```
5. Salin output tersebut mulai dari baris:
   ```text
   -----BEGIN OPENSSH PRIVATE KEY-----
   ... (isi key) ...
   -----END OPENSSH PRIVATE KEY-----
   ```
6. Buka kembali GitHub, buat secret baru dengan nama **`VPS_SSH_PRIVATE_KEY`**, lalu tempel teks kunci tersebut.

---

## 4. Pengujian & Verifikasi

Setelah semua secrets tersimpan:
1. Lakukan perubahan kode atau push commit ke branch `main`.
2. Buka tab **Actions** di repositori GitHub.
3. Anda akan melihat alur kerja **CI/CD Pipeline (Test & Auto-Deploy)**:
   - Job **Run Automated Tests** akan berjalan terlebih dahulu.
   - Setelah centang hijau, job **Deploy ke VPS Production (Root)** otomatis berjalan dan mengeksekusi pembaruan di server VPS.
