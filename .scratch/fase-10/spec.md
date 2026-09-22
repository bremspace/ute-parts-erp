# SPEC — Fase 10: Keamanan & Optimasi UI

**Generated from:** `/grill-with-docs` interview (Round 1 & 2)  
**Date:** 2025-09-19  
**ADRs:** 0006-0012 (recorded in `CONTEXT.md` & `docs/adr/`)

---

## Overview

Fase 10 addresses 10 tasks (T-28 through T-37) covering security hardening, UI/UX optimization, PWA, theme system, thermal printing, and operational fixes. All decisions documented in ADRs 0006-0012.

**COA References (from AkunCoaSeeder):**
- `110-01` Kas (Aset, debit)
- `310-01` Modal Pemilik (Ekuitas, kredit) — used for "Modal Kas Awal Shift"
- `520-07` Selisih Kas (Beban, debit) — already exists for T-09

---

## Task Specifications

### T-28 — Security Scan & Hardening
**Type:** Bug fix / Security  
**Priority:** Highest (blocks all — run first)  
**Dependencies:** None  
**Blocking:** T-29, T-30, T-31, T-32, T-33, T-34, T-35, T-36, T-37

**Scope (Automated + Manual per ADR 0006):**
1. **Automated scans:**
   - `composer audit` — PHP deps
   - `npm audit` — JS deps
   - Laravel security headers check (CSP, HSTS, X-Frame-Options, Referrer-Policy)
   - OWASP ZAP scan against local dev

2. **Manual code review:**
   - Auth flows: Sanctum token expiration, rotation, session fixation
   - RBAC: Spatie middleware on ALL routes (not UI-only)
   - Rate limiting: ALL `/api/*` routes (throttle:60,1)
   - File upload: MIME, size, extension validation (Excel import)
   - SQL injection: parameter binding audit (no raw queries)
   - XSS: Livewire `{!! !!}` audit, output escaping
   - CSRF: tokens on all state-changing forms
   - Error handling: `APP_DEBUG=false` in production, no stack traces
   - `.env` secrets: no committed secrets, strong `APP_KEY`
   - HTTPS enforce: `ASSET_URL` HTTPS, `FORCE_HTTPS` middleware
   - Session: secure, httpOnly, sameSite=lax

**Acceptance Criteria:**
- [ ] Security scan report with severity + remediation per finding
- [ ] All critical/high findings fixed and verified
- [ ] All `/api/*` endpoints have rate limiting + input validation
- [ ] CSP + security headers configured in Laravel 13

---

### T-29 — Sidebar Ganti Cabang Fix
**Type:** Bug fix  
**Priority:** High  
**Dependencies:** T-28 (security baseline)  
**Blocking:** None

**Scope:**
- Fix event ganti cabang di sidebar to:
  1. Call `AUTH-02 POST /api/select-branch` correctly
  2. Refresh session `cabang_aktif_id`
  3. Trigger `wire:navigate`/reload dependent components (dashboard, POS, WMS)
- Verify fix works across all cabang-dependent modules

**Acceptance Criteria:**
- [ ] Ganti cabang → immediate data refresh in Dashboard, POS, WMS
- [ ] Page refresh retains selected cabang
- [ ] All cabang-dependent modules reset state on cabang change

---

### T-30 — Responsive UI Optimization
**Type:** Feature / UI  
**Priority:** High  
**Dependencies:** T-28  
**Blocking:** None

**Scope (per ADR 0010):**
- Add custom breakpoints to Tailwind 4 `@theme`: `xs: 320px`, `xxs: 375px`
- Audit all pages at 320px, 375px, 768px, 1440px
- Fix: horizontal scroll, touch targets (<44x44px), text readability
- POS-specific: search dropdown, cart panel, customer picker, numpad, payment modal, struk preview

**Acceptance Criteria:**
- [ ] No horizontal scroll at 320px-375px
- [ ] All interactions work without zoom
- [ ] Text readable without browser zoom

---

### T-31 — PWA Basic Install
**Type:** Feature  
**Priority:** Medium  
**Dependencies:** T-28  
**Blocking:** None

**Scope (per ADR 0007):**
- `manifest.json`: name, short_name, icons (192/512), theme_color, background_color, display: standalone
- Service worker (Vite PWA plugin): stale-while-revalidate for assets, network-first for API GET, network-only for mutations
- Precache: Vite build assets + critical pages (`/app/pos`, `/app/dashboard`, `/shop`)
- Offline page: `/offline.html`
- Test: "Add to Home Screen" appears on Chrome Android

**Acceptance Criteria:**
- [ ] Installable from browser to home screen
- [ ] Opens without URL bar after install
- [ ] Works offline for cached pages
- [ ] Icons/colors match "Ute Prism" design system

---

### T-32 — Dark/Light Theme Toggle
**Type:** Feature  
**Priority:** Medium  
**Dependencies:** T-28  
**Blocking:** None

**Scope (per ADR 0008):**
- Tailwind 4 `dark:` variant with `class` strategy (toggle `dark` class on `<html>`)
- Persistence: localStorage (instant) + `users.theme_preference` column (sync)
- Auto-detect: `prefers-color-scheme` fallback
- Zone defaults: Backoffice = dark, Marketplace = light
- Migration: add `theme_preference` enum (`light`, `dark`, `auto`) to `users` table

**Acceptance Criteria:**
- [ ] Manual toggle via UI button
- [ ] Auto-switch per system preference
- [ ] Preference persists across sessions & devices
- [ ] No broken UI on theme switch

---

### T-33 — Kas-Akunting Sync Verification
**Type:** Bug fix / Feature  
**Priority:** High  
**Dependencies:** T-28, T-09 (KasSesi from Fase 2)  
**Blocking:** T-34

**Scope:**
- Verify `KasSesi` entity: `saldo_awal`, `saldo_akhir_sistem`, `saldo_akhir_fisik`, `selisih`
- Buka kas: jurnal debit `110-01` Kas, kredit `310-01` Modal Pemilik
- Tutup kas: hitung `saldo_akhir_sistem` from tunai transaksi, compare `saldo_akhir_fisik`, jurnal penyesuaian `selisih` to `520-07` Selisih Kas
- Shift carryover: `saldo_awal` next shift = `saldo_akhir_fisik` previous shift
- Multi-kasir: each kasir has own `KasSesi` per cabang per shift
- Laporan Akunting shows riwayat sesi kas per cabang

**Acceptance Criteria:**
- [ ] No tunai transaksi without open KasSesi
- [ ] Tutup kas produces balanced journal (debit = kredit)
- [ ] Akunting report shows kas sesi history per cabang
- [ ] Saldo awal source tracked (manual/legacy/carryover)

---

### T-34 — Ribuan Separator for Nominal Inputs
**Type:** Feature / UI  
**Priority:** Medium  
**Dependencies:** T-33 (uses in Buka Kas)  
**Blocking:** None

**Scope (per ADR 0011):**
- Alpine.js `x-format-number` component
- Auto-format on input: `1000000` → `1.000.000` (Indonesian locale)
- Backend receives clean numeric value (strip separators)
- No typing lag (<50ms)
- Apply to: Buka Kas, POS nominal, all monetary inputs

**Acceptance Criteria:**
- [ ] Thousand separator appears while typing
- [ ] No perceptible typing lag
- [ ] Backend handles formatted or raw input identically

---

### T-35 — Thermal Print (58mm/80mm) + Web Print A4
**Type:** Feature  
**Priority:** High  
**Dependencies:** T-28  
**Blocking:** None

**Scope (per ADR 0009):**
- **Primary**: Web Bluetooth API (Chrome Android/Edge) — direct to Bluetooth printer
- **Fallback**: Backend CUPS/escpos-php (iOS Safari, desktop)
- **58mm layout**: Simple struk (header, items, total, payment, barcode, footer)
- **80mm layout**: Full faktur (customer info, item table, PPN 11%, payment detail, signatures)
- **Web Print A4**: `window.print()` with `@page` CSS for PO, kontrak, dokumen lengkap
- Reuse `picqer/php-barcode-generator` (T-15) for barcode labels

**Acceptance Criteria:**
- [ ] 58mm prints directly from POS on Android
- [ ] 80mm faktur prints correctly
- [ ] A4 web print opens browser dialog with proper layout
- [ ] No browser plugin required

---

### T-36 — Customer Create in POS (Sync CRM)
**Type:** Bug fix / Feature  
**Priority:** High  
**Dependencies:** T-28, T-04 (CRM-06 from Fase 1)  
**Blocking:** None

**Scope:**
- Tombol "+ Pelanggan Baru" in POS opens same modal as CRM (§5.6 PRD Frontend)
- Fields: nama, no HP (unique), alamat, tier awal (default lowest)
- Submits to `CRM-06 POST /api/crm/pelanggan` (single source of truth)
- New customer immediately available in POS search (T-08) and Servis

**Acceptance Criteria:**
- [ ] Modal opens, create succeeds
- [ ] New customer appears in CRM list without reload
- [ ] Searchable in POS & Servis immediately

---

### T-37 — Birthday Field + Tahan Fix
**Type:** Feature + Bug fix  
**Priority:** Medium  
**Dependencies:** T-28, T-03 (Tahan from Fase 1), T-04 (CRM-06)  
**Blocking:** None

**Scope:**
1. **Birthday field (per ADR 0012):**
   - Add `tanggal_lahir` (nullable date) to `pelanggan` table
   - Standard `<input type="date">` in CRM form
   - Campaign query: `whereMonth/whereDay` for birthday promos

2. **Tahan fix (T-03 enhancement):**
   - Transaksi ditekan F6 → immediately appears in "Transaksi Tertahan" panel
   - Kasir can resume without confusion
   - Queue flows smoothly

**Acceptance Criteria:**
- [ ] `tanggal_lahir` in pelanggan table + CRM form
- [ ] Marketing can filter by birthday month/day
- [ ] F6 (tahan) → shows in tertahan panel
- [ ] Kasir resumes tertahan smoothly, no queue disruption

---

## Blocking Edges Summary

```
T-28 ──┬──→ T-29
       ├──→ T-30
       ├──→ T-31
       ├──→ T-32
       │
       ├──→ T-33 ──→ T-34
       ├──→ T-35
       ├──→ T-36
       └──→ T-37
```

**Parallel execution groups (after T-28):**
- **Group A (UI, independent):** T-29, T-30, T-31, T-32
- **Group B (Backend + specific UI):** T-33, T-34, T-35, T-36, T-37

---

## Technical Notes for Implementers

- All new endpoints: `{success, data, message}` format, Indonesian errors
- RBAC middleware on ALL new routes
- Queue for notifications (never sync)
- Idempotent webhooks
- `cabang_id` scoping on ALL queries
- Branch per ticket: `fase-10-t-XX`
- `/code-review` (Standards + Spec) before merge
- Update `CHANGELOG.md` on completion