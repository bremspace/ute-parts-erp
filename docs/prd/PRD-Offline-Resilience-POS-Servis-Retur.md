# PRD: Arsitektur Resiliensi Offline-First (POS Kasir, Retur Barang & Pengambilan Servis)

> **Dokumen ID**: PRD-OFFLINE-02  
> **Status**: PROPOSED FOR IMPLEMENTATION REVIEW  
> **Tanggal**: 2026-11-10  
> **Target Pengguna**: Kasir POS, Customer Service, Teknisi Servis, Supervisor Cabang  
> **Konteks Sistem**: Laravel (TALL Stack), MySQL 8 (VPS Linux Direct Host), PWA Client-Side IndexedDB, Multi-Cabang Scoped (`cabang_id`), Double-Entry Ledger (`JurnalService`).  
> **Standar Kepatuhan**: ISO 9001 (Traceability), ISO 27001 (Information Security & Non-Repudiation), ISO 22301 (Business Continuity Management).

---

## 1. RINGKASAN EKSEKUTIF & AUDIT ARSITEKTUR SAAT INI

### 1.1. Status Sistem Saat Ini
Sistem `ute-parts-erp` beroperasi dengan arsitektur monolitik modern:
- **Server**: VPS Linux Murni (Direct Host, resource-efficient 1GB–2GB RAM).
- **Backend**: Laravel modular (`App\Modules\*`), strict scoping `cabang_id`, double-entry book-keeping (`JurnalService`), dan validasi RBAC per permission.
- **Frontend**: Livewire SSR + Alpine.js.
- **Fondasi Offline Tersedia**:
  - Kolom `offline_uuid`, `is_offline`, `sync_status` pada tabel `transaksi`.
  - Tabel audit: `pos_offline_sync_logs`.
  - Service backend: `PosOfflineSyncService`.
  - Store klien: `resources/js/components/offline-db.js` & `pos-offline-engine.js`.

### 1.2. Masalah Utama (Problem Statement)
Koneksi internet toko fisik rentan putus (ISP maintenance, pemadaman listrik darurat). Operasional toko yang wajib tetap berjalan tanpa internet mencakup:
1. Penjualan Kasir (POS).
2. Pengecekan produk (harga tier, barcode, rak, stok lokal).
3. Pengambilan HP yang sudah selesai diservis oleh pelanggan.
4. Klaim garansi / retur barang rusak ke toko.

---

## 2. AUDIT CELAH & STRATEGI MITIGASI (VULNERABILITY & GAP ANALYSIS)

Hasil audit mendalam menemukan 6 celah kritis jika fitur offline dieksekusi tanpa mitigasi ketat:

| ID Celah | Titik Rawan / Skenario Bahaya | Risiko Bisnis / Teknis | Solusi & Mitigasi Wajib (ISO / ERP Standard) |
|---|---|---|---|
| **GAP-01** | **Retur Produk Fiktif / Dobel Klaim Offline**<br>Pelanggan membawa barang rusak luar toko atau mengklaim retur uang tunai (cash refund) berulang saat internet mati. | Kerugian kas langsung, penerimaan unit sampah tanpa riwayat pembelian sah. | 1. **No-Cash-Refund Offline Rule**: Transaksi retur saat offline DILARANG mengeluarkan uang tunai. Retur offline HANYA boleh tukar ganti unit baru sejenis (1-to-1 replacement).<br>2. **Nota Fisik Wajib Ber-Barcode**: Kasir wajib memindai nomor nota asli.<br>3. **Draft Retur Outbox**: Transaksi retur disimpan sebagai draft bersyarat dengan audit log supervisor. |
| **GAP-02** | **Pengambilan HP Servis Belum Lunas / Kurang Bayar**<br>Pelanggan mengambil HP saat offline, kasir tidak tahu ada penambahan jasa/part oleh teknisi di server. | HP diserahkan tanpa pelunasan biaya riil (piutang macet). | 1. **Cache Tiket Aktif (`servis_active_cache`)**: Saat online, browser menyimpan snapshot tiket berstatus `qc` dan `selesai` beserta nominal sisa tagihan terverifikasi.<br>2. **Tanda Terima Pelunasan Offline**: Pembayaran sisa tagihan wajib tunai, dicatat di `servis_pickup_outbox` dengan UUID v7, dan cetak tanda serah-terima fisik. |
| **GAP-03** | **Manipulasi Jam Klien (Backdating / Replay Attack)**<br>Kasir memundurkan jam sistem PC Windows untuk menyusupkan transaksi ke shift/bulan buku yang sudah tutup. | Kerusakan laporan keuangan & audit trail akuntansi terganggu. | 1. **Monotonic UUID v7**: Pengurutan mengacu pada sequence bit UUID v7.<br>2. **Dual-Timestamp Accounting**: Server mencatat `client_transacted_at` untuk fakta kasir, namun `posting_date` jurnal buku besar mengacu pada waktu sinkronisasi server (`server_synced_at`) agar tidak merusak buku kas masa lalu (Kepatuhan ISO 27001). |
| **GAP-04** | **Tabrakan Serial Number / IMEI (`NomorSeri`)**<br>Unit sparepart ber-IMEI/SN dijual offline, sementara di VPS unit yang sama dialokasikan ke pesanan marketplace. | Duplikasi penjualan barang unik fisik (satu SN terjual 2x). | 1. **SN Locked Cache**: Master cache offline hanya menandai SN yang berstatus `tersedia` di gudang cabang aktif.<br>2. **Fisik Menang**: Jika terjadi tabrakan SN, penjualan kasir offline tetap dipertahankan; pesanan marketplace di-flag otomatis untuk pergantian alokasi SN oleh admin. |
| **GAP-05** | **Cache Invalidation & Schema Drift (Service Worker)**<br>Backend VPS melakukan update skema JSON, tetapi PWA kasir masih menggunakan aset cache usang. | Request sync gagal total (HTTP 422 / JS TypeError). | 1. **Payload Schema Versioning**: Setiap paket outbox menyertakan `schema_version: 2`.<br>2. **Auto Service Worker Update**: Polling `version.json` setiap 15 menit; jika versi berbeda, lakukan `skipWaiting()` dan background refresh IndexedDB. |
| **GAP-06** | **Thundering Herd & Resource Exhaustion VPS 1GB RAM**<br>Banyak cabang online serentak setelah mati lampu massal; puluhan request sync membanjiri PHP-FPM. | PHP-FPM worker habis, OOM killer aktif, server VPS crash. | 1. **Jittered Backoff**: Delay acak 1.000–5.000 ms sebelum sync batch.<br>2. **Chunk Limit**: Maksimal 10 transaksi per HTTP request.<br>3. **Low-Memory DB Transaction**: Gunakan direct queries tanpa eager loading relasi berat. |

---

## 3. ARSITEKTUR SISTEM & KESERAGAMAN MODUL

Agar tidak merusak struktur monolitik modular Laravel saat ini, seluruh integrasi wajib mematuhi konvensi modul repo:

### 3.1. Penataan Modul & Alias
- **`App\Modules\Pos`**:
  - `PosOfflineSyncService`: Menangani payload penjualan POS offline.
  - `ReturnOfflineSyncService`: Menangani payload retur barang offline.
  - Endpoint API: `/api/pos/*`.
- **`App\Modules\Servis`**:
  - `ServisOfflineSyncService`: Menangani payload pengambilan unit (`selesai` → `diambil`) dan draft pendaftaran servis darurat.
  - Endpoint API: `/api/servis/*`.
- **`App\Modules\Wms`**:
  - `StokDeductionService`: Pemotongan stok idempoten, mengizinkan stok minus sementara dengan flag `PENYESUAIAN_OFFLINE_MINUS`.
  - `NomorSeriService`: Verifikasi SN/IMEI barang offline.
- **`App\Modules\Akunting`**:
  - `JurnalService`: Deterministic double-entry ledger posting (Kas vs Penjualan, HPP vs Persediaan).
- **`App\Modules\Rbac`**:
  - Scoping mutlak `cabang_id` via middleware `scope.cabang`.

### 3.2. Tidak Mengubah Arsitektur Inti (Zero Breaking Changes)
- Basis data MySQL utama tetap menggunakan primary key default (`id`).
- Identifikasi offline menggunakan kolom terindeks unik: `offline_uuid` (UUID v7).
- Tidak ada database engine tambahan (no CouchDB, no MySQL Galera, no SymmetricDS).
- Sisi klien murni PWA + IndexedDB Native Promise Wrapper (ringan, tanpa dependensi vendor berat).

---

## 4. SPESIFIKASI PENYIMPANAN KLIEN (INDEXEDDB SCHEMA v2)

Nama Database: `UtePartsOfflineDB`  
Versi: `2`

### Store 1: `master_produk` (Katalog & Cek Produk)
- **KeyPath**: `id`
- **Indexes**: `idx_barcode`, `idx_kode`, `idx_nama`, `idx_kategori`.
- **Fields**: `id`, `kode`, `barcode`, `nama`, `kategori`, `harga_retail`, `harga_bengkel`, `harga_grosir`, `stok_terakhir`, `lokasi_rak`, `butuh_sn`.

### Store 2: `pos_outbox` (Antrean Penjualan)
- **KeyPath**: `offline_uuid`
- **Indexes**: `idx_status` (`pending`, `syncing`, `synced`, `conflict`), `idx_created_at`, `idx_shift`.
- **Fields**: Data transaksi tunai kasir, array item, uang diterima, kembalian.

### Store 3: `retur_outbox` (Antrean Retur Offline)
- **KeyPath**: `retur_uuid`
- **Indexes**: `idx_status`, `idx_created_at`.
- **Payload Schema**:
  ```json
  {
    "retur_uuid": "0192e21b-8711-71a2-94b1-cf840d892b11",
    "cabang_id": 1,
    "user_id": 4,
    "no_transaksi_asal": "TRX-01-20261105-0012",
    "tipe_retur": "tukar_unit",
    "alasan": "Barang cacat pabrik / layar blank",
    "items": [
      {
        "produk_id": 105,
        "qty": 1,
        "kondisi": "rusak",
        "produk_pengganti_id": 105,
        "qty_pengganti": 1
      }
    ],
    "client_created_at": "2026-11-10T14:35:00+07:00",
    "status": "pending"
  }
  ```

### Store 4: `servis_active_cache` (Snapshot HP Siap Ambil)
- **KeyPath**: `tiket_id`
- **Indexes**: `idx_no_tiket`, `idx_no_wa`, `idx_imei`.
- **Fields**: `tiket_id`, `no_tiket`, `nama_pelanggan`, `no_wa`, `tipe_gadget`, `imei`, `total_biaya`, `uang_muka`, `sisa_tagihan`, `status` (`selesai`).

### Store 5: `servis_pickup_outbox` (Antrean Pengambilan HP)
- **KeyPath**: `pickup_uuid`
- **Indexes**: `idx_status`, `idx_tiket_id`.
- **Payload Schema**:
  ```json
  {
    "pickup_uuid": "0192e21b-8711-71a2-94b1-cf840d892c22",
    "cabang_id": 1,
    "user_id": 4,
    "tiket_id": 452,
    "no_tiket": "SRV-202611-0089",
    "nominal_pelunasan": 150000,
    "metode_bayar": "cash",
    "nama_pengambil": "Hendra",
    "client_created_at": "2026-11-10T15:10:00+07:00",
    "status": "pending"
  }
  ```

---

## 5. KONTRAK API & PROTOKOL SINKRONISASI SERVER

Semua endpoint dilindungi:
- Autentikasi: Laravel Sanctum (`auth:sanctum`).
- Scoping: `scope.cabang` (`X-Cabang-Id`).
- Idempotensi: `offline_uuid` / `pickup_uuid` / `retur_uuid`.

### 5.1. Penjualan POS: `POST /api/pos/sync-offline`
- Menggunakan `PosOfflineSyncService::syncBatch()`.
- Validasi anti-tamper harga via `PricingService`.
- Potong stok riil via `StokDeductionService`.
- Posting jurnal double-entry otomatis via `JurnalService`.

### 5.2. Pengambilan Unit Servis: `POST /api/servis/sync-pickup`
- Controller: `ServisController@syncPickupOffline`.
- Service: `ServisOfflineSyncService`.
- Alur Server:
  1. Kunci baris tiket: `TiketServis::where('id', $tiketId)->lockForUpdate()->first()`.
  2. Cek idempotensi `pickup_uuid`.
  3. Validasi status harus `selesai` atau `qc`.
  4. Eksekusi transisi state: `ServisStateMachine::transition($tiket, 'diambil')`.
  5. Catat kas pelunasan & posting jurnal pendapatan jasa/sparepart.
  6. Return response sukses.

### 5.3. Retur Offline: `POST /api/pos/sync-retur`
- Controller: `ReturnPenjualanController@syncOffline`.
- Service: `ReturnPenjualanService`.
- Alur Server:
  1. Validasi `no_transaksi_asal`.
  2. Catat `return_penjualan` bertipe offline tukar barang.
  3. Masukkan barang retur ke gudang karantina/rusak (`gudang_retur_id`).
  4. Potong stok barang pengganti dari gudang utama cabang.
  5. Buat jurnal penyesuaian persediaan.

---

## 6. KEPATUHAN STANDAR ISO & ERP ENTERPRISE

### 6.1. ISO 9001:2015 (Quality Management & Traceability)
- **Lacak Jejak Penuh**: Setiap transaksi offline memiliki ID unik UUID v7, identitas kasir (`user_id`), identitas cabang (`cabang_id`), nomor shift kasir, dan watermark `[OFFLINE]` pada struk fisik.
- **Log Mutasi Stok Terperinci**: Mutasi stok offline tercatat di `StockMutationLog` dengan keterangan tipe mutasi yang jelas (`POS_OFFLINE_SYNC`, `RETUR_OFFLINE_SWAP`).

### 6.2. ISO 27001:2022 (Information Security & Non-Repudiation)
- **Anti-Tampering DevTools**: Server memegang otoritas harga (`Server Pricing Authority`). Jika kasir sengaja memanipulasi nominal di browser, selisih dibukukan ke akun selisih dan diberi peringatan fraud.
- **Zero Sensitive Data In Client**: Harga pokok (HPP/COGS) dan margin keuntungan TIDAK PERNAH dikirim ke IndexedDB klien. Hanya harga jual tier yang dapat dilihat kasir.
- **Data Cleanup On Logout**: Pembersihan otomatis memori browser saat kasir melakukan logout resmi.

### 6.3. ISO 22301:2019 (Business Continuity Management)
- **RTO (Recovery Time Objective)**: 0 detik untuk kasir. Kasir otomatis berpindah ke mode decoupled saat jaringan putus tanpa perlu restart aplikasi.
- **RPO (Recovery Point Objective)**: 0 data hilang. Transaksi tersimpan persisten di IndexedDB dan disinkronkan otomatis berurutan saat jaringan pulih.

---

## 7. RENCANA KERJA & TAHAPAN IMPLEMENTASI (NON-DESTRUCTIVE)

```
[Fase 1: Backend Foundation]
  ├── Registrasi Endpoint Servis & Retur Offline di routes/api.php
  ├── Implementasi ServisOfflineSyncService (validasi status 'selesai' -> 'diambil')
  ├── Implementasi ReturnOfflineSyncService (tukar unit, no-cash refund)
  └── Unit & Feature Testing (SQLite :memory:)

[Fase 2: Frontend Client Store v2]
  ├── Update offline-db.js: Store servis_active_cache, servis_pickup_outbox, retur_outbox
  ├── Update pos-offline-engine.js: Integrasi fungsi ambil servis & retur tukar unit
  └── PWA Service Worker: Cache shell UI & static assets

[Fase 3: E2E Verification & Hardening]
  ├── Simulasi Mode Pesawat (Kasir POS, Ambil HP, Retur)
  ├── Simulasi Sync Batch Pasca Online (Verifikasi Jurnal & Stok)
  └── Audit Penggunaan Memori VPS (< 32MB peak)
```

---

Dokumen ini siap dieksekusi bertahap setelah konfirmasi. Seluruh arsitektur menjaga integritas kode yang ada tanpa memodifikasi skema relasional utama.