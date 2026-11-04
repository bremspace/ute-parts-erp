# Implement Plan: Fase 5 — Integritas ERP: Valuasi HPP & Payroll Multi-Cabang

Status: COMPLETED  
Dokumen Acuan: `docs/prd/PRD-Audit-Review-ERP-Gaps.md` (§GAP-ERP-02, §GAP-ERP-03)

---

## Task & Acceptance Criteria

- [x] T-01 — Tambahkan migrasi `cabang_id` ke tabel `payroll_periode` dengan foreign key dan indeks
- [x] T-02 — Perbarui model `PayrollPeriode` (`BelongsToCabang`, relasi `cabang()`, scoping cabang)
- [x] T-03 — Perbaiki `PayrollService`: scoping `hitungDraft` per `cabang_id` dan perbaiki posting jurnal payroll per cabang
- [x] T-04 — Perbarui Livewire component Payroll (`PayrollIndex` / `ApprovalPayroll`) untuk filter & kelola payroll per cabang
- [x] T-05 — Verifikasi & kalibrasi moving average cost / HPP pada `ProdukService` dan kartu stok (WMS/POS/Order)
- [x] T-06 — Buat & jalankan test suite integritas multi-cabang payroll & HPP, pastikan deptrac & pint 100% lulus

---

## Catatan Verifikasi
- T-01: Migrasi `2026_11_03_173933_add_cabang_id_to_payroll_periode_table.php` sukses diaplikasikan ke MySQL staging dan SQLite memory dengan composite unique `[periode, cabang_id]`.
- T-02: Model `PayrollPeriode` menggunakan `BelongsToCabang`, relasi `cabang()`, dan fillable `cabang_id`.
- T-03: `PayrollService` mendukung parameter `cabangId`, `getOrCreatePeriode` terisolasi per cabang tanpa konflik unik, dan posting jurnal menggunakan referensi cabang aktif.
- T-04: `PayrollPage` Livewire terhubung dengan isolasi cabang (`cabangFilter` / `currentCabangId()`), modal buat periode, hitung draft, approval, dan eksekusi bayar.
- T-05: `ProdukService::sesuaikanHargaBeliProduk` menerima `cabangId` untuk menghitung stok gudang aktual khusus cabang penerima barang (GRN).
- T-06: Test `tests/Feature/PayrollMultiCabangDanHppTest.php` 100% pass (18 assertions), seluruh test suite HR pass, Pint bersih (0 violations), Deptrac bersih (0 violations).
