# Ute Parts — Panduan Jalankan Lokal (Preview)

Setup lokal untuk *review UI/UX, fitur, dan modul* di mesin sendiri.

## Prasyarat

| Tool | Versi Min | Cek |
|---|---|---|
| PHP CLI | 8.3+ | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js + npm | 20+ | `node -v` |
| MySQL/MariaDB **atau** PHP SQLite | - | `php -m \| findstr sqlite` |

> **Disarankan: SQLite** — nol konfigurasi, langsung jalan.
> Aktifkan di `php.ini`: `extension=pdo_sqlite` dan `extension=sqlite3`, lalu `php -m` harus tampil `pdo_sqlite`.

## Langkah Instalasi

Buka terminal baru di folder project:

```sh
# 1. Install dependency PHP
composer install

# 2. Buat .env (jika belum ada) + app key
copy .env.example .env
php artisan key:generate

# 3. Konfigurasi database di .env
# == SQLite (disarankan) ==
# DB_CONNECTION=sqlite
# DB_DATABASE=C:\workspace\ute-parts\database\database.sqlite
# (buat file kosong:  type nul > database\database.sqlite)
#
# == ATAU MySQL/MariaDB ==
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=ute_parts
# DB_USERNAME=root
# DB_PASSWORD=rahasia

# 4. Migrasi + seed data contoh
php artisan migrate --force
php artisan db:seed --force

# 5. Install & build aset frontend (Tailwind Vite)
npm install
npm run build
```

## Jalankan

Dua terminal:

```sh
# Terminal 1 — Laravel dev server
php artisan serve          # → http://127.0.0.1:8000

# Terminal 2 — Vite hot reload (opsional, untuk development aset)
npm run dev
```

Buka **http://127.0.0.1:8000** — akan redirect ke login backoffice.

## Akun Demo

| Role | Email/Telepon | Password |
|---|---|---|
| Super Admin (semua modul backoffice) | `admin@uteparts.com` | `password` |
| Pelanggan marketplace (member Gold) | telepon `08123344556` | (buat via daftar, atau daftar baru) |
| Reseller (ujian komisi) | telepon `08119876543` | (buat via daftar, atau daftar baru) |

Backoffice: buka **http://127.0.0.1:8000/app/login** (default dark mode).
Marketplace: buka **http://127.0.0.1:8000/shop** (mobile-first, light mode).

> Pelanggan marketplace daftar via **http://127.0.0.1:8000/daftar-pelanggan** (1 langkah: nama + HP + password).

## Data Contoh yang Sudah Ter-seed

- 2 Cabang (`CBG-01` Pusat, `CBG-02` Selatan) + 3 Gudang
- 6 Produk sparepart + 2 varian SKU + stok di gudang
- 2 Pelanggan: member Gold + 1 **Reseller** (untuk uji komisi)
- 3 Tier: Silver / Gold / Platinum
- 5 Jenis Servis beserta durasi garansi (30/90/14 hari)
- 23 Akun COA standar retail+servis
- 4 Skema Komisi reseller

## Alur Uji Cepat (rekomendasi)

1. **Marketplace** (publik): `/shop` → hero + katalog dengan filter kategori/kondisi/brand/harga → detail produk (harga retail utk guest, stok per cabang) → daftar pelanggan → masukkan ke keranjang → `/checkout` (pilih ambil di toko) → **Buat Pesanan** → muncul no. order `menunggu_pembayaran` (Duitku menyusul Hari 6).
2. **POS**: `/app/pos` → pilih produk → pilih pelanggan `Budi Reseller Cell` → bayar → cek perhitungan harga reseller + jurnal otomatis + komisi pending.
3. **POS Kasbon**: metode bayar **Kasbon** → otomatis buat Piutang → lihat tab Akunting → Piutang.
4. **WMS**: `/app/wms` → buat transfer antar gudang → kirim → terima (stok bergerak, tercatat di kartu stok).
5. **Servis**: `/app/servis` → Terima Unit (wajib 2 foto) → Diagnosa → Input Estimasi → Menunggu Approval → Setujui → Kerjakan → QC → Selesai (garansi otomatis) → Diambil.
6. **Akunting**: `/app/akunting` → tab Laporan → **Laba Rugi, Neraca, Arus Kas** — terisi otomatis dari POS, Servis, Komisi (jurnal double-entry debit=kredit).
7. **Reseller**: `/app/reseller` → tab Komisi → komisi `pending` → centang → **Setujui** → cek Akunting: jurnal Beban Komisi + Utang Komisi → tab Utang → Bayar.
8. **CRM**: `/app/crm` → detail pelanggan (360°: riwayat belanja, servis, progress tier).

## Integrasi Eksternal (Day 6 — sandbox siap)

| Integrasi | .env | Status |
|---|---|---|
| **Duitku** payment | `DUITKU_MERCHANT_CODE`, `DUITKU_API_KEY`, `DUITKU_MERCHANT_KEY`, `DUITKU_SANDBOX=true` | Sandbox: createTransaction PAY-01, **webhook PAY-02** `/webhook/duitku` (signature MD5 + idempotent — teruji duplikat callback tidak dobel kurangi stok/jurnal) |
| **Biteship** ongkir | `BITESHIP_API_KEY` | SHIP-01 `/api/shipping/rates` multi-origin, create order → `pengiriman.tracking_id` |
| **Shopee** omnichannel | `SHOPEE_PARTNER_ID`, `SHOPEE_PARTNER_KEY`, `SHOPEE_SHOP_ID` | Adapter + mapping produk + OMNI-01..06 + webhook `/webhook/channel/{id}` |
| **Excel import** | — | `/api/import/produk/preview` (dry-run wajib) + `/commit` — SID Retail mapping via heading |
| **Tracking servis** | — | `/tracking/{token}` — stepper publik tanpa login |
| **Notifikasi** | — | Queue `database`, job `KirimNotifikasiJob` (WA provider: pilih user) |

> **Webhook test** (`tests/Feature/DuitkuWebhookTest.php`): verifikasi signature salah ditolak 400, callback lunas → stok turun + jurnal debit=kredit + status lunas, callback **duplikat idempotent** (skip, no dobel entry), callback gagal → status gagal tanpa ubah stok.

## Troubleshooting

| Masalah | Solusi |
|---|---|
| `could not find driver` (SQLite) | Aktifkan `pdo_sqlite`+`sqlite3` di php.ini |
| `could not find driver` (gd) utk Excel | Aktifkan `extension=gd` di php.ini |
| `Unknown character set utf8mb4` | Server DB lawas; set `DB_CHARSET=utf8 DB_COLLATION=utf8_unicode_ci` |
| `old insecure authentication` | Jalankan `SET GLOBAL old_passwords=0;` lalu reset password user DB |
| Halaman tampil tanpa CSS | Jalankan `npm run build` (pastikan sudah) |
| Migrasi/seeder jalan ulang error duplikat | Seeder sudah idempotent; jalankan `php artisan db:seed` lagi aman |

## Tests

```sh
# Cek PRD §8: unit test state machine servis + pricing + jurnal double-entry
php artisan test
```

## Queue Notifikasi

Notifikasi WA/email di-queue. Untuk development, kosongkan `jobs` otomatis via:

```sh
# Jalankan worker sekali jalan untuk memproses antrian
php artisan queue:work --once
# Atau biarkan berjalan
php artisan queue:work
```

Provider WA (Fonnte/Wablas/WA Business API) adalah keputusan user — hook siap di `app/Modules/Notifikasi/Jobs/KirimNotifikasiJob.php` (saat ini log + email).

## Structure Module

```
app/Modules/
├── Rbac/        Auth, User, Cabang, Role/Permission
├── Pos/         Kasir, Transaksi, Pricing tier
├── Wms/         Gudang, Stok, Transfer, Opname
├── Servis/      Tiket servis, Garansi, Kanban
├── Crm/         Pelanggan 360°, Tier, Broadcast
├── Reseller/    Komisi, Skema komisi
├── Akunting/    COA, Jurnal double-entry, Laporan, AR/AP
└── Notifikasi/  Queue WA/email + notifikasi in-app
```