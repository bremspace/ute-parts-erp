# PRD FRONTEND — Ute Parts ERP, POS & Marketplace
**Versi:** 1.0 | **Tanggal:** 15 September 2026 | **Perusahaan:** Ute Parts
**Dokumen pasangan:** `PRD-Backend-UteParts.md` (semua endpoint, kontrak data, dan aturan bisnis yang dirujuk di sini didefinisikan lengkap di sana — cari kode seperti `[API: AUTH-01]`)
**Urutan upload ke agentic AI:** 1) dokumen ini, 2) `PRD-Backend-UteParts.md`

---

## 0. Cara Membaca Dokumen Ini (untuk Agentic AI / Coding Agent)
Dokumen ini adalah **kontrak UI/UX**, bukan implementasi bebas. Aturan wajib:
1. Setiap layar WAJIB memanggil endpoint yang namanya persis sama dengan kode `[API: ...]` yang tertulis di backend PRD — jangan mengarang endpoint baru tanpa menambah ke backend PRD dulu.
2. Semua istilah entitas (Produk, SKU, Servis, Tier, Reseller, Komisi, Cabang, Gudang) harus konsisten penamaannya dengan `§2 Kamus Data` di backend PRD.
3. Ikuti Design System di `§3` — dilarang membuat komponen baru di luar token yang sudah didefinisikan kecuali sangat diperlukan, agar hasil tidak terlihat "AI slop" (template generik tanpa identitas).
4. Sebelum ngoding, install & aktifkan MCP/skill yang direkomendasikan di `§9`.

---

## 1. Ringkasan Produk

| | |
|---|---|
| Nama brand | **Ute Parts** |
| Jenis usaha | Retail sparepart HP + jasa servis HP, multi-cabang (>5 cabang), multi-gudang |
| Produk software | 1 aplikasi web dengan 2 wajah: **(A) Backoffice ERP/POS** (internal staff) dan **(B) Marketplace publik** (pelanggan retail, member, reseller/agen) |
| Stack frontend | TALL Stack: **T**ailwind CSS, **A**lpine.js, **L**aravel **13** (PHP 8.3+), **L**ivewire (+ Livewire Volt untuk komponen ringan). Fallback: Blade + Livewire klasik bila Volt/TALL penuh sulit dieksekusi agent. |
| Target platform | Web responsif (desktop untuk kasir/admin, mobile-first untuk marketplace & tracking servis pelanggan) |
| Target rilis | MVP live dalam 7 hari |

---

## 2. Dua Zona Aplikasi

### 2.A Backoffice (ERP/POS) — `/app/*`
Dipakai staf internal. Desktop-first, dioptimalkan untuk kecepatan input (keyboard shortcut, barcode scanner, minim klik).

### 2.B Marketplace — `/` (public storefront)
Dipakai pelanggan umum, member bertier, dan reseller/agen. Mobile-first, harus terasa seperti e-commerce modern (bukan seperti dashboard admin yang dipakaikan tema publik).

Kedua zona **berbagi satu Design System** (§3) tapi kepadatan informasi berbeda: Backoffice = high-density, Marketplace = breathing room lebih lega.

---

## 3. Design System — "**Ute Prism**"

### Konsep & Nama
"Ute Prism" — memadukan **glassmorphism** (panel kaca berlapis, blur, translucent) dengan **garis sirkuit tipis** (motif elektronik/motherboard sebagai aksen dekoratif halus) dan **neumorphic-lite** pada tombol aksi cepat POS (agar kasir merasakan "tactile feedback" visual saat transaksi cepat). Nama "Prism" merepresentasikan satu aplikasi (cahaya) yang terurai jadi banyak modul (spektrum warna) — ERP, POS, servis, marketplace — tapi tetap satu sumber. Ini identitas khas Ute Parts, bukan template dashboard generik (hindari clone Tailwind UI/shadcn default tanpa modifikasi).

### Palet Warna
| Token | Hex | Peran |
|---|---|---|
| `--up-primary` (Electric Indigo) | `#5B4FE9` | Aksi utama, brand, link aktif |
| `--up-primary-dark` | `#3B2FC9` | Hover/pressed |
| `--up-accent` (Solder Copper) | `#E8873B` | Aksen sekunder — reseller/komisi, badge tier tinggi, CTA promo |
| `--up-mint` (Signal Mint) | `#1FBF8F` | Sukses, stok aman, pembayaran lunas |
| `--up-amber` | `#F5A623` | Peringatan, stok menipis, servis pending approval |
| `--up-red` | `#EF4444` | Error, stok habis, transaksi gagal |
| `--up-ink-900` | `#0B1020` | Background dark mode POS / teks utama |
| `--up-ink-50` | `#F6F7FB` | Background light mode |
| Glass surface | `rgba(255,255,255,.08)` (dark) / `rgba(255,255,255,.65)` (light) + `backdrop-blur-xl` + border `1px rgba(255,255,255,.15)` | Kartu, modal, sidebar |

