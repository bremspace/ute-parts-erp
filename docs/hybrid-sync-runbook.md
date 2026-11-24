# Panduan Arsitektur & Runbook Operasional: Asymmetric Hybrid Sync
## Bremspace ERP (Storefront Cloud VPS & Toko Lokal)

Dokumen ini adalah spesifikasi arsitektur teknis dan buku panduan operasional (*operational runbook*) untuk sistem **Asymmetric Hybrid Sync** pada **Bremspace ERP**.

Sistem ini dirancang khusus untuk memecahkan tantangan arsitektur retail: toko fisik (*primary store*) yang berada di balik NAT / koneksi internet lokal (tanpa IP publik statis) harus tetap terhubung secara *real-time* dan andal dengan toko online / marketplace (*cloud storefront*) yang berjalan di VPS publik.

---

## 1. Ringkasan Arsitektur & Pola Sinkronisasi Asimetris

Dalam lingkungan ritel modern Bremspace ERP, terdapat dua peran node (*node roles*):

```mermaid
flowchart LR
    subgraph PrimaryStore ["Toko Fisik / Primary Store (Lokal LAN / NAT)"]
        direction TB
        POS["POS Kasir & Servis"]
        WMS["Gudang & Stok Lokal"]
        DB_Local[("MySQL / SQLite Lokal")]
        Outbox_Local[("hybrid_sync_outbox")]
        Inbox_Local[("hybrid_sync_inbox")]
        PullWorker["Cron: sync:pull (Setiap 1 Menit)"]
        Queue_Local["Queue Worker: sync"]
    end

    subgraph CloudVPS ["Marketplace / Cloud Storefront (VPS Publik)"]
        direction TB
        Ecom["Online Storefront & Duitku PG"]
        DB_Cloud[("MySQL Cloud")]
        Outbox_Cloud[("hybrid_sync_outbox")]
        Inbox_Cloud[("hybrid_sync_inbox")]
        ApiPush["POST /api/internal/sync/push"]
        ApiPull["GET /api/internal/sync/pull"]
        ApiHealth["GET /api/internal/sync/health"]
    end

    %% Aliran Data Utama
    POS -->|Mutasi Stok / Kasir| Outbox_Local
    WMS -->|Katalog & Stok| Outbox_Local
    Queue_Local -->|1. PUSH HTTPS + HMAC Signature| ApiPush
    ApiPush --> Inbox_Cloud
    Inbox_Cloud -->|Update Katalog / Stok| DB_Cloud

    %% Aliran Pesanan Online
    Ecom -->|2. Order Online Lunas (Duitku)| Outbox_Cloud
    PullWorker -->|3. PULL HTTPS Request| ApiPull
    ApiPull -.->|Return Pending Orders| PullWorker
    PullWorker --> Inbox_Local
    Inbox_Local -->|4. Potong Stok & Jurnal Akunting| DB_Local
```

### Mengapa Disebut "Asimetris"?
1. **Aksesibilitas Jaringan Asimetris**:
   - **Cloud VPS**: Memiliki IP publik statis / domain HTTPS publik. Dapat dihubungi langsung dari mana saja.
   - **Primary Store**: Berada di jaringan lokal kantor/toko (Dynamic IP / CGNAT), tidak dapat menerima koneksi *inbound* langsung dari internet tanpa VPN/Tunneling rumit.
2. **Kombinasi Pola Push & Pull**:
   - **Toko Fisik $\to$ Cloud VPS (Push)**: Master data produk, mutasi stok fisik gudang, dan pembaruan tiket servis langsung didispatch via antrean queue *asynchronous* (`PushHybridSyncJob`) ke endpoint `POST /api/internal/sync/push`.
   - **Cloud VPS $\to$ Toko Fisik (Pull Fallback)**: Ketika ada transaksi online lunas (*order online paid*) di web, Cloud VPS menyimpannya di antrean outbox. Node toko fisik secara berkala menarik (*pull*) data antrean tersebut via `GET /api/internal/sync/pull` menggunakan command `php artisan sync:pull`.
3. **Kewenangan Data (*Single Source of Truth*)**:
   - Master Stok & Produk: **Primary Store** adalah *source of truth*.
   - Master Pembayaran Online: **Cloud Storefront** adalah *source of truth* untuk pesanan e-commerce sebelum diserahkan ke gudang lokal untuk diproses.

---

## 2. Struktur Data & Skema Database

Sistem sinkronisasi memanfaatkan pola **Transactional Outbox & Inbox** untuk menjamin *eventual consistency* tanpa risiko kehilangan data (*data loss*) jika koneksi internet terputus.

