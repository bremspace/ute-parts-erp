# SESSION-STATE.md — Status Terakhir + Lanjutan di VPS Linux

Dokumen ini berisi semua keputusan, status, dan issue yang perlu diketahui saat melanjutkan dari posisi terakhir.
**Baca file ini SEBELUM mulai coding di VPS Linux.**

**Terakhir diperbarui:** 2026-09-19
**Commit terakhir:** `d138439` (setup-vps.sh universal + install.php)
**Branch:** `main`

---

## 1. Status Implementasi

| Modul | Status | Catatan |
|---|---|---|
| Hari 1: RBAC + auth + migrasi inti | ✅ | 7 role, 44 permissions, Sanctum SPA |
| Hari 2: WMS + POS | ✅ | stok, transfer, opname, PO, kas sesi |
| Hari 3: Servis HP | ✅ | state machine 8 status, part/jasa split, garansi, notifikasi queue |
| Hari 4: CRM + Reseller + Akunting | ✅ | tier otomatis, komisi custom/skema, COA 23 akun, jurnal double-entry |
| Hari 5: Marketplace katalog/checkout | ✅ | Livewire front, cart session, customer auth, harga tier live |
| Hari 6: Duitku + Biteship + Omnichannel | ✅ | webhook idempotent, Shopee adapter, import Excel |
| Hari 7: Hardening + deploy | ✅ | audit log, rate limit, integrasi test 26/26, browser installer, setup-vps.sh |
| **Dashboard** | ✅ | omzet 30 hari, kategori, status servis, piutang aging |
| **Pengaturan** | ✅ | user CRUD, role+permission matrix, jenis servis CRUD |

---

## 2. Pipeline Deploy

### Di VPS (Linux, satu perintah):
```bash
bash <(curl -fsSL https://raw.githubusercontent.com/bremspace/ute-parts-erp/main/setup-vps.sh) uteparts.com
```

### Update dari GitHub:
- **GitHub Actions** (otomatis push main): SSH ke VPS → pull → migrate → cache → restart queue → **rollback otomatis** jika gagal
- **Cron auto-migrate**: `* * * * * cd /var/www/ute-parts && php artisan migrate --force`
- PHP binary di VPS: `/usr/local/lsws/lsphp85/bin/php` atau `/usr/bin/php8.3` (setelah install)

---

## 3. Keputusan Teknis Kritis (jangan diubah)

| Keputusan | Alasan |
|---|---|
| **JurnalService: `generateNoJurnal` LIKE pattern WAJIB include `$cabangId`** | BUG hari ini: LIKE tanpa cabangID → nomor jurnal bentrok lintas cabang |
| **Channel kredensial: cast `json` (bukan `encrypted:array`)** | MySQL JSON constraint gagal pada encrypted value; APP_KEY Encryption pakai `text` column |
| **Pelanggan implements Authenticatable** | Guard `customer` butuh model yang implements Authenticatable trait |
| **TiketServisItem tipe `part` vs `jasa`** | Part: potong stok + stok log; Jasa: jurnal HPP/Pendapatan saja |
| **Jurnal servis di onSelesai** | 4 baris: Kas debit / Pendapatan jasa kredit / Pendapatan penjualan sparepart kredit / HPP debit (persediaan kredit) |
| **TiketServis status `menunggu_approval` → token** | Token WAJIB di-generate saat setEstimasi, bukan saat updateStatus |
| **Livewire computed properties** | WAJIB pass eksplisit di `render()`: `return view('...', ['propName' => $this->computedProp])` — bare `$prop` di blade GAGAL |
| **Setup MySQL: pakai `sudo mysql`** | CyberPanel/Unix socket auth — `mysql -uroot -p` FAILS |
| **Handler LSAPI bernama `lsphp`** (bukan `lsphp85`) | ExtProcessor di server bernama `lsphp` + socket `lsphp85.sock`; vhost scripthandler harus pakai nama itu |
| **OLS vhost format: flat, bukan `virtualHostConfig{}`** | `virtualHostConfig{}` dengan `dirindex`/`enablelscache` → invalid parse → 403 |

---

## 4. File Kunci (path relatif ke root)

```
app/Modules/
  Servis/Services/ServisStateMachine.php  — 8 status, 10 transisi valid
  Servis/Services/ServisService.php       — onSelesai jurnal + inputPekerjaan part/jasa
  Akunting/Services/JurnalService.php     — double-entry, generateNoJurnal
  Reseller/Services/KomisiService.php     — hitungKomisi, prosesApproval
  Marketplace/Services/DuitkuService.php  — webhook + signature MD5
  Marketplace/Services/OrderService.php   — checkout marketplace tanpa payment
  Wms/Services/StokDeductionService.php   — kurangiDariGudangTersedia (multi-gudang)

database/seeders/
  RolesAndPermissionsSeeder.php  — 36 permissions, 7 roles (idempotent)
  AkunCoaSeeder.php              — 23 akun (kas, pendapatan, HPP, utang, dll)
  TransaksiDemoSeeder.php        — semua jenis transaksi (idempotent, try/catch channel)

tests/Feature/
  ServisStateMachineTest.php     — 10 valid + 7 backward + terminal + kanban
  IntegrasiModulTest.php         — full flow POS→komisi→utang→servis→jurnal balance
  DuitkuWebhookTest.php          — signature + idempotent + stok + jurnal
  LoginFlowTest.php              — auth web + CSRF + session
```

---

## 5. Issue Known & Status

| Issue | Status | Fix |
|---|---|---|
| Livewire computed properties tidak tampil di blade | ✅ | Pass eksplisit di `render()`: `['prop' => $this->computed]` |
| `Context [/] is not accessible` di OLS | ✅ | `docRoot == context /` + handler `lsapi:lsphp` + vhssl + CyberPanel VHost Save |
| Composer timeout di Windows (developer env) | ✅ | CD ke WEBROOT dulu; `COMPOSER_ALLOW_SUPERUSER=1` |
| Channel kredensial encrypted → MySQL JSON gagal | ✅ | `encrypted:array` → `json` (ponytail) |
| Pesan `Permission [permission] does not exist` | ✅ | Daftarkan Spatie middleware alias di `bootstrap/app.php` |

---

## 6. Deploy di VPS Linux (checklist)

```bash
# Jalankan setup (dari VPS langsung):
bash <(curl -fsSL https://raw.githubusercontent.com/bremspace/ute-parts-erp/main/setup-vps.sh) uteparts.com

# Setelah deploy:
curl -s -o /dev/null -w "%{http_code}" https://uteparts.com/up       # → 200
curl -s -o /dev/null -w "%{http_code}" https://uteparts.com/shop     # → 200
curl -s -o /dev/null -w "%{http_code}" https://uteparts.com/app/login # → 200
```

PHP binary di VPS: cari dengan `find /usr -name "php" -path "*/bin/*" 2>/dev/null | head -1`

---

## 7. Fase 2+ yang BELUM diimplementasikan (dari implement-plan.md)

Hari 1-7 roadmap + install-plan sudah selesai. Fase 2 (post-MVP) yang belum:

| Fase | Fokus |
|---|---|
| Fase 10+ | Add adapter Tokopedia, Blibli, TikTok Shop ke `ChannelAdapterInterface` |
| Fase 11+ | Marketplace: live search debounced, rekomendasi produk, wishlist |
| Fase 12+ | Dashboard advanced: grafik Chart.js, report schedule, notification preferences |
| Fase 13+ | Multi-cabang view switching (current session) |
| Fase 14+ | Audit log UI (search/inspect log), export audit |
| Fase 15+ | Mobile responsive test ala PRD §8 (Playwright) |