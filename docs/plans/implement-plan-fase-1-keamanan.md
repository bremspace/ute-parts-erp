# Implement Plan: Fase 1 — Keamanan & Sanitasi Backdoor

Status: IN PROGRESS  
Dokumen Acuan: `docs/prd/PRD-Audit-Review-ERP-Gaps.md`  

---

## Task & Acceptance Criteria

- [x] T-01 — Hapus backdoor credential override super-admin di `routes/web.php:485-495`
- [x] T-02 — Perketat rate limiting pada endpoint publik tracking (`/tracking*`) dan booking online (`/servis/booking-online`, `/booking-servis`)
- [x] T-03 — Sanitasi route /api/login rate limit & session handling
- [x] T-04 — Verifikasi unit & feature test autentikasi dan route smoke test

---

## Catatan Verifikasi
- T-01: Verifikasi `Auth::attempt` hanya berjalan normal tanpa fallback password hardcoded/live update DB.
- T-02: Rate limiter terpasang pada route tracking search, tracking detail, tracking verify, tracking approve/reject.
- T-03: Rate limiter API login tetap ketat (10 attempt/menit) dan tidak ada credentials bypass.
- T-04: Test suite `LoginFlowTest`, `TwoFactorTest`, `SmokeSemuaHalamanTest`, dan `ServisTrackingKonsumenTest` lulus 100%.
