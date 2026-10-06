# Ute Parts ERP — Context & Domain Specifications

## 1. Codebase Purpose
- **Product Name**: Ute Parts ERP
- **Tagline**: Sistem ERP untuk bisnis sparepart & servis motor/sepeda: point of sale, management servis, marketplace, reseller & komisi, HR & payroll, akunting, dan inventori — dalam satu aplikasi modular.
- **Business Model**: Retail sparepart HP/motor + jasa servis, multi-cabang (>5 cabang), multi-gudang.
- **Two Application Zones**:
  - **Backoffice ERP/POS** (`/app/*`): Internal staff, desktop-first, high-density, default dark mode (`--up-ink-900`), keyboard-driven (F2/F4/F6/ESC), barcode-driven.
  - **Marketplace** (`/`): Public storefront, mobile-first, breathing room, default light mode (`--up-ink-50`), tiered customer & reseller self-service.

## 2. Core Goals & Constraints
- **Resource Envelope**: CyberPanel + OpenLiteSpeed on **1 GB RAM** VPS.
- **Execution Architecture**: Modular monolith in `app/Modules/` (15 modules: `Akunting`, `Crm`, `Dashboard`, `Hr`, `Marketplace`, `Notifikasi`, `Omnichannel`, `Pos`, `Rbac`, `Report`, `Reseller`, `Servis`, `Webhook`, `Wms`, `Workflow`).
- **Data Isolation**: Multi-tenancy scoped by active `cabang_id` in session. Never leak cross-branch data.
- **Asynchronous Execution**: Strict database queue driver for jobs (notifications, stock broadcast, export). No synchronous external HTTP in request lifecycle.
- **Accounting Invariance**: Automated double-entry bookkeeping on POS completion, inventory adjustments, and service ticket resolution.

## 3. Critical Files

### Architectural Specs & Contracts
- `README.md` — Product tagline, core module matrix, low-RAM QA pipeline instructions.
- `PRD-Frontend-UteParts.md` — "Ute Prism" design system tokens, screen flows, keyboard shortcuts, navigation contracts.
- `PRD-Backend-UteParts.md` — Domain entity dictionary (§2), RBAC matrix (§3), business rules (§4), and API contracts (`[API: ...]` §5).
- `PRD-Advanced-UteParts.md` — Advanced enterprise specs (omnichannel sync, dynamic reports, automated workflow approvals).
- `SESSION-STATE.md` — Production operating constraints, LSAPI configuration, MySQL socket auth, scoped query requirements.
- `implement-plan.md` — Post-MVP roadmap verification checklist.

### Core Architecture & Routing
- `routes/web.php` & `routes/api.php` — Route entry points for backoffice, marketplace, webhooks.
- `app/Modules/*/routes.php` — Modular domain-driven routes.
- `resources/views/layouts/backoffice.blade.php` — Backoffice layout shell (Sora/Plus Jakarta Sans + Inter + tabular-nums).
- `resources/views/layouts/marketplace.blade.php` — Public storefront layout shell.

### Design System & Visual Assets
- `resources/css/prism-tokens.css` — CSS variables for colors, glassmorphism, Emil Kowalski easing/spring motion curves.
- `resources/views/components/prism/*.blade.php` — UI primitives (`glass-card`, `prism-button`, `status-pill`, `tier-badge`, `stock-gauge`, `data-table`, `barcode-scan-input`).

### Critical Domain Services & State Machines
- `app/Modules/Servis/Services/ServisStateMachine.php` — Strict 7-step repair state machine.
- `app/Modules/Crm/Services/PricingService.php` — Tier-based price resolution (Retail, Silver, Gold, Platinum, Reseller).
- `app/Modules/Akunting/Services/JurnalService.php` — Double-entry general ledger journal generation.
- `app/Modules/Wms/Services/StokDeductionService.php` — Warehouse inventory decrement and serialized stock logs.
- `app/Modules/Workflow/Services/ApprovalService.php` — Multi-tiered threshold approval engine.

## 4. Visual Identity & Design System ("Ute Prism")

### Palette Tokens
- `--up-primary`: `#5B4FE9` (Electric Indigo — primary action, active links)
- `--up-primary-dark`: `#3B2FC9` (Hover / pressed state)
- `--up-accent`: `#E8873B` (Solder Copper — reseller/commission, high-tier badges, promo CTA)
- `--up-mint`: `#1FBF8F` (Signal Mint — success, stock safe, paid in full)
- `--up-amber`: `#F5A623` (Warning, low stock threshold, pending service approval)
- `--up-red`: `#EF4444` (Danger, out of stock, failed transaction)
- `--up-ink-900`: `#0B1020` (Dark canvas, POS background, primary text in light mode)
- `--up-ink-50`: `#F6F7FB` (Light canvas, marketplace background)
- `--up-gradient-signature`: `linear-gradient(135deg, #5B4FE9 0%, #8B7CF6 45%, #E8873B 100%)`

### Surfaces & Typography
- **Glassmorphism**: `rgba(14, 21, 43, 0.75)` (dark) / `rgba(255, 255, 255, 0.65)` (light), `backdrop-blur-xl`, `border: 1px solid rgba(255,255,255,0.08)`.
- **Headings**: Sora / Plus Jakarta Sans (technological, geometric).
- **Body**: Inter.
- **Numbers & Prices**: `font-variant-numeric: tabular-nums` (Indonesian dot separator for thousands, e.g. `Rp 150.000`).
- **Motion Tokens**: `--ease-out: cubic-bezier(0.23, 1, 0.32, 1)`, `--ease-spring: cubic-bezier(0.32, 0.72, 0, 1)`, `--duration-fast: 160ms`.

## 5. Native Product Shapes & Data Structures
- **POS / Sales**: Rapid catalog + scanner pipeline. F2 search, F4 pay, F6 park, ESC cancel. Split payment, immediate double-entry ledger posting.
- **Service Workflow**: Strict Kanban pipeline (`Diterima` → `Diagnosa` → `Estimasi Dikirim` → `Approved Customer` → `Dikerjakan` → `QC` → `Selesai` → `Diambil`). Tokens required for customer estimation approval.
- **Inventory (WMS)**: Serialized tracking (`NomorSeri`), stock opname with discrepancy approval, inter-warehouse transfers (`pending` → `confirmed`).
- **Reseller & Commission**: Multi-actor commission tree (internal staff, technician, external reseller) linked to accounting vouchers.

## 6. Architecture Decision Records (ADR Index)
- `docs/adr/0006-security-scan-approach.md` — Security headers, CSP, HTTPS enforcement
- `docs/adr/0007-pwa-scope.md` — PWA scope & service worker strategy
- `docs/adr/0008-theme-system.md` — localStorage + DB sync, Tailwind `dark:` class strategy
- `docs/adr/0009-thermal-print-architecture.md` — ESC/POS writer, printer abstraction
- `docs/adr/0010-responsive-breakpoints.md` — Breakpoint tokens for Backoffice vs Marketplace
- `docs/adr/0011-ribuan-separator.md` — Indonesian number formatting (dot separator)
- `docs/adr/0012-birthday-field.md` — Pelanggan tanggal_lahir field & birthday campaign
