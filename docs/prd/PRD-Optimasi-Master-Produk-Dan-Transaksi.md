# PRD: Optimasi Menyeluruh Master Data Produk & Alur Transaksi End-to-End
**Sistem:** Bremspace ERP (Laravel 13, TALL Stack)  
**Versi Dokumen:** 1.0.0  
**Tanggal:** 8 November 2026  
**Status:** DRAFT / AUDITED  
**Penulis:** Tim Arsitektur & Core Engine  

---

## 1. Eksekutif & Latar Belakang

Berdasarkan audit mendalam sistematis pada seluruh modul yang terlibat dalam siklus hidup **Master Data Produk** dan **Alur Transaksi** (WMS, POS Kasir, Marketplace Online, Servis, Akunting/Jurnal, Omnichannel, CRM, Workflow, dan Pelaporan), ditemukan beberapa celah kritis yang dapat menyebabkan:
1. **Kebocoran Finansial & Inkonsistensi Buku Besar**: Total akhir online order mengabaikan ongkir, laporan laba rugi bernilai Rp 0 akibat salah agregasi, arus kas mengabaikan mutasi bank/kas laci, jurnal transfer antar-cabang absen.
2. **Race Condition & Tabrakan Nomor Dokumen**: Penomoran transaksi online, payroll, dan return menggunakan pola `count() + 1` tanpa lock pesimistik, memicu benturan `UNIQUE` dan benturan nomor jurnal antar cabang.
3. **Pemisahan Stok Fisik vs Buku (SOT Desync)**: Pembatalan pesanan online menulis kolom fiktif ke `stok_log`, kegagalan potong stok order channel ditelan senyap, dan reservasi stok multi-gudang salah hitung.
4. **Deadlock Workflow & Otorisasi**: Approval Purchase Order di inbox workflow tidak pernah mengubah status PO di WMS (handler hilang), dan route destructive `DELETE /api/wms/produk/{id}` tidak memiliki guard permission Spatie.

Dokumen ini menjadi cetak biru teknis komprehensif untuk memperbaiki semua celah di atas dan mengoptimalkan sistem agar siap produksi 100%.

---

## 2. Peta Hubungan Antar-Modul (Cross-Module Map)

```
[Master Produk: WMS]
  │  ├── Produk (Global) ──< SkuVariant (Global)
  │  ├── Brand, Kategori, Kualitas, Satuan (Global)
  │  └── StokItem (Per Gudang x Cabang)
  │
  ├──► [POS Kasir: Pos]
  │      ├── Scan SKU / Barcode / SN
  │      ├── Pricing Tier Resolution (CRM)
  │      ├── StokDeductionService (Lock Pessimistic)
  │      ├── Transaksi & TransaksiItem
  │      └── JurnalService (Kas/Bank/Piutang vs Pendapatan + HPP/Persediaan)
  │
  ├──► [Marketplace Online: Marketplace]
  │      ├── Catalog & Cart (Cabang-scoped stock check)
  │      ├── Biteship Rate & Shipping Selection (Ongkir Integration)
  │      ├── Duitku Gateway (Webhook Signature & Idempotency)
  │      ├── StockReservationService (TTL 2 jam) -> Deduction
  │      └── Jurnal Otomatis (Clearing Gateway + Ongkir + Pendapatan + HPP)
  │
  ├──► [Servis HP: Servis]
  │      ├── TiketServis & Estimasi Biaya
  │      ├── Sparepart Deduction (StokLog 'servis' + Claim SN)
  │      ├── onSelesai (Akrual: Piutang 120-01 vs Pendapatan Jasa/Part + HPP)
  │      └── bayar (Kas/Bank vs Piutang 120-01)
  │
  ├──► [Omnichannel: Shopee/Marketplace Eksternal]
  │      ├── Webhook Ingestion (Signature Check Mandatory)
  │      ├── PushStockToChannelJob (Debounced per SKU)
  │      └── Sinkronisasi Order -> Transaksi Lokal + Potong Stok Fail-Closed
  │
  └──► [Audit Trail, Akunting & Pelaporan]
         ├── StokLog & StockMutationLog (Dual-Audit Immutable SOT)
         ├── JurnalAkuntansi (Double-Entry Header-Line, Cabang-Scoped)
         ├── Subledger (Piutang Usaha 120-01, Utang Usaha 210-01, Utang Komisi 210-03)
         └── Laporan Finansial (Laba Rugi, Neraca Saldo, Arus Kas Kas/Bank, Mutasi Stok)
```

---

## 3. Matriks Hasil Audit & Celah Kritis (Audit Findings Matrix)

### Kategori A: Finansial, Jurnal & Pembukuan (Akunting)
| ID | Lokasi File | Masalah | Tingkat Keparahan | Dampak |
|---|---|---|---|---|
| A-01 | `ExportLaporanService.php:140` | Laba Rugi menjumlahkan seluruh debit dan kredit akun tanpa filter jenis pendapatan/beban. | 🔴 CRITICAL | Laba bersih di laporan ekspor selalu Rp 0. Neraca akun (Kas/Utang) bocor ke laporan laba rugi. |
| A-02 | `ExportLaporanService.php:301` | Laporan Arus Kas hardcoded akun `110-01` (Kas Utama). | 🔴 CRITICAL | Transaksi Bank (`110-02`), QRIS, dan Kas Laci (`110-04`) hilang dari laporan arus kas. |
| A-03 | `ValidasiBarisJurnal.php:200` | Melarang mutasi kredit pada akun saldo normal debit, dan melarang debit pada akun normal kredit. | 🔴 CRITICAL | Jurnal manual penyesuaian normal tidak bisa diposting (misal kredit Kas/Bank saat bayar biaya). |
| A-04 | `OrderService.php:127` | `transaksi.total_akhir` pesanan online mengabaikan nilai ongkir (`$shippingData['ongkir']`). | 🔴 CRITICAL | Konsumen tidak tertagih ongkir, pembukuan piutang/clearing Duitku selisih dengan nilai tagihan. |
| A-05 | `DuitkuPaymentProcessor.php:127` | Jurnal pembayaran Duitku tidak mencatat beban MDR gateway (`520-06`) dan akun ongkir (`410-xx`/`210-xx`). | 🔴 CRITICAL | Selisih rekonsiliasi bank Duitku dengan penerimaan fisik dana. |
| A-06 | `PayrollService.php:172, 220` | `generateNoJurnal` untuk payroll menggunakan format `JRL-PR-{periode}` tanpa parameter `cabang_id`. | 🔴 CRITICAL | Payroll cabang kedua pada bulan yang sama gagal posting karena tabrakan kunci unik `no_jurnal`. |
| A-07 | `ReturnPenjualanService.php:301, 616` & `ReturnPembelianService.php:124, 680` | `no_return` tanpa identitas cabang diteruskan langsung sebagai parameter `$noJurnal`. | 🔴 CRITICAL | Format `no_jurnal` standar `JRL-...` dilanggar dan berisiko konflik lintas cabang. |
| A-08 | `TransferTab.php:452, 507` | Mutasi transfer stok antar cabang tidak memicu pembuatan jurnal mutasi persediaan. | 🟡 HIGH | Nilai aset persediaan di neraca saldo cabang asal dan cabang tujuan tidak berubah walau barang pindah. |
| A-09 | `PembayaranSubledgerService.php:166` | Resolusi akun utang komisi gagal mendeteksi `Komisi::class`, jatuh ke akun Utang Usaha (`210-01`) bukannya `210-03`. | 🟡 HIGH | Distorsi subledger utang usaha dan utang komisi. |
| A-10 | `PosCheckoutService.php:210, 255` | Kasir memilih metode bayar `piutang` (kasbon) tanpa memilih pelanggan. GL `120-01` terdebit tanpa subledger Piutang. | 🟡 HIGH | Piutang gantung yatim piatu tanpa identitas penagihan. |
| A-11 | `Utang.php:17` | Model `Utang` tidak memiliki saving hook untuk kalkulasi kolom `sisa` (`jumlah - jumlah_dibayar`). | 🟡 HIGH | Query raw SQL yang membaca `utang.sisa` menghasilkan data basi atau NULL. |
| A-12 | `GrnService.php:822` | Typo nama tabel `jurnal_headers` (seharusnya `jurnal_header`) pada pengecekan urutan nomor jurnal GRN. | 🟢 MEDIUM | Fallback ke pencarian lambat karena query header gagal dieksekusi. |
| A-13 | `ImportProdukService.php:3869` & `ImportSidRetailService.php:913` | Nomor jurnal saldo awal produk import mengabaikan `cabang_id`. | 🟢 MEDIUM | Potensi benturan jika dua cabang mengimpor produk secara bersamaan. |

---

### Kategori B: Alur Transaksi, Konkurensi & Gateway (POS & Marketplace)
| ID | Lokasi File | Masalah | Tingkat Keparahan | Dampak |
|---|---|---|---|---|
| B-01 | `OrderService.php:47-51` | Nomor transaksi online menggunakan `count() + 1` tanpa kunci baris (`lockForUpdate`). | 🔴 CRITICAL | Checkout bersamaan menghasilkan nomor transaksi kembar, gagal simpan atau ditolak Duitku. |
| B-02 | `PaymentController.php:346-358` | Endpoint unauthenticated `POST /api/payment/check-status` mencocokkan `qris_payment_{amount}`. | 🔴 CRITICAL | Kerentanan keamanan: pengguna lain yang polling dengan nominal sama dapat mengklaim status bayar order orang lain. |
| B-03 | `PaymentController.php:160` | Webhook Duitku memverifikasi merchant code terhadap `config('duitku.merchant_code')`, mengabaikan DB. | 🔴 CRITICAL | Webhook HTTP 400 Invalid Merchant jika kredensial diatur via UI database (`duitku_gateway_config`). |
| B-04 | `DuitkuService.php:450` | Signature MD5 Duitku menggunakan `$this->apiKey` bukan `$this->merchantKey`. | 🔴 CRITICAL | Validasi signature MD5 selalu gagal saat Duitku mengirim callback. |
| B-05 | `DuitkuPaymentProcessor.php:78` | Pembayaran masuk pada order yang telah berstatus `kadaluarsa` direspon HTTP 200 `sudah_diproses` senyap. | 🔴 CRITICAL | Dana konsumen tertahan tanpa barang diproses, tanpa jurnal uang muka, dan tanpa notifikasi petugas. |
| B-06 | `PosKasir.php:2669` & `PosCheckoutService.php:300` | `TransactionActivityLogService::catatPenjualan()` dipanggil dua kali pada checkout Livewire POS. | 🟡 HIGH | Duplikasi riwayat audit log transaksi kasir di database. |
| B-07 | `CartCheckout.php:155` | Integrasi Biteship (pilihan kurir dan hitung tarif live) hilang dari blade UI checkout. | 🟡 HIGH | Konsumen checkout tanpa ongkir atau kurir yang valid di antarmuka publik. |
| B-08 | `BiteshipService.php:51` & `ShippingController.php:205` | Payload koordinat Biteship invalid & koordinat cabang di-generate pseudo-random mock. | 🟡 HIGH | Panggilan API Biteship gagal atau menghasilkan tarif ongkir fiktif. |
| B-09 | `PosKasir.php:1473` | Nilai `harga_beli` (HPP barang) tersimpan pada array `$cart` public Livewire. | 🟡 HIGH | Kebocoran data sensitif margin toko ke inspect element browser konsumen. |
| B-10 | `PosKasir.php:2696` & `ManagesHoldTransactions.php:82` | `lihatDetailTransaksi` dan `getDitahanList` menggunakan `when($cabangId, ...)`. | 🟢 MEDIUM | Jika sesi cabang null, query membaca transaksi seluruh cabang toko. |

---

### Kategori C: Master Data Produk, Stok & Mutasi (WMS)
| ID | Lokasi File | Masalah | Tingkat Keparahan | Dampak |
|---|---|---|---|---|
| C-01 | `Produk.php` | Tidak mengimplementasikan `SoftDeletes` sementara tabel anak memiliki `cascadeOnDelete`. | 🔴 CRITICAL | Penghapusan produk di database akan menghancurkan baris `stok_log`, merusak asas audit trail immutable. |
| C-02 | `PesananOnlineIndex.php:577-585` | Pembatalan pesanan online memasukkan nama kolom yang salah pada `StokLog` dan tidak memicu `StockMutationLog`. | 🔴 CRITICAL | Nilai mutasi dibuang oleh fillable Eloquent, SOT mutasi omnichannel tidak sinkron dengan fisik. |
| C-03 | `ReturnPembelianService.php:492` | Penolakan retur oleh supplier mengembalikan stok via kenaikan manual `StokItem` tanpa `StokLog` & `StockMutationLog`. | 🔴 CRITICAL | Stok bertambah secara misterius tanpa jejak kartu stok dan audit log. |
| C-04 | `StokDeductionService.php:111` | Reservasi stok level cabang dikurangkan langsung ke masing-masing saldo gudang fisik. | 🔴 CRITICAL | Jika satu cabang punya 3 gudang, reservasi 5 pcs dipotong 5 pcs dari Gudang A, 5 dari B, dan 5 dari C (multiplikasi palsu). |
| C-05 | `StockReservationService.php:95` | `lockForUpdate()` pada baris `StokItem` yang belum pernah dibuat mengunci 0 baris. | 🟡 HIGH | Checkout bersamaan pada item stok awal lolos validasi tanpa proteksi race condition. |
| C-06 | `StokLog.php:18` & `StockMutationLog.php:14` | Relasi `referensi(): MorphTo` belum didefinisikan secara eksplisit di model. | 🟡 HIGH | Panggilan eager-loading `$log->referensi` gagal atau melempar BadMethodCallException. |
| C-07 | `stok_log` migration | Kolom polimorfik `(referensi_tipe, referensi_id)` belum memiliki indeks gabungan. | 🟢 MEDIUM | Query pelacakan histori mutasi per dokumen transaksi/PO lambat saat log membesar. |
| C-08 | `Produk.php` & `SkuVariant.php` | Kolom `barcode`, `harga_beli`, dan `harga_jual_retail` terduplikasi di kedua model. | 🟢 MEDIUM | Rentan terjadi desinkronisasi harga jika diupdate di luar `ProdukService`. |

---

### Kategori D: Servis, Omnichannel & Alur Workflow
| ID | Lokasi File | Masalah | Tingkat Keparahan | Dampak |
|---|---|---|---|---|
| D-01 | `ApprovalService.php:193-355` | `selesaikanEntity()` tidak memiliki blok penanganan untuk `entity_type === 'po'`. | 🔴 CRITICAL | Purchase Order yang disetujui atau ditolak di Approval Inbox tidak pernah berubah statusnya di WMS. |
| D-02 | `ChannelSyncService.php:381-397` | Kegagalan pemotongan stok pada order Shopee ditelan dengan `Log::warning()`. Transaksi tetap `selesai`. | 🔴 CRITICAL | Oversell tidak terdeteksi: barang tercatat terjual dan jurnal persediaan dipotong tanpa pemotongan stok fisik. |
| D-03 | `ChannelSyncService.php:270` | Pengecekan order masuk Shopee tidak menggunakan `lockForUpdate()`. | 🟡 HIGH | Webhook paralel Shopee memicu crash duplikasi entri nomor order. |
| D-04 | `OmnichannelController.php:264` | Verifikasi signature webhook Shopee di-bypass jika `partner_key` belum diisi. | 🟡 HIGH | Pihak ketiga dapat mengirimkan request palsu ke webhook tanpa otentikasi signature jika konfigurasi kosong. |
| D-05 | `routes/api.php:130` | Route `DELETE /api/wms/produk/{id}` tidak memiliki middleware permission Spatie. | 🟡 HIGH | Setiap user terotentikasi dapat menghapus master produk tanpa permission `wms.delete`. |
| D-06 | `ServisService.php:405` | Transisi status servis langsung dari `diagnosa` ke `menunggu_approval` via `updateStatus` lolos tanpa item estimasi. | 🟢 MEDIUM | Tiket servis masuk ke antrian approval dengan estimasi biaya Rp 0. |

---

## 4. Rencana Kerja & Rincian Solusi Teknis (Technical Specifications)

### Modul 1: Mesin Akuntansi & Laporan Finansial (Akunting Core)
1. **Perbaikan Agregasi Laba Rugi (`ExportLaporanService.php`)**:
   - Filter jurnal hanya pada akun bertipe `pendapatan` (kredit - debit) dan `beban` (debit - kredit).
   - Keluarkan seluruh akun Neraca (Kas, Piutang, Persediaan, Utang, Modal) dari perhitungan laba rugi.
2. **Perbaikan Agregasi Arus Kas (`ExportLaporanService.php`)**:
   - Agregasikan seluruh akun kas dan setara kas (`kelompok === 'kas'` atau kode `110-01`, `110-02`, `110-04`).
3. **Penyempurnaan `ValidasiBarisJurnal.php`**:
   - Cabut restriksi arah debit/kredit kaku pada jurnal umum manual agar transaksi penyesuaian double-entry standar dapat diposting.
4. **Standarisasi Penomoran Jurnal Multi-Cabang**:
   - Update `PayrollService`, `ReturnPenjualanService`, `ReturnPembelianService`, `ImportProdukService`, dan `ImportSidRetailService` untuk selalu memanggil `JurnalService::generateNoJurnal($sumber, $cabangId)`.
   - Perbaiki typo `jurnal_headers` -> `jurnal_header` di `GrnService.php`.
5. **Jurnal Transfer Antar-Cabang**:
   - Tambahkan entri akuntansi saat transfer antar-cabang diterima (`terimaTransfer`):
     - Di Cabang Asal: Kredit Persediaan `130-01`, Debit Rekening Antar Cabang (RAK) / Persediaan Transit.
     - Di Cabang Tujuan: Debit Persediaan `130-01`, Kredit Rekening Antar Cabang (RAK).

---

### Modul 2: Transaksi Online & Integrasi Gateway (Marketplace Core)
1. **Pencegahan Race Condition Nomor Pesanan & Invoice**:
   - Gunakan nomor transaksi berbasis sequence terkunci pesimistik (`TransaksiSequenceService`) pada `OrderService::generateNoTransaksi()`.
2. **Kalkulasi Ongkir & Total Tagihan End-to-End**:
   - Pastikan `OrderService::buatOrder()` menghitung: `total_akhir = subtotal - diskon + pajak + ongkir`.
   - Update `DuitkuPaymentProcessor` untuk memposting jurnal:
     - Debit: `120-02` (Piutang Clearing Gateway Duitku) sebesar total transfer.
     - Debit: `520-06` (Beban Admin Marketplace / MDR) sebesar fee Duitku.
     - Kredit: `410-01` (Pendapatan Penjualan) sebesar DPP barang.
     - Kredit: `210-xx` / `410-xx` (Pendapatan/Titipan Ongkir Kurir) sebesar nilai ongkir.
     - Kredit: Akun Pajak Keluaran (PPN) jika berlaku.
     - Debit: `510-02` (HPP) & Kredit `130-01` (Persediaan).
3. **Keamanan & Idempotensi Duitku**:
   - Samakan verifikasi merchant code dengan database gateway config (`$this->duitku->getMerchantCode()`).
   - Perbaiki signature MD5 menggunakan `merchantKey`.
   - Tangani pembayaran order kedaluwarsa: tandai status `perlu_tindakan_manual`, buat jurnal titipan konsumen (`210-xx`), dan kirim notifikasi ke admin toko.
4. **Pencegahan Pembajakan Status QRIS**:
   - Kunci verifikasi status QRIS berdasarkan `order_id` / `invoice_ref` milik user yang terotentikasi, bukan nominal semata.

---

### Modul 3: Transaksi Kasir POS & Keamanan Data (POS Core)
1. **Eliminasi Duplikasi Audit Log**:
   - Hapus pemanggilan manual `catatPenjualan` di `PosKasir.php:2669` karena sudah ditangani secara tersentral di `PosCheckoutService::checkout()`.
2. **Pembersihan Data Sensitif dari State Publik Livewire**:
   - Hapus `harga_beli` dari struktur `$cart` publik di `PosKasir.php`. Simpan HPP hanya di sisi server saat transaksi diproses.
3. **Penyempurnaan Scoping Cabang Strict**:
   - Ubah `when($cabangId, ...)` menjadi `where('cabang_id', $cabangId)` ketat pada query detail transaksi dan daftar tahan transaksi.
4. **Validasi Kasbon (Piutang)**:
   - Wajibkan pemilihan data pelanggan jika metode pembayaran yang dipilih adalah `piutang`.

---

### Modul 4: Master Data Produk, Stok & Integritas SOT (WMS Core)
1. **Perlindungan Audit Trail Master Produk**:
   - Tambahkan trait `SoftDeletes` pada model `Produk` dan tabel migrasi. Cegah hard delete yang menghancurkan relasi foreign key `stok_log`.
2. **Perbaikan Pembatalan Pesanan Online & Return**:
   - Gunakan `StokDeductionService::kembalikan()` saat pesanan online dibatalkan di `PesananOnlineIndex.php`.
   - Gunakan `StokDeductionService::mutasi()` saat barang retur pembelian ditolak vendor di `ReturnPembelianService.php`.
3. **Koreksi Multi-Warehouse Reservation**:
   - Perbaiki `StokDeductionService.php` agar pengurangan reservasi diperhitungkan terhadap total stok cabang, bukan dikurangkan penuh pada masing-masing gudang secara terpisah.
4. **Relasi Polimorfik & Pengindeksan Log**:
   - Tambahkan `public function referensi(): MorphTo` pada `StokLog` dan `StockMutationLog`.
   - Tambahkan indeks komposit `(referensi_tipe, referensi_id)` pada migrasi tabel `stok_log`.

---

### Modul 5: Integrasi Servis, Omnichannel, Workflow & RBAC
1. **Eksekusi Approval Purchase Order di Workflow**:
   - Tambahkan penanganan `case 'po':` pada `ApprovalService::selesaikanEntity()` untuk mengupdate status PO menjadi `disetujui` atau `ditolak`.
2. **Penanganan Fail-Closed Order Omnichannel**:
   - Jangan tandai order Shopee sebagai `selesai` jika stok fisik tidak mencukupi. Buat antrian insiden inventaris (`ChannelOrder` status `stok_kurang`) dan batalkan posting jurnal persediaan otomatis.
   - Tambahkan `lockForUpdate()` pada pencarian order di `ChannelSyncService::prosesOrderMasuk()`.
   - Tolak webhook Shopee (HTTP 401) jika `partner_key` belum dikonfigurasi.
3. **Perlindungan Hak Akses Route (RBAC)**:
   - Tambahkan `middleware('permission:wms.delete')` pada `DELETE /api/wms/produk/{id}`.
   - Tambahkan middleware `permission:approve-workflow` pada `app/Modules/Workflow/routes.php`.

---

## 5. Rencana Pelaksanaan & Checklist Verifikasi (Acceptance Criteria)

Sesuai aturan kerja, setiap task wajib diverifikasi dengan pengujian unit/feature sebelum checkbox dicentang.

### Fase 1: Perbaikan Akuntansi, Jurnal & Laporan Keuangan
- [x] T-01 — Perbaiki agregasi Laba Rugi di `ExportLaporanService.php` agar hanya menghitung akun pendapatan & beban.
- [x] T-02 — Perbaiki agregasi Arus Kas di `ExportLaporanService.php` agar mencakup seluruh akun kas & bank (110-01, 110-02, 110-04).
- [x] T-03 — Perbaiki `ValidasiBarisJurnal.php` untuk mengizinkan mutasi normal debit/kredit pada jurnal penyesuaian manual.
- [x] T-04 — Standarisasi pemanggilan `generateNoJurnal` dengan `cabang_id` di Payroll, Return Penjualan, Return Pembelian, dan Import Produk.
- [x] T-05 — Perbaiki typo tabel `jurnal_headers` di `GrnService.php`.
- [x] T-06 — Implementasi jurnal akuntansi transfer persediaan antar-cabang di `TransferTab.php`.
- [x] T-07 — Perbaiki mapping akun utang komisi `210-03` di `PembayaranSubledgerService.php`.
- [x] T-08 — Tambahkan saving hook pada model `Utang` untuk kalkulasi otomatis kolom `sisa`.

### Fase 2: Perbaikan Transaksi Online, Gateway & Kurir
- [x] T-09 — Pasang sequence generator pesimistik `lockForUpdate` untuk pembuatan `no_transaksi` di `OrderService.php`.
- [x] T-10 — Masukkan komponen ongkir ke dalam `transaksi.total_akhir` dan integrasikan ke jurnal Duitku di `OrderService` & `DuitkuPaymentProcessor`.
- [x] T-11 — Perbaiki verifikasi merchant code dan signature MD5 Duitku di `PaymentController` & `DuitkuService`.
- [x] T-12 — Pasang penanganan pembayaran order kadaluarsa (safety holding account) di `DuitkuPaymentProcessor`.
- [x] T-13 — Amankan endpoint `checkStatus` QRIS dengan otentikasi user & pengikatan invoice reference unik.
- [x] T-14 — Perbaiki kalkulasi reservasi multi-gudang pada `StokDeductionService.php`.

### Fase 3: Optimasi Transaksi POS Kasir
- [ ] T-15 — Hapus pemanggilan ganda audit log transaksi kasir di `PosKasir.php`.
- [ ] T-16 — Hapus data `harga_beli` dari array `$cart` public Livewire `PosKasir.php`.
- [ ] T-17 — Terapkan filter `where('cabang_id', ...)` ketat pada `lihatDetailTransaksi` dan `ManagesHoldTransactions`.
- [ ] T-18 — Tambahkan validasi wajib pilih pelanggan pada transaksi kasbon/piutang di kasir.

### Fase 4: Integritas Master Data Produk & SOT Stok WMS
- [ ] T-19 — Tambahkan `SoftDeletes` pada model `Produk` dan migrasi tabel produk.
- [ ] T-20 — Perbaiki pemulihan stok saat pembatalan order online di `PesananOnlineIndex.php` via `StokDeductionService::kembalikan()`.
- [ ] T-21 — Pasang `StokDeductionService::mutasi()` pada alur penolakan retur vendor di `ReturnPembelianService.php`.
- [ ] T-22 — Tambahkan relasi `referensi(): MorphTo` pada `StokLog` dan `StockMutationLog` beserta indeks migrasi `(referensi_tipe, referensi_id)`.

### Fase 5: Servis, Omnichannel, Workflow & RBAC Security
- [ ] T-23 — Tambahkan handler eksekusi permohonan `entity_type === 'po'` di `ApprovalService::selesaikanEntity()`.
- [ ] T-24 — Jadikan pemotongan stok omnichannel fail-closed (cegah transaksi `selesai` jika stok kurang) di `ChannelSyncService.php`.
- [ ] T-25 — Pasang `lockForUpdate` pada ingestion order channel di `ChannelSyncService.php`.
- [ ] T-26 — Kunci verifikasi signature webhook Shopee (wajib tolak jika key kosong) di `OmnichannelController.php`.
- [ ] T-27 — Pasang middleware permission `wms.delete` pada route `DELETE /api/wms/produk/{id}` dan guard permission pada route Workflow.

---

## 6. Strategi Mitigasi Risiko & Rollout

1. **Database Migrations Non-Destruktif**:
   - Penambahan kolom `deleted_at` pada tabel `produk` dan penambahan indeks pada tabel `stok_log` dijalankan tanpa drop kolom/tabel yang ada.
2. **Backward Compatibility Transaksi Berjalan**:
   - Order yang sedang berstatus `menunggu_pembayaran` tetap diselesaikan dengan logika baru; jurnal lama tidak diubah surut (no retroactive mutations).
3. **Pengujian Terisolasi**:
   - Seluruh perbaikan wajib diuji melalui suite test PHPUnit di lingkungan `:memory:` sebelum rilis ke branch utama.
4. **Monitoring Log & Alerting**:
   - Supervisor queue worker dipantau untuk memastikan job `PrintThermalJob`, `PushStockToChannelJob`, dan `ProcessChannelOrderJob` berjalan tanpa retry berlebih.
