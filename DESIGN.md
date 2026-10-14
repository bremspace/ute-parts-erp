---
name: Ute Prism Enterprise
description: Precision high-density operational aesthetic and fluid touch commerce for multi-branch hardware retail & repair lifecycle
colors:
  primary: "#5B4FE9"
  primary-hover: "#493ED9"
  primary-active: "#3B2FC9"
  accent: "#E8873B"
  mint: "#1FBF8F"
  amber: "#F5A623"
  red: "#EF4444"
  # Dark Spectrum (Backoffice Default - OLED/Neutral Slate)
  ink-950: "#070A14"
  ink-900: "#0B1020"
  ink-850: "#0E152B"
  ink-800: "#141D3A"
  ink-700: "#1E2B54"
  ink-600: "#334375"
  # Neutral Light & Muted
  ink-500: "#64748B"
  ink-400: "#94A3B8"
  ink-300: "#CBD5E1"
  ink-200: "#E2E8F0"
  ink-100: "#F1F5F9"
  ink-50: "#F8FAFC"
  white: "#FFFFFF"
typography:
  display:
    fontFamily: "Plus Jakarta Sans, Instrument Sans, -apple-system, sans-serif"
    fontWeight: 700
    lineHeight: 1.15
    letterSpacing: "-0.025em"
  headline:
    fontFamily: "Plus Jakarta Sans, Instrument Sans, -apple-system, sans-serif"
    fontWeight: 600
    lineHeight: 1.25
    letterSpacing: "-0.015em"
  title:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontWeight: 600
    lineHeight: 1.3
  body:
    fontFamily: "Instrument Sans, Inter, -apple-system, sans-serif"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "0em"
  numbers:
    fontFamily: "Instrument Sans, Inter, monospace"
    fontVariantNumeric: "tabular-nums"
    letterSpacing: "-0.01em"
rounded:
  xs: "4px"
  sm: "6px"
  md: "10px"
  lg: "14px"
  xl: "20px"
  full: "9999px"
elevation:
  dark-surface: "0 0 0 1px rgba(255, 255, 255, 0.06), 0 8px 24px -4px rgba(0, 0, 0, 0.4)"
  dark-popover: "0 0 0 1px rgba(255, 255, 255, 0.08), 0 16px 36px -6px rgba(0, 0, 0, 0.6)"
  light-surface: "0 0 0 1px rgba(15, 23, 42, 0.06), 0 4px 20px -2px rgba(15, 23, 42, 0.04)"
  light-popover: "0 0 0 1px rgba(15, 23, 42, 0.08), 0 16px 32px -4px rgba(15, 23, 42, 0.08)"
motion:
  ease-out-tactile: "cubic-bezier(0.23, 1, 0.32, 1)"
  ease-drawer: "cubic-bezier(0.32, 0.72, 0, 1)"
  press-duration: "100ms"
  dialog-duration: "200ms"
---

# Design System: Ute Prism Enterprise

## 1. Filosofi & Karakter Visual

**Ute Prism Enterprise** adalah sistem desain antarmuka berskala produksi untuk operasional toko fisik, teknisi reparasi, logistik, dan e-commerce suku cadang. Desain mengadopsi prinsip:
- **Restrained Craft:** Menghindari elemen dekoratif berlebih. Blur dan material tembus cahaya diaplikasikan fungsional sebagai pembeda level kedalaman (elevation), bukan sekadar kosmetik.
- **Physical Feel (Tactile):** Respon interaksi tombol dan dialog meniru mekanika hardware instan tanpa jeda perceptif.
- **Data Densitas Tinggi Namun Teratur:** Backoffice memaksimalkan pemanfaatan ruang layar untuk efisiensi input, sementara Storefront memberikan ruang sentuh jempol (thumb-zone) yang lega.

---

## 2. Arsitektur Lintas Perangkat (Universal Ergonomics)

### A. Large Screen / Desktop PC (≥ 1024px)
- **POS Kasir:** Dual-pane view permanen (Kiri: Grid produk & search scanner; Kanan: Cart, multi-payment calculation, hotkeys F2/F4/F6/Esc).
- **Backoffice Navigation:** Sidebar expanded modular atau icon rail kolapsibel (disimpan di `localStorage`).
- **Data Table:** Sticky header padat, zebra striping tipis (`rgba(255,255,255,0.015)` dark), aksi row terlihat saat hover, kolom angka selalu `text-right tabular-nums`.

### B. Tablet / POS Terminal Touchscreen (768px - 1023px)
- **Sidebar:** Berubah otomatis menjadi icon rail atau off-canvas drawer untuk memaksimalkan area kerja teknisi dan kasir.
- **Touch Sizing:** Seluruh target sentuh otomatis dinaikkan ke minimal **44×44px**.
- **Servis Board:** Kanban kolom dapat di-swipe secara horizontal dengan snapping halus (`scroll-snap-type: x mandatory`).

### C. Smartphone / Mobile Web (375px - 430px)
- **Storefront Marketplace:**
  - Sticky header dengan pencarian cepat dan keranjang belanja mengambang.
  - Floating Bottom Navigation Bar (Home, Booking, Keranjang, Akun) terikat ke `env(safe-area-inset-bottom)`.
  - Transisi modal menggunakan **Bottom Sheet (Drawer)** yang dapat digeser (drag to dismiss) dengan batas kecepatan flick/velocity (Emil framework: velocity > 0.11 memicu dismiss instan).
- **Pencegahan Zoom iOS:** Seluruh `<input>`, `<select>`, dan form kontrol menggunakan ukuran minimal `16px` (`text-base` pada mobile) untuk mencegah viewport auto-zoom Safari.

---

## 3. Motion & Animation Decision Rules (Emil Kowalski Standard)

### A. Aturan Waktu & Easing
1. **Tombol & Aksi Sentuh:**
   - Feedback tekan: `:active { transform: scale(0.97); }`
   - Durasi: `100ms` - `140ms` dengan `transition: transform 120ms cubic-bezier(0.23, 1, 0.32, 1)`.
2. **Modal & Dialog:**
   - Masuk: `opacity: 0; transform: scale(0.96);` menuju `opacity: 1; transform: scale(1);`.
   - Durasi: Maksimal `180ms - 220ms` (`ease-out`).
   - Dilarang keras memulai animasi dari `scale(0)`.
3. **Dropdown & Popover:**
   - Wajib menggunakan `transform-origin` dinamis sesuai titik tombol trigger.
   - Durasi: `150ms` `ease-out`.
4. **Keyboard-Triggered Actions:**
   - Operasi shortcut kasir (F2, F4, Enter) dilarang memiliki animasi buka-tutup lambat. Dialog pembayaran kasir harus muncul instan (<80ms) agar tidak memperlambat antrean toko.
5. **Aksesibilitas Gerak:**
   - Hormati `@media (prefers-reduced-motion: reduce)` dengan mematikan semua transformasi spasial (`transform`), hanya mempertahankan transisi `opacity` lembut.

---

## 4. Format Keuangan & Numerik (Mandatori Non-Negotiable)

1. **Tabular Figures:**
   - Seluruh elemen yang menampilkan angka harga, kuantitas stok, nomor SKU, nomor tiket servis (`SRV-XXXXXXXX`), nomor jurnal, dan jam wajib memiliki kelas `.tabular-nums`.
2. **Pemisah Ribuan Titik (Format Indonesia):**
   - Mengikuti ADR 0011: Format nominal Rupiah menggunakan titik sebagai pemisah ribuan (contoh: `Rp 1.450.000`), bukan koma.
3. **Alignment Tabel:**
   - Kolom nama dan teks: Rata Kiri (`text-left`).
   - Kolom stok, kuantitas, harga, total, status keuangan: Rata Kanan (`text-right`).
   - Kolom status pill & badge: Rata Tengah (`text-center`).

---

## 5. Komponen Standar Ute Prism Enterprise

| Komponen | Spesifikasi Visual Dark Mode | Spesifikasi Visual Light Mode | Interaksi & State |
| :--- | :--- | :--- | :--- |
| **`GlassCard`** | Background `rgba(14, 21, 43, 0.75)`, border `rgba(255,255,255,0.06)`, radius `rounded-xl`. | Background `rgba(255,255,255,0.92)`, border `rgba(15,23,42,0.06)`, radius `rounded-xl`. | Hover elevation ringan bila bertindak sebagai tautan/kartu interaktif. |
| **`PrismButton`** | Solid Electric Indigo (`#5B4FE9`), hover `#493ED9`, active scale `0.97`. | Solid Electric Indigo (`#5B4FE9`), teks putih, active scale `0.97`. | Non-disabled cursor pointer, transisi transform spesifik. |
| **`StatusPill`** | Background opacity `15%`, border tint `30%`, teks terang dengan kontras WCAG AA ≥ 4.5:1. | Background opacity `12%`, teks pekat terbaca jelas. | Dot indikator opsional berkedip hanya untuk status kritis/proses. |
| **`StockGauge`** | Barometer visual: Hijau Mint (`≥ min*2`), Kuning Amber (`≤ min`), Merah Kritis (`0`). | Sama dengan versi dark. | Tooltip rincian unit stok riil per gudang/cabang. |
| **`DataTable`** | Dual-scroll horizontal bila overflow, sticky header latar pekat `bg-ink-900`. | Sticky header `bg-white`, zebra striping halus `bg-slate-50/50`. | Baris transisi hover `bg-white/[0.03]` tanpa pergeseran layout. |
| **`BarcodeScanInput`** | Border glow Electric Indigo saat fokus, mint flash 120ms saat scan sukses. | Border glow Electric Indigo saat fokus, mint flash 120ms saat scan sukses. | Autofocus aman, listener hardware scanner terisolasi. |

---

## 6. Do's and Don'ts (Quality Bar)

### DO
- Gunakan kurva bezier kustom `--ease-out: cubic-bezier(0.23, 1, 0.32, 1)`.
- Pastikan area sentuh tombol aksi utama minimal 44×44px di seluruh breakpoint.
- Selalu cantumkan label status cabang aktif pada header atau sidebar backoffice.
- Berikan empty-state visual yang elegan dan jelas saat data tabel atau pencarian kosong.
- Gunakan drawer/bottom-sheet native gesture saat diakses lewat mobile.

### DON'T
- DILARANG menggunakan `transition: all`. Tentukan properti eksplisit (`transition: transform 120ms ease-out, opacity 120ms ease-out`).
- DILARANG menggunakan `transform: scale(0)` untuk transisi masuk elemen.
- DILARANG menggunakan animasi lambat (>200ms) untuk alur kerja transaksi kasir atau scanning barcode.
- DILARANG menggunakan font berukuran di bawah 16px untuk elemen input pada layar mobile.
- DILARANG membocorkan ID foreign key internal atau timestamp database mentah di antarmuka publik atau drilldown viewer.
