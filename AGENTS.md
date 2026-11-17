<!-- AUTO-OPTIMIZATION-TRIO-START -->
# ==============================================================================
# GLOBAL OPTIMIZATION DIRECTIVES: CAVEMAN + PONYTAIL + RTK (AUTOSTART ACTIVE)
# ==============================================================================

## 1. CAVEMAN MODE (Token Compression & Zero Fluff)
Respond terse like smart caveman. All technical substance stay. Only fluff die.
- Answer first: Answer, then reason, then next step. Pattern: [thing] [action] [reason]. [next step].
- Kill ceremony: No greeting, hedging, pleasantries, recap, or closer. No "Sure!", "I'd be happy to", "Here is...", etc.
- Short words: "fix" not "implement a solution for". Standard acronyms fine.
- Articles optional, meaning never: Drop a/an/the when sentence still reads in one pass.
- One idea per sentence: 20 words max per sentence, active voice, imperative.
- Payload verbatim: Code blocks, commands, paths, numbers, and errors exact. Never alter payload.
- Safety / Irreversible: Use plain standard prose for security warnings, destructive actions, or user confusion.

## 2. PONYTAIL MODE (Anti-Over-Engineering — Lazy Senior Dev)
The best code is the code never written. Solve the whole problem with the least new code.
- YAGNI: Skip unrequested features, wrappers, speculative configs, or future-proofing.
- Reuse first: Existing helpers in codebase -> standard library -> installed dependencies. Never add packages for trivial tasks.
- Shortest working diff: Fix root cause at source. Finish all affected callers, tests, and fixtures.
- Safety & correctness: NEVER cut validation at trust boundaries, data-loss protections, or security checks.
- End reply with 1-2 lines on what was deferred/skipped and any limits.

## 3. RTK COMMAND COMPRESSION
Terminal outputs are condensed via RTK to preserve LLM context window. Run commands normally; bypass only when raw output is strictly necessary.
<!-- AUTO-OPTIMIZATION-TRIO-END -->

# Bremspace ERP — Agent Instructions

## Mandatory Reading (before any work)
1. `docs/prd/PRD-Frontend-UteParts.md` — UI/UX contract, Design System "Bremspace Prism", component names
2. `docs/prd/PRD-Backend-UteParts.md` — entities, business rules, API contracts, 7-day roadmap
3. `DESIGN.md` — UI design tokens & frontend base knowledge
4. `docs/adr/` — Architecture Decision Records (see list below)

**Follow the 7-day roadmap in docs/prd/PRD-Backend-UteParts.md §9. Do not skip modules — each day builds on the previous.** After each phase, update `docs/CHANGELOG.md` with a summary of completed work.
**White-labeling Note**: Product is fully white-labeled as **Bremspace ERP**. All branding, design tokens, and customer-facing interfaces use the Bremspace identity.

## Stack (verifiable facts only)
- **Laravel 13** (PHP 8.5+), TALL stack (Tailwind 4, Alpine.js, Livewire)
- **Vite 8** + `@tailwindcss/vite` v4, `laravel-vite-plugin` v3
- **MySQL** (production), **SQLite :memory:** (tests via phpunit.xml)
- **Dev dependencies**: Pint (formatter), PHPUnit 12, Faker, Mockery, Collision
- Font: Bunny CDN via `laravel-vite-plugin/fonts` (Instrument Sans in `vite.config.js`)
- `.npmrc`: `ignore-scripts=true`, `audit=true`

## Project State (Current)
**NOT a fresh scaffold** — 15 modules fully implemented in `app/Modules/`:
- **Akunting** — COA, Jurnal, Piutang/Utang, export jobs, depresiasi aset
- **Crm** — Pelanggan, Tier, Broadcast, Konfigurasi, lead & pipeline
- **Dashboard** — Role-based widgets
- **Hr** — Karyawan, absensi shift, KPI, slip payroll, approval payroll
- **Marketplace** — Shop, Cart, Checkout, Customer Account, Duitku, Biteship, Shipping
- **Notifikasi** — Queue jobs, channels (WA/Email), NotificationService
- **Omnichannel** — ShopeeAdapter, webhook handling, command center
- **Pos** — Kasir screen, ESC/POS thermal print, park/hold transactions, clock-in/out
- **Rbac** — Spatie permission, cabang scoping, settings UI, audit log
- **Report** — Drilldown viewer, filter periode, export data format terkurasi
- **Reseller** — Komisi multi-aktor, dashboard, konfigurasi
- **Servis** — Kanban board, state machine, garansi, sparepart, approval token
- **Webhook** — Outbound webhooks
- **Wms** — Produk/SKU, pricelist export per tier, stok log/transfer/opname, PO, barcode, import Excel
- **Workflow** — Multi-level approval inbox & rule engine (GRN, cycle count, PO, payroll)

Each module owns Models, Controllers, Livewire components, Services, Migrations. Two zones:
- **Backoffice** (`/app/*` routes) — internal staff, desktop-first, dark mode default
- **Marketplace** (`/` public storefront) — customers, mobile-first, light mode default

