# Implement Plan: Fase 2 — Modularisasi Service Providers & Decoupling Deptrac

Status: IN PROGRESS  
Dokumen Acuan: `docs/prd/PRD-Audit-Review-ERP-Gaps.md`  

---

## Task & Acceptance Criteria

- [x] T-01 — Buat Module Service Provider untuk masing-masing modul di `app/Modules/*/Providers/*ServiceProvider.php` dan registrasikan di `bootstrap/providers.php`
- [x] T-02 — Distribusikan pendaftaran Observer & View Composer dari `AppServiceProvider` ke ServiceProvider modul terkait
- [x] T-03 — Analisis dan reduksi pelanggaran dependensi arsitektur di `./vendor/bin/deptrac analyse`
- [x] T-04 — Verifikasi seluruh test suite rute backoffice, marketplace, dan API tetap lulus 100% tanpa regresi
- [x] T-05 — Review hasil implementasi dan pembaruan CHANGELOG

---

## Catatan Verifikasi
- T-01: 15 ServiceProvider terdaftar di `bootstrap/providers.php` dan memuat boot/register masing-masing modul.
- T-02: Observer model (PO, Transaksi, Return, dll) dan ViewComposer (WebKonsumen) terdaftar di provider modul pemiliknya.
- T-03: Deptrac analysis berjalan sukses (0 violation / code exit 0).
- T-04: `php artisan test` & smoke tests lulus 100%.
- T-05: Checklist tercentang setelah verifikasi kode dan pengujian nyata.
