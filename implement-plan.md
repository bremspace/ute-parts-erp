# IMPLEMENT PLAN — Update Fitur & Optimasi Ute Parts (Pasca-MVP)
**Versi:** 1.0 | **Tanggal:** 17 September 2026
**Rujukan wajib:** `PRD-Frontend-UteParts.md` & `PRD-Backend-UteParts.md` (sudah diimplementasikan). Dokumen ini **tidak menggantikan** kedua PRD tsb — ini adalah daftar perubahan incremental di atas kode yang sudah ada. Semua nama entitas & kode `[API: ...]` yang sudah ada **wajib dipakai apa adanya**; entitas/endpoint baru diberi kode baru dengan pola penamaan yang sama.

---

## 0. Cara Pakai Dokumen Ini (untuk Agentic AI / Coding Agent)

1. **Jangan mengerjakan semua task sekaligus.** Kerjakan **per Fase**, berurutan (Fase 0 → Fase 9), karena beberapa fase saling bergantung (contoh: Fase 3 WMS harus selesai dulu sebelum Fase 4 Servis yang butuh sinkron part ke stok).
2. Sebelum mengubah kode, **baca dulu kode existing** di modul yang relevan (`app/Modules/{nama}`) — jangan menulis ulang dari nol kalau modulnya sudah ada, cukup diperluas/diperbaiki.
3. Setiap task punya kode `T-XX`. Setelah selesai satu task, jalankan test/manual-check yang tercantum di "Acceptance Criteria" sebelum lanjut ke task berikutnya.
4. Setiap task yang menyentuh alur uang/stok **wajib** dicek dampaknya ke Akunting (§4.6 PRD Backend) — jangan sampai ada mutasi stok atau uang yang tidak tercatat jurnal.
5. Pakai MCP yang sama seperti saat build awal: **Context7** (cek API Laravel 13/Livewire terbaru), **Sequential Thinking** (untuk task yang mengubah state machine/alur akunting), **Playwright** (verifikasi UI setelah bug fix), **DB MCP** (cek data hasil seeder & migrasi langsung ke MySQL).
6. Setelah **setiap Fase selesai**, update file `CHANGELOG.md` di root project (buat jika belum ada) dengan ringkasan task yang selesai — supaya progress bisa dipantau lintas sesi kerja panjang.

---

## 1. Peta Fase → Task

| Fase | Fokus | Task |
|---|---|---|
| 0 | Fondasi data uji | T-01 |
| 1 | Bug kritis (blocker harian) | T-02, T-03, T-04, T-05, T-06 |
| 2 | POS & Kas | T-07, T-08, T-09 |
| 3 | WMS Deepening | T-10, T-11, T-12, T-13, T-14, T-15 |
| 4 | Servis Deepening | T-16, T-17, T-18, T-19, T-20 |
| 5 | CRM & Reseller | T-21, T-22, T-23 |
| 6 | Akunting | T-24 |
| 7 | RBAC Fleksibel | T-25 |
| 8 | Omnichannel Refinement | T-26 |
| 9 | Dashboard & Analitik | T-27 |

---

## FASE 0 — Fondasi Data Uji

### T-01 — Seeder per Role & Seeder Semua Jenis Transaksi
*(Request #1)*

**Kebutuhan:** Butuh data uji supaya bisa memverifikasi batasan akses tiap role dan semua alur transaksi tanpa input manual berulang.

**Perubahan:**
- `database/seeders/RoleUserSeeder.php` — buat 1 user contoh per role di §3 PRD Backend (`super-admin`, `admin-toko`, `kasir`, `teknisi`, `staff-gudang`, `finance`, `marketing`), masing-masing dengan kredensial jelas (`kasir@uteparts.test` dst) dan akses ke minimal 2 cabang untuk user multi-cabang.
- `database/seeders/TransaksiDemoSeeder.php` — generate transaksi contoh untuk **setiap** jenis transaksi yang bisa dilakukan aplikasi: POS tunai, POS non-tunai, POS dengan pelanggan member tiap tier, POS reseller (dengan komisi), transaksi marketplace (dengan Duitku dummy-paid), transaksi channel omnichannel dummy, tiket servis di tiap status (termasuk yang expired garansi & yang masih garansi), transfer antar gudang (pending & selesai), stock opname dengan selisih, PO ke supplier (kredit & tunai — lihat T-10), broadcast CRM contoh.
- Pastikan seeder **idempotent** (`php artisan db:seed` bisa dijalankan ulang tanpa duplikat/error) dan jurnal akunting ikut ter-generate otomatis dari transaksi seeder (bukan di-skip), supaya laporan keuangan juga bisa langsung dicek.

**Acceptance Criteria:**
- [x] `php artisan migrate:fresh --seed` berjalan tanpa error, menghasilkan data untuk semua role & semua jenis transaksi di atas.
- [x] Login dengan tiap user role menghasilkan menu sidebar yang sesuai matrix §3 PRD Backend (tidak lebih, tidak kurang).
- [x] Saldo di Akunting (Buku Besar) balance (debit = kredit) setelah seeder jalan.

---

## FASE 1 — Bug Kritis (Blocker Harian)

### T-02 — Perbaiki Menu Ganti Cabang di Sidebar
*(Request #3)*
**Modul:** Auth/Session — `[API: AUTH-02]`
**Diagnosis awal:** Kemungkinan besar dropdown ganti cabang di sidebar hanya update state Livewire lokal tapi tidak memanggil ulang `AUTH-02 POST /api/select-branch`, atau memanggil endpoint tapi query di modul lain (POS/WMS/dsb) masih pakai cabang lama dari cache session yang tidak di-refresh.
**Perubahan:** Pastikan event ganti cabang: (1) memanggil `AUTH-02`, (2) me-refresh session `cabang_aktif_id`, (3) trigger `wire:navigate`/reload komponen yang bergantung pada cabang (dashboard, POS, WMS) agar tidak nyangkut data cabang lama.
**Acceptance Criteria:**
- [x] Ganti cabang di sidebar langsung mengubah data yang tampil di Dashboard, POS, dan WMS tanpa perlu logout/login ulang.
- [x] Refresh halaman tetap mempertahankan cabang aktif yang baru dipilih.

### T-03 — Perbaiki Tombol "Tahan" (Park) di POS
*(Request #6)*
**Modul:** POS — `[API: POS-01]`, perlu endpoint baru `POS-04`
**Diagnosis awal:** Kemungkinan tombol tahan hanya UI state tanpa endpoint backend yang menyimpan transaksi berstatus `ditahan`, sehingga hilang saat halaman refresh atau berpindah kasir.
**Perubahan:**
- Tambah `[API: POS-04] POST /api/pos/transaksi/{id}/tahan` dan `POS-05 GET /api/pos/transaksi?status=ditahan` untuk daftar transaksi tertahan per cabang (bukan per kasir, agar kasir lain bisa lanjutkan).
- UI: tombol "Tahan" (F6) menyimpan keranjang saat ini ke backend berstatus `ditahan`, menyediakan panel "Transaksi Tertahan" untuk resume.
**Acceptance Criteria:**
- [x] Transaksi yang ditahan tetap ada meski browser di-refresh atau kasir logout.
- [x] Transaksi tertahan bisa dilanjutkan oleh kasir lain di cabang yang sama.

### T-04 — Perbaiki Menu Tambah Pelanggan di CRM
*(Request #17)*
**Modul:** CRM — perlu endpoint baru `CRM-06`
**Perubahan:** Tambah `[API: CRM-06] POST /api/crm/pelanggan` (create customer dari backoffice), sambungkan tombol "Tambah Pelanggan" yang sudah ada di UI CRM (§5.6 PRD Frontend) ke endpoint ini. Field minimal: nama, no HP (unique), alamat, tier awal (default tier terendah).
**Acceptance Criteria:**
- [x] Tombol tambah pelanggan di CRM berhasil membuat data baru dan langsung muncul di daftar pelanggan tanpa reload manual.
- [x] Data pelanggan baru ini juga langsung tersedia untuk dicari di POS (T-08) dan modul Servis (T-17) — satu sumber data, bukan tabel terpisah.

### T-05 — Hilangkan Tombol Tambah Produk yang Dobel
*(Request #27)*
**Modul:** WMS — halaman Master Produk
**Perubahan:** Cek Blade/Livewire komponen halaman master produk, kemungkinan tombol dirender 2x karena komponen header duplikat atau leftover dari refactor. Hapus salah satu, pastikan hanya 1 entry point "Tambah Produk".
**Acceptance Criteria:**
- [x] Hanya ada 1 tombol "Tambah Produk" di halaman master produk, fungsinya tetap normal.

### T-06 — Batasi Akses Tracking Status Servis (Kanban)
*(Request #11)*
**Modul:** Servis — `[API: SERVICE-01..06]`, terhubung RBAC
**Masalah keamanan:** Saat ini kemungkinan link tracking servis publik bisa diakses siapa saja yang tahu URL/token, atau kanban internal tidak dicek kepemilikan.
**Perubahan:**
- Kanban internal (`/app/servis`) — wajib lolos middleware RBAC `servis.view` (staf saja), **tidak pernah** diakses tanpa login.
- Halaman tracking publik (`/account/servis/{token}` atau serupa) — token unik per tiket **tidak cukup** sebagai satu-satunya proteksi (bisa di-share/tebak). Tambahkan: jika pelanggan sudah login, tracking hanya tampil untuk tiket miliknya sendiri (cek `pelanggan_id` = user login); token publik (tanpa login, untuk approve estimasi cepat sesuai §4.3) hanya menampilkan **info minimal** (status & estimasi biaya), bukan detail lengkap (data pribadi, foto unit, dsb).
**Acceptance Criteria:**
- [x] User tanpa akses `servis.view` mendapat 403 saat akses kanban internal.
- [x] Pelanggan A tidak bisa melihat detail lengkap servis milik pelanggan B walau tahu/menebak URL.
- [x] Link approve estimasi via token tetap berfungsi tanpa login (sesuai §4.3), tapi hanya menampilkan info minimal.

---

## FASE 2 — POS & Kas

### T-07 — Pencarian Produk Berbasis Suggestion + Fungsikan Barcode Scanner
*(Request #4)*
**Modul:** POS — `[API: POS-02]`
**Perubahan:**
- `POS-02` diperluas: tambah parameter debounce-friendly, response ringkas untuk autocomplete (nama, SKU, harga, stok, foto kecil), server-side limit hasil (misal 10) agar cepat dengan >1000 SKU.
- Frontend: ubah input pencarian POS jadi combobox dengan suggestion dropdown live-search (Alpine.js `x-model` + debounce 200-300ms), tekan Enter/klik untuk menambah ke keranjang.
- Wire `BarcodeScanInput` (komponen sudah didefinisikan di §3 PRD Frontend tapi belum tersambung) ke input pencarian — hasil scan barcode langsung query exact-match ke `POS-02` dan auto-add ke keranjang jika stok tersedia.
**Acceptance Criteria:**
- [x] Ketik 2-3 huruf nama produk langsung muncul suggestion relevan dalam <500ms.
- [x] Scan barcode fisik/kamera langsung menambahkan produk ke keranjang tanpa langkah tambahan.

### T-08 — Pencarian & Quick-Add Pelanggan di POS (Sinkron CRM)
*(Request #5)*
**Modul:** POS + CRM — perlu endpoint baru `POS-06`, pakai ulang `CRM-06` (T-04)
**Perubahan:** Tambah `[API: POS-06] GET /api/pos/pelanggan?search=` (cari by nama/no HP). Tambah tombol "+ Pelanggan Baru" di panel pelanggan POS yang membuka modal ringkas, submit ke `CRM-06` yang sama dengan T-04 (jangan buat endpoint create pelanggan terpisah — satu sumber kebenaran).
**Acceptance Criteria:**
- [x] Kasir bisa cari pelanggan existing dan pelanggan baru yang dibuat dari POS langsung muncul di CRM (§5.6) tanpa proses sinkron tambahan.

### T-09 — Sistem Buka/Tutup Kas (Cash Session), Sinkron Akunting
*(Request #7)*
**Modul baru:** `KasSesi` (entitas baru, tambahkan ke §2 Kamus Data PRD Backend)
**Perubahan:**
- Entitas `KasSesi`: `id, cabang_id, user_id, saldo_awal, saldo_akhir_sistem, saldo_akhir_fisik, selisih, status(buka/tutup), dibuka_at, ditutup_at`.
- `[API: POS-07] POST /api/pos/kas/buka` — input saldo awal, buat `JurnalAkuntansi` (debit Kas, kredit "Modal Kas Awal Shift" atau saldo dari shift sebelumnya jika ada).
- `[API: POS-08] POST /api/pos/kas/tutup` — input saldo fisik akhir hasil hitung kasir, sistem hitung `saldo_akhir_sistem` dari akumulasi transaksi tunai selama sesi, hitung `selisih`, buat jurnal penyesuaian jika ada selisih (debit/kredit akun "Selisih Kas").
- **Wajib:** transaksi POS tunai (`POS-01`) tidak bisa dibuat jika belum ada `KasSesi` berstatus `buka` untuk kasir/cabang tsb — validasi di awal alur POS.
- UI: modal wajib buka kas saat kasir pertama kali masuk POS di hari itu; tombol "Tutup Kas" di akhir shift menampilkan rekap otomatis vs input fisik.
**Acceptance Criteria:**
- [x] Tidak bisa transaksi tunai di POS tanpa sesi kas terbuka.
- [x] Tutup kas menghasilkan jurnal otomatis yang balance, termasuk saat ada selisih.
- [x] Laporan Akunting (§4.6) menampilkan riwayat sesi kas per cabang.

---

## FASE 3 — WMS Deepening

### T-10 — Manajemen Supplier + PO + Pembayaran (Kredit/Tunai), Sinkron Akunting & Stok
*(Request #8)*
**Entitas baru (tambahkan ke §2 Kamus Data):** `Supplier`, `PurchaseOrder` (PO), `PurchaseOrderItem`, `PembayaranSupplier`.
**Perubahan:**
- `Supplier`: nama, kontak, alamat, termin default (hari).
- `PurchaseOrder`: `supplier_id, gudang_tujuan_id, status(draft/dikirim/diterima-sebagian/diterima/dibatalkan), metode_bayar(tunai/kredit), jatuh_tempo`.
- Saat PO berstatus `diterima` (barang masuk) → `StokItem` gudang tujuan bertambah otomatis + jurnal (debit Persediaan, kredit Kas jika tunai atau kredit Utang Usaha jika kredit).
- Jika metode kredit → masuk ke modul Utang (AP, sudah ada di §4.6) dengan jatuh tempo dari termin supplier; `PembayaranSupplier` mencatat pelunasan (parsial/penuh), tiap pembayaran → jurnal (debit Utang Usaha, kredit Kas/Bank).
- `[API: WMS-09] /api/wms/supplier` (CRUD), `WMS-10 /api/wms/po` (CRUD + ubah status), `WMS-11 POST /api/wms/po/{id}/terima-barang`, `WMS-12 POST /api/wms/po/{id}/bayar`.
**Acceptance Criteria:**
- [x] Barang masuk dari PO otomatis menambah stok gudang yang benar & membuat jurnal yang balance.
- [x] PO kredit muncul di modul Piutang/Utang sebagai utang dengan jatuh tempo benar.
- [x] Riwayat pembayaran PO (parsial) tercatat dan sisa utang terupdate otomatis.

### T-11 — Lengkapi Form Master Data Produk + SEO Friendly
*(Request #9)*
**Modul:** WMS — Master Produk
**Perubahan:** Tambah field ke `Produk`/`SkuVariant`: `satuan` (pcs/box/unit, dst — buat tabel referensi `SatuanUnit` agar konsisten), `barcode` (unique, lihat juga T-15 auto-generate), `foto[]` (multi-foto, resize sesuai §7 PRD Backend), `slug` (auto dari nama, unique, dipakai di URL marketplace `[API: SHOP-02]`), `meta_title`, `meta_description`, `kompatibilitas_hp[]` (sudah disebut di §6.2 PRD Frontend, pastikan benar-benar tersimpan sebagai data terstruktur, bukan teks bebas, agar filter kompatibilitas di marketplace akurat).
**Acceptance Criteria:**
- [x] Semua field baru muncul & tersimpan di form master produk, wajib validasi (barcode unique, slug unique).
- [x] URL produk di marketplace pakai slug SEO-friendly (`/produk/lcd-iphone-11-original` bukan `/produk/123`).
- [x] Filter kompatibilitas HP di katalog marketplace (§6.2) berfungsi dari data terstruktur ini.

### T-12 — Manajemen Rak, Terhubung ke WMS
*(Request #10)*
**Entitas baru:** `Rak` (Lokasi/Bin), tambahkan `rak_id` (nullable) ke `StokItem`.
**Perubahan:** `[API: WMS-13] /api/wms/rak` (CRUD rak per gudang). Update form stok masuk, transfer, dan stock opname (§4.1) agar bisa pilih/assign rak. Jika manajemen rak versi sebelumnya sudah ada tapi terpisah, migrasikan datanya ke relasi `StokItem.rak_id` — jangan buat tabel paralel yang tidak saling terhubung.
**Acceptance Criteria:**
- [x] Setiap `StokItem` bisa (opsional) menunjuk lokasi rak spesifik.
- [x] Pencarian produk saat stock opname (T-14) bisa difilter/diurutkan per rak.

### T-13 — Perbaiki & Pastikan Alur Transfer Antar Gudang Tersinkron Penuh
*(Request #24)*
**Modul:** WMS — `[API: WMS-03]` (bagian dari `WMS-01..08`)
**Perubahan:** Audit ulang alur transfer (§4.1): pastikan status `pending → diterima` benar-benar mengunci stok di gudang asal (tidak bisa dijual/dipakai transaksi lain saat status pending, atau minimal ada warning), dan konfirmasi terima di gudang tujuan benar-benar menambah `StokItem` tujuan + mengurangi gudang asal secara atomic (gunakan DB transaction, hindari race condition saat 2 user proses transfer bersamaan).
**Acceptance Criteria:**
- [x] Transfer yang belum dikonfirmasi tidak bisa "hilang"/dobel dihitung di kedua gudang.
- [x] Uji dengan 2 user submit transfer bersamaan tidak menghasilkan angka stok yang salah.

### T-14 — Alur Stock Opname Komprehensif + Pencarian by Barcode
*(Request #25)*
**Modul:** WMS — `[API: WMS-04]`
**Perubahan:** Lengkapi alur opname mengikuti standar gudang: (1) buat sesi opname per lokasi/rak/gudang, (2) input stok fisik per item (scan barcode atau cari manual — pakai ulang komponen `BarcodeScanInput` dari T-07), (3) sistem tampilkan selisih real-time saat input, (4) submit ke approval supervisor (sudah ada di §4.1), (5) setelah approve baru `StokItem` & jurnal disesuaikan. Tambahkan riwayat sesi opname (siapa, kapan, hasil) untuk audit.
**Acceptance Criteria:**
- [x] Input opname bisa dipercepat dengan scan barcode, bukan hanya cari manual dari list panjang.
- [x] Sesi opname tercatat lengkap dengan histori untuk audit.

### T-15 — Auto-Generate & Cetak Barcode/QR Produk
*(Request #26)*
**Modul:** WMS
**Perubahan:** `[API: WMS-14] POST /api/wms/produk/{id}/generate-barcode` — generate kode unik (format internal, misal `UTP-{id}-{checksum}`) untuk produk yang field `barcode`-nya kosong (lihat T-11). Tambah halaman/print-view label barcode (pakai library barcode generator, contoh `milon/barcode` atau `picqer/php-barcode-generator`) yang bisa dicetak langsung dari browser (print-friendly CSS, ukuran label standar).
**Acceptance Criteria:**
- [x] Produk tanpa barcode bisa digenerate otomatis 1 klik.
- [x] Ada halaman cetak label barcode (bisa multi-produk sekaligus) yang rapi saat diprint.

---

## FASE 4 — Servis Deepening

### T-16 — Form "Jenis Servis" Lebih Lengkap & Editable di Pengaturan
*(Request #13)*
**Modul:** Servis/Pengaturan
**Perubahan:** Lengkapi entitas `JenisServis` (jika belum ada sebagai entitas terpisah, buat): `nama, estimasi_durasi, durasi_garansi_default, kategori(hardware/software), butuh_part(bool)`. Form CRUD di halaman Pengaturan (§5.11 PRD Frontend), **hanya menambah field, tidak mengubah relasi/alur inti** yang sudah dipakai T-17.
**Acceptance Criteria:**
- [x] Admin bisa CRUD jenis servis dari Pengaturan tanpa dev intervention.
- [x] Perubahan jenis servis tidak merusak tiket servis yang sudah ada/berjalan.

### T-17 — Split Part & Jasa per Tiket Servis, Sinkron Stok & Akunting
*(Request #14)*
**Entitas baru:** `TiketServisItem` (`tiket_servis_id, tipe(part/jasa), produk_id(nullable, hanya utk part), nama_item, qty, harga`).
**Perubahan:** Saat teknisi input pekerjaan (misal "Ganti LCD"), form menghasilkan minimal 2 baris: 1 baris `tipe=jasa` (ongkos kerja, tidak pengaruh stok) dan 1 baris `tipe=part` (LCD, terhubung `produk_id`, otomatis mengurangi `StokItem` gudang cabang — samakan logikanya dengan §4.3 yang sudah ada, jangan buat pengurangan stok ganda). Total `TiketServisItem` per tiket = dasar perhitungan HPP jasa & pendapatan di jurnal akunting (§4.6).
**Acceptance Criteria:**
- [x] 1 tiket servis bisa punya banyak item part & jasa, masing-masing tercatat terpisah.
- [x] Part yang dipakai mengurangi stok tepat 1x (tidak dobel dengan mekanisme lama), jasa tidak menyentuh stok.
- [x] Jurnal akunting servis memisahkan pendapatan jasa vs HPP part secara akurat.

### T-18 — Pencarian & Quick-Add Pelanggan di Modul Terima Unit Servis
*(Request #12)*
**Modul:** Servis — reuse `POS-06`/`CRM-06` (T-04, T-08)
**Perubahan:** Form terima unit servis (§5.4) pakai komponen pencarian pelanggan yang sama dengan POS (T-08) — pilih pelanggan → auto-fill nama/kontak; tombol "+ Pelanggan Baru" submit ke `CRM-06` yang sama. **Jangan buat form/endpoint create pelanggan versi ketiga** — ini kali kedua diminta (setelah POS), jadi wajib 1 komponen reusable (`<x-customer-picker>` misalnya) dipakai di kedua tempat.
**Acceptance Criteria:**
- [x] Komponen pencarian/tambah pelanggan yang sama persis dipakai di POS dan Terima Unit Servis (bukan implementasi terpisah).

### T-19 — Form Kunci Gadget (Pola/PIN) untuk Teknisi
*(Request #15)*
**Modul:** Servis
**Perubahan:** Tambah field terenkripsi ke `TiketServis`: `tipe_kunci(pola/pin/password/tidak_ada), kunci_terenkripsi`. **Wajib dienkripsi at-rest** (Laravel `encrypted` cast di model, bukan plaintext) dan hanya bisa dilihat oleh role `teknisi`/`admin-toko` yang menangani tiket tsb (bukan seluruh staff). Untuk pola, sediakan input grid 3x3 visual (klik urutan titik) yang disimpan sebagai urutan angka terenkripsi.
**Acceptance Criteria:**
- [x] Data kunci gadget tidak pernah tersimpan/terkirim sebagai plaintext.
- [x] Hanya teknisi yang ditugaskan & admin cabang terkait yang bisa melihat data ini.

### T-20 — Upload Foto Unit via HP (Kamera Mobile)
*(Request #16)*
**Modul:** Servis (frontend-only enhancement)
**Perubahan:** Input upload foto di form terima unit & QC pakai atribut `capture="environment"` pada `<input type="file">` untuk mobile, plus tetap sediakan opsi pilih dari galeri. Pastikan halaman ini lolos requirement responsif §8 PRD Frontend (mudah dipakai satu tangan di HP/tablet oleh teknisi).
**Acceptance Criteria:**
- [x] Dari browser HP, tombol upload foto langsung membuka kamera (bukan cuma file picker biasa).

---

## FASE 5 — CRM & Reseller

### T-21 — Reseller Baru + Skema Komisi Lengkap, Sinkron Akunting
*(Request #18)*
**Modul:** Reseller — `[API: RESELLER-01..04]`, tambah `RESELLER-05`
**Perubahan:** `[API: RESELLER-05] POST /api/reseller/daftar` — form pendaftaran reseller baru dari backoffice: data pelanggan dasar (reuse `CRM-06`) + skema komisi (`persen` atau `nominal_tetap`, bisa override per kategori produk — tabel `skema_komisi_reseller(reseller_id, kategori_produk_id?, tipe, nilai)`). Pastikan alur approval komisi (§4.5, sudah ada) tetap dipakai tanpa berubah — ini hanya menambah cara reseller baru didaftarkan & skema disimpan lebih detail.
**Acceptance Criteria:**
- [x] Admin bisa daftarkan reseller baru dengan skema komisi custom per kategori produk.
- [x] Perhitungan komisi otomatis (§4.5) menghormati skema custom ini, fallback ke skema default jika tidak ada override.

### T-22 — Konfigurasi Poin, Diskon, Komisi Sesuai Strategi Perusahaan
*(Request #21)*
**Modul:** CRM/Pengaturan
**Perubahan:** `[API: CRM-07] /api/crm/config` — buat halaman Pengaturan khusus "Strategi Loyalitas": rasio earn poin (% dari nominal transaksi, bisa beda per tier), rasio redeem poin ke rupiah, % diskon per tier (sudah ada dasarnya di §4.4, sekarang dibuat editable UI, bukan hardcode), dan skema komisi default (dipakai sebagai fallback T-21). Semua perubahan config tidak retroaktif (tidak mengubah poin/diskon transaksi lama).
**Acceptance Criteria:**
- [x] Owner/Super Admin bisa ubah rasio poin & diskon tier dari UI tanpa deploy ulang kode.
- [x] Perubahan config hanya berlaku untuk transaksi baru setelah config diubah.

### T-23 — Perjelas & Lengkapi Alur Broadcast CRM
*(Request #22)*
**Modul:** CRM — `[API: CRM-05]`, perluas jadi `CRM-08`
**Perubahan:** Definisikan alur eksplisit: (1) admin pilih segmen (per tier / custom filter seperti "belum belanja 30 hari"), (2) pilih channel (WA/Email — reuse §4.9 notifikasi), (3) tulis konten (template + personalisasi nama/tier), (4) opsi kirim sekarang atau jadwalkan, (5) log hasil kirim (`terkirim/gagal` per penerima) untuk audit & agar tidak dikirim dobel ke orang yang sama. `[API: CRM-08] POST /api/crm/broadcast`, `CRM-09 GET /api/crm/broadcast/{id}/log`.
**Acceptance Criteria:**
- [x] Broadcast bisa disegmentasi, dijadwalkan, dan punya log pengiriman yang jelas.
- [x] Tidak ada broadcast dobel terkirim ke penerima yang sama untuk 1 kampanye yang sama.

---

## FASE 6 — Akunting

### T-24 — Export Laporan (Excel) untuk Semua Laporan Relevan
*(Request #19)*
**Modul:** Akunting — `[API: ACC-01..10]`, tambah `ACC-11`
**Perubahan:** `[API: ACC-11] GET /api/akunting/export?jenis=&periode=&cabang=` — generate `.xlsx` (pakai `maatwebsite/laravel-excel`, sudah dipakai untuk migrasi data di §4.11, reuse dependency yang sama) untuk: Laba Rugi, Neraca, Arus Kas, Buku Besar per akun, **Laporan Stok** (per gudang, termasuk yang dari T-10/T-14), **Laporan Pelanggan** (rekap belanja & tier, dari CRM), **Laporan Servis** (jumlah tiket, pendapatan jasa vs part), **Laporan Piutang/Utang jatuh tempo**. Semua export **wajib async** (§7 PRD Backend — jangan generate sinkron di request untuk laporan besar), notifikasi/link download muncul setelah job selesai.
**Acceptance Criteria:**
- [x] Semua jenis laporan di atas bisa diexport ke Excel dengan format tabel yang rapi & sesuai standar laporan bisnis.
- [x] Export laporan besar tidak membuat request timeout/membebani server (RAM 1GB) karena diproses via queue.

---

## FASE 7 — RBAC Fleksibel

### T-25 — Hak Akses per Role Dapat Disesuaikan Owner/Super Admin + Tambah Role Baru
*(Request #23)*
**Modul:** RBAC — `[API: RBAC-01..04]`, tambah `RBAC-05`
**Perubahan:** `[API: RBAC-05] /api/rbac/roles` (CRUD role custom) + `RBAC-06 PUT /api/rbac/roles/{id}/permissions` (update daftar permission suatu role). UI: halaman matrix permission (§5.9 PRD Frontend, sudah direncanakan sebagai "grid centang") dibuat **fully editable** untuk role manapun (termasuk role seed default), plus tombol "Tambah Role Baru" yang membuka form nama role + pilih permission via checklist yang sama. **Guardrail:** role `super-admin` tidak boleh dihapus/di-downgrade permissionnya sampai kosong (mencegah lockout total dari sistem).
**Acceptance Criteria:**
- [x] Super Admin bisa ubah permission role manapun via checklist UI tanpa edit kode.
- [x] Super Admin bisa membuat role baru dari nol dengan kombinasi permission bebas.
- [x] Sistem mencegah skenario tidak ada satupun user dengan akses penuh (lockout).

---

## FASE 8 — Omnichannel Refinement

### T-26 — Sinkronisasi Stok dengan SOT Mutasi + Biaya Platform di Akunting
*(Request #20)*
**Modul:** Omnichannel — §4.10 PRD Backend, `[API: OMNI-01..06]`
**Perubahan:**
- **Source of Truth (SOT) stok**: buat entitas `StockMutationLog` (`produk_id, gudang_id, delta, sumber(pos/servis/transfer/opname/po/channel:{nama}), referensi_id, terjadi_at`) — **semua** perubahan `StokItem` (dari modul manapun: POS, Servis/T-17, WMS/T-10, Omnichannel) wajib insert log ini. Sinkronisasi stok ke tiap channel (§4.10 alur poin 3) membaca dari log ini secara berurutan (`terjadi_at`) agar tidak ada race condition/silang antar sumber mutasi, bukan hanya baca angka `StokItem` terakhir tanpa jejak urutan.
- **Biaya platform**: tambah akun COA baru "Beban Biaya Admin Marketplace" (per channel bisa di-breakdown pakai dimensi `channel_id` di jurnal). Saat `ChannelOrder` diproses (§4.10 poin 5), sistem hitung estimasi biaya admin channel (persentase dari nilai order, configurable per channel karena tiap marketplace beda %) dan catat sebagai jurnal terpisah dari HPP — **tujuannya supaya laporan margin per channel akurat**, tidak tercampur dengan HPP produk.
**Acceptance Criteria:**
- [x] Setiap mutasi stok dari sumber manapun tercatat di `StockMutationLog`, bisa dipakai audit "kenapa stok produk X berubah".
- [x] Laporan margin per channel omnichannel memisahkan HPP produk vs biaya admin platform, tidak tercampur.

---

## FASE 9 — Dashboard & Analitik

### T-27 — Chart/Grafik Dashboard Informatif per Role
*(Request #2)*
**Modul:** Dashboard — `[API: DASH-01]`
**Perubahan:** Perluas `DASH-01` mengembalikan data siap-chart (bukan cuma angka summary): tren omzet 30 hari (line chart), komposisi omzet per kategori produk (donut), status servis aktif per tahap kanban (bar), stok kritis top-10 (bar), piutang jatuh tempo per umur (aging chart). **Widget yang tampil disesuaikan role** (§5.2 sudah menyebutkan ini, sekarang dieksekusi penuh): Kasir cukup lihat omzet shift-nya sendiri (dari `KasSesi` T-09), Finance lihat semua chart keuangan + piutang, Marketing lihat komposisi tier & performa broadcast (T-23), Staff Gudang lihat stok kritis & PO pending (T-10). Chart pakai library ringan sesuai §3 PRD Frontend, data agregat dihitung di server (bukan di-loop di frontend) demi performa RAM 1GB.
**Acceptance Criteria:**
- [x] Tiap role melihat kombinasi chart yang relevan dengan pekerjaannya, bukan dashboard generik yang sama untuk semua orang.
- [x] Chart tetap responsif & tidak lambat meski data transaksi sudah banyak (hasil seeder T-01 + data produksi).

---

## FASE 10 — Keamanan & Optimasi UI

### T-28 — Scan Keamanan Komprehensif & Perbaiki Celah Login
*(Request #1)*
**Modul:** Keamanan Aplikasi — review keseluruhan
**Perubahan:** Lakukan scanning comprehensive untuk mencari celah keamanan pada aplikasi yang terinstall di ip public. Checklist include:
- [x] **Authentication bypass** — coba login tanpa password, session fixation, credential stuffing protection
- [x] **Authorization gaps** — cek akses lintas user (user A bisa lihat data user B?), RBAC enforcement di server-side
- [x] **Input validation** — SQL injection, XSS, CSRF tokens di semua form, file upload validation
- [x] **Header security** — HTTPS enforce, HSTS, Content-Security-Policy, X-Frame-Options
- [x] **Error handling** — error messages tidak leak info sistem, stack trace tidak ditampilkan ke user
- [x] **Dependency scan** — verifikasi semua composer paket tidak punya vulnerability known, update ke versi terperlu
- [x] **API security** — rate limiting, input sanitization di semua `[API: ...]` endpoints, payload size limit
- **Acceptance Criteria:**
  - [x] Laporan scan keamanan lengkap dengan severity dan rekomediasi per celah
  - [x] Semua celah kritis/diperbaiki dan terverifikasi tidak bisa dieksploitasi lagi
  - [x] Semua endpoint `/api/` memiliki rate limiting dan input validation yang tepat
  - [x] CSP dan headers keamanan sudah konfigurasikan di Laravel 13

### T-29 — Perbaiki Sidebar Ganti Cabang di POS
*(Request #2)*
**Modul:** Auth/Session — `[API: AUTH-02]`
**Perubahan:** Pastikan event ganti cabang di sidebar: (1) memanggil `AUTH-02 POST /api/select-branch` dengan benar, (2) me-refresh session `cabang_aktif_id`, (3) trigger `wire:navigate`/reload komponen yang bergantung pada cabang (dashboard, POS, WMS) agar tidak nyangkut data cabang lama. Sesuai diagnosis awal di implement-plan.md Fase 1 T-02, task ini memastikan fix tersebut terverifikasi dan working di semua module.
**Acceptance Criteria:**
- [x] Ganti cabang di sidebar langsung mengubah data yang tampil di Dashboard, POS, dan WMS tanpa perlu logout/login ulang.
- [x] Refresh halaman tetap mempertahankan cabang aktif yang baru dipilih.
- [x] Semua module module bergantung pada cabang (POS, WMS, Servis) mereset state ketika cabang berubah.

### T-30 — Optimalkan Tampilan di Perangkat Selular (Responsive)
*(Request #3)*
**Modul:** Frontend — seluruh modul (POS, Kasir, CRM, Dashboard)
**Perubahan:** Lakukan audit UI/UX di berbagai ukuran layar (320px, 375px, 768px, 1440px). Masalah utama saat ini: komponen yang dirancang untuk desktop membuat horizontal scroll di HP, tombol terlalu kecil untuk di-tap, dan teks tidak mudah dibaca tanpa zoom. Perbaiki dengan:
- Gunakan Tailwind 4 utility-first untuk breakpoint yang lebih baik
- Pastikan semua tombol minimal 44x44px untuk touch target
- Gunakan `max-width` dan `overflow-hidden` pada container utama
- Optimalkan gambar dan font rendering di HP
- Test dengan device real atau Chrome DevTools device toolbar
- **Acceptance Criteria:**
  - [x] Halaman tidak memiliki horizontal scroll di layar HP (320px-375px)
  - [x] Semua interaksi (tap/click) bisa dilakukan tanpa zoom
  - [x] Baca-ability teks optimal tanpa pengaturan zoom browser

### T-31 — Tambahkan Fitur PWA (Progressive Web App)
*(Request #4)*
**Modul:** Frontend — `vite.config.js`, `manifest.json`, service worker
**Perubahan:** Konfigurasi PWA agar aplikasi bisa di-install di perangkat apapun (HP, tablet, desktop):
- Buat `manifest.json` dengan nama, short_name, icons (192x192 dan 512x512), theme_color, background_color, display: "standalone"
- Tambahkan service worker script yang meregistrasi di `vite.config.js` via `laravel-vite-plugin` v3
- Pastikan aplikasi work offline minimal untuk halaman yang pernah diakses (cache strategi: stale-while-revalidate untuk aset, network-first untuk API)
- Test install di Chrome di HP: menu "Add to Home Screen" harus muncul
- **Acceptance Criteria:**
  - [x] Aplikasi bisa di-install dari browser ke home screen HP
  - [x] Setelah di-install, aplikasi bisa dibuka tanpa browser URL bar
  - [x] Aplikasi bekerja minimal untuk halaman yang pernah diakses (offline first)
  - [x] Icon dan theme warna sesuai desain "Ute Prism"

### T-32 — Sediakan Tema Gelap & Terang (Manual & Otomatis berdasarkan Waktu)
*(Request #5)*
**Modul:** Frontend — Global CSS, Tailwind config, Vue/Livewire component
**Perubahan:** Implementasi toggle tema yang bisa:
- Dipilih manual user (tombol switch di sidebar/header)
- Otomatis berdasarkan waktu sistem (system preferences via `prefers-color-scheme` media query)
- Simpan preferensi user di `localStorage` atau database per user
- Consistent application warna seluruh modul (POS, Kasir, CRM, Dashboard, Servis)
- Warna tema: light menggunakan token `--up-primary` (#5B4FE9) pada area utama, dark menggunakan `--up-primary` dengan opacity/modifikasi yang sesuai
- **Acceptance Criteria:**
  - [x] Tema bisa di-toggle manual via tombol di UI
  - [x] Tema auto berubah sesuai setting sistem HP (light malam → dark siang)
  - [x] Preferensi user tersimpan dan dikenali di sesi berikutnya
  - [x] Tidak ada komponen UI yang "pecah" atau warna kontras salah saat theme diubah

### T-33 — Pastikan Alur Saldo Kas Shift Tercatat & Tersinkronisasi dengan Akunting
*(Request #6)*
**Modul:** KasSesi — `[API: POS-07]`, `[API: POS-08]`, entitas `KasSesi`
**Perubahan:** Verifikasi dan lengkapi alur saldo kas agar tercatat dan tersinkronisasi total dengan sistem akunting (§4.6 PRD Backend):
- Pastikan `KasSesi` entitas memiliki `saldo_awal` (input saat buka shift), `saldo_akhir_sistem` (akumulasi transaksi tunai), `saldo_akhir_fisik` (input kasir), dan `selisih` (perbedaan keduanya)
- Setiap transaksi POS tunai (`[API: POS-01]`) wajib memiliki validasi `KasSesi` berstatus `buka` untuk cabang/casir tsb (sebab T-09)
- Saat tutup kas (`[API: POS-08]`), sistem otomatis buat `JurnalAkuntansi` balance: debit Kas, kredit "Saldo Shift Akhir" atau akun penyesuaian sesuai akunting principle
- Sumber `saldo_awal` setiap shift harus tercatat: apakah dari shift sebelumnya (legacy data), manual input, atau nol (shift baru)
- Laporan Akunting (§4.6) harus menampilkan riwayat sesi kas per cabang lengkap dengan debit/kredit per transaksi
- **Acceptance Criteria:**
  - [x] Tidak bisa transaksi tunai di POS tanpa sesi kas terbuka (validasi T-09)
  - [x] Output tutup kas menghasilkan jurnal otomatis balance (SUM(debit) = SUM(kredit))
  - [x] Laporan Akunting menampilkan riwayat sesi kas per cabang dengan akurasi 100%
  - [x] Sumber saldo awal shift selalu tercatak dengan keterangan (manual/legacy/nol)

### T-34 — Sparator Ribuan untuk Semua Input Nominal di Menu Buka Kas
*(Request #7)*
**Modul:** POS — input field nominal, Livewire components
**Perubahan:** Semua field input nominal di menu buka kas dan transaksi POS harus menyertakan separator ribuan untuk pembacaan yang lebih mudah, serta menghilangkan delay saat pengetikan:
- Gunakan PHP formatter `number_format()` atau Tailwind `numeric` input type di server-side rendering
- Di frontend, gunakan Alpine.js `x-format` atau komponen input yang otomatis menambahkan titik ribuan saat pengguna mengetik (contoh: mengetik "1000000" langsung tampil "1.000.000")
- Hilangkan delay/lag saat pengetikan di field nominal (cek performance, kemungkinan cause ada di debounce atau validation yang terlalu besar)
- Pastikan separator tidak interferensi dengan validasi numerik dan pengiriman ke backend
- **Acceptance Criteria:**
  - [x] Field nominal otomatis menampilkan separator ribuan (titik) saat pengetikan, contoh: "1000000" → "1.000.000"
  - [x] Tidak ada delay/lag yang dirasakan saat mengetik di field nominal (batas maksimal 50ms penundaan)
  - [x] Backend menerima nilai dengan atau tanpa separator dan merespons sama

### T-35 — Optimalkan Menu Print Thermal Bluetooth & Web Print A4
*(Request #8)*
**Modul:** Print — thermal printer, web print
**Perubahan:** Dua mode print yang didukung aplikasi:
- **Thermal Bluetooth (58mm/80mm):** Konfigurasi printer thermal Bluetooth yang bisa langsung print dari browser tanpa setup rumit. Sesuaikan format print ke ukuran 58mm (kocek/struk sederhana) dan 80mm (faktur lengkap). Gunakan library `milon/barcode` atau `picqer/php-barcode-generator` untuk label barcode yang sudah di-generate (seperti T-15). Print preview wajim tampil rapi di ukuran label tersebut.
- **Web Print A4:** Untuk modul PO, keluar kontak, atau modul lain yang butuh format dokumen lengkap, gunakan fitur web print browser (`.print()` atau `window.print()`) dengan format halaman A4, termasuk header company, tabel data, dan footer. Pastikan tidak bergantung pada plugin pihak ketiga yang rumit diinstal.
- **Acceptance Criteria:**
  - [x] Printer thermal 58mm bisa langsung print dari halaman POS tanpa konfigurasi tambahan di browser
  - [x] Printer thermal 80mm mencetak faktur dengan format yang rapi
  - [x] Mode web print A4 terbuka dialog print standar browser dengan layout yang rapi
  - [x] Tidak ada dependensi plugin browser yang wajib diinstall untuk print

### T-36 — Menu Tambah Pelanggan Baru di Menu Kasir & Sinkronisasi CRM
*(Request #9)*
**Modul:** Kasir — `[API: POS-06]`, CRM — `[API: CRM-06]`
**Perubahan:** Pastikan tombol "Tambah Pelanggan Baru" di menu kasir (dan modul lain yang menggunakan data pelanggan) benar-benar berfungsi dan terhubung dengan CRM:
- Tombol "+ Pelanggan Baru" di POS membuka modal ringkas yang sama dengan modal di CRM (§5.6 PRD Frontend)
- Field minimal: nama, no HP (unique), alamat, tier awal (default tier terendah)
- Setiap pelanggan baru yang dibuat dari POS langsung terdaftar di tabel pelanggan CRM (`[API: CRM-06]`) — satu sumber data, tidak ada duplikasi
- Data pelanggan yang sudah ada di CRM bisa dicari dan dipilih di POS (sebab T-08)
- **Acceptance Criteria:**
  - [x] Tombol tambah pelanggan di modal POS terbuka dan fungsi create pelanggan berhasil
  - [x] Data pelanggan baru langsung muncul di daftar CRM tanpa reload manual
  - [x] Data pelanggan baru juga langsung tersedia untuk dicari di POS dan modul Servis — satu tabel sumber kebenaran
  - [x] Field tanggal ulang tahun sudah ada di form CRM (lihat T-37)

### T-37 — Tambah Field Tanggal Ulang Tahun di Data CRM & Fitur Optimasi "Tahan" di Kasir
*(Request #10)*
**Modul:** CRM — form pelanggan, Kasir — transaksi ditahan
**Perubahan:** Dua perbaikan kecil namun krusial:
1. **CRM:** Tambah field `tanggal_lahir` ke entitas pelanggan dan form CRUD di halaman Pengaturan. Field ini akan digunakan oleh marketing untuk membuat promo khusus ulang tahun atau relasi dengan konsumen semakin meningkat karena kita bisa membuat promo khusus ulang tahun atau yang lainnya.
2. **Kasir (Fase 1 T-03):** Optimalkan fitur "tahan" (park) di menu kasir. Saat transaksi ditahan, dia harus otomatis dipindah ke daftar "Transaksi Tertahan" yang sudah ada di bawah (bukan hilang atau membingungkan kasir). Jika transaksi sempat ditahan, sistem harus tetap tercatat dengan benar dan tidak mengacaukan penggunaan kasir — antrian harus tetap mengalir lancar.
- **Acceptance Criteria:**
  - [x] Field `tanggal_lahir` muncul di form pelanggan CRM dan disimpan di database
  - [x] Marketing bisa filter pelanggan berdasarkan tanggal lahir untuk promo ulang tahun
  - [x] Transaksi yang ditekan F6 (tahan) langsung muncul di panel "Transaksi Tertahan"
  - [x] Kasir bisa melanjutkan transaksi tertahan tanpa kebingungan, antrian kasir tidak terganggu

---
## 2. Catatan Lintas-Fase (Wajib Dipatuhi Semua Task)

- **Satu komponen pencarian/tambah pelanggan** dipakai ulang di POS (T-08), Servis (T-18), dan CRM (T-04) — jangan triplikasi logic.
- **Setiap perubahan stok** (T-09 s.d T-17, T-26) harus tercermin di `StockMutationLog` (T-26) — kalau T-26 dikerjakan di Fase 8 sementara task stok lain di fase lebih awal, tambahkan logging `StockMutationLog` secara bertahap di tiap task terkait (T-10, T-17, T-13, T-14), jangan tunggu sampai Fase 8 baru ditambal semua sekaligus — sebutkan ini eksplisit ke agent saat mengerjakan T-10/T-13/T-14/T-17.
- **Setiap perubahan uang** (T-09, T-10, T-17, T-21, T-26) harus menghasilkan jurnal akunting balance — cek dengan query `SUM(debit) = SUM(kredit)` per transaksi sebagai bagian dari acceptance test.
- Semua endpoint baru mengikuti format response konsisten (§5 PRD Backend: `{success, data, message}`) dan dicek permission RBAC server-side (§6 PRD Backend), termasuk endpoint baru dari RBAC fleksibel (T-25).

---

## 3. Cara Menjalankan Plan Ini di Agentic AI (OpenCode)

Ikuti pola yang sama seperti saat build awal (lihat §10.1 PRD Backend), plan ini tinggal ditambahkan sebagai rujukan ketiga:

1. Taruh file **`implement-plan.md`** ini sejajar dengan `PRD-Frontend-UteParts.md`, `PRD-Backend-UteParts.md`, `AGENTS.md`, dan `opencode.json` yang sudah ada di root project.
2. Update `AGENTS.md` yang sudah ada, tambahkan baris:
   ```markdown
   Untuk pekerjaan update/optimasi pasca-MVP, baca juga implement-plan.md.
   Kerjakan per Fase secara berurutan (§1 implement-plan.md), jangan lompat fase.
   Setelah tiap Fase selesai, update CHANGELOG.md dengan ringkasan task yang selesai.
   ```
3. Jalankan `opencode` dari root project, lalu mulai per fase, contoh prompt:
   - *"Kerjakan Fase 0 dari implement-plan.md: buat seeder T-01."*
   - Setelah selesai & dicek: *"Lanjut Fase 1: kerjakan T-02 sampai T-06, satu per satu, jalankan acceptance criteria masing-masing sebelum lanjut ke task berikutnya."*
4. Untuk task yang sifatnya bug fix (Fase 1), minta agent **diagnosis dulu sebelum ngoding** — prompt seperti: *"Sebelum perbaiki T-03, telusuri dulu kode POS existing terkait tombol tahan, laporkan root cause-nya, baru eksekusi perbaikan."* Ini penting karena diagnosis awal di dokumen ini masih dugaan (belum lihat kode aktual), agent harus verifikasi ke kode nyata dulu.
5. Karena ini kerja lanjutan di atas app yang sudah hidup (bukan project kosong), **selalu minta agent jalan di branch terpisah per Fase** (`git checkout -b fase-1-bugfix`, dst) dan buka PR untuk direview sebelum merge ke `main` — jangan langsung commit ke `main`, karena risiko merusak fitur yang sudah jalan lebih tinggi dibanding saat MVP awal.


---
## 4. Alur Skill (Skill Flow) — Mapping Skill ke Tahap Development

Berdasarkan analisis skill yang tersedia (`ask-matt` dan daftar skill lain), berikut mapping skill ke tahap implementasi proyek Ute Parts. **Wajib diikuti urutannya** untuk menjaga konteks dan kualitas kode.

### Prasyarat (Sekali di Awal)
| Skill | Tujuan | Kapan |
|-------|--------|-------|
| `/setup-matt-pocock-skills` | Setup issue tracker, triage labels, doc layout yang diasumsikan skill lain | Sebelum memulai fase pertama (sekali saja) |

---

### Tahap 1: Persiapan & Analisis (Sebelum Mulai Kode)

| Skill | Tahap | Deskripsi | Output |
|-------|-------|-----------|--------|
| `/grill-with-docs` | **Sharpening** | Interview terlena untuk sharpen rencana Fase 10 (dan fase lain yang belum dikerjakan). Menyimpan hasil ke `CONTEXT.md` dan ADR. Wajib dijalankan **di working directory** ini (`/var/www/test.uteparts.id`). | `CONTEXT.md`, ADR decisions, clarified scope per task |
| `/domain-modeling` | **Domain clarity** | Jika ada istilah ambigu (misal "saldo awal kas" vs "modal kas", "tahan" vs "park", "print thermal" vs "web print"), gunakan skill ini untuk sharpen definisi & record ke `CONTEXT.md` | Glossary terms, resolved ambiguities |
| `/codebase-design` | **Deep module design** | Untuk task kompleks (T-28 security scan, T-31 PWA, T-33 kas accounting sync, T-35 print thermal) — design module interface, seam, adapter sebelum implement. | Module interface sketches, seam definitions |

**Catatan:** Jalankan `/grill-with-docs` **satu kali** untuk seluruh Fase 10 (bukan per task). Simpan context, jangan `/clear` sampai selesai `/to-tickets`.

---

### Tahap 2: Breakdown ke Tickets

| Skill | Tahap | Deskripsi | Output |
|-------|-------|-----------|--------|
| `/to-spec` | **Spec creation** | Collapse hasil grill + design decisions menjadi spec yang buildable untuk Fase 10. Hanya jika Fase 10 terasa "multi-session" (lebih dari 1-2 jam kerja). | Spec document (markdown) |
| `/to-tickets` | **Ticket breakdown** | Pecah spec menjadi tracer-bullet tickets (T-28 s.d T-37) dengan **blocking edges** yang eksplisit. Setiap ticket punya file sendiri di `.scratch/fase-10/issues/` (local tracker) atau native blocking di GitHub Issues. | Ticket files dengan blocking edges |

---

### Tahap 3: Implementasi Per Ticket (Loop per Ticket)

**Untuk setiap ticket (T-28 sampai T-37):**

| Skill | Tahap | Deskripsi | Kapan |
|-------|-------|-----------|-------|
| `/diagnosing-bugs` | **Diagnosis** | Wajib untuk ticket bug fix (T-28, T-29, T-37 bagian "tahan"). Jangan langsung coding — buat tight feedback loop (test yang gagal), lalu fix dengan regression test. | Semua ticket bug fix / behavior yang tidak jelas |
| `/implement` | **Build** | Implementasi ticket. Internal drive `/tdd` (red-green-refactor per slice). Close dengan `/code-review` (Standards + Spec). **Context fresh per ticket** — `/clear` sebelum mulai ticket baru. | Code changes + passing tests |
| `/code-review` | **Review** | Two-axis review: Standards (PSR-12, Laravel best practices, AGENTS.md compliance) + Spec (match acceptance criteria). Jalankan sebelum commit/merge. | Review report, ready-to-merge |
| `/tdd` | **Test-first** | (Internal ke `/implement`) — gunakan standalone jika ingin build behavior spesifik test-first tanpa full spec. | Passing tests dulu, baru implement |

**Phase boundary di antar ticket:** `/clear` context, checkout branch baru (`git checkout -b fase-10-t-28`), implement, review, PR, merge, baru lanjut T-29.

---

### Tahap 4: Quality Gates & Health (Paralel / Periodik)

| Skill | Tahap | Deskripsi | Frekuensi |
|-------|-------|-----------|-----------|
| `/improve-codebase-architecture` | **Architecture health** | Scan deepening opportunities, surface candidates untuk refactor. Bisa jalan saat menunggu review PR atau di akhir fase. | Akhir setiap Fase / mingguan |
| `/research` | **External knowledge** | Delegate riset ke background agent: Laravel 13 security config, PWA best practices, thermal printer web API, Tailwind 4 dark mode patterns. Bawa hasil ke `/grill-with-docs` berikutnya. | Saat butuh docs/library research |
| `/prototype` | **UI/UX validation** | Untuk T-30 (responsive), T-31 (PWA install flow), T-32 (theme toggle) — buat throwaway prototype jawab "apakah ini feel right?" sebelum implement penuh. | Saat design question sulut diselesaikan di paper |
| `/code-review` | **Standalone review** | Review branch/PR dari contributor lain atau PR lama yang belum di-merge. | On-demand |

---

### Tahap 5: Dokumentasi & Knowledge Capture (Akhir Fase)

| Skill | Tahap | Deskripsi | Output |
|-------|-------|-----------|--------|
| `/retro` | **Retrospective** | Review sesi kerja: apa yang cepat, apa yang slow, skill mana berguna, friction apa. Generate action items untuk next fase. | Retro notes, process improvements |
| `/writing-for-agents` | **Doc quality** | Pastikan `AGENTS.md`, `CHANGELOG.md`, `CONTEXT.md`, ADR readable oleh agent sesi berikutnya. | Polished agent docs |

---

### Skill On-Demand (Situasional)

| Skill | Gunakan Saat |
|-------|--------------|
| `/handoff` | Perlu fork side-task ke directory terpisah (misal: prototype PWA di folder terpisah), atau handover ke agent/session lain |
| `/wayfinder` | Jika Fase 11+ (future) terasa "foggy" — greenfield besar, butuh decision map dulu |
| `/triage` | Bug report masuk dari user/production yang bukan kita buat (bukan ticket dari `/to-tickets`) |
| `/wizard` | Setup credential Duitku/Biteship/WA Gateway di VPS yang butuh human click-through |
| `/wait-what` | Mid-conversation jika message tidak land (re-pitch dengan vocabulary `CONTEXT.md`) |
| `/teach` | Belajar concept baru (misal: Laravel 13 Octane, Livewire 3, Tailwind 4) over multiple sessions |
| `/resolving-merge-conflicts` | Jika merge conflict saat PR merge (resolusi by intent, bukan line-picking) |
| `/loop-me` | Grill diri sendiri tentang spec workflow yang mau dibangun |

---

### Ringkasan Alur Utama (Main Flow) untuk Fase 10:

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. /setup-matt-pocock-skills     (sekali di awal project)      │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. /grill-with-docs  ──→  /domain-modeling  ──→  /codebase-design │
│    (sharpen plan)         (resolve terms)      (design modules)   │
│    Output: CONTEXT.md, ADRs, module interfaces                   │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. /to-spec  (jika multi-session)  ──→  /to-tickets             │
│    (collapse to spec)                (break to tickets w/ edges)│
│    Output: spec.md, ticket files di .scratch/fase-10/issues/    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. LOOP per ticket (T-28 → T-37):                               │
│    ┌─────────────────────────────────────────────────────────┐  │
│    │ /clear context                                          │  │
│    │ git checkout -b fase-10-t-XX                            │  │
│    │ /diagnosing-bugs (jika bug fix)                         │  │
│    │ /implement  (drives /tdd internally)                    │  │
│    │ /code-review (Standards + Spec)                         │  │
│    │ commit, push, PR, merge                                 │  │
│    └─────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ 5. /improve-codebase-architecture  (periodik/akhir fase)       │
│    /research (background, parallel)                             │
│    /retro  (akhir fase)                                         │
│    Update CHANGELOG.md, AGENTS.md, CONTEXT.md                   │
└─────────────────────────────────────────────────────────────────┘
```

---

### Mapping Skill ke Setiap Task Fase 10:

| Task | Skill Utama | Skill Pendukung |
|------|-------------|-----------------|
| T-28 Security Scan | `/diagnosing-bugs` (tight loop), `/research` (Laravel security config), `/implement` | `/code-review` |
| T-29 Sidebar Cabang | `/diagnosing-bugs` (root cause dulu), `/implement` | `/code-review` |
| T-30 Responsive UI | `/prototype` (test breakpoint), `/implement`, `/code-review` | `/research` (Tailwind 4 responsive patterns) |
| T-31 PWA | `/prototype` (install flow), `/implement`, `/code-review` | `/research` (Vite PWA plugin, service worker strategies) |
| T-32 Dark/Light Theme | `/prototype` (theme toggle UX), `/implement`, `/code-review` | `/research` (CSS custom properties, prefers-color-scheme) |
| T-33 Kas-Akunting Sync | `/domain-modeling` (clarify accounting terms), `/implement` (drives `/tdd`), `/code-review` | `/codebase-design` (module seam Kas↔Akunting) |
| T-34 Ribuan Separator | `/implement` (UI component), `/code-review` | `/prototype` (input feel test) |
| T-35 Thermal/Web Print | `/prototype` (print layout test), `/implement`, `/code-review` | `/research` (Web Bluetooth API, print CSS @page) |
| T-36 Customer Create | `/implement` (reuse CRM-06), `/code-review` | - |
| T-37 Birthday + Tahan | `/implement` (2 sub-fix), `/code-review` | `/diagnosing-bugs` (untuk fix "tahan") |

---

### Catatan Penting untuk Agent:

1. **Jangan skip `/grill-with-docs`** — ini satu-satunya skill yang stateful dan leave paper trail (`CONTEXT.md`, ADR). Tanpa ini, session berikutnya buta konteks.
2. **`/clear` antar ticket** — mencegah context pollution. Setiap ticket fresh context.
3. **`/code-review` wajib** — two-axis (Standards + Spec). Jangan merge tanpa review.
4. **Branch per ticket** — `fase-10-t-28`, `fase-10-t-29`, dst. PR review sebelum merge ke `main`.
5. **`/research` pakai background** — biarkan jalan sambil kamu kerjakan ticket lain. Hasilnya bawa ke grill berikutnya.
6. **Update `CHANGELOG.md` setiap fase selesai** — sudah di Section 3, tapi diulang di sini untuk emphasis.

---

### Setup Skill Directory (Jika Belum):

```bash
# Jalankan sekali di root project
/setup-matt-pocock-skills
```

Ini akan setup:
- Issue tracker config (local `.scratch/` atau GitHub)
- Triage labels vocabulary
- Doc layout (CONTEXT.md, ADR folder, CHANGELOG.md structure)

---

> **Referensi Skill Lengkap:** Lihat `/root/.agents/skills/` untuk 35+ skill tersedia. Skill di atas adalah subset yang relevan untuk proyek Ute Parts Fase 10. Skill lain (seperti `writing-*`, `scaffold-exercises`, `git-guardrails-claude-code`) bersifat optional/situasional.