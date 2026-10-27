# Implement Plan: Optimasi Sentralisasi Pencarian & Filter Global

## Deskripsi
Optimasi sistem pencarian dan filter secara global di semua modul (WMS, POS Kasir, Marketplace, dan Modal Pencarian Produk Global). Menempatkan nama produk sebagai prioritas tertinggi dalam ranking relevansi, menyediakan reset filter per-item (tombol `✕`), menyentralisasi query filter katalog, dan memisahkan pengelolaan Master Merk HP serta Tipe HP di Pengaturan Master Atribut.

---

## Acceptance Criteria Checklist

- [x] T-01 — SmartSearchService: Tingkatkan bobot relevansi pencarian nama produk (exact +1000, prefix +800, all-tokens +600, partial +300, kompatibilitas +150, brand +100, ready stock +25).
- [x] T-02 — SmartSearchService: Sentralisasi filter katalog (`applyCatalogFilters` & `scopeFilterKatalog`) untuk dipakai seragam di seluruh modul.
- [x] T-03 — Integrasi Ranking Relevansi Otomatis: Pastikan `urutkanRelevansi` otomatis dipanggil saat input pencarian terisi di WMS ProdukTab, POS Kasir, ShopPage, dan ProductSearchModal.
- [x] T-04 — Blade Component Active Filter Chips: Buat komponen reusable untuk menampilkan chip filter aktif dengan tombol `✕` per-item dan reset semua.
- [x] T-05 — Integrasi Filter Chips & Reset Per-Item: Pasang komponen chip filter di Marketplace (shop-catalog), POS Kasir (pos-kasir), WMS Master Produk (produk-tab), dan Global ProductSearchModal.
- [x] T-06 — Master Atribut Merk HP & Tipe HP: Tambahkan manajemen Merk HP dan hubungkan dropdown pemilihan Merk HP pada Tipe HP di SettingsRbac.
- [x] T-07 — Verifikasi & Testing: Jalankan php artisan test / linting dan pastikan semua fitur terintegrasi tanpa error.
