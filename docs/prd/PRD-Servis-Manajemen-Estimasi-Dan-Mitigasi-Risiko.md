# PRD — Servis: Manajemen Perubahan Estimasi, Pembatalan & Mitigasi Risiko Terintegrasi

**Status:** APPROVED / IN-PROGRESS  
**Tanggal:** 2026-11-10  
**Standar Kepatuhan:** ISO 9001:2015 (§8.2.4 Perubahan Persyaratan Produk & Servis, §8.7 Kontrol Output Tidak Sesuai), ISO 27001 (Audit Trail & Immutability), Standar Enterprise TALL Stack Laravel 13.

---

## 1. Latar Belakang & Masalah
Dalam proses bisnis perbaikan perangkat (servis gadget/laptop/elektronik):
1. **Revisi Estimasi (Addendum):** Estimasi awal sering berubah saat pembongkaran fisik mendalam (hidden damage) atau suku cadang tidak lagi dibutuhkan.
2. **Pembatalan Parsial:** Pelanggan membatalkan sebagian jasa/part saat servis sedang berjalan. Sistem harus mengembalikan stok fisik dan melepas klaim Serial Number (SN) secara akurat tanpa manipulasi data manual.
3. **Pemisahan Dokumen Transaksi:**
   - **SPK / Tanda Terima Estimasi**: Diterbitkan saat unit masuk / diestimasi (status: Belum Lunas).
   - **Kwitansi Pelunasan Servis**: Diterbitkan HANYA setelah pembayaran tuntas di Kasir POS atau pelunasan online (status: Lunas, menyertakan nomor faktur kasir, garansi, kembalian).
4. **Audit Trail & Traceability:** Setiap pembatalan baris pekerjaan atau perubahan estimasi WAJIB menyertakan alasan (`alasan_pembatalan`), aktor yang mengubah, timestamp, dan tercatat di status log.

---

## 2. Ruang Lingkup & Arsitektur Solusi

### A. Alur Siklus Hidup Revisi Estimasi (State Machine)
- **Status `diagnosa` / `menunggu_approval` / `ditolak`:**
  - Teknisi/Staf dapat mengupdate estimasi (tambah/kurang baris jasa & part).
  - Status beralih ke `menunggu_approval`.
  - Sistem memperbarui token approval dan mengirimkan rincian revisi ke pelanggan (WhatsApp/Tracking portal).
- **Status Sedang Dikerjakan (`disetujui`, `dikerjakan`, `qc`):**
  - **Penambahan Part/Jasa:** Ditambahkan langsung lewat form input pekerjaan teknisi (langsung potong stok part 1x + log mutasi).
  - **Pembatalan Item Part/Jasa:** Item ditandai `dibatalkan_at = now()`, dengan `alasan_batal`. Stok part dikembalikan otomatis ke gudang asal melalui `StokDeductionService::kembalikan()`.

### B. Arsitektur Data & Model
1. Kolom audit pada tabel `tiket_servis_item`:
   - `dibatalkan_at` (timestamp, nullable) — sudah ada.
   - `alasan_batal` (string 255, nullable) — ditambahkan untuk kepatuhan ISO 9001.
   - `dibatalkan_by` (unsignedBigInteger FK users, nullable) — ditambahkan untuk audit trail.
2. Filter kalkulasi biaya (`TiketServis::getRincianBiayaLengkap`):
   - Hanya menghitung item aktif (`whereNull('dibatalkan_at')`).
   - Item batal ditampilkan pada riwayat rincian dengan garis coret dan keterangan pembatalan.

### C. Dokumen & UI Output (POS & Kanban)
1. Modal pembatalan item pada UI Detail Tiket (`ServisBoard`) yang mewajibkan input alasan pembatalan sebelum submit.
2. Pembeda cetak dokumen:
   - Tombol **Cetak SPK / Tanda Terima** (selalu tersedia selama pengerjaan).
   - Tombol **Cetak Kwitansi Pelunasan** (hanya aktif setelah status pembayaran = `lunas`).

---

## 3. Rencana Eksekusi & Checklist Acceptance Criteria

- [x] **T-01: Migrasi Database Kolom Alasan Pembatalan Item Servis**
  - Buat migration menambahkan `alasan_batal` dan `dibatalkan_by` pada `tiket_servis_item`.
  - Non-destructive, SQLite & MySQL compatible.

- [x] **T-02: Backend Service Refactor (`ServisService::batalkanItemPekerjaan`)**
  - Update method untuk menerima dan menyimpan `alasan_batal` serta user pembatal.
  - Reversal stok otomatis via `StokDeductionService::kembalikan` tetap aman dan idempoten.
  - Catat log aktivitas ke `ServisStatusLog`.

- [x] **T-03: Frontend ServisBoard (UI Modal Pembatalan Item dengan Alasan)**
  - Tambahkan modal dialog konfirmasi pembatalan item di `servis-board.blade.php` dengan input teks alasan.
  - Hubungkan Livewire action `konfirmasiBatalItem($itemId, $alasan)` di `ServisBoard.php`.

- [x] **T-04: Standarisasi Tombol Cetak Dokumen (SPK vs Kwitansi)**
  - Di detail tiket, sediakan opsi cetak SPK Estimasi dan Cetak Kwitansi Pelunasan.
  - Integrasikan flag `is_spk` vs `is_lunas` ke `thermal-receipt-modal.blade.php`.

- [x] **T-05: Verifikasi & Automated Testing**
  - Unit/Feature test memverifikasi penambahan part, pembatalan part + reversal stok + pencatatan alasan, dan cetak struk kwitansi vs SPK.
