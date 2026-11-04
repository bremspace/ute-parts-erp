# Implement Plan: Fase 3 — Multi-Branch Hardening & Scope Isolation

Status: IN PROGRESS  
Dokumen Acuan: `docs/prd/PRD-Audit-Review-ERP-Gaps.md`  

---

## Task & Acceptance Criteria

- [x] T-01 — Buat `BranchContextManager` singleton di `app/Services/BranchContextManager.php` untuk mengelola active branch context di level HTTP session, CLI, dan background Queue
- [x] T-02 — Tambahkan helper `currentCabangId()` dan `currentCabang()` di `app/Helpers/functions.php`
- [x] T-03 — Buat `CabangScope` dan Trait `BelongsToCabang` di `app/Traits/BelongsToCabang.php` yang mendukung auto-scope dan auto-fill `cabang_id` saat create
- [x] T-04 — Terapkan trait `BelongsToCabang` pada model-model utama yang berskup cabang (`Lead`, `AsetTetap`, `ReturnPembelian`, `ReturnPenjualan`) dengan opsi bypass `withoutCabangScope()`
- [x] T-05 — Integrasikan `BranchContextManager` ke dalam `EnsureCabangSelected` middleware dan hapus fallback hardcoded cabang `1` pada service-service rentan (`ReturnPembelianService`, `ChannelSyncService`, `KomisiService`)
- [x] T-06 — Verifikasi deptrac, pint, unit & feature tests untuk multi-branch isolation

---

## Catatan Verifikasi
- T-01: `BranchContextManager` dapat mengatur, membaca, dan mengeksekusi callback dalam scope cabang (`runInBranchContext`), serta membaca session HTTP atau fallback user cabang default secara aman.
- T-02: Helper `currentCabangId()` mengembalikan ID cabang aktif baik saat request web maupun queue/CLI context.
- T-03: Model dengan `BelongsToCabang` otomatis menyaring data berdasarkan `cabang_id` aktif jika ada context, dan auto-assign `cabang_id` jika tidak disuplai saat create.
- T-04: Query model yang menggunakan `withoutCabangScope()` dapat membaca data seluruh cabang untuk konsolidasi laporan.
- T-05: Tidak ada lagi fallback `$cabangId ?? 1` tanpa memeriksa context cabang.
- T-06: Seluruh test suite lulus 100% dan Deptrac 0 violation.
