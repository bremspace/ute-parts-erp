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
- [ ] `php artisan migrate:fresh --seed` berjalan tanpa error, menghasilkan data untuk semua role & semua jenis transaksi di atas.
- [ ] Login dengan tiap user role menghasilkan menu sidebar yang sesuai matrix §3 PRD Backend (tidak lebih, tidak kurang).
- [ ] Saldo di Akunting (Buku Besar) balance (debit = kredit) setelah seeder jalan.

---

## FASE 1 — Bug Kritis (Blocker Harian)

### T-02 — Perbaiki Menu Ganti Cabang di Sidebar
*(Request #3)*
**Modul:** Auth/Session — `[API: AUTH-02]`
**Diagnosis awal:** Kemungkinan besar dropdown ganti cabang di sidebar hanya update state Livewire lokal tapi tidak memanggil ulang `AUTH-02 POST /api/select-branch`, atau memanggil endpoint tapi query di modul lain (POS/WMS/dsb) masih pakai cabang lama dari cache session yang tidak di-refresh.
**Perubahan:** Pastikan event ganti cabang: (1) memanggil `AUTH-02`, (2) me-refresh session `cabang_aktif_id`, (3) trigger `wire:navigate`/reload komponen yang bergantung pada cabang (dashboard, POS, WMS) agar tidak nyangkut data cabang lama.
**Acceptance Criteria:**
- [ ] Ganti cabang di sidebar langsung mengubah data yang tampil di Dashboard, POS, dan WMS tanpa perlu logout/login ulang.
- [ ] Refresh halaman tetap mempertahankan cabang aktif yang baru dipilih.

### T-03 — Perbaiki Tombol "Tahan" (Park) di POS
*(Request #6)*
**Modul:** POS — `[API: POS-01]`, perlu endpoint baru `POS-04`
**Diagnosis awal:** Kemungkinan tombol tahan hanya UI state tanpa endpoint backend yang menyimpan transaksi berstatus `ditahan`, sehingga hilang saat halaman refresh atau berpindah kasir.
**Perubahan:**
- Tambah `[API: POS-04] POST /api/pos/transaksi/{id}/tahan` dan `POS-05 GET /api/pos/transaksi?status=ditahan` untuk daftar transaksi tertahan per cabang (bukan per kasir, agar kasir lain bisa lanjutkan).
- UI: tombol "Tahan" (F6) menyimpan keranjang saat ini ke backend berstatus `ditahan`, menyediakan panel "Transaksi Tertahan" untuk resume.
**Acceptance Criteria:**
- [ ] Transaksi yang ditahan tetap ada meski browser di-refresh atau kasir logout.
- [ ] Transaksi tertahan bisa dilanjutkan oleh kasir lain di cabang yang sama.

### T-04 — Perbaiki Menu Tambah Pelanggan di CRM
*(Request #17)*
**Modul:** CRM — perlu endpoint baru `CRM-06`
**Perubahan:** Tambah `[API: CRM-06] POST /api/crm/pelanggan` (create customer dari backoffice), sambungkan tombol "Tambah Pelanggan" yang sudah ada di UI CRM (§5.6 PRD Frontend) ke endpoint ini. Field minimal: nama, no HP (unique), alamat, tier awal (default tier terendah).
**Acceptance Criteria:**
- [ ] Tombol tambah pelanggan di CRM berhasil membuat data baru dan langsung muncul di daftar pelanggan tanpa reload manual.
- [ ] Data pelanggan baru ini juga langsung tersedia untuk dicari di POS (T-08) dan modul Servis (T-17) — satu sumber data, bukan tabel terpisah.

### T-05 — Hilangkan Tombol Tambah Produk yang Dobel
*(Request #27)*
**Modul:** WMS — halaman Master Produk
**Perubahan:** Cek Blade/Livewire komponen halaman master produk, kemungkinan tombol dirender 2x karena komponen header duplikat atau leftover dari refactor. Hapus salah satu, pastikan hanya 1 entry point "Tambah Produk".
**Acceptance Criteria:**
- [ ] Hanya ada 1 tombol "Tambah Produk" di halaman master produk, fungsinya tetap normal.

### T-06 — Batasi Akses Tracking Status Servis (Kanban)
*(Request #11)*
**Modul:** Servis — `[API: SERVICE-01..06]`, terhubung RBAC
**Masalah keamanan:** Saat ini kemungkinan link tracking servis publik bisa diakses siapa saja yang tahu URL/token, atau kanban internal tidak dicek kepemilikan.
**Perubahan:**
- Kanban internal (`/app/servis`) — wajib lolos middleware RBAC `servis.view` (staf saja), **tidak pernah** diakses tanpa login.
- Halaman tracking publik (`/account/servis/{token}` atau serupa) — token unik per tiket **tidak cukup** sebagai satu-satunya proteksi (bisa di-share/tebak). Tambahkan: jika pelanggan sudah login, tracking hanya tampil untuk tiket miliknya sendiri (cek `pelanggan_id` = user login); token publik (tanpa login, untuk approve estimasi cepat sesuai §4.3) hanya menampilkan **info minimal** (status & estimasi biaya), bukan detail lengkap (data pribadi, foto unit, dsb).
**Acceptance Criteria:**
- [ ] User tanpa akses `servis.view` mendapat 403 saat akses kanban internal.
- [ ] Pelanggan A tidak bisa melihat detail lengkap servis milik pelanggan B walau tahu/menebak URL.
- [ ] Link approve estimasi via token tetap berfungsi tanpa login (sesuai §4.3), tapi hanya menampilkan info minimal.

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
- [ ] Ketik 2-3 huruf nama produk langsung muncul suggestion relevan dalam <500ms.
- [ ] Scan barcode fisik/kamera langsung menambahkan produk ke keranjang tanpa langkah tambahan.

### T-08 — Pencarian & Quick-Add Pelanggan di POS (Sinkron CRM)
*(Request #5)*
**Modul:** POS + CRM — perlu endpoint baru `POS-06`, pakai ulang `CRM-06` (T-04)
**Perubahan:** Tambah `[API: POS-06] GET /api/pos/pelanggan?search=` (cari by nama/no HP). Tambah tombol "+ Pelanggan Baru" di panel pelanggan POS yang membuka modal ringkas, submit ke `CRM-06` yang sama dengan T-04 (jangan buat endpoint create pelanggan terpisah — satu sumber kebenaran).
**Acceptance Criteria:**
- [ ] Kasir bisa cari pelanggan existing dan pelanggan baru yang dibuat dari POS langsung muncul di CRM (§5.6) tanpa proses sinkron tambahan.

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
- [ ] Tidak bisa transaksi tunai di POS tanpa sesi kas terbuka.
- [ ] Tutup kas menghasilkan jurnal otomatis yang balance, termasuk saat ada selisih.
- [ ] Laporan Akunting (§4.6) menampilkan riwayat sesi kas per cabang.

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
- [ ] Barang masuk dari PO otomatis menambah stok gudang yang benar & membuat jurnal yang balance.
- [ ] PO kredit muncul di modul Piutang/Utang sebagai utang dengan jatuh tempo benar.
- [ ] Riwayat pembayaran PO (parsial) tercatat dan sisa utang terupdate otomatis.

### T-11 — Lengkapi Form Master Data Produk + SEO Friendly
*(Request #9)*
**Modul:** WMS — Master Produk
**Perubahan:** Tambah field ke `Produk`/`SkuVariant`: `satuan` (pcs/box/unit, dst — buat tabel referensi `SatuanUnit` agar konsisten), `barcode` (unique, lihat juga T-15 auto-generate), `foto[]` (multi-foto, resize sesuai §7 PRD Backend), `slug` (auto dari nama, unique, dipakai di URL marketplace `[API: SHOP-02]`), `meta_title`, `meta_description`, `kompatibilitas_hp[]` (sudah disebut di §6.2 PRD Frontend, pastikan benar-benar tersimpan sebagai data terstruktur, bukan teks bebas, agar filter kompatibilitas di marketplace akurat).
**Acceptance Criteria:**
- [ ] Semua field baru muncul & tersimpan di form master produk, wajib validasi (barcode unique, slug unique).
- [ ] URL produk di marketplace pakai slug SEO-friendly (`/produk/lcd-iphone-11-original` bukan `/produk/123`).
- [ ] Filter kompatibilitas HP di katalog marketplace (§6.2) berfungsi dari data terstruktur ini.

### T-12 — Manajemen Rak, Terhubung ke WMS
*(Request #10)*
**Entitas baru:** `Rak` (Lokasi/Bin), tambahkan `rak_id` (nullable) ke `StokItem`.
**Perubahan:** `[API: WMS-13] /api/wms/rak` (CRUD rak per gudang). Update form stok masuk, transfer, dan stock opname (§4.1) agar bisa pilih/assign rak. Jika manajemen rak versi sebelumnya sudah ada tapi terpisah, migrasikan datanya ke relasi `StokItem.rak_id` — jangan buat tabel paralel yang tidak saling terhubung.
**Acceptance Criteria:**
- [ ] Setiap `StokItem` bisa (opsional) menunjuk lokasi rak spesifik.
- [ ] Pencarian produk saat stock opname (T-14) bisa difilter/diurutkan per rak.

### T-13 — Perbaiki & Pastikan Alur Transfer Antar Gudang Tersinkron Penuh
*(Request #24)*
**Modul:** WMS — `[API: WMS-03]` (bagian dari `WMS-01..08`)
**Perubahan:** Audit ulang alur transfer (§4.1): pastikan status `pending → diterima` benar-benar mengunci stok di gudang asal (tidak bisa dijual/dipakai transaksi lain saat status pending, atau minimal ada warning), dan konfirmasi terima di gudang tujuan benar-benar menambah `StokItem` tujuan + mengurangi gudang asal secara atomic (gunakan DB transaction, hindari race condition saat 2 user proses transfer bersamaan).
**Acceptance Criteria:**
- [ ] Transfer yang belum dikonfirmasi tidak bisa "hilang"/dobel dihitung di kedua gudang.
- [ ] Uji dengan 2 user submit transfer bersamaan tidak menghasilkan angka stok yang salah.

### T-14 — Alur Stock Opname Komprehensif + Pencarian by Barcode
*(Request #25)*
**Modul:** WMS — `[API: WMS-04]`
**Perubahan:** Lengkapi alur opname mengikuti standar gudang: (1) buat sesi opname per lokasi/rak/gudang, (2) input stok fisik per item (scan barcode atau cari manual — pakai ulang komponen `BarcodeScanInput` dari T-07), (3) sistem tampilkan selisih real-time saat input, (4) submit ke approval supervisor (sudah ada di §4.1), (5) setelah approve baru `StokItem` & jurnal disesuaikan. Tambahkan riwayat sesi opname (siapa, kapan, hasil) untuk audit.
**Acceptance Criteria:**
- [ ] Input opname bisa dipercepat dengan scan barcode, bukan hanya cari manual dari list panjang.
- [ ] Sesi opname tercatat lengkap dengan histori untuk audit.

### T-15 — Auto-Generate & Cetak Barcode/QR Produk
*(Request #26)*
**Modul:** WMS
**Perubahan:** `[API: WMS-14] POST /api/wms/produk/{id}/generate-barcode` — generate kode unik (format internal, misal `UTP-{id}-{checksum}`) untuk produk yang field `barcode`-nya kosong (lihat T-11). Tambah halaman/print-view label barcode (pakai library barcode generator, contoh `milon/barcode` atau `picqer/php-barcode-generator`) yang bisa dicetak langsung dari browser (print-friendly CSS, ukuran label standar).
**Acceptance Criteria:**
- [ ] Produk tanpa barcode bisa digenerate otomatis 1 klik.
- [ ] Ada halaman cetak label barcode (bisa multi-produk sekaligus) yang rapi saat diprint.

---

## FASE 4 — Servis Deepening

### T-16 — Form "Jenis Servis" Lebih Lengkap & Editable di Pengaturan
*(Request #13)*
**Modul:** Servis/Pengaturan
**Perubahan:** Lengkapi entitas `JenisServis` (jika belum ada sebagai entitas terpisah, buat): `nama, estimasi_durasi, durasi_garansi_default, kategori(hardware/software), butuh_part(bool)`. Form CRUD di halaman Pengaturan (§5.11 PRD Frontend), **hanya menambah field, tidak mengubah relasi/alur inti** yang sudah dipakai T-17.
**Acceptance Criteria:**
- [ ] Admin bisa CRUD jenis servis dari Pengaturan tanpa dev intervention.
- [ ] Perubahan jenis servis tidak merusak tiket servis yang sudah ada/berjalan.

### T-17 — Split Part & Jasa per Tiket Servis, Sinkron Stok & Akunting
*(Request #14)*
**Entitas baru:** `TiketServisItem` (`tiket_servis_id, tipe(part/jasa), produk_id(nullable, hanya utk part), nama_item, qty, harga`).
**Perubahan:** Saat teknisi input pekerjaan (misal "Ganti LCD"), form menghasilkan minimal 2 baris: 1 baris `tipe=jasa` (ongkos kerja, tidak pengaruh stok) dan 1 baris `tipe=part` (LCD, terhubung `produk_id`, otomatis mengurangi `StokItem` gudang cabang — samakan logikanya dengan §4.3 yang sudah ada, jangan buat pengurangan stok ganda). Total `TiketServisItem` per tiket = dasar perhitungan HPP jasa & pendapatan di jurnal akunting (§4.6).
**Acceptance Criteria:**
- [ ] 1 tiket servis bisa punya banyak item part & jasa, masing-masing tercatat terpisah.
- [ ] Part yang dipakai mengurangi stok tepat 1x (tidak dobel dengan mekanisme lama), jasa tidak menyentuh stok.
- [ ] Jurnal akunting servis memisahkan pendapatan jasa vs HPP part secara akurat.

### T-18 — Pencarian & Quick-Add Pelanggan di Modul Terima Unit Servis
*(Request #12)*
**Modul:** Servis — reuse `POS-06`/`CRM-06` (T-04, T-08)
**Perubahan:** Form terima unit servis (§5.4) pakai komponen pencarian pelanggan yang sama dengan POS (T-08) — pilih pelanggan → auto-fill nama/kontak; tombol "+ Pelanggan Baru" submit ke `CRM-06` yang sama. **Jangan buat form/endpoint create pelanggan versi ketiga** — ini kali kedua diminta (setelah POS), jadi wajib 1 komponen reusable (`<x-customer-picker>` misalnya) dipakai di kedua tempat.
**Acceptance Criteria:**
- [ ] Komponen pencarian/tambah pelanggan yang sama persis dipakai di POS dan Terima Unit Servis (bukan implementasi terpisah).

### T-19 — Form Kunci Gadget (Pola/PIN) untuk Teknisi
*(Request #15)*
**Modul:** Servis
**Perubahan:** Tambah field terenkripsi ke `TiketServis`: `tipe_kunci(pola/pin/password/tidak_ada), kunci_terenkripsi`. **Wajib dienkripsi at-rest** (Laravel `encrypted` cast di model, bukan plaintext) dan hanya bisa dilihat oleh role `teknisi`/`admin-toko` yang menangani tiket tsb (bukan seluruh staff). Untuk pola, sediakan input grid 3x3 visual (klik urutan titik) yang disimpan sebagai urutan angka terenkripsi.
**Acceptance Criteria:**
- [ ] Data kunci gadget tidak pernah tersimpan/terkirim sebagai plaintext.
- [ ] Hanya teknisi yang ditugaskan & admin cabang terkait yang bisa melihat data ini.

### T-20 — Upload Foto Unit via HP (Kamera Mobile)
*(Request #16)*
**Modul:** Servis (frontend-only enhancement)
**Perubahan:** Input upload foto di form terima unit & QC pakai atribut `capture="environment"` pada `<input type="file">` untuk mobile, plus tetap sediakan opsi pilih dari galeri. Pastikan halaman ini lolos requirement responsif §8 PRD Frontend (mudah dipakai satu tangan di HP/tablet oleh teknisi).
**Acceptance Criteria:**
- [ ] Dari browser HP, tombol upload foto langsung membuka kamera (bukan cuma file picker biasa).

---

## FASE 5 — CRM & Reseller

### T-21 — Reseller Baru + Skema Komisi Lengkap, Sinkron Akunting
*(Request #18)*
**Modul:** Reseller — `[API: RESELLER-01..04]`, tambah `RESELLER-05`
**Perubahan:** `[API: RESELLER-05] POST /api/reseller/daftar` — form pendaftaran reseller baru dari backoffice: data pelanggan dasar (reuse `CRM-06`) + skema komisi (`persen` atau `nominal_tetap`, bisa override per kategori produk — tabel `skema_komisi_reseller(reseller_id, kategori_produk_id?, tipe, nilai)`). Pastikan alur approval komisi (§4.5, sudah ada) tetap dipakai tanpa berubah — ini hanya menambah cara reseller baru didaftarkan & skema disimpan lebih detail.
**Acceptance Criteria:**
- [ ] Admin bisa daftarkan reseller baru dengan skema komisi custom per kategori produk.
- [ ] Perhitungan komisi otomatis (§4.5) menghormati skema custom ini, fallback ke skema default jika tidak ada override.

### T-22 — Konfigurasi Poin, Diskon, Komisi Sesuai Strategi Perusahaan
*(Request #21)*
**Modul:** CRM/Pengaturan
**Perubahan:** `[API: CRM-07] /api/crm/config` — buat halaman Pengaturan khusus "Strategi Loyalitas": rasio earn poin (% dari nominal transaksi, bisa beda per tier), rasio redeem poin ke rupiah, % diskon per tier (sudah ada dasarnya di §4.4, sekarang dibuat editable UI, bukan hardcode), dan skema komisi default (dipakai sebagai fallback T-21). Semua perubahan config tidak retroaktif (tidak mengubah poin/diskon transaksi lama).
**Acceptance Criteria:**
- [ ] Owner/Super Admin bisa ubah rasio poin & diskon tier dari UI tanpa deploy ulang kode.
- [ ] Perubahan config hanya berlaku untuk transaksi baru setelah config diubah.

### T-23 — Perjelas & Lengkapi Alur Broadcast CRM
*(Request #22)*
**Modul:** CRM — `[API: CRM-05]`, perluas jadi `CRM-08`
**Perubahan:** Definisikan alur eksplisit: (1) admin pilih segmen (per tier / custom filter seperti "belum belanja 30 hari"), (2) pilih channel (WA/Email — reuse §4.9 notifikasi), (3) tulis konten (template + personalisasi nama/tier), (4) opsi kirim sekarang atau jadwalkan, (5) log hasil kirim (`terkirim/gagal` per penerima) untuk audit & agar tidak dikirim dobel ke orang yang sama. `[API: CRM-08] POST /api/crm/broadcast`, `CRM-09 GET /api/crm/broadcast/{id}/log`.
**Acceptance Criteria:**
- [ ] Broadcast bisa disegmentasi, dijadwalkan, dan punya log pengiriman yang jelas.
- [ ] Tidak ada broadcast dobel terkirim ke penerima yang sama untuk 1 kampanye yang sama.

---

## FASE 6 — Akunting

### T-24 — Export Laporan (Excel) untuk Semua Laporan Relevan
*(Request #19)*
**Modul:** Akunting — `[API: ACC-01..10]`, tambah `ACC-11`
**Perubahan:** `[API: ACC-11] GET /api/akunting/export?jenis=&periode=&cabang=` — generate `.xlsx` (pakai `maatwebsite/laravel-excel`, sudah dipakai untuk migrasi data di §4.11, reuse dependency yang sama) untuk: Laba Rugi, Neraca, Arus Kas, Buku Besar per akun, **Laporan Stok** (per gudang, termasuk yang dari T-10/T-14), **Laporan Pelanggan** (rekap belanja & tier, dari CRM), **Laporan Servis** (jumlah tiket, pendapatan jasa vs part), **Laporan Piutang/Utang jatuh tempo**. Semua export **wajib async** (§7 PRD Backend — jangan generate sinkron di request untuk laporan besar), notifikasi/link download muncul setelah job selesai.
**Acceptance Criteria:**
- [ ] Semua jenis laporan di atas bisa diexport ke Excel dengan format tabel yang rapi & sesuai standar laporan bisnis.
- [ ] Export laporan besar tidak membuat request timeout/membebani server (RAM 1GB) karena diproses via queue.

---

## FASE 7 — RBAC Fleksibel

### T-25 — Hak Akses per Role Dapat Disesuaikan Owner/Super Admin + Tambah Role Baru
*(Request #23)*
**Modul:** RBAC — `[API: RBAC-01..04]`, tambah `RBAC-05`
**Perubahan:** `[API: RBAC-05] /api/rbac/roles` (CRUD role custom) + `RBAC-06 PUT /api/rbac/roles/{id}/permissions` (update daftar permission suatu role). UI: halaman matrix permission (§5.9 PRD Frontend, sudah direncanakan sebagai "grid centang") dibuat **fully editable** untuk role manapun (termasuk role seed default), plus tombol "Tambah Role Baru" yang membuka form nama role + pilih permission via checklist yang sama. **Guardrail:** role `super-admin` tidak boleh dihapus/di-downgrade permissionnya sampai kosong (mencegah lockout total dari sistem).
**Acceptance Criteria:**
- [ ] Super Admin bisa ubah permission role manapun via checklist UI tanpa edit kode.
- [ ] Super Admin bisa membuat role baru dari nol dengan kombinasi permission bebas.
- [ ] Sistem mencegah skenario tidak ada satupun user dengan akses penuh (lockout).

---

## FASE 8 — Omnichannel Refinement

### T-26 — Sinkronisasi Stok dengan SOT Mutasi + Biaya Platform di Akunting
*(Request #20)*
**Modul:** Omnichannel — §4.10 PRD Backend, `[API: OMNI-01..06]`
**Perubahan:**
- **Source of Truth (SOT) stok**: buat entitas `StockMutationLog` (`produk_id, gudang_id, delta, sumber(pos/servis/transfer/opname/po/channel:{nama}), referensi_id, terjadi_at`) — **semua** perubahan `StokItem` (dari modul manapun: POS, Servis/T-17, WMS/T-10, Omnichannel) wajib insert log ini. Sinkronisasi stok ke tiap channel (§4.10 alur poin 3) membaca dari log ini secara berurutan (`terjadi_at`) agar tidak ada race condition/silang antar sumber mutasi, bukan hanya baca angka `StokItem` terakhir tanpa jejak urutan.
- **Biaya platform**: tambah akun COA baru "Beban Biaya Admin Marketplace" (per channel bisa di-breakdown pakai dimensi `channel_id` di jurnal). Saat `ChannelOrder` diproses (§4.10 poin 5), sistem hitung estimasi biaya admin channel (persentase dari nilai order, configurable per channel karena tiap marketplace beda %) dan catat sebagai jurnal terpisah dari HPP — **tujuannya supaya laporan margin per channel akurat**, tidak tercampur dengan HPP produk.
**Acceptance Criteria:**
- [ ] Setiap mutasi stok dari sumber manapun tercatat di `StockMutationLog`, bisa dipakai audit "kenapa stok produk X berubah".
- [ ] Laporan margin per channel omnichannel memisahkan HPP produk vs biaya admin platform, tidak tercampur.

---

## FASE 9 — Dashboard & Analitik

### T-27 — Chart/Grafik Dashboard Informatif per Role
*(Request #2)*
**Modul:** Dashboard — `[API: DASH-01]`
**Perubahan:** Perluas `DASH-01` mengembalikan data siap-chart (bukan cuma angka summary): tren omzet 30 hari (line chart), komposisi omzet per kategori produk (donut), status servis aktif per tahap kanban (bar), stok kritis top-10 (bar), piutang jatuh tempo per umur (aging chart). **Widget yang tampil disesuaikan role** (§5.2 sudah menyebutkan ini, sekarang dieksekusi penuh): Kasir cukup lihat omzet shift-nya sendiri (dari `KasSesi` T-09), Finance lihat semua chart keuangan + piutang, Marketing lihat komposisi tier & performa broadcast (T-23), Staff Gudang lihat stok kritis & PO pending (T-10). Chart pakai library ringan sesuai §3 PRD Frontend, data agregat dihitung di server (bukan di-loop di frontend) demi performa RAM 1GB.
**Acceptance Criteria:**
- [ ] Tiap role melihat kombinasi chart yang relevan dengan pekerjaannya, bukan dashboard generik yang sama untuk semua orang.
- [ ] Chart tetap responsif & tidak lambat meski data transaksi sudah banyak (hasil seeder T-01 + data produksi).

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
