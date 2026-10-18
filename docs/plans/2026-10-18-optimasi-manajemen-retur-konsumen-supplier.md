# Blueprint & Rencana Kerja: Optimasi Manajemen Retur End-to-End
**Ute Parts ERP — Toko Offline, Web Store, Marketplace (Shopee), & Supplier**
*Tanggal Perencanaan: 18 Oktober 2026*

---

## 1. Latar Belakang & Ruang Lingkup
Proses retur di Ute Parts memiliki dinamika multi-channel (Toko Offline POS, Web Store, dan Marketplace seperti Shopee) yang bermuara pada dua arah penyelesaian:
1. **Penyelesaian ke Konsumen**: Pengembalian uang (refund) atau tukar produk (exchange).
2. **Penyelesaian ke Supplier**: Konsolidasi barang rusak/cacat untuk dikembalikan ke supplier asal dengan kompensasi berupa saldo deposit (potong utang / kredit belanja berikutnya) atau pengembalian dana transfer.

Tantangan operasional terbesar adalah **penerimaan barang retur (QC)**: mencegah fraud/penipuan (konsumen meretur barang yang bukan dibeli dari toko, segel garansi rusak, unit ditukar, atau klaim palsu di marketplace).

---

## 2. Matriks Jalur Masuk Retur (Multi-Channel Entry)

Sistem mengakomodir 3 pintu masuk retur dengan karakteristik operasional masing-masing:

| Saluran (Channel) | Cara Pengajuan | Identifikasi Awal | Bukti Awal yang Wajib Ada |
|---|---|---|---|
| **POS (Toko Offline)** | Konsumen walk-in langsung ke kasir/customer service cabang. | No. Struk / No. Transaksi POS atau Scan Barcode Nota. | Fisik nota/struk, fisik barang, cek segel toko langsung di tempat. |
| **Web Store (Online)** | Konsumen klik "Ajukan Retur" di riwayat pesanan dashboard akun web. | No. Order Web (contoh: `TRX-WEB-...`). | Foto kerusakan produk, video kendala, nomor resi kirim balik (Biteship). |
| **Marketplace (Shopee / dll)** | Komplain masuk lewat sistem marketplace (tersinkron via Omnichannel Webhook). | No. Pesanan Marketplace (`channel_orders.channel_order_id`). | Foto/video di aplikasi Shopee, nomor resi retur kurir marketplace. |

---

## 3. Protokol QC Penerimaan Barang & Pencegahan Fraud (Anti-Barang Palsu)

Untuk memastikan barang yang diterima **benar-benar barang yang dijual oleh Ute Parts**, diterapkan 4 lapis verifikasi:

```
                  [PAKET / BARANG RETUR TIBA]
                               │
            ┌──────────────────┴──────────────────┐
            ▼                                     ▼
     (Produk Ber-SN)                     (Produk Non-SN)
            │                                     │
   [1. Cek Nomor Seri / SN]             [1. Cek Riwayat Nota]
   Sistem cek tabel `nomor_seri`:       Apakah SKU & jumlah cocok
   - Terdaftar di database?             dengan invoice asli?
   - Tercatat terjual pada no nota ini?           │
            │                                     │
            └──────────────────┬──────────────────┘
                               │
            [2. Cek Segel Toko & Fisik (QC)]
            - Segel garansi utuh? (Tidak ada bekas congkel/bakar)
            - Kondisi fisik wajar? (Bukan kelalaian pemakaian: jatuh/kena air)
            - Tanggal retur masih dalam masa garansi toko?
                               │
            [3. Dokumentasi Foto / Video Unboxing]
            - Kasir/Gudang wajib upload 1-3 foto fisik barang saat diterima
            - Khusus marketplace: upload bukti unboxing paket kurir
                               │
                 ┌─────────────┴─────────────┐
                 ▼                           ▼
          [LOLOS VERIFIKASI]          [TOLAK RETUR]
                 │                           │
          Lanjut ke Solusi:            - Walk-in: Kembalikan fisik + nota tolak
          Refund / Tukar Produk        - Web: Kirim balik paket ke konsumen
                                       - Marketplace: Klik "Ajukan Banding/Dispute"
                                         ke Shopee dengan bukti foto/SN sistem.
```

### Aturan Validasi Sistem (System Constraints):
1. **Pengecekan Nomor Seri (SN Tracking)**:
   - Jika produk memiliki atribut `is_sn = true`, kasir/staf gudang **wajib scan/input SN barang yang dikembalikan**.
   - Sistem melakukan query lock ke tabel `nomor_seri`. Jika SN tersebut berstatus bukan `terjual` pada transaksi tersebut, sistem **menolak transaksi retur** dengan pesan: `"Nomor Seri [SN] bukan merupakan barang dari transaksi ini / tidak terdaftar di sistem Ute Parts"`.
2. **Pengecekan Masa Garansi**:
   - Sistem otomatis menghitung `tanggal_transaksi + durasi_garansi_hari`. Jika tanggal penerimaan melebihi periode garansi, muncul peringatan: `"Masa garansi toko telah habis (berakhir pada DD/MM/YYYY). Butuh approval Supervisor/Admin Toko untuk pengecualian."`
3. **Lampiran Foto / Video Bukti Penerimaan**:
   - Form penerimaan retur menyediakan field upload foto kondisi fisik barang (dan resi paket jika via ekspedisi).

---

## 4. Alur Kerja Detil per Saluran Penjualan

### A. Alur Retur Offline (Toko / Walk-in)
1. **Penerimaan di Kasir**: Kasir buka menu POS -> tombol `Retur Transaksi` -> scan barcode struk.
2. **Pemeriksaan Barang**: Kasir cek fisik segel & input SN (jika ada).
3. **Pilihan Solusi**:
   - **Opsi 1: Pengembalian Uang (Refund)**: Kasir pilih Kas Laci / Transfer. Jika nominal > batas wewenang kasir (threshold), minta PIN approval Supervisor.
   - **Opsi 2: Tukar Produk (Exchange)**:
     * Kasir pilih produk pengganti dari katalog POS.
     * Sistem menghitung kalkulasi selisih harga:
       - *Barang sama / Harga sama*: Selisih Rp 0 (Impas).
       - *Harga pengganti lebih mahal*: Konsumen tambah bayar selisih di kasir.
       - *Harga pengganti lebih murah*: Toko kembalikan sisa uang ke konsumen.
4. **Pemisahan Kondisi Fisik**:
   - Kasir menentukan status barang retur:
     * `Layak Jual`: Langsung kembali ke stok aktif toko.
     * `Rusak / Cacat Pabrik`: Otomatis masuk ke **Pool Karantina Retur WMS** (tercatat supplier asal).

---

### B. Alur Retur Web Store (Online UteParts.id)
1. **Pengajuan oleh Konsumen**: Konsumen login ke web -> menu Akun Saya -> Riwayat Pesanan -> klik `Ajukan Retur/Garansi`. Konsumen mengisi alasan & upload foto/video kendala.
2. **Tinjauan CS / Admin Toko**: CS memeriksa foto/video di backoffice. Jika valid, status diubah menjadi `Menunggu Pengiriman Balik` dan sistem menerbitkan kode RMA serta alamat tujuan retur.
3. **Penerimaan Paket oleh Gudang**:
   - Paket tiba dari ekspedisi. Staf gudang membuka menu `WMS -> Retur Masuk Online`.
   - Staf scan resi pengiriman / input kode RMA.
   - Staf melakukan unboxing, mencocokkan SN & kondisi fisik.
4. **Eksekusi Solusi**:
   - *Jika Refund*: Finance memproses refund (via payment gateway Duitku / transfer / saldo akun).
   - *Jika Tukar Produk*: Sistem otomatis membuat order pengiriman baru di Biteship untuk mengirimkan unit pengganti ke alamat konsumen.
   - *Jika Gagal QC*: Staf menolak retur dengan menyertakan bukti foto unboxing, lalu paket dikirim balik ke konsumen.

---

### C. Alur Retur Marketplace (Shopee, Tokopedia, dll.)
1. **Deteksi Pengajuan**: Pembeli mengajukan komplain/retur di aplikasi Shopee. Status pesanan di `channel_orders` berubah menjadi `return_requested` (via Webhook / sync otomatis).
2. **Penerimaan Paket Retur Ekspedisi Marketplace**:
   - Kurir mengantar paket retur ke gudang toko.
   - Staf gudang buka menu `Omnichannel -> Penerimaan Retur Marketplace`.
   - Scan No. Resi Marketplace / No. Pesanan Shopee.
3. **Pemeriksaan Keaslian & Integritas Paket (Anti-Fraud)**:
   - Staf rekam foto/video unboxing.
   - Cek apakah barang di dalam paket adalah produk Ute Parts (cocokkan SN dengan data SN yang dikirim saat packing pesanan awal).
4. **Keputusan Sistem**:
   - **Skenario Sah (Barang Sesuai & Cacat/Rusak)**:
     * Staf klik `Konfirmasi Terima Retur (Valid)`.
     * Sistem memberi instruksi/trigger ke Shopee untuk menyetujui refund dana ke pembeli.
     * Barang otomatis dimasukkan ke **Pool Karantina Retur Supplier**.
   - **Skenario Fraud (Barang Ditukar / Palsu / Paket Kosong / Rusak Akibat Konsumen)**:
     * Staf klik `Tolak Retur & Ajukan Banding ke Shopee`.
     * Sistem menyediakan rangkuman bukti lengkap: foto packing awal toko, riwayat SN keluar, dan foto unboxing barang palsu yang diterima.
     * Dana toko di Shopee terlindungi dari pemotongan sepihak.

---

## 5. Alur Lanjutan: Toko ke Supplier (WMS & Akunting)

Setelah barang retur berstatus `Rusak / Klaim Supplier` (baik dari toko offline, web, maupun marketplace), alurnya disatukan di WMS:

```
[Pool Karantina Retur WMS]
  ├── Filter: Supplier "PT Surya Jaya" (Ada 6 item rusak: LCD, IC, Flexible)
  ├── Filter: Supplier "Shenzhen Parts" (Ada 12 item rusak)
        │
        ▼ (Staf Gudang checklist barang & klik "Buat Retur Supplier")
[Dokumen Retur Pembelian / Pengiriman ke Supplier]
        │
        ▼ (Barang dikirim ke Supplier & dikonfirmasi)
[Penyelesaian Keuangan oleh Finance]
  ├── 1. Saldo / Deposit Supplier (Default):
  │     - Otomatis memotong sisa Utang Usaha ke supplier tersebut.
  │     - Jika tidak ada utang, masuk ke Saldo Kredit Supplier (dipakai potong PO baru).
  │     - Jurnal: [Dr] Utang Usaha / Deposit Supplier -- [Cr] Persediaan Karantina
  └── 2. Pengembalian Dana (Refund Kas/Bank):
        - Supplier transfer kembali uang ke rekening toko.
        - Jurnal: [Dr] Kas/Bank -- [Cr] Persediaan Karantina
```

---

## 6. Pemetaan Hak Akses (RBAC) & Peran Pengguna

| Role | Akses Fitur & Tindakan Operasional |
|---|---|
| **Kasir** | Menerima retur POS langsung, tukar barang sejenis/beda jenis, cek SN & struk. Tidak bisa approve retur di atas threshold limit nominal. |
| **Staff Gudang** | Menerima paket retur web/marketplace, QC fisik unboxing, input SN verifikasi, mengelola Pool Karantina Retur, membuat surat jalan pengiriman retur ke supplier. |
| **Customer Service (CS)** | Meninjau pengajuan retur awal dari Web Store / komplain Marketplace, berkomunikasi dengan konsumen. |
| **Admin Toko / Supervisor** | Approval retur POS yang melewati batas limit kasir, approval retur tanpa nota (pengecualian darurat), approval pengajuan banding dispute marketplace. |
| **Finance / Akunting** | Eksekusi refund uang (Kas/Bank/Duitku), input nota kredit / rekonsiliasi saldo deposit supplier, validasi bukti transfer refund supplier. |
| **Super Admin / Owner** | Akses penuh seluruh konfigurasi, laporan audit tingkat retur per brand/supplier, investigasi fraud. |

---

## 7. Desain Teknis (Database & Komponen Kode)

### A. Penyesuaian Skema Database (Migrations)
1. **Tabel `return_penjualan`**:
   - `tipe_penyelesaian`: `enum('refund', 'tukar_produk')` default `'refund'`.
   - `tukar_transaksi_id`: `bigInteger unsigned nullable` (FK ke `transaksi` baru hasil tukar).
   - `selisih_nominal`: `decimal(14,2)` default `0`.
   - `foto_bukti`: `json nullable` (menyimpan array path foto fisik / unboxing).
   - `catatan_qc`: `text nullable`.
   - `channel`: `string(30)` default `'pos'` (`'pos'`, `'web'`, `'shopee'`).

2. **Tabel `return_penjualan_item`**:
   - `kondisi_barang`: `enum('bagus', 'rusak')` default `'rusak'`.
   - `nomor_seri`: `json nullable` (SN yang divalidasi).
   - `supplier_id`: `bigInteger unsigned nullable` (supplier asal barang untuk rujukan retur pembelian).

3. **Tabel `supplier`**:
   - `saldo_deposit`: `decimal(14,2)` default `0` (saldo kredit dari retur untuk memotong pembelian PO berikutnya).

4. **Tabel `return_pembelian`**:
   - Jadikan `purchase_order_id` menjadi `nullable` (memungkinkan retur gabungan konsolidasi banyak barang rusak tanpa terikat satu nomor PO lama).
   - `metode_pengembalian`: `enum('utang', 'saldo', 'kas')` default `'saldo'`.

### B. Komponen Modul Terkait
- **POS**: `app/Modules/Pos/Livewire/PosKasir.php` & modal retur (penambahan radio tukar produk + scan SN).
- **Online & Marketplace**: `app/Modules/Omnichannel/Livewire/OmnichannelCommandCenter.php` & tab penerimaan retur paket.
- **WMS**: `app/Modules/Wms/Livewire/ReturnPembelianTab.php` (penambahan sub-tab: **Pool Karantina Retur Supplier**).
- **Akunting**: `app/Modules/Akunting/Services/JurnalService.php` (template jurnal tukar produk & kredit deposit supplier).

---

## 8. Langkah-Langkah Eksekusi (Implementation Steps)

1. **Fase 1: Database Migration**: Tambah kolom penyelesaian tukar produk, kondisi barang, audit foto, dan saldo deposit supplier.
2. **Fase 2: Backend Logic & Services**:
   - Update `ReturnPenjualanService`: validasi SN ketat, isolasi stok karantina jika rusak, kalkulasi tukar barang.
   - Update `ReturnPembelianService`: konsolidasi barang retur dari pool karantina, alokasi saldo deposit supplier / potong utang berjalan.
3. **Fase 3: Frontend POS & WMS**:
   - Modal retur kasir: pilihan Refund vs Tukar Produk + scan produk pengganti + pilihan kondisi barang (Layak Jual vs Rusak).
   - WMS: Tab Pool Karantina Retur Supplier dengan grouping per supplier + tombol 1-klik buat retur.
   - Omnichannel: Antarmuka verifikasi paket retur marketplace (QC unboxing + tombol Terima vs Banding).
4. **Fase 4: Testing & Verifikasi**:
   - Unit test validasi SN pada retur.
   - Feature test transaksi tukar barang (selisih bayar lebih / kurang / impas).
   - Feature test retur supplier dengan pemotongan utang dan deposit.
   - Verifikasi automated test suite.
