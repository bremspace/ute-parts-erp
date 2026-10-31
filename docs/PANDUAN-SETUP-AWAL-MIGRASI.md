# PANDUAN LENGKAP SETUP AWAL & MIGRASI DARI APLIKASI LAMA (FULL WIPE KE GO-LIVE)

Dokumen panduan operasional teknis & bisnis untuk inisialisasi ulang Ute Parts ERP dari database bersih (*full wipe*) hingga sistem kasir POS siap digunakan bertransaksi.

---

## 1. Persiapan Infrastruktur & Environment (.env)

### 1.1 Persyaratan Sistem
- PHP 8.3+ (ekstensi: `pdo_mysql`, `mbstring`, `bcmath`, `xml`, `zip`, `gd`, `intl`)
- MySQL 8.0+ / MariaDB 10.6+
- Node.js 20+ & NPM
- Queue Worker aktif (Supervisor / CLI) — **RAM 1GB wajib driver database**

### 1.2 Konfigurasi File `.env`
Salin template dan konfigurasi parameter inti:
```bash
cp .env.example .env
php artisan key:generate
```

Pastikan variabel utama terkonfigurasi:
```ini
APP_NAME="Ute Parts ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://test.uteparts.id

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=uteparts_db
DB_USERNAME=uteparts_user
DB_PASSWORD="PasswordKuatDb123!"

# Queue & Cache (Wajib database utk VPS 1GB)
QUEUE_CONNECTION=database
CACHE_STORE=file
SESSION_DRIVER=database

# Payment & Courier Gateway (Opsional untuk toko fisik, wajib untuk marketplace)
DUITKU_ENV=sandbox
DUITKU_MERCHANT_CODE=
DUITKU_API_KEY=
BITESHIP_API_KEY=

# Notifikasi WA
WA_GATEWAY_PROVIDER=fonnte
FONNTE_TOKEN=
```

### 1.3 Folder Storage & Build Aset
```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## 2. Full Wipe Database & Skema Bersih

Jalankan penghapusan penuh dan build ulang seluruh tabel (15 modul):
```bash
php artisan migrate:fresh --force
```

---

## 3. Eksekusi Seeder Pondasi (Master Data Wajib)

> **PERINGATAN:** Jangan jalankan `php artisan db:seed` default karena menyertakan data dummy/transaksi demo (`TransaksiDemoSeeder`, `ReportSeeder`, dummy produk). Jalankan **HANYA** seeder pondasi arsitektur berikut:

```bash
# 1. RBAC (Roles & 70+ Permissions Spatie)
php artisan db:seed --class="Database\Seeders\RolesAndPermissionsSeeder" --force

# 2. Struktur Cabang & Gudang Default (Wajib: semua data terikat cabang_id)
php artisan db:seed --class="Database\Seeders\CabangSeeder" --force

# 3. Chart of Accounts Akuntansi (Wajib: JurnalService fail-closed jika COA absen)
php artisan db:seed --class="Database\Seeders\AkunCoaSeeder" --force

# 4. Tier Harga Membership (Retail, Bengkel, Reseller, Distributor)
php artisan db:seed --class="Database\Seeders\TierMembershipSeeder" --force

# 5. Master Atribut WMS (Satuan unit, Kualitas produk, Brand, Tipe HP)
php artisan db:seed --class="Database\Seeders\MasterDataSeeder" --force

# 6. Master Kategori Produk
php artisan db:seed --class="Database\Seeders\KategoriProdukSeeder" --force

# 7. Master Kategori & Tarif Jasa Servis Standar
php artisan db:seed --class="Database\Seeders\JenisServisSeeder" --force

# 8. Skema Komisi (Teknisi & Reseller)
php artisan db:seed --class="Database\Seeders\KomisiSkemaSeeder" --force

