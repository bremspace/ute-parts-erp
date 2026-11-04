# PRD: Audit Komprehensif, Analisis Celah & Rencana Penyempurnaan ERP Ute Parts

**Versi:** 1.0  
**Tanggal:** 2026-11-03  
**Status:** DRAFT UNTUK REVIEW  
**Peninjau:** Senior Software Architect & ERP Specialist  
**Target Sistem:** Laravel 13, TALL Stack (Livewire 3, Tailwind 4, Alpine.js), MySQL 8 / SQLite :memory:, VPS Linux 2GB RAM (Baseline Minimum)  

---

## 1. Executive Summary & Audit Scorecard

Pemeriksaan mendalam telah dilakukan terhadap seluruh 15 modul (`app/Modules/*`), perutean (`routes/web.php`, `routes/api.php`), arsitektur dependensi (`deptrac.php`), komponen UI/UX Livewire, basis data, dan batasan operasional VPS 2GB RAM.

### Ringkasan Penilaian Kepatuhan (Scorecard)

| Dimensi Audit | Skor (1–10) | Status | Ringkasan Temuan |
|---|:---:|:---:|---|
| **Keamanan & Autentikasi** | 5.5 / 10 | ⚠️ KRITIS | Ditemukan backdoor/fallback credential overwrite super-admin di `routes/web.php`. |
| **Arsitektur Modular Laravel** | 6.0 / 10 | ⚠️ PERLU REFACTOR | 15 modul tidak memiliki ServiceProvider mandiri; 88 layer violation di Deptrac; route monolitik (>900 baris). |
| **Kinerja & Resource (2GB RAM)** | 6.5 / 10 | ⚠️ PERLU EFISIENSI | Komponen Livewire raksasa (>4.000 baris) memicu beban serialisasi snapshot dan konsumsi memori tinggi. |
| **Multi-Cabang & Data Scoping** | 6.5 / 10 | ⚠️ RAWAN BOCOR | Scoping manual via `session('cabang_id')` tanpa Global Scope; rawan bocor di background queue worker. |
| **Integritas ERP (WMS & Finance)** | 7.5 / 10 | 🟢 BAIK (DENGAN CATATAN) | Double-entry journal & lockForUpdate solid; valuasi HPP produk masih global & belum fully branch-isolated. |
| **HR & Payroll** | 6.5 / 10 | ⚠️ ANOMALI | Tabel `payroll_periode` tidak memiliki kolom `cabang_id`; memproses karyawan multi-cabang dalam 1 batch. |
| **Integrasi & Webhook** | 7.0 / 10 | 🟡 CUKUP | Webhook Duitku idempotent; webhook Shopee/Omnichannel masih dieksekusi sinkron di request lifecycle. |
| **UI/UX & Design System Prism** | 7.5 / 10 | 🟢 KONSISTEN | Token dasar terpasang; direktori view terpecah (`resources/views/modules` vs `resources/views/wms`). |

---

## 2. Temuan Celah & Anomali Mendalam (Deep-Dive Gaps)

### [GAP-SEC-01] Backdoor Override Password Superadmin di `routes/web.php` (CRITICAL)
- **Lokasi:** `routes/web.php:486-495`
- **Kode:**
  ```php
  if (! $attemptSuccess && in_array($lowerEmail, ['superadmin', 'super-admin', ...], true)) {
      $superCandidate = User::whereIn('email', ['superadmin@uteparts.id', 'super-admin@uteparts.id'])->first();
      if ($superCandidate) {
          $allowedPasswords = ['Password123!', 'password123', 'password', 'Password123', 'admin', 'admin123', '12345678'];
          if (in_array($rawPassword, $allowedPasswords, true)) {
              $superCandidate->update(['password' => Hash::make($rawPassword)]);
          }
          $attemptSuccess = Auth::attempt(['email' => $superCandidate->email, 'password' => $rawPassword]);
      }
  }
  ```
- **Dampak:** Siapa pun yang mencoba login dengan username default dan salah satu dari 7 password umum akan langsung **mengubah password akun super-admin di database secara live** dan mendapatkan akses penuh ke ERP toko.
- **Rekomendasi:** Hapus logika bypass ini sepenuhnya. Password reset hanya melalui CLI `php artisan` atau alur forgot password terverifikasi.

---

### [GAP-ARCH-01] Absensi Module Service Providers & Route Monolitik (HIGH)
- **Lokasi:** `app/Modules/*`, `routes/web.php` (905 baris), `routes/api.php` (270 baris), `bootstrap/providers.php`
- **Temuan:**
  - Tidak ada satupun `ModuleServiceProvider` di dalam `app/Modules/` (`AkuntingServiceProvider`, `WmsServiceProvider`, dll).
  - Seluruh observer model, view composer, macro blade, dan event listener dijejalkan ke dalam satu file `app/Providers/AppServiceProvider.php`.
  - Seluruh rute backoffice dan API dideklarasikan dalam satu file besar, menyulitkan isolasi modul dan pengujian per-fitur.
- **Rekomendasi:** Terapkan standar Modular Monolith Laravel:
  - Buat `<ModuleName>ServiceProvider.php` di setiap modul untuk mendaftarkan rute, migrasi, view, dan observer masing-masing.
  - Pisahkan file routing: `app/Modules/Wms/Routes/web.php`, `app/Modules/Pos/Routes/web.php`, dll.

---

### [GAP-ARCH-02] 88 Pelanggaran Arsitektur Dependensi Antar-Modul (HIGH)
- **Lokasi:** Laporan `./vendor/bin/deptrac analyse`
- **Temuan:**
  1. **Core layer coupling:** `AkuntingController` & `AkuntingDashboard` bergantung langsung ke `Workflow\Services\ApprovalService`.
  2. **Domain cross-talk:** `PosKasir` dan `RiwayatTransaksiIndex` memanggil langsung model `TiketServis` dan `ServisService` tanpa perantara event/kontrak.
  3. **Relasi antar modul:** Model `Transaksi` (modul POS) memiliki relasi Eloquent langsung ke `TiketServis`, `Pengiriman` (Marketplace), dan `ChannelOrder` (Omnichannel).
  4. **Modul Notifikasi:** `NotificationCenter` bergantung langsung pada model `Transaksi`, `ApprovalRequest`, dan `KampanyeBroadcast`.
- **Rekomendasi:**
  - Bangun Contracts / Interfaces atau Domain Events (`TransaksiSelesaiEvent`, `TiketServisSelesaiEvent`) agar modul tidak saling import kelas konkrit internal modul lain.

---

### [GAP-ARCH-03] "God Components" Livewire & Profiling Memori VPS (HIGH)
- **Lokasi:**
  - `ProdukTab.php`: 4.686 baris
  - `PosKasir.php`: 4.402 baris
  - `SettingsRbac.php`: 3.832 baris
  - `AkuntingDashboard.php`: 2.075 baris
  - `RiwayatTransaksiIndex.php`: 1.971 baris
  - `ServisBoard.php`: 1.937 baris
- **Temuan:**
  - Livewire mengenkripsi dan mentransfer seluruh public property dalam snapshot JSON (`memo`) pada setiap network roundtrip.
  - Komponen sebesar 4.000 baris dengan puluhan property array besar memakan waktu CPU PHP dan memori tinggi saat re-hydration, rentan menembus batas alokasi memori pada server VPS 2GB RAM saat kasir dan admin aktif bersamaan.
- **Rekomendasi:**
  - Dekomposisi modular: pecah modal form, modal kas/laci, transaksi park, dan tabel data menggunakan sub-komponen / modular trait concerns.
  - Pindahkan business logic dari Livewire component ke dedicated Action/Service classes.

---

### [GAP-ERP-01] Kerentanan Multi-Cabang pada Background Queue & Fallback Hardcoded (HIGH)
- **Lokasi:** `StokDeductionService.php`, `ProdukTab.php`, `ReturnPembelianService.php`, `app/Modules/*/Jobs/*`
- **Temuan:**
  - Isolasi data multi-cabang sangat bergantung pada pembacaan manual `session('cabang_id')`.
  - Ditemukan banyak fallback berbahaya seperti:
    `$cabangId = $cabangId ?? session('cabang_id') ?? 1;`
  - **Masalah Utama:** Queue Worker (`php artisan queue:work`) **TIDAK memiliki sesi HTTP**. `session('cabang_id')` di dalam job antrean selalu `null`! Bila job memanggil service yang mengandalkan session, eksekusi akan jatuh ke cabang default `1` atau membocorkan data lintas cabang.
- **Rekomendasi:**
  - Implementasikan `BelongsToCabang` Trait dengan Global Scope yang diinjeksi via Tenant Context Manager (menyimpan active branch ID di runtime state, bukan sekadar session web).
  - Pastikan setiap Job antrean mengemas eksplisit `cabang_id` di constructor.

---

### [GAP-ERP-02] Anomali Skup Cabang pada Modul HR & Payroll (HIGH)
- **Lokasi:** `app/Modules/Hr/Models/PayrollPeriode.php`, `app/Modules/Hr/Services/PayrollService.php:48`
- **Temuan:**
  - Tabel `payroll_periode` tidak memiliki kolom `cabang_id`.
  - `PayrollService::hitungDraft()` mengambil semua karyawan aktif tanpa filter cabang:
    `$karyawans = Karyawan::where('status_aktif', true)->get();`
  - Akibatnya, pengajuan payroll periode `2026-11` menggabungkan seluruh cabang menjadi satu slip kolektif, tetapi penjurnalan akuntansi didebetkan ke cabang yang sedang aktif di sesi user yang menekan tombol.
- **Rekomendasi:**
  - Tambahkan kolom `cabang_id` pada `payroll_periode`.
  - Pisahkan komputasi draft payroll per cabang atau izinkan payroll holding terpusat dengan jurnal subledger per cabang yang terisolasi.

---

### [GAP-ERP-03] Valuasi Persediaan & Sinkronisasi Moving Average Cost (MEDIUM)
- **Lokasi:** `ProdukService.php:834`, `PosKasir.php:2568`, `OrderService.php:93`
- **Temuan:**
  - Komputasi average cost saat GRN:
    `$stokTotalSekarang = (int) StokItem::where('produk_id', $produkId)->sum('jumlah');`
    menjumlahkan stok dari seluruh gudang di seluruh cabang, padahal harga perolehan barang per cabang/wilayah bisa berbeda karena ongkos kirim antarpulau.
  - Penjualan di POS dan Marketplace mencatat HPP berdasarkan angka snapshot `$produk->harga_beli` statis, bukan batch lot FIFO aktual saat barang masuk.
- **Rekomendasi:**
  - Sediakan opsi valuasi: Moving Average per Cabang atau Global.
  - Pertahankan integritas pencatatan HPP ke kartu stok dengan mereferensikan perolehan batch barang masuk.

---

### [GAP-INT-01] Pemrosesan Sinkron Webhook Omnichannel (MEDIUM)
- **Lokasi:** `OmnichannelController.php:280-282`
- **Temuan:**
  - Ketika Shopee/marketplace mengirimkan webhook pesanan baru, controller memanggil `prosesOrderMasuk` (deduksi stok, pembuatan pesanan, penjurnalan) secara sinkron sebelum mengembalikan response HTTP.
  - Lonjakan pesanan saat flash sale atau batch sync dapat memblokir proses PHP-FPM, berisiko timeout HTTP 504 dan pemanggilan webhook berulang dari pihak marketplace.
- **Rekomendasi:**
  - Simpan payload mentah ke `webhook_deliveries` / staging table, kirim respon HTTP 200 langsung (<150ms).
  - Dispatch asynchronous job (`ProcessChannelOrderJob`) untuk pemrosesan order dan penjurnalan.

---

### [GAP-UIUX-01] Fragmentasi Struktur Direktori View & Token Inline (MEDIUM)
- **Lokasi:** `resources/views/`
- **Temuan:**
  - View terbagi tanpa konvensi seragam: ada yang di `resources/views/modules/<mod>/livewire/`, ada yang di `resources/views/<mod>/` (seperti `wms/po-print.blade.php`), dan ada yang di `resources/views/servis/tracking-publik.blade.php`.
  - Beberapa template blade masih mencampur arbitrary Tailwind classes (`bg-slate-800`, `text-indigo-500`) dan tidak konsisten menggunakan token Ute Prism (`--color-up-primary`, `glass-card`, `status-pill`).
- **Rekomendasi:**
  - Rapikan direktori view mengikuti arsitektur modular (`app/Modules/<Mod>/Views/` atau `resources/views/modules/<mod>/`).
  - Standardisasi seluruh badge, button, dan card ke komponen Ute Prism yang sudah teruji.

---

## 3. Rencana Penyempurnaan Bertahap (Phased Roadmap)

Agar proses penyempurnaan aman, terukur, dan tidak mengganggu stabilitas sistem live, pekerjaan dibagi menjadi 7 Fase:

```
[Fase 1: Keamanan & Sanitasi Backdoor]
                │
[Fase 2: Modularisasi Service Providers & Decoupling Deptrac]
                │
[Fase 3: Multi-Branch Hardening (Global Scope & Queue Context)]
                │
[Fase 4: Dekomposisi "God Components" Livewire & Profiling 2GB RAM]
                │
[Fase 5: Integritas ERP: Valuasi HPP & Payroll Multi-Cabang]
                │
[Fase 6: Resiliensi Webhook & Asynchronous Queue Processing]
                │
[Fase 7: Unifikasi UI/UX Ute Prism & Standardisasi Blade]
```

### Rincian Tiap Fase:

| Fase | Fokus Utama | Target Deliverables | Estimasi Risiko |
|---|---|---|:---:|
| **Fase 1** | **Keamanan Kritis & Hardening** | Hapus backdoor password fallback di `routes/web.php`, audit permission sanitization, tambahkan rate limiter ketat pada tracking & public booking. | Rendah (Aman) |
| **Fase 2** | **Arsitektur Modular** | Buat 15 `ModuleServiceProvider`, pindahkan route web/api ke folder modul masing-masing, kurangi 88 pelanggaran Deptrac dengan domain events/contracts. | Sedang |
| **Fase 3** | **Multi-Tenancy Cabang** | Terapkan `BelongsToCabang` Global Scope, buat `BranchContextManager` yang mendukung HTTP session sekaligus CLI/Queue context. | Sedang |
| **Fase 4** | **Optimasi Livewire (2GB RAM)** | Dekomposisi `PosKasir`, `ProdukTab`, `SettingsRbac` ke sub-concerns/actions; optimasi snapshot memo & memory footprint. | Sedang |
| **Fase 5** | **Integritas WMS & HR** | Tambahkan `cabang_id` ke `payroll_periode`, perbaiki scoping payroll, pisahkan moving average cost per cabang jika dibutuhkan. | Sedang |
| **Fase 6** | **Integrasi & Webhook Async** | Ubah penerimaan webhook Shopee/Omnichannel menjadi async via queue job + idempotent store. | Rendah |
| **Fase 7** | **Penyelarasan UI/UX Prism** | Rapikan lokasi file blade, pastikan 100% komponen memakai token Ute Prism, optimasi responsive breakpoint mobile (`xxs: 320px`). | Rendah |

---

## 4. Acceptance Criteria & Verifikasi

Untuk setiap fase yang dieksekusi nantinya:
1. `composer test` wajib lulus 100% tanpa regresi.
2. `./vendor/bin/deptrac analyse` menunjukkan penurunan pelanggaran hingga 0.
3. `./vendor/bin/pint` memastikan standar kode PSR-12 bersih.
4. Profiling memori memastikan alokasi per request efisien dan aman pada server VPS 2GB RAM.
5. Tidak ada query data lintas cabang yang bocor pada mode multi-cabang.
