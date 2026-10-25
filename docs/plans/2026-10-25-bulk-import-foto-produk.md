# Implementation Plan: Bulk Import & Auto-Mapping Foto Produk Berdasarkan SKU

**Tanggal Mulai:** 25 Oktober 2026  
**PRD Referensi:** `docs/prd/PRD-Bulk-Import-Foto-Produk.md`  
**Target:** Standalone VPS 2GB RAM (Safeguard 1GB RAM memory-friendly), Laravel 13, TALL Stack  

---

## 1. Breakdown Tasks & Acceptance Criteria

### T-01 — Engine Ekstraksi SKU & BulkProductImageImportService
- **Deskripsi:** Buat service `BulkProductImageImportService` yang bertugas:
  - Parsing SKU dari nama file (mendukung exact match, `_1`, `-1`, `(1)`, `-main`, dan subfolder).
  - Pre-scanning & matching dengan database `produks.sku` (case-insensitive query).
  - Eksekusi kompresi WebP (1200x1200px full @ 82% & 250x250px thumb @ 75%) menggunakan `ProductImageService`.
  - Update `produks.gambar` & `produks.foto` dengan mode: `append`, `replace`, atau `skip_existing`.
- **Acceptance Criteria:**
  - [x] T-01 — Service `BulkProductImageImportService` menangani regex SKU berbagai variasi format dan berhasil memetakan ke ID Produk.

### T-02 — Navigasi Tab Import Foto di Workspace WMS
- **Deskripsi:** Tambahkan tab ke-3 `📸 Import Foto Massal` di halaman `/app/wms/import-export` (`ImportExportPage.php` & blade), dan shortcut link dari header `/app/wms`.
- **Acceptance Criteria:**
  - [x] T-02 — Tab `📸 Import Foto Massal` dapat diakses langsung via URL `/app/wms/import-export?tab=foto` dan render tanpa error.

### T-03 — Antarmuka Upload Folder (`webkitdirectory`), Drag-and-Drop, dan ZIP
- **Deskripsi:** Rancang UI Ute Prism untuk area upload yang mendukung:
  - Input folder (HTML5 `webkitdirectory`).
  - File picker multi-gambar (`.jpg`, `.jpeg`, `.png`, `.webp`).
  - Arsip ZIP (`.zip`) dengan auto-extract streaming di server.
  - Opsi strategi: Append / Replace / Skip Existing.
- **Acceptance Criteria:**
  - [x] T-03 — UI mendukung pemilihan folder, multi-file, dan file ZIP dengan validasi format file.

### T-04 — Tahap Pre-Scan & Review Pemetaan SKU (Interactive Step)
- **Deskripsi:** Buat antarmuka dan logika validasi sebelum foto diproses:
  - Membaca daftar file/nama yang dipilih.
  - Menghitung statistik cepat: total file, SKU cocok (hijau), SKU tidak ditemukan (kuning/merah).
  - Menampilkan tabel preview mapping sebelum konfirmasi eksekusi.
- **Acceptance Criteria:**
  - [x] T-04 — Pengguna dapat melihat daftar file yang cocok dan tidak cocok sebelum proses kompresi dijalankan.

### T-05 — Eksekusi Batch Chunked & Kompresi WebP Berkala
- **Deskripsi:** Menjalankan pemrosesan foto dalam batch (chunk 5-10 foto) untuk menjaga efisiensi RAM (aman untuk VPS 1GB/2GB):
  - Konversi WebP otomatis.
  - Garbage collection & freeing GD memory (`imagedestroy`, `gc_collect_cycles`).
  - Progress bar real-time persentase pemrosesan.
- **Acceptance Criteria:**
  - [x] T-05 — Batch processing berjalan stabil tanpa OOM, foto tersimpan di `storage/app/public/produk/{Y}/{m}/` dan tercatat di DB.

### T-06 — Ekspor Laporan Excel File yang Di-Skip
- **Deskripsi:** Sediakan ekspor Excel (`.xlsx`) jika ada file yang SKU-nya tidak ditemukan atau gagal diproses, menggunakan `maatwebsite/excel`.
- **Acceptance Criteria:**
  - [x] T-06 — File yang di-skip dapat diunduh dalam format Excel berisi kolom Nama File, SKU yang dicari, dan Alasan Skip.

### T-07 — Sinkronisasi Seluruh Modul (WMS, POS, Servis, Marketplace)
- **Deskripsi:** Verifikasi bahwa hasil foto yang diimpor langsung tampil di:
  - Thumbnail katalog WMS (`/app/wms`).
  - POS Kasir (`/app/pos`).
  - Sparepart picker di tiket servis (`/app/servis`).
  - Marketplace storefront (`/shop`).
- **Acceptance Criteria:**
  - [x] T-07 — Foto produk hasil bulk import tampil sempurna di WMS, POS, Servis, dan Marketplace.

### T-08 — Automated Testing & Verifikasi Akhir
- **Deskripsi:** Tulis automated unit & feature tests:
  - `tests/Unit/BulkProductImageImportTest.php`
  - `tests/Feature/BulkFotoProdukFeatureTest.php`
  - Jalankan `php artisan test` dan `pint` formatter.
- **Acceptance Criteria:**
  - [x] T-08 — Seluruh automated tests lolos 100% dan standar PSR-12 (Pint) terpenuhi.
