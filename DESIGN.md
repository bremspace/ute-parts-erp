---
name: Bremspace Prism Enterprise
description: Precision high-density operational aesthetic and fluid touch commerce for multi-branch hardware retail & repair lifecycle
version: 2.0.0
last_updated: 2026-11-17
tokens:
  brand:
    primary: "#4F46E5"
    primary-hover: "#4338CA"
    primary-active: "#3730A3"
    accent: "#D97706"
    mint: "#10B981"
    amber: "#F59E0B"
    red: "#EF4444"
  neutral-dark:
    ink-950: "#090D16"
    ink-900: "#0F172A"
    ink-850: "#141E33"
    ink-800: "#1E293B"
    ink-700: "#334155"
    ink-600: "#475569"
    ink-500: "#64748B"
    ink-400: "#94A3B8"
  neutral-light:
    ink-300: "#CBD5E1"
    ink-200: "#E2E8F0"
    ink-100: "#F1F5F9"
    ink-50: "#F8FAFC"
    white: "#FFFFFF"
  typography:
    display: "Plus Jakarta Sans, Instrument Sans, -apple-system, sans-serif"
    body: "Instrument Sans, Inter, -apple-system, sans-serif"
    numbers: "Instrument Sans, Inter, monospace"
  motion:
    ease-out-tactile: "cubic-bezier(0.23, 1, 0.32, 1)"
    ease-drawer: "cubic-bezier(0.32, 0.72, 0, 1)"
    duration-fast: "120ms"
    duration-normal: "180ms"
    duration-modal: "220ms"
---

# Bremspace Prism Enterprise — Design System Standard (PRD)

Dokumen ini adalah spesifikasi arsitektur desain antarmuka baku (Design System PRD) untuk ekosistem ERP dan Marketplace **Bremspace**. Seluruh perbaikan frontend, pembuatan komponen Blade `<x-ui.*>`, dan refactoring tampilan modul wajib mematuhi standar di bawah ini.

---

## 1. Filosofi & Karakter Visual

**Bremspace Prism Enterprise** dirancang untuk dua zona pengguna yang berbeda namun harmonis:
1. **Backoffice Cockpit (`/app/*`):** Lingkungan kerja staf (kasir, teknisi reparasi, staf gudang, akuntan, owner) berdurasi panjang (8–10 jam/hari). Mengutamakan **densitas informasi tinggi**, **ergonomi mata (rasio kontras ~13.5:1)**, navigasi keyboard instan, dan minim dekorasi tidak fungsional.
2. **Marketplace Storefront (`/*`):** Antarmuka publik belanja suku cadang & booking servis konsumen. Mengutamakan **mobile-first ergonomics**, navigasi jempol (*thumb-zone*), tipografi jernih, dan alur checkout bebas hambatan.

### Tiga Pilar Desain (Anti-AI Slop Manifesto):
- **Restrained Craft over Visual Noise:** Tidak menggunakan gradien neon spekulatif, glassmorphism tebal (`backdrop-blur-2xl`) di atas tabel data padat, atau animasi lambat yang menghambat pekerjaan.
- **Physical Feel (Tactile Hardware Feedback):** Tombol dan kartu merespon sentuhan secara instan (`:active { transform: scale(0.97); }`) meniru saklar mekanis perangkat keras.
- **Data Densitas Tinggi Namun Teratur:** Angka finansial, kuantitas stok, nomor seri, dan SKU selalu menggunakan font monospace bertabular (`tabular-nums`) dengan perataan kanan (*right-aligned*).

---

## 2. Sistem Token & Presisi Tipografi

### A. Semantic Color Palette

| Token Semantic | Nilai Light Mode | Nilai Dark Mode | Penerapan |
|---|---|---|---|
| `canvas-root` | `#F8FAFC` (Alabaster Slate) | `#090D16` (Obsidian Charcoal) | Background utama viewport |
| `surface-panel` | `#FFFFFF` | `#0F172A` (Ink 900) | Kartu modul, kontainer tabel |
| `surface-elevated` | `#FFFFFF` (Shadow L2) | `#141E33` (Ink 850) | Modal dialog, drawer, dropdown popover |
| `border-subtle` | `rgba(15, 23, 42, 0.08)` | `rgba(255, 255, 255, 0.08)` | Hairline divider tabel, pembatas kartu |
| `border-strong` | `rgba(15, 23, 42, 0.16)` | `rgba(255, 255, 255, 0.16)` | Border input aktif, tombol sekunder |
| `text-main` | `#0F172A` | `#F1F5F9` | Teks judul, label utama, angka |
| `text-muted` | `#64748B` | `#94A3B8` | Subtitle, helper text, breadcrumbs |
| `brand-primary` | `#4F46E5` | `#6366F1` | Aksi utama tunggal, status aktif |
| `status-mint` | `#10B981` | `#34D399` | Lunas, selesai, stok aman, sukses |
| `status-amber` | `#F59E0B` | `#FBBF24` | Menunggu approval, draft, stok menipis |
| `status-red` | `#EF4444` | `#F87171` | Ditolak, void, kadaluarsa, stok habis |

