# Rencana Kerja Revisi Bug Aplikasi (revision-plan.md)
**Ute Parts ERP — Backoffice, POS, & WMS**  
*Tanggal: 23 Oktober 2026*

---

## 1. Ringkasan & Ruang Lingkup
Perbaikan 11 item bug operasional pada modul Layout, WMS, Auth/RBAC, POS Kasir, Retur, dan Thermal Print.

---

## 2. Rincian Masalah & Rencana Eksekusi

### Item 1: Top Bar Title
- **Masalah:** Judul header top bar statis / tidak sinkron menu aktif (`resources/views/layouts/backoffice.blade.php:547`).
- **Solusi:** Map rute URL ke judul modul dinamis via helper/view-composer atau sinkronisasi `$header` di setiap Livewire page / layout slot.

### Item 2: Product Master - Sembunyikan Harga Beli
- **Masalah:** Kolom harga beli tetap tampil di sub-baris harga jual saat toggle kolom dimatikan (`resources/views/modules/wms/livewire/produk-tab.blade.php:1404`).
- **Solusi:** Hapus fallback rendering `@if(!($visibleColumns['harga_beli'] ?? false))` di bawah nama/harga jual. Evaluasi izin `canSeeField('harga_beli')`.

### Item 3: User Registration - Kegagalan Registrasi Pengguna
- **Masalah:** Registrasi gagal saat testing (validasi telepon/duplikasi, sanitasi password, penugasan cabang default, atau approval workflow pending).
- **Solusi:** Periksa `routes/web.php:322`, `Pelanggan.php`, dan `SettingsRbac.php:1230`. Lengkapi error handler, fallback system user approver, serta feedback validasi form.

### Item 4: Go-Live Data Reset - Pembersihan Sisa Riwayat Transaksi
- **Masalah:** Reset data mode `transaksi_only` menyisakan record di `transaksi`, `transaksi_item`, `jurnal_header`, `jurnal_akuntansi`, `approval_requests`, `kas_sesi`, `kas_mutasi_laci`.
- **Solusi:** Perluas array `$transactionTables` di `DatabaseManagementService.php:240` untuk mencakup seluruh tabel transaksi, log mutasi, approval transaksi, dan reset stok ke 0.

### Item 5: Page Reload Behavior - Preservasi State Menu Aktif
- **Masalah:** Refresh halaman mereset dropdown sidebar ke default karena rute sekunder (`/app/riwayat-transaksi`, `/app/retur-penjualan`, `/app/database`, tab Livewire) tidak terdaftar di `activeDropdown` (`backoffice.blade.php:807`).
- **Solusi:** Simpan `activeDropdown` di `localStorage`, lengkapi pola regex rute aktif di Blade layout, dan sinkronkan tab aktif Livewire ke URL query parameter.

### Item 6: Return Flow - Retur Langsung dari Riwayat Transaksi
- **Masalah:** Tombol retur di riwayat transaksi mengirim query `transaksi_id`, namun `ReturPenjualanIndex::mount()` memanggil `bukaModalTambah()` yang mereset `kwitansiInput` menjadi kosong (`ReturPenjualanIndex.php:66, 105`).
- **Solusi:** Pertahankan nilai `kwitansiInput` saat parameter URL tersedia, lalu auto-trigger `cariKwitansi()`.

### Item 7: Item Exchange in Returns - Perbaikan List Barang & Search Bar Tukar
- **Masalah:** Daftar produk kosong sebelum mengetik, pemanggilan `$this->hasilCariTukarProduk` di blade tidak lolos eksak dari `render()`, dan UI pencarian sempit (`retur-penjualan-index.blade.php:408`).
- **Solusi:** Kirim computed hasil pencarian eksplisit di `render()`, sediakan default 5 produk terpopuler/tersedia saat kolom dibuka, dan optimasi input pencarian SKU/nama/barcode.

### Item 8: Internal Server Error (HTTP 500)
- **Masalah:** Livewire 3 crash dengan error `MethodNotFoundException: Public method [$dispatch] not found` akibat sintaks `wire:click="$dispatch(...)"` di `wms-dashboard.blade.php`, `po-tab.blade.php`, dan `transfer-tab.blade.php`.
- **Solusi:** Ganti seluruh `wire:click="$dispatch(...)"` menjadi Alpine `@click="$dispatch(...)"`. Rapikan duplikasi method di `ProductSearchModal.php`.

### Item 9: Receipt Printing - Teks Terpotong pada Struk
- **Masalah:** Class `truncate` pada nama produk di struk thermal memotong teks dengan elipsis (`thermal-receipt-modal.blade.php:118`).
- **Solusi:** Ganti `truncate` dengan `break-words whitespace-normal` dan tambahkan aturan override di `print.css` agar teks membungkus rapi (multi-line wrap).

### Item 10: Membership Pricing - Tampilan Harga Platinum & Diskon Item
- **Masalah:** Harga khusus Platinum / tier diskon pada POS Kasir tidak menampilkan coretan harga retail asli dan besaran diskon per item di tabel keranjang (`pos-kasir.blade.php:135, 1125`).
- **Solusi:** Tambahkan visual harga coret (`line-through`), pill diskon tier (`Hemat Rp X`), dan rincian diskon per baris item keranjang.

### Item 11: Selling Price Adjustments - Edit Manual Harga & Diskon Produk Fleksibel
- **Masalah:** Fitur ubah harga di POS terbatas pada tombol kecil dan belum mendukung input diskon item langsung untuk produk yang dikonfigurasi fleksibel (`pos-kasir.blade.php:1101`, `PosKasir.php:1270`).
- **Solusi:** Sediakan modal/popover edit harga jual & diskon nominal/persen item jika `flex = true` / izin kasir terpenuhi, lengkap dengan batas minimal modal (HPP).

---

## 3. Rencana Kerja & Acceptance Criteria (Checklist)

### Fase 1: Perbaikan Kritis & Stabilitas Sistem (HTTP 500, Reset, Registrasi)
- [x] T-01 — Perbaiki `wire:click="$dispatch"` menjadi `@click="$dispatch"` di seluruh view WMS & bersihkan error duplicate method.
- [x] T-02 — Perbarui `DatabaseManagementService.php` dengan daftar tabel transaksi lengkap dan eksekusi pembersihan riwayat transaksi go-live.
- [x] T-03 — Perbaiki alur registrasi pengguna/pelanggan, validasi data, dan penanganan error.

### Fase 2: Navigasi & UX Layout
- [x] T-04 — Implementasikan judul top bar dinamis sesuai menu aktif di layout backoffice.
- [x] T-05 — Preservasi status menu sidebar dan dropdown aktif menggunakan `localStorage` serta perluasan rute matching.
- [x] T-06 — Perbaiki toggle kolom master produk WMS agar harga beli benar-benar tersembunyi total saat dimatikan.

### Fase 3: Transaksi POS, Struk, Retur, & Pricing Tier
- [x] T-07 — Perbaiki alur retur langsung dari riwayat transaksi (`transaksi_id` auto-load & verifikasi otomatis).
- [x] T-08 — Sediakan search bar dan daftar produk pengganti yang responsif pada langkah tukar barang retur.
- [x] T-09 — Hilangkan `truncate` pada template struk thermal & sesuaikan `print.css` agar nama barang wrap tanpa terpotong.
- [x] T-10 — Tampilkan harga retail coret, label tier Platinum, dan nominal diskon per item di katalog serta keranjang POS.
- [x] T-11 — Buka fitur penyesuaian harga jual manual dan diskon per item untuk produk yang diizinkan fleksibel.

### Fase 4: Pengujian & Verifikasi Akhir
- [x] T-12 — Jalankan unit test & feature test terkait (`composer test`).
- [x] T-13 — Verifikasi manual alur kasir POS, cetak struk 58/80mm, tukar retur, dan navigasi menu.
