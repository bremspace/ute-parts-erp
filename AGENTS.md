# Ute Parts — Agent Instructions

## Mandatory Reading (before any work)
1. `PRD-Frontend-UteParts.md` — UI/UX contract, Design System "Ute Prism", component names
2. `PRD-Backend-UteParts.md` — entities, business rules, API contracts, 7-day roadmap
3. `implement-plan.md` — post-MVP optimization phases (§1 onward), do not skip phases

**Follow the 7-day roadmap in PRD-Backend-UteParts.md §9. Do not skip modules — each day builds on the previous.** After each phase, update `CHANGELOG.md` with a summary of completed work.

## Stack (verifiable facts only)
- **Laravel 13** (PHP 8.3+), TALL stack (Tailwind 4, Alpine.js, Livewire)
- **Vite 8** + `@tailwindcss/vite` v4, `laravel-vite-plugin` v3
- **MySQL** (production), **SQLite :memory:** (tests via phpunit.xml)
- **Dev dependencies**: Pint (formatter), PHPUnit 12, Faker, Mockery, Collision
- Font: Bunny CDN via `laravel-vite-plugin/fonts` (Instrument Sans in `vite.config.js`)
- `.npmrc`: `ignore-scripts=true`, `audit=true`

## Project State
Fresh Laravel 13 scaffold. No modules, models (besides User), migrations (besides defaults), routes, or Livewire components have been built yet. Everything described in the PRDs is yet to be implemented.

**Key:** Code goes in `app/Modules/{Pos,Servis,Wms,Crm,Akunting,Reseller,Marketplace,Rbac}`. Each module owns its Models, Controllers, Livewire components, migrations. Do not scatter module code across default `app/` folders.

Two zones:
- **Backoffice** (`/app/*` routes) — internal staff, desktop-first, dark mode default
- **Marketplace** (`/` public storefront) — customers, mobile-first, light mode default

## Key Constraints (non-obvious)
- **1GB RAM server** (CyberPanel + OpenLiteSpeed): 2-3 PHP-LSAPI workers, OPcache mandatory, `database` queue driver (not Redis unless RAM permits), async reports with 15-min cache, no sync queue dispatch in requests
- **All queries MUST scope to `cabang_id`** (active branch in session). Never leak cross-branch data.
- **RBAC via `spatie/laravel-permission` enforced in route middleware**, not just UI
- **Auth via Laravel Sanctum** (SPA cookie session)
- **PHP binary**: `/usr/local/lsws/lsphp85/bin/php` or `/usr/bin/php8.3` (VPS); verify with `find /usr -name "php" -path "*/bin/*" 2>/dev/null | head -1`

## API Contract Rules
- Every endpoint uses codes like `[API: AUTH-01]`, `[API: POS-01]`, etc. defined in `PRD-Backend-UteParts.md` §5
- Names, routes, and methods are contracts shared with the frontend PRD — do not rename
- All responses follow: `{ "success": true, "data": {...}, "message": "" }`
- **Error messages in Indonesian**

## Commands (project-specific)
```sh
# Setup (install deps + key + migrate + build assets)
composer setup

# Dev server (separate terminals)
composer dev              # runs `php artisan dev`
npm run dev               # Vite dev server

# Tests
composer test             # clears config cache, then runs artisan test
php artisan test --filter=FooTest  # single test

# Formatter
./vendor/bin/pint         # Laravel Pint (PSR-12 style)

# Build assets
npm run build
```
**Note:** `composer setup` requires write permission; ensure current user can write to `/var/www/test.uteparts.id`. If permission denied, run: `sudo chown -R $(whoami):$(whoami) /var/www/test.uteparts.id` then retry.

## Testing
- PHPUnit 12, test suites: `Unit` (`tests/Unit/`), `Feature` (`tests/Feature/`)
- **Tests run on SQLite `:memory:`** — see `phpunit.xml` for env overrides
- PRD requires unit tests for: price tier resolution (§4.2) and service ticket state machine (§4.3) at minimum
- **Never dispatch sync queue in requests** — notifications must go through queue (`database` driver + Supervisor)

## Design System — "Ute Prism" (from PRD-Frontend-UteParts.md §3)
- **Tokens**: `--up-primary` (#5B4FE9), `--up-accent` (#E8873B), `--up-mint` (#1FBF8F), `--up-amber` (#F5A623), `--up-red` (#EF4444)
- **Glassmorphism surfaces**, circuit-line motifs (decorative only, not in data tables)
- **Components**: `GlassCard`, `PrismButton`, `TierBadge`, `StatusPill`, `StockGauge`, `BarcodeScanInput`, `DataTable`, `Toast`, `ConfirmDialog`
- **Headings**: Sora or Plus Jakarta Sans. **Body**: Inter. **Financial numbers**: `tabular-nums`.
- Do not use default Tailwind UI / shadcn templates without customization.

## External Integrations (configure via .env)
- **Duitku** — payment gateway. Webhook at `POST /webhook/duitku`. Must verify signature, be idempotent.
- **Biteship** — shipping/courier rates. Multi-origin (closest branch with stock).
- **WA Gateway** (Fonnte/Wablas/WA Business API) + Email — notifications, **always via queue**.
- **Shopee Open API** — MVP channel for omnichannel adapter. Others (Tokopedia, Blibli, TikTok Shop, Lazada) are phase 2.

## Gotchas (critical operational landmines)
- **Notifications MUST go through queue** (`database` driver + Supervisor). Never send synchronously in request lifecycle.
- **Stock updates to channels must be serialized per SKU** to prevent oversell race conditions.
- **Service ticket status transitions are a strict state machine** — no backward moves except admin override with logged reason.
- **Webhook callbacks (Duitku, marketplace channels) must be idempotent** — duplicate callbacks must not double-adjust stock or create duplicate journal entries.
- **`maatwebsite/laravel-excel` for data import** (Excel/CSV). Always offer dry-run/preview before commit.
- **Use Context7 MCP** to check current Laravel 13 / Livewire / Spatie Permission docs — do not rely on stale knowledge.

**Critical scoped constraints** (from SESSION-STATE.md):
- **`generateNoJurnal` LIKE pattern WAIBS include `$cabangId`** — LIKE without cabangId → jurnal number conflict across branches
- **Channel kredensial: cast `json` (not `encrypted:array`)** — MySQL JSON constraint fails on encrypted value; use `text` column
- **`TiketServisItem` tipe `part` vs `jasa`** — Part: potong stok + stok log; Jasa: jurnal HPP/Pendapatan saja
- **Jurnal servis di onSelesai** — 4 baris: Kas debit / Pendapatan jasa kredit / Pendapatan penjualan sparepart kredit / HPP debit (persediaan kredit)
- **TiketServis status `menunggu_approval` → token** — Token WAIBS di-generate saat setEstimasi, bukan saat updateStatus
- **Livewire computed properties** — WAIBS pass eksplisit di `render()`: `['propName' => $this->computedProp]` — bare `$prop` di blade GAGAL
- **Setup MySQL: pakai `sudo mysql`** — CyberPanel/Unix socket auth — `mysql -uroot -p` FAILS
- **Handler LSAPI bernama `lsphp`** (bukan `lsphp85`) — ExtProcessor di server bernama `lsphp` + socket `lsphp85.sock`; vhost scripthandler harus pakai nama itu
- **OLS vhost format: flat, bukan `virtualHostConfig{}`** — `virtualHostConfig{}` dengan `dirindex`/`enablelscache` → invalid parse → 403

## References (preserve these links)
- `PRD-Frontend-UteParts.md` §3 — Design System tokens and components
- `PRD-Backend-UteParts.md` §4–§9 — Roadmap, kamus data, RBAC matrix, modul rules
- `implement-plan.md` — post-MVP optimization phases
- `CHANGELOG.md` — update after each fase selesai
- Context7 MCP for current Laravel 13 / Livewire / Spatie Permission docs

## Workflow Rules (user-mandated, 2026-09-20 — WAJIB diingat)
- **Model subagent = model main agent (`9router/antig`)** — JANGAN pernah spawn subagent tanpa `model: "9router/antig"` (default `openai/gpt-5.6-luna` rusak/unavailable). Berlaku untuk SEMUA subagent (explorer, fixer, designer, oracle, librarian).
- **Implement-plan checklist flow**: untuk SETIAP plan/implement-plan → (1) baca plan, (2) verifikasi kebutuhan ke kode aktual, (3) eksekusi task, (4) review acceptance criteria, (5) centang checkbox `- [x]` di implement-plan.md HANYA jika sudah terverifikasi benar, (6) lanjut otomatis ke task/fase berikutnya. Jangan centang task yang belum diverifikasi.
- **Izin akses**: user memberi izin penuh semua akses sesi kerja (per 2026-09-20) — lanjut tanpa minta konfirmasi berulang untuk eksekusi kode/deploy/dev.
- **Template checklist**: setiap implement-plan baru harus punya checkbox per task (format `- [ ] T-XX — ...` di Acceptance Criteria) yang dicentang hanya setelah verified.

## Agent skills

### Issue tracker
GitHub Issues (`bremspace/ute-parts-erp`). Uses `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels
Default five canonical roles: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs
Single-context layout: `CONTEXT.md` + `docs/adr/` at repo root. See `docs/agents/domain.md`.