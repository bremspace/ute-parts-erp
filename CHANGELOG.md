# Ute Parts — CHANGELOG

Semua ringkasan task yang selesai dari `implement-plan.md` (pasca-MVP).
Format: `[Fase X] T-XX — ringkasan`.

## 2026-09-20

### Gap Verifikasi Servis — T-17, T-18, T-20
- **[T-17] Form pekerjaan teknisi (part/jasa) di modal detail tiket**
  - Livewire `ServisBoard`: `pekerjaanItems` array, `addPekerjaanRow`/`removePekerjaanRow`/`simpanPekerjaan` → memanggil `ServisService::inputPekerjaan` (endpoint `POST /api/servis/{id}/pekerjaan` sudah ada SERVICE-06c).
  - Blade: grid row `tipe(part/jasa)` + `produk_id picker` (part) + `nama_item` + `qty` + `harga` + hapus baris.
  - Part: `kurangiStokServis` (lockForUpdate 1x) + `StokLog` + `StockMutationLog`; Jasa: hanya tagihan/jurnal (tidak sentuh stok).
  - **Guard anti-dobel stok**: `inputSparepart` (legacy, route `POST /api/servis/{id}/sparepart`) menolak bila sudah ada `TiketServisItem tipe=part` → error "Item sudah dicatat via form pekerjaan".
  - Jurnal `onSelesai` pakai items (Pendapatan Jasa 420-01 vs Pendapatan Penjualan part 410-01 + HPP 510-02/130-01) — fallback ke `ServisSparepart` legacy.
  - Unit test `test_pekerjaan_servis_split_part_jasa`: 15 assertions PASS (part -1 stok, jasa tanpa stok, jurnal balance).

- **[T-18] Quick-add Pelanggan Baru di form terima unit (modal ringkas)**
  - Ganti `openPelangganBaruServis` alert lama → modal (nama, telepon, alamat) submit ke `PelangganService::create` (CRM-06 single source).
  - Reuse `<x-customer-picker>` di form terima unit (sudah dipakai) + hasil quick-add muncul di daftar pencarian langsung.
  - `PelangganService` validasi unique telepon, auto tier terendah, `is_reseller=false` default.

- **[T-20] Input foto galeri + kamera (dual input)**
  - Terima unit: setiap slot foto punya 2 label — "Kamera" (`capture="environment"`) + "Pilih dari galeri" (input biasa).
  - Responsif, fallback galeri bila device tidak support capture. Pertahankan validasi min 2 foto.

### Gap Verifikasi Akunting/Omnichannel/Dashboard — T-24, T-26, T-27
- **[T-24] Export Laporan — jenis `arus_kas` + GET/POST + Notifikasi persist**
  - Controller (`AkuntingController::export`): validasi `jenis` tambah `arus_kas`; input ambil via `$request->input()` biar GET/POST ambil sama; response: notifikasi + link download `/api/akunting/export/download` (route :149 sudah ada). **Route GET `/api/akunting/export` BELUM didaftarkan** (di luar scope fixer) — frontend butuh `Route::match(['get','post'])` agar query param `?jenis=&periode_dari=&periode_sampai=` jalan.
  - Service (`ExportLaporanService::dataArusKas`): query kas masuk/keluar per hari dari jurnal akun `110-01` (kas) — sederhana, RAM-friendly.
  - Job (`ExportLaporanJob`): **ganti session flash rapuh** dengan `NotificationService::kirim('inapp', ...)` → persist `notifikasi_keluar` + queue (DB driver + Supervisor), payload berisi `url` download (base64 path). Error juga lewat notifikasi.
- **[T-26] SOT Stok + Biaya Admin Marketplace**
  - `ChannelSyncService::syncStokSemuaChannel`: **baca `StockMutationLog` urut `terjadi_at`** (delta kumulatif per produk+gudang) **BUKAN `StokItem::sum`** — konsisten dengan buku mutasi. Serialized per SKU (lock mapping) tetap dipertahankan. `StockMutationLog` dipakai via `get()->sum('delta')` (ponytail: incremental watermark).
  - `ChannelOrder` model: tambah `estimasi_biaya_platform` ke `Fillable` (sebelum mass-assign drop → simpan 0).
  - `ChannelSyncService::prosesBiayaAdmin(ChannelOrder)`: jurnal **Debit 520-06 "Beban Biaya Admin Marketplace" / Kredit 110-01 Kas** (COA firstOrCreate fallback, pola `KasSesiState`). **Idempoten**: `referensi_tipe='channel_order' + referensi_id` unik → duplikat callback/webhook tidak dobel posting.
  - `ChannelSyncService::pullOrders`: status `selesai` → auto `prosesBiayaAdmin`.
  - `OmnichannelController::webhook`: `estimasi_biaya_platform` dihitung dari payload + `kredensial['biaya_persen']` (fallback 5%); status `COMPLETED` → update `selesai` + `prosesBiayaAdmin` (idempoten). Duplicate webhook `COMPLETED` juga panggil.