Gradient signature: `linear-gradient(135deg, #5B4FE9 0%, #8B7CF6 45%, #E8873B 100%)` — dipakai terbatas (hero marketplace, halaman login, badge tier Platinum) agar tidak berlebihan.

### Tipografi
- Heading: **Sora** atau **Plus Jakarta Sans** (geometric, terasa modern-teknologi, tidak generik seperti Inter polos).
- Body: **Inter** (keterbacaan tinggi untuk tabel/angka POS).
- Angka finansial/struk: font tabular (`font-variant-numeric: tabular-nums`) wajib di semua tabel harga & laporan.

### Motif "Circuit Line"
Garis tipis 1px dengan titik-simpul kecil (seperti jalur PCB) dipakai sebagai divider dekoratif di hero section marketplace, empty state, dan halaman login — bukan di dalam tabel data (supaya tidak mengganggu keterbacaan).

### Komponen Kunci (Livewire/Volt)
`GlassCard`, `PrismButton` (variant: primary/ghost/danger/neumorphic-pos), `TierBadge` (Silver/Gold/Platinum — warna dari `--up-accent` gradasi), `StatusPill` (servis: Diterima→Diagnosa→Menunggu Approval→Dikerjakan→QC→Selesai→Diambil), `StockGauge` (radial mini chart), `BarcodeScanInput`, `DataTable` (server-side pagination, dipakai konsisten di semua modul ERP), `Toast`/`ConfirmDialog` (Alpine-based, no full page reload).

### Mode
Backoffice/POS: default **dark mode** (`--up-ink-900`) agar nyaman dipakai berjam-jam & mengurangi silau di kasir; toggle ke light tetap disediakan.
Marketplace: default **light mode**, glass effect lebih subtle di atas foto produk.

### Aksesibilitas & Non-AI-Slop Checklist
- Kontras teks minimum WCAG AA di atas semua glass surface (uji dengan overlay solid fallback jika `backdrop-filter` tidak didukung).
- Tidak memakai icon set default tanpa kurasi (pakai Lucide/Heroicons dikurasi, bukan seluruh library ditumpuk asal).
- Empty state, loading state, dan error state didesain khusus (bukan teks polos "No data") — pakai motif circuit-line + copy yang manusiawi berbahasa Indonesia santai.

---

## 4. Peta Navigasi & Role Menu (RBAC-aware)

Menu sidebar backoffice **dirender dinamis berdasarkan permission** dari `[API: RBAC-02 GET /api/me/permissions]`. Role rekomendasi (detail matrix permission ada di backend PRD §3):

| Role | Menu utama yang terlihat |
|---|---|
| Super Admin | Semua modul + Pengaturan Sistem + Manajemen Cabang |
| Admin Toko (per cabang) | POS, Inventori cabang, Servis, CRM, Laporan cabang |
| Kasir | POS, lihat stok (read-only), riwayat transaksi |
| Teknisi Servis | Modul Servis (antrian, update status, input sparepart terpakai) |
| Staff Gudang | WMS: stok, transfer antar gudang, stock opname, terima PO |
| Finance/Akunting | Akunting, AP/AR, Laporan Keuangan, Approval komisi reseller |
| Marketing/Reseller Manager | CRM, tier membership, komisi reseller |

Menu yang tidak diizinkan **tidak dirender sama sekali** (bukan disabled/abu-abu), demi mengurangi kebingungan dan kebocoran informasi struktur sistem.

---

## 5. Modul Backoffice — Layar & Alur

### 5.1 Login & Pemilihan Cabang
- Layar login glass-card di atas gradient signature.
- Setelah login, jika user punya akses >1 cabang → modal pemilihan cabang aktif (disimpan sebagai session context, dipakai semua query cabang berikutnya). `[API: AUTH-01, AUTH-02]`

### 5.2 Dashboard
Widget per role: omzet hari ini semua cabang vs per cabang, antrian servis aktif, stok kritis (multi-gudang), piutang jatuh tempo, komisi reseller pending approval. Grafik memakai `chart.js`-style ringan (server aggregate, bukan hitung di frontend). `[API: DASH-01]`

### 5.3 POS (Kasir)
- Layout 2 kolom: kiri = pencarian produk/scan barcode (grid produk dengan foto, badge stok cabang saat ini), kanan = keranjang + kalkulasi otomatis harga sesuai **tier pelanggan** (retail/member Silver-Gold-Platinum/reseller-agen) yang diambil dari `[API: PRICING-01]`.
- Input pelanggan opsional (scan member card / cari nomor HP) → auto-terapkan harga tier & poin loyalty.
- Split payment (tunai + non-tunai), integrasi struk cetak/PDF.
- Shortcut keyboard: F2 cari produk, F4 bayar, F6 tahan transaksi (park), ESC batal — wajib karena target kasir cepat.
- Transaksi POS **auto-generate jurnal akunting** di backend (tidak ada langkah manual dari kasir). `[API: POS-01, POS-02]`

### 5.4 Servis HP
- **Kanban board** status servis (Diterima → Diagnosa → Estimasi Dikirim → Approved Customer → Dikerjakan → QC → Selesai → Diambil), drag antar kolom oleh teknisi/admin, tiap kartu = 1 tiket servis dengan foto unit, kerusakan, estimasi biaya.
- Form terima unit servis: data pelanggan (atau buat akun baru), jenis HP, keluhan, checklist kondisi fisik (dengan foto wajib minimal 2), estimasi awal.
- Saat status "Estimasi Dikirim" → sistem kirim notifikasi (WA/email) ke pelanggan berisi link tracking publik + tombol approve/reject estimasi (approval tercatat, tidak bisa dikerjakan tanpa approve kecuali override admin dengan alasan).
- Modul **garansi servis**: saat status "Selesai", set tanggal expired garansi otomatis (default konfigurasi per jenis servis), muncul badge "Dalam Garansi" saat pelanggan klaim ulang. `[API: SERVICE-01..06]`

### 5.5 Inventori & WMS
- Daftar produk (>1000 SKU) dengan filter kategori, brand HP kompatibel, kondisi (baru/OEM/compatible), stok per gudang.
- Transfer antar gudang (form + approval + tracking status transfer).
- Stock opname (mode input cepat per lokasi rak, selisih otomatis dihitung, butuh approval supervisor untuk penyesuaian jurnal stok).
- Scan barcode/QR untuk semua operasi stok masuk/keluar. `[API: WMS-01..08]`

### 5.6 CRM & Membership
- Profil pelanggan 360°: riwayat pembelian, riwayat servis, tier saat ini, progress ke tier berikutnya (progress bar bergaya "Prism"), poin loyalty.
- Manajemen konfigurasi tier (nama tier, syarat naik tier, diskon %, benefit lain) — hanya role Marketing/Super Admin.
- Broadcast promo (terhubung WA/Email) tersegmentasi per tier. `[API: CRM-01..05]`

### 5.7 Reseller & Komisi
- Dashboard reseller (internal, dilihat admin): omzet per reseller, komisi terhutang vs terbayar.
- Approval komisi terhubung otomatis ke Akunting (jadi entri hutang saat approved). `[API: RESELLER-01..04]`

### 5.8 Akunting & Keuangan
- Chart of Account (COA) editable terbatas (Super Admin/Finance).
- Jurnal otomatis (read-only list, tercipta dari POS/Servis/Pembelian/Komisi) + jurnal manual untuk kasus khusus.
- Laporan: Laba Rugi, Neraca, Arus Kas, Buku Besar per akun, per cabang & konsolidasi semua cabang.
- **Piutang/Utang**: daftar invoice pelanggan (kasbon/termin) dan utang ke supplier, dengan reminder jatuh tempo. `[API: ACC-01..10]`

### 5.9 Manajemen Pengguna & RBAC
- CRUD user, assign role, assign akses cabang. Matrix permission per role ditampilkan visual (grid centang), bukan JSON mentah. `[API: RBAC-01..04]`

