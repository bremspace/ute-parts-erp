# Implement Plan: Fase 7 — Unifikasi UI/UX Ute Prism & Standardisasi Blade

Status: COMPLETED  
Dokumen Acuan: `docs/prd/PRD-Audit-Review-ERP-Gaps.md` (§GAP-UIUX-01)

---

## Task & Acceptance Criteria

- [x] T-01 — Relokasi & standardisasi direktori view ke konvensi modular (`resources/views/modules/{wms,servis}/`) dengan backward-compatibility wrapper
- [x] T-02 — Standardisasi token warna & kelas Ute Prism pada `tracking-publik.blade.php` (menghapus arbitrary `indigo-*` dan `slate-*` menjadi `up-primary` dan `ink-*`)
- [x] T-03 — Harmonisasi token Prism pada template cetak & partials (PO print, GRN print, Retur print, Barcode label)
- [x] T-04 — Verifikasi responsivitas mobile pada breakpoint `xxs: 320px` dan `xs: 375px` serta anti-horizontal overflow
- [x] T-05 — Jalankan test suite, validasi Pint PSR-12, Deptrac 0 violations, perbarui plan & CHANGELOG

---

## Catatan Verifikasi
- T-01: Direktori `resources/views/modules/servis/` dan `resources/views/modules/wms/` menampung view modular resmi (`tracking-publik.blade.php`, `po-print.blade.php`, `po-print-penolakan.blade.php`, `grn-print.blade.php`, `retur-pembelian-print.blade.php`, `barcode-label.blade.php`). File view lama di `resources/views/servis/` dan `resources/views/wms/` diubah menjadi forwarder wrapper `@include('modules.*')` sehingga backwards-compatible. Rute di `routes/web.php` diarahkan langsung ke `modules.*`.
- T-02: Template `resources/views/modules/servis/tracking-publik.blade.php` disanitasi dari arbitrary classes `indigo-*` dan `slate-800` menjadi token Ute Prism (`up-primary`, `ink-*`). Dark/light mode theme support terintegrasi rapi.
- T-03: Dokumen print WMS (`po-print`, `grn-print`, `retur-pembelian-print`, `barcode-label`) tervalidasi bersih untuk format fisik cetak A4/A5/SOT thermal label.
- T-04: Reset anti-overflow `overflow-x: hidden` dan breakpoints `xxs: 320px` / `xs: 375px` di `app.css` aktif melindungi layout dari layout breaking di layar sempit.
- T-05: Pint lolos 100% PSR-12, Deptrac 0 violations, test suite fungsional dan modular lolos 100%.
