# Bremspace ERP — Multi-Branch Sparepart & Servis HP Platform

Sistem operasional terpadu (**Backoffice ERP/POS** + **Storefront Marketplace Publik**) untuk toko retail suku cadang smartphone, konter HP, reseller B2B, dan bengkel servis HP multi-cabang.

Dirancang ringan untuk VPS Linux standalone maupun mode **Hybrid Offline/Online Store Node**, lengkap dengan **Dynamic Tier Pricing**, **Servis Kanban & Approval Pelanggan**, **Omnichannel Shopee**, **Gateway Pembayaran Duitku**, **Kurir Biteship**, serta **Self-Hosted WhatsApp Gateway (OpenWA)**.

---

## Arsitektur & Fitur Utama

Aplikasi terdiri dari **16 modul domain modular** di `app/Modules/` yang terhubung dalam dua zona utama:

### 1. Backoffice ERP (`/app/*`) — Desktop-First, Dark Mode Default
- **Dashboard (`Dashboard`):** Widget analitik peran-spesifik (omzet harian, grafik performa, ringkasan servis, persediaan kritis).
- **Kasir POS (`Pos`):** Layar kasir cepat dengan barcode scanner, shortcut keyboard (`F2`, `F4`, `F6`), park/hold transaksi, auto-pricing tier pelanggan, retur penjualan, pesanan online, serta cetak struk thermal ESC/POS.
- **Manajemen Gudang & Produk (`Wms`):** Katalog sparepart dengan kompatibilitas Merk/Tipe HP, SKU varian, kartu stok terikat gudang/rak, transfer antar-cabang, Purchase Order (PO), penerimaan barang (GRN), cycle count & stock opname, pencetakan barcode, serta import/export Excel (`maatwebsite/laravel-excel`).
- **Kanban Servis & Garansi (`Servis`):** State machine tiket servis ketat (`Diterima → Diagnosa → Estimasi → Approved → Dikerjakan → QC → Selesai → Diambil`), token link persetujuan pelanggan tanpa login, tracking klaim garansi, penggunaan part fisik (potong stok) vs jasa, dan penjurnalan HPP/pendapatan otomatis.
- **Akunting (`Akunting`):** Bagan Akun (COA), jurnal double-entry otomatis dari POS/Servis/Pembelian, buku besar, piutang/utang, neraca saldo, laba rugi, depresiasi aset, dan export laporan.
- **CRM & Loyalti (`Crm`):** Manajemen profil pelanggan, tier membership dinamis, poin loyalitas, broadcast kampanye WhatsApp, serta lead & sales pipeline.
- **Reseller & Bagi Hasil (`Reseller`):** Skema komisi multi-aktor, dashboard performa mitra/sales, dan pencairan komisi.
- **HR & Payroll (`Hr`):** Data karyawan tersinkron ke user RBAC, shift & absensi, penilaian KPI bulanan otomatis, slip gaji, serta approval payroll.
- **Omnichannel & Webhook (`Omnichannel`, `Webhook`):** Adapter sinkronisasi katalog/stok/pesanan Shopee Open API dan outbound webhook terautentikasi.
- **Approval Engine (`Workflow`) & Laporan (`Report`):** Mesin persetujuan bertingkat (PO, GRN, cycle count, payroll) dan laporan analitik drilldown dengan export.
- **RBAC, Keamanan & Pengaturan Sistem (`Rbac`):** Hak akses berbasis Spatie Permission, scoping ketat per `cabang_id`, 2FA Google Authenticator, manajemen sesi perangkat, audit trail (`spatie/laravel-activitylog`), backup/restore snapshot SQL, upgrade domain/Cloudflare Tunnel, tema & palet desain, serta automasi QRIS dinamis Android.
- **Notifikasi & Gateway WA (`Notifikasi`):** Queue job asinkron untuk WhatsApp (**OpenWA self-hosted**, Fonnte, Wablas, Custom Webhook), Email, dan pusat notifikasi In-App real-time.
- **Hybrid Sync (`Sync`):** Sinkronisasi asimetris node toko lokal dengan server cloud pusat (`sync:flush`, `sync:pull`) untuk ketahanan kasir saat internet terputus.

### 2. Marketplace & Portal Konsumen (`/`) — Mobile-First, Light Mode Default
- Katalog sparepart responsif dengan filter merk/tipe HP & pencarian cerdas.
- Registrasi, login member, profil akun, dan histori belanja/poin.
- Keranjang & checkout multi-origin kurir **Biteship** + pembayaran online **Duitku** (QRIS, Virtual Account, E-Wallet) dengan webhook idempoten.
- Pelacakan tiket servis publik & halaman approval estimasi biaya pelanggan via token unik.

---

## Stack Teknologi (Terverifikasi)

