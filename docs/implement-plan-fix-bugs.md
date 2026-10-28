# Implement Plan: Perbaikan 17 Bug Dashboard, POS Kasir, Audit Trail, & Modul Servis

Dokumen implementasi pengerjaan teknis berdasarkan `docs/prd/PRD-Fix-Bugs-Dashboard-POS-Servis.md`.

---

## Acceptance Criteria Checklist

### Fase 1: Perbaikan Modul Dashboard
- [x] T-01 — Dashboard Data Penjualan: Filter periode, sumber (pos, marketplace, servis), dan status transaksi akurat; tombol aksi tracking berfungsi; tabel tracking mendukung pagination jika >15 data.
- [x] T-02 — Dashboard Chart Accuracy: Grafik tren omzet 30 hari dan komposisi kategori produk akurat mencakup transaksi berstatus selesai & lunas; cache refresh tepat waktu.
- [x] T-03 — Dashboard Multi-Transaction Feed: Widget aktivitas transaksi terkini diperluas menampilkan POS, PO, GRN, Servis, dan Retur; hak akses dibatasi hanya untuk super-admin, owner, atau permission terkait.
- [x] T-04 — Dashboard Arus Kas Drill-down: Tombol drill-down piutang dan utang pada widget proyeksi arus kas mengarah secara tepat ke subledger Akunting atau laporan terfilter 30 hari.

### Fase 2: POS Kasir & Transaksi
- [x] T-05 — POS Modal Z-Index: Modal detail transaksi pada tab riwayat kasir muncul di depan menu riwayat (z-index bertingkat rapi).
- [x] T-06 — POS Qty Keranjang: Input kuantitas barang di keranjang kasir dapat diketik manual langsung tanpa hanya mengandalkan tombol tambah/kurang.
- [x] T-07 — POS Auto-Show Search on Focus: Kotak pencarian barang dan konsumen langsung menampilkan daftar data relevan (atau terfilter) saat difokuskan sebelum mengetik.
- [x] T-08 — POS Auto Split Bill: Perhitungan pembagian nominal tunai dan non-tunai pada split bill berjalan otomatis dan selalu seimbang sesuai total tagihan.
- [x] T-09 — POS QRIS Zoom Minimalis: Tampilan perbesar QRIS toko/dinamis bersih, minimalis, tanpa latar belakang hitam pekat yang berlebihan.
- [x] T-10 — POS Struk Piutang: Format struk untuk transaksi kasbon/piutang secara tegas memuat tanda "PIUTANG / BELUM LUNAS", jatuh tempo, dan tanda tangan akad.
- [x] T-17 — POS Riwayat Custom Date: Rentang tanggal kustom di menu riwayat transaksi responsif dan langsung dapat diubah saat opsi dipilih.

### Fase 3: Audit Trail & Retur
- [x] T-11 — Audit Trail Sentralisasi: Riwayat aktivitas global diterjemahkan ke bahasa Indonesia ramah awam tanpa memperlihatkan nama kolom database teknis mentah.
- [x] T-12 — Tombol Audit Retur Konsumen: Tombol audit pada manajemen retur konsumen berfungsi normal dan memunculkan riwayat aktivitas perubahan retur.

### Fase 4: Modul Servis & Pengerjaan
- [x] T-13 — Form Terima Servis Standar & CRM: Form intake web konsumen dan toko diseragamkan; penambahan konsumen servis otomatis tersinkronisasi ke data pelanggan CRM.
- [x] T-14 — Kunci Pola Animasi Mobile: UI/UX pattern lock interaktif dengan frame smartphone, animasi titik, dan feedback sentuh yang halus.
- [x] T-15 — Part/Jasa, SLA Jam/Hari, Struk Kanban, & SPK vs Kwitansi: Jasa dan part dapat ditambahkan di form servis; SLA dihitung dalam jam/hari; tombol struk tersedia di card kanban; tombol tutup modal bekerja; struk belum lunas ditandai estimasi/SPK dan struk lunas ditandai kwitansi.
- [x] T-16 — Progress Otomatis & Wajib Foto Step Servis: Progress bar bergerak otomatis sesuai tahap kanban; perpindahan step terkunci hingga teknisi mengunggah update catatan dan foto pengerjaan.
