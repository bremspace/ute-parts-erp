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
**Ute Prism** memadukan estetika hardware/elektronika modern: panel kaca berlapis terstruktur (*glassmorphism*), garis sirkuit halus (*circuit lines*) sebagai divider dekoratif, dan *tactile feedback* neumorphic-lite untuk kecepatan kasir POS. Sistem ini melayani dua zona operasional:
1. **Backoffice ERP/POS (`/app/*`):** High-density, keyboard-driven, default *dark mode* (`--up-ink-900`) untuk mengurangi silau di meja kasir dan meja teknisi.
2. **Marketplace Publik (`/`):** Mobile-first, *breathing room* lega, default *light mode* dengan glassmorphism subtle di atas katalog produk.

## Colors
- **Primary (`#5B4FE9` - Electric Indigo):** Aksi utama, brand hero, link aktif, focus ring.
- **Primary Dark (`#3B2FC9`):** State hover dan active pada tombol primer.
- **Accent (`#E8873B` - Solder Copper):** Komisi reseller, badge tier, call-to-action promosi.
- **Mint (`#1FBF8F` - Signal Mint):** Transaksi lunas, stok aman, verifikasi sukses, indikator online cabang.
- **Amber (`#F5A623`):** Peringatan stok menipis, servis menunggu persetujuan pelanggan, status pending.
- **Red (`#EF4444`):** Error validasi, stok habis, transaksi dibatalkan, tiket servis ditolak.
- **Ink Spectrum:**
  - Dark mode: Background utama `#070A14`, panel `#0B1020` / `#0E152B`, teks `#F6F7FB` / `#E2E8F0`.
  - Light mode: Background utama `#F8FAFC`, panel `#FFFFFF` (dengan glass opacity 72%), border `#E2E8F0`, teks `#0F172A`.

## Typography
- **Headings & Brand Title:** `Sora` atau `Instrument Sans` (modern, geometric, terasa presisi perangkat keras).
- **Body Text & Form Fields:** `Instrument Sans` / `Inter` (keterbacaan tinggi, rendering tajam).
- **Financial & Numerical Data:** Wajib menggunakan `font-variant-numeric: tabular-nums` (`.tabular-nums`) di semua tabel harga, ringkasan kas, stok, dan laporan keuangan.

## Layout
- **Multi-Device Grid & Breakpoints:**
  - `xxs`: 320px (iPhone SE, small devices)
  - `xs`: 375px (standard mobile)
  - `sm`: 640px
  - `md`: 768px (tablet)
  - `lg`: 1024px (desktop)
  - `xl`: 1280px (wide desktop)
- **Ergonomi Sentuh (Mobile):**
  - Minimum touch target 44×44px untuk semua tombol, nav links, dan input kontrol.
  - Safe-area insets (`env(safe-area-inset-top)` & `env(safe-area-inset-bottom)`) terpasang di header dan bottom navigation bar.
- **Density:**
  - Backoffice: data tables padat, margin efisien (8-16px), floating summary bar di mobile.
  - Marketplace: margin 16-24px, card padding lega, spacing bernafas.

## Elevation & Depth
- **Glass Surfaces:**
  - Dark mode: `rgba(14, 21, 43, 0.75)` dengan `backdrop-filter: blur(16px)` dan border `1px solid rgba(255, 255, 255, 0.08)`.
  - Light mode: `rgba(255, 255, 255, 0.72)` dengan `backdrop-filter: blur(16px)` dan border `1px solid rgba(15, 23, 42, 0.1)`.
- **Shadows:**
  - Panel: `0 12px 32px 0 rgba(0, 0, 0, 0.37)` (dark) / `0 12px 32px 0 rgba(15, 23, 42, 0.08)` (light).
  - Hover: `0 16px 36px 0 rgba(91, 79, 233, 0.15)`.

## Shapes
- **Radius Scale:**
  - `rounded-md` (8-10px): Badges, mini tags, status pills, inside inputs.
  - `rounded-xl` / `rounded-2xl` (14-18px): Cards, modals, drawers, main buttons.
  - `rounded-full`: Avatar, round indicator dots, floating action buttons.

## Components
- **PrismButton:** Tombol interaktif dengan transisi transform terarah (`160ms cubic-bezier(0.23, 1, 0.32, 1)`) dan feedback sentuh `:active:scale-[0.97]`.
- **GlassCard:** Kontainer serbaguna dengan surface glass, border highlight subtle, dan opsi hover lift.
- **DataTable:** Wrapper tabel dengan horizontal scrolling touch-safe, header sticky, striping halus, dan kolom aksi terfokus.
- **StatusPill:** Indikator status berwarna tegas dengan teks kontras tinggi (sesuai WCAG AA).
- **BarcodeScanInput:** Input spesifik barcode dengan highlight indigo dan trigger scan visual.
- **TierBadge:** Badge Silver, Gold, Platinum dengan aksen gradasi tembaga Ute Prism.

## Do's and Don'ts
- **DO:**
  - Gunakan `tabular-nums` untuk semua angka uang, stok, dan nomor seri.
  - Gunakan kurva custom easing (`cubic-bezier(0.23, 1, 0.32, 1)`) dengan durasi < 250ms untuk aksi UI.
  - Berikan feedback `:active:scale-[0.97]` pada tombol.
  - Buat touch target minimal 44px di mobile.
  - Lindungi hover states dengan `@media (hover: hover) and (pointer: fine)`.
- **DON'T:**
  - JANGAN gunakan `transition: all`. Selalu sebutkan properti eksplisit (`transform`, `opacity`, `border-color`).
  - JANGAN menganimasikan modal/dropdown dari `scale(0)` (mulai dari `scale(0.95)` dan `opacity: 0`).
  - JANGAN gunakan motif garis sirkuit di dalam tabel data (hanya di divider hero, empty state, atau login background).
  - JANGAN biarkan teks berstatus memiliki kontras rendah di atas panel transparan.
  - JANGAN biarkan input form di iOS memicu auto-zoom (font size minimal 16px di mobile).