### 5.10 Omnichannel Marketplace — "Command Center"
Konsep setara Jubelio/BigSeller, hanya untuk role Super Admin/Admin Toko:
- **Manajemen Channel**: kartu per marketplace (Shopee, Tokopedia, Blibli, TikTok Shop, Lazada) dengan status `Terhubung`/`Belum Terhubung`/`Token Bermasalah` (`StatusPill`), tombol "Hubungkan" memicu OAuth redirect ke marketplace terkait. Badge "MVP" pada channel yang sudah full-integrated (Shopee) vs "Segera" pada channel fase 2, sesuai keputusan skala di backend PRD §4.10 — supaya ekspektasi pengguna jelas sejak awal.
- **Pemetaan Produk**: tabel 2 kolom (Produk Ute Parts ↔ SKU Channel) dengan tombol "Auto-match" (cocokkan otomatis by SKU/nama) + indikator status sinkron per baris (`tersinkron`/`belum dipetakan`/`error`, pakai `StatusPill`). Mengingat >1000 SKU, wajib pagination + filter "hanya yang belum dipetakan".
- **Order Terpadu (Unified Inbox)**: satu tabel semua order — kolom sumber (ikon kecil per channel + POS/Marketplace sendiri), status, aksi cepat (proses/kirim resi). Filter per channel & per status. `[API: OMNI-03]`
- **Status Sinkronisasi**: panel kesehatan tiap channel (terakhir sync stok kapan, ada error tidak) agar admin cepat sadar kalau ada channel gagal sync sebelum terjadi oversell. `[API: OMNI-06]`

### 5.11 Pengaturan Sistem
- Data cabang & gudang, konfigurasi pajak, konfigurasi tier membership, konfigurasi metode pembayaran Duitku, konfigurasi kurir Biteship, template notifikasi (WA/email), konfigurasi garansi servis default.

---

## 6. Modul Marketplace — Layar & Alur

### 6.1 Beranda
Hero dengan gradient signature + circuit-line motif, kategori produk unggulan, promo aktif per tier (pelanggan login melihat harga sesuai tiernya langsung di listing, guest melihat harga retail standar + badge "Login untuk harga member").

### 6.2 Katalog & Pencarian Produk
Filter kompatibilitas HP (merk/model), kategori sparepart, kondisi, rentang harga, ketersediaan stok (gabungan semua cabang/gudang terdekat). Kartu produk pakai glass-card ringan di atas foto produk (bukan solid card generik). `[API: SHOP-01, SHOP-02]`

### 6.3 Detail Produk
Galeri foto, deskripsi, kompatibilitas HP, harga sesuai tier login, stok per gudang/cabang terdekat (opsional ambil di toko / dikirim), produk terkait.

### 6.4 Keranjang & Checkout
- Checkout guest tidak diizinkan (wajib akun, sesuai keputusan bisnis) — tapi proses signup dibuat 1 langkah cepat (nomor HP + OTP) agar tidak menambah friksi berlebihan.
- Pilihan: ambil di cabang (klik & kolek) atau dikirim (integrasi **Biteship** — pilihan kurir & estimasi ongkir real-time). `[API: SHIP-01]`
- Ringkasan harga otomatis sesuai tier/reseller (harga & diskon dari `[API: PRICING-01]`), jika reseller login maka ada info potensi komisi ditampilkan transparan (opsional, sesuai konfigurasi).
- Metode pembayaran: **Duitku** (VA, QRIS, e-wallet, retail outlet) via popup/redirect sesuai `[API: PAY-01, PAY-02]`.

### 6.5 Akun Pelanggan
- Dashboard: status tier & benefit, progress tier berikut, poin loyalty, riwayat order, **tracking servis HP** (status real-time sama seperti kanban internal tapi versi sederhana untuk pelanggan — linear stepper, bukan kanban), riwayat servis + status garansi.
- Untuk reseller/agen: tab tambahan "Komisi Saya" (riwayat komisi, status cair). `[API: ACCOUNT-01..05]`

### 6.6 Booking Servis Online
Form ajukan servis dari rumah (isi keluhan + foto, pilih cabang tujuan) → masuk sebagai tiket servis status "Diajukan Online" di backoffice, admin cabang konfirmasi jadwal. `[API: SERVICE-07]`

---

## 7. Notifikasi & Real-time

- Status servis berubah → notifikasi WA/email + update otomatis di halaman tracking (polling ringan atau Laravel Echo/websocket jika resource server memungkinkan; fallback polling interval 15–30 detik mengingat server 1GB RAM — didetailkan di backend PRD §7).
- Notifikasi stok kritis → dashboard admin gudang.
- Notifikasi pembayaran Duitku sukses → update status order real-time di halaman customer.

---

## 8. Non-Functional Requirements (sisi frontend)

- **Performa**: First Contentful Paint < 2.5s di koneksi 4G rata-rata Indonesia; lazy-load gambar produk; pagination server-side di semua tabel data besar (>1000 SKU tidak boleh di-load sekaligus).
- **Responsif penuh di semua perangkat** — ini bukan "nice to have", wajib diverifikasi Playwright MCP di 3 viewport sebelum dianggap selesai:

