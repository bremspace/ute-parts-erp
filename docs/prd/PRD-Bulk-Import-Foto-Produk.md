# PRD: Bulk Import & Auto-Mapping Foto Produk Berdasarkan SKU (WMS & Master Katalog)
**Versi:** 1.0  
**Tanggal:** 25 Oktober 2026  
**Status:** Approved for Implementation  
**Modul Utama:** WMS (Warehouse Management System) & Master Katalog  
**Target Lingkungan:** Standalone VPS (Direct Host, Non-CyberPanel), RAM 2GB (dengan safeguard memori ketat kompatibel fallback 1GB RAM), PHP 8.3+, Laravel 13, MySQL  

---

## 1. Executive Summary & Problem Statement

### 1.1 Masalah Saat Ini
1. **Pemindahan Foto Manual Sangat Lambat**: Tim gudang/katalog memiliki ribuan foto produk di komputer lokal (hasil foto kamera atau download supplier). Saat ini, pengunggahan harus dilakukan satu per satu melalui modal edit produk di WMS.
2. **Keterbatasan Bandwidth & Disk Storage**: Foto mentah berukuran 2MB–10MB per file. Jika diunggah langsung tanpa kompresi otomatis:
   - Storage server cepat penuh dan beban I/O memori meningkat.
   - Halaman POS Kasir dan Marketplace menjadi lambat dimuat.
3. **Risiko Salah Asosiasi Foto**: Memasukkan foto manual rentan tertukar antar SKU/varian part yang bentuk fisiknya mirip (misal: LCD iPhone 13 vs iPhone 13 Pro).
4. **Kebutuhan Akses Global**: Foto harus dapat dimuat dengan cepat di seluruh modul internal (WMS, POS Kasir, Tiket Servis) dan eksternal (Marketplace Storefront publik) dengan URL yang konsisten dan adaptif protokol (HTTP/HTTPS).

### 1.2 Solusi
Membangun fitur **Import Foto Produk Bulk Folder**:
- **Folder / Multi-File / ZIP Upload**: Pengguna dapat drag-and-drop satu folder penuh langsung dari PC lokal atau mengunggah berkas arsip `.zip`.
- **Auto-Scanning & Smart SKU Mapping**: Sistem membaca nama file dan folder untuk mengekstrak SKU secara otomatis (case-insensitive, toleransi delimiter spasi/dash/underscore).
- **Graceful Skip & Reporting**: Jika SKU tidak ditemukan di database, file tidak membatalkan proses; sistem melewatinya (skip) dan menyajikan laporan interaktif (bisa diunduh Excel/CSV) yang mencantumkan nama file, SKU yang dicari, dan alasan.
- **Ultra-Efficient Compression Engine**: Konversi semua gambar ke format modern **WebP** dengan auto-resize (Full max 1200x1200px @ 80-82% quality, Thumbnail 250x250px @ 75% quality), menghemat ruang simpan hingga 70% tanpa degradasi kualitas visual.
- **Seamless System-Wide Synchronization**: Foto langsung terikat ke kolom `gambar` (cover) dan JSON `foto` (galeri) pada tabel `produks`, otomatis tampil seketika di WMS, Kasir POS, modul Servis, dan Marketplace.

---

## 2. Aturan Bisnis & Logika Pemetaan (Business Rules)

### 2.1 Pola Deteksi & Pemetaan SKU dari Nama File
Sistem mendukung berbagai konvensi penamaan file foto lokal:

| Pola File Lokal | Contoh Nama File | SKU Terdeteksi | Urutan / Tipe |
|---|---|---|---|
| **Exact SKU** | `LCD-IP13-ORI.jpg` | `LCD-IP13-ORI` | Foto Utama (Cover) |
| **SKU + Urutan Underscore** | `BAT-IP11-5000_1.png` | `BAT-IP11-5000` | Foto Urutan 1 (Primary) |
| **SKU + Urutan Dash** | `BAT-IP11-5000-2.jpeg` | `BAT-IP11-5000` | Foto Urutan 2 (Galeri) |
| **SKU + Format Kurung** | `FLX-CHG-SAM-A52 (1).webp`| `FLX-CHG-SAM-A52` | Foto Urutan 1 |
| **Struktur Subfolder** | `LCD-IP13-ORI/tampak-depan.jpg`| `LCD-IP13-ORI` | Foto Galeri |
| **Suffix Khusus** | `SPK-IPX-main.jpg` | `SPK-IPX` | Diprioritaskan sebagai Cover |

> **Normalisasi Algoritma Penamaan:**
> 1. Bersihkan ekstensi file (`.jpg`, `.jpeg`, `.png`, `.webp`, `.bmp`).
> 2. Deteksi subfolder jika diunggah via folder: jika nama folder induk cocok dengan SKU di DB, gunakan nama folder sebagai SKU.
> 3. Hapus suffix urutan regex: `/[_\-\s]+(main|cover|\d+|thumb|\(\d+\))$/i`.
> 4. Trim spasi dan samakan case (case-insensitive query: `LOWER(sku) = LOWER(?)`).

### 2.2 Aturan Resolusi Terhadap Data Eksisting (Opsi Pengguna)
Sebelum pemrosesan dimulai, user dapat memilih salah satu dari 3 strategi:
1. **Tambahkan ke Galeri (Append - Default)**:
   - Jika produk sudah memiliki foto, foto baru ditambahkan ke galeri array `foto`.
   - Foto pertama yang diunggah otomatis dijadikan `gambar` utama jika produk sebelumnya belum memiliki cover.
2. **Timpa Seluruh Foto Lama (Replace)**:
   - Foto lama pada produk dihapus fisiknya dari disk (`ProductImageService::hapusFoto`).
   - Diganti sepenuhnya dengan foto-foto baru yang ada di batch upload.
3. **Isi Hanya Jika Belum Punya Foto (Skip If Has Photo)**:
   - Jika produk sudah memiliki gambar/foto di sistem, file dilewati agar foto kurasi lama tidak tertimpa.

### 2.3 Aturan File Tanpa SKU (Skip & Report)
- File yang SKU-nya tidak ditemukan pada tabel `produks` (atau produk terhapus/soft-deleted) **tidak boleh menghentikan proses (fail-safe)**.
- File tersebut dicatat ke log in-memory/tabel temporary dengan status `skipped`.
- Alasan pencatatan:
  - `SKU_NOT_FOUND`: SKU hasil parse tidak terdaftar di master produk.
  - `INVALID_IMAGE`: File rusak atau bukan format gambar yang didukung GD.
  - `FILE_TOO_LARGE`: Ukuran file mentah melampaui batas upload (maks 15MB).
- Pengguna dapat mengunduh daftar file yang di-skip dalam format Excel (`.xlsx`) lengkap dengan kolom: Nama File Asli, SKU Hasil Tebakan, Status, dan Rekomendasi Solusi.

---

## 3. Spesifikasi Arsitektur & Penyimpanan (Storage & Compression)

### 3.1 Penyimpanan Fisik vs URL Database
- **Lokasi Fisik**: Disimpan di direktori privat/publik server via disk `public`:
  `storage/app/public/produk/{Y}/{m}/{hash_24_char}.webp`  
  `storage/app/public/produk/{Y}/{m}/{hash_24_char}_thumb.webp`
- **Tautan Simbolik**: Terhubung ke web server melalui `public/storage`.
- **Nilai di Database (`produks`)**:
  - Kolom `gambar`: string path relatif fleksibel (contoh: `/storage/produk/2026/10/a1b2c3d4e5f6.webp`).
  - Kolom `foto`: JSON array terstruktur:
    ```json
    [
      {
        "url": "/storage/produk/2026/10/a1b2c3d4e5f6.webp",
        "thumb": "/storage/produk/2026/10/a1b2c3d4e5f6_thumb.webp",
        "path": "produk/2026/10/a1b2c3d4e5f6.webp",
        "thumb_path": "produk/2026/10/a1b2c3d4e5f6_thumb.webp",
        "size": 84210,
        "thumb_size": 14200,
        "is_primary": true
      }
    ]
    ```
- **Aksesibilitas Multi-Device & Multi-Domain**:
  - Model `Produk::getGambarUrlAttribute()` dan `Produk::getGaleriFotoAttribute()` otomatis mendeteksi domain server saat ini (menggunakan `request()->getSchemeAndHttpHost()`), sehingga gambar selalu tampil valid saat dibuka dari localhost, IP server staging, maupun domain produksi HTTPS.

### 3.2 Spesifikasi Kompresi Gambar
Memanfaatkan ekstensi PHP GD yang terpasang pada OpenLiteSpeed PHP 8.3:
1. **EXIF Auto-Orientation**: Menghindari foto miring/terbalik hasil bidikan kamera smartphone.
2. **Dimensi Full Image**:
   - Max Width: 1200px | Max Height: 1200px (Aspect ratio terjaga proporsional, tanpa stretching).
   - Format: WebP, Quality: 82.
3. **Dukungan Format Gambar Universal & Cerdas**:
   - Engine decoding dual-tier: PHP GD + Imagick (`php8.4-imagick`).
   - Format didukung penuh: JPG, JPEG, PNG, WebP, GIF, BMP, AVIF, HEIC/HEIF (kamera Apple iOS), TIFF/TIF, PSD, SVG, TGA, dan file RAW kamera (DNG, CR2, NEF, ARW).
   - Validasi cerdas: pemeriksaan ekstensi + inspeksi biner/MIME header otomatis (`isImageFile`).
4. **Dimensi Full Image**:
   - Max Width: 1200px | Max Height: 1200px (Aspect ratio terjaga proporsional, tanpa stretching).
   - Format output: WebP, Quality: 82.
5. **Dimensi Thumbnail Image**:
   - Max Width: 250px | Max Height: 250px (Optimal untuk rendering tabel WMS dan grid POS).
   - Format output: WebP, Quality: 75.
6. **Alokasi Memori Server (Target VPS 2GB, Proteksi Fallback 1GB RAM)**:
   - Menggunakan pemrosesan batch bertahap (batch size 5–10 foto per request AJAX/Livewire chunk).
   - Memanggil `imagedestroy()` segera setelah stream selesai dan `gc_collect_cycles()` berkala untuk mencegah Out of Memory (OOM) meskipun dijalankan di server dengan spesifikasi rendah (1GB RAM).

---

## 4. UI/UX Specification (Design System "Ute Prism")

Fitur ditempatkan di halaman: **`/app/wms/import-export?tab=foto`** dan dapat diakses cepat dari tombol header di katalog master produk WMS (`/app/wms`).

### 4.1 Flow Interaksi Pengguna (4-Step Wizard)

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  1. PILIH FOTO  │ ──> │ 2. SCAN & MATCH │ ──> │ 3. PROSES & SYNC│ ──> │   4. SELESAI    │
│ Drag Folder/ZIP │     │ Preview Pemetaan│     │ Kompresi WebP   │     │ Ringkasan & Log │
│ Opsi Timpa/Add  │     │ Cek SKU Valid   │     │ Progress Bar    │     │ Unduh Excel Skip│
└─────────────────┘     └─────────────────┘     └─────────────────┘     └─────────────────┘
```

#### Step 1: Upload Box & Pengaturan
- **Dropzone Interaktif**:
  - Pilihan A: **Folder Picker (`webkitdirectory`)** — User memilih satu folder dari komputernya.
  - Pilihan B: **ZIP Archive Upload** — User mengunggah file `.zip` (ekstraksi streaming di server).
  - Pilihan C: **Multi-File Select** — User memilih puluhan/ratusan file gambar sekaligus (`.jpg`, `.jpeg`, `.png`, `.webp`).
- **Opsi Konfigurasi**:
  - Mode Penggabungan: `Tambahkan ke galeri (Append)` [Default] | `Ganti foto lama (Replace)` | `Hanya produk tanpa foto`.
  - Penanda Foto Utama: `Otomatis urutan pertama` | `Gunakan file berakhiran _1 atau -main`.

#### Step 2: Pre-Scan & Validasi Pemetaan (Interactive Review)
- Sebelum foto diproses secara permanen, sistem menyajikan statistik kilat:
  - Total File Terbaca: misal `150 foto`.
  - SKU Ditemukan & Cocok: misal `132 produk` (Badge Hijau).
  - SKU Tidak Ditemukan: misal `18 file` (Badge Kuning/Merah).
- **Tabel Preview Interaktif**:
  - Kolom: Thumbnail Kecil, Nama File Asli, SKU Terdeteksi, Nama Produk di Sistem, Status (Siap Sync / Dilewati).
  - Filter tombol: `Semua (150)`, `Cocok (132)`, `Dilewati (18)`.
- Tombol: `Batalkan` atau `Mulai Proses Kompresi & Simpan (132 Foto)`.

#### Step 3: Proses Eksekusi & Progress Real-Time
- Tampilan Progress Bar dinamis (0% s/d 100%) dengan angka realtime:
  - *“Memproses file 45 dari 132... (34%)”*
  - Indikator hemat storage: *“Estimasi hemat ruang: ~68% (Konversi ke WebP)”*.
  - Tidak membebani browser, responsif di mobile maupun desktop.

#### Step 4: Ringkasan & Download Log
- Kartu Sukses dengan statistik akhir:
  - Jumlah Foto Sukses Dikompresi & Disimpan.
  - Jumlah Produk yang Diperbarui.
  - Jumlah File yang Dilewati.
- **Tombol Aksi**:
  - `📥 Unduh Laporan File Dilewati (.xlsx)` (Jika ada file skip).
  - `📦 Lihat Katalog Produk WMS` (Redirect ke `/app/wms`).
  - `🔄 Upload Folder Lain`.

---

## 5. Integrasi & Sinkronisasi Seluruh Modul

| Modul Sistem | Titik Integrasi | Dampak & Tampilan |
|---|---|---|
| **WMS Master Produk** | `ProdukTab.php`, `DataTable` | Foto cover dan galeri langsung ter-render di thumbnail tabel katalog, modal edit produk, dan cetak barcode label. |
| **POS Kasir** | `PosScreen.php` | Produk di kasir langsung menampilkan thumbnail WebP ringan (250x250), mempercepat pencarian barang saat transaksi. |
| **Modul Servis** | `ServisSparepartPicker.php` | Teknisi dapat melihat foto fisik sparepart saat memilih komponen pengganti pada tiket servis. |
| **Marketplace Publik** | `/shop`, `/p/{slug}`, Keranjang | Gambar produk di etalase toko online langsung tersinkronisasi tanpa jeda, mendukung mode lazy-load WebP. |
| **Laporan & Export** | `ExportMasterProdukService` | URL foto WebP otomatis disertakan pada kolom `foto_url` saat ekspor Excel master produk. |

---

## 6. Struktur Teknis & Kontrak Komponen

### 6.1 Service Backend
1. **`App\Modules\Wms\Services\BulkProductImageImportService`**:
   - `parseSkuFromFilename(string $filename, ?string $subfolder = null): string`
   - `scanFiles(array $files): array` (Validasi SKU, return mapped & unmapped list)
   - `processSingleFile(UploadedFile|string $file, int $produkId, string $mode = 'append', bool $isPrimary = false): array`
   - `exportSkippedFilesExcel(array $skippedItems): BinaryFileResponse`

2. **Peningkatan pada `ProductImageService`**:
   - Memastikan batas dimensi full image ditingkatkan ke 1200x1200px untuk ketajaman katalog marketplace.
   - Pengecekan efisiensi memori (`imagedestroy` wajib di blok `finally`).

### 6.2 Livewire Component & View
- **File**: `App\Modules\Wms\Livewire\BulkFotoProdukPage.php` (atau terintegrasi sebagai Tab di `ImportExportPage.php`).
- **Blade**: `resources/views/modules/wms/livewire/bulk-foto-produk-tab.blade.php`.
- **Route**: `GET /app/wms/import-export?tab=foto` (Nama route: `wms.import-foto`).

---

## 7. Penanganan Kasus Tepi (Edge Cases & Resilience)

1. **Nama File Mengandung Spasi & Karakter Non-Alfanumerik**:
   - Contoh: `LCD - IP13 _ ORI (2).jpg` -> dibersihkan menjadi `LCD-IP13_ORI` atau `LCD-IP13-ORI`.
2. **Koneksi Terputus di Tengah Upload**:
   - Setiap batch yang sudah berhasil tersimpan di-commit langsung ke database (`DB::transaction` per produk), sehingga jika jaringan putus, foto yang sudah diproses tidak hilang atau duplikat.
3. **File Non-Gambar Terselip di Folder**:
   - Jika di dalam folder ada file `.DS_Store`, `Thumbs.db`, `.txt`, atau `.pdf`, sistem otomatis memfilter dan mengabaikannya tanpa memunculkan error crash.
4. **Batas Ukuran Upload PHP (upload_max_filesize / post_max_size)**:
   - Pengunggahan folder via browser dilakukan secara chunked (per 10-20 file) melalui JavaScript/Livewire request terpisah untuk menghindari limit PHP `post_max_size` (biasanya 50M/100M).

---

## 8. Rencana Pengujian (Testing Matrix)

- **Unit Test (`tests/Unit/BulkProductImageImportTest.php`)**:
  - Test regex ekstraksi SKU untuk berbagai format nama file.
  - Test penanganan file tanpa SKU yang valid (verifikasi flag skipped).
  - Test konversi WebP dan ukuran thumbnail.
- **Feature Test (`tests/Feature/BulkFotoProdukFeatureTest.php`)**:
  - Test upload batch file foto terhubung ke database SQLite/MySQL.
  - Test mode `append` vs `replace` terhadap relasi foto produk.
  - Test otorisasi akses (hanya staff dengan permission `wms.produk.create`/`edit`).
- **Verifikasi UI Browser**:
  - Pengujian drag & drop folder 50+ foto di browser Chrome / Firefox / Safari.
  - Verifikasi tampilan foto di POS Kasir dan Marketplace storefront.

---

## 9. Checklist Implementasi (Implementation Plan)

- [ ] **T-01**: Buat engine ekstraksi SKU & service bulk image import (`BulkProductImageImportService.php`).
- [ ] **T-02**: Tambahkan tab "Import Foto Massal" di workspace `/app/wms/import-export`.
- [ ] **T-03**: Bangun antarmuka upload folder (`webkitdirectory`), drag-and-drop, dan arsip ZIP dengan styling Ute Prism.
- [ ] **T-04**: Implementasikan tahap pre-scan & mapping review (statistik SKU ditemukan vs di-skip).
- [ ] **T-05**: Implementasikan eksekusi batch upload & kompresi WebP (full 1200px + thumb 250px).
- [ ] **T-06**: Buat fitur ekspor Excel untuk daftar file yang di-skip (tidak ditemukan SKU).
- [ ] **T-07**: Pastikan sinkronisasi instan ke WMS Katalog, Kasir POS, dan Marketplace.
- [ ] **T-08**: Tulis Automated Unit & Feature Test untuk validasi logika SKU dan import.