### B. Kalibrasi Ergonomi Mata (Eye Fatigue Protocol)
- **Target Kontras ~13.5:1:** Hindari kontras hitam pekat murni (`#000000`) dengan putih murni (`#FFFFFF`) berkontras 21:1 yang memicu halasi dan mata cepat lelah.
- **Pintu Warna Operasional (Operational Color Gating):** Warna cerah (hijau, kuning, merah) dicadangkan khusus untuk status penting/urgensi operasional. Informasi netral (kategori, ID, catatan umum) wajib menggunakan tint abu-abu netral.

### C. Hirarki Tipografi & Aturan Angka

| Level | Ukuran Desktop | Ukuran Mobile | Weight | Line Height | Keterangan |
|---|---|---|---|---|---|
| **Display** | 28px (`text-2xl`) | 22px (`text-xl`) | Bold (700) | 1.15 | Dashboard headline, total sales |
| **Title** | 18px (`text-lg`) | 16px (`text-base`) | SemiBold (600) | 1.25 | Judul modal, judul tabel, nama modul |
| **Section** | 14px (`text-sm`) | 14px (`text-sm`) | SemiBold (600) | 1.30 | Subheader kartu, header tab |
| **Body** | 13px (`text-xs`+) | 14px (`text-sm`) | Regular (400) | 1.50 | Teks deskripsi, rincian data |
| **Caption** | 11px (`text-[11px]`) | 11px (`text-[11px]`) | Medium (500) | 1.40 | Timestamp, status badge, kode seri |
| **Monospace / Tabular** | Sesuai konteks | Sesuai konteks | 500–700 | — | `font-mono tabular-nums` (Wajib untuk Rupiah, SKU, Stok) |

### D. Aturan Cegah Auto-Zoom iOS Safari (16px Input Rule)
- Seluruh elemen `<input>`, `<select>`, dan `<textarea>` pada viewport mobile (`< 640px`) **WAJIB berukuran minimal 16px (`text-base`)**.
- Jika ukuran font form di bawah 16px pada iPhone/iPad, Safari akan memperbesar (auto-zoom) layar secara otomatis saat field disentuh, yang merusak layout dan menggeser tombol aksi.
- Standar kelas: `text-base sm:text-sm` (16px di mobile, 14px di desktop).

---

## 3. Matematika Responsif Presisi (Desktop, Tablet, Mobile)

### A. Breakpoint Standar
- **`xxs` (320px):** Perangkat compact (iPhone SE generasi lama, handheld barcode mini).
- **`xs` (375px):** Standar smartphone modern (iPhone 12/13/14/15/16).
- **`sm` (640px):** Smartphone orientasi lanskap / phablet.
- **`md` (768px):** Tablet iPad portrait / layar POS terminal kasir compact.
- **`lg` (1024px):** Tablet iPad Pro lanskap / Workstation laptop kecil.
- **`xl` (1280px):** Desktop PC kasir & backoffice standar.
- **`2xl` (1536px):** Monitor monitor monitor workstation kantor utama.

### B. Target Sentuh & Ergonomi Jempol (Touch Targets)
- **Smartphone Touch Target:** Minimal **44×44px** untuk seluruh tombol, link navigasi, dan checkbox operasional.
- **Desktop Hit Target:** Minimal **38×38px** untuk tombol aksi, **32×32px** khusus icon-action di dalam row tabel data.
- **Touch Manipulation:** Setiap elemen interaktif menyertakan properti CSS `touch-action: manipulation;` untuk menghapus latensi 300ms tap delay di mobile.

### C. Guard Nol Horizontal Overflow (Zero-Overflow Policy)
- Root kontainer wajib menerapkan `max-w-full overflow-x-hidden` atau `overflow-x-clip`.
- Seluruh elemen flex child teks panjang wajib menerapkan `min-w-0` dan `truncate` untuk mencegah flex child meregang melebihi lebar layar.
- Chip filter horizontal carousel wajib menyertakan kelas `shrink-0 whitespace-nowrap` agar teks tombol tidak terperas secara vertikal menjadi satu huruf per baris.

### D. Konversi Tabel Data Padat Menjadi Kartu di Mobile (Table-to-Card Rule)
- Tabel padat dengan lebih dari 4 kolom dilarang dipaksa merapat di layar ponsel.
- Standar konversi:
  - Layar Tablet/Desktop (`≥ 768px`): Render tabel HTML semantik (`table`, `thead`, `tbody`) berdensitas tinggi dengan header sticky.
  - Layar Mobile (`< 768px`): Render slot kartu adaptif (`<div class="block md:hidden">`) yang merangkum info kunci (Nama unit, status badge, SKU, harga, dan tombol aksi cepat).

### E. Integrasi Safe Area Notch & Bottom Nav Bar (Safe Area Math)
- Seluruh bar aksi bawah yang menempel (sticky bottom action bar / POS Pay bar / Bottom Sheet) wajib memperhitungkan safe-area iPhone:
  ```css
  padding-bottom: max(env(safe-area-inset-bottom, 0px), 0.75rem);
  ```
- **Pemberantasan Tabrakan Navbar (Zero Navbar Collision):** Pada rute transaksi penuh seperti POS Kasir (`/app/pos`), Floating Mobile Dock global backoffice disembunyikan sepenuhnya sehingga bilah pembayaran kasir (`BAYAR`) memiliki kepemilikan layar 100% tanpa tumpang tindih.

---

## 4. Gerakan Bertujuan & Poles TALL Stack (Emil Kowalski Framework)

### A. Parameter Waktu & Kurva Easing Baku

```css
/* Easing Sentuh Cepat untuk Tombol & Feedback Interaktif */
--ease-out-tactile: cubic-bezier(0.23, 1, 0.32, 1);

/* Easing Alami Lembut untuk Slide Drawer & Bottom Sheet (Ionic standard) */
--ease-drawer: cubic-bezier(0.32, 0.72, 0, 1);
```

| Tipe Elemen | Durasi Masuk | Durasi Keluar | Kurva Easing | Transform Awal |
|---|---|---|---|---|
| **Feedback Tombol (`:active`)** | 100ms | 120ms | `--ease-out-tactile` | `scale(0.97)` |
| **Modal Dialog** | 200ms | 140ms | `--ease-out-tactile` | `scale(0.96) translate-y-2` (Bukan scale 0!) |
| **Drawer / Bottom Sheet** | 250ms | 200ms | `--ease-drawer` | `translate-y-full` (mobile) / `translate-x-full` |
| **Dropdown Popover** | 160ms | 120ms | `--ease-out-tactile` | `scale(0.97) opacity-0` |
| **Toast Notifikasi** | 220ms | 180ms | `--ease-out-tactile` | `translate-y-2 opacity-0` |

> **Prinsip Emil Kowalski:** Dilarang menganimasikan elemen dari `scale(0)` karena di dunia nyata tidak ada objek yang membesar dari titik nol. Selalu mulai dari `scale(0.96)` dengan `opacity: 0`.

### B. Aksesibilitas Gerak (Reduced-Motion Compliance)
```css
@media (prefers-reduced-motion: reduce) {
    *, ::before, ::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}
```

### C. Kontrak Performa Livewire & Alpine.js
1. **Pencegahan FOUC (`[x-cloak]`):** Seluruh elemen berstatus Alpine wajib dipasangi `x-cloak` dengan CSS global `[x-cloak] { display: none !important; }`.
2. **Kunci DOM Loop (`wire:key`):** Seluruh perulangan `@foreach(...)` dalam Blade Livewire wajib memiliki `wire:key` deterministik unik (contoh: `wire:key="pos-cart-item-{{ $item['id'] }}-{{ $loop->index }}"`).
3. **Skeleton Anti-Shift (`wire:loading`):** Saat menunggu respons backend, tampilkan skeleton berdimensi proporsional (`<x-ui.skeleton>`) menggunakan `wire:loading.delay` untuk mengeliminasi pergeseran layout (CLS).
4. **Modal Decoupling (Lazy Loading):** Modal yang tertutup dilarang ada di pohon DOM aktif. Bungkus pemanggilan modal dengan `@if($showModal)` agar Livewire tidak mendiff ribuan node tersembunyi.

---

## 5. Pustaka Komponen Blade Baku (`<x-ui.*>`)

Komponen standar telah dibangun di `resources/views/components/ui/`:

### 1. `<x-ui.button>`
- **Lokasi:** `resources/views/components/ui/button.blade.php`
- **Props:** `variant` (primary, secondary, ghost, danger, mint, pos-action), `size` (sm, md, lg, icon), `type`, `disabled`, `loading`, `target` (Livewire target), `icon`, `iconRight`.
- **Fitur:** Feedback sentuh `:active:scale-[0.97]`, auto-spinner saat wire:loading, min-height 44px di mobile.

### 2. `<x-ui.input>`
- **Lokasi:** `resources/views/components/ui/input.blade.php`
- **Props:** `label`, `name`, `id`, `type`, `placeholder`, `error`, `hint`, `icon`, `iconRight`, `mono`, `required`, `disabled`, `inputmode`.
- **Fitur:** Auto-sizing 16px di mobile (anti iOS zoom), indikator error otomatis, slot icon leading/trailing.

### 3. `<x-ui.select>`
- **Lokasi:** `resources/views/components/ui/select.blade.php`
- **Props:** `label`, `name`, `id`, `error`, `hint`, `required`, `disabled`, `icon`.
- **Fitur:** Chevron dropdown kustom, 16px di mobile, styling konsisten dark & light mode.

### 4. `<x-ui.badge>`
- **Lokasi:** `resources/views/components/ui/badge.blade.php`
- **Props:** `variant` (neutral, primary, mint, amber, danger, blue, purple), `size` (sm, md), `dot` (boolean), `pulse` (boolean).
- **Fitur:** Dot status dengan animasi denyut opsional, border hairline rapi.

### 5. `<x-ui.card>`
- **Lokasi:** `resources/views/components/ui/card.blade.php`
- **Props:** `title`, `subtitle`, `hover`, `padding`, `rounded`, slots (`header`, `action`, `footer`).
- **Fitur:** Permukaan elegan ber-hairline border 1px, zero lag di perangkat POS, shadow terkalibrasi.

### 6. `<x-ui.data-table>`
- **Lokasi:** `resources/views/components/ui/data-table.blade.php`
- **Props:** `headers`, `empty`, `emptyMessage`, `emptyIcon`, `stickyHeader`, `target`, slots (`toolbar`, `mobile`, `pagination`).
- **Fitur:** Dual-mode (desktop table + mobile card layout), skeleton loading loader otomatis, sticky header.

### 7. `<x-ui.modal>`
- **Lokasi:** `resources/views/components/ui/modal.blade.php`
- **Props:** `name`, `show`, `title`, `subtitle`, `maxWidth` (sm, md, lg, xl, 2xl, full), `bottomSheetOnMobile`, `teleport`.
- **Fitur:** Menggunakan transisi Emil Kowalski, otomatis berubah menjadi Bottom Sheet di mobile, escape listener, teleport ke body.

### 8. `<x-ui.drawer>`
- **Lokasi:** `resources/views/components/ui/drawer.blade.php`
- **Props:** `name`, `title`, `subtitle`, `maxWidth`, `teleport`, slot (`footer`).
- **Fitur:** Slide dari kanan di desktop, geser dari bawah (bottom-sheet) di mobile, grab handle ponsel, safe-area padding.

### 9. `<x-ui.empty-state>`
- **Lokasi:** `resources/views/components/ui/empty-state.blade.php`
- **Props:** `title`, `description`, `icon`, slot tombol aksi.
- **Fitur:** Tampilan kosong yang tenang dan tidak berlebihan, ukuran proporsional.

### 10. `<x-ui.skeleton>`
- **Lokasi:** `resources/views/components/ui/skeleton.blade.php`
- **Props:** `variant` (text, circle, button, card, table-row, badge), `lines`, `width`, `height`.
- **Fitur:** Placeholder loading anti-CLS beranimasi denyut halus.

---

## 6. Inventaris & Urutan Prioritas Refactoring Modul (Phase 2 Roadmap)

Berdasarkan audit fungsional dan lalu lintas operasional, 16 modul dikelompokkan ke dalam 4 gelombang eksekusi:

### Gelombang 1: Operasional Inti & Kasir (High-Traffic Cockpits)
1. **POS (Point of Sale & Kasir):**
   - *File Kunci:* `modules/pos/livewire/pos-kasir.blade.php`, `modules/pos/livewire/partials/cart-content.blade.php`, 11 modal transaksi.
   - *Arketipe:* A (Cockpit).
   - *Fokus:* Pinned checkout bar, anti-navbar collision di mobile, kartu keranjang ringkas 2-tier, keyboard bindings (F2/F4/F6/Esc).
2. **WMS (Warehouse & Katalog Suku Cadang):**
   - *File Kunci:* `modules/wms/livewire/produk-tab.blade.php`, `stok-tab.blade.php`, `po-tab.blade.php`, `grn-tab.blade.php`, `transfer-tab.blade.php`.
   - *Arketipe:* B (Dense Data Grid).
   - *Fokus:* Konversi tabel ke kartu ringkas di mobile, filter hierarki kategori 3 tingkat, formatting `tabular-nums` untuk stok/harga.