- **[T-27] Dashboard Per Role**
  - `DashboardIndex`: `limit(8)→10` stok kritis; 4 computed properties role-scoped (lazy eval):
    - `kasir` → `omzetShiftKasir`: omzet shift sendiri dari `KasSesiState::sesiKasAktif()` (scoped `kasir_id` + `created_at >= dibuka_at`), tanpa sesi fallback hari ini.
    - `finance` → `ringkasanKeuangan`: laba bersih bulan ini (jurnal) + total piutang + lewat tempo.
    - `marketing` → `marketInsight`: komposisi tier pelanggan (server `groupBy`) + performa broadcast (dari `notifikasi_keluar`).
    - `staff-gudang` → `poPending`: PO status `draft|dikirim` (Wms PO status valid) per cabang.
  - Blade (`dashboard-index.blade.php`): render via `@role('kasir|finance|marketing|staff-gudang')` — hanya widget role terkini dievaluasi (query ringan). **Chart lib tidak ditambah** — SVG/CSS server-computed (aman RAM 1GB).
- **PERLU `StockMutationLog` di (diluar scope saya — laporan ke owner):**
  - `ServisService:226-240` (`inputSparepart`) — kurangi StokItem tanpa `StockMutationLog`.
  - `PosController:263` (transaksi selesai API) — item created, deduct stok via service/loop, tanpa log mutasi.
  - `PosKasir:389` (Livewire POS) — deduct StokItem langsung tanpa `StockMutationLog`.
- **Route dibutuhkan (scope luar fixer):**
  - `Route::match(['get','post'], '/akunting/export', [AkuntingController::class, 'export'])` agar GET query param jalan (sekarang hanya POST di routes/api.php:148).
- **DDL usulan (hanya catatan — JANGAN jalankan migrate fresh):**
  - `notifikasi_keluar`: opsional `user_id` kolom (PK foreign) bila perlu query per user cepat; sekarang `payload['user_id']` di JSON cukup.
  - `channel_orders.estimasi_biaya_platform` sudah ada via migration `000035`.

### Gap Verifikasi POS — T-07, T-09, T-18 (reuse customer-picker)
- **[T-07]** `GET /api/pos/produk` (`PosController::products`, API POS-02) — mode ringkas utk autocomplete/debounce via parameter baru `q` (alias `query`): respons ±6 field (`id`, `nama`, `sku`, `harga`, `stok`, `foto`), server-side `limit 10`, harga via `PricingService` (tier/reseller tetap berlaku). Jalur legacy param `search` + paginasi 24 tidak diubah → kompatibel pemakaian existing. SKU diambil dari varian aktif pertama; `foto` = `foto[0]` (gallery T-11) fallback `gambar`.
- **[T-09]** Livewire `PosKasir::processTransaction` kini validasi sesi kas utk `metode_bayar=tunai` (`KasSesiState::isActiveSesi()`, sama dgn API POS-01 `PosController:184-190`) → error + paksa buka modal buka kas (`bukaKasModal()`), transaksi dibatalkan. Auto-open modal buka kas saat `mount()` bila sesi tidak aktif (fallback `isActiveSesi()` = true saat tabel `kas_sesi` belum ada → tidak muncul). Non-tunai (transfer/qris/piutang/split) tidak divalidasi — sesuai aturan existing API.
- **[T-18]** Blok pencarian/tambah pelanggan inline di `pos-kasir.blade.php` (187-239) diganti `<x-customer-picker>` (reusable, sama seperti Servis `servis-board.blade.php:182`): `wireModel="pelangganSearch"`, `selectAction="setPelanggan"`, `addAction="openPelangganBaru"`, `:results="$pelangganCari"`. Tambah method `PosKasir::openPelangganBaru()` (reset form + buka modal create CRM-06); `simpanPelangganBaru` via `PelangganService` tetap dipakai → hasil langsung terpilih (`setPelanggan`) + muncul di keranjang. Select "Pelanggan Umum"/daftar member & tombol reset ✕ dipertahankan.
- Route dibutuhkan (sudah ada, tidak diubah): `GET /api/pos/produk` (`routes/api.php:63`, `permission:pos.view`). Verifikasi: `php -l` OK, `artisan view:cache` PASS, Pint 1 fix (import `KasSesiState`).

