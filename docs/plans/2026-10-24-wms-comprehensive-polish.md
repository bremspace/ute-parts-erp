# Implement Plan: WMS Frontend & Logic Optimization (13 Poin User)

**Target:** Perbaiki 13 poin kendala UI/UX, workflow logic, dark/light contrast, filter responsif, dan performa navigasi modul WMS.

## Checklist Tugas & Acceptance Criteria

- [x] **T-01 — UI Filter Master Produk Rapi Saat Semua Dicentang (Poin 1):** Grid responsif pada filter toolbar (`produk-tab.blade.php`), tidak bertumpuk/berantakan di mobile & desktop saat semua filter aktif.
- [x] **T-02 — Hilangkan Box Export di Menu Filter & Optimasi Export Terpilih di Atas (Poin 2):** Hapus box ekspor pricelist yang menempel di filter toolbar, integrasikan menu dropdown Export di samping Import (atas) agar mendukung export semua / export item terseleksi (`selectedProdukIds`).
- [x] **T-03 — Master Produk Ringan & Batasi '+ Saldo Awal' Hanya Produk Baru (Poin 3):** Ringankan render tabel, batasi tombol `+ Saldo Awal` secara ketat hanya jika produk baru (`total_stok == 0` dan `stok_logs_count == 0`).
- [x] **T-04 — Popup Filter Kolom di Tabel Master Produk Rapi (Poin 4):** Berikan container terstruktur, styling glassmorphism yang rapi, dan origin-aware transition pada popover filter kolom `produk-tab`.
- [x] **T-05 — Kontras Teks & Box di Mode Siang dan Malam (Poin 5):** Hapus styling hardcoded gelap seperti `bg-ink-900` atau text putih tanpa varian light mode pada input/select/card di komponen WMS agar adaptif di dark & light theme.
- [x] **T-06 — Perbaiki Input Transfer Barang & Tambahkan Tombol Batal/X (Poin 6):** Tambahkan tombol Batal / X yang jelas pada modal transfer barang, rapikan form stepper dan card input di mobile.
- [x] **T-07 — Pelaksana Tercatat di Cycle Count & Rapikan UI/UX Tombol Detail + Count (Poin 7):** Catat pelaksana (`user_id = auth()->id()`) saat tugas cycle count dijalankan/dihitung, perbaiki tata letak tombol Detail & Input Count agar rapi dan tidak tumpang tindih di layar kecil.
- [x] **T-08 — Tambahkan Tombol/Menu Kembali di Cycle Count (Poin 8):** Berikan navigasi kembali (← Kembali ke WMS) di halaman cycle count (`cycle-count.blade.php`).
- [x] **T-09 — Rapikan UI Rekomendasi Pengadaan & Pisahkan PO per Supplier (Poin 9):** Rapikan modal rekomendasi pengadaan. Saat membuat draft PO dari rekomendasi yang barangnya berasal dari supplier berbeda, kelompokkan per supplier dan buat draft PO masing-masing per supplier.
- [x] **T-10 — Sempurnakan UI/UX Cari Produk pada Pembuatan PO (Poin 10):** Optimasi modal pencarian produk global (`ProductSearchModal`) dan trigger di form PO agar cepat, responsif, dan jelas.
- [x] **T-11 — Optimalkan Workflow Input GRN & Berfungsi Tombol 'Terima Semua Lengkap' (Poin 11):** Perbaiki handler `terimaSemuaItem()` agar mengupdate `activeItemQty` dan seluruh baris form GRN, serta perbaiki UI form GRN di mobile.
- [x] **T-12 — Optimalkan Performa Navigasi Antar Menu WMS (Poin 12):** Percepat perpindahan tab pada `wms-dashboard.blade.php` dengan transisi Alpine ringan, unmount on-demand yang bersih, dan hilangkan beban komputasi ganda.
- [x] **T-13 — Optimalkan Workflow & UI/UX Retur Pembelian (Poin 13):** Rapikan tab riwayat retur & pool karantina, sediakan aksi Approve/Tolak jika status menunggu approval, dan berikan feedback status yang jelas.
- [x] **T-14 — Verifikasi Lint, Build & Tests:** Jalankan `./vendor/bin/pint --test`, `npm run build`, dan test PHPUnit modul WMS.
