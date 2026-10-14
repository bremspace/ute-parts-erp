# Rencana Refactor Menyeluruh Frontend Ute Parts

## Diagnosis Masalah Utama

### 1. Warna & Kontras (Penyebab Pusing/Silau):
- Palet gelap `#070A14` & `#0B1020` dengan aksen ungu jenuh `#5B4FE9` dan orange `#E8873B` bertabrakan keras (visual overstimulation).
- Efek border kaca (`glass-panel`) bertumpuk-tumpuk dengan border putih `border-white/10` membuat mata cepat lelah saat shift panjang.
- Mode terang (`data-theme="light"`) hasil invert CSS variabel menghasilkan kontras yang tidak konsisten (teks abu di atas panel transparan).

### 2. Kepadatan Informasi (Layout Clutter):
- Terlalu banyak badge kecil berjejer di dalam satu kartu produk (diskon, status stok, kategori, kompatibilitas, terjual).
- Storefront hanya menyediakan grid rapat tanpa opsi tata letak sesuai kenyamanan mata pengguna.

### 3. Kebutuhan Fitur Baru:
- Belum ada opsi View Switcher (Grid vs List vs Compact/Dense) pada katalog produk (Marketplace & POS Kasir).

---

## Arah Desain Baru: "Modern Minimalist Precision" (Clean Slate / Swiss Functionalism)

- **Neutral Palette:** Beralih ke slate/zinc hangat yang tenang (Dark: `#0F172A`/`#1E293B`, Light: `#FFFFFF`/`#F8FAFC`).
- **Brand Accent:** Electric Indigo diganti ke Cobalt Precision (`#3B82F6` / `#2563EB`) atau Deep Indigo (`#4F46E5`).

---

## Fase Pelaksanaan

### [x] Fase 1: Color Palette & Design Tokens (`prism-tokens.css` & `app.css`)
- [x] Rombak palet warna dasar: ganti warna silau ke neutral zinc/slate tenang.
- [x] Perbaiki sistem kontras dark/light mode agar teks tajam dan tidak pudar.
- [x] Sederhanakan efek glassmorphism agar tidak berlapis-lapis.

### [x] Fase 2: View Switcher & State Management
- [x] Tambahkan state Alpine/Livewire `viewMode`: `'grid'` | `'list'` | `'compact'` dengan persistensi `localStorage`.
- [x] Buat komponen toggle switcher modern di toolbar produk.

### [x] Fase 3: Marketplace Catalog Refactor (`shop-catalog.blade.php`)
- [x] Implementasikan rendering 3 mode: Grid lega & clean, List mendatar informatif, Compact baris cepat.
- [x] Rapikan visual hierarchy: kurangi badge bertumpuk, buat tipografi harga dan nama produk dominan dan bersih.

### [x] Fase 4: POS Kasir Screen Refactor (`pos-kasir.blade.php`)
- [x] Terapkan palet baru yang tidak silau untuk layar kasir.
- [x] Tambahkan opsi Grid vs List pada katalog kasir agar kasir bisa memilih mode tercepat.

### [x] Fase 5: Komponen Inti & Master Shell (`x-prism.*`, `backoffice`, `marketplace`)
- [x] Sesuaikan button, card, status pill, table, dan header navigation ke palet minimalis baru.

### [x] Fase 6: Build & Verifikasi Visual
- [x] Jalankan `npm run build`, uji responsiveness desktop & mobile, pastikan test suite backend tetap lolos 100%.
