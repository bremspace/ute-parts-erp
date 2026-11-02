# PANDUAN-PEMULIHAN-DARURAT.md
## SOP Pemulihan Database & Mitigasi Bencana (Disaster Recovery)

Dokumen ini adalah panduan cepat langkah demi langkah jika database down, hilang, terkena ransomware, atau sistem tidak bisa diakses, bahkan ketika Anda tidak bisa membuka Backoffice maupun OpenCode.

---

### 1. Jalur Pemulihan Otomatis (Tanpa Tindakan / Zero-Touch)
Sistem memiliki pengawas mandiri di level sistem operasi (Crontab):
- Perintah: `php artisan ute:db-health-check --auto-restore` berjalan **tiap 10 menit**.
- Jika tabel hilang atau corrupt, sistem otomatis mendeteksi dan mengimpor file backup terbaru di `storage/app/backups/`.
- Cukup tunggu maksimal 10 menit, sistem akan pulih dengan sendirinya.

---

### 2. Jalur Pemulihan Kilat 1 Perintah (Via SSH / Terminal / Web Console VPS)
Jika Anda membuka terminal VPS (dari laptop maupun HP):

Cukup jalankan:
```bash
./emergency-restore.sh
```
*(Atau `sudo bash emergency-restore.sh`)*

**Apa yang dilakukan skrip ini secara otomatis?**
1. Memastikan service MySQL aktif (`systemctl start mysql`).
2. Mencari berkas cadangan `.sql.gz` terbaru di `storage/app/backups/`.
3. Membuat ulang database jika hilang.
4. Mengimpor seluruh tabel secara instan (100+ tabel kembali utuh dalam hitungan detik).

---

### 3. Jalur Pemulihan Melalui Web Browser (Tanpa SSH)
Jika tidak memiliki akses terminal:
1. Buka halaman web toko di browser.
2. Jika database terputus, akan muncul layar darurat:
   **"Layanan MySQL Tidak Terhubung" (Error 503)**.
3. Klik tombol **"Buka Setup Wizard / Ganti Database →"** (atau langsung akses `/install.php`).
4. Pada form database:
   - Pilih **SQLite** jika ingin langsung online tanpa MySQL.
   - Atau klik **Test Koneksi / Auto-Create Database** untuk inisialisasi ulang.

---

### 4. Pencegahan & Mitigasi Keamanan (Agar Tidak Terulang)
1. **Port 3306 Wajib Tertutup untuk Publik**:
   - MySQL hanya boleh mendengar di `127.0.0.1` (lokal server).
   - Verifikasi firewall dengan: `sudo ufw status`. Pastikan port 3306 **TIDAK ADA** di daftar `ALLOW`.
2. **Kredensial Database**:
   - Jangan pernah menggunakan user `root@%` atau user tanpa password.
   - Hak akses database hanya untuk `@localhost` atau `@127.0.0.1`.
3. **Penyimpanan Salinan Backup**:
   - Seluruh dump tersimpan di `storage/app/backups/` (rotasi otomatis jam 02:00 dan 13:00 WIB).
   - Sangat disarankan mengunduh berkas `.sql.gz` tersebut secara berkala ke Google Drive / komputer lokal sebagai cadangan offsite.
