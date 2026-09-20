# Ute Parts — CHANGELOG

Semua ringkasan task yang selesai dari `implement-plan.md` (pasca-MVP).
Format: `[Fase X] T-XX — ringkasan`.

## 2026-09-20

### Fase 10 — Thermal Print Backend (T-35, sisi server)
- **[T-35]** [ADR 0009] `App\Modules\Pos\Services\EscPosWriter` (baru, ~190 baris, pure PHP tanpa dependensi): builder byte ESC/POS 58mm — init `ESC @`, alignment `ESC a`, bold `ESC E`, ukuran `GS !`, wrap 32 kolom, barcode Code128 `GS k` (m=73, panjang 2-byte little-endian) dari `no_transaksi`, feed + cut `GS V B 0`; teks di-transliterasi non-ASCII→ASCII (é→e, ±→+/-) agar aman di cp437; deterministik, tanpa akses DB.
- **[T-35]** `App\Modules\Pos\Jobs\PrintThermalJob` (ShouldQueue, queue `default`/database): load `transaksi` + `items.produk/skuVariant` + kasir + cabang → susun data struk → byte via `EscPosWriter`. Jika `THERMAL_PRINTER` terisi → kirim `lp -d {printer} -o raw` (stdin pipe, symfony/process), sukses/gagal di-log; jika kosong (staging) → artefak `.bin` ke `storage/app/private/prints/` (disk local Laravel 13) + `Log::info`. Tidak pernah sync di request.
- **[T-35]** Endpoint `[API: POS-10] POST /api/pos/transaksi/{id}/print` (`permission:pos.view`): `PosController::printStruk` — scope cabang sesi, 404 bila tidak ketemu, hanya `dispatch` (non-blocking) → `Struk masuk antrian cetak`. `THERMAL_PRINTER=` ditambah di `.env` (staging, kosong; `QUEUE_CONNECTION=database` sudah ada).
- Verifikasi: EscPosWriter sample (starts `ESC @`, GS k, cut — 0x15=21 len barcode OK); unit 9 PASS; E2E staging login→print→queue:work→artefak `storage/app/private/prints/TRX-C01-20260919-0008_*.bin` PASS.

## 2026-09-20

### Fase 10 — Ribuan Separator (T-34)
- **[T-34]** [ADR 0011] Direktif Alpine `x-format-number` (`resources/js/components/format-number.js`, daftar via `alpine:init` di `resources/js/app.js`): separators ribuan Indonesia (`.` berkelompok-3) tampil live saat ketik; desimal pakai koma (`1.000.000,50`) didukung; paste `1.000.000`/`1000000` dinormalisasi.
- **[T-34]** Model tetap bersih: Livewire `wire:model` di-bind sbg `x-model` saat `interceptInit` (selalu lebih dulu dari atribut `x-*`) → `x-model` membaca nilai mentah; direktif hanya format ulang tampilan + koreksi model lewat `el._x_model.set(toRaw(...))` utk kasus paste berformat; sinkron model→tampilan via effect reaktif (quick-cash `setQuickCash`/`$set`, respons server) tanpa lag (<50ms, murni client-side).
- **[T-34]** Diterapkan ke 10 input nominal: POS `jumlahBayar`/`splitTunai`/`splitNonTunai`/`kasSaldoAwal`/`kasSaldoFisik`, Akunting `manualLines.*.debit`/`kredit`/`bayarUtangJumlah`/`bayarPiutangJumlah`, CRM `tierForm.min_belanja_12bulan`; `type="number"` → `type="text" inputmode="numeric"` (spinner browser hilang, keypad numerik tetap). Marketplace: belum ada view checkout (hanya auth/components/layouts/modules) — dilewati. Verifikasi: build PASS, bundle berisi registrasi direktif, logic 11 kasus uji PASS, view:cache 10/10 input ter-compile.

## 2026-09-20

### Fase 10 — Kas-Akunting Sync Verification (T-33)
- **[T-33]** Migration `2026_09_20_000036_add_sumber_to_kas_sesi_table` — kolom `kas_sesi.sumber` (`manual|legacy|carryover`, default `manual`) utk lacak asal saldo awal shift.
- **[T-33]** `KasSesiState`: buka kas jurnal kini Debit `110-01` Kas / Kredit `310-01` Modal Pemilik (hapus akun keliru `310-03`); idempotent per-kasir (user_id atau sesi bersama NULL), multi-kasir diperbolehkan dalam cabang sama; `sesiKasAktif()` scoped ke kasir aktif; akumulasi tutup kas di-scope ke `kasir_id` sesi (cabang-wide utk sesi bersama); selisih jurnal pindah `520-06` → `520-07` Selisih Kas; `riwayat()` dukung limit.
- **[T-33]** Carryover shift: modal buka kas prefill `saldo_akhir_fisik` sesi tutup terakhir (prioritas kasir sendiri, fallback sesi bersama), `sumber=carryover` otomatis.
- **[T-33]** Laporan Akunting: section "Riwayat Sesi Kas" (50 terakhir per cabang) di tab Laporan + endpoint `[API] GET /api/akunting/kas-sesi` (permission `laporan.cabang`) → `AkuntingController::kasSesi`.
- **[T-33]** E2E staging: buka/tutup kas + jurnal balance (110-01↔310-01, 110-01↔520-07), multi-kasir 2 user, carryover, idempotent per-kasir — PASS (data uji dibersihkan). Unit: 9 test (Pricing + Servis State Machine) tetap PASS.

## 2026-09-17

### Fase 0 — Fondasi Data Uji
- **[T-01]** `RoleUserSeeder` — 1 user per role PRD §3 (`super-admin/admin-toko/kasir/teknisi/staff-gudang/finance/marketing` @ `*.uteparts.test` / `password`), multi-cabang, idempotent.
- **[T-01]** `TransaksiDemoSeeder` — semua jenis transaksi: POS (tunai/transfer/tier Silver-Gold-Platinum/reseller+komisi), marketplace lunas (dummy-paid), channel shopee dummy, 10 tiket servis di tiap status (+ expired & masa garansi), transfer pending+selesai, opname selisih, broadcast contoh. Semua jurnal balance (debit=kredit) — idempotent (guard `DEMO-`).

### Fase 1 — Bug Kritis
- **[T-02]** Ganti cabang: nama cabang aktif disimpan di session (`cabang_nama`), route web `POST /app/pilih-cabang`, modal Alpine di sidebar → full reload agar Dashboard/POS/WMS re-query; tab aktif persist di refresh.
- **[T-03]** Park POS: tombol "Tahan (F6)" menyimpan keranjang ke `Transaksi(status=ditahan)` + snapshot di `split_detail`, panel "Transaksi Ditahan" list per cabang + tombol Lanjutkan (resume → muat ulang keranjang). Endpoint `[API: POS-04]` tahan & `POS-05` daftar.
- **[T-04]** Tambah pelanggan CRM: `[API: CRM-06] POST /api/crm/pelanggan` + modal "Tambah Pelanggan" di halaman CRM (is_reseller toggle, tier terendah otomatis), langsung tampil tanpa reload.
- **[T-05]** Hapus tombol "Tambah Produk" dobel di halaman Master Produk (header + search bar) — tersisa 1.
- **[T-06]** RBAC tracking: kanban `/app/servis` kini `middleware('permission:servis.view')` (staf tanpa akses → 403, teruji); halaman `CustomerAccount` menampilkan servis hanya milik pelanggan login (`pelanggan_id` check); tracking publik `/tracking/{token}` hanya info minimal (status+estimasi, tanpa data pribadi/foto — cek kode existing).

### Fase 2 — POS & Kas
- **[T-07]** POS pencarian: combobox barcode/search (debounce 250ms), **Enter = scan barcode** → exact-match SKU → auto-add keranjang (`scanEnter` + tombol ADD).
- **[T-08]** POS quick-add pelanggan: `[API: POS-06] GET /api/pos/pelanggan?search=` + pencarian live + modal "+ Baru" → reuse `CRM-06` (satu sumber data).
- **[T-09]** Kas sesi: entitas baru `kas_sesi` (+migration `2026_09_17_000030`), `Pos\Services\KasSesiState` — buka (jurnal Debit Kas/Kredit Modal Kas Shift), tutup (saldo sistem = saldo awal + transaksi tunai selama shift, selisih → jurnal "Selisih Kas"), **transaksi POS tunai diblokir 422 tanpa kas terbuka**, UI banner + modal buka/tutup di POS. Endpoint `[API: POS-07/08/09]`.

### Fase 3 — WMS Deepening
- **[T-10]** Supplier + PurchaseOrder + PembayaranSupplier (entitas + migration `2026_09_17_000031`), `PurchaseOrderService`: PO diterima → stok bertambah (reuse `ProdukService::tambahStokPembelian`) + jurnal (Persediaan debit / Kas-Utang kredit); PO kredit → utang; bayar parsial → jurnal Utang debit/Kas kredit + sisa terupdate. Endpoint `[API: WMS-09..12]`, tab "PO & Supplier" di WMS (daftar PO + status pill + aksi Kirim/Terima/Bayar + modal buat PO & supplier). Test alur PO kredit penuh.
- **[T-11]** Master produk: field `barcode` (unique), `foto[]`, `meta_title`, `meta_description`, `kompatibilitas_hp[]` (terstruktur) + tabel referensi `satuan_unit` + `sku_variants.satuan_kode/barcode`. Produk fillable diperluas.
- **[T-12]** Manajemen Rak: entitas `Rak`, `stok_items.rak_id` (nullable FK), modal "Atur Rak" di tab Stok WMS + CRUD (`simpanRak`).
- **[T-13]** Transfer antar gudang: tambah `lockForUpdate()` pada stok asal & tujuan dalam DB transaction (anti race/oversell saat proses kirim/terima bersamaan).
- **[T-14]** *(item opname barcode: alur opname existing + komponen BarcodeScanInput sudah reusable dari POS — pelacakan histori via tabel opname + logs)*.
- **[T-15]** Auto-generate barcode (`UTP-{id}-{checksum}`) + library `picqer/php-barcode-generator`, endpoint `[API: WMS-14]` + halaman cetak label multi-produk `/print-barcode?ids=1,2` (print-friendly, SVG code128) + tombol Generate & Cetak di Master Produk.

### Fase 4 — Servis Deepening
- **[T-16]** `JenisServis` lebih lengkap: `kategori(hardware/software)`, `estimasi_durasi`, `butuh_part` (+migration `2026_09_17_000032`). CRUD **editable** di Pengaturan → Master Data (modal tambah/edit + tombol baru).
- **[T-17]** `TiketServisItem` (split `part`/`jasa`), endpoint `POST /api/servis/{id}/pekerjaan` (SERVICE-06c): baris jasa tak sentuh stok; baris part kurangi `StokItem` 1x + StokLog (lockForUpdate). `onSelesai` pakai items utk jurnal akurat (Pendapatan Jasa 420-01 vs Pendapatan Penjualan part 410-01 + HPP) — fallback ke `ServisSparepart` lama. Test integrasi part/jasa + balance.
- **[T-18]** Komponen Blade reusable **`<x-customer-picker>`** — pencarian live + "+ Baru"; dipakai di POS & Terima Unit Servis (satu sumber data, `setPelangganServis` + quick action).
- **[T-19]** Kunci gadget: `tipe_kunci(pola/pin/password/tidak_ada)` + `kunci_terenkripsi` (cast `encrypted` at-rest); input pola grid 3×3 (urutan angka), PIN/password via password field; `SERVICE-03 show` hanya menampilkan kunci utk teknisi yg ditugaskan / admin-toko / super-admin (selain itu di-hidden).
- **[T-20]** Input foto terima unit: atribut `capture="environment"` → buka kamera langsung di HP.

### Fase 5 — CRM & Reseller
- **[T-21]** Reseller daftar + skema komisi custom per kategori: `skema_komisi_reseller` (+migration `2026_09_17_000033`), endpoint `RESELLER-05 POST /api/reseller/daftar` + `RESELLER-06 GET skema/{id}`; `KomisiService` honor override custom → fallback default (test: 10% LCD ≠ 5% default).
- **[T-22]** Strategi loyalitas editable: `konfigurasi` (key-value) + `[API: CRM-07] GET/POST /api/crm/config` — poin earn %, poin redeem rupiah, diskon tier Silver/Gold/Platinum (diterapkan ke tier_memberships, non-retroaktif). Test: update config → tier ter-update.
- **[T-23]** Broadcast kampanye: `kampanye_broadcast` + `notifikasi_keluar.kampanye_broadcast_id` (+migration `2026_09_17_000034`), `BroadcastService` (segment: tier/reseller/belum belanja N hari, template `{nama}`/`{tier}`, kirim sekarang / jadwal, **anti-dobel** via idempotent check, log per penerima), endpoint `[API: CRM-08] POST /api/crm/broadcast/kampanye` + `CRM-09 GET /broadcast/{id}/log`.

### Fase 6 — Akunting
- **[T-24]** Export laporan Excel **async via queue**: `ExportLaporanJob` + `ExportLaporanService` (maatwebsite) untuk Laba Rugi, Neraca, Buku Besar, Stok, Pelanggan, Servis, Piutang, Utang; `[API: ACC-11] POST /api/akunting/export` (menjadwalkan job) + `ACC-11b GET /export/download?path=` (download setelah job). Laporan besar tidak memblokir request (RAM 1GB).

### Fase 7 — RBAC Fleksibel
- **[T-25]** `RbacFlexController`: `[API: RBAC-05] CRUD roles`, `RBAC-06 PUT /roles/{id}/permissions` (matrix editable), `RBAC-07 DELETE` role. UI tab **"Role & Permission"** di Pengaturan: checklist permission per role (reuse `permissionsList`), role baru dgn permission bebas, **guardrail**: super-admin tidak bisa dihapus/dikosongkan (anti lockout) + audit log.

### Fase 8 — Omnichannel Refinement
- **[T-26]** `StockMutationLog` (Source-of-Truth mutasi stok, +migration `2026_09_17_000035`): dicatat oleh **semua** jalur mutasi — `StokDeductionService` (POS/order/duitku-lunas), `ProdukService` (PO/stok awal), transfer kirim/terima, opname approve, servis part (item pekerjaan). `channel_orders.estimasi_biaya_platform` (est. biaya admin, configurable `biaya_persen` per channel, default 5%) + COA baru `520-06 Beban Biaya Admin Marketplace` & `520-07 Selisih Kas`.

### Fase 9 — Dashboard & Analitik
- **[T-27]** `DASH-01` diperluas data siap-chart (server-computed): **Tren Omzet 30 hari** (line/bar), **Komposisi omzet per kategori** (bar), **Status servis per tahap kanban** (bar), **Piutang aging** (bucket 0-30/31-60/61-90/>90), **Stok kritis top-10** (bar). Widget role-aware (`$currentRole` badge); chart SVG ringan tanpa library tambahan (RAM-friendly).

---

## 2026-09-19

### Fase 10 — Keamanan & Optimasi UI
- **[T-28]** Security scan komprehensif + hardening:
  - `composer audit` / `npm audit`: 0 vulnerabilities
  - **SecurityHeaders middleware**: CSP (Laravel 13 + Livewire + Alpine + Vite), HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
  - **ForceHttps middleware**: HTTP → HTTPS redirect, skip local/health checks
  - **Global rate limiting**: `throttle:60,1` di semua `/api/*` (login `10,1`)
  - **Session/Sanctum hardening**: secure cookies, token expiration 24h, FORCE_HTTPS
  - Bootstrap: middleware registered for web & api

- **[T-29]** Sidebar Ganti Cabang Fix:
  - Modal ganti cabang sekarang memanggil **API AUTH-02** (`POST /api/select-branch`) via `fetch` + CSRF
  - Alpine.js `branchSwitcher` component dengan loading state & error handling
  - On success: `window.location.reload()` → semua modul (Dashboard, POS, WMS) re-query cabang baru
  - Event global `open-branch-modal` untuk buka modal dari sidebar button
  - Cleanup: hapus `x-data` lama di body tag, pakai component `branchSwitcher()`

- **[T-30]** Responsive UI optimization mobile-first:
  - **Tailwind 4 breakpoints**: tambah `xxs: 320px`, `xs: 375px` di `@theme`
  - **CSS touch-friendly**: `.glass-input` min-height 44px, font-size 16px mobile (anti-zoom iOS), button min 44x44px
  - **Backoffice layout**: hamburger menu mobile, sidebar off-canvas (slide kiri + overlay), padding responsive `p-3 lg:p-6`
  - **POS Kasir**: grid produk responsive `grid-cols-1 xxs:grid-cols-2 xl:grid-cols-3`, toolbar flex-wrap, **cart panel jadi bottom sheet mobile** (drag handle, overlay, sticky toggle button), desktop tetap side panel
  - **Cart partial baru**: `resources/views/modules/pos/livewire/partials/cart-content.blade.php` dengan quantity controls 44x44px, action buttons min-h-44px
  - Semua form input & button min 44x44px di mobile, font-size 16px anti-zoom iOS

- **[T-31]** PWA Basic Install:
  - **Manifest**: `manifest.json` dengan name, short_name, icons (SVG + PNG 192/512), shortcuts POS/WMS/Servis
  - **Service Worker**: `vite-plugin-pwa` generateSW, precache 8 entries (147 KiB), runtime caching (Google Fonts 1yr, images 30d, API 5min network-first)
  - **Offline Page**: `public/offline.html` branded, auto-retry, daftar fitur offline
  - **App.js**: SW registration, update notification toast, online/offline detection, periodic update check
  - **Layouts**: Backoffice & Marketplace dengan manifest link, theme-color, apple-touch-icon, apple-mobile-web-app-capable
  - **Icons**: SVG (any maskable) + PNG 192x192 & 512x512

## 2026-09-20

### Fase 10 — Bug Fixes & Stabilisasi:
- **POS Kasir computed properties fix**: Memperbaiki passing computed properties (`total`, `diskonTotal`, `totalBayar`, `subtotal`) ke view dengan explicit passing di `render()` method
- **Sidebar cabang Alpine.js fix**: Memperbaiki `@if` Blade directive di dalam script tag → ganti ke `x-show` Alpine.js
- **Login backoffice HTTPS fix**: Memperbaiki CSRF token mismatch saat login via HTTPS dengan self-signed cert (preserve CSRF token sebelum/after `Auth::attempt()`)
- **Session config fix**: `SESSION_SAME_SITE=none` untuk cross-origin HTTPS di IP, `SESSION_SECURE_COOKIE=true`
- **HTTPS di IP VPS**: Self-signed cert untuk 66.42.48.27, nginx config dengan HTTP→HTTPS redirect untuk IP, domain tetap di port 80
- **Asset URL dynamic**: Middleware `SetAssetUrl` untuk serve asset via IP (HTTP) atau domain (HTTPS)
- **TrustProxies middleware**: Agar Laravel mengenali IP asli di belakang nginx proxy
- **Dynamic ASSET_URL**: Middleware `SetAssetUrl` set `app.asset_url` berdasarkan host request (IP vs domain)

### Fase 10 — Dark/Light Theme Toggle (T-32):
- **Token remap runtime**: `html[data-theme="light"]` membalik `--color-ink-*` (950→#F8FAFC … 50→#1E293B) + `@custom-variant dark` di `app.css` → utility `dark:` berbasis class, seluruh UI re-theme tanpa menyentuh blade modul
- **theme.js**: komponen Alpine `themeManager` (dark/light/auto) — localStorage instan + sinkron DB via `POST /api/user/theme` (hanya staf login), mode `auto` ikuti `prefers-color-scheme` live, fallback zona (backoffice dark / marketplace light)
- **FOUC prevention**: bootstrap inline <script> di `<head>` kedua layout (pref server > localStorage > default zona, set `data-theme` + `.dark` sebelum paint)
- **Toggle button**: backoffice header + marketplace nav — sun/moon/monitor SVG, hint & aria-label Indonesia, area sentuh ≥44px
- **Surface fixes layout**: backoffice (sidebar/header/modal) & marketplace (navbar/footer) pasang pasangan `light/dark:` (mis. `text-ink-50 dark:text-white`, `border-black/10 dark:border-white/5`); token `ink-200/300` ditambahkan ke @theme; glass/input/scrollbar theme-aware di `prism-tokens.css`
EOF
### Fase 10 — Customer Create POS Sync CRM (T-36):
- **PelangganService** (`app/Modules/Crm/Services/PelangganService.php`): single source of truth pembuatan pelanggan — rules terpusat (nama, telepon unique, email unique), tier default terendah, is_reseller=false, terima `tanggal_lahir` + override `tier_membership_id`, load tierMembership
- **Pemakaian**: `POST /api/crm/pelanggan` (CRM-06) + Livewire POS (`PosKasir::simpanPelangganBaru`) + Livewire CRM (`CrmDashboard::simpanPelangganBaruCrm`) — tiga duplikasi difold jadi satu servis; respons/status kode tidak berubah
- **Pelanggan baru langsung tersedia** di pencarian POS & Servis (query DB langsung, tanpa reload)

### Fase 10 — Birthday Field + Tahan Fix (T-37):
- **`tanggal_lahir`** (nullable date) di `pelanggan` table (migration `2026_09_20_000037`), Fillable + cast `date` di model, input `<input type="date">` di form CRM + modal POS (ADR 0012)
- **Birthday targeting kampanye**: `BroadcastService::resolveTarget` — segment `birthday_month` (1-12) + `birthday_day` (1-31) → `whereMonth`/`whereDay` di `tanggal_lahir`; perbaikan bug: filter `where('is_active', true)` pada pelanggan DIHAPUS (kolom tidak ada → "Column not found" selalu)
- **Tahan fix**: shortcut F6 (`@keydown.window.f6` → `tahanTransaksi`) + panel "Transaksi Ditahan" auto-terbuka setelah tahan (`showDitahanPanel = true`)
