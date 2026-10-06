---
name: Ute Prism
description: Precision glassmorphism & electronics aesthetic for high-speed POS, service lifecycle, and mobile sparepart marketplace
colors:
  primary: "#5B4FE9"
  primary-dark: "#3B2FC9"
  accent: "#E8873B"
  mint: "#1FBF8F"
  amber: "#F5A623"
  red: "#EF4444"
  ink-950: "#070A14"
  ink-900: "#0B1020"
  ink-850: "#0E152B"
  ink-800: "#141D3A"
  ink-700: "#1E2B54"
  ink-600: "#334375"
  ink-500: "#64748B"
  ink-400: "#94A3B8"
  ink-100: "#E2E8F0"
  ink-50: "#F6F7FB"
typography:
  display:
    fontFamily: "Sora, Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontWeight: 700
    lineHeight: 1.1
    letterSpacing: "-0.02em"
  headline:
    fontFamily: "Sora, Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "-0.01em"
  title:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontWeight: 600
    lineHeight: 1.3
  body:
    fontFamily: "Instrument Sans, Inter, ui-sans-serif, system-ui, sans-serif"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontWeight: 500
    lineHeight: 1.4
rounded:
  sm: "6px"
  md: "10px"
  lg: "16px"
  xl: "20px"
  full: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "#FFFFFF"
    rounded: "{rounded.lg}"
    padding: "10px 20px"
  button-primary-hover:
    backgroundColor: "{colors.primary-dark}"
  button-accent:
    backgroundColor: "{colors.accent}"
    textColor: "#FFFFFF"
    rounded: "{rounded.lg}"
    padding: "10px 20px"
---

# Design System: Ute Prism

## Overview
**Ute Prism** memadukan presisi hardware/elektronika modern: panel kaca berlapis terstruktur (*glassmorphism*), aksen sirkuit halus (*circuit lines*), dan *tactile feedback* responsif untuk kecepatan kasir POS, lifecycle tiket servis, serta katalog sparepart pelanggan. Dibangun di atas TALL Stack (Tailwind CSS 4, Alpine.js, Laravel Livewire, Blade).

Sistem melayani 2 zona antarmuka utama:
1. **Backoffice ERP/POS (`/app/*`):** Desktop-first, density tinggi, default *dark mode* (`--up-ink-900`) untuk kenyamanan teknisi/kasir, kontrol multi-cabang terisolasi, audit log, dan navigasi modular lengkap (Akunting, CRM, Dashboard, HR, Notifikasi, Omnichannel, POS, RBAC, Report, Reseller, Servis, Webhook, WMS, Workflow).
2. **Marketplace Publik (`/` storefront):** Mobile-first, card padding lega, default *light mode*, responsive bottom navigation bar, touch-friendly checkout, Biteship ongkir selector, dan Duitku payment badge.

---

## 1. Arsitektur Zona Antarmuka

### A. Backoffice (`/app/*`)
- **Target Pengguna:** Kasir, teknisi servis, staf gudang (WMS), akuntan, supervisor, manajer cabang, superadmin.
- **Strategi Layout:** Desktop-first (1024px+ optimal), responsive graceful degradation ke tablet & mobile.
- **Theme Default:** Dark Mode (`bg-[#070A14]` / surface `bg-[#0B1020]`).
- **Elemen Shell:**
  - **Top Navigation Bar:** Multi-cabang switcher (`cabang_id` scope indicator), notifikasi queue indicator, profile switch, theme toggle (ADR 0008).
  - **Sidebar Modular:** Navigasi 15 modul bisnis terkelompok secara hierarkis dengan badge status notifikasi/pending approvals.
  - **Floating Action & Summary Bar (Mobile/Tablet View):** Di viewport kecil, aksi utama (Simpan, Bayar, Cetak, Filter) dan summary bar (Total Kas, Status Tiket) melayang di atas batas bawah layar dengan safe-area offset.
  - **Audit Log Drawer / Trail:** Jejak modifikasi data dan aktor perubahan dapat diakses pada setiap record utama.

### B. Marketplace Storefront (`/`)
- **Target Pengguna:** Reseller, bengkel mitra, dan pelanggan ritel sparepart.
- **Strategi Layout:** Mobile-first (375px - 430px optimal viewport), scale up ke desktop catalog grid.
- **Theme Default:** Light Mode (`bg-[#F8FAFC]` / surface `bg-white` 72% glass opacity).
- **Elemen Shell:**
  - **Sticky Header:** Search bar ekspresif, pill filter kategori cepat, keranjang belanja counter.
  - **Bottom Navigation Bar (Mobile):** Navigasi jempol (Home, Katalog, Pesanan, Akun Reseller/Member) dengan touch target 48×48px dan `pb-[env(safe-area-inset-bottom)]`.
  - **Touch-Friendly Checkout:**
    - Alamat & kalkulator ongkir kurir terintegrasi Biteship (multi-origin cabang terdekat).
    - Status badge & kanal pembayaran Duitku (QRIS, VA, E-Wallet).

---

