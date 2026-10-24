# Master Implementation Plan: Frontend & Mobile Design Polish (Ute Prism & Emil Design Eng)

**Goal:** Polish frontend and mobile ergonomic layouts (<768px) across all 15 ERP modules and customer storefront, eliminating AI slop, sticky hovers, viewport blowouts, and missing dark mode styling.

## Acceptance Criteria

- [x] **T-01 — Micro-interaction transitions & origins:** Sub-300ms transitions, custom ease-out curves (`--ease-out`), explicit properties (no `transition: all`), tactile active scale on pagination and triggers (`active:scale-95`).
- [x] **T-02 — HR module dark mode & form styling:** Fix `resources/views/modules/hr/absensi.blade.php` and `resources/views/modules/hr/komisi-skema.blade.php` to add complete `dark:` classes, Ute Prism tokens, glassmorphism, and responsive tables.
- [x] **T-03 — POS mobile cashier viewport & controls:** Verify cashier control bar, floating cart drawer/mode, and touch targets (≥44px) on small screens without horizontal page blowout. Removed hardcoded `bg-ink-900 text-white` classes.
- [x] **T-04 — Servis & CRM Kanban boards:** Ensure smooth horizontal touch scrolling (`touch-pan-x scrollbar-none`), visible status indicators with static tailwind classes, and responsive card touch states.
- [x] **T-05 — WMS action bars & sub-modul navigation:** Responsive 2-tier navigation tabs with 44px touch targets, overflow wrap prevention, mobile action sheet/button ergonomics.
- [x] **T-06 — Marketplace storefront mobile ergonomics:** Catalog sticky search/filter bar, booking-servis pattern lock mobile touch guard (`touch-action: none; @pointerdown`), responsive checkout summary.
- [x] **T-07 — Mobile ergonomics & touch target review:** Ensure all interactive elements adhere to min 44px tap targets and hover styles are gated or safe on mobile devices.
- [x] **T-08 — Regression build & test suite:** Run `npm run build`, `./vendor/bin/pint --test`, and PHPUnit tests (`php artisan test --filter=...`) to ensure clean assets and 100% test pass rate.