### Rilis Fase 10 ke PROD (T-28..T-37 ✅)
- **Rilis 635c66d live di prod** (`https://66.42.48.27`) — seluruh Fase 10: Sanctum SPA auth fix (statefulApi+session), T-32 theme toggle, T-33 kas-akunting, T-34 ribuan, T-35 thermal print, T-36 customer sync, T-37 birthday+tahan.
- Migrate prod DONE (3 kolom baru: `users.theme_preference`, `kas_sesi.sumber`, `pelanggan.tanggal_lahir`); `npm run build` + config/route/view:cache; queue workers restart via `supervisorctl restart ute-parts-queue:*`.
- **Verifikasi live prod PASS**: /shop, /app/login, /manifest.json, /offline.html → 200; /app/pos → 302 (auth); login 302 → /app/pos; `POST /api/user/theme` 200 (DB=dark); `POST /api/select-branch` 200; build assets 200.
- **Auto-deploy DIMATIKAN** (permintaan user): timer `ute-parts-deploy.timer` stop+disable → rilis prod manual.
- **deploy.sh 2 bug difix** (commit `c47ef10`): (a) `rm -rf` sebelum `mv public/build` (cegah Permission denied → 6× deploy mati senyap di 10:26–10:46); (b) early-exit "Tidak ada perubahan" kini memulihkan build yang dipindah ke backup. Uji ulang PASS ("Pulihkan public/build dari backup").
- Sisa plan: tidak ada — Fase 10 = fase terakhir implement-plan.md; semua tiket T-28..T-37 status done.

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

### Fase 10 — PWA Gap Fix (T-31):
- **Dead SW registration dihapus**: `resources/js/app.js` registrasi manual `navigator.serviceWorker.register('/sw.js')` (404 — SW asli di `/build/sw.js`) dihapus. Registrasi kini SATU jalur: `import { registerSW } from 'virtual:pwa-register'` (VitePWA generateSW) + `injectRegister: 'script-defer'` (file `registerSW.js` tetap di-generate sebagai artifact, tanpa script-tag injection karena Laravel tidak punya HTML build-time). Update-notification custom dihapus (registerType autoUpdate sudah handle), online/offline toast dipertahankan
- **Offline fallback ter-wire**: `workbox.navigateFallback: '/offline.html'` + `navigateFallbackDenylist: [/^\/api\//, /^\/build\//]` + `additionalManifestEntries: [{ url: '/offline.html', revision: null }]` → `public/offline.html` ter-precache (build: precache 10 entries, termuat `/offline.html`)
- **Cache strategy sesuai AC**: runtimeCaching baru `assets-cache` StaleWhileRevalidate untuk `/\.(?:css|js|mjs)$/` (30d); API tetap NetworkFirst (5min, timeout 10s); fonts Google CacheFirst 1yr; images CacheFirst 30d
- **Build diverifikasi** (`npm run build`): `public/build/sw.js` mengandung navigateFallback `/offline.html` + denylist api/build + StaleWhileRevalidate + NetworkFirst; `public/build/registerSW.js` ada; manifest (public/manifest.json + build/manifest.webmanifest) lengkap — name, short_name, icons 192/512, theme_color `#5B4FE9`, background_color, display standalone
- **Known limitation (belum di scope)**: SW scope = `/build/` (base laravel-vite-plugin). Untuk kontrol penuh `/app/*`, perlu header `Service-Worker-Allowed: /` di nginx untuk `/build/sw.js` lalu set `scope: '/'` di VitePWA config

## 2026-09-20 (Gap Verifikasi WMS/Marketplace)

### Gap Verifikasi WMS/Marketplace — T-10, T-11, T-12, T-13, T-14
- **[T-10] Selesaikan alur supplier+PO lengkap**
  - **WmsController**: `updateSupplier` (PUT), `destroySupplier` (DELETE) — WMS-09 CRUD lengkap. `storeSupplier` (POST) sudah ada. `supplier` (GET) index sudah ada.
  - **PurchaseOrderService::terimaBarang**: sekarang `postJurnal=false` di `tambahStokPembelian` (hindari dobel jurnal), jurnal agregat diposting di service: **Debit Persediaan 130-01 / Kredit Utang 210-01 (kredit) ATAU Kas 110-01 (tunai)** — balance. PO kredit → **Otomatis buat record `Utang` (modul Akunting)** dengan `referensi_tipe=PurchaseOrder`, `kreditor_nama=supplier.nama`, `jumlah=totalHPP`, `jatuh_tempo=po.jatuh_tempo`. PO tunai → tidak buat Utang.
  - **PurchaseOrderService::bayarPO**: selain jurnal Utang debit/Kas kredit, **update record `Utang` (jumlah_dibayar, status → sebagian/lunas)**. Model `Utang` sudah ada (`app/Modules/Akunting/Models/Utang.php` + migration `2026_09_16_000026`), kolom: `po_id` via `referensi_id`, `pelanggan/supplier` via `kreditor_nama`, `jumlah`, `jatuh_tempo`, `sisa` (accessor), `status`.
  - Endpoint `WMS-11` (terima barang) akan didaftarkan pusat; method controller `updatePoStatus(action=diterima)` siap.