# 9. Akun Pengguna Awal (Super Admin, Owner, Admin Toko, Kasir)
php artisan db:seed --class="Database\Seeders\AdminUserSeeder" --force
```

Akun default awal yang terbentuk:
- **Super Admin:** `superadmin@uteparts.id` | Sandi: `Password123!`
- **Kasir Cabang Pusat:** `kasir@uteparts.id` | Sandi: `Password123!`
*(Segera ubah password melalui menu pengaturan akun setelah login).*

---

## 4. Tahapan Migrasi Data dari Aplikasi Lama

Lakukan migrasi data secara berurutan sesuai relasi database:

### Tahap 4.1: Validasi Cabang & Gudang Toko
1. Login ke `/app/login` sebagai Super Admin.
2. Masuk ke menu **Rbac / Pengaturan Cabang** (`/app/cabang`):
   - Sesuaikan nama Cabang Pusat dengan nama toko fisik Anda (contoh: *Ute Parts Sentral*).
   - Masukkan alamat toko, nomor telepon WhatsApp toko, dan footer struk kasir.
3. Masuk ke menu **WMS / Gudang & Rak** (`/app/wms/gudang`):
   - Pastikan minimal 1 Gudang Utama aktif terikat pada Cabang ini.

### Tahap 4.2: Migrasi Master Pelanggan (CRM)
1. Export data pelanggan dari software lama ke format Excel/CSV:
   - Kolom: `Nama`, `No Telepon / WA`, `Alamat`, `Email`, `Tipe/Tier` (`Retail` / `Bengkel` / `Reseller`).
2. Masuk menu **CRM / Pelanggan** (`/app/crm/pelanggan`):
   - Lakukan import data pelanggan atau entri massal.
   - Set plafon piutang/kasbon awal jika ada pelanggan bengkel langganan yang memiliki hak tempo.

### Tahap 4.3: Migrasi Master Produk, Harga Tier & Stok Fisik Awal (WMS)
1. Unduh template Excel import produk melalui menu **WMS / Import & Export** (`/app/wms/import-export`):
   - File template: `ImportProdukTemplateExport`.
2. Isi kolom data dari aplikasi lama:
   - `kode_sku` (SKU unik per produk)
   - `nama_produk`
   - `kategori` (LCD, Baterai, Fleksibel, Konektor, Aksesoris)
   - `brand` (Apple, Samsung, Xiaomi, dll)
   - `tipe_hp` (Tipe kompatibel)
   - `satuan` (pcs, unit, set)
   - `kualitas` (Original, Grade A, OEM)
   - `harga_beli` (Harga HPP modal produk)
   - `harga_jual` (Harga jual retail umum)
   - `harga_bengkel` (Tier Bengkel)
   - `harga_reseller` (Tier Reseller)
   - `stok_awal` (Qty fisik di rak saat stock opname cut-off)
   - `gudang` (Gudang Utama)
3. Upload file Excel di menu **WMS / Import & Export**:
   - Sistem menjalankan **Dry-run Preview** (validasi format, deteksi baris error).
   - Klik **Commit Import**:
     * Master produk, varian SKU, dan matriks harga tier tersimpan.
     * Stok masuk dicatat pada tabel `stok_items` dan `stok_log` (sumber: `import:excel`).
     * **Jurnal Akuntansi Otomatis Dibuat:**
       - **Debit:** `130-01 Persediaan Barang Dagang` (Total Qty × HPP)
       - **Kredit:** `310-01 Modal Pemilik / Stok Awal`
       *(Neraca langsung balance tanpa selisih).*

### Tahap 4.4: Inisialisasi Saldo Kas & Bank Awal (Akunting)
Jika berpindah aplikasi dengan membawa saldo riil kas tunai dan rekening bank:
1. Masuk menu **Akunting / Jurnal Umum** (`/app/akunting/jurnal`).
2. Buat Jurnal Penyesuaian Saldo Awal:
   - **Debit:** `110-01 Kas` (Uang tunai brankas/laci)
   - **Debit:** `110-02 Bank` (Saldo rekening operasional)
   - **Kredit:** `310-01 Modal Pemilik` (Total nominal kas + bank)
3. Simpan dan Posting Jurnal.

### Tahap 4.5: Migrasi Piutang & Utang Berjalan (Opsional)
Jika ada utang supplier atau piutang pelanggan dari sistem lama:
- **Piutang Pelanggan:** Catat invoice piutang awal di **Akunting / Piutang** (`/app/akunting/piutang`) dengan lawan akun `310-01 Modal Pemilik`.
- **Utang Supplier:** Catat invoice utang awal di **Akunting / Utang** (`/app/akunting/utang`) dengan lawan akun `310-01 Modal Pemilik`.

---

## 5. Konfigurasi Hardware Kasir & Operasional

### 5.1 Printer Struk Thermal (ESC/POS)
1. Sambungkan printer kasir thermal (58mm atau 80mm) via USB / Ethernet / Bluetooth.
2. Di PC Kasir, pastikan driver printer terpasang di OS dan diset sebagai default printer.
3. Pada browser kasir, aktifkan opsi *"Silent Printing"* jika ingin struk tercetak otomatis tanpa dialog print:
   - Shortcut Chrome: `--kiosk-printing`

### 5.2 Barcode Scanner
- Scanner barcode USB/Wireless plug & play (keyboard-wedge) langsung bekerja pada input field pencarian POS Kasir (`F2`).

### 5.3 QRIS Cabang (Pembayaran Non-Tunai)
1. Masuk menu **Pengaturan POS / QRIS Cabang** (`/app/pos/settings`).
2. Masukkan static QRIS payload atau upload gambar QRIS toko.
3. Kasir dapat menampilkan QRIS langsung pada layar kasir untuk discan pembeli.

### 5.4 Jalankan Background Queue Worker
Pastikan background worker aktif agar proses async (notifikasi WA, push omnichannel, background report) berjalan:
```bash
php artisan queue:work database --sleep=3 --tries=3
```
*(Untuk production di VPS CyberPanel/Ubuntu, pasang konfigurasi ini di Supervisor).*

---

## 6. Prosedur Pembukaan Kasir & Transaksi Perdana (Day-1 Go-Live)

Sistem siap dipakai bertransaksi dengan alur SOP berikut:

### Langkah 1: Kasir Login & Buka Shift Kas
1. Kasir membuka URL `/app/login` dan memasukkan akun kasir.
2. Klik menu **POS Kasir** (`/app/pos`).
3. Sistem mendeteksi belum ada sesi kas aktif. Dialog modal **"Buka Sesi Kas"** muncul otomatis:
   - Masukkan nominal **Modal Kas Awal** di laci (contoh: `Rp 200.000` untuk uang kembalian).
   - Klik tombol **"Buka Kas"**.
   - Status sesi kas laci aktif tercatat pada tabel `kas_sesi`.

### Langkah 2: Proses Transaksi Penjualan
1. **Input Barang:**
   - Scan barcode SKU barang, atau ketik nama/tipe HP pada kolom pencarian katalog.
   - Barang otomatis masuk ke keranjang belanja kasir.
2. **Pilih Pelanggan & Tier Harga (Opsional):**
   - Default: Pelanggan Umum (Harga Retail).
   - Jika pelanggan bengkel/member dipilih: harga satuan otomatis menyesuaikan dengan Tier Bengkel / Reseller.
3. **Pembayaran:**
   - Tekan tombol **"Bayar"** atau tombol cepat `F4`.
   - Pilih metode pembayaran:
     - **Tunai:** Masukkan uang diterima kasir, sistem menghitung uang kembalian.
     - **QRIS / Transfer:** Verifikasi pembayaran masuk.
     - **Kasbon / Piutang:** Hanya bisa untuk member terdaftar dengan plafon mencukupi.
4. **Selesaikan Transaksi:**
   - Klik **"Selesai & Cetak"**.
   - Struk thermal tercetak otomatis.
   - Stok terpotong *real-time* dari tabel `stok_items`.
   - Jurnal transaksi otomatis terposting:
     - **Debit:** Kas Laci `110-04` (atau Bank)
     - **Kredit:** Pendapatan Penjualan `410-01`
     - **Debit:** HPP `510-02`
     - **Kredit:** Persediaan Barang `130-01`

### Langkah 3: Penutupan Shift Kasir (End of Day)
1. Di akhir jam operasional toko, kasir mengklik tombol **"Tutup Kas"** di menu POS.
2. Kasir menghitung fisik uang tunai di laci (*blind count*) dan menginput nominal fisik aktual.
3. Sistem mencocokkan saldo fisik dengan saldo sistem (Modal Awal + Penjualan Tunai):
   - Jika sesuai: Status *Balance*.
   - Jika selisih: Sistem mencatat deviasi pada log sesi kasir untuk audit owner.
4. Cetak Rekap Tutup Shift Kasir.

---

## 7. Checklist Verifikasi Kesiapan (Go-Live Ready)

Gunakan checklist ini sebelum pintu toko dibuka untuk umum:

- [ ] Database fresh migrated tanpa error (`php artisan migrate:status`)
- [ ] Role dan Permission Spatie terisi lengkap
- [ ] Cabang Pusat & Gudang Utama sudah disesuaikan
- [ ] Chart of Account (COA) aktif lengkap
- [ ] Tier harga (Retail, Bengkel, Reseller) aktif
- [ ] Master produk & stok fisik opname berhasil diimport via Excel
- [ ] Jurnal saldo awal persediaan tercatat di Akunting
- [ ] Saldo awal Kas & Bank telah diinput
- [ ] Akun kasir sudah dibuat dan diserahkan ke staf
- [ ] Printer thermal dan barcode scanner teruji di menu POS
- [ ] Queue worker berjalan di background
- [ ] Kasir berhasil membuka kas perdana dan melakukan test 1 transaksi dummy lalu void/hapus.

**SISTEM SIAP DIGUNAKAN BERJUALAN PENUH.**