## Key Constraints (non-obvious)
- **2GB+ RAM server** (VPS Murni / Standalone Linux, non-CyberPanel): PHP 8.5+, OPcache mandatory, `database` queue driver (not Redis unless RAM permits), async reports with 15-min cache, no sync queue dispatch in requests
- **All queries MUST scope to `cabang_id`** (active branch in session). Never leak cross-branch data.
- **RBAC via `spatie/laravel-permission` enforced in route middleware**, not just UI
- **Auth via Laravel Sanctum** (SPA cookie session)
- **PHP binary**: `/usr/bin/php8.5` atau `/usr/local/lsws/lsphp85/bin/php` (VPS); verify with `find /usr -name "php" -path "*/bin/*" 2>/dev/null | head -1`

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
- Key test files: `tests/Unit/PricingServiceTest.php`, `tests/Unit/ServisStateMachineTest.php`, `tests/Feature/JurnalServiceTest.php`, `tests/Feature/IntegrasiModulTest.php`

## Design System — "Bremspace Prism" (from PRD-Frontend-UteParts.md §3)
- **Tokens**: `--up-primary` (#5B4FE9), `--up-accent` (#E8873B), `--up-mint` (#1FBF8F), `--up-amber` (#F5A623), `--up-red` (#EF4444)
- **Glassmorphism surfaces**, circuit-line motifs (decorative only, not in data tables)
- **Components**: `GlassCard`, `PrismButton`, `TierBadge`, `StatusPill`, `StockGauge`, `BarcodeScanInput`, `DataTable`, `Toast`, `ConfirmDialog`
- **Headings**: Sora or Plus Jakarta Sans. **Body**: Inter. **Financial numbers**: `tabular-nums`.
- Do not use default Tailwind UI / shadcn templates without customization.
- Theme system: ADR 0008 — localStorage + DB sync, Tailwind `dark:` class strategy

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
- **Setup MySQL: pakai `sudo mysql`** — Linux auth_socket / sudo access
- **Infrastruktur: VPS Murni (Direct Host / Bare Linux, Non-CyberPanel)** — kelola daemon & service via systemd/Supervisor standar Linux

## ADR Index (docs/adr/)
- `0006-security-scan-approach.md` — Security headers, CSP, HTTPS enforcement
- `0007-pwa-scope.md` — PWA scope & service worker strategy
- `0008-theme-system.md` — localStorage + DB sync, Tailwind `dark:` class strategy
- `0009-thermal-print-architecture.md` — ESC/POS writer, printer abstraction
- `0010-responsive-breakpoints.md` — Breakpoint tokens for Backoffice vs Marketplace
- `0011-ribuan-separator.md` — Indonesian number formatting (dot separator)
- `0012-birthday-field.md` — Pelanggan tanggal_lahir field & birthday campaign

## References (preserve these links)
- `docs/prd/PRD-Frontend-UteParts.md` §3 — Design System tokens and components
- `docs/prd/PRD-Backend-UteParts.md` §4–§9 — Roadmap, kamus data, RBAC matrix, modul rules
- `DESIGN.md` — Design System Bremspace Prism base knowledge
- `docs/CHANGELOG.md` — update after each fase selesai
- `SESSION-STATE.md` — running session notes, critical constraints
- `docs/adr/` — Architecture Decision Records
- `docs/agents/` — Agent skill configs (issue-tracker, triage-labels, domain)
- Context7 MCP for current Laravel 13 / Livewire / Spatie Permission docs

## Workflow Rules (user-mandated, 2026-09-20 — WAJIB diingat)
- **Git Multi-Collaborator Workflow**:
  - `main` diproteksi di GitHub (Require Pull Request aktif; non-admin otomatis ditolak push langsung).
  - Deteksi branch aktif sebelum bekerja (`git branch --show-current`).
  - **Branch harian `brem`**:
    - Sebelum ubah kode, tarik update: `git fetch origin && git pull origin main && git pull origin brem`
    - Push harian ke `origin/brem`.
  - **Branch kolaborator (misal `Unas` atau fitur)**:
    - Sebelum ubah kode, tarik update: `git fetch origin && git pull origin main && git pull origin <branch>`
    - Push harian ke branch masing-masing, buat Pull Request ke `main` via GitHub untuk direview.
  - **Protokol Push/Merge ke `main`**:
    - DILARANG KERAS push atau merge langsung ke branch `main` tanpa instruksi eksplisit langsung dari user pada turn tersebut.
    - Setiap rilis/push ke `main` WAJIB atas perintah eksplisit user dan melewati urutan: pull origin main -> merge/commit -> automated check -> push origin main.
    - Tanpa instruksi langsung dari user, push harian hanya boleh ke branch kerja masing-masing (`origin/brem`, `origin/Unas`, dll).
- **Model subagent = model main agent / orchestrator SAAT INI** — JANGAN pernah spawn subagent tanpa `model:` yang sama dengan model orkestrator. Saat ini orkestrator = **Big Pickle** → `model: "opencode/big-pickle"`. Setiap sesi baru: cek model orkestrator (jika berubah, ikuti perubahan itu; verifikasi via `opencode models` bila ragu). Berlaku untuk SEMUA subagent (explorer, fixer, designer, oracle, librarian).
- **Implement-plan checklist flow**: untuk SETIAP plan/implement-plan → (1) baca plan, (2) verifikasi kebutuhan ke kode aktual, (3) eksekusi task, (4) review acceptance criteria, (5) centang checkbox `- [x]` di implement-plan.md HANYA jika sudah terverifikasi benar, (6) lanjut otomatis ke task/fase berikutnya. Jangan centang task yang belum diverifikasi.
- **Izin akses**: user memberi izin penuh semua akses sesi kerja (per 2026-09-20) — lanjut tanpa minta konfirmasi berulang untuk eksekusi kode/deploy/dev.
- **Template checklist**: setiap implement-plan baru harus punya checkbox per task (format `- [ ] T-XX — ...` di Acceptance Criteria) yang dicentang hanya setelah verified.

### Deteksi Akun GitHub & Routing Push Branch (Aturan Tetap)
- **Setiap kali diminta commit dan push**:
  1. **Cek akun GitHub aktif** via `ssh -T git@github.com` atau `gh api user`.
  2. **Jika akun adalah `bremspace` (owner)**:
     - Target branch push WAJIB ke branch **`brem`** (`git push origin brem`).
     - Pastikan perubahan disinkronkan ke branch `brem`.
  3. **Jika akun BUKAN `bremspace`** (misal collaborator `Unas`):
     - Target branch push ke branch **`Unas`** (`git push origin Unas`).
  4. **Proteksi `.gitignore` untuk AI Agent Setup**:
     - Direktori `.agents/` dan `scripts/` DILARANG di-ignore. Pastikan konfigurasi skills, rules, plugins, dan MCP tetap terlacak di repositori.

### Aturan Git Kolaborasi (Permanen — Branch Unas)
- Branch kerja eksklusif: **Unas**. Semua modifikasi kode, commit, dan push HANYA di branch Unas.
- WAJIB sebelum mengedit kode apa pun di setiap sesi/task:
  ```sh
  git fetch origin
  git pull origin main
  git pull origin Unas
  ```
- DILARANG KERAS push atau merge langsung ke branch `main`.
- Push harian hanya ke remote branch sendiri:
  ```sh
  git push origin Unas
  ```
- Penggabungan ke main wajib melalui Pull Request (PR) dari Unas ke main dan menunggu review/merge dari pemilik repositori (tim branch brem).

## Agent Skills (configured in docs/agents/)
### Issue tracker
GitHub Issues (`bremspace/ute-parts-erp`). Uses `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels
Default five canonical roles: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs
Single-context layout: `CONTEXT.md` + `docs/adr/` at repo root. See `docs/agents/domain.md`.
**Note:** `CONTEXT.md` does not exist yet — create it at session start if domain glossary needed.

## Codebase Memory (codebase-memory-mcp)
Project indexed as `var-www-test.uteparts.id-staging`. Use MCP tools for structural queries:
- `search_graph` — find functions, classes, routes by pattern
- `trace_path` — trace call chains (inbound/outbound)
- `get_code_snippet` — read specific function/class source
- `check_index_coverage` — verify no gaps before negative claims
- `query_graph` — Cypher for complex patterns
- `get_architecture` — high-level summary

Prefer graph tools over grep/glob for code discovery. Fall back to grep for string literals, configs, non-code files.

## Universal Agent Setup & Environment Sync ("pasang bre")
Repository ini menyimpan konfigurasi lengkap seluruh ekosistem AI agent (bebas dari credential pribadi):
- **Skills**: `.agents/skills/` (175+ specialized agent skills)
- **Rules**: `.agents/rules/` (Caveman, Ponytail, RTK, Codebase Memory)
- **Plugins**: `.agents/plugins/` (Caveman, Ponytail, RTK)
- **MCP Configs**: `.agents/mcp/` (Codebase memory graph schemas & servers)
- **Shortcuts & Switcher**: `scripts/anti.sh`, `scripts/agent-skills.py`, `scripts/pasang-bre.sh`

### Perintah "pasang bre":
Kapan pun user memberi instruksi **"pasang bre"**, **"pasang brem"**, atau **/pasang-bre**, AI agent WAJIB langsung menjalankan:
```bash
bash scripts/pasang-bre.sh
```
Script tersebut akan menginstal seluruh konfigurasi, menyambungkan path skills/plugins, menyiapkan shortcut `anti`, dan mengaktifkan Trio Optimization (Caveman + Ponytail + RTK) secara otomatis pada Antigravity CLI, OpenCode, dan Hermes Agent.

Untuk mengontrol trio optimization secara global:
- `agent-skills on` (aktifkan semua)
- `agent-skills off` (matikan semua, kembali ke normal mode)
- `agent-skills status` (cek status ketiga agent)