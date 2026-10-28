# PRD: Perbaikan Komprehensif Bug Dashboard, POS Kasir, Audit Trail, & Modul Servis

**Dokumen:** Product Requirements Document (PRD) & Implementation Plan  
**Versi:** 1.0  
**Tanggal:** 28 Oktober 2026  
**Status:** Approved for Implementation  
**Target Modul:** Dashboard, POS Kasir, RBAC & Audit Log, Manajemen Retur, Servis & Kanban, Riwayat Transaksi  
**Target Lingkungan:** Standalone VPS, 1GB RAM (Safeguard memori ketat), Laravel 13, PHP 8.3+, Livewire 3, MySQL

---

## 1. Executive Summary & Tujuan

Sistem ERP & POS Ute Parts telah beroperasi dengan 15 modul aktif. Namun, evaluasi operasional lapangan menemukan 17 bug fungsional dan UI/UX kritis yang tersebar di modul Dashboard, POS Kasir, Audit Log, Retur Konsumen, Modul Servis, dan Riwayat Transaksi.

PRD ini menyatukan spesifikasi teknis perbaikan, arsitektur data, batasan sistem, dan checklist verifikasi agar seluruh perbaikan diselesaikan secara terstruktur tanpa kehilangan konteks. Pengerjaan dibagi menjadi 4 fase logis, dimulai dari perbaikan modul Dashboard.

---

## 2. Rincian Spesifikasi & Desain Solusi Per Item (1–17)

### Fase 1: Perbaikan Modul Dashboard (Item 1 – 4)

#### Item 1: Filter Waktu, Dropdown Sumber, Status, Aksi Tracking, & Pagination Data Penjualan
- **Problem:**
  1. Filter waktu di widget *Data & Analisis Penjualan* (`DashboardIndex`) tidak akurat memfilter rentang tanggal transaksi karena cache key dan binding waktu belum sinkron.
  2. Dropdown sumber memiliki opsi `website`, padahal transaksi web konsumen disimpan sebagai `marketplace` di database sehingga filter menghasilkan 0 data.
  3. Filter status tidak memperhitungkan transaksi marketplace (`status = 'lunas'`) yang seharusnya setara dengan `selesai`.
  4. Tombol aksi *Detail & Tracking* pada tabel *Tracking Transaksi Terfilter* belum bekerja optimal atau terhalang modal/cabang scoping.
  5. Data dibatasi kaku `limit(15)` tanpa pagination jika transaksi melebihi 15 baris.
- **Solusi Teknis:**
  - Perbaiki `resolvePeriodePenjualan()` dan query waktu `whereBetween('created_at', [$dari, $sampai])` dengan penanganan timezone lokal cabang (`Asia/Jakarta`).
  - Ubah opsi dropdown sumber di blade: map `website` ke `marketplace` (atau izinkan `marketplace` & `website` via `whereIn('sumber', ['marketplace', 'website'])`).
  - Selaraskan filter status: jika user memilih status `selesai`, sertakan juga `lunas` agar mencakup transaksi web/marketplace, atau tampilkan status aktual (`selesai`, `lunas`, `pending`, `dibatalkan`).
  - Tambahkan trait `WithPagination` (dengan `$paginationTheme = 'tailwind'`) pada `DashboardIndex` khusus untuk pagination tabel tracking (15 data per halaman via `pagePenjualan`), tanpa merusak agregat total metrik.
  - Perbaiki method `lihatDetailTransaksi($id)` dan modal tracking: pastikan fallback scoping cabang aman dan modal tracking render di z-index yang tepat (`z-50`).

#### Item 2: Verifikasi & Akurasi Widget Tren Omzet 30 Hari, Komposisi Produk, & Widget Lainnya
- **Problem:**
  - Potensi ketidakakuratan data grafik tren 30 hari karena perbedaan format tanggal MySQL vs Carbon app, join tabel produk pada komposisi kategori (`produk.kategori` vs relasi `kategori_id`), serta cache agregat yang menahan data usang saat transaksi baru terjadi.
- **Solusi Teknis:**
  - Pada `getChartOmzet30HariProperty`: pastikan iterasi 30 hari menggunakan `Carbon::today()->subDays($i)` dengan pengelompokan `DATE(created_at)` yang konsisten timezone.
  - Pada `getChartKategoriProperty`: perbaiki join kategori agar memprioritaskan `kategori_produks.nama` lewat `produk.kategori_id` sebelum fallback ke string `produk.kategori`, serta pastikan join mencakup transaksi berstatus `selesai` dan `lunas`.
  - Tambahkan event listener / cache invalidation saat transaksi POS atau marketplace berhasil dibukukan (`transaksi-selesai` -> bust cache `dash-chart-omzet-30`, `dash-chart-kategori`).

#### Item 3: Perluasan Widget Aktivitas Transaksi Terkini & Pembatasan Role/Permission
- **Problem:**
  - Widget *Aktivitas Transaksi Terkini* saat ini hanya menampilkan transaksi POS kasir (`Transaksi`). PO, GRN, Tiket Servis, dan Retur belum muncul.
  - Widget belum dibatasi ketat hak aksesnya (harus owner & super-admin, atau role yang memiliki permission izin).
- **Solusi Teknis:**
  - Gabungkan multi-entitas transaksi terkini ke dalam satu feed aktivitas terpadu:
    - Transaksi Kasir & Web (`Transaksi` -> kode `no_transaksi`)
    - Purchase Order WMS (`PurchaseOrder` -> kode `nomor_po`)
    - Penerimaan Barang (`Grn` -> kode `nomor_grn`)
    - Tiket Servis (`TiketServis` -> kode `no_tiket`)
    - Retur Penjualan / Retur Pembelian (`ReturnPenjualan` / `ReturnPembelian` -> kode `nomor_retur`)
  - Batasi akses widget di blade dan controller: hanya role `super-admin`, `owner`, atau permission `dashboard.aktivitas-transaksi`.

#### Item 4: Drill-down Piutang dan Utang pada Widget Proyeksi Arus Kas 30 Hari
- **Problem:**
  - Tombol drill-down piutang dan utang mengarah ke `route('laporan.drill', ['model' => 'Piutang'])` tanpa parameter yang relevan, atau tidak langsung membawa staf ke modul subledger piutang/utang.
- **Solusi Teknis:**
  - Arahkan drill-down secara tepat ke subledger Akunting tab terkait: `route('akunting', ['tab' => 'piutang'])` dan `route('akunting', ['tab' => 'utang'])`, atau buka `DrillDownViewer` dengan pre-filter status belum lunas jatuh tempo 30 hari.

---

### Fase 2: POS Kasir & Transaksi (Item 5 – 10, 17)

#### Item 5: Modal Detail Transaksi Berada di Belakang Menu Riwayat (Z-Index / Stacking Context)
- **Problem:**
  - Di `pos-kasir.blade.php`, `@include('partials.thermal-receipt-modal')` berada di baris 1662 sebelum `@include('modules.pos.livewire.modals.riwayat-transaksi-modal')` di baris 1663. Keduanya memakai kelas `z-50`. Karena `riwayat-transaksi-modal` berada setelahnya di urutan DOM, modal struk/detail transaksi tertutup di belakang modal riwayat.
- **Solusi Teknis:**
  - Pindahkan `@include('partials.thermal-receipt-modal')` ke urutan terakhir setelah `riwayat-transaksi-modal`, atau naikkan z-index modal struk / detail menjadi `z-[60]` agar selalu berada di atas modal riwayat saat dibuka dari dalam riwayat transaksi.

#### Item 6: Input Kuantitas (Qty) Manual Langsung pada Keranjang Kasir
- **Problem:**
  - Qty pada item keranjang kasir (`pos-kasir.blade.php` baris 1023, 1175, 1390) hanya berupa elemen `<span>` statis di antara tombol `+` dan `-`. Kasir tidak bisa mengetik langsung angka qty jika membeli dalam jumlah banyak (misal: 50 pcs).
- **Solusi Teknis:**
  - Ganti `<span>` qty menjadi input `<input type="number" min="1" max="...">` dengan `wire:change="setQty('{{ $key }}', $event.target.value)"` atau Alpine auto-save pada enter/blur. Pertahankan validasi batas stok maksimum dan tombol `+` / `-`.

#### Item 7: Auto-Show Data saat Kotak Pencarian Barang & Konsumen Diakses (Focus State)
- **Problem:**
  - Dropdown floating pencarian konsumen (`pos-kasir.blade.php` baris 305) mewajibkan `$wire.pelangganSearch.length >= 1`.
  - Dropdown floating pencarian produk (`pos-kasir.blade.php` baris 44) mewajibkan `$wire.search.length > 0`.
  - Saat input difokuskan / diklik pertama kali sebelum mengetik, dropdown tidak muncul.
- **Solusi Teknis:**
  - Pada input pencarian konsumen: saat difokuskan (`@focus="crmOpen = true"`), jika input kosong, tampilkan daftar 10 konsumen terbaru / pelanggan umum / pelanggan terdaftar cabang aktif.
  - Pada input pencarian barang: saat difokuskan (`@focus="searchOpen = true"`), jika input kosong, tampilkan produk-produk katalog yang sudah terfilter oleh filter aktif (kategori, brand, stok ready, dll.).

#### Item 8: Perhitungan Otomatis Split Bill (Tunai & Non-Tunai)
- **Problem:**
  - Pada modal pembayaran dengan metode `split`, kasir harus menghitung dan mengetik manual kedua nominal (`splitTunai` dan `splitNonTunai`). Rentan terjadi selisih (kurang bayar / lebih bayar).
- **Solusi Teknis:**
  - Tambahkan reaktivitas otomatis:
    - Jika kasir memasukkan `splitTunai`, sistem otomatis menghitung `splitNonTunai = max(0, totalAkhir - splitTunai)`.
    - Jika kasir memasukkan `splitNonTunai`, sistem otomatis menghitung `splitTunai = max(0, totalAkhir - splitNonTunai)`.
  - Tambahkan tombol pintas pembagian (misal: "Bagi Rata 50:50", "Semua Non-Tunai", "Semua Tunai").

#### Item 9: Popup Perbesar QRIS Minimalis Tanpa Backdrop Hitam Berlebih
- **Problem:**
  - Layar zoom QRIS saat ini menggunakan backdrop `bg-black/90 backdrop-blur-md` yang sangat gelap, border tebal 4px, dan tata letak terlalu padat/kaku.
- **Solusi Teknis:**
  - Desain ulang popup QRIS zoom menjadi minimalis, bersih, dan modern:
    - Backdrop lembut: `bg-ink-950/60 backdrop-blur-sm`.
    - Card putih bersih dengan bayangan halus (soft shadow), rasio QR code optimal agar mudah discan oleh kamera smartphone konsumen.
    - Informasi esensial: Nama Merchant, Total Tagihan yang terkunci, tombol Unduh PNG/SVG, dan kirim WA.

#### Item 10: Penegasan Kondisi Struk untuk Transaksi Piutang (Kasbon)
- **Problem:**
  - Pada transaksi dengan metode pembayaran `piutang` (kasbon), struk thermal saat ini mencantumkan format standar `BAYAR (PIUTANG): Rp X`, `KEMBALI: Rp 0` yang menyerupai struk lunas biasa.
- **Solusi Teknis:**
  - Pertegas header dan footer struk saat metode bayar = `piutang`:
    - Header/Watermark: **"NOTA PIUTANG / KASBON (BELUM LUNAS)"**.
    - Rincian: Tanggal Jatuh Tempo, Sisa Tagihan Piutang.
    - Kolom Tanda Tangan Konsumen (Akad/Tanda Terima Kasbon) untuk kepastian hukum penagihan.

#### Item 17: Filter Rentang Tanggal Custom pada Menu Riwayat Transaksi
- **Problem:**
  - Pada halaman `RiwayatTransaksiIndex`, opsi "Rentang Kustom" di dropdown terkadang tidak memunculkan atau mengunci input datepicker karena binding Livewire tanpa `wire:key` unik.
- **Solusi Teknis:**
  - Tambahkan `wire:key="filter-custom-date-inputs"` pada wrapper datepicker kustom.
  - Pastikan saat user memilih opsi `custom`, input `filterTanggalDari` dan `filterTanggalSampai` langsung terbuka, dapat dipilih bebas, dan otomatis merefresh tabel transaksi tanpa reload halaman.

---

### Fase 3: Sentralisasi Audit Trail & Retur (Item 11, 12)

#### Item 11: Sentralisasi & Format Ramah Awam Riwayat Aktivitas Global
- **Problem:**
  - Modal `RiwayatAktivitas` saat ini menampilkan nama kolom mentah database (`diskon_nominal`, `pajak_persen`, `cabang_id: 1`, `status: pending -> selesai`), sehingga sulit dipahami oleh staf kasir atau owner non-teknis.
- **Solusi Teknis:**
  - Bangun kamus istilah ramah awam (`ActivityLogFormatter`):
    - `status` -> Status Transaksi
    - `total_akhir` -> Total Pembayaran
    - `diskon_nominal` -> Potongan Diskon
    - `jumlah` -> Kuantitas
    - `harga_satuan` -> Harga Satuan
    - `pelanggan_id` / `user_id` -> konversi ke Nama Pelanggan / Nama Staf
  - Format kalimat terstruktur: *"Mengubah Status dari 'Menunggu Pembayaran' menjadi 'Selesai'"*, *"Memberikan diskon kasir sebesar Rp 10.000"*, *"Mencetak ulang struk thermal"*.
  - Terapkan format seragam ini di semua modal riwayat aktivitas (POS Kasir, PO, GRN, WMS Produk, Servis, dan Akunting).

#### Item 12: Pengaktifan Tombol Audit pada Menu Manajemen Retur Konsumen
- **Problem:**
  - Pada `retur-penjualan-index.blade.php`, tombol `wire:click="bukaRiwayat('retur_penjualan', ...)"` sudah ada, namun `@include('partials.riwayat-modal')` tidak dipasang di akhir blade. Akibatnya tombol audit tidak menampilkan apa pun.
- **Solusi Teknis:**
  - Tambahkan `@include('partials.riwayat-modal')` pada view `resources/views/modules/pos/livewire/retur-penjualan-index.blade.php`.
  - Pastikan `ReturPenjualanIndex.php` mendefinisikan listener dan properti yang dibutuhkan trait `PunyaRiwayatAktivitas`.

---

### Fase 4: Servis Unit, Kunci Pola, Struk SPK/Lunas, & Pengerjaan (Item 13 – 16)

#### Item 13: Standarisasi Form Terima Unit Servis (Web Konsumen & Toko) & Sinkronisasi CRM
- **Problem:**
  - Form booking servis online (`BookingServis`) dan form terima unit di toko (`ServisBoard`) memiliki perbedaan field kelengkapan dan belum otomatis menghubungkan pelanggan baru ke database CRM (`pelanggan`).
- **Solusi Teknis:**
  - Standarisasi field input antara Web dan Toko: Nama Konsumen, Nomor WhatsApp, Tipe HP & Seri, Keluhan Utama, Tipe Kunci Keamanan, Kondisi Fisik, Kelengkapan Unit, dan Pilihan Estimasi/SLA.
  - Tambahkan auto-create / auto-link CRM: jika nomor telepon konsumen belum ada di tabel `pelanggan`, sistem otomatis membuatkan data `Pelanggan` baru dengan status aktif dan mengaitkannya ke tiket servis.

#### Item 14: Modernisasi UI/UX Kunci Pola (Pattern Lock) dengan Nuansa Mobile Phone
- **Problem:**
  - Komponen `x-pattern-lock` masih kaku, garis SVG berwarna ungu standar, tanpa animasi denyut (pulse/glow) atau frame smartphone modern.
- **Solusi Teknis:**
  - Desain ulang komponen `pattern-lock.blade.php`:
    - Bungkus dalam bingkai smartphone elegan (glassmorphism mobile phone frame).
    - Berikan efek ripple / glow saat titik disentuh atau diswipe.
    - Tampilkan ringkasan nomor urut titik dan arah swipe yang responsif.
    - Dukung touch event mobile dan mouse drag desktop tanpa lag.

#### Item 15: Penambahan Jasa & Part, SLA Jam/Hari, Tombol Cetak Kanban, & Pemisahan Struk SPK vs Kwitansi Lunas
- **Problem:**
  - Penambahan part dan jasa pada form terima servis terkadang macet pada modal pencarian produk.
  - SLA ditampilkan dalam hitungan menit (misal: 1440 menit) yang membingungkan staf dan konsumen.
  - Tombol cetak struk tidak tersedia di card kanban; setelah submit form terima, tombol tutup modal (x) macet karena tumpang tindih state modal.
  - Struk yang dicetak sebelum pembayaran menyerupai kwitansi pembayaran, padahal seharusnya berupa Tanda Terima Servis (SPK / Estimasi).
- **Solusi Teknis:**
  - Perbaiki picker jasa dan part pada form terima servis agar dapat menambah multiple item tanpa error.
  - Konversi format SLA dari menit menjadi **Jam** dan **Hari** (misal: 120 menit -> "2 Jam", 2880 menit -> "2 Hari").
  - Tambahkan tombol pintas *Cetak Struk* langsung pada setiap Card Kanban Tiket Servis.
  - Perbaiki handler tombol `✕` modal servis agar selalu mereset state `selectedTiketId = null` dan `showTerimaModal = false`.
  - Pisahkan template struk servis:
    - **Sebelum Lunas:** Cetak **"TANDA TERIMA SERVIS / ESTIMASI BIAYA"** (Watermark: *BELUM LUNAS - ESTIMASI*, mencantumkan keluhan, kelengkapan, estimasi biaya, dan SLA).
    - **Setelah Lunas:** Cetak **"KWITANSI PELUNASAN SERVIS"** (Watermark: *LUNAS*, mencantumkan detail pembayaran kasir, rincian part & jasa, dan masa garansi).

#### Item 16: Progress Bar Otomatis Berdasarkan Posisi Kanban & Validasi Wajib Foto per Step
- **Problem:**
  - Input persentase progress servis diisi manual oleh teknisi, tidak mencerminkan tahapan kanban aktual.
  - Teknisi bisa memindahkan tiket tanpa menyertakan bukti pengerjaan/foto.
- **Solusi Teknis:**
  - Otomatisasi persentase progress berdasarkan status kanban:
    - `diterima` = 10%
    - `diagnosa` = 25%
    - `disetujui` = 40%
    - `dikerjakan` = 65%
    - `qc` = 85%
    - `selesai` = 100%
  - Validasi ketat pengerjaan teknisi: sebelum tiket dapat dipindahkan ke tahap berikutnya, teknisi wajib melakukan update progress dengan catatan tindakan dan minimal 1 unggahan foto pengerjaan. Tombol status berikutnya terkunci (disabled) jika step berjalan belum diperbarui.

---

## 3. Rencana Eksekusi & Roadmap Bertahap

Pengerjaan dilakukan secara sekuensial dan terukur:
1. **Fase 1 (Task T-01 s/d T-04):** Dashboard & Analisis Penjualan, Verifikasi Grafik Omzet & Kategori, Aktivitas Transaksi Multi-Entitas, Drill-down Arus Kas.
2. **Fase 2 (Task T-05 s/d T-10, T-17):** POS Kasir Z-Index, Qty Input Langsung, Focus Search Auto-Show, Split Bill Auto, QRIS Zoom Minimalis, Struk Piutang, Custom Date Riwayat Transaksi.
3. **Fase 3 (Task T-11 s/d T-12):** Sentralisasi Audit Trail Ramah Awam & Tombol Audit Retur Penjualan.
4. **Fase 4 (Task T-13 s/d T-16):** Standarisasi Form Servis & CRM, Kunci Pola Mobile-Style, SLA Jam/Hari & Pemisahan Struk SPK vs Lunas, Auto Progress & Wajib Foto Step Kanban.

---

## 4. Acceptance Criteria Checklist

Checklist ini wajib diverifikasi dan dicentang `- [x]` secara bertahap saat setiap task selesai diuji:

### Fase 1: Menu Dashboard
- [ ] T-01 — Dashboard Data Penjualan: Filter periode, sumber (pos, marketplace, servis), dan status transaksi akurat; tombol aksi tracking berfungsi; tabel tracking mendukung pagination jika >15 data.
- [ ] T-02 — Dashboard Chart Accuracy: Grafik tren omzet 30 hari dan komposisi kategori produk akurat mencakup transaksi berstatus selesai & lunas; cache refresh tepat waktu.
- [ ] T-03 — Dashboard Multi-Transaction Feed: Widget aktivitas transaksi terkini diperluas menampilkan POS, PO, GRN, Servis, dan Retur; hak akses dibatasi hanya untuk super-admin, owner, atau permission terkait.
- [ ] T-04 — Dashboard Arus Kas Drill-down: Tombol drill-down piutang dan utang pada widget proyeksi arus kas mengarah secara tepat ke subledger Akunting atau laporan terfilter 30 hari.

### Fase 2: POS Kasir & Transaksi
- [ ] T-05 — POS Modal Z-Index: Modal detail transaksi pada tab riwayat kasir muncul di depan menu riwayat (z-index bertingkat rapi).
- [ ] T-06 — POS Qty Keranjang: Input kuantitas barang di keranjang kasir dapat diketik manual langsung tanpa hanya mengandalkan tombol tambah/kurang.
- [ ] T-07 — POS Auto-Show Search on Focus: Kotak pencarian barang dan konsumen langsung menampilkan daftar data relevan (atau terfilter) saat difokuskan sebelum mengetik.
- [ ] T-08 — POS Auto Split Bill: Perhitungan pembagian nominal tunai dan non-tunai pada split bill berjalan otomatis dan selalu seimbang sesuai total tagihan.
- [ ] T-09 — POS QRIS Zoom Minimalis: Tampilan perbesar QRIS toko/dinamis bersih, minimalis, tanpa latar belakang hitam pekat yang berlebihan.
- [ ] T-10 — POS Struk Piutang: Format struk untuk transaksi kasbon/piutang secara tegas memuat tanda "PIUTANG / BELUM LUNAS", jatuh tempo, dan tanda tangan akad.
- [ ] T-17 — POS Riwayat Custom Date: Rentang tanggal kustom di menu riwayat transaksi responsif dan langsung dapat diubah saat opsi dipilih.

### Fase 3: Audit Trail & Retur
- [ ] T-11 — Audit Trail Sentralisasi: Riwayat aktivitas global diterjemahkan ke bahasa Indonesia ramah awam tanpa memperlihatkan nama kolom database teknis mentah.
- [ ] T-12 — Tombol Audit Retur Konsumen: Tombol audit pada manajemen retur konsumen berfungsi normal dan memunculkan riwayat aktivitas perubahan retur.

### Fase 4: Modul Servis & Pengerjaan
- [ ] T-13 — Form Terima Servis Standar & CRM: Form intake web konsumen dan toko diseragamkan; penambahan konsumen servis otomatis tersinkronisasi ke data pelanggan CRM.
- [ ] T-14 — Kunci Pola Animasi Mobile: UI/UX pattern lock interaktif dengan frame smartphone, animasi titik, dan feedback sentuh yang halus.
- [ ] T-15 — Part/Jasa, SLA Jam/Hari, Struk Kanban, & SPK vs Kwitansi: Jasa dan part dapat ditambahkan di form servis; SLA dihitung dalam jam/hari; tombol struk tersedia di card kanban; tombol tutup modal bekerja; struk belum lunas ditandai estimasi/SPK dan struk lunas ditandai kwitansi.
- [ ] T-16 — Progress Otomatis & Wajib Foto Step Servis: Progress bar bergerak otomatis sesuai tahap kanban; perpindahan step terkunci hingga teknisi mengunggah update catatan dan foto pengerjaan.
