<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

# Ute Parts — Agent Instructions

## Mandatory Reading (before any work)

1. `PRD-Frontend-UteParts.md` — UI/UX contract, Design System "Ute Prism", component names
2. `PRD-Backend-UteParts.md` — entities, business rules, API contracts, 7-day roadmap

Follow the 7-day roadmap in `PRD-Backend-UteParts.md` §9. Do not skip modules — each day builds on the previous.

## Stack

- **Laravel 13** (PHP 8.3+), TALL stack (Tailwind 4, Alpine.js, Livewire/Volt, Laravel)
- **Vite 8** + `@tailwindcss/vite` v4, `laravel-vite-plugin` v3
- **MySQL** (production), **SQLite :memory:** (tests via phpunit.xml)
- **Dev dependencies**: Pint (formatter), PHPUnit 12, Pail (log tail), Pao, Faker, Mockery, Collision
- Font: Bunny CDN via `laravel-vite-plugin/fonts` (Instrument Sans configured in `vite.config.js`)
- `.npmrc`: `ignore-scripts=true`, `audit=true`

## Project State

Fresh Laravel 13 scaffold. No modules, models (besides User), migrations (besides defaults), routes, or Livewire components have been built yet. Everything described in the PRDs is yet to be implemented.

## Architecture (from PRD)

Modular monolith. Code goes in `app/Modules/{Pos,Servis,Wms,Crm,Akunting,Reseller,Marketplace,Rbac,Omnichannel}`. Each module has its own Models, Controllers, Livewire components, migrations, etc. Do not scatter module code across the default `app/` folders.

Two zones:
- **Backoffice** (`/app/*` routes) — internal staff, desktop-first, dark mode default
- **Marketplace** (`/` public storefront) — customers, mobile-first, light mode default

## Key Constraints

- **1GB RAM server** (CyberPanel + OpenLiteSpeed): 2-3 PHP-LSAPI workers, OPcache mandatory, `database` queue driver (not Redis unless RAM permits), async reports with 15-min cache, no sync queue dispatch in requests.
- All queries MUST scope to `cabang_id` (active branch in session). Never leak cross-branch data.
- RBAC via `spatie/laravel-permission` enforced in **route middleware**, not just UI.
- Auth via Laravel Sanctum (SPA cookie session).

## API Contract Rules

- Every endpoint uses codes like `[API: AUTH-01]`, `[API: POS-01]`, etc. defined in `PRD-Backend-UteParts.md` §5.
- Names, routes, and methods are contracts shared with the frontend PRD — do not rename.
- All responses follow: `{ "success": true, "data": {...}, "message": "" }`.
- Error messages in Indonesian.

## Commands

```sh
# Setup (install deps + key + migrate + build assets)
composer setup

# Dev server
composer dev              # runs `php artisan dev`
npm run dev               # Vite dev server (separate terminal)

# Tests
composer test             # clears config cache, then runs artisan test
php artisan test --filter=FooTest  # single test

# Formatter
./vendor/bin/pint         # Laravel Pint (PSR-12 style)

# Build assets
npm run build
```

## Testing

- PHPUnit 12, test suites: `Unit` (`tests/Unit/`), `Feature` (`tests/Feature/`)
- Tests run on SQLite `:memory:` — see `phpunit.xml` for env overrides
- PRD requires unit tests for: price tier resolution (§4.2) and service ticket state machine (§4.3) at minimum

## Design System — "Ute Prism"

Defined in `PRD-Frontend-UteParts.md` §3. Key tokens:
- `--up-primary` (#5B4FE9), `--up-accent` (#E8873B), `--up-mint` (#1FBF8F), `--up-amber` (#F5A623), `--up-red` (#EF4444)
- Glassmorphism surfaces, circuit-line motifs (decorative only, not in data tables)
- Components: `GlassCard`, `PrismButton`, `TierBadge`, `StatusPill`, `StockGauge`, `BarcodeScanInput`, `DataTable`, `Toast`, `ConfirmDialog`
- Headings: Sora or Plus Jakarta Sans. Body: Inter. Financial numbers: `tabular-nums`.
- Do not use default Tailwind UI / shadcn templates without customization.

## External Integrations (configure via .env)

- **Duitku** — payment gateway. Webhook at `POST /webhook/duitku`. Must verify signature, be idempotent.
- **Biteship** — shipping/courier rates. Multi-origin (closest branch with stock).
- **WA Gateway** (Fonnte/Wablas/WA Business API) + Email — notifications, always via queue.
- **Shopee Open API** — MVP channel for omnichannel adapter. Others (Tokopedia, Blibli, TikTok Shop, Lazada) are phase 2.

## Gotchas

- Notifications MUST go through queue (`database` driver + Supervisor). Never send synchronously in request lifecycle.
- Stock updates to channels must be serialized per SKU to prevent oversell race conditions.
- Service ticket status transitions are a strict state machine — no backward moves except admin override with logged reason.
- Webhook callbacks (Duitku, marketplace channels) must be idempotent — duplicate callbacks must not double-adjust stock or create duplicate journal entries.
- `maatwebsite/laravel-excel` for data import (Excel/CSV). Always offer dry-run/preview before commit.
- Use Context7 MCP to check current Laravel 13 / Livewire / Spatie Permission docs — do not rely on stale knowledge.
