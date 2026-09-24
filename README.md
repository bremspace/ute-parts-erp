# Ute Parts ERP

Sistem ERP untuk bisnis sparepart & servis motor/sepeda: point of sale, management servis, marketplace, reseller & komisi, HR & payroll, akunting, dan inventori — dalam satu aplikasi modular.

## Fitur Utama

| Area | Modul | Kemampuan |
|------|-------|------------|
| Penjualan | `Pos` | Kasir, sesi kas, struk, clock-in/out kasir, transaksi & retur |
| Servis | `Servis` | Work order, teknisi, tiket servis, KPI penyelesaian |
| Reseller & Komisi | `Reseller` | Reseller, komisi multi-aktor (internal, teknisi, reseller), slip komisi |
| Marketplace | `Marketplace` | Katalog, cart, checkout, payment, shipping, impor produk |
| HR & Payroll | `Hr` | Karyawan, absensi shift, KPI, slip payroll, approval payroll |
| Akunting | `Akunting` | Jurnal, akun, laporan laba-rugi/neraca, depresiasi aset |
| Inventori | `Wms` | Produk, SKU/variant, stok, purchase order, return, cycle count, nomor seri |
| CRM | `Crm` | Pelanggan, lead, konversi lead, sales pipeline |
| Workflow | `Workflow` | Approval rule & request multi-level, GRN/cycle count/payroll/PO |
| Integrasi | `Notifikasi`, `Omnichannel`, `Webhook` | Notifikasi internal, sinkron channel, outbound webhook |
| Platform | `Rbac`, `Dashboard`, `Report` | Permission per role, audit log, dashboard & laporan |

## Kebutuhan Sistem

- **PHP >= 8.3** (diuji pada 8.4.25) — ekstensi: `mbstring`, `xml`, `curl`, `zip`, `pdo`, `pdo_sqlite`/`pdo_mysql`, `gd`, `bcmath`
- **Composer 2.x**
- **Node.js 20+ & npm** (untuk build asset frontend)
- RAM: minimal 1 GB (tool QA sudah punya profil otomatis untuk mesin kecil)

## Instalasi Cepat

```bash
git clone https://github.com/bremspace/ute-parts-erp.git
cd ute-parts-erp

composer install
cp .env.example .env
php artisan key:generate

npm install
npm run build

php artisan migrate --seed
```

Setelah itu jalankan server:

```bash
php artisan serve
```

## Perintah QA Otomatis

Seluruh tooling QA bersifat **gratis, terminal-only, tanpa biaya token AI**. Jalankan satu perintah:

```bash
./scripts/qa-auto.sh
```

Yang dilakukan otomatis:

1. Cek PHP (minimal 8.3) — gagal cepat dengan pesan jelas bila tidak sesuai
2. Deteksi RAM dan pilih profil analisis
3. `composer install` bila `vendor/` belum ada
4. Clear cache Laravel (config, route, view)
5. Jalankan pipeline: **Pint** → **PHPStan** → **Deptrac** → **Composer Audit**
6. Tulis 3 log di `qa-results/`

| Profil RAM | PHPStan memory | PHPStan paralel |
|------------|----------------|-----------------|
| < 1 GB (`lowram`) | 256M | 1 (serial) |
| 1–2 GB (`normal`) | 512M | 2 |
| > 2 GB (`highram`) | 1G | 4 |

### Output Log

```
qa-results/
├── qa-<timestamp>.log           # output lengkap, human-readable
├── qa-<timestamp>.json          # terstruktur, untuk AI parse
└── qa-<timestamp>.summary.txt   # ringkasan status + daftar yang perlu diperbaiki
```

`summary.txt` berisi status tiap step dan "Next Actions for AI" bila ada kegagalan — file ini siap dibaca/diolah AI untuk perbaikan.

### Perintah Manual (Composer)

```bash
composer qa                # pipeline lengkap: format → stan → deptrac → audit
composer qa:format         # cek style code (Pint, mode --test)
composer qa:stan           # static analysis (Larastan level 6 + baseline)
composer qa:stan:lowram    # PHPStan hemat RAM (256M, serial)
composer qa:stan:full      # PHPStan mesin kuat (1G, 4 proses)
composer qa:deptrac        # cek dependensi antar modul
composer qa:audit          # scan kerentanan dependency
```

### Wrapper dengan Profil Manual

```bash
./scripts/qa-runner.sh [perintah] [profil]
# perintah: all | stan | deptrac | insights | audit | format
# profil:   lowram | normal | highram

# contoh
./scripts/qa-runner.sh stan lowram
./scripts/qa-runner.sh all highram
```

Override manual bila perlu:

```bash
PHPSTAN_MEMORY_LIMIT=768M PHPSTAN_PROCESSES=3 ./scripts/qa-runner.sh stan
```

## Menjalankan Test

Test memakai PHPUnit dengan filter per fitur (full suite tidak stabil pada server 1 GB):

```bash
vendor/bin/phpunit --filter=PayrollPermissionTest
vendor/bin/phpunit --filter=AbsensiShiftTest
vendor/bin/phpunit --filter=KomisiMultiAktorTest
```

Menjalankan seluruh file test:

```bash
vendor/bin/phpunit tests/Feature/PayrollPermissionTest.php
```

## Struktur Proyek

```
app/Modules/          # 15 modul domain
├── Akunting/         # jurnal, akun, laporan keuangan, depresiasi
├── Crm/              # pelanggan, lead, konversi
├── Dashboard/        # dashboard ringkasan
├── Hr/               # karyawan, absensi, KPI, payroll
├── Marketplace/      # katalog, cart, order, payment, shipping
├── Notifikasi/       # notifikasi internal
├── Omnichannel/      # sinkron channel
├── Pos/              # kasir, sesi kas, transaksi
├── Rbac/             # permission, role, audit log
├── Report/           # laporan
├── Reseller/         # reseller & komisi multi-aktor
├── Servis/           # work order servis, teknisi
├── Webhook/          # outbound webhook
├── Wms/              # produk, stok, PO, cycle count, nomor seri
└── Workflow/         # approval multi-level
```

Berkas konfigurasi QA:

| Berkas | Fungsi |
|--------|--------|
| `phpstan.neon` | Konfigurasi Larastan/PHPStan level 6 |
| `phpstan-baseline.neon` | Baseline error existing yang diabaikan |
| `deptrac.php` | Layer & ruleset dependensi antar modul |
| `scripts/qa-auto.sh` | Pipeline QA otomatis satu perintah |
| `scripts/qa-runner.sh` | Wrapper per tool dengan profil RAM |

## Dokumentasi

| Dokumen | Isi |
|---------|-----|
| [`docs/QA-TOOLS.md`](docs/QA-TOOLS.md) | Panduan lengkap tiap tool QA, cara baca output, CI, troubleshooting |
| [`PRD-Advanced-UteParts.md`](PRD-Advanced-UteParts.md) | Requirement produk & status implementasi per fitur |
| [`CHANGELOG.md`](CHANGELOG.md) | Riwayat perubahan per fase |

## Lisensi

MIT
