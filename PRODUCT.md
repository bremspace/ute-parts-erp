# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Staf Kasir & Toko:** Melayani transaksi retail langsung di kasir (POS), scanning barcode, pemilihan pelanggan & auto-pricing tier, park/hold transaksi, cetak struk thermal ESC/POS.
- **Teknisi Servis:** Menerima unit HP pelanggan, diagnosa fisik & foto unit, input estimasi biaya, tracking pengerjaan via Kanban board, catat sparepart terpakai, uji QC, dan aktivasi garansi.
- **Staff Gudang / WMS:** Mengelola penerimaan barang supplier (PO), transfer stok antar-gudang dan cabang, serta stock opname berkala dengan barcode scanner.
- **Finance & Super Admin:** Mengawasi ringkasan omzet multi-cabang, piutang/utang (AP/AR), approval komisi reseller, dan rekonsiliasi jurnal akuntansi double-entry otomatis.
- **Pelanggan & Reseller / Agen:** Belanja sparepart HP via storefront marketplace publik, cek status perbaikan servis HP secara real-time via nomor tiket/token, dan klaim komisi/tier membership (Silver, Gold, Platinum).

## Product Purpose

Ute Parts adalah sistem terintegrasi multi-cabang untuk bisnis retail sparepart HP dan jasa servis HP. Sistem ini menyatukan dua sisi operasional: Backoffice ERP/POS untuk staf internal dan Marketplace publik untuk pelanggan serta reseller. Tujuannya adalah menghilangkan fragmentasi pencatatan manual, mencegah selisih stok antar-gudang dan cabang, memastikan akurasi penghitungan komisi reseller dan tier harga, serta mengotomatisasi jurnal akuntansi dari setiap transaksi servis dan kasir.

## Positioning

Satu-satunya sistem operasional terpadu retail & reparasi HP yang mengawinkan lifecycle servis HP (Kanban, persetujuan estimasi pelanggan via token, tracking garansi, potong stok sparepart fisik) dengan multi-branch inventory, tier-based dynamic pricing, omnichannel sync, dan double-entry accounting otomatis dalam arsitektur monolit hemat resource (1GB RAM budget).

## Operating Context

- **Backoffice (`/app/*`):** Lingkungan operasional internal bertempo cepat (toko fisik, meja kasir, meja teknisi servis, gudang). Desktop-first, keyboard shortcuts (F2 cari produk, F4 bayar, F6 tahan transaksi, ESC), barcode scan input, printer thermal ESC/POS, density data tinggi, dan default dark mode untuk mengurangi silau saat shift panjang.
- **Marketplace Publik (`/`):** Lingkungan browsing pelanggan retail & reseller dari smartphone (mobile-first), katalog e-commerce responsif, tracking tiket servis mandiri tanpa login rumit, checkout terintegrasi payment gateway (Duitku) dan kurir multi-ekspedisi (Biteship), default light mode dengan glassmorphism subtle.
- **Multi-Cabang Context:** Setiap staf operasional terikat ke konteks cabang aktif (`cabang_id`) dalam sesi; isolasi data transaksi, gudang, dan laporan per cabang wajib dipatuhi.

## Capabilities and Constraints

- **Multi-Cabang & Multi-Gudang Scoping:** Setiap transaksi, stok, tiket servis, dan laporan ter-scope secara ketat ke `cabang_id` aktif.
- **Strict Service State Machine:** Status tiket servis mengikuti alur terarah (`Diterima` → `Diagnosa` → `Estimasi Dikirim` → `Approved Customer` → `Dikerjakan` → `QC` → `Selesai` → `Diambil`). Tidak diperbolehkan perpindahan mundur kecuali override supervisor dengan audit log. Token persetujuan pelanggan dibuat saat penetapan estimasi.
- **Komponen Jurnal Akuntansi Otomatis:** POS dan penyelesaian tiket servis langsung menghasilkan jurnal akuntansi double-entry otomatis (Kas, Pendapatan Jasa, Pendapatan Sparepart, HPP, Persediaan).
- **Infrastruktur & Resource Constraint:** Berjalan di CyberPanel + OpenLiteSpeed pada server 1GB RAM (2-3 worker PHP-LSAPI). Semua antrean notifikasi (WA Gateway / Email) dan background jobs wajib menggunakan database queue worker—dilarang synchronous dispatch dalam siklus HTTP request.
- **Idempotensi Integrasi Eksternal:** Webhook Duitku dan sinkronisasi pesanan omnichannel Shopee wajib memiliki penanganan idempotensi ketat untuk menghindari duplikasi penyesuaian stok atau jurnal ganda.

## Brand Commitments

- **Nama Brand:** Ute Parts
- **Design System:** "Ute Prism" — perpaduan glassmorphism terstruktur, aksen garis sirkuit halus (motherboard motif) pada titik non-tabel, dan tactile feel neumorphic-lite pada aksi kasir POS.
- **Warna Utama:** Electric Indigo (`--up-primary`: `#5B4FE9`), Solder Copper (`--up-accent`: `#E8873B`), Signal Mint (`--up-mint`: `#1FBF8F`), Amber (`--up-amber`: `#F5A623`), Red (`--up-red`: `#EF4444`), Ink Dark (`--up-ink-900`: `#0B1020`), Ink Light (`--up-ink-50`: `#F6F7FB`).
- **Bahasa & Tone:** Seluruh UI backoffice, pesan validasi, notifikasi, dan error wajib menggunakan Bahasa Indonesia yang profesional, ramah, dan ringkas.

## Evidence on Hand

- Dokumen spesifikasi lengkap: `PRD-Frontend-UteParts.md` (spesifikasi antarmuka & token Ute Prism) dan `PRD-Backend-UteParts.md` (spesifikasi entitas, RBAC, dan kamus API).
- 11 modul modular di `app/Modules/` (Akunting, Crm, Dashboard, Marketplace, Notifikasi, Omnichannel, Pos, Rbac, Reseller, Servis, Wms).
- Rangkaian Architecture Decision Records di `docs/adr/` (ADR 0006 s.d. ADR 0012).
- Unit dan feature tests: `tests/Unit/PricingServiceTest.php`, `tests/Unit/ServisStateMachineTest.php`, `tests/Feature/JurnalServiceTest.php`, `tests/Feature/IntegrasiModulTest.php`.

## Product Principles

1. **Kecepatan Kasir & Meja Servis Nomor Satu:** Interaksi kasir POS dan penerimaan servis tidak boleh terhalang navigasi lambat atau dialog bertumpuk; prioritaskan barcode scanning dan keyboard-driven flows.
2. **Integritas Stok & Nol Selisih Antar-Cabang:** Setiap pergerakan barang (penjualan kasir, servis part, transfer gudang, pesanan online) harus tercatat dalam kartu stok ber-timestamp dan terikat gudang spesifik.
3. **Data Terisolasi, Transparansi Tersentralisasi:** Data operasional harian cabang terisolasi rapat demi keamanan, namun konsolidasi ke Super Admin dan Finance instan dan akurat.
4. **Resilience pada Resource Minimal:** UI dan backend dirancang ringan, efisien memory, menghindari re-render masif dan dependensi berat pada server 1GB RAM.

## Accessibility & Inclusion

- Memenuhi standar kontras minimum WCAG AA pada seluruh panel glassmorphism dan background gelap/terang.
- Menggunakan tipografi tabular (`tabular-nums`) untuk semua nilai mata uang rupiah dan laporan numerik.
- Mendukung interaksi keyboard penuh pada alur transaksi kasir POS.
