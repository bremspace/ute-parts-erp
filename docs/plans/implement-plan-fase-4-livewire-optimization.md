# Implement Plan: Fase 4 — Dekomposisi "God Components" Livewire & Profiling 2GB RAM

Status: COMPLETED  
Dokumen Acuan: `docs/prd/PRD-Audit-Review-ERP-Gaps.md`  
Hardware Target: VPS Linux 2GB RAM (Baseline Minimum)

---

## Task & Acceptance Criteria

- [x] T-01 — Sesuaikan acuan hardware baseline proyek ke VPS 2GB RAM pada dokumentasi dan konfigurasi sistem
- [x] T-02 — Dekomposisi `PosKasir`: Ekstraksi concern `ManagesHoldTransactions` dan `ManagesKasLaci` ke `app/Modules/Pos/Livewire/Concerns/`
- [x] T-03 — Dekomposisi `ProdukTab`: Ekstraksi concern `ManagesProdukExcel` ke `app/Modules/Wms/Livewire/Concerns/`
- [x] T-04 — Dekomposisi `SettingsRbac`: Ekstraksi concern `ManagesUserRbac` ke `app/Modules/Rbac/Livewire/Concerns/`
- [x] T-05 — Benchmark & profiling penggunaan memori instansiasi komponen Livewire terhadap headroom VPS 2GB RAM
- [x] T-06 — Verifikasi deptrac (0 violation), laravel pint, dan regression test suite modul

---

## Catatan Verifikasi
- T-01: Baseline 2GB RAM tercatat jelas di `PRD-Audit-Review-ERP-Gaps.md`.
- T-02: Fitur hold transaksi dan mutasi kas laci di POS diekstraksi ke trait modular terisolasi, test feature lolos.
- T-03: Fitur import/export excel produk di WMS diekstraksi ke `ManagesProdukExcel`, test opname & WMS lolos.
- T-04: Manajemen user dan assignment role di RBAC diekstraksi ke `ManagesUserRbac`, arsitektur tetap bersih.
- T-05: Peak memory usage instansiasi Livewire komponen stabil di ~41 MB (sangat aman di atas plafon headroom 2GB RAM).
- T-06: Deptrac 0 violation, Pint PSR-12 lolos, seluruh test relevan hijau.