- **[T-11] Marketplace filter kompatibilitas HP dari `kompatibilitas_hp[]` JSON**
  - `ShopPage.php`: tambah computed property `hpMerkList` (ekstrak merk unik dari JSON produk) + `hpModelList` (ekstrak model, filtered by merk jika dipilih). `getProductsProperty()`: filter via `JSON_CONTAINS(kompatibilitas_hp, ['merk'=>...])` / `['model'=>...]` — **prioritaskan data terstruktur**; legacy `brand_kompatibel`/`model_kompatibel` tetap dipertahankan (tidak dihapus).
  - `shop-catalog.blade.php`: tambah 2 dropdown "Merk HP" & "Model HP" di filter bar (6 kolom grid), `wire:model.live="filterHpMerk"` + `filterHpModel`.

- **[T-12] Rak CRUD + field rak_id di form stok/transfer/opname**
  - Migration `2026_09_20_000040_add_wms_rak_opname_lock`: `stok_transfer_item.rak_id`, `stok_opname.rak_id`, `stok_opname_item.rak_id`, COA `520-08 Selisih Stok (Opname)`.
  - **WmsController**: `indexRak` (GET scope cabang), `storeRak`, `updateRak`, `destroyRak` — CRUD lengkap, guard relasi (stok/transfer/opname terkait → tidak bisa hapus).
  - **WmsDashboard (Livewire)**: `tambahStokForm.rak_id` → dikirim ke `ProdukService::tambahStokPembelian(rakId)` → `firstOrCreate` dengan `rak_id`. Transfer form: item row ada dropdown `rak_id` (filter gudang tujuan). Opname form: `opnameRakId` (opsional scope sesi per rak).
  - Blade `wms-dashboard`: select Rak di modal Tambah Stok, Transfer item row, Opname modal.

- **[T-13] Transfer pending → stok asal terkunci (guard oversell)**
  - `StokTransfer::pendingLockedByGudang($gudangId, $excludeId?)` & `pendingLockedFor(StokItem, $excludeId?)`: hitung qty draft transfer per produk/varian di gudang asal.
  - `StokDeductionService::kurangi`: **guard cek pending locked** — throw `Exception` "stok tidak mencukupi karena X unit terkunci transfer pending" bila `stok->jumlah - locked < qty`. POS, Servis, Marketplace order semua lewat service ini → terproteksi.
  - `WmsController::stok` (API WMS-01): tambah `stok_dikunci` virtual attribute per item (qty draft pending).
  - `WmsController::kirimTransfer`: guard lock exclude current transfer (allow kirim own draft).
  - Pertahankan `DB::transaction + lockForUpdate` existing.

- **[T-14] Opname scope rak + jurnal penyesuaian balance**
  - `storeOpname`: optional `rak_id` (scope sesi per rak). `inputOpnameItems`: per-item `rak_id` (scope per item), `stok_sistem` dibaca dari `StokItem` per rak.
  - `StokOpname` model: relasi `rak()` + `items()` sudah terupdate.
  - `approveOpname` (WmsController): selisih ≠ 0 → **buat jurnal balance pakai `JurnalService`** (service yang sudah ada). Pola: `selisih>0` (fisik>sistem) → **Debit 130-01 Persediaan / Kredit 520-08 Selisih Stok**; `selisih<0` (fisik<sistem) → **Debit 520-08 / Kredit 130-01**. `StokLog` + `StockMutationLog` tetap tercatat per item. COA `520-08` di-create via migration.

- **Tests**: 38 passed (187 assertions) — termasuk `test_po_kredit_diterima_dan_dibayar`, `test_tambah_produk_dengan_stok_awal_membuat_jurnal_pembelian`, `test_halaman_marketplace_render`.

