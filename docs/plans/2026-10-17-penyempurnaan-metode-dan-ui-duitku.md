# Implement Plan — Penyempurnaan Metode Pembayaran, UI/UX Checkout, dan Alur End-to-End Duitku

Tanggal: 2026-10-17

## 1. Masalah & Temuan
- **Metode Pembayaran**:
  - Duitku `getPaymentMethods()` mengembalikan 23 metode sekaligus secara mentah tanpa kategori.
  - Metode `DN` (Indodana Paylater) mengembalikan HTTP 400 (karena butuh rincian angsuran/kredit).
  - Metode `DA` (DANA) dan E-Wallet di sandbox mengarah ke URL eksternal yang di desktop/sandbox sering terkendala tanpa akun sandbox khusus. Rekomendasi utama di Indonesia untuk e-wallet adalah **QRIS** (universal untuk DANA, GoPay, OVO, ShopeePay, BCA, dll).
  - Tidak ada pengelompokan (kategori VA, QRIS, E-Wallet, Retail), sehingga tampilan mobile menjadi daftar 23 tombol vertikal yang sangat panjang dan melelahkan.
- **UI/UX Checkout**:
  - Selesai checkout (`orderSuccess`), tombol "Bayar Sekarang" melempar user ke `/account`, memaksa user mencari ulang ordernya dan mengklik "Bayar Sekarang" untuk kedua kalinya.
  - Modal pembayaran tidak terkelompok (un-segmented), memakan ruang layar berlebih.
  - Di mode sandbox, tidak ada petunjuk cara mensimulasikan pembayaran lunas (padahal Duitku punya halaman demo simulator VA resmi).
- **Alur End-to-End**:
  - Perlu kejelasan alur dari sisi Konsumen (Cart → Checkout → Bayar VA/QRIS/E-Wallet → Notifikasi Realtime) dan Backoffice (Pesanan Online → Auto-Jurnal → Potong Stok Gudang → Packing/Kirim).

## 2. Rincian Tasks

- [x] T-01 — Backend: Kurasi & Kategorisasi Metode Pembayaran di `DuitkuService` & `PaymentController`:
  - Kelompokkan metode pembayaran ke dalam 4 kategori: `va` (Virtual Account), `qris` (QRIS), `ewallet` (E-Wallet), `retail` (Gerai Retail), `kartu` (Kartu Kredit).
  - Filter keluar metode bermasalah di sandbox (`DN` / Indodana 400).
  - Beri metadata bank/channel (logo/badge singkat, nama bersih, fee terformat).
- [x] T-02 — Frontend: Redesain UI/UX Modal Pembayaran Duitku (`customer-account.blade.php`):
  - Ganti daftar vertikal panjang dengan **Segmented Tabs / Pills** (Transfer Bank / VA, QRIS, E-Wallet, Gerai Retail).
  - Grid compact 2 kolom di mobile/desktop dengan styling Ute Prism (kartu modern, badge fee, radio indicator).
  - Layar Tagihan (VA): Tampilkan nomor VA besar, tombol salin, dan jika sandbox tampilkan link tombol "🧪 Buka Halaman Uji Coba VA Duitku" langsung ke simulator Duitku.
- [x] T-03 — Frontend: Seamless Checkout Flow (`cart-checkout.blade.php`):
  - Pasang payment deep link langsung di halaman success checkout (`?tab=orders&bayar=...`), sehingga klik "Bayar Sekarang" langsung membuka popup pembayaran untuk order yang baru dibuat secara otomatis.
- [x] T-04 — Verifikasi: Test suite hijau (39 tests: Duitku, Checkout, Workflow).
- [x] T-05 — Dokumentasi: Rangkuman Alur End-to-End Konsumen & Backoffice yang disempurnakan.

## 3. Pembagian Spesialis
- T-01: @fixer (Backend `DuitkuService.php`, `PaymentController.php`, `DuitkuServiceTest.php`)
- T-02, T-03: @designer (Frontend Blade views & Alpine JS)