3. **Servis (Reparasi & Workshop Kanban):**
   - *File Kunci:* `modules/servis/livewire/servis-board.blade.php`, `modules/servis/tracking-publik.blade.php`.
   - *Arketipe:* A (Cockpit Kanban).
   - *Fokus:* Horizontal swipe snapping untuk kartu tiket servis, drawer teknisi estimasi sparepart, tracking publik mobile-friendly.
4. **Marketplace (Storefront Konsumen):**
   - *File Kunci:* `modules/marketplace/livewire/shop-catalog.blade.php`, `shop-detail.blade.php`, `cart-checkout.blade.php`, `customer-account.blade.php`.
   - *Arketipe:* E-Commerce Mobile-First.
   - *Fokus:* Bottom navigation bar mengambang, alur checkout satu jempol, filter model HP & kategori cepat.

### Gelombang 2: Manajemen & Finansial
5. **Dashboard Index:** `modules/dashboard/livewire/dashboard-index.blade.php` (Widget ringkasan eksekutif, KPI omset, chart performa cabang).
6. **HR (SDM, Absensi & Payroll):** `modules/hr/livewire/karyawan-page.blade.php`, `payroll.blade.php`, `absensi.blade.php`, `mobile-scan.blade.php`.
7. **Akunting (COA, Jurnal, Pajak & Aset):** `modules/akunting/livewire/akunting-dashboard.blade.php`, `aset-register.blade.php`, `laporan-pajak.blade.php`.
8. **CRM (Pelanggan & Pipeline Leads):** `modules/crm/livewire/crm-dashboard.blade.php`, `lead-kanban.blade.php`.
9. **Workflow Approval:** `modules/workflow/livewire/approval-inbox.blade.php` (Kotak masuk multi-level approval GRN, PO, Kasbon).

### Gelombang 3: Strategis, Pelaporan & Integrasi
10. **Report Center:** `modules/report/livewire/report-builder.blade.php`, `drill-down.blade.php`.
11. **RBAC & Pengaturan Sistem:** `modules/rbac/livewire/settings-rbac.blade.php`, `session-management.blade.php`, `database-management.blade.php`.
12. **Omnichannel Command Center:** `modules/omnichannel/livewire/omnichannel-command-center.blade.php`.
13. **Reseller Dashboard:** `modules/reseller/livewire/reseller-dashboard.blade.php`.
14. **Pusat Notifikasi:** `modules/notifikasi/livewire/notification-center.blade.php`.

### Gelombang 4: Otentikasi & Shell Global
15. **Halaman Otentikasi & Error:** `auth/login.blade.php`, `customer-login.blade.php`, `two-factor-challenge.blade.php`, `errors/403.blade.php`, dll.
16. **Layout Shells & Print Templates:** `layouts/backoffice.blade.php`, `layouts/marketplace.blade.php`, `thermal-receipt-modal.blade.php`, label barcode print.

---

## 7. Checklist Eksekusi Per Siklus Modul (Phase 2 Execution Protocol)

Setiap kali modul masuk dalam siklus refactoring, lakukan urutan terstandar berikut:
- [ ] **Langkah 1 (Audit Modul):** Buka seluruh view modul terkait, catat input form, tabel, modal, dan interaksi Livewire.
- [ ] **Langkah 2 (Penerapan Primitives `<x-ui.*>`):** Ganti tombol acak dengan `<x-ui.button>`, ganti form kontrol dengan `<x-ui.input>` dan `<x-ui.select>`, ganti status pill dengan `<x-ui.badge>`, gunakan `<x-ui.card>`.
- [ ] **Langkah 3 (Optimasi Responsif 3 Layar):**
  - Pastikan 0 horizontal overflow di mobile (375px).
  - Terapkan konversi tabel ke kartu adaptif pada layar sempit.
  - Tambahkan padding safe-area pada tombol aksi lengket di bagian bawah.
- [ ] **Langkah 4 (Poles TALL Stack):**
  - Pasang `x-cloak` pada semua elemen Alpine kondisional.
  - Pastikan setiap `@foreach` memiliki `wire:key` unik yang deterministik.
  - Tambahkan `<x-ui.skeleton>` dengan `wire:loading` pada area muat data.
  - Bungkus modal dengan `@if($showModal)` agar unmounted dari DOM saat tertutup.
- [ ] **Langkah 5 (Verifikasi Interaktivitas):** Uji seluruh aksi Livewire (`wire:click`, `wire:model`), tombol simpan, pencarian filter, dan navigasi tab.