| Breakpoint | Target perangkat | Zona | Perilaku kunci |
|---|---|---|---|
| ≤428px (mobile) | HP | Marketplace, tracking servis publik, booking servis online, dashboard akun pelanggan | Layout 1 kolom, bottom-nav untuk aksi utama marketplace (Beranda/Kategori/Keranjang/Akun), tabel diubah jadi **card list** (bukan tabel di-scroll horizontal sempit) |
| 768–1024px (tablet) | Tablet | Backoffice untuk teknisi servis (kanban servis, update status, foto unit) & kasir cadangan | Kanban servis tetap draggable dengan touch target ≥44px; POS bisa dipakai 1 tangan di tablet |
| ≥1280px (desktop) | PC/Laptop kasir & admin | Semua modul backoffice, akunting, laporan, mapping produk omnichannel | Layout multi-kolom penuh, tabel data lebar dengan sticky header/kolom pertama |

- Semua `DataTable` backoffice wajib punya 2 mode render (desktop: tabel biasa; mobile/tablet sempit: tumpukan card) lewat 1 komponen yang sama — bukan membuat 2 halaman terpisah.
- Touch target minimum 44×44px di semua tombol aksi pada breakpoint ≤1024px (POS, kanban servis, checkout marketplace).
- Gambar produk pakai `srcset`/ukuran responsif agar tidak mengirim gambar resolusi desktop ke HP (hemat data & mempercepat loading di jaringan seluler).
- Uji nyata minimal di 3 lebar viewport: 375px, 768px, 1440px, sebelum modul dianggap Definition of Done (§10).
- **Konsistensi**: satu file `resources/css/prism-tokens.css` sebagai sumber tunggal token warna/spacing — dilarang hardcode hex di Blade/Livewire component.
- **i18n-ready**: semua string UI lewat Laravel `__()` walau MVP hanya Bahasa Indonesia, agar mudah ekspansi.
- **Keamanan sisi UI**: role-aware rendering (§4), CSRF token di semua form Livewire (default Laravel), tidak menyimpan token/kredensial sensitif di localStorage.

---

## 9. Rekomendasi Skill/MCP untuk Agentic AI (khusus kerja Frontend)

Install/aktifkan sebelum mulai coding, agar agent bekerja efisien dan tidak berhalusinasi API TALL stack yang berubah cepat:

| Tool | Fungsi | Prioritas |
|---|---|---|
| **Context7 MCP** | Dokumentasi versi-terbaru Laravel, Livewire, Alpine.js, Tailwind — supaya agent tidak pakai syntax lama | Wajib |
| **Playwright MCP** | Verifikasi visual & interaksi nyata di browser (cek glass effect render benar, cek flow checkout end-to-end) | Wajib |
| **Sequential Thinking MCP** | Memecah pembangunan tiap modul UI jadi langkah terstruktur, terutama untuk alur checkout & tracking servis yang bercabang | Sangat disarankan |
| **Filesystem MCP** (scoped ke folder project) | Operasi baca/tulis file Blade/Livewire yang aman & terbatas scope | Wajib |
| **GitHub MCP** | Kelola branch/PR per modul agar histori perubahan rapi walau dikerjakan agent | Disarankan |
| Skill: **frontend-design** (jika tersedia di environment agent) | Menjaga guardrail desain token & tidak jatuh ke UI generik | Wajib bila tersedia |
| **Memory/codebase-memory MCP** | Menyimpan konteks keputusan desain (nama komponen, token, keputusan tier pricing UI) antar sesi kerja agent yang panjang | Sangat disarankan |

> Catatan untuk agent: baca `§0` dan seluruh dokumen backend sebelum generate kode apa pun, lalu jalankan `context7` untuk memverifikasi API Livewire/Alpine versi yang terpasang di `composer.json`/`package.json` project sebelum mulai.

---

## 10. Definition of Done (Frontend MVP)

- [ ] Semua layar §5 & §6 berfungsi terhubung ke endpoint backend sesuai kode `[API: ...]`
- [ ] Role-based menu rendering teruji untuk minimal 5 role
- [ ] Checkout end-to-end (keranjang → Duitku → status paid → update stok) berhasil di sandbox
- [ ] Tracking servis publik menampilkan status real-time sesuai perubahan dari backoffice
- [ ] Desain lolos checklist §3 (kontras, empty/loading/error state, tidak ada komponen default tak terkurasi)
- [ ] Responsif teruji nyata di viewport 375px (mobile), 768px (tablet), 1440px (desktop) — bukan hanya diasumsikan dari Tailwind class
- [ ] Layar Command Center Omnichannel (§5.10) berfungsi: hubungkan channel, mapping produk, order terpadu, status sinkron