Migration: `database/migrations/2026_11_17_000001_create_hybrid_sync_tables.php`

### Tabel 1: `hybrid_sync_outbox`
Mencatat setiap event lokal yang harus dikirim ke node remote.

| Kolom | Tipe Data | Deskripsi |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Primary key |
| `sync_uuid` | `VARCHAR(36) UNIQUE` | UUIDv4 unik untuk setiap event |
| `event_type` | `VARCHAR(255)` | Jenis event (`produk.upsert`, `stok.mutasi`, `transaksi.pos`, `servis.update`, `order.online_paid`) |
| `entity_type` | `VARCHAR(255)` | Namespace kelas model (misal `App\Modules\Wms\Models\Produk`) |
| `entity_id` | `BIGINT UNSIGNED` | ID entitas lokal |
| `cabang_id` | `BIGINT UNSIGNED NULLABLE` | ID cabang untuk isolasi multi-tenant/multi-cabang |
| `payload` | `JSON` | Snapshot data lengkap entitas saat event terjadi |
| `status` | `VARCHAR(255)` | `pending`, `processing`, `synced`, `failed` |
| `attempts` | `INT DEFAULT 0` | Jumlah percobaan pengiriman |
| `last_error` | `TEXT NULLABLE` | Pesan error terakhir jika pengiriman gagal |
| `synced_at` | `TIMESTAMP NULLABLE` | Waktu berhasil diterima remote node |
| `created_at` / `updated_at` | `TIMESTAMP` | Waktu pembuatan event |

*Index*: `INDEX (status, created_at)` untuk performa query antrean.

### Tabel 2: `hybrid_sync_inbox`
Mencatat setiap event masuk dari node remote untuk menjamin prinsip **Idempotency**.

| Kolom | Tipe Data | Deskripsi |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Primary key |
| `sync_uuid` | `VARCHAR(36) UNIQUE` | UUID unik dari outbox pengirim |
| `source_node` | `VARCHAR(255)` | Node ID pengirim (misal `STORE-01` atau `CLOUD-VPS`) |
| `event_type` | `VARCHAR(255)` | Jenis event yang diproses |
| `payload_hash` | `VARCHAR(64)` | Hash SHA-256 dari string JSON payload |
| `status` | `VARCHAR(255)` | `processing`, `processed`, `ignored`, `failed` |
| `error_message` | `TEXT NULLABLE` | Pesan kegagalan pemrosesan |
| `processed_at` | `TIMESTAMP` | Waktu event selesai dieksekusi |
| `created_at` / `updated_at` | `TIMESTAMP` | Audit trail waktu penerimaan |

---

## 3. Keamanan & Verifikasi Tanda Tangan (HMAC-SHA256)

Semua komunikasi antar node melalui endpoint `/api/internal/sync/*` diproteksi oleh middleware `verify.sync` (`App\Modules\Sync\Middleware\VerifySyncSignature`).

### Aturan Header HTTP
Setiap request wajib menyertakan header berikut:
1. `X-Sync-Node`: Identifier node pengirim (contoh: `STORE-01`).
2. `X-Sync-Timestamp`: UNIX timestamp dalam detik (batas toleransi replay attack +/- 300 detik).
3. `X-Sync-Signature`: Tanda tangan kriptografis HMAC-SHA256.
4. `Content-Type`: `application/json` (untuk request POST).

### Algoritma Pembuatan Signature
- **Untuk POST Request (`/api/internal/sync/push`, `/api/internal/sync/push-batch`, `/api/internal/sync/ack`)**:
  ```php
  $timestamp = (string) time();
  $rawBody = json_encode($payload);
  $signature = hash_hmac('sha256', $timestamp.'.'.$rawBody, config('sync.api_secret'));
  ```
- **Untuk GET Request (`/api/internal/sync/pull`, `/api/internal/sync/health`)**:
  ```php
  $timestamp = (string) time();
  $queryString = (string) $request->getQueryString(); // misal: 'limit=50' atau '' untuk /health
  $signature = hash_hmac('sha256', $timestamp.'.'.$queryString, config('sync.api_secret'));
  ```

