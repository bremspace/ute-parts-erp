# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Staf Internal (Backoffice `/app/*`):**
  - **Kasir POS:** Menangani antrean transaksi retail fisik berkecepatan tinggi, pemindaian barcode hardware, multi-metode pembayaran (tunai, QRIS/Duitku, transfer), penahanan transaksi (*park/hold*), dan pencetakan struk thermal ESC/POS.
  - **Teknisi Servis HP:** Melakukan diagnosa kerusakan gadget, pembaruan tahapan tiket servis (state machine 8 status), pemisahan input sparepart vs jasa, pengujian kontrol kualitas (QC), dan penerbitan garansi servis.
  - **Staf Gudang & Logistik (WMS):** Mengelola katalog ribuan SKU sparepart, stock opname fisik vs sistem dengan approval selisih, mutasi/transfer stok multi-gudang, dan penerimaan PO supplier.
  - **Finance & Akunting:** Mengawasi jurnal double-entry otomatis (COA 23 akun standar), mengelola piutang pelanggan (AR) dan utang supplier/komisi (AP), menyetujui komisi reseller, dan mencetak laporan keuangan (Laba Rugi, Neraca, Arus Kas).
  - **Store Manager & Administrator:** Mengonfigurasi cabang toko, matriks hak akses RBAC (Spatie), komisi reseller, dan memantau KPI performa cabang via dashboard analitik.

- **Pelanggan & Mitra Eksternal (Storefront `/`):**
  - **Pelanggan Retail:** Membeli suku cadang dan aksesori gadget via e-commerce mobile-first, menghitung ongkir multi-kurir Biteship, dan membayar via gateway Duitku.
  - **Pelanggan Servis:** Mengajukan booking perbaikan secara online, melacak status pengerjaan tiket servis secara real-time, serta memberikan persetujuan/penolakan estimasi biaya secara mandiri melalui tautan token publik tanpa login wajib.
  - **Member Loyalty Bertier:** Menikmati potongan harga dinamis berdasarkan tier membership (Silver, Gold, Platinum) dan mengumpulkan poin belanja.
  - **Reseller & Agen:** Mengakses pricelist tier reseller khusus, menyebarkan kode referral, serta memantau saldo komisi transaksi di dashboard mitra.

## Product Purpose

- Menyediakan ekosistem ERP, POS, servis gadget, dan marketplace terpadu dalam satu solusi modular (*single source of truth*) untuk bisnis retail suku cadang dan perbaikan elektronik multi-cabang.
- Menghubungkan secara instan transaksi di meja kasir fisik dan bengkel teknisi dengan pemotongan stok gudang dan penjurnalan akuntansi keuangan double-entry secara otomatis tanpa rekonsiliasi manual.
- Memberikan transparansi menyeluruh kepada pelanggan mengenai status perbaikan perangkat dan biaya suku cadang guna membangun kepercayaan pelanggan.

## Positioning

- Solusi operasional bengkel perbaikan gadget & distributor suku cadang multi-cabang yang menggabungkan kecepatan input workstation kasir/teknisi dengan kemudahan belanja e-commerce publik dan kepatuhan akuntansi double-entry.
- Arsitektur "Dual-Zone":
  - **Backoffice Cockpit:** Antarmuka berdensitas informasi tinggi (*high-density*), default dark-mode (`#0B1020`), dioptimalkan untuk navigasi keyboard dan pemindai barcode fisik tanpa pergeseran layar.
  - **Storefront Marketplace:** Pengalaman belanja e-commerce modern yang ramah sentuhan jempol (*thumb-zone friendly*), default light-mode, dengan drawer/bottom-sheet interaktif untuk pengguna mobile.

## Operating Context

- **Toko Fisik & Meja Kasir:** Lingkungan kerja cepat dengan barcode scanner fisik, keyboard hotkeys (F2, F4, F6, Esc), printer thermal ESC/POS, dan laci uang kasir.
- **Meja Kerja Servis Teknisi:** Penerimaan unit fisik gadget pelanggan, pencatatan keluhan & kelengkapan unit, pengujian komponen, pembongkaran perangkat, pemakaian suku cadang bergaransi, dan pengujian akhir (QC).
- **Gudang Suku Cadang:** Pengelolaan lebih dari 1.000 SKU komponen sensitif (LCD, baterai, fleksibel, IC) dengan pembedaan batch/lokasi rak, pergerakan transfer antar-gudang berstatus pending konfirmasi, dan stock opname berkala.
- **Infrastruktur Produksi:** Linux VPS mandiri (non-CyberPanel, PHP 8.5+ dengan OPcache aktif), database MySQL, antrean asinkronus (`database` driver + Supervisor) untuk notifikasi WhatsApp/Email dan webhook eksternal yang aman serta hemat memori.

## Capabilities and Constraints

- **Isolasi Multi-Cabang Absolut:** Seluruh query data operasional (stok, transaksi, tiket servis, laporan) wajib di-scope ke `cabang_id` aktif dalam sesi. Dilarang keras membocorkan data lintas cabang.
- **State Machine Servis 8-Status:** Siklus ketat: `diajukan_online` → `diterima` → `diagnosa` → `menunggu_approval` → (`disetujui` | `ditolak`) → `dikerjakan` → `qc` → `selesai` → `diambil`. Transisi mundur dilarang kecuali melalui override supervisor dengan pencatatan alasan audit.
- **Pemisahan Part vs Jasa Servis:** Pemakaian sparepart memotong `StokItem` gudang dan mencatat HPP; jasa servis mencatat pendapatan jasa tanpa memotong fisik inventaris.
- **Persetujuan Publik Berbasis Token:** Pelanggan dapat menyetujui atau menolak estimasi servis melalui URL token unik tanpa kewajiban login akun.
- **Akuntansi Double-Entry Otomatis:** Setiap transaksi kasir, tiket servis selesai, penerimaan barang, dan persetujuan komisi otomatis menghasilkan entri `JurnalAkuntansi` berimbang. Format nomor jurnal wajib menyertakan ID cabang untuk mencegah benturan nomor antar-cabang.
- **Pricing Multi-Tier Dinamis:** Resolusi harga otomatis dengan hirarki: Override Reseller → Tier Membership Pelanggan → Harga Retail Standar.
- **Integrasi Eksternal Idempoten:** Callback webhook Duitku dan pesanan marketplace diverifikasi secara ketat dan idempoten guna mencegah penyesuaian stok ganda atau duplikasi jurnal keuangan.

## Brand Commitments

- **Identitas Merek:** **Bremspace ERP** (white-labeled) untuk antarmuka publik, backoffice, dokumen cetak, dan komponen visual.
- **Sistem Desain:** Mengikuti **Bremspace Prism Enterprise** (`DESIGN.md`):
  - **Palet Utama:** Electric Indigo (`#5B4FE9` / `#4F46E5`), Solder Copper / Accent (`#E8873B`), Signal Mint (`#1FBF8F`), Amber (`#F5A623`), Red (`#EF4444`), Ink Slate (`#0B1020` s/d `#1E2B54`).
  - **Tipografi:** Plus Jakarta Sans & Instrument Sans untuk display/heading, Inter untuk teks body, serta format `tabular-nums` wajib pada seluruh angka finansial, kuantitas stok, nomor SKU, dan serial number.
  - **Format Angka:** Mengikuti konvensi Indonesia (ADR 0011) dengan pemisah ribuan titik (contoh: `Rp 1.450.000`).
  - **Sensasi Taktil & Mikro-Interaksi:** Waktu respons interaksi cepat (<140ms), animasi dialog instan tanpa jeda yang menghambat kasir, dan bebas dari ornamen visual berlebihan (*anti-slop*).

## Evidence on Hand

- **Dokumentasi Produk:** `docs/prd/PRD-Frontend-UteParts.md` (kontrak UI/UX) dan `docs/prd/PRD-Backend-UteParts.md` (entitas, alur bisnis, kontrak API).
- **Spesifikasi Desain:** `DESIGN.md` (Bremspace Prism Enterprise — token warna, tipografi, arketipe tata letak, dan do's & don'ts).
- **Arsitektur Teknis:** Rekam keputusan `docs/adr/` (ADR 0006 s/d ADR 0012) dan status implementasi di `SESSION-STATE.md`.
- **Implementasi Kode Nyata:** 15 modul lengkap di `app/Modules/` (Akunting, Crm, Dashboard, Hr, Marketplace, Notifikasi, Omnichannel, Pos, Rbac, Report, Reseller, Servis, Webhook, Wms, Workflow).
- **Data Uji:** Seeder transaksi, akun COA, dan matriks role/permission Spatie teruji di `database/seeders/` dan `tests/Feature/`.
- **Batasan Bukti (Larangan Mengarang):** Kredensial gateway live dan nomor kontak/identitas riil pelanggan tidak boleh difabrikasi dalam pengujian atau dokumentasi.

## Product Principles

- **Zero Cross-Branch Leakage:** Privasi dan batas data antar cabang fisik adalah integritas mutlak yang tidak boleh dikompromikan.
- **Workstation Ergonomics First:** Layar operasional kasir dan teknisi mengutamakan kecepatan input, kepadatan informasi yang terstruktur, dan zero unnecessary clicks.
- **Strict Financial & Inventory Traceability:** Setiap barang keluar/masuk dan pergeseran dana wajib menghasilkan jejak audit dan jurnal akuntansi yang transparan.
- **Mobile Storefront with Native Polish:** Area belanja publik harus terasa seperti aplikasi native yang gesit, mudah digunakan dengan satu tangan, dan ramah pengguna umum.

## Accessibility & Inclusion

- Rasio kontras teks minimum WCAG AA (≥ 4.5:1) di seluruh permukaan antarmuka dark mode maupun light mode.
- Ukuran target sentuh interaktif minimum 44×44px pada layar tablet dan ponsel pintar.
- Kontrol form pada layar sentuh menggunakan font minimum 16px untuk mencegah pembesaran otomatis (*auto-zoom*) browser iOS Safari.
- Menghormati pengaturan pengguna `@media (prefers-reduced-motion: reduce)` dengan mematikan transformasi spasial saat aktif.
