# PRD BACKEND — Ute Parts ERP, POS & Marketplace
**Versi:** 1.0 | **Tanggal:** 15 September 2026 | **Perusahaan:** Ute Parts
**Dokumen pasangan:** `PRD-Frontend-UteParts.md` — semua kode `[API: ...]` di sini adalah kontrak yang dipanggil oleh layar-layar di dokumen frontend. Jangan mengubah nama/kontrak tanpa menyesuaikan dokumen frontend.
**Urutan upload ke agentic AI:** dokumen ini diupload **kedua**, setelah PRD Frontend.

---

## 0. Cara Membaca Dokumen Ini (untuk Agentic AI / Coding Agent)
1. Bangun backend **mengikuti kontrak endpoint di §5** — nama route, method, dan bentuk response harus konsisten agar frontend (sudah/ sedang dibangun dari PRD Frontend) tidak perlu diubah.
2. Semua istilah entitas mengikuti **Kamus Data §2** — satu nama untuk satu konsep di seluruh kode (migration, model, variabel).
3. Ikuti urutan build di **§9 Roadmap 7 Hari** — jangan lompat ke modul lanjutan sebelum modul fondasi (Auth/RBAC/Multi-cabang) selesai dan teruji.
4. Install MCP/skill di **§10** sebelum mulai, terutama Context7 (API Laravel versi terbaru berubah cepat) dan filesystem MCP scoped.

---

## 1. Arsitektur Sistem

```
[ Marketplace (public) ]     [ Backoffice ERP/POS (internal) ]
            \                          /
             \                        /
           Laravel App (monolith modular, TALL stack)
        ┌─────────────────────────────────────────────┐
        │ Auth (Sanctum, session) + RBAC (Spatie)      │
        │ Modules: POS | Servis | WMS | CRM | Akunting │
        │          | Reseller/Komisi | Marketplace     │
        │ Queue: database driver (ringan, RAM terbatas)│
        └─────────────────────────────────────────────┘
                    │                │
              MySQL (utama)     Integrasi eksternal:
                                 - Duitku (payment)
                                 - Biteship (kurir)
                                 - WA Gateway/Email (notifikasi)
```

- **Monolith modular** dipilih (bukan microservices) karena target server CyberPanel + OpenLiteSpeed **1GB RAM** dan target live 7 hari — microservices akan memperlambat development dan boros resource.
- **Versi Laravel: 13** (rilis 17 Maret 2026, stabil per tanggal dokumen ini). Alasan pakai 13, bukan 12:
  - Upgrade dari 12→13 nyaris tanpa breaking change (framework resmi menyebut migrasi ±10 menit), jadi tidak menambah risiko meski target 7 hari.
  - Ada SDK AI native & dukungan PHP Attributes bawaan — relevan karena project ini dibangun & akan dikembangkan lanjut oleh agentic AI, memudahkan pola kode yang lebih deklaratif dan mudah diverifikasi otomatis.
  - Dukungan **JSON:API** & **passkey authentication** bawaan bisa dipakai langsung untuk kontrak API §5 dan opsi login tanpa password di masa depan (tidak wajib di MVP, tapi pondasinya sudah tersedia).
  - Konsekuensi: **PHP minimum 8.3** (Laravel 13 drop dukungan PHP 8.1/8.2). **Wajib dicek dulu** modul PHP di CyberPanel/OpenLiteSpeed sudah `lsphp83` ke atas sebelum provisioning server — kalau panel hosting cuma sampai PHP 8.2, turunkan ke **Laravel 12** (masih disupport bugfix hingga Agustus 2026, security lebih lama) sebagai fallback aman, tanpa mengubah PRD ini secara struktural karena API Laravel 12↔13 nyaris identik di level yang dipakai project ini.

- Struktur folder Laravel memakai **domain-driven module** di dalam `app/Modules/{Pos,Servis,Wms,Crm,Akunting,Reseller,Marketplace,Rbac}` agar tetap rapi walau monolith, dan memudahkan agentic AI bekerja per-modul tanpa saling tabrakan.

---

## 2. Kamus Data (Entitas Inti)

| Entitas | Deskripsi singkat | Relasi kunci |
|---|---|---|
| `Cabang` (Branch) | Lokasi toko fisik | punya banyak `Gudang`, `User`, `Transaksi` |
| `Gudang` (Warehouse) | Lokasi penyimpanan stok, bisa lebih dari 1 per cabang | punya `StokItem` |
| `Produk` / `SkuVariant` | Master sparepart (>1000 SKU), varian per kompatibilitas | `StokItem`, `HargaTier` |
| `StokItem` | Kuantitas produk per gudang | `Gudang`, `Produk` |
| `Pelanggan` (Customer) | Akun publik, punya `TierMembership` | `Transaksi`, `TiketServis` |
| `TierMembership` | Silver/Gold/Platinum (nama & syarat configurable) | benefit diskon, poin |
| `Reseller` | Sub-tipe akun pelanggan dengan flag `is_reseller=true` + skema komisi | `Komisi` |
| `TiketServis` | 1 unit servis HP dari terima s.d. diambil | `Pelanggan`, `Garansi`, item sparepart terpakai |
| `Garansi` | Masa garansi hasil servis | `TiketServis` |
| `Transaksi` (POS/Order) | 1 transaksi penjualan (POS atau marketplace) | `Pelanggan`, `Produk`, `Pembayaran` |
| `Pembayaran` | Catatan pembayaran (tunai/Duitku) | `Transaksi` |
| `Pengiriman` | Data pengiriman via Biteship | `Transaksi` |
| `Komisi` | Komisi reseller per transaksi | `Reseller`, `JurnalAkuntansi` |
| `JurnalAkuntansi` | Entri jurnal double-entry | `AkunCOA` |
| `AkunCOA` | Chart of Account | — |
| `Piutang` / `Utang` (AR/AP) | Invoice pelanggan (kasbon) & utang supplier | `JurnalAkuntansi` |
| `Role` / `Permission` | RBAC (Spatie laravel-permission) | `User` |
| `User` | Staf internal | `Role`, akses multi-`Cabang` |
| `Channel` | Akun toko di 1 marketplace eksternal (Shopee/Tokopedia/dst) | `ChannelProductMapping`, `ChannelOrder` |
| `ChannelProductMapping` | Pemetaan SKU lokal ↔ SKU channel | `Produk`, `Channel` |
| `ChannelOrder` | Order masuk dari channel eksternal | `Channel`, `Transaksi` |

---

## 3. RBAC — Role & Permission Matrix (rekomendasi default)

Pakai **`spatie/laravel-permission`**. Role bersifat konfigurabel dari UI (§ Pengaturan di frontend), tapi seed default:

| Role | Permission group |
|---|---|
| `super-admin` | semua permission |
| `admin-toko` | `pos.*`, `wms.view/transfer`, `servis.*`, `crm.view`, `laporan.cabang` |
| `kasir` | `pos.create`, `pos.view-own`, `stok.view` |
| `teknisi` | `servis.view`, `servis.update-status`, `servis.input-sparepart` |
| `staff-gudang` | `wms.*` |
| `finance` | `akunting.*`, `piutang.*`, `utang.*`, `komisi.approve` |
| `marketing` | `crm.*`, `tier.manage`, `reseller.view` |

Permission disimpan granular per-aksi (`modul.aksi`), dicek di middleware route **dan** dikembalikan sebagai daftar ke frontend lewat `[API: RBAC-02]` supaya menu dirender dinamis. Setiap `User` bisa punya akses ke >1 `Cabang` lewat tabel pivot `user_cabang` — semua query data operasional (stok, transaksi, laporan) **wajib** di-scope ke cabang aktif di session.

---

## 4. Modul & Aturan Bisnis

### 4.1 Multi-Cabang & Multi-Gudang (WMS)
- 1 Cabang bisa punya lebih dari 1 Gudang (misal gudang utama + rak toko).
- Stok = agregat per `(produk_id, gudang_id)`. Transfer antar gudang membuat 2 entri (keluar-masuk) berstatus `pending` sampai gudang tujuan konfirmasi terima.
- Stock opname: input per lokasi, selisih (`selisih = stok_sistem - stok_fisik`) butuh approval supervisor sebelum menyesuaikan `StokItem` + membuat jurnal penyesuaian stok otomatis.
- Barcode/QR: setiap `SkuVariant` punya kode unik; scan dipakai di POS, terima barang, transfer, opname.

### 4.2 Harga Multi-Tier
Tabel `harga_tier` menyimpan `(produk_id, tier_id | reseller=true, harga)`. Prioritas resolusi harga saat transaksi:
1. Harga khusus reseller (jika pembeli reseller & ada override) →
2. Harga tier membership pelanggan →
3. Harga retail default.
`[API: PRICING-01]` mengembalikan harga final + breakdown alasan (untuk transparansi di struk/invoice).

### 4.3 Servis HP & Garansi
Alur status (state machine, tidak boleh mundur kecuali admin override + log alasan):
`diajukan_online?` → `diterima` → `diagnosa` → `menunggu_approval` → (`disetujui` | `ditolak`) → `dikerjakan` → `qc` → `selesai` → `diambil`.
- Saat `menunggu_approval` dibuat, sistem catat estimasi biaya & kirim notifikasi + link approve/reject publik (token unik per tiket, tanpa login wajib untuk approve cepat, tapi status penuh hanya terlihat lengkap jika login).
- Saat `selesai`, sistem buat `Garansi` (durasi dari konfigurasi jenis servis, default 30 hari untuk servis, 90 hari untuk part original — configurable).
- Sparepart yang dipakai teknisi otomatis mengurangi `StokItem` gudang cabang & masuk sebagai HPP di jurnal transaksi servis.

### 4.4 CRM & Tier Membership
- Kenaikan tier otomatis dihitung dari akumulasi belanja (`total_belanja_12bulan`) via scheduled job harian, threshold per tier configurable.
- Benefit tier: % diskon, multiplier poin loyalty, prioritas antrian servis (opsional flag).
- Poin loyalty: earn dari transaksi (`% dari nominal`), redeem sebagai potongan (konfigurasi rasio poin:rupiah).

### 4.5 Reseller & Komisi (terhubung Akunting)
- `Reseller` = `Pelanggan` dengan skema komisi (`%` atau nominal tetap per kategori produk).
- Saat transaksi marketplace dari kode referral reseller **atau** reseller login langsung, sistem hitung komisi → status `pending`.
- Approval komisi (role `finance`) → otomatis membuat `JurnalAkuntansi` (debit Beban Komisi, kredit Utang Komisi) dan entri `Utang` ke reseller tsb, muncul di modul pembayaran utang.

### 4.6 Akunting
- **COA** standar retail+servis (Kas, Bank, Piutang Usaha, Persediaan, Utang Usaha, Utang Komisi, Pendapatan Penjualan, Pendapatan Jasa Servis, HPP, Beban Operasional, dll) — di-seed, editable.
- **Jurnal otomatis** dari: POS (kas/bank masuk, pendapatan, HPP, stok berkurang), Servis (pendapatan jasa + HPP sparepart), Pembelian ke supplier (persediaan bertambah, utang bertambah), Komisi reseller (§4.5), penyesuaian stok opname.
- **Piutang (AR)**: transaksi kasbon/termin ke pelanggan (misal korporat/reseller besar) tercatat sebagai piutang dengan jatuh tempo & reminder.
- **Utang (AP)**: pembelian ke supplier & komisi reseller yang belum dibayar.
- Laporan: Laba Rugi, Neraca, Arus Kas (metode tidak langsung), Buku Besar per akun — per cabang & konsolidasi. Generate via query agregat terjadwal + cache (bukan realtime compute berat, demi RAM 1GB).

### 4.7 Marketplace & Pembayaran (Duitku)
- Order marketplace membuat `Transaksi` berstatus `menunggu_pembayaran` → panggil Duitku `createTransaction` → simpan `reference/merchantOrderId` → redirect/popup ke halaman pembayaran Duitku.
- Callback Duitku (`POST /webhook/duitku`) **wajib** verifikasi signature sebelum update status (`lunas`/`gagal`/`kadaluarsa`), lalu trigger: kurangi stok, buat jurnal, jika ada reseller buat komisi pending, kirim notifikasi ke pelanggan.
- Metode: VA, QRIS, e-wallet, retail outlet (sesuai daftar `getPaymentMethods` Duitku, ambil dinamis, jangan hardcode).
- Sandbox dulu (`DUITKU_SANDBOX=true`) sampai lolos UAT, baru production key.

### 4.8 Pengiriman (Biteship)
- Saat checkout, panggil `POST https://api.biteship.com/v1/rates/couriers` dengan alamat asal (cabang/gudang terpilih) & tujuan → tampilkan opsi kurir & estimasi biaya.
- Setelah pembayaran lunas → buat order pengiriman via Biteship Order API, simpan `tracking_id`, expose status ke `[API: ACCOUNT-05 tracking]`.
- Origin bisa multi-cabang (pilih cabang/gudang terdekat dari stok tersedia).

### 4.9 Notifikasi
- Channel: WhatsApp (via provider gateway — pilih salah satu saat implementasi: Fonnte/Wablas/resmi WA Business API, keputusan biaya ada di tangan user) + Email (Laravel Mail, SMTP CyberPanel).
- Event yang trigger notifikasi: status servis berubah, estimasi biaya perlu approval, pembayaran Duitku sukses/gagal, kenaikan tier, komisi cair, stok PO diterima (internal).
- Karena RAM 1GB terbatas, notifikasi **wajib lewat queue** (`database` driver + `php artisan queue:work` via Supervisor), bukan dikirim sinkron saat request.

### 4.10 Omnichannel Marketplace (Shopee, Tokopedia, Blibli, TikTok Shop, Lazada, dll.)
Konsep setara **Jubelio/BigSeller**: satu sumber kebenaran stok & produk yang tersinkron ke banyak kanal jualan, dan semua order dari kanal manapun masuk ke satu dashboard.

**Arsitektur adapter (wajib, agar channel baru mudah ditambah tanpa ubah core):**
```
app/Modules/Omnichannel/
 ├─ Contracts/ChannelAdapterInterface.php   # pullOrders(), pushStock(), pushPrice(), fetchProducts(), mapProduct()
 ├─ Adapters/ShopeeAdapter.php
 ├─ Adapters/TokopediaAdapter.php
 ├─ Adapters/BlibliAdapter.php
 ├─ Adapters/TiktokShopAdapter.php   (opsional fase 2)
 ├─ Adapters/LazadaAdapter.php       (opsional fase 2)
 └─ Services/ChannelSyncService.php  # orkestrasi, dipanggil oleh queue job
```

**Entitas tambahan (masuk §2 Kamus Data):**
| Entitas | Deskripsi |
|---|---|
| `Channel` | 1 akun toko di 1 marketplace (mis. "Ute Parts Official — Shopee"), simpan kredensial OAuth (partner_id/key, access_token, refresh_token, expired_at) terenkripsi |
| `ChannelProductMapping` | Pemetaan `produk_id` lokal ↔ `item_id/sku` di channel tsb + `gudang_id` sumber stok untuk channel itu |
| `ChannelOrder` | Order masuk dari channel, terhubung ke `Transaksi` lokal setelah diproses (field `channel_order_id`, `channel_status`) |

**Alur kerja:**
1. **Autentikasi channel** — admin hubungkan toko marketplace lewat OAuth (Shopee Open API, Tokopedia Seller Center authorization, dst) dari layar Manajemen Channel. Token refresh otomatis via scheduled job sebelum kedaluwarsa.
2. **Pemetaan produk** — UI mapping SKU lokal ↔ SKU tiap channel (manual pertama kali, bisa "auto-match by SKU/nama" untuk mempercepat >1000 produk), status per produk: `belum-dipetakan` / `tersinkron` / `error`.
3. **Sinkronisasi stok** — setiap perubahan `StokItem` pada gudang yang dikonfigurasi sebagai sumber channel tsb → job queue push stok terbaru ke channel (debounce beberapa detik agar tidak spam API saat banyak perubahan beruntun). Update stok antar-channel diserialkan **per SKU** untuk mencegah race condition oversell.
4. **Sinkronisasi harga** (opsional per channel) — harga bisa berbeda per channel (markup untuk menutup biaya admin marketplace), disimpan di `harga_tier` dengan scope `channel_id`.
5. **Order ingestion** — webhook (Shopee/Tokopedia mendukung push notification) atau polling terjadwal (fallback untuk channel tanpa webhook reliable) → buat `ChannelOrder` → setelah validasi, buat `Transaksi` lokal (`source = channel:{nama}`), kurangi stok, buat jurnal akunting, catat **beban komisi/biaya admin marketplace** sebagai akun terpisah dari komisi reseller internal (§4.5).
6. **Order Terpadu (Unified Order Inbox)** — satu dashboard menampilkan order dari POS, Marketplace Ute Parts sendiri, dan semua channel eksternal, dengan status pengiriman tersinkron (update resi dikirim balik ke channel via API mereka).

**Skala MVP vs Fase 2 (penting untuk target 7 hari):**
Membangun adapter penuh untuk 5+ marketplace sekaligus **tidak realistis** dalam 7 hari bersamaan dengan seluruh modul ERP lain. Rekomendasi:
- **MVP (7 hari)**: bangun arsitektur adapter generik (`ChannelAdapterInterface`) + implementasi **1 channel prioritas (Shopee, karena API paling terbuka & terdokumentasi)** end-to-end (stok, order, harga).
- **Fase 2 (minggu berikutnya)**: tambah `TokopediaAdapter`, lalu `BlibliAdapter`, `TiktokShopAdapter`, `LazadaAdapter` — tinggal implement interface yang sama, tidak menyentuh core ERP.
- Ini harus dikomunikasikan eksplisit ke agentic AI agar tidak memaksakan semua channel sekaligus dan mengorbankan kualitas modul inti (POS, Servis, Akunting).
`[API: OMNI-01..06]`

### 4.11 Migrasi Data
- **Excel**: import produk, stok awal, pelanggan via template `.xlsx` (pakai `maatwebsite/laravel-excel`), dengan mode dry-run/preview sebelum commit.
- **SID Retail (POS lama)**: karena skema database SID Retail tidak dipublikasikan resmi, buat **layer mapping generik**: ekspor data dari SID Retail ke CSV/Excel (fitur ekspor bawaan aplikasi tsb) → import lewat mapping kolom fleksibel (UI mapping "kolom sumber → field Ute Parts") — jangan asumsikan skema tabel SID Retail, karena berisiko salah jika beda versi. Field minimal yang perlu dipetakan: kode produk, nama produk, kategori, harga beli, harga jual, stok per lokasi, data pelanggan (nama, HP, alamat).

---

## 5. Kontrak API (ringkas — detail request/response dielaborasi saat implementasi, agent boleh menambah field non-breaking)

| Kode | Method & Route | Fungsi |
|---|---|---|
| `AUTH-01` | `POST /api/login` | Login staf/pelanggan (beda guard: `staff`, `customer`) |
| `AUTH-02` | `POST /api/select-branch` | Set cabang aktif di session (staf multi-cabang) |
| `RBAC-01` | `GET/POST/PUT /api/users` | CRUD user & assign role/cabang |
| `RBAC-02` | `GET /api/me/permissions` | Daftar permission user login (untuk render menu) |
| `DASH-01` | `GET /api/dashboard/summary` | Widget dashboard sesuai role |
| `POS-01` | `POST /api/pos/transaksi` | Buat transaksi kasir |
| `POS-02` | `GET /api/pos/produk?search=&gudang=` | Cari produk + stok untuk kasir |
| `PRICING-01` | `GET /api/pricing/{produk_id}?customer_id=` | Resolusi harga final per pelanggan |
| `SERVICE-01..06` | `/api/servis/*` | CRUD tiket servis, update status, approval estimasi |
| `SERVICE-07` | `POST /api/servis/booking-online` | Booking servis dari marketplace |
| `WMS-01..08` | `/api/wms/*` | Stok, transfer, opname, terima PO |
| `CRM-01..05` | `/api/crm/*` | Profil pelanggan, tier, broadcast |
| `RESELLER-01..04` | `/api/reseller/*` | Data reseller, komisi, approval |
| `ACC-01..10` | `/api/akunting/*` | COA, jurnal, laporan, AR/AP |
| `SHOP-01,02` | `GET /api/shop/produk`, `GET /api/shop/produk/{id}` | Katalog publik |
| `SHIP-01` | `POST /api/shipping/rates` | Cek ongkir Biteship |
| `PAY-01` | `POST /api/payment/create` | Buat transaksi Duitku |
| `PAY-02` | `POST /webhook/duitku` | Callback Duitku (signature-verified) |
| `ACCOUNT-01..05` | `/api/account/*` | Dashboard pelanggan, tracking servis, komisi reseller |
| `OMNI-01` | `GET/POST /api/omnichannel/channels` | List & hubungkan channel marketplace (OAuth) |
| `OMNI-02` | `POST /api/omnichannel/channels/{id}/mapping` | Mapping produk lokal ↔ SKU channel |
| `OMNI-03` | `GET /api/omnichannel/orders` | Order terpadu semua channel (Unified Order Inbox) |
| `OMNI-04` | `POST /api/omnichannel/sync-stock` | Trigger manual sinkron stok (selain auto via queue) |
| `OMNI-05` | `POST /webhook/channel/{channel}` | Penerima webhook order/update dari tiap marketplace |
| `OMNI-06` | `GET /api/omnichannel/channels/{id}/status` | Status koneksi & kesehatan sinkronisasi channel |

Semua response memakai format konsisten:
```json
{ "success": true, "data": {...}, "message": "" }
```
Error memakai HTTP status sesuai (422 validasi, 403 permission, 401 auth, 500 server) + `message` berbahasa Indonesia yang jelas.

---

## 6. Keamanan

- **Auth**: Laravel Sanctum (SPA-style cookie session untuk web, token untuk kebutuhan API eksternal jika ada).
- **RBAC** ditegakkan di **middleware route**, bukan hanya UI — setiap endpoint sensitif dicek permission server-side.
- **Rate limiting** di endpoint login & webhook pembayaran.
- **Webhook Duitku**: verifikasi signature wajib, idempotency check (jangan proses callback duplikat 2x mengubah stok/jurnal dobel).
- **Audit log**: semua perubahan harga, stok manual, approval komisi, dan override status servis tercatat (`who, when, what, before/after`).
- **Enkripsi**: kredensial Duitku/Biteship di `.env`, tidak pernah di-commit; password user di-hash bcrypt (default Laravel).
- **Backup**: dump MySQL harian otomatis (cron CyberPanel) + retensi minimal 7 hari sebelum go-live.
- **HTTPS wajib** (Let's Encrypt via CyberPanel) untuk seluruh domain, termasuk webhook.
- **Validasi input** ketat di semua form (FormRequest Laravel), terutama endpoint publik marketplace (rawan abuse).

---

## 7. Infrastruktur & Optimasi (CyberPanel + OpenLiteSpeed, RAM 1GB)

Server 1GB RAM adalah **constraint keras** — keputusan arsitektur berikut wajib diikuti:
- PHP-LSAPI worker terbatas (2–3 worker), gunakan **OPcache** wajib aktif + `opcache.memory_consumption` disesuaikan (~64–96MB).
- **Queue worker** via Supervisor, jangan jalankan queue sync di request (§4.9).
- **Cache**: pakai `file` atau `database` cache driver (hindari Redis jika RAM sangat ketat; jika masih memungkinkan sisakan ≥128MB, Redis kecil boleh dipertimbangkan — keputusan final saat provisioning).
- **MySQL**: tuning `innodb_buffer_pool_size` kecil (~128–256MB), pastikan index di semua kolom yang dipakai filter (produk_id, gudang_id, cabang_id, status servis, dsb) mengingat >1000 SKU + banyak transaksi.
- **Asset**: build Tailwind/Alpine production (minified), pakai CDN untuk asset statis jika trafik marketplace tinggi agar tidak membebani server asal.
- **Image produk**: resize/optimize saat upload (jangan simpan resolusi asli mentah, boros disk & bandwidth kecil).
- **Swap**: pastikan swap file dikonfigurasi di server (mitigasi RAM 1GB saat lonjakan trafik/migrasi data awal).
- Pertimbangkan **horizontal-lite**: jika trafik marketplace tumbuh, pisahkan proses queue/notifikasi ke waktu off-peak dulu sebelum upgrade RAM server.

---

## 8. Non-Functional Requirements

- Uptime target 99% (wajar untuk MVP di shared/VPS kecil).
- Semua laporan besar (akunting, stok) di-generate async + cache 15 menit, bukan query realtime berat tiap request.
- Skalabilitas data: desain skema siap >5 cabang, >1000 SKU, ribuan transaksi/bulan tanpa redesign besar (index & partisi logis by `cabang_id`).
- Testing minimal: unit test untuk resolusi harga tier (§4.2) dan state machine servis (§4.3) karena keduanya paling rawan bug bisnis.

---

## 9. Roadmap 7 Hari (MVP, dibantu agentic AI)

| Hari | Fokus |
|---|---|
| 1 | Setup project Laravel + TALL stack, migration skema inti (Cabang, Gudang, User, Role/Permission, Produk, StokItem), auth + RBAC dasar |
| 2 | Modul WMS (stok, transfer, opname) + Modul POS dasar (transaksi, cetak struk) |
| 3 | Modul Servis HP (kanban status, approval estimasi, garansi) + notifikasi dasar |
| 4 | Modul CRM/Tier + Reseller/Komisi + integrasi awal ke Akunting (jurnal otomatis POS & Servis) |
| 5 | Modul Akunting lengkap (COA, laporan, AR/AP) + Marketplace katalog & checkout (tanpa payment dulu) |
| 6 | Integrasi Duitku (payment) + Biteship (ongkir) + tracking servis publik + migrasi data (Excel/SID Retail) + arsitektur adapter Omnichannel + integrasi penuh **1 channel (Shopee)** |
| 7 | Hardening keamanan (§6), optimasi server (§7) & uji multi-perangkat (§8-responsif), UAT menyeluruh, deploy production + smoke test |
| Fase 2 (pasca-MVP) | Tambah adapter Tokopedia → Blibli → TikTok Shop → Lazada memakai `ChannelAdapterInterface` yang sudah ada, tanpa mengubah modul inti |

---

## 10. Rekomendasi Skill/MCP untuk Agentic AI (khusus kerja Backend)

| Tool | Fungsi | Prioritas |
|---|---|---|
| **Context7 MCP** | Dokumentasi versi terbaru Laravel/Sanctum/Spatie Permission/Livewire — hindari API usang | Wajib |
| **Filesystem MCP** (scoped project) | Baca/tulis migration, model, controller secara aman | Wajib |
| **Sequential Thinking MCP** | Memecah state machine servis & alur akunting (double-entry) jadi langkah terverifikasi sebelum coding | Wajib untuk §4.3 & §4.6 |
| **GitHub MCP** | PR per modul, review diff sebelum merge ke `main`, penting karena banyak modul saling terhubung | Sangat disarankan |
| **MySQL/Postgres-style DB MCP** (arahkan ke MySQL) | Cek langsung skema & data hasil migration, debug query lambat | Sangat disarankan |
| **Sentry MCP** (atau setup Sentry manual) | Tangkap error production sejak hari 1 deploy, krusial untuk MVP 7 hari | Disarankan |
| **Playwright MCP** | Uji end-to-end alur kritikal: checkout Duitku sandbox, approval servis, transfer stok | Wajib sebelum go-live |
| **Memory/codebase-memory MCP** | Simpan keputusan skema & kontrak API antar sesi kerja panjang agent, mencegah agent lupa kontrak `[API: ...]` di dokumen ini | Sangat disarankan |

> Catatan untuk agent: sebelum membuat migration apa pun, verifikasi lagi field di §2 Kamus Data via `sequential-thinking` agar tidak ada entitas yang menyimpang dari PRD Frontend.

### 10.1 Cara Wiring ke OpenCode (khusus jika eksekusi pakai OpenCode CLI)
OpenCode membaca instruksi project dari file **`AGENTS.md`** di root project, dan konfigurasi MCP/permission dari **`opencode.json`** — bukan lewat prompt chat biasa. Langkah setup:

1. Di root project (sejajar dengan `PRD-Frontend-UteParts.md` dan `PRD-Backend-UteParts.md`), buat file **`AGENTS.md`** berisi arahan singkat, contoh:
   ```markdown
   # Ute Parts — Instruksi Agent

   Baca dulu sebelum mengerjakan apa pun:
   1. PRD-Frontend-UteParts.md
   2. PRD-Backend-UteParts.md

   Ikuti roadmap 7 hari di PRD-Backend-UteParts.md §9. Jangan lompat modul.
   Semua kontrak API (`[API: ...]`) di kedua dokumen wajib dipatuhi persis namanya.
   Stack: Laravel 13 (PHP 8.3+), TALL stack, MySQL, target server 1GB RAM (lihat §7 backend PRD).
   Sebelum coding, cek dokumentasi terbaru lewat Context7 MCP — jangan andalkan pengetahuan lama soal Livewire/Laravel.
   ```
2. Buat/edit **`opencode.json`** di root project untuk mengaktifkan MCP yang direkomendasikan §10:
   ```json
   {
     "$schema": "https://opencode.ai/config.json",
     "mcp": {
       "context7": { "type": "remote", "url": "https://mcp.context7.com/mcp" },
       "playwright": { "type": "local", "command": ["npx", "-y", "@playwright/mcp"] },
       "sequential-thinking": { "type": "local", "command": ["npx", "-y", "@modelcontextprotocol/server-sequential-thinking"] }
     },
     "permission": {
       "edit": { "./**": "allow" }
     }
   }
   ```
   Tambahkan MCP lain (GitHub, DB MySQL, Sentry) sesuai kebutuhan modul yang sedang dikerjakan — tidak perlu semuanya aktif dari hari pertama, cukup Context7 + Playwright + Sequential Thinking di awal.
3. Jalankan `opencode` dari root folder project tsb. Karena OpenCode otomatis membaca `AGENTS.md`, kamu tidak perlu paste ulang isi PRD ke chat — cukup mulai prompt kerja per modul, misalnya: *"Kerjakan Hari 1 dari roadmap: setup Laravel 13 + migration inti + RBAC dasar."*
4. Ulangi arahan "baca PRD dulu" di setiap sesi baru yang panjang/terpisah, karena context window tetap bisa penuh di sesi sangat panjang — `AGENTS.md` membantu tapi tidak menggantikan pengecekan berkala.

---

## 11. Definition of Done (Backend MVP)

- [ ] Semua endpoint §5 berjalan & terverifikasi via Playwright/manual test dari layar frontend terkait
- [ ] RBAC teruji: user tanpa permission mendapat 403 di level API, bukan hanya disembunyikan di UI
- [ ] Jurnal akunting otomatis benar (debit=kredit) untuk POS, Servis, Komisi, Penyesuaian Stok
- [ ] Webhook Duitku idempotent & signature-verified, teruji di sandbox
- [ ] Biteship rate & order API terhubung, tracking status tampil ke pelanggan
- [ ] Migrasi data Excel & mapping SID Retail berhasil dry-run tanpa korupsi data
- [ ] Server CyberPanel/OpenLiteSpeed 1GB RAM stabil di bawah simulasi beban dasar (queue jalan, tidak OOM)
- [ ] Audit log & backup harian aktif sebelum go-live
- [ ] Channel Shopee berhasil terhubung, stok tersinkron dua arah, order Shopee masuk otomatis ke Transaksi & jurnal akunting
- [ ] Arsitektur `ChannelAdapterInterface` siap menerima adapter channel baru tanpa mengubah modul inti (dibuktikan lewat 1 adapter stub tambahan yang lolos contract test)
- [ ] Semua response API konsisten & ringan (tidak over-fetch) agar performa tetap baik saat diakses dari koneksi mobile
