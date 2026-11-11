---
name: Bremspace Prism Enterprise
description: Precision high-density operational aesthetic and fluid touch commerce for multi-branch hardware retail & repair lifecycle
colors:
  primary: "#4F46E5"
  primary-hover: "#4338CA"
  primary-active: "#3730A3"
  accent: "#D97706"
  mint: "#10B981"
  amber: "#F59E0B"
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

# Design System: Bremspace Prism Enterprise

## 1. Filosofi & Karakter Visual

**Bremspace Prism Enterprise** adalah sistem desain antarmuka berskala produksi untuk operasional toko fisik, teknisi reparasi, logistik, dan e-commerce suku cadang. Desain mengadopsi prinsip:
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

## 5. Komponen Standar Bremspace Prism Enterprise

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

---

## 7. Standar Arsitektur UI/UX Lintas Modul (3 Arketipe Baku)

Untuk mencegah disparitas desain dan inkonsistensi visual antar modul, setiap modul backoffice ERP UTE wajib mengadopsi salah satu dari **3 Arketipe Baku**:

### Arketipe A: Workstation Cockpit (POS Kasir, Kanban Servis)
- **Karakteristik:** Single-screen cockpit layout. Seluruh antarmuka operasional selesai dalam satu pandangan mata tanpa perlu scrolling vertikal halaman di desktop.
- **Desktop Behavior (`≥ 1024px`):** Wrapper `h-full md:overflow-hidden`, action bar bawah dipin permanen (`flex-shrink-0`), hotkey keyboard (F2, F4, F6, ESC) tercantum jelas pada tombol aksi.
- **Mobile Behavior (`< 768px`):** Layout otomatis beralih ke natural vertical flow (`overflow-y-auto`), diberi padding bawah `pb-24` sampai `pb-28` agar tombol aksi tidak tertutup oleh Backoffice Floating Mobile Dock (tinggi 56px).

### Arketipe B: Dense Data Grid (WMS Stok/Katalog, Riwayat Transaksi, CRM, Akunting)
- **Karakteristik:** Tabel data padat informasi (*high information density*) untuk pemantauan ribuan unit SKU dan transaksi.
- **Top Control:** Sticky search, filter dropdown kategori/lokasi, dan toggle kolom di bagian atas.
- **Tabel Core:** Header sticky pekat (`sticky top-0 bg-ink-900/90 backdrop-blur-md`), teks kode/SKU/nominal wajib `font-mono tabular-nums`, zebra striping halus.
- **Responsive Table:** Horizontal scroll container mandiri (`overflow-x-auto`) dengan shadow scroll indicator di mobile; kartu ringkasan accordion untuk review cepat di layar sempit.
- **Bottom Bar:** Pinned pagination & status total data (misal: "Menampilkan 1-50 dari 12.450 item").

### Arketipe C: Drawer / Slide-Over & Detail Editor (Form Part, Rincian Tiket Servis, Form Mutasi)
- **Karakteristik:** Pengeditan dan penambahan data cepat tanpa memutus konteks halaman kerja utama.
- **Desktop:** Slide-over drawer dari sisi kanan layar (`max-w-xl` atau `max-w-2xl`) dengan overlay backdrop blur tipis.
- **Mobile:** Bottom sheet yang dapat di-swipe atau drawer full-screen dengan sticky header judul & tombol silang `ESC`.
- **Action Control:** Tombol CTA Simpan (`.btn-primary-cockpit`) dan Batal (`.btn-erp-ghost`) selalu tersemat di bagian dasar sheet.

---

## 8. Kontrak Responsivitas Multi-Device (Dual-Behavior Rules)

Setiap komponen UI wajib lolos verifikasi di 3 spektrum layar:

1. **Desktop Cockpit (`1440×900` & `1920×1080`):**
   - Zero body window scrollbar untuk Arketipe A.
   - Pinned action bar dan status sesi kas/status sistem di baris bawah.
   - Hardware barcode scanner diutamakan; banner izin kamera otomatis dimatikan.
2. **Tablet / iPad (`768×1024` s/d `1024×1366`):**
   - Grid fleksibel 2 kolom (Katalog di kiri, Keranjang/Detail di kanan).
   - Area sentuh tombol minimal 40×40px.
3. **Mobile Smartphone (`375×812` s/d `430×932`):**
   - Vertical natural touch scrolling (`overflow-y-auto`).
   - Tombol shortcut horizontal carousel wajib memiliki properti `white-space: nowrap !important; flex-shrink: 0 !important;` agar teks tidak terperas secara vertikal menjadi satu huruf per baris.
   - Kontainer konten wajib menyertakan margin/padding bawah `pb-24` s/d `pb-28` untuk menjamin clearance bersih di atas Backoffice Floating Mobile Dock.
   - Target sentuhan minimal 44×44px.

---

## 9. Sistem Token, Icon & Hirarki Tombol Baku (Workstation Standard)

1. **Icon Standard:**
   - Semua icon wajib menggunakan icon Lucide / SVG dengan `stroke-width: 1.75px` baku. Dilarang mencampur icon stroke tipis 1px dengan stroke tebal 2.5px.
2. **Hirarki Tombol 3-Tier:**
   - **Tier 1 (Primary Cockpit - `.btn-primary-cockpit`):** Solid Emerald / Electric Indigo untuk aksi mutlak (BAYAR, Simpan Perubahan, Mulai Servis). Tinggi min 42px di desktop, 44px di mobile.
   - **Tier 2 (Tactile Utility - `.btn-tactile`):** Background netral berbingkai tipis dengan efek tactile saat ditekan (`active:scale-[0.98]`) untuk aksi pendukung (Tahan, Filter, Refresh).
   - **Tier 3 (Ghost Pill - `.btn-erp-ghost`):** Tombol carousel horizontal hemat ruang untuk shortcut cepat (Retur, Riwayat, Online, Mutasi Kas). Wajib `shrink-0 whitespace-nowrap`.
3. **Format Angka & Monospace:**
   - Setiap elemen yang memuat SKU, Serial Number, Barcode, Kuantitas Stok, Nomor Tiket Servis, dan Rupiah wajib memakai kelas Tailwind: `font-mono tabular-nums`.

---

## 10. Kebijakan Performa Livewire & Anti-Bloat DOM

1. **Lazy Modal Inclusions (Mandatori):**
   - Seluruh modal Livewire dilarang di-render secara permanen dalam DOM jika kondisinya sedang tertutup.
   - Wajib membungkus `@include(...)` modal dengan conditional Blade:
     ```blade
     @if(!empty($showPaymentModal))
         @include('modules.pos.livewire.modals.payment-modal')
     @endif
     ```
   - Aturan ini memangkas ribuan node DOM tersembunyi dan mempercepat Livewire DOM-diffing hingga >70%.
2. **Zero N+1 Query Contract:**
   - Dilarang keras melakukan loop query agregat (seperti hitung stok, subtotal, atau relasi) di dalam view.
   - Seluruh kalkulasi agregat wajib diselesaikan pada backend controller/Livewire component melalui eager-loading (`with(...)`) atau query agregat tunggal `whereIn` groupBy.