### Proteksi Keamanan yang Diterapkan
- **Feature Flag Enforcement**: Endpoint `/api/internal/sync/*` (kecuali `/health`) menolak request dengan HTTP 503 bila `SYNC_ENABLED=false`.
- **Replay Protection**: Header `X-Sync-Timestamp` diverifikasi maksimal selisih 300 detik dari waktu server.
- **Timing Attack Mitigation**: Menggunakan `hash_equals($expectedSignature, $receivedSignature)` untuk mencegah serangan perbandingan waktu (*timing attack*).
- **Secret Enforcement**: Jika `SYNC_API_SECRET` belum diisi di `.env`, request langsung ditolak dengan HTTP 401 (`Konfigurasi sync secret tidak ditemukan.`).
- **Data Tampering Protection**: Segala perubahan pada query string, timestamp, atau request body di tengah transmisi akan membuat signature tidak cocok dan request ditolak.

---

## 4. Jaminan Idempotency & Penanganan Kegagalan

Dalam lingkungan jaringan yang tidak stabil, *network timeout* dapat menyebabkan request dikirim ulang (*retry*). Sistem Hybrid Sync Bremspace ERP menerapkan **Strict Idempotency**:

1. **Pemeriksaan UUID Unik (`status === 'processed'`)**:
   Sebelum memproses data, `HybridSyncProcessorService` mengecek apakah event sudah benar-benar sukses (`status = 'processed'`). Event yang berstatus `failed` atau `processing` diizinkan diulang bersih saat retry berikutnya:
   ```php
   $existing = HybridSyncInbox::where('sync_uuid', $syncUuid)->first();
   if ($existing && $existing->status === 'processed') {
       return [
           'success' => true,
           'data' => ['sync_uuid' => $syncUuid, 'status' => $existing->status],
           'message' => 'Sudah diproses sebelumnya (idempotent skip).'
       ];
   }
   ```
2. **Atomic Database Transaction**:
   Pemrosesan data (pemotongan stok, pembuatan faktur penjualan, pembukuan jurnal akunting) dibungkus dalam `DB::beginTransaction()` dan `DB::commit()`. Jika terjadi error, database langsung di-`rollBack()` dan status inbox dicatat sebagai `failed`.
3. **Queue Retry dengan Exponential Backoff**:
   `PushHybridSyncJob` dikonfigurasi dengan:
   - Maksimum percobaan: 4 kali (`$tries = 4`).
   - Interval backoff: `[30, 60, 300, 900]` detik (30 detik, 1 menit, 5 menit, 15 menit).

---

## 5. Event yang Didukung & Logika Bisnis

| Event Type | Asal Aktor | Penerima Target | Tindakan di Sisi Penerima |
|---|---|---|---|
| `produk.upsert` | Kedua Node (Master) | Kedua Node | Memperbarui katalog produk lengkap (tanpa `cabang_id`) termasuk soft-delete & restore berbasis `sync_uuid`/natural key. |
| `sku_variant.upsert` / `harga_tier.upsert` / `produk_satuan_konversi.upsert` / `produk_barcode.sync` | Kedua Node (Master/POS) | Kedua Node | Sinkronisasi varian SKU, harga tier reseller/membership, konversi multi-satuan, dan multi-barcode. |
| `kategori_produk.upsert` / `brand.upsert` / `kualitas_produk.upsert` / `satuan_unit.upsert` / `tipe_hp.upsert` / `pelanggan.upsert` | Kedua Node (Master/CRM) | Kedua Node | Sinkronisasi data master referensi & pelanggan anti-kolisi ID lintas node via `sync_uuid`. |
| `stok.mutasi` | Toko Lokal (`primary_store`) | Cloud VPS | Mengupdate jumlah stok (`StokItem` termasuk `sku_variant_id`) & mencatat `StokLog` audit di VPS. |
| `transaksi.pos` | Toko Lokal (`primary_store`) | Cloud VPS | Rekap transaksi & jurnal penuh (`tunai`, `transfer`/`qris`, `piutang`, `split`, PPN, HPP; skip penjualan ganda untuk `sumber='servis'`). |
| `transaksi.pos_cloud` | Cloud VPS Failover | Toko Lokal | Menyerap transaksi darurat cloud (`VPS-...`), memotong stok fisik (`StokDeductionService`), dan mencatat piutang/jurnal lokal. |
| `transaksi.void` | Kedua Node | Kedua Node | Menandai `status='void'`, membalik `JurnalHeader`, membatalkan `Piutang`, dan memulihkan stok (`pos_cloud`/`VPS-`). |
| `servis.update` | Toko Lokal (Teknisi) | Cloud VPS | Memperbarui data lengkap tiket servis termasuk estimasi, pembayaran, dan `token_approval`. |
| `order.online_paid` | Cloud VPS (Duitku) | Toko Lokal | Memotong stok fisik gudang (`StokDeductionService` `[C4]`), mencatat pesanan, dan membukukan jurnal DPP/PPN/ongkir/HPP. |
| `jurnal.upsert` / `payroll.upsert` | Toko Lokal (Backoffice) | Cloud VPS | Sinkronisasi jurnal standalone (`manual`, `payroll`, `payroll-bayar`, `asset`, `depresiasi`, `disposal`) dan periode/slip payroll final. |

---

## 6. Petunjuk Instalasi & Konfigurasi Lingkungan

### 6.1 Konfigurasi Node Cloud VPS (Storefront)

1. Buka file `.env` di VPS:
   ```bash
   nano /var/www/ute-parts-erp/.env
   ```
2. Set konfigurasi berikut:
   ```ini
   # Hybrid Sync Configuration - Cloud VPS
   SYNC_ENABLED=true
   SYNC_NODE_ROLE=cloud_storefront
   SYNC_NODE_ID=CLOUD-VPS-01
   SYNC_REMOTE_URL=
   SYNC_API_SECRET=GantiDenganStringRahasiaAcakPanjangMinimal32Karakter!
   SYNC_PULL_INTERVAL_SECONDS=30
   ```
   *(Catatan: `SYNC_REMOTE_URL` di VPS dapat dikosongkan karena VPS bertindak sebagai passive receiver).*

3. Jalankan migrasi tabel sync & kolom `sync_uuid`:
   ```bash
   php artisan migrate --force
   ```

4. Pastikan Supervisor mengawasi queue `sync`:
   ```bash
   # Di /etc/supervisor/conf.d/ute-worker.conf
   # Tambahkan parameter queue sync
   command=php /var/www/ute-parts-erp/artisan queue:work --queue=sync,default --sleep=3 --tries=3
   ```

---

### 6.2 Konfigurasi Node Primary Store (Toko Fisik / Lokal)

1. Buka file `.env` di server toko lokal:
   ```bash
   nano .env
   ```
2. Set konfigurasi berikut:
   ```ini
   # Hybrid Sync Configuration - Primary Store
   SYNC_ENABLED=true
   SYNC_NODE_ROLE=primary_store
   SYNC_NODE_ID=STORE-UTAMA-01
   SYNC_REMOTE_URL=https://toko.bremspace.com
   SYNC_API_SECRET=GantiDenganStringRahasiaAcakPanjangMinimal32Karakter!
   SYNC_PULL_INTERVAL_SECONDS=30
   ```
   > [!IMPORTANT]
   > Nilai `SYNC_API_SECRET` **wajib persis sama** antara Cloud VPS dan Toko Lokal.

3. Jalankan migrasi database lokal:
   ```bash
   php artisan migrate --force
   ```

4. Konfigurasikan Scheduler & Queue Worker di Lokal:
   Pastikan cron sistem menjalankan `php artisan schedule:run` setiap menit:
   ```bash
   * * * * * cd /path/ke/ute-parts-erp && php artisan schedule:run >> /dev/null 2>&1
   ```
   Jalankan worker lokal untuk antrean sync:
   ```bash
   php artisan queue:work --queue=sync,default
   ```

---

## 7. Panduan Perintah Operasional & Troubleshooting

### 7.1 Cek Kesehatan Node (*Health Check*)
Setiap node menyediakan endpoint pemantauan kesehatan publik tanpa autentikasi ketat:

**Endpoint**: `GET /api/internal/sync/health`

**Contoh Request**:
```bash
curl -s http://localhost:8000/api/internal/sync/health | jq .
```

**Contoh Response Sukses**:
```json
{
  "success": true,
  "data": {
    "node_id": "STORE-UTAMA-01",
    "node_role": "primary_store",
    "sync_enabled": true,
    "pending_count": 0,
    "last_synced_at": "2026-11-17T04:55:12.000000Z",
    "server_time": "2026-11-17T05:00:00.000000Z"
  },
  "message": "Node sync aktif dan sehat."
}
```

---

### 7.2 Menarik Event Manual: `sync:pull`
Gunakan perintah ini di node **Primary Store** jika ingin memaksa penarikan event order online dari Cloud VPS tanpa menunggu cron:

```bash
# Tarik default 50 event
php artisan sync:pull

# Tarik dengan limit kustom
php artisan sync:pull --limit=100
```

**Indikator Output**:
- `Menghubungi VPS: https://toko.bremspace.com/api/internal/sync/pull?limit=50 ...`
- `Berhasil mengambil 3 event dari remote node.`
- `Sinkronisasi pull selesai: 3 diproses, 0 gagal.`

---

### 7.3 Mengirim Ulang Antrean Gagal: `sync:flush`
Jika koneksi internet toko lokal sempat terputus berjam-jam, item outbox akan menumpuk dalam status `failed` atau `pending`. Setelah internet kembali menyala, jalankan:

```bash
php artisan sync:flush
```

**Perilaku**:
- Mencari seluruh record `hybrid_sync_outbox` yang berstatus `pending` atau `failed`.
- Mendispatch ulang `PushHybridSyncJob` untuk masing-masing item ke queue `sync`.

---

### 7.4 Memeriksa Log & Error Transmisi

1. **Filter Log Khusus Hybrid Sync**:
   ```bash
   tail -n 100 -f storage/logs/laravel.log | grep --line-buffered "\[HybridSync\]"
   ```

2. **Query SQL Cek Item Outbox yang Gagal**:
   ```sql
   SELECT id, sync_uuid, event_type, attempts, last_error, created_at 
   FROM hybrid_sync_outbox 
   WHERE status = 'failed' 
   ORDER BY id DESC LIMIT 20;
   ```

3. **Query SQL Cek Inbox Terbaru**:
   ```sql
   SELECT id, sync_uuid, source_node, event_type, status, error_message, processed_at 
   FROM hybrid_sync_inbox 
   ORDER BY id DESC LIMIT 20;
   ```

---

## 8. Panduan Mengatasi Masalah (*Incident Runbook*)

### Skenario A: HTTP 401 "Tanda tangan tidak valid"
- **Penyebab**: `SYNC_API_SECRET` tidak identik di kedua node, atau reverse proxy (Cloudflare/Nginx) mengubah/memangkas payload JSON saat transmisi.
- **Solusi**:
  1. Periksa `SYNC_API_SECRET` di `.env` kedua server:
     ```bash
     grep SYNC_API_SECRET .env
     ```
  2. Jika menggunakan Cloudflare di depan VPS, pastikan aturan WAF tidak memodifikasi request body atau me-reset header kustom `X-Sync-Node`, `X-Sync-Timestamp` & `X-Sync-Signature`.
  3. Lakukan clear config cache:
     ```bash
     php artisan config:clear
     ```

---

### Skenario B: Stok Toko Fisik Tidak Berubah Setelah Order Online
- **Penyebab**: Command `sync:pull` di lokal tidak berjalan karena cron sistem mati, atau antrean queue `sync` tidak diproses.
- **Solusi**:
  1. Jalankan `php artisan sync:pull` secara manual di server lokal.
  2. Periksa apakah cron scheduler aktif:
     ```bash
     ps aux | grep "artisan schedule:run"
     ```
  3. Pastikan queue worker aktif:
     ```bash
     ps aux | grep "queue:work"
     ```
     Jika tidak ada worker yang berjalan, nyalakan:
     ```bash
     php artisan queue:work --queue=sync,default
     ```

---

### Skenario C: Duplikasi Event / Kejadian Terproses Ganda
- **Penyebab**: Pelanggaran kolom unik `sync_uuid` pada database jika migrasi belum lengkap.
- **Solusi**:
  1. Pastikan tabel `hybrid_sync_inbox` memiliki constraint `UNIQUE(sync_uuid)`.
  2. Sistem secara otomatis mengembalikan `Sudah diproses sebelumnya (idempotent skip)` jika UUID yang sama dikirimkan berulang kali, sehingga saldo keuangan dan stok fisik dijamin aman dari pemotongan ganda.

---

## 9. Checklist Pemeliharaan & Audit Berkala

Setiap minggu, administrator sistem disarankan menjalankan checklist berikut:
- [ ] Buka endpoint `/api/internal/sync/health` di kedua node untuk memastikan `pending_count` mendekati 0.
- [ ] Periksa apakah ada record `status = 'failed'` di `hybrid_sync_outbox`. Jika ada, telaah `last_error` lalu jalankan `php artisan sync:flush`.
- [ ] Bersihkan log lama tabel outbox dan inbox yang sudah tersinkronisasi lebih dari 30 hari untuk menghemat kapasitas storage:
  ```sql
  DELETE FROM hybrid_sync_outbox WHERE status = 'synced' AND synced_at < NOW() - INTERVAL 30 DAY;
  DELETE FROM hybrid_sync_inbox WHERE status = 'processed' AND processed_at < NOW() - INTERVAL 30 DAY;
  ```
- [ ] Pastikan rotasi log Laravel berjalan dengan baik pada `storage/logs/`.
