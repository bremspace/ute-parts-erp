# ARCHITECTURE AUDIT & REMEDIATION PRD
**Bremspace ERP — Enterprise TALL Stack Platform**  
*White-Labeled Operational Architecture*  
*Prepared by: Principal Laravel Architect & Senior Software Auditor*  
*Date: November 3, 2026*  
*Target Environment: Linux (Bare-metal / Standalone VPS), 2GB+ RAM, PHP 8.5 (OPcache & JIT enabled), Laravel 13, Livewire 3, MySQL 8 / SQLite memory*

---

## 1. Executive Summary & Audit Context

### 1.1 Context, White-Labeling & Infrastructure Specifications
Bremspace ERP is a fully white-labeled multi-branch enterprise resource planning system comprising 15 integrated modules in `app/Modules/` (Akunting, Crm, Dashboard, Hr, Marketplace, Notifikasi, Omnichannel, Pos, Rbac, Report, Reseller, Servis, Webhook, Wms, Workflow). The product features zero vendor-locked legacy branding, adopting the **Bremspace Prism Enterprise** design system.

The platform orchestrates two primary operational surfaces:
1. **Backoffice (`/app/*`)**: Desktop-first, dark mode default, operational staff (POS Kasir, WMS inventory, accounting ledger, service repair board, HR payroll).
2. **Marketplace (`/*`)**: Mobile-first, light mode default, public customer portal and storefront.

**Server & Runtime Infrastructure**:
- **RAM**: **2GB+ RAM** dedicated host. A 2GB+ baseline provides dedicated headroom for Linux OS (~250MB), MySQL 8 InnoDB buffer pool (~600MB–800MB), Redis/Queue storage, and concurrent PHP 8.5-FPM worker pools.
- **PHP**: **PHP 8.5+** with mandatory OPcache, modern JIT optimizations, and strict type handling.
- **Queue**: `database` queue driver backed by systemd/Supervisor worker daemons.
- **White-label Guarantee**: Complete brand neutrality under **Bremspace ERP**; all UI tokens (`--up-primary` aliases), documentation, notifications, and metadata reference Bremspace.

### 1.2 Audit Scope & Methodology
A comprehensive architectural, code quality, domain boundary, and database performance audit was conducted across the 103,113 lines of PHP codebase, 15 modules, database migrations, Livewire components, and dependency rules (`deptrac.php`, `phpstan.neon`).

Findings are categorized by impact severity:
- **[Critical Issues]**: Direct risks of data corruption, transaction collision, OOM process termination, or ledger anomalies.
- **[Architectural Violations]**: Structural decay, broken domain boundaries, God-classes, and duplicated business orchestration.
- **[Performance Bottlenecks]**: Database table scans, N+1 query patterns, Livewire state hydration bloat, and CPU/memory saturation.

---

## 2. Categorized Findings & Root-Cause Analyses

```
==================================================================================================
SEVERITY MATRIX
--------------------------------------------------------------------------------------------------
ID      Category                    Area                      Blast Radius      Risk Level
--------------------------------------------------------------------------------------------------
CRIT-01 Concurrency Race Condition  POS Checkout              High (Financial)  P0 - IMMEDIATE
CRIT-02 Memory Spike / OOM Risk     WMS Import Engine         Server Stability  P0 - IMMEDIATE
CRIT-03 Zombie Queue Duplication    Reports & Exports         Resource Waste    P0 - IMMEDIATE
CRIT-04 Base64 DB Storage           Servis & Retur Unit       DB Crash (1038)   P0 - IMMEDIATE
ARCH-01 God Livewire Components     WMS / POS / RBAC          Maintainability   P1 - HIGH
ARCH-02 Duplicated Checkout Logic   POS Livewire & Controller Data Drift        P1 - HIGH
ARCH-03 Shotgun Surgery in Workflow Workflow ApprovalService Modularity        P1 - HIGH
ARCH-04 Dual Stock Mutation Logging WMS Stock Deductions      Data Integrity    P1 - HIGH
ARCH-05 Spiderweb Dependencies      Inter-module Boundaries   Architecture      P1 - HIGH
ARCH-06 Blocking HTTP in Livewire   RBAC Settings             Worker Starvation P1 - HIGH
PERF-01 Missing High-Volume Indexes Transaksi, Stok, Jurnal   Query Latency     P2 - MEDIUM
PERF-02 Full-Table Scan on Render   Wms ProdukTab             CPU / RAM Spike   P2 - MEDIUM
PERF-03 State Hydration Bloat       POS / Wms Livewire        Network / Client  P2 - MEDIUM
PERF-04 Missing Lazy Load Guard     AppServiceProvider        N+1 Regression    P2 - MEDIUM
PERF-05 Uncached Layout Queries     User Cabang Access        Redundant Queries P2 - MEDIUM
==================================================================================================
```

---

### 2.1 [Critical Issues] (P0 — Immediate Remediation Required)

#### CRIT-01: Concurrency Race Condition in Invoice Number Generation
- **Affected Files**:
  - `app/Modules/Pos/Livewire/PosKasir.php` (Lines 2492–2495)
  - `app/Modules/Pos/Controllers/PosController.php` (Lines 338–341)
- **Root Cause**:
  ```php
  $countToday = Transaksi::whereDate('created_at', now()->toDateString())
      ->where('cabang_id', $cabangId)
      ->count() + 1;
  $noTransaksi = sprintf('TRX-C%02d-%s-%04d', $cabangId, $today, $countToday);
  ```
  Invoice generation relies on a non-locking `count() + 1` query. When two cashiers complete checkout concurrently within the same branch, both queries read the identical count, resulting in identical `no_transaksi` values. The second transaction triggers a fatal SQL unique constraint violation (`Duplicate entry for key 'transaksi_no_transaksi_unique'`), causing transaction failure and lost sales.
- **Risk Assessment**: Financial loss, POS cashier blocking during peak retail hours, incomplete ledger entries.
- **Remediation**: Implement atomic sequencing via a dedicated sequence lock table or database row lock (`SELECT ... FOR UPDATE`), or generate cryptographically collision-resistant alphanumeric suffixes with branch/date prefix.

---

#### CRIT-02: Unbounded Memory Spike Risk via 512M Limit Bypass in Import Engine
- **Affected Files**:
  - `app/Modules/Wms/Services/ImportProdukService.php` (Lines 1076–1077, 1327–1328)
- **Root Cause**:
  ```php
  @ini_set('memory_limit', '512M');
  @set_time_limit(0);
  ```
  The Excel/CSV product import engine attempts to bypass memory limits up to 512MB to process large catalogs in memory. Even with a 2GB+ server baseline, allowing a single worker thread to consume 512MB reduces concurrency drastically: 2 or 3 concurrent imports or heavy report exports will exhaust available memory, destabilizing MySQL and PHP-FPM workers.
- **Risk Assessment**: Server worker starvation, sudden process termination under concurrent batch tasks.
- **Remediation**: Eliminate `@ini_set('memory_limit', '512M')`. Enforce chunked stream parsing (`OpenSpout` or `fgetcsv` / generator-based stream) with a strict maximum 64MB memory ceiling and background queued worker dispatch.

---

#### CRIT-03: Zombie Queue Double-Execution Antipattern in Reports & Exports
- **Affected Files**:
  - `app/Modules/Servis/Livewire/ServisBoard.php` (Lines 428–448)
  - `app/Modules/Akunting/Livewire/AkuntingDashboard.php` (Lines 350–371)
  - `app/Modules/Akunting/Livewire/LaporanPajak.php` (Lines 144–165)
  - `app/Modules/Reseller/Livewire/ResellerDashboard.php` (Lines 195–216)
  - `app/Modules/Wms/Livewire/StokTab.php` (Lines 235–256)
- **Root Cause**:
  ```php
  // Step 1: Dispatched to background queue
  dispatch(new ExportLaporanJob(jenis: $jenis, ...));

  // Step 2: Executed synchronously in the same HTTP request!
  $path = app(ExportLaporanService::class)->export($jenis, ...);
  $fullPath = Storage::disk('local')->path($path);
  return response()->download($fullPath, ...);
  ```
  In 5 separate modules, the export action dispatches a queue job to satisfy async guidelines, but immediately calls the export service synchronously on the web thread to return a binary file download.
- **Impact**:
  1. The user request blocks synchronously anyway, nullifying queue advantages.
  2. The background worker picks up the job and re-executes the exact same heavy calculation and file generation, saving a duplicate file to disk and consuming double CPU and memory.
- **Remediation**: Unify into either:
  - Direct streamed download (`StreamedResponse` with generator) for fast/filtered reports (<5,000 rows).
  - Pure asynchronous export via queue with user notification and download link in Notification Center for heavy datasets. Discontinue dual invocation.

---

#### CRIT-04: Base64 Multimedia Storage in MySQL Relational Columns
- **Affected Files**:
  - `database/migrations/2026_09_16_000018_create_tiket_servis_table.php` (Line 24: `$table->json('foto_unit')->nullable();`)
  - `database/migrations/2026_09_25_020000_add_created_at_indexes_to_tiket_servis_table.php` (Documentation note: measured at 431,406 bytes per row)
  - `database/migrations/2026_10_18_120000_optimize_return_management_and_pools.php` (Line 24: `$table->json('foto_bukti')->nullable();`)
- **Root Cause**:
  Raw base64 images (several hundred kilobytes per photo) are stored directly inside JSON columns of relational tables (`tiket_servis`, `return_penjualan`, `return_penjualan_item`).
- **Impact**:
  1. MySQL `sort_buffer_size` overflow: Any query performing `ORDER BY` with `SELECT *` triggers `QueryException 1038 "Out of sort memory"` because rows exceed 256KB sort buffer.
  2. InnoDB buffer pool thrashing: Querying 50 tickets loads >20MB of binary data into RAM, evicting relational indexes and hot data pages.
  3. Inflated database backup and replication payloads.
- **Remediation**: Transition image ingestion to Laravel Storage (`storage/app/public/...`), storing only relative paths or CDN URLs in JSON arrays. Provide an idempotent data migration script to extract existing base64 payloads to disk files.

---

### 2.2 [Architectural Violations] (P1 — Structural & Domain Integrity)

#### ARCH-01: God Livewire Components Violating Single Responsibility Principle
- **Affected Components**:
  - `app/Modules/Wms/Livewire/ProdukTab.php` (4,330 lines)
  - `app/Modules/Pos/Livewire/PosKasir.php` (4,014 lines)
  - `app/Modules/Rbac/Livewire/SettingsRbac.php` (3,672 lines)
  - `app/Modules/Akunting/Livewire/AkuntingDashboard.php` (2,075 lines)
  - `app/Modules/Pos/Livewire/RiwayatTransaksiIndex.php` (1,971 lines)
  - `app/Modules/Servis/Livewire/ServisBoard.php` (1,937 lines)
- **Root Cause**:
  Components accumulate dozens of responsibilities: handling multi-step modals, inline CRUD forms, complex financial calculations, barcode lookup, data export, and UI tab state.
- **Impact**:
  - State hydration overhead: Hundreds of public properties are serialized and signed with HMAC checksums on every user keystroke.
  - Testability nightmare: Over 4,000 lines in a single class makes unit testing brittle and component reusability impossible.
- **Remediation**: Decompose monolithic components into focused child components or dedicated Livewire Traits / Action classes (e.g., `PosCartManager`, `PosPaymentProcessor`, `ProdukFilterManager`, `ProdukBulkEditor`).

---

#### ARCH-02: Duplicated Business Logic Between Livewire & API Controllers
- **Affected Files**:
  - `app/Modules/Pos/Livewire/PosKasir.php` (Lines 2490–2640)
  - `app/Modules/Pos/Controllers/PosController.php` (Lines 335–460)
- **Root Cause**:
  Checkout orchestration (transaction creation, stock deduction, serial number validation, customer loyalty point calculation, accounting journal entries) is duplicated verbatim in both the Livewire component and the REST API controller.
- **Impact**:
  High probability of business logic divergence. Any bug fix, tax adjustment, or stock rule modified in `PosKasir.php` is easily missed in `PosController.php`.
- **Remediation**: Extract a unified, pure domain service: `App\Modules\Pos\Services\PosCheckoutService`. Both `PosKasir` and `PosController` must delegate all checkout orchestration to this single service.

---

#### ARCH-03: Shotgun Surgery in Workflow Approval Engine
- **Affected Files**:
  - `app/Modules/Workflow/Services/ApprovalService.php` (Lines 6–20, and execution methods)
  - `deptrac.php` (Ruleset for CoreWorkflow)
- **Root Cause**:
  `ApprovalService` directly imports and couples to concrete domain entities and services across multiple modules (`Grn`, `ReturnPembelian`, `ReturnPenjualan`, `PurchaseOrder`, `CycleCount`, `Pelanggan`). It inspects entity types using procedural switch/match blocks to execute domain actions upon approval.
- **Impact**:
  Whenever a new approval workflow is introduced (e.g., asset write-off, payroll approval), `ApprovalService` in the Core layer must be modified. This directly violates the Open/Closed Principle (OCP) and causes tight reverse coupling from Core to Domain modules.
- **Remediation**: Implement an `Approvable` interface with domain-specific handler classes (`ApprovalHandlerInterface`) or event-driven approval resolutions (`ApprovalGrantedEvent`, `ApprovalRejectedEvent`) registered via Service Providers.

---

#### ARCH-04: Dual Stock Mutation Logging Architecture
- **Affected Tables & Models**:
  - `stok_log` (`App\Modules\Wms\Models\StokLog`)
  - `stock_mutation_log` (`App\Modules\Wms\Models\StockMutationLog`)
- **Root Cause**:
  Two parallel tables log inventory movements. Write calls to both tables are scattered across 18+ locations, including inside Livewire components (`TransferTab.php`, `OpnameTab.php`) and controllers (`WmsController.php`), instead of a centralized inventory ledger.
- **Impact**:
  Risk of drift where one table is updated and the other is omitted (e.g., direct DB update or failed second insert). Increased write I/O on disk.
- **Remediation**: Route all stock deductions and additions strictly through `StokDeductionService` / `ProdukService`. Deprecate manual creation calls in controllers and Livewire views.

---

#### ARCH-05: Spiderweb Dependency Graph Across ERP Modules
- **Evidence**:
  Inspection of `deptrac.php` reveals that virtually all 15 modules have cross-boundary access rules permitted (e.g., `Akunting` accessing `Pos`, `Crm`, `Wms`, `Servis`, `Reseller`, `Hr`; `Pos` accessing `Crm`, `Hr`, `Reseller`, `Wms`, `Servis`, `Marketplace`, `Omnichannel`).
- **Root Cause**:
  Lack of strict domain contracts or facade interfaces between modules. Domain models are queried directly across boundaries.
- **Remediation**: Establish explicit service contracts for inter-module interactions, prohibiting direct foreign model mutations.

---

#### ARCH-06: Synchronous Blocking External HTTP Calls in Livewire Actions
- **Affected Files**:
  - `app/Modules/Rbac/Livewire/SettingsRbac.php` (Lines 1154–1174)
- **Root Cause**:
  `SettingsRbac::testKoneksiWa()` executes `Http::timeout(12)->post(...)` directly on the Livewire request thread.
- **Impact**:
  If the external WhatsApp gateway latency spikes or drops packets, the PHP-FPM worker thread is locked for up to 12 seconds, starving available PHP workers.
- **Remediation**: Dispatch WhatsApp gateway health check as a background test job or reduce connection timeout to 3 seconds with graceful failure fallback.

---

### 2.3 [Performance Bottlenecks] (P2 — Database & Lifecycle Optimization)

#### PERF-01: Missing High-Volume Composite Indexes
1. **`transaksi`**:
   - Current indexes: `['cabang_id', 'status']`, `['kasir_id']`, `['created_at']`.
   - Frequent query: `WHERE cabang_id = ? AND created_at BETWEEN ? AND ?` (used in POS history, finance reconciliations, dashboards).
   - Defect: Lacks `['cabang_id', 'created_at']`. MySQL must perform index merge or scan all records for that branch.
2. **`stok_log`**:
   - Current indexes: `['gudang_id', 'produk_id']`, `['created_at']`.
   - Frequent query: `WHERE produk_id = ? ORDER BY created_at DESC` (product stock audit trail).
   - Defect: Leftmost index prefix requires `gudang_id`. Queries filtering only on `produk_id` perform a full table scan. Missing `['produk_id', 'created_at']`.
3. **`jurnal_akuntansi`**:
   - Current indexes: `['no_jurnal']`, `['akun_coa_id', 'tanggal']`, `['cabang_id', 'tanggal']`.
   - Frequent query: `WHERE referensi_tipe = ? AND referensi_id = ?` (audit subledger lookup).
   - Defect: Missing index on `['referensi_tipe', 'referensi_id']`.

---

#### PERF-02: Full-Table Iterations on Every Livewire Render in `ProdukTab`
- **Affected File**: `app/Modules/Wms/Livewire/ProdukTab.php` (Lines 4140–4180)
- **Root Cause**:
  ```php
  // Executed on EVERY render:
  $merekHpDariProduk = Produk::query()->whereNotNull('brand_kompatibel')->pluck('brand_kompatibel')->...;
  $kompatJsonList = Produk::query()->whereNotNull('kompatibilitas_hp')->pluck('kompatibilitas_hp');
  foreach ($kompatJsonList as $kJson) { ... }
  ```
  On every Livewire update (search input, checkbox toggle, pagination), the component queries the entire `produk` table to pluck compatibility strings and parses JSON blobs in PHP memory.
- **Impact**:
  As the product catalog grows to 10,000+ items, each Livewire request consumes 40MB+ RAM and several CPU seconds, creating severe UI lag and server CPU spikes.
- **Remediation**: Cache `brandKompatibelList` and distinct attribute lists in cache with tags or duration (e.g., 60 minutes) or refresh only upon catalog mutations.

---

#### PERF-03: Excessive Livewire State Hydration Overhead
- **Affected Components**: `PosKasir.php`, `ProdukTab.php`, `SettingsRbac.php`
- **Root Cause**:
  Components expose 50+ public properties representing UI toggle states (`$showBulkEditModal`, `$showPaymentModal`, `$visibleFilters`, `$showSalinDataModal`).
- **Impact**:
  Livewire serializes every public property into the JSON payload sent to the browser and deserializes it back on the server. This results in payload sizes exceeding 100KB per interaction and redundant server processing.
- **Remediation**: Delegate pure UI modal toggles and client-only state to Alpine.js (`x-data="{ showModal: false }"`). Only retain data properties required for backend persistence in Livewire.

---

#### PERF-04: Lack of Eloquent Lazy Loading Prevention Guard
- **Affected File**: `app/Providers/AppServiceProvider.php`
- **Root Cause**:
  `Model::preventLazyLoading(! app()->isProduction())` is absent. Developers and automated tests cannot catch inadvertent N+1 query introduction during local feature development.
- **Remediation**: Register `Model::preventLazyLoading(! $this->app->isProduction());` in `AppServiceProvider::boot()`.

---

#### PERF-05: Uncached Redundant Layout Queries
- **Affected File**: `app/Models/User.php` (`daftarCabangAkses()`) via `layouts/backoffice.blade.php` (Line 881)
- **Root Cause**:
  Every backoffice request executes `auth()->user()->daftarCabangAkses()`, triggering fresh database queries to `cabang` or pivot tables without in-memory or request-level memoization.
- **Remediation**: Cache the result per request lifecycle using static property memoization on the `User` model.

---

## 3. Phased Remediation Roadmap

The remediation roadmap follows a disciplined 4-phase sequence designed to guarantee **zero breaking changes**, **uninterrupted business behavior**, and **atomic commits**.

```
+---------------------------------------------------------------------------------------+
| PHASE 1: Critical System Stability & Database Performance (P0 / PERF)                  |
| - Composite indexes for Transaksi, StokLog, JurnalAkuntansi                           |
| - Atomic transaction number sequencing (lockForUpdate)                                |
| - Eliminate double-execution export antipattern                                       |
| - Lazy loading prevention in local/testing env                                        |
+-------------------------------------------+-------------------------------------------+
                                            |
                                            v
+---------------------------------------------------------------------------------------+
| PHASE 2: Domain Boundary Consolidation & Service Layer Extraction (ARCH)              |
| - Centralize POS Checkout Service (PosKasir & PosController unified)                  |
| - Unify stock mutation logging under StokDeductionService                             |
| - Decouple Workflow ApprovalService via Approvable Handlers                           |
| - Add request-level memoization for Cabang access                                     |
+-------------------------------------------+-------------------------------------------+
                                            |
                                            v
+---------------------------------------------------------------------------------------+
| PHASE 3: Livewire Component Encapsulation & Hydration Optimization                   |
| - Cache dynamic brand/compatibility queries in ProdukTab                              |
| - Shift UI modal toggling from Livewire public props to Alpine.js                     |
| - Extract child components / traits from God components                               |
+-------------------------------------------+-------------------------------------------+
                                            |
                                            v
+---------------------------------------------------------------------------------------+
| PHASE 4: Memory Hardening & Multimedia Storage Normalization                         |
| - Replace memory_limit 512M in ImportProdukService with stream generator parsing      |
| - Migrate base64 image storage to Laravel Storage disk with file migration command     |
| - Asynchronous WhatsApp gateway ping with bounded 3s timeout                          |
+---------------------------------------------------------------------------------------+
```

---

## 4. Phase-by-Phase Task Breakdown & Verification Matrix

### Phase 1: Critical System Stability & Database Performance
- [x] **T-01: Create Composite Indexes Migration**
  - Add composite index `transaksi_cabang_created_at_idx` on `transaksi(cabang_id, created_at)`.
  - Add composite index `stok_log_produk_created_at_idx` on `stok_log(produk_id, created_at)`.
  - Add composite index `jurnal_akuntansi_ref_idx` on `jurnal_akuntansi(referensi_tipe, referensi_id)`.
  - *Acceptance Criteria*: Migrations execute idempotently; `EXPLAIN` query on POS history date filter uses index instead of filesort.
- [x] **T-02: Fix Invoice Number Concurrency Lock**
  - Wrap transaction generation in `TransaksiSequenceService` with database row lock or atomic sequence table.
  - *Acceptance Criteria*: Concurrent checkouts within the same millisecond in the same branch produce sequential, collision-free numbers without unique key exceptions.
- [x] **T-03: Eliminate Double-Execution Export Antipattern**
  - Refactor export handlers in `ServisBoard`, `AkuntingDashboard`, `LaporanPajak`, `ResellerDashboard`, and `StokTab`.
  - Stream immediate download for user-requested reports without dispatching phantom queue jobs.
  - *Acceptance Criteria*: Single execution per export; zero orphan jobs queued.
- [x] **T-04: Enable Lazy Loading Guard in AppServiceProvider**
  - Add `Model::preventLazyLoading(! $this->app->isProduction());`.
  - *Acceptance Criteria*: Unit & feature tests pass without unexpected lazy loading violations.

---

### Phase 2: Domain Boundary Consolidation & Service Layer Extraction
- [x] **T-05: Extract Unified `PosCheckoutService`**
  - Create `app/Modules/Pos/Services/PosCheckoutService.php`.
  - Encapsulate transaction creation, stock deduction, SN claiming, and journal posting.
  - Refactor `PosKasir::selesaikanTransaksi()` and `PosController::store()` to delegate to `PosCheckoutService`.
  - *Acceptance Criteria*: POS UI checkout and API checkout produce identical ledger, stock, and transaction records. 100% test pass in `PosKasirTest` and `PosControllerTest`.
- [x] **T-06: Centralize Stock Mutation Logging**
  - Consolidate dual write calls (`StokLog` and `StockMutationLog`) strictly within `StokDeductionService` and `ProdukService`.
  - Remove direct model instantiation from Livewire tabs (`TransferTab`, `OpnameTab`).
  - *Acceptance Criteria*: All stock changes produce synchronized entries in both logs with actor audit trails.
- [x] **T-07: Decouple Workflow Approval Engine**
  - Refactor `ApprovalService` to resolve approvable handlers via container/registry rather than monolithic concrete imports.
  - *Acceptance Criteria*: Deptrac dependency rules reflect clean unidirectional boundaries.
- [x] **T-08: Memoize User Cabang Access**
  - Add request-level static memoization to `User::daftarCabangAkses()`.
  - *Acceptance Criteria*: Layout renders execute zero duplicate branch queries per request.

---

### Phase 3: Livewire & Frontend Reactive State Optimization
- [x] **T-09: Optimize `ProdukTab::render()` Queries**
  - Cache brand compatibility lists (`brandKompatibelList`) with a 60-minute TTL or cache tags invalidated on product update.
  - *Acceptance Criteria*: `ProdukTab::render()` execution time drops below 30ms; query count reduced by >70%.
- [x] **T-10: Migrate Modal State to Alpine.js**
  - Convert transient modal flags (`showBulkEditModal`, `showSalinDataModal`, filter toggles) to Alpine `x-data`.
  - *Acceptance Criteria*: Livewire network payload size reduced by >40%; smoother UI interaction.

---

### Phase 4: Memory Hardening & Multimedia Storage Normalization
- [x] **T-11: Stream-based Product Import Pipeline**
  - Refactor `ImportProdukService` to use stream generators / chunked reading without `@ini_set('memory_limit', '512M')`.
  - Enforce max memory ceiling of 64MB for 10,000-row imports.
  - *Acceptance Criteria*: Peak memory during import does not exceed 48MB on staging.
- [x] **T-12: Multimedia Extraction from Relational Columns**
  - Create command `php artisan storage:migrate-base64-images` to extract existing base64 strings in `tiket_servis` and `return_penjualan` to `storage/app/public/...` and store paths.
  - Update intake handlers to store files directly to disk.
  - *Acceptance Criteria*: `tiket_servis` average row size drops from 431KB to <2KB; zero 1038 sort buffer errors.
- [x] **T-13: Asynchronous Gateway Testing**
  - Enforce 3s connection timeout on `SettingsRbac::testKoneksiWa()` with async feedback.
  - *Acceptance Criteria*: Gateway network failure does not freeze PHP-FPM workers.

---

## 5. Risk Assessment & Mitigation Strategy

| Risk Factor | Potential Impact | Mitigation Measure |
|---|---|---|
| **Schema Migration Locks** | Table locking during index creation on active production DB | Use `ALGORITHM=INPLACE, LOCK=NONE` on MySQL 8 where supported; execute migrations during maintenance window. |
| **Checkout Service Refactor** | Edge-case discrepancy between POS UI and API | Comprehensive side-by-side regression testing comparing JSON outputs and database diffs. |
| **Base64 Storage Migration** | Missing image files if migration fails midway | Idempotent migration command that verifies file written before nullifying base64 payload; backup verification. |
| **Memory Limit Enforcement** | Large import timeout if not chunked properly | Validate stream generator with large 20,000-row benchmark dataset under 64MB memory limit. |

---

## 6. Zero-Breaking-Change & Regression Testing Contract
- All existing API endpoints (`[API: POS-01]`, `[API: WMS-01]`, etc.) retain exact request parameters, validation rules, and JSON response envelopes.
- All Livewire public event listeners and method signatures remain backwards-compatible.
- Existing database tables, columns, and foreign key relations are strictly preserved.
- Remediation commits will be atomic per task, accompanied by automated regression test verification.