## 2. Color System & Tokens

### Core Palette
- **Primary (`#5B4FE9` - Electric Indigo):** Aksi primer, focus rings, link aktif, brand accents.
- **Primary Dark (`#3B2FC9`):** State hover/active tombol primer.
- **Accent (`#E8873B` - Solder Copper):** Komisi reseller, tier customer (Gold/Platinum), highlight promosi.
- **Mint (`#1FBF8F` - Signal Mint):** Transaksi sukses/lunas, stok aman, indikator servis selesai, sinyal webhook/printer aktif.
- **Amber (`#F5A623`):** Peringatan stok menipis, servis menunggu persetujuan (approval pending), workflow review.
- **Red (`#EF4444`):** Validasi error, stok habis/kritis, transaksi dibatalkan, tiket servis ditolak.

### Ink & Neutral Spectrum
- **Dark Mode (Backoffice default):**
  - App Canvas: `#070A14` (`ink-950`)
  - Elevated Surface: `#0B1020` (`ink-900`) / `#0E152B` (`ink-850`)
  - Border Glass: `rgba(255, 255, 255, 0.08)`
  - Text Primary: `#F6F7FB` (`ink-50`)
  - Text Muted: `#94A3B8` (`ink-400`) / `#64748B` (`ink-500`)
- **Light Mode (Marketplace default):**
  - Canvas: `#F8FAFC`
  - Elevated Surface: `#FFFFFF` (opacity 72% + blur)
  - Border Glass: `rgba(15, 23, 42, 0.1)`
  - Text Primary: `#0F172A`
  - Text Muted: `#64748B`

---

## 3. Typography & Numerical Rules

### Font Families
- **Display & Headings:** `Sora` atau `Instrument Sans` (modern, geometric, kesan presisi hardware).
- **Body & Controls:** `Instrument Sans` / `Inter` (keterbacaan prima, optimal di resolusi tinggi/rendah).

### Format Angka & Keuangan (Mandatori)
- **Aturan `tabular-nums`:** Wajib diterapkan (`font-variant-numeric: tabular-nums` / class Tailwind `.tabular-nums`) pada:
  - Nominal mata uang (Rupiah dengan pemisah ribuan titik sesuai ADR 0011, contoh: `Rp 1.250.000`).
  - Kuantitas stok gudang (WMS) dan jumlah keranjang POS.
  - Kode SKU, Barcode, nomor resi, nomor seri part.
  - Nomor jurnal akuntansi dan nomor tiket servis (`SRV-202610-0001`).
  - Timestamp jam/menit transaksi.
- Mencegah pergeseran lebar kolom (layout shifting) saat data angka berubah dinamis via Livewire polling atau kalkulasi POS realtime.

---

## 4. Spesifikasi Komponen (Ute Prism UI)

### A. Surface & Buttons
- **`GlassCard`:** Kontainer berlapis `backdrop-blur-md`, subtle border highlight (`border-white/10` dark, `border-slate-200/60` light), sudut lengkung `rounded-xl` atau `rounded-2xl`.
- **`PrismButton`:** Tombol tactile presisi dengan feedback `:active:scale-[0.97]` dan transisi eksplisit `160ms cubic-bezier(0.23, 1, 0.32, 1)`. Varian: Primary (Indigo), Accent (Copper), Danger (Red), Ghost/Outline.
- **`TierBadge`:** Badge klasifikasi tier customer/reseller (Standard, Silver, Gold, Platinum) dengan aksen gradasi tembaga dan border mikro.
- **`StatusPill`:** Indikator status terstandarisasi dengan kontras rasio WCAG AA minimal 4.5:1. Dilengkapi dot indicator opsional.
- **`StockGauge`:** Barometer visual level stok produk (Merah = Kritis, Kuning = Menipis, Mint = Aman).
- **`Toast` & `ConfirmDialog`:** Feedback asinkron Livewire tanpa blocking layar kasir; dialog konfirmasi non-destruktif vs destruktif dengan keyboard trigger (Enter/Esc).

### B. Data Table, Filter Bar & Drilldown Viewer
- **`DataTable`:**
  - Header sticky dengan background padat saat scrolling.
  - Baris zebra striping halus (`even:bg-white/[0.02]` dark).
  - Column alignment: Teks rata kiri, angka/finansial rata kanan (`text-right tabular-nums`), aksi rata tengah.
  - Touch-safe horizontal overflow wrapper (`overflow-x-auto`).
- **Filter Periode Terintegrasi:**
  - Kontrol rentang tanggal kompak: `Tanggal Mulai` — `Tanggal Akhir` berdampingan dengan shortcut periode (Hari Ini, 7 Hari, Bulan Ini).
  - Tombol aksi filter terpadu: `Terapkan` & `Reset` berdampingan tanpa makan ruang vertikal berlebih.
- **Drilldown Viewer UI:**
  - Dual Mode: Tabel Ringkasan (Agregat) dan Detail Modal/Drawer Drilldown.
  - **Kurasi Kolom Bisnis:** Menampilkan label human-readable terkurasi (contoh: *Nomor Jurnal*, *Akun COA*, *Cabang*, *Debet*, *Kredit*). Dilarang keras membocorkan metadata internal ORM/Eloquent (`id`, `created_at_epoch`, `deleted_at`, foreign key mentah).
- **Aturan Action Button Export:**
  - Tombol Export (Excel / CSV / PDF) **hanya diizinkan** pada view data yang telah terkurasi (contoh: di Drilldown Viewer atau Master Produk per tier harga).
  - Tombol export dilarang ditempatkan pada form query builder mentah atau dump table tak terstruktur.
  - Memanfaatkan async export queue (ADR / constraints 1GB RAM) untuk mencegah timeout server.

### C. POS (Point of Sale) Screen
- **Dual-Pane Kasir Layout:** Kiri = Catalog/Barcode search & quick grid; Kanan = Keranjang transaksi, subtotal, diskon, tombol bayar.
- **`BarcodeScanInput`:**
  - Input field autofocus dengan listener keydown instan (intersep input hardware scanner barcode).
  - Indikator status visual (indigo glow saat idle, mint pulse saat barcode terdeteksi).
- **Quick Payment Calculator:** Numpad virtual + pecahan uang cepat (Rp 20.000, Rp 50.000, Rp 100.000, Uang Pas) dengan kalkulasi kembalian realtime tabular-nums.
- **Park / Hold Modal:** Fitur simpan sementara keranjang transaksi saat pelanggan mengambil barang tambahan, dengan recall instan via satu sentuhan.
- **Thermal Print Layout (ADR 0009):** View cetak struk ESC/POS monokrom 58mm / 80mm presisi, auto-cut trigger, tanpa elemen web styling non-thermal.

### D. Servis Kanban & Lifecycle
- **Board Kolom State Machine:**
  - Menunggu (`status: menunggu` - Amber)
  - Proses / Estimasi (`status: proses` - Indigo)
  - Selesai (`status: selesai` - Mint)
  - Dibatalkan / Ditolak (`status: dibatalkan` - Red)
- **Strict Transition Guard:** Tidak ada pergeseran kartu mundur kecuali dilakukan via role admin/supervisor dengan modal alasan override.
- **Part vs Jasa Itemization:** Visualisasi pembeda jelas antara item sparepart (disertai indikator potong stok gudang) dan item jasa servis teknisi.
- **Public Approval Token View:** Halaman ringkas mobile-friendly untuk pelanggan menyetujui estimasi biaya via token tautan tanpa harus login ke sistem.

---

## 5. Theme System & Ergonomi (ADR 0008, 0010)

### Strategi Tema
- Menggunakan strategi kelas Tailwind `dark:`.
- Sinkronisasi dwiarah: Tersimpan di `localStorage` klien dan tersinkronisasi ke preferensi user di database saat login.
- Backoffice default `dark:`, Storefront default `light:` (tetap menghormati toggle pengguna).

### Ergonomi Mobile & Sentuh
- **Minimum Touch Target:** Area sentuh interaktif minimal **44×44px** (tombol kasir, nav link, row expander, checkbox).
- **Mencegah Auto-Zoom iOS:** Seluruh input teks, select, dan barcode input memiliki font-size minimal **16px (1rem)** di layar mobile (`text-base`).
- **Safe-Area Insets:** Elemen sticky/fixed wajib menerapkan:
  - Header: `pt-[env(safe-area-inset-top)]`
  - Bottom Bar / Floating Bar: `pb-[env(safe-area-inset-bottom)]`

---

## 6. Do's and Don'ts

### DO
- **DO:** Gunakan `tabular-nums` untuk semua angka rupiah, stok, jam, nomor transaksi, dan kuantitas.
- **DO:** Gunakan format ribuan titik khas Indonesia (ADR 0011) di seluruh layer tampilan pengguna.
- **DO:** Pastikan dropdown cabang pada backoffice selalu terlihat jelas agar operator sadar scope cabang aktif.
- **DO:** Terapkan transisi micro-interaction cepat (<200ms) dengan feedback tactile `:active:scale-[0.97]` pada tombol.
- **DO:** Sediakan empty states yang informatif dan ramah saat hasil filter atau pencarian barcode kosong.

### DON'T
- **DON'T:** JANGAN gunakan `transition: all`. Tentukan properti transisi secara spesifik (`transform`, `opacity`, `background-color`, `border-color`).
- **DON'T:** JANGAN gunakan motif dekoratif circuit line di dalam baris data table atau invoice thermal. Hanya izinkan di background hero, divider login, atau watermarking status.
- **DON'T:** JANGAN mengekspos ID database mentah atau properti ORM di UI Drilldown / tabel audit log.
- **DON'T:** JANGAN letakkan tombol export di luar view data yang terkurasi.
- **DON'T:** JANGAN izinkan transisi status tiket servis bergerak mundur secara bebas di UI Kanban.
- **DON'T:** JANGAN biarkan input form di bawah 16px pada viewport mobile yang memicu zoom otomatis iOS.
