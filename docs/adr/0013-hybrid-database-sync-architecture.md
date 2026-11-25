# ADR 0013: Hybrid Database Sync Architecture (PC Toko Offline-First & Cloud VPS Failover)

**Status:** Accepted (Telah Diaudit & Dioptimalkan)  
**Tanggal:** 23 November 2026  
**Revisi:** 1.1 (Penyempurnaan Anti-Echo Loop, Batch Payload, & Cloud Master Data Routing)  
**Penulis:** Tim Arsitektur Bremspace ERP  
**Supersedes/Extends:** [ADR 0007: PWA Scope](file:///var/www/ute-parts-erp/docs/adr/0007-pwa-scope.md)  
**Dokumen Terkait:**  
- [hybrid-sync-runbook.md](file:///var/www/ute-parts-erp/docs/hybrid-sync-runbook.md)
- [PRD-Backend-UteParts.md](file:///var/www/ute-parts-erp/docs/prd/PRD-Backend-UteParts.md)
- [2026-11-23-hybrid-database-sync-plan.md](file:///var/www/ute-parts-erp/docs/plans/2026-11-23-hybrid-database-sync-plan.md)

---

## 1. Konteks Bisnis & Masalah (*Context & Problem Statement*)

Toko ritel fisik sparepart dan servis gadget (Ute Parts / Bremspace) menghadapi tantangan operasional harian:
1. **Fluktuasi Jaringan**: Koneksi internet toko fisik (ISP kabel/seluler) sering terputus atau mengalami lonjakan latensi (200–800ms).
2. **Ketergantungan Cloud Murni (SPOF)**: Jika kasir harus menunggu konfirmasi server cloud sebelum struk keluar, antrean kasir tersendat dan penjualan lumpuh saat internet padam.
3. **Ketergantungan PC Lokal Murni (Risiko Kehilangan Data)**: Jika hanya berbasis software desktop jadul di 1 PC, kerusakan fisik/kebakaran pada PC menyebabkan seluruh data pembukuan dan kasir musnah. Owner juga tidak bisa memantau omzet secara real-time dari luar kota.
4. **Kebutuhan Fleksibilitas Backoffice**: Owner dan tim akuntan/HR sering bekerja malam hari dari rumah (menambah produk, mengatur gaji, jurnaling manual) saat PC toko di ruko sudah dimatikan. Saat PC toko dinyalakan keesokan harinya, seluruh pembaruan master data tersebut harus otomatis tersinkronisasi.

Sistem membutuhkan kapabilitas mirip **SID Retail Pro** (aplikasi kasir yang bisa offline di PC lokal dan tersinkron ke cloud), namun dengan keunggulan ekosistem web modern (Laravel 13, TALL Stack) yang ringan, terisolasi per cabang, dan tanpa lisensi pihak ketiga.

---

## 2. Pilihan Keputusan Arsitektur (*Decision Drivers*)

1. **Zero-Lag Kasir (< 50ms)**: Proses simpan transaksi kasir tidak boleh terhambat panggilan HTTP jaringan (*non-blocking asynchronous I/O*).
2. **Offline-Resilient**: Transaksi kasir, servis, dan retur tukar barang tetap berfungsi 100% tanpa internet.
3. **Cloud-First Backoffice**: Manajemen produk, harga, supplier, payroll, dan akuntansi dapat dilakukan di Cloud VPS kapan saja tanpa mengharuskan PC toko menyala.
4. **Anti-Bentrok & Anti-Echo Loop**: Transaksi lokal dan cloud tidak boleh bertabrakan penomorannya, dan event yang disinkronkan tidak boleh memantul bolak-balik tanpa akhir (*infinite ping-pong sync*).
5. **Efisiensi Bandwidth & CPU**: Mengirim mutasi ringkas (delta payload terkompresi), bukan menduplikasi tabel mentah (raw database dump).

---

## 3. Keputusan yang Diambil (*Decision Outcome*)

Kami mengadopsi arsitektur **"Asymmetric Hybrid Sync with Transactional Outbox/Inbox, Event-Delta Aggregation, & Event Suppression Guard"**:

```mermaid
flowchart TB
    subgraph TOKO ["🏪 Node PC Toko (primary_store / Offline-Ready)"]
        direction TB
        UI_Lokal["🖥️ Layar Kasir POS & Servis (Localhost)"]
        APP_Lokal["⚡ Laravel Monolith (PHP 8.3+)"]
        DB_Lokal[("🗄️ MySQL Lokal (Mirror Terkurasi)")]
        Outbox_Lokal[("📦 hybrid_sync_outbox (Lokal)")]
        Inbox_Lokal[("📬 hybrid_sync_inbox (Lokal)")]
        Worker_Lokal["🔄 Background Queue Worker (sync)"]
        Cron_Pull["⏱️ Cron Pull (Tiap 30-60 dtk)"]
    end

    subgraph CLOUD ["☁️ Node VPS Cloud (cloud_storefront / High Availability)"]
        direction TB
        UI_Cloud["🌐 Backoffice ERP & Web Storefront (Public Domain)"]
        APP_Cloud["⚡ Laravel Monolith (Cloud VPS)"]
        DB_Cloud[("🗄️ MySQL Cloud (Master Sentral)")]
        Outbox_Cloud[("📦 hybrid_sync_outbox (Cloud)")]
        Inbox_Cloud[("📬 hybrid_sync_inbox (Cloud)")]
        API_Push["🚪 POST /api/internal/sync/push-batch"]
        API_Pull["🚪 GET /api/internal/sync/pull"]
    end

    %% Aliran Transaksi Kasir Toko
    UI_Lokal -->|1. Transaksi < 20ms (ACID)| APP_Lokal
    APP_Lokal -->|2. Tulis Transaksi & Stok| DB_Lokal
    APP_Lokal -->|3. Tulis Event Sync| Outbox_Lokal
    Outbox_Lokal -->|4. Batching Queue (Async)| Worker_Lokal
    Worker_Lokal -.->|5. HTTPS + HMAC Batch Push| API_Push
    API_Push --> Inbox_Cloud
    Inbox_Cloud -->|6. Event Suppression Save| DB_Cloud

    %% Aliran Master Data / Failover VPS
    UI_Cloud -->|7. Tambah Produk / Gaji / Order Online| DB_Cloud
    DB_Cloud --> Outbox_Cloud
    Cron_Pull -.->|8. Pull Event Baru| API_Pull
    API_Pull -.-> Outbox_Cloud
    Cron_Pull --> Inbox_Lokal
    Inbox_Lokal -->|9. Event Suppression Save| DB_Lokal
```

---

## 4. Klasifikasi Data & Batasan Wilayah Replikasi

Untuk menjaga performa dan mencegah bloat database di PC toko, data diklasifikasikan secara tegas:

| Tipe Data | Arah Replikasi | Keterangan & Aturan |
|---|---|---|
| **Master Produk & Harga Tier** | ☁️ Cloud $\to$ 🏪 Toko (Utama)<br>🏪 Toko $\to$ ☁️ Cloud (Opsional) | Admin input di cloud saat malam $\to$ pagi hari tersedot ke toko. Aturan: *Last-Write-Wins* via timestamp ISO-8601. |
| **Transaksi POS & Nota Kasir** | 🏪 Toko $\to$ ☁️ Cloud | Kasir jualan di toko $\to$ outbox kirim faktur & item lengkap ke cloud. |
| **Tiket Servis HP & Status** | 🏪 Toko $\leftrightarrow$ ☁️ Cloud | State machine ketat dengan penanda `offline_pickup_uuid`. |
| **Mutasi Stok (StokLog)** | 🏪 Toko $\leftrightarrow$ ☁️ Cloud | **Delta-Based**: Penjualan memotong qty $(-X)$, penerimaan menambah qty $(+Y)$. Bukan timpa nilai mutlak. |
| **Transaksi POS Darurat (Failover)** | ☁️ Cloud $\to$ 🏪 Toko | Jika PC toko mati, kasir login ke web cloud (prefix nota `VPS-`). Saat PC toko nyala kembali, transaksi darurat terserap ke database lokal. |
| **Pesanan Online Marketplace** | ☁️ Cloud $\to$ 🏪 Toko | Pesanan Shopee/Web lunas ditarik toko fisik untuk disiapkan barangnya. |
| **Jurnaling Umum & Laporan Keuangan** | **Cloud-Only** (Kecuali jurnal POS lokal) | Buku besar, depresiasi, dan neraca diproses sentral di cloud; PC toko tidak dibebani komputasi akuntansi korporat. |
| **Payroll & Penggajian Karyawan** | **Cloud-Only** | Privasi terjamin; slip gaji dan persetujuan payroll hanya diakses owner/HR di cloud. |

---

## 5. Mitigasi Celah Kritis Arsitektur

### 5.1 Pencegahan Loop Echo (*Anti Ping-Pong Sync*)
**Masalah**: Ketika Toko menarik produk baru dari Cloud dan menyimpannya ke MySQL lokal, model observer `ProdukSyncObserver` di lokal bisa terpicu dan memasukkan kembali event tersebut ke outbox lokal untuk dikirim ulang ke Cloud, menciptakan siklus tanpa akhir.  
**Solusi**:
1. Gunakan method `saveQuietly()` pada seluruh proses ingest di `HybridSyncProcessorService`.
2. Pasang konteks eksekusi global `SyncExecutionGuard::runWithoutSync(fn)` yang menonaktifkan dispatch observer saat pemrosesan inbox berlangsung.

### 5.2 Optimasi Pengiriman: *Batch Push Payload*
**Masalah**: Jika satu transaksi memiliki 10 item barang dan memicu 10 HTTP request terpisah, koneksi internet toko yang pas-pasan akan cepat mengalami *congestion* atau timeout.  
**Solusi**:
* Ganti pengiriman 1-per-1 menjadi endpoint **`POST /api/internal/sync/push-batch`**.
* Worker mengambil hingga 50 item outbox berstatus `pending` sekaligus dan mengirimkannya dalam 1 payload terkompresi gzipped JSON (< 25 KB).
* Mengurangi 90% overhead koneksi SSL/TLS handshake.

### 5.3 Normalisasi Pull Cloud Master Data
**Masalah**: Logika lama pada `SyncController::pull()` hanya mengembalikan `order.online_paid` jika node adalah cloud, sehingga produk baru yang diinput owner di cloud terabaikan.  
**Solusi**:
* Endpoint `pull()` cloud wajib menyajikan seluruh event keluar yang relevan untuk cabang tersebut: `order.online_paid`, `produk.upsert`, `sku_variant.upsert`, `pelanggan.upsert`, `kategori_produk.upsert`, `brand.upsert`, dan `transaksi.pos_cloud`.

---

## 6. Jaminan Performa & Toleransi Kesalahan (SLA)

| Parameter | Target Standar | Mekanisme Penjamin |
|---|---|---|
| **Respon Kasir Checkout** | **< 30 ms** | Transaksi langsung di-commit ke MySQL lokal; sync outbox menggunakan event queue terpisah. |
| **Pencetakan Nota Thermal** | **< 80 ms** | Driver printer lokal (ESC/POS socket / Web Bluetooth) langsung dari browser lokal. |
| **Waktu Tanggap Failover** | **< 15 detik** | Kasir langsung membuka URL web cloud di browser perangkat alternatif. |
| **Ukuran Memori PC Toko** | **< 400 MB** | Tanpa daemon berat; hanya runtime PHP, MySQL lokal ter-tuning, dan cron worker ringan. |
| **Ketahanan Putus Internet** | **Tak Terbatas** | Outbox lokal menampung ribuan transaksi offline hingga internet kembali aktif. |

---

## 7. Status & Langkah Lanjutan

Dokumen ini melandasi penyusunan rencana aksi teknis pada [2026-11-23-hybrid-database-sync-plan.md](file:///var/www/ute-parts-erp/docs/plans/2026-11-23-hybrid-database-sync-plan.md). Implementasi akan diverifikasi melalui automated test suite di SQLite/MySQL serta pengujian simulasi putus-sambung internet.