- **Backend:** PHP `8.4+`, Laravel `13.x`, Livewire `4.x`, Laravel Sanctum `4.x`
- **Frontend:** Tailwind CSS `4.x`, Alpine.js, Vite `8.x`, `vite-plugin-pwa`, Instrument Sans (Bunny Fonts)
- **Database & Queue:** MySQL / MariaDB (produksi), SQLite `:memory:` (automated tests), driver queue `database` + Supervisor
- **Paket Inti:** `spatie/laravel-permission`, `spatie/laravel-activitylog`, `maatwebsite/excel`, `pragmarx/google2fa`, `bacon/bacon-qr-code`, `picqer/php-barcode-generator`
- **QA & Analisis Statis:** PHPUnit `13.x`, Laravel Pint (`PSR-12`), Larastan / PHPStan, Deptrac, PHPInsights

---

## Setup Lokal / VPS Baru

### 1. Instalasi Cepat
```bash
git clone <repo-url> ute-parts-erp
cd ute-parts-erp
composer setup
```

Perintah `composer setup` menjalankan:
1. `composer install`
2. Menyalin `.env.example` ke `.env` jika belum ada
3. `php artisan key:generate`
4. `php artisan migrate --force`
5. `php artisan db:seed --force --no-interaction`
6. `npm install --ignore-scripts && npm run build`

### 2. Menjalankan Server Development
```bash
# Terminal 1 — Laravel PHP server
composer dev

# Terminal 2 — Vite HMR asset watcher
npm run dev
```

### 3. Integrasi Eksternal & Service Produksi
Salin dan sesuaikan variabel di `.env` (referensi lengkap ada di `.env.example`):
- **WhatsApp Gateway (OpenWA Self-Hosted):** Pasang kontainer lewat `deploy/openwa/` lalu ikuti panduan UI 1-klik di [Panduan Pemasangan OpenWA](docs/PANDUAN-OPENWA.md).
- **Queue Worker (Wajib di Produksi):** Pasang konfigurasi Supervisor dari `deploy/supervisor/ute-parts-queue.conf` agar notifikasi WA/Email, sync stok, dan pekerjaan latar belakang berjalan lewat `queue:work database`.
- **Payment & Ongkir:** Isi `DUITKU_*`, `BITESHIP_*`, dan `SHOPEE_*` di `.env` atau via **Pengaturan → Gateway & Integrasi**.
- **Scheduler Cron (`routes/console.php`):** Pasang cron Laravel setiap menit (`* * * * * php artisan schedule:run >> /dev/null 2>&1`) untuk cek SLA servis (`servis:cek-batas-waktu`), rekonsiliasi Duitku (`duitku:rekon`), KPI bulanan, dan scheduler Hybrid Sync.

---

## Testing & Quality Assurance

Seluruh unit & feature tests berjalan terisolasi di atas SQLite `:memory:` (`phpunit.xml`):

```bash
# Jalankan seluruh test suite
composer test

# Jalankan test tertentu
php artisan test --filter=PricingServiceTest

# Code formatting check & static analysis & arsitektur modul
composer qa:format     # vendor/bin/pint --test
composer qa:stan       # vendor/bin/phpstan analyse
composer qa:deptrac    # vendor/bin/deptrac analyse
composer qa            # Jalankan seluruh gerbang QA
```

---

## Dokumentasi & Referensi

- **Panduan OpenWA (Self-Hosted WhatsApp Gateway):** [docs/PANDUAN-OPENWA.md](docs/PANDUAN-OPENWA.md) & [deploy/openwa/README.md](deploy/openwa/README.md)
- **Setup Awal & Migrasi VPS ke Lokal:** [docs/PANDUAN-SETUP-AWAL-MIGRASI.md](docs/PANDUAN-SETUP-AWAL-MIGRASI.md) & [docs/MIGRASI-VPS-KE-LOKAL.md](docs/MIGRASI-VPS-KE-LOKAL.md)
- **Kasir Offline, Hybrid Sync & Failover:** [docs/hybrid-sync-runbook.md](docs/hybrid-sync-runbook.md) & [docs/PANDUAN-KASIR-OFFLINE-DAN-FAILOVER.md](docs/PANDUAN-KASIR-OFFLINE-DAN-FAILOVER.md)
- **Otomasi QRIS & Duitku:** [docs/PANDUAN-OTOMASI-QRIS-ANDROID.md](docs/PANDUAN-OTOMASI-QRIS-ANDROID.md) & [docs/DUITKU-SETUP.md](docs/DUITKU-SETUP.md)
- **Deployment, Maintenance & CI/CD:** [docs/DEPLOY.md](docs/DEPLOY.md), [docs/SERVER-MAINTENANCE.md](docs/SERVER-MAINTENANCE.md), [docs/PANDUAN-CI-CD.md](docs/PANDUAN-CI-CD.md)
- **Akun Default Backoffice & QA Tools:** [docs/AKUN-BACKOFFICE.md](docs/AKUN-BACKOFFICE.md) & [docs/QA-TOOLS.md](docs/QA-TOOLS.md)
- **Arsitektur & Riwayat Perubahan:** `docs/adr/`, [DESIGN.md](DESIGN.md), [docs/CHANGELOG.md](docs/CHANGELOG.md)

---

## Hak Cipta

Lisensi internal proyek **Bremspace ERP**. Lihat detail dependensi open-source pada `composer.json` dan `package.json`.
