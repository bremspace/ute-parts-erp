# Bremspace ERP — Manual Testing & Verification Guide
**Target System:** Bremspace ERP (Laravel 13, Livewire 3, Alpine.js, Tailwind 4)  
**Host & Environment:** 2GB+ RAM, PHP 8.5+ (OPcache & JIT enabled), MySQL 8 / SQLite memory  
**User Persona:** Super Admin / Store Manager / Cashier / Auditor  
**Date:** November 3, 2026  

---

## Index of Manual Verification Scenarios

1. [Scenario 1: Database Composite Indexes Verification (DB / CLI)](#scenario-1-database-composite-indexes-verification-db--cli)
2. [Scenario 2: POS Concurrent Checkout & Invoice Sequence Generation](#scenario-2-pos-concurrent-checkout--invoice-sequence-generation)
3. [Scenario 3: Report & Data Export Single-Execution Flow](#scenario-3-report--data-export-single-execution-flow)
4. [Scenario 4: POS Unified Checkout Service (Web UI vs REST API)](#scenario-4-pos-unified-checkout-service-web-ui-vs-rest-api)
5. [Scenario 5: Synchronized Stock Mutation Logging (Transfer & Opname)](#scenario-5-synchronized-stock-mutation-logging-transfer--opname)
6. [Scenario 6: Workflow Decoupled Approval Engine](#scenario-6-workflow-decoupled-approval-engine)
7. [Scenario 7: User Branch Access Memoization (Layout Performance)](#scenario-7-user-branch-access-memoization-layout-performance)
8. [Scenario 8: WMS Product Catalog Caching & N+1 Prevention](#scenario-8-wms-product-catalog-caching--n1-prevention)
9. [Scenario 9: Bounded Memory Ceiling on Product Excel/CSV Import](#scenario-9-bounded-memory-ceiling-on-product-excelcsv-import)
10. [Scenario 10: Relational Base64 Image Extraction Command](#scenario-10-relational-base64-image-extraction-command)
11. [Scenario 11: Bounded Gateway Timeout in Settings](#scenario-11-bounded-gateway-timeout-in-settings)
12. [Scenario 12: Bremspace White-Label Brand Verification](#scenario-12-bremspace-white-label-brand-verification)

---

### Scenario 1: Database Composite Indexes Verification (DB / CLI)

- **Objective:** Verify that the composite indexes exist in MySQL and optimize index scans for high-volume date queries.
- **Tools Needed:** Terminal / Bash CLI.
- **Steps:**
  1. Open terminal on server and run:
     ```bash
     php artisan tinker --execute="echo json_encode([
         'transaksi_idx' => Schema::hasIndex('transaksi', 'transaksi_cabang_id_created_at_idx'),
         'stok_log_idx' => Schema::hasIndex('stok_log', 'stok_log_produk_id_created_at_idx'),
         'jurnal_idx' => Schema::hasIndex('jurnal_akuntansi', 'jurnal_akuntansi_referensi_tipe_id_idx')
     ]);"
     ```
  2. Inspect an EXPLAIN query in tinker:
     ```bash
     php artisan tinker --execute="DB::enableQueryLog(); \App\Modules\Pos\Models\Transaksi::where('cabang_id', 1)->whereBetween('created_at', ['2026-01-01', '2026-12-31'])->get(); dump(DB::getQueryLog());"
     ```
- **Expected Result:**
  - All index checks return `true`.
  - In MySQL production, `EXPLAIN` indicates `key: transaksi_cabang_id_created_at_idx` instead of full table scan or filesort.

---

### Scenario 2: POS Concurrent Checkout & Invoice Sequence Generation

- **Objective:** Verify that rapid sequential checkouts produce uninterrupted, monotonically increasing invoice numbers (`TRX-C{cabang_id:02d}-{YYYYMMDD}-{seq:04d}`).
- **Tools Needed:** Web Browser (POS Cashier).
- **Steps:**
  1. Log into Backoffice at `/app/login` with cashier/admin credentials.
  2. Ensure active branch is selected (e.g., Cabang Pusat / ID: 1).
  3. Navigate to **Kasir POS** (`/app/pos`).
  4. Add an in-stock product to cart.
  5. Click **Bayar** (or press shortcut), select **Tunai**, enter exact cash, and click **Selesaikan Transaksi**.
  6. Note the generated receipt number (e.g., `TRX-C01-20261103-0001`).
  7. Immediately perform a second checkout for another item.
  8. Inspect the new invoice number.
- **Expected Result:**
  - Invoice numbers increment cleanly by 1 (e.g., `0001` -> `0002`).
  - Zero SQL duplicate key exceptions (`Duplicate entry for key 'transaksi_no_transaksi_unique'`).
  - Transaction status is `selesai`, thermal receipt modal renders accurately.

---

### Scenario 3: Report & Data Export Single-Execution Flow

- **Objective:** Verify report exports download immediately in the browser while background queue workers reuse the pre-generated file without regenerating duplicate disk files.
- **Tools Needed:** Web Browser & Terminal.
- **Steps:**
  1. In the browser, navigate to **Akunting -> Laporan Keuangan / Dashboard** (`/app/akunting`).
  2. On any export card (e.g. Laporan Jurnal / Laporan Stok / Laporan Pajak), select format `XLSX` or `CSV` and click **Export Laporan**.
  3. Verify the browser triggers a file download promptly (e.g. `laporan-stok-20261103-...xlsx`).
  4. Open terminal and verify queue jobs table:
     ```bash
     php artisan tinker --execute="dump(\Illuminate\Support\Facades\DB::table('jobs')->count());"
     ```
  5. Check `storage/app/exports` directory:
     ```bash
     ls -lt storage/app/exports | head -5
     ```
- **Expected Result:**
  - File downloads directly with complete headers and data rows.
  - Exactly one export file is generated per user click (no orphan duplicates).
  - An in-app notification appears indicating the export is ready.

---

### Scenario 4: POS Unified Checkout Service (Web UI vs REST API)

- **Objective:** Verify that `PosCheckoutService` produces identical transactional, inventory, and accounting ledger records regardless of source (Livewire UI vs REST API).
- **Tools Needed:** Postman / cURL & Browser.
- **Steps:**
  1. **Web UI Checkout:** Perform a checkout via `/app/pos` for `Product A` with qty 1. Note the resulting journal and stock deduction.
  2. **API Checkout:** Execute cURL POST with Sanctum token:
     ```bash
     curl -X POST "http://localhost/api/pos/transaksi" \
       -H "Content-Type: application/json" \
       -H "Accept: application/json" \
       -H "Authorization: Bearer <TOKEN>" \
       -d '{
         "items": [
           {
             "produk_id": 1,
             "jumlah": 1,
             "harga_satuan": 100000
           }
         ],
         "metode_bayar": "tunai",
         "jumlah_bayar": 100000
       }'
     ```
  3. Inspect database:
     ```bash
     php artisan tinker --execute="dump(\App\Modules\Pos\Models\Transaksi::latest()->first()->toArray());"
     ```
- **Expected Result:**
  - Both methods return status `201` / `selesai`.
  - Both create balanced accounting entries in `jurnal_akuntansi` (Debit Kas 110-04, Kredit Pendapatan 410-01, Debit HPP 510-02, Kredit Persediaan 130-01).
  - Both accurately deduct stock via `StokDeductionService`.

---

### Scenario 5: Synchronized Stock Mutation Logging (Transfer & Opname)

- **Objective:** Verify that inter-warehouse transfers and stock opname actions write synchronized entries to both `stok_log` and `stock_mutation_log` with actor tracking.
- **Tools Needed:** Web Browser (WMS Module).
- **Steps:**
  1. Navigate to **WMS -> Transfer Stok** (`/app/wms/transfer`).
  2. Create a transfer of 5 units of an SKU from Warehouse A to Warehouse B.
  3. Approve / Send the transfer.
  4. Receive the transfer at the destination warehouse.
  5. Open terminal to inspect logs:
     ```bash
     php artisan tinker --execute="
     dump('StokLog:', \App\Modules\Wms\Models\StokLog::latest()->take(2)->pluck('jenis', 'perubahan'));
     dump('StockMutationLog:', \App\Modules\Wms\Models\StockMutationLog::latest()->take(2)->pluck('sumber', 'delta'));
     "
     ```
- **Expected Result:**
  - Both tables have synchronized pairs: negative delta at origin warehouse, positive delta at destination warehouse.
  - `user_id` matches the authenticated actor in both logs.

---

### Scenario 6: Workflow Decoupled Approval Engine

- **Objective:** Verify that approval requests trigger properly via `ApprovalHandlerRegistry` without runtime coupling exceptions.
- **Tools Needed:** Web Browser (Workflow Inbox) / Terminal.
- **Steps:**
  1. Navigate to **Workflow -> Kotak Masuk Approval** (`/app/workflow`).
  2. Locate a pending approval item (e.g. GRN Pembelian, Selisih Kas, or Member Baru).
  3. Click **Setujui** with optional notes.
  4. Alternatively, execute via tinker:
     ```bash
     php artisan test --filter=ApprovalEngineTest
     ```
- **Expected Result:**
  - Status updates to `disetujui`.
  - Underlying entity triggers downstream actions (e.g. GRN posts AP journal; Member activates tier).
  - Zero `Class not found` or reflection container exceptions.

---

### Scenario 7: User Branch Access Memoization (Layout Performance)

- **Objective:** Confirm `User::daftarCabangAkses()` memoizes database queries within the same request lifecycle.
- **Tools Needed:** Terminal (Tinker).
- **Steps:**
  1. Run the memoization test snippet:
     ```bash
     php artisan tinker --execute="
     \$user = \App\Models\User::first();
     \Illuminate\Support\Facades\DB::enableQueryLog();
     \$user->daftarCabangAkses();
     \$firstCount = count(\Illuminate\Support\Facades\DB::getQueryLog());
     \$user->daftarCabangAkses();
     \$secondCount = count(\Illuminate\Support\Facades\DB::getQueryLog());
     dump('First call queries: ' . \$firstCount, 'Second call queries: ' . \$secondCount);
     "
     ```
- **Expected Result:**
  - `secondCount` is identical to `firstCount` (0 additional queries on subsequent invocations within the same request).

---

### Scenario 8: WMS Product Catalog Caching & N+1 Prevention

- **Objective:** Verify that catalog brand compatibility lists load from cache and that scrolling/searching products triggers zero lazy loading errors.
- **Tools Needed:** Web Browser (WMS Produk).
- **Steps:**
  1. Navigate to **WMS -> Master Produk** (`/app/wms`).
  2. Open browser DevTools Network tab.
  3. Filter by brand or search for a keyword (e.g. "LCD").
  4. Clear search and toggle brand filters.
  5. Inspect Redis/Cache in terminal:
     ```bash
     php artisan tinker --execute="dump(\Illuminate\Support\Facades\Cache::has('wms_brand_kompatibel_list'));"
     ```
- **Expected Result:**
  - `Cache::has('wms_brand_kompatibel_list')` returns `true`.
  - Table renders smoothly under 100ms.
  - Zero `LazyLoadingViolationException` on `satuanKonversi` or related relations.

---

### Scenario 9: Bounded Memory Ceiling on Product Excel/CSV Import

- **Objective:** Verify that large CSV/Excel product imports operate within a strict 64MB memory envelope without failing or spiking server RAM.
- **Tools Needed:** Web Browser / Terminal.
- **Steps:**
  1. Navigate to **WMS -> Master Produk -> Import Excel / CSV** (`/app/wms`).
  2. Download sample CSV template.
  3. Populate template with 500+ test product rows.
  4. Upload and click **Pratinjau / Preview**.
  5. Click **Commit / Simpan Data**.
  6. Check peak memory usage in terminal test:
     ```bash
     php artisan test --filter=ImportProdukAtomikTest
     ```
- **Expected Result:**
  - Import succeeds without memory exhaustion or timeouts.
  - Peak worker memory remains well below the 64MB threshold.

---

### Scenario 10: Relational Base64 Image Extraction Command

- **Objective:** Verify that `php artisan storage:migrate-base64-images` extracts inline base64 images to public disk files and updates database references to file paths.
- **Tools Needed:** Terminal (Artisan).
- **Steps:**
  1. Run the migration command in terminal:
     ```bash
     php artisan storage:migrate-base64-images
     ```
  2. Check the command output.
  3. Verify the files written in storage:
     ```bash
     ls -la storage/app/public/tiket-servis/ 2>/dev/null || echo "Directory ready"
     ```
- **Expected Result:**
  - Command executes cleanly: `Migration completed!`.
  - Database row size drops; images are accessible via disk paths.
  - Automated test passes: `php artisan test --filter=MigrateBase64ImagesTest`.

---

### Scenario 11: Bounded Gateway Timeout in Settings

- **Objective:** Verify that WhatsApp gateway testing terminates within 3 seconds when connecting to an unreachable or slow endpoint, protecting PHP-FPM workers.
- **Tools Needed:** Web Browser (RBAC / Settings).
- **Steps:**
  1. Navigate to **Pengaturan -> Integrasi / WhatsApp Gateway** (`/app/rbac/settings`).
  2. In the Gateway URL field, enter a non-routable blackhole IP (e.g., `http://10.255.255.1:9999`).
  3. Enter dummy token and phone number, then click **Uji Coba Kirim WA**.
  4. Observe response duration.
- **Expected Result:**
  - Request aborts gracefully in ~3 seconds with error toast `Koneksi ke gateway WA gagal: ...`.
  - Web server does not lock up; other browser tabs remain responsive.

---

### Scenario 12: Bremspace White-Label Brand Verification

- **Objective:** Confirm that all public, backoffice, and document print templates display the **Bremspace** brand identity with zero legacy brand leaks.
- **Tools Needed:** Web Browser.
- **Steps:**
  1. Inspect browser title and favicon on login page (`/app/login`).
  2. Check navigation header brand in backoffice (`/app/*`).
  3. Check public tracking page: `/servis/tracking/{any_valid_token_or_kode}`.
  4. Check thermal print receipts and PDF printouts (PO, GRN, Barcode label).
- **Expected Result:**
  - All pages render "Bremspace ERP" / "Bremspace Prism Enterprise".
  - Clean styling utilizing the official color tokens (`--up-primary: #4F46E5`, `--up-accent: #D97706`, `--up-mint: #10B981`).
  - No occurrences of obsolete third-party trademarks.