### ROUTES DIBUTUHKAN (scope luar fixer, catat di sini untuk owner):
- `Route::put('/wms/supplier/{id}', [WmsController::class, 'updateSupplier'])->middleware('permission:wms.update');`
- `Route::delete('/wms/supplier/{id}', [WmsController::class, 'destroySupplier'])->middleware('permission:wms.delete');`
- `Route::get('/wms/rak', [WmsController::class, 'indexRak'])->middleware('permission:wms.view');`
- `Route::post('/wms/rak', [WmsController::class, 'storeRak'])->middleware('permission:wms.create');`
- `Route::put('/wms/rak/{id}', [WmsController::class, 'updateRak'])->middleware('permission:wms.update');`
- `Route::delete('/wms/rak/{id}', [WmsController::class, 'destroyRak'])->middleware('permission:wms.delete');`
- `Route::put('/wms/transfer/{id}/kirim', ...)` — sudah ada (WMS-03)
- `Route::put('/wms/transfer/{id}/terima', ...)` — sudah ada (WMS-04)
- `Route::put('/wms/po/{id}/status', ...)` — sudah ada (WMS-10/11)
- `Route::post('/wms/po/{id}/bayar', ...)` — sudah ada (WMS-12)

### Gap Verifikasi CRM/Reseller/Seeder — T-01, T-22, T-23, T-36
- **[T-01] TransaksiDemoSeeder: lengkap semua jenis transaksi + jurnal balance**
  - Tambah PO Supplier kredit & tunai (`PurchaseOrder`, `PurchaseOrderItem`) + `PembayaranSupplier` parsial untuk kredit + jurnal yang balance:
    - PO kredit: 130-01 debit / 210-01 kredit → bayar parsial: 210-01 debit / 110-01 kredit
    - PO tunai: 130-01 debit / 110-01 kredit
  - Servis `onSelesai` jurnal otomatis via `ServisService::updateStatus` (state machine transitions): 3 jurnal untuk 3 tiket 'selesai' (Kas 110-01 / Pendapatan Jasa 420-01).
  - Transfer gudang: existing (tanpa jurnal, hanya stok log).
  - Stock Opname: selisih -1 → jurnal **520-07 (Selisih Kas) debit / 130-01 (Persediaan) kredit** @ HPP produk.
  - Docblock updated: "semua jenis transaksi + jurnal".
  - Verified: **ALL JURNALS BALANCED ✓**, idempotent (`sudahAdaDemo` + `firstOrCreate` guards).

- **[T-22] Pengaturan "Strategi Loyalitas" di SettingsRbac (halaman RBAC)**
  - Tab baru `loyalitas` di `settings-rbac.blade.php` + state di `SettingsRbac.php` (`loyalitasForm`, `skemaKomisiForm`, `mount()`, `simpanLoyalitas()`).
  - Form: rasio earn poin (% transaksi), rasio redeem (1 poin = Rp X), diskon % per tier (Silver/Gold/Platinum), skema komisi default (persen/nominal per kategori).
  - GET `/api/crm/config`: **baca nilai TERSIMPAN dari tabel `konfigurasi`**, fallback `defaults()` bila NULL (tidak hardcode).
  - PUT `/api/crm/config`: simpan ke `konfigurasi` + update `TierMembership.diskon_persen` + `SkemaKomisi.nilai`.
  - Non-retroaktif: komentar di blade + controller.
  - Fix: `KonfigurasiService::set()` ganti `updateOrInsert` dengan `COALESCE` subquery (MySQL error) → dua step `update`/`insert`.

- **[T-23] Eksekusi broadcast terjadwal via scheduler**
  - Command `crm:broadcast-terjadwal` (`app/Modules/Crm/Console/Commands/EksekusiBroadcastTerjadwal.php`): ambil `KampanyeBroadcast` status `terjadwal` & `dijadwalkan_at <= now()` → `BroadcastService::kirimSekarang()` (channel WA/Email/InApp, personalisasi `{nama}`/`{tier}`) → update status + log per penerima (`NotifikasiKeluar.kampanye_broadcast_id`).
  - Daftar di `routes/console.php` + `bootstrap/app.php` schedule tiap menit (`->everyMinute()`).
  - Queue-only: notifikasi lewat `NotificationService::kirim` → `KirimNotifikasiJob` (DB queue + Supervisor), **tidak sync di request**.

- **[T-36] ResellerController::daftarReseller pakai PelangganService::create**
  - Ganti `Pelanggan::create` langsung → `PelangganService::create()` (single source of truth).
  - Validasi terpusat: `nama`, `telepon` unique, `email` unique, `tanggal_lahir`, `tier_membership_id` optional, `is_reseller=true` tetap dipertahankan.
  - Custom skema komisi per kategori tetap disimpan ke `skema_komisi_reseller`.

