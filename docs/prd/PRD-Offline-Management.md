# PRD: Manajemen Aplikasi Offline & Sinkronisasi Resilien (Offline-First POS & Operasional Cabang)

> **Dokumen ID**: PRD-OFFLINE-01  
> **Status**: APPROVED FOR IMPLEMENTATION PLANNING  
> **Tanggal Pembuatan**: 2026-10-31  
> **Target Eksekusi**: Fase 11 (Pasca Validasi Core ERP & PWA Dual-Scope)  
> **Pemilik Dokumen**: Tim Arsitektur & Rekayasa Perangkat Lunak Ute Parts  
> **Target Pengguna**: Kasir POS, Supervisor Cabang, Teknisi Servis, Staf Gudang  
> **Konteks Sistem**: Laravel 13, TALL Stack (Tailwind 4, Alpine.js, Livewire), MySQL 8, VPS Murni Linux (Direct Host, Non-CyberPanel) 1GB RAM, Multi-Cabang Scoped (`cabang_id`), SQLite `:memory:` untuk Testing.

---

## 1. RINGKASAN EKSEKUTIF & MASALAH BISNIS

### 1.1. Latar Belakang
Ute Parts mengoperasikan jaringan toko fisik multi-cabang untuk penjualan sparepart HP, servis gadget, dan distribusi grosir/bengkel. Toko fisik sering mengalami kendala konektivitas internet (ISP down, jaringan 4G/5G drop di area ruko, pemadaman listrik darurat).

### 1.2. Titik Kritis Kegagalan Saat Ini (Current State Pitfalls)
1. **POS Kasir Berhenti Total**: Berbasis Livewire component (`/app/pos`) yang mewajibkan HTTP roundtrip setiap kali menambah produk atau bayar. Saat koneksi putus, UI freeze dan kasir tidak bisa memproses antrean.
2. **Penerimaan Servis Manual**: Unit servis pelanggan dicatat di nota kertas, berisiko kehilangan informasi kondisi fisik, pola kunci layar, dan nomor kontak.
3. **Pencatatan Ganda & Selisih Kas**: Penjualan manual di kertas sering lupa diinput kembali ke sistem saat internet pulih, menyebabkan selisih fisik uang kas dan stok gudang.
4. **Kebijakan ADR 0007 Sebelumnya**: ADR 0007 memilih "Basic Install Only" untuk menekan kompleksitas awal. Namun, operasional kasir membutuhkan ketahanan offline minimum agar roda bisnis toko tidak terhenti.

### 1.3. Tujuan PRD (Objective)
Membangun arsitektur **Resilient Hybrid Offline-First** untuk POS Kasir dan Draft Penerimaan Servis yang:
- Mengizinkan kasir memproses transaksi tunai dan cetak struk Bluetooth tanpa internet.
- Menyimpan transaksi di IndexedDB lokal dengan pengurutan waktu terjamin (UUID v7).
- Melakukan sinkronisasi otomatis, aman, idempoten, dan hemat memori ke server saat koneksi pulih.
- Menyediakan mitigasi risiko operasional penuh (oversell, selisih harga, fraud lokal, lonjakan server 1GB RAM).

---

## 2. PRINSIP ARSITEKTUR & BATASAN SISTEM (SYSTEM CONSTRAINTS)

1. **Integritas Double-Entry Akunting Sakral**:
   - Jurnal akunting (`jurnal_header` dan `jurnal_detail`) **TIDAK PERNAH dibuat di klien**. Jurnal hanya di-generate secara deterministik oleh `JurnalService` di server saat data transaksi offline berhasil divalidasi dan dicommit.
2. **Scoping Multi-Cabang Mutlak (`cabang_id`)**:
   - Master data lokal yang ter-cache di perangkat kasir **HANYA berisi data cabang aktif kasir**. Klien tidak boleh menyimpan atau mengakses data cabang lain.
3. **Server 1GB RAM & Worker PHP-FPM / Standalone Friendly**:
   - Sinkronisasi dilakukan dalam batch kecil (maksimal 10–20 transaksi per payload).
   - Menggunakan mekanisme jittered exponential backoff untuk mencegah *thundering herd problem* ketika beberapa cabang serentak online bersamaan.
4. **Pembayaran Tunai (Cash Only) & No E-Wallet Settlement**:
   - Transaksi offline **hanya mengizinkan metode pembayaran TUNAI (CASH)**.
   - Metode online gateway (Duitku QRIS, Virtual Account, Kartu Kredit) dinonaktifkan secara otomatis di UI saat status terdeteksi `offline`.
5. **Fakta Lapangan Menang (Physical Transaction Precedence)**:
   - Jika kasir offline telah menerima uang tunai dan menyerahkan barang ke pelanggan, server **TIDAK BOLEH menolak/membatalkan** transaksi tersebut saat sync. Jika stok di server minus, sistem mencatat transaksi dan melakukan penyesuaian inventori otomatis dengan audit trail lengkap.

---

## 3. MATRIKS RUANG LINGKUP (SCOPE MATRIX)

### 3.1. Dalam Cakupan (In-Scope)
- **POS Kasir Offline**:
  - Cache katalog produk & harga tier lokal per cabang (IndexedDB).
  - Keranjang belanja lokal mandiri via Alpine.js store (lepas dari ketergantungan Livewire saat offline).
  - Penyelesaian transaksi pembayaran Tunai dengan kalkulasi kembalian.
  - Cetak struk belanja via Web Bluetooth ESC/POS (langsung ke printer thermal 58mm/80mm tanpa server).
  - Outbox transaksi offline terenkripsi/terproteksi di IndexedDB.
- **Auto-Sync & Outbox Management**:
  - Deteksi status jaringan (`navigator.onLine`, event `online`/`offline`, dan heartbeat probe ringan).
  - Sinkronisasi batch otomatis saat online kembali.
  - Indikator UI: Status Jaringan, Badge Pending Transaksi, Modal Rekonsiliasi.
- **Draft Servis Offline**:
  - Form darurat penerimaan unit servis pelanggan & cetak nomor tanda terima sementara.
- **Mitigasi & Audit**:
  - Deteksi dan logging otomatis stok minus pasca-sync (`StockMutationLog`).
  - Rekalkulasi harga di server (anti-tamper DevTools).
  - Idempotency key berbasis UUID v7 untuk pencegahan mutlak duplikasi transaksi.

### 3.2. Di Luar Cakupan (Out-of-Scope)
- Transaksi offline untuk modul Marketplace konsumen (`/`).
- Pembayaran via EDC Online, QRIS, atau transfer bank saat offline.
- Perubahan master data (tambah produk baru, ubah harga master, edit hak akses) saat offline.
- Sinkronisasi peer-to-peer antar perangkat kasir dalam satu cabang (semua sync hanya Client-to-Server).

---

## 4. SPESIFIKASI TEKNIS KLIEN (FRONTEND ARCHITECTURE)

### 4.1. Skema Penyimpanan Lokal IndexedDB (`UtePartsOfflineDB`)
Versi Database: `1`

#### Store 1: `master_produk`
Menyimpan snapshot katalog aktif cabang kasir.
- **KeyPath**: `id` (integer / string)
- **Indexes**:
  - `idx_barcode`: `barcode` (unique: false)
  - `idx_kode`: `kode` (unique: true)
  - `idx_nama`: `nama` (unique: false)
  - `idx_kategori`: `kategori` (unique: false)
- **Payload Schema**:
  ```json
  {
    "id": 105,
    "kode": "LCD-IP11-ORI",
    "barcode": "8991234567890",
    "nama": "LCD iPhone 11 Original Equipment",
    "kategori": "LCD & Touchscreen",
    "harga_retail": 450000,
    "harga_bengkel": 410000,
    "harga_grosir": 385000,
    "stok_terakhir": 14,
    "gudang_id": 1,
    "cabang_id": 1,
    "updated_at": "2026-10-31T08:00:00Z"
  }
  ```

#### Store 2: `pos_outbox`
Antrean transaksi yang terjadi saat offline dan belum diakui oleh server.
- **KeyPath**: `offline_uuid` (string, UUID v7)
- **Indexes**:
  - `idx_status`: `status` (pending, syncing, synced, conflict)
  - `idx_created_at`: `client_created_at`
  - `idx_shift`: `shift_id`
- **Payload Schema**:
  ```json
  {
    "offline_uuid": "0192e21b-8711-71a2-94b1-cf840d892a01",
    "cabang_id": 1,
    "user_id": 4,
    "kasir_nama": "Budi Santoso",
    "shift_id": 42,
    "client_created_at": "2026-10-31T14:15:22+07:00",
    "pelanggan_id": null,
    "tier_pelanggan": "retail",
    "metode_bayar": "cash",
    "total_nominal": 450000,
    "uang_diterima": 500000,
    "kembalian": 50000,
    "catatan": "Transaksi offline kasir 1",
    "items": [
      {
        "produk_id": 105,
        "gudang_id": 1,
        "qty": 1,
        "harga_satuan": 450000,
        "subtotal": 450000
      }
    ],
    "status": "pending",
    "retry_count": 0,
    "error_message": null,
    "server_transaksi_id": null,
    "server_no_faktur": null
  }
  ```

#### Store 3: `servis_draft_outbox`
Penerimaan unit servis darurat saat offline.
- **KeyPath**: `draft_uuid` (string, UUID v7)
- **Indexes**:
  - `idx_status`: `status` (pending, synced)
- **Payload Schema**:
  ```json
  {
    "draft_uuid": "0192e21b-8711-71a2-94b1-cf840d892a02",
    "cabang_id": 1,
    "user_id": 4,
    "client_created_at": "2026-10-31T14:20:00+07:00",
    "nama_pelanggan": "Agus Pratama",
    "no_wa": "081234567890",
    "tipe_gadget": "Samsung A52",
    "imei": "356789123456789",
    "keluhan": "Ganti baterai kembung",
    "estimasi_biaya": 250000,
    "status": "pending"
  }
  ```

### 4.2. Arsitektur State Kasir Decoupled (Alpine.js Local Store)
Saat online, POS beroperasi normal dengan Livewire. Begitu koneksi terputus:
1. `window.addEventListener('offline')` memicu mode **Fallback Decoupled**:
   - Komponen keranjang beralih kendali ke `Alpine.store('posOffline')`.
   - Pencarian produk mengeksekusi query lokal ke IndexedDB `master_produk`.
   - Tombol Bayar memanggil fungsi lokal `posOffline.checkout()`.
   - Transaksi disimpan ke IndexedDB `pos_outbox` dengan status `pending`.
   - Stok tampilan lokal dikurangi secara optimistik agar kasir tidak menjual barang yang sama melebihi batas pandangan mata.
   - Panggilan langsung ke `window.UteThermalPrint.printRawReceipt(receiptData)` untuk mencetak struk kasir via Bluetooth ESC/POS dengan watermark `[OFFLINE - BUKTI SEMENTARA]`.

---

## 5. SPESIFIKASI PROTOKOL SINKRONISASI & API BACKEND

### 5.1. Alur Deteksi Konektivitas & Jittered Trigger
Klien tidak boleh langsung memborbardir server begitu event `online` tertangkap.
1. Klien menerima event `window.addEventListener('online')`.
2. Klien melakukan uji koneksi ringan (heartbeat probe):
   `HEAD /api/ping` (Timeout 3 detik).
3. Jika probe sukses, kasir menghitung delay acak (jitter):
   `delay = Math.floor(Math.random() * 4000) + 1000;` (1 hingga 5 detik).
4. Klien memulai proses sinkronisasi batch.

### 5.2. Kontrak Endpoint Sinkronisasi POS
- **URL**: `POST /api/pos/sync-offline`
- **Autentikasi**: Sanctum Token (`Authorization: Bearer <token>`)
- **Proteksi Scoping**: Middleware `scope.cabang` (memastikan `X-Cabang-Id` valid).
- **Rate Limiting**: `60 requests / minute per branch`.

#### Payload Request (JSON)
```json
{
  "cabang_id": 1,
  "client_batch_id": "0192e21b-8711-71a2-94b1-batch-001",
  "transactions": [
    {
      "offline_uuid": "0192e21b-8711-71a2-94b1-cf840d892a01",
      "shift_id": 42,
      "client_created_at": "2026-10-31T14:15:22+07:00",
      "pelanggan_id": null,
      "tier_pelanggan": "retail",
      "metode_bayar": "cash",
      "total_nominal": 450000,
      "uang_diterima": 500000,
      "kembalian": 50000,
      "catatan": "Transaksi offline kasir 1",
      "items": [
        {
          "produk_id": 105,
          "gudang_id": 1,
          "qty": 1,
          "harga_satuan": 450000,
          "subtotal": 450000
        }
      ]
    }
  ]
}
```

#### Payload Response (JSON)
```json
{
  "success": true,
  "message": "Sinkronisasi 1 transaksi berhasil diproses.",
  "data": {
    "total_received": 1,
    "total_synced": 1,
    "total_conflicts": 0,
    "results": [
      {
        "offline_uuid": "0192e21b-8711-71a2-94b1-cf840d892a01",
        "status": "synced",
        "transaksi_id": 9812,
        "no_faktur": "TRX-01-20261031-0042",
        "warning": null,
        "synced_at": "2026-10-31T14:45:00+07:00"
      }
    ]
  }
}
```

---

## 6. ATURAN BISNIS REKONSILIASI & PENANGANAN KONFLIK (CONFLICT RESOLUTION)

### 6.1. Logika Pemrosesan Server per Transaksi
Setiap transaksi dalam batch diproses dalam transaksi basis data terisolasi (`DB::transaction`):

1. **Pemeriksaan Idempotensi**:
   ```sql
   SELECT id, no_faktur FROM pos_transaksi WHERE offline_uuid = :uuid FOR UPDATE;
   ```
   Jika ditemukan, return respons sukses dengan data faktur yang sudah ada (tidak ada eksekusi ulang).

2. **Verifikasi Anti-Tamper Harga**:
   - Server mengambil harga master aktual untuk tier pelanggan terkait (`Produk::find($item['produk_id'])`).
   - Server menghitung ulang `expected_total = sum(qty * master_harga_tier)`.
   - Jika `abs(expected_total - client_total) > 0`:
     - Transaksi **tetap dicatat** sesuai uang fisik yang diterima kasir.
     - Selisih nominal dicatat ke akun COA: `5-5099 (Beban Selisih Transaksi Offline / Kasir)`.
     - Catatan audit log dibuat dengan tanda peringatan manipulasi harga.

3. **Verifikasi & Pemotongan Stok (StokDeductionService)**:
   - Server mengunci baris stok: `StokItem::where(...)->lockForUpdate()->first()`.
   - Kasus A: Stok fisik mencukupi (`stok >= qty`):
     - Stok dipotong normal.
     - `StockMutationLog` dicatat dengan referensi `POS_OFFLINE_SYNC`.
   - Kasus B: Stok fisik tidak mencukupi / habis (`stok < qty`):
     - **Transaksi kasir tetap disimpan** (barang sudah diserahkan ke pelanggan).
     - Kolom `stok` diizinkan bernilai negatif sementara waktu.
     - `StockMutationLog` dicatat dengan kategori `PENYESUAIAN_OFFLINE_MINUS`.
     - Sistem membuat tiket notifikasi otomatis ke Supervisor Cabang & Staf Gudang untuk segera melakukan opname rak atau transfer darurat dari cabang utama.

4. **Posting Jurnal Akunting Double-Entry**:
   - `JurnalService::post()` dipanggil dengan 2 baris standar POS Tunai:
     - **Debit**: Kas Kasir Cabang (`1-1001-XX`) sebesar `total_nominal`
     - **Kredit**: Pendapatan Penjualan POS (`4-1001-XX`) sebesar `total_nominal`
   - Plus jurnal HPP dan Persediaan sesuai nilai COGS sistem.

---

## 7. MATRIKS RISIKO & MITIGASI MENYELURUH (DETAILED RISK MITIGATION)

| ID | Risiko Spesifik | Skenario Kejadian | Dampak Finansial / Teknis | Tindakan Mitigasi Terperinci |
|---|---|---|---|---|
| **R-01** | **Oversell Stok Lintas Saluran** | Barang laku di Shopee/Online saat kasir offline menjual barang yang sama di toko. | Stok gudang fisik minus, pesanan online berisiko dibatalkan dengan penalti. | 1. **Safety Buffer Offline**: Nilai `stok_terakhir` yang di-cache di IndexedDB otomatis dipotong buffer 1 unit jika stok > 2.<br>2. **Auto-Prioritize Fisik**: Transaksi offline kasir langsung di-commit; pesanan online dialihkan ke fulfillment cabang terdekat melalui fitur routing order. |
| **R-02** | **Koneksi Terputus Saat Sync (Network Dropout Mid-Flight)** | Klien mengirim batch sync, server selesai commit, namun paket response HTTP gagal sampai ke klien. | Klien mengira transaksi belum tersinkron dan mengirim ulang (duplikasi). | 1. **Unique Index `offline_uuid`**: Kolom berindeks unik di tabel `pos_transaksi`.<br>2. **Idempotent Return**: Handler controller memeriksa keberadaan UUID sebelum insert; jika sudah ada, server mengembalikan status HTTP 200 dengan payload faktur yang sudah tersimpan tanpa mutasi ulang stok/jurnal. |
| **R-03** | **Manipulasi Local Storage / DevTools Console** | Staf kasir membuka browser DevTools dan mengubah harga di IndexedDB dari Rp 500.000 menjadi Rp 50.000. | Potensi penggelapan dana kas toko fisik. | 1. **Server Pricing Authority**: Server menghitung ulang nominal berdasarkan master database.<br>2. **Fraud Discrepancy Flag**: Jika nominal klien < nominal server, status transaksi otomatis diberi flag `SUSPICIOUS_PRICE_DISCREPANCY` dan akun kasir dibekukan dari otorisasi offline hingga diverifikasi supervisor. |
| **R-04** | **Manipulasi Jam Klien (Backdating / Time Drift)** | Baterai CMOS perangkat kasir habis atau jam sengaja dimundurkan untuk manipulasi shift. | Inkonsistensi urutan mutasi stok dan laporan penutupan shift kasir. | 1. **Immutable UUID v7**: Timestamp bawaan UUID v7 digunakan sebagai referensi internal urutan.<br>2. **Dual-Time Audit**: Tabel menyimpan `client_transacted_at` dan `server_synced_at`. Jurnal akunting diposting pada tanggal pembukuan aktif saat sinkronisasi tiba. |
| **R-05** | **Pergantian Perangkat Kasir & Kebocoran Data** | Tablet kasir dipinjam staf lain atau dibawa keluar toko dalam kondisi data ter-cache. | Data riwayat penjualan dan harga modal lokal terekspos. | 1. **Scoped Storage Clearing**: Saat tombol "Logout" ditekan resmi, seluruh store `master_produk` dan `pos_shift_state` dibersihkan dari browser.<br>2. **No COGS / Modal in Cache**: Data modal / HPP barang TIDAK PERNAH dikirim ke IndexedDB klien (hanya harga jual). |
| **R-06** | **Thundering Herd pada VPS 1GB RAM** | Pemadaman listrik massal usai; 10 cabang serentak online bersamaan dan memicu sync ratusan transaksi. | CPU spike 100%, OOM killer membunuh proses PHP worker, web server crash / 503/504. | 1. **Randomized Jitter**: Jeda acak 1.000–5.000ms sebelum request pertama.<br>2. **Small Chunking**: Klien mengirim maksimal 10 transaksi per request HTTP.<br>3. **Low-Memory Querying**: Sinkronisasi tidak memuat relasi berat, hanya menggunakan lock spesifik baris (`whereIn` + `lockForUpdate`). |
| **R-07** | **Kegagalan Cetak Struk Fisik di Lapangan** | Pelanggan membutuhkan bukti fisik tetapi printer Bluetooth mati atau kehabisan kertas. | Pelanggan komplain tidak menerima tanda bukti pembayaran. | 1. **Cetak Ulang Offline**: Fitur "Riwayat Outbox" di kasir menyediakan tombol "Cetak Ulang Struk Offline" langsung dari data IndexedDB.<br>2. **Queueing Notifikasi WA**: Klien menyimpan nomor WhatsApp pelanggan di payload outbox; server otomatis mengirim e-struk PDF via WhatsApp Gateway begitu online kembali. |

---

## 8. SPESIFIKASI UI/UX (DESAIN SISTEM "UTE PRISM")

### 8.1. Indikator Status Konektivitas Global
- **Lokasi**: Pojok kanan atas navbar backoffice (`/app/*`) berdampingan dengan profil dan badge cabang.
- **Komponen**: `StatusPill` & `Toast` Ute Prism:
  - **Online**: Pill hijau mint halus (`bg-emerald-500/10 text-emerald-400 border-emerald-500/20`), teks "Online".
  - **Offline**: Pill merah amber berkedip lembut (`bg-rose-500/20 text-rose-300 border-rose-500/30 animate-pulse`), teks "Mode Offline".
  - **Syncing**: Pill indigo animasi putar (`bg-indigo-500/20 text-indigo-300 border-indigo-500/30`), teks "Sinkronisasi (X)...".

### 8.2. Banner Peringatan Kasir POS
Jika status jaringan terputus, muncul banner non-intrusif di atas keranjang kasir:
> ⚠️ **Koneksi Terputus — Berjalan dalam Mode Kasir Mandiri**  
> Pembayaran dibatasi untuk Tunai (Cash). Seluruh transaksi akan tersimpan lokal dan disinkronisasi otomatis saat online kembali. Struk Bluetooth tetap berfungsi normal.

### 8.3. Drawer Antrean Offline (Offline Outbox Drawer)
Kasir dapat mengklik ikon awan/antrean di header POS untuk melihat daftar transaksi yang belum terkirim:
- Menampilkan: Waktu transaksi, Total Rupiah, Jumlah Item, dan Status (`Menunggu Koneksi` / `Gagal - Coba Lagi`).
- Tombol: `Sinkronkan Sekarang` (aktif jika probe internet berhasil).

---

## 9. PERUBAHAN SKEMA BASIS DATA (DATABASE MIGRATIONS)

### 9.1. Perubahan Tabel `pos_transaksi`
Menambahkan kolom pengenal transaksi offline dan status sinkronisasi:
```php
Schema::table('pos_transaksi', function (Blueprint $table) {
    $table->uuid('offline_uuid')->nullable()->unique()->after('id');
    $table->boolean('is_offline')->default(false)->after('status');
    $table->timestamp('client_created_at')->nullable()->after('is_offline');
    $table->timestamp('synced_at')->nullable()->after('client_created_at');
    $table->string('sync_status', 30)->default('online')->after('synced_at'); // online, synced, discrepancy_fixed
    $table->decimal('discrepancy_amount', 15, 2)->default(0)->after('sync_status');
});
```

### 9.2. Tabel Baru: `pos_offline_sync_logs`
Mencatat rekapitulasi audit setiap kali batch sync diterima dari perangkat:
```php
Schema::create('pos_offline_sync_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cabang_id')->constrained('cabang')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('batch_id', 100);
    $table->integer('total_payload');
    $table->integer('total_success');
    $table->integer('total_conflicts');
    $table->json('conflict_details')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->timestamps();

    $table->index(['cabang_id', 'created_at']);
});
```

---

## 10. RENCANA PENGUJIAN & VERIFIKASI (TESTING PLAN)

Pengujian wajib mematuhi panduan repo: **PHPUnit 12 pada SQLite `:memory:`** dan zero-leak cross-branch scoping.

### 10.1. Unit Testing
1. **`OfflineUuidValidationTest`**:
   - Memastikan UUID v7 terurai dengan benar dan timestamp valid.
2. **`OfflinePriceRecalculationTest`**:
   - Simulasi input nominal yang sengaja dimanipulasi lebih murah; memastikan server memvalidasi dan mencatat selisih ke COA yang ditentukan.

### 10.2. Feature Testing
1. **`PosOfflineSyncEndpointTest`**:
   - Kirim payload 5 transaksi offline valid → pastikan record `pos_transaksi` terbentuk dengan relasi items dan status `synced`.
   - Pastikan `JurnalService` terpanggil dan menghasilkan 1 pasang header/detail jurnal per transaksi.
2. **`PosOfflineIdempotencyTest`**:
   - Kirim payload yang sama 2 kali berturut-turut → pastikan server tidak membuat transaksi ganda, tidak menduplikasi jurnal, dan stok tidak terpotong dua kali.
3. **`PosOfflineOversellNegativeStockTest`**:
   - Kirim transaksi offline dengan kuantitas melebihi stok yang ada di database → pastikan transaksi tetap tersimpan, stok bernilai minus, dan tercatat di `StockMutationLog` dengan keterangan penyesuaian darurat.
4. **`PosOfflineBranchScopingTest`**:
   - Kirim transaksi dengan `offline_uuid` valid tetapi kasir mencoba menyuntikkan ID produk milik cabang lain → pastikan request ditolak (HTTP 422 / 403).

### 10.3. Pengujian Lapangan Manual (End-to-End Simulation)
1. Buka POS di browser/PWA pada tablet kasir.
2. Masukkan kabel LAN / matikan WiFi (Aktifkan Mode Pesawat).
3. Pastikan UI berganti ke indikator Offline dan tombol metode pembayaran online terkunci.
4. Scan 2 item produk, klik Bayar Tunai, masukkan nominal uang diterima.
5. Verifikasi cetak struk via Bluetooth thermal printer berhasil keluar dengan watermark `[OFFLINE]`.
6. Nyalakan kembali WiFi.
7. Amati drawer sinkronisasi; pastikan dalam 5 detik antrean berkurang menjadi 0 dan status di server berubah menjadi `synced`.

---

## 11. CHECKLIST IMPLEMENTASI (TASK BACKLOG)

Patuhi aturan implement-plan repo: centang `- [x]` **HANYA setelah diverifikasi lewat pengujian riil**.

- [x] **T-OFFLINE-01** — Migrasi skema database: tambahkan `offline_uuid`, `is_offline`, `synced_at`, `sync_status` pada `pos_transaksi` dan buat tabel `pos_offline_sync_logs`.
- [x] **T-OFFLINE-02** — Pembuatan database IndexedDB klien (`UtePartsOfflineDB`) menggunakan wrapper lightweight dan hook Service Worker cache master produk.
- [x] **T-OFFLINE-03** — Pembuatan endpoint `POST /api/pos/sync-offline` di `PosController` lengkap dengan validasi request dan scoping `cabang_id`.
- [x] **T-OFFLINE-04** — Pembuatan Service Rekonsiliasi Server `PosOfflineSyncService`: penanganan idempoten, rekalkulasi harga master, dan logging audit.
- [x] **T-OFFLINE-05** — Integrasi pemotongan stok darurat pada `StokDeductionService` untuk mendukung kuantitas minus sementara dan logging `StockMutationLog`.
- [x] **T-OFFLINE-06** — Integrasi posting jurnal otomatis via `JurnalService::post()` untuk setiap transaksi offline yang berhasil divalidasi.
- [x] **T-OFFLINE-07** — Pembuatan Alpine.js store `posOffline` pada halaman POS kasir untuk menangani keranjang lokal dan checkout tunai saat koneksi terputus.
- [x] **T-OFFLINE-08** — Penyesuaian `thermal-print.js` untuk mendukung pencetakan nota offline mandiri dengan watermark format khusus.
- [x] **T-OFFLINE-09** — Pembuatan komponen UI indikator konektivitas (`StatusPill`), banner peringatan offline, dan modal drawer antrean outbox.
- [x] **T-OFFLINE-10** — Implementasi background sync worker pada klien: heartbeat probe, exponential backoff, dan auto-dispatch saat koneksi online.
- [x] **T-OFFLINE-11** — Pengujian otomatis penuh: Suite Feature Test `PosOfflineSyncTest` (idempotensi, stok minus, scoping cabang, dan kalkulasi ulang harga).
- [x] **T-OFFLINE-12** — Verifikasi performa memori pada server VPS murni (memory footprint < 32MB saat memproses batch 20 transaksi).
