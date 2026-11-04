# Implement Plan: Fase 6 — Resiliensi Webhook & Asynchronous Queue Processing

Status: COMPLETED  
Dokumen Acuan: `docs/prd/PRD-Audit-Review-ERP-Gaps.md` (§GAP-INT-01, §GAP-ERP-01)

---

## Task & Acceptance Criteria

- [x] T-01 — Buat migrasi tabel staging webhook inbound marketplace: `channel_webhook_inbound`
- [x] T-02 — Buat model `ChannelWebhookInbound` di `App\Modules\Omnichannel\Models`
- [x] T-03 — Buat background queue job `ProcessChannelOrderJob` di `App\Modules\Omnichannel\Jobs` dengan explicit branch context (`BranchContextManager`)
- [x] T-04 — Refaktor `OmnichannelController::webhook` untuk async ingestion (<150ms return HTTP 200, staging delivery, dispatch `ProcessChannelOrderJob`)
- [x] T-05 — Audit & sanitasi seluruh queue jobs (`ExportLaporanJob`, `DepresiasiAsetJob`, `ReportExportJob`, `ImportProdukExcelJob`, `SupplierScoreJob`, `PrintThermalJob`) agar mengemas `cabangId` dan `runInBranchContext`
- [x] T-06 — Buat & jalankan test suite integrasi Webhook Async & Queue Branch Context, verifikasi Deptrac 0 violation, Pint lulus, update CHANGELOG

---

## Catatan Verifikasi
- T-01: Migrasi `2026_11_03_180000_create_channel_webhook_inbound_table.php` sukses dieksekusi di database staging (`channel_webhook_inbound` dengan kolom channel_id, channel_order_id, event, payload, status, error, processed_at).
- T-02: Model `ChannelWebhookInbound` dibuat lengkap dengan cast array untuk payload, datetime untuk processed_at, dan relasi `channel()`.
- T-03: `ProcessChannelOrderJob` membungkus pemrosesan order masuk dalam `BranchContextManager::runInBranchContext($cabangId, ...)`, mengupdate status staging inbound ('processing' -> 'processed' / 'failed'), serta mendukung retry hingga 3 kali.
- T-04: `OmnichannelController::webhook` memvalidasi payload, mencatat payload mentah ke `ChannelWebhookInbound` berstatus `queued`, mendispatch `ProcessChannelOrderJob`, dan segera mengembalikan respons HTTP 200 JSON tanpa memblokir thread HTTP.
- T-05: Seluruh queue job yang beroperasi multi-cabang (`ExportLaporanJob`, `DepresiasiAsetJob`, `ReportExportJob`, `ImportProdukExcelJob`, `SupplierScoreJob`, `PrintThermalJob`, `SendWebhookJob`) terverifikasi mengemas `cabangId` eksplisit dan dieksekusi di dalam `runInBranchContext()` sehingga bebas dari kebocoran data lintas cabang saat dieksekusi oleh queue worker CLI tanpa sesi HTTP.
- T-06: Test suite `tests/Feature/WebhookAsyncDanQueueBranchContextTest.php` 100% lulus (4 tests, 18 assertions), `OmnichannelShopeeIntegrationTest` (8 tests, 24 assertions) lulus, `MarketplacePpnTest` (11 tests, 54 assertions) lulus, Pint 100% lulus, dan Deptrac 0 violations (297 class dianalisis).
