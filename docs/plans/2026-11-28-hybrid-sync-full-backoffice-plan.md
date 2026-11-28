# Plan: Full Bidirectional Backoffice Sync + Secret Encryption

**Tanggal:** 28 November 2026
**Status:** COMPLETED — Seluruh task T-01 s.d. T-08 terverifikasi (PHPStan 0 error, Pint OK, 58/58 test sync hijau)
**Prasyarat:** Semua task Fase 1-4 dari [2026-11-23-hybrid-database-sync-plan.md](file:///var/www/ute-parts-erp/docs/plans/2026-11-23-hybrid-database-sync-plan.md) sudah ✅ selesai.
**Tujuan:**
1. **Enkripsi & masking `sync_api_secret`** di DB + UI Pengaturan Integrasi + tombol Generate Secret.
2. **Sinkronisasi bidireksional SEMUA aktivitas backoffice** antar `primary_store` (PC Toko di balik NAT) dan `cloud_storefront` (VPS) tanpa bentrok PK/FK dan tanpa duplikasi potong stok/jurnal.

---

## Catatan Audit Arsitektur (Perubahan Penting vs Draft Awal)

1. **Pola Transport Asimetris tetap dipertahankan** (`ADR 0013` + `HybridSyncAuditRegressionTest::test_cloud_storefront_never_pushes_outbox_and_stuck_processing_recovers`):
   - PC Toko (`primary_store`) berada di balik NAT → inisiasi PUSH ke VPS dan PULL berkala (`sync:pull`) dari VPS.
   - VPS (`cloud_storefront`) menulis seluruh event backoffice ke outbox lokal → ditarik PC Toko via `GET /api/internal/sync/pull`.
   - **Fix celah utama:** Sebelumnya `SyncController::pull()` memfilter hanya 14 event (`$allowedEvents`) sehingga event backoffice dari VPS tidak pernah terkirim ke PC Toko. Filter whitelist sempit ini dibuka agar semua event backoffice VPS ikut ditarik ke PC Toko.
2. **Nama tabel terverifikasi terhadap skema SQL aktual** (contoh: `purchase_order`, `purchase_order_item`, `stok_transfer_item`, `stok_opname_item`, `return_penjualan_item`, `return_pembelian_item`, `kpi_metric`, `payroll_slip`, `tiket_servis_item`, `tiket_servis_estimasi_item`, `servis_status_log`, `vouchers`, `channel_product_mapping`, `nomor_seri_produk`).
3. **Anti-dobel potong stok & jurnal:**
   - Tiga observer `Transaksi` (`TransaksiPosSyncObserver`, `TransaksiCloudPosSyncObserver`, `OrderOnlinePaidSyncObserver`) TETAP menjaga guard `node_role` masing-masing karena sudah pasangan bidireksional yang lengkap; melepas guard-nya akan menyebabkan 1 transaksi memicu 2 event sekaligus.
   - `StokLogSyncObserver` dibuka untuk `cloud_storefront` (agar opname/GRN/transfer/adjust/servis dari VPS turun ke Toko), dengan guard eksplisit: lewati `StokLog` milik `Transaksi` bertipe `marketplace` / `pos_cloud` / `VPS-*` agar stok tidak terpotong 2x saat `order.online_paid` / `transaksi.pos_cloud` diproses di Toko.
   - `HybridSyncProcessorService::processForPrimaryStore()` ditambah handler `'stok.mutasi'`.
4. **Resolusi FK & Unique Index Lintas Node:**
   - Satu sumber kebenaran `BackofficeSyncRegistry` menyimpan peta tabel, natural matchers (`kode`, `no_po`, `no_grn`, `no_transfer`, `no_opname`, `no_return`, `nik`, dll.), FK lookup (`_refs`), dan kolom sensitif yang disaring.
   - Kolom bertipe cast `array`/`json` dikirim sebagai array terdekode dan diisi dengan `forceFill()` + `saveQuietly()` agar tidak terkena mass-assignment drop (`sync_uuid`) maupun double-JSON-encoding.
5. **Pola Secret Settings UI (`SettingsRbac`):**
   - Mengikuti pola `wa_token` + `$waTokenConfigured` yang sudah ada di codebase (`SettingsRbac`): secret terenkripsi tidak dikirim plaintext kembali ke browser setelah tersimpan.

---

## Part A: Enkripsi, Masking & Generate `sync_api_secret`

- [x] **T-01** — Enkripsi at-rest & dekripsi runtime `hybrid_sync_config.api_secret`:
  - `app/Modules/Rbac/Livewire/SettingsRbac.php`: tambah `$syncSecretConfigured`, `resolveSyncSecret()`, enkripsi `Crypt::encryptString()` di `simpanIntegrasi()`, pertahankan ciphertext lama bila input kosong/`••••••••`, auto-migrasi plaintext lama saat disimpan ulang, kosongkan input UI pasca-simpan.
  - `app/Modules/Sync/Providers/SyncServiceProvider.php`: dekripsi `api_secret` dengan fallback `DecryptException` di `applyDatabaseConfig()` sebelum mengisi `config('sync.api_secret')`.
- [x] **T-02** — UI Masking & Tombol Generate Secret:
  - `app/Modules/Rbac/Livewire/SettingsRbac.php`: tambah method `generateSyncSecret()` (`pengaturan.manage`, `Str::random(64)`, dispatch `sync-secret-generated`).
  - `resources/views/modules/rbac/livewire/settings-rbac.blade.php`: badge `● Tersimpan Terenkripsi`, `autocomplete="new-password"`, tombol `Generate`, listener Alpine copy ke clipboard + alert toast.

---

## Part B: Full Bidirectional Backoffice Sync

- [x] **T-03** — Buat `app/Modules/Sync/BackofficeSyncRegistry.php` berisi definisi kanonik seluruh tabel backoffice yang disinkronkan secara generik, natural unique matchers, peta FK (`FK_MAP`), polymorphic FK, dan kolom sensitif yang dikecualikan.
- [x] **T-04** — Buat migrasi `database/migrations/2026_11_28_000001_add_sync_uuid_to_backoffice_tables.php` menambahkan kolom `sync_uuid` (`string(36)->nullable()->unique()`) secara idempoten ke seluruh tabel di `BackofficeSyncRegistry` yang belum memilikinya.
- [x] **T-05** — Buat `app/Modules/Sync/Observers/BackofficeModelSyncObserver.php` (menggunakan `SyncsModelEvents`, serialize JSON/array cast secara aman, lampirkan `_refs` untuk FK, strip kolom rahasia) dan daftarkan seluruh model di `SyncObserverBootstrap::register()`.
- [x] **T-06** — Buka sinkronisasi bidireksional penuh pada engine yang ada:
  - Hapus guard `node_role` di `TiketServisSyncObserver`, `PayrollPeriodeSyncObserver`, `JurnalHeaderSyncObserver` (perluas sumber jurnal non-POS agar `grn`, `servis`, `retur`, `tutup_buku`, `bagi_hasil`, `kas` ikut sinkron tanpa menggandakan jurnal `pos`).
  - Buka `StokLogSyncObserver` untuk `cloud_storefront` sambil mencegah double-deduction transaksi `marketplace`/`pos_cloud`/`VPS-*`.
  - Buka `SyncController::pull()` pada `cloud_storefront` agar menyajikan semua antrean outbox backoffice (kecuali `transaksi.pos` jika ada).
  - Tambah `'stok.mutasi'` + `handleGenericBackofficeEvent()` (`handleGenericUpsert()` / `handleGenericDelete()`) ke `HybridSyncProcessorService`.

---

## Part C: Testing & Verifikasi

- [x] **T-07** — Buat unit & feature test baru:
  - `tests/Unit/SyncApiSecretEncryptionTest.php`
  - `tests/Unit/BackofficeModelSyncObserverTest.php`
  - `tests/Feature/BidirectionalBackofficeSyncTest.php`
- [x] **T-08** — Jalankan `./vendor/bin/pint --test` (format PSR-12) dan `composer test` (seluruh test suite hijau) + update `docs/CHANGELOG.md`.

---

## Part D: Audit Hardening (Pass 2 — 28 November 2026)

- [x] **T-09** — Sinkronisasi signature HMAC query GET (`SyncPullCommand` `ksort()` param sebelum `http_build_query` agar cocok dgn normalisasi Symfony `VerifySyncSignature`) + guard `--all` saat batch gagal/ACK gagal + clamp limit `/pull` + tie-break `orderBy('created_at')->orderBy('id')` pada seluruh outbox reader.
- [x] **T-10** — Reload live konfigurasi runtime (`SyncServiceProvider::markRuntimeConfigUpdated()` & `refreshRuntimeConfigIfChanged()` dipanggil dari `VerifySyncSignature`, `PushHybridSyncJob`, `BatchPushHybridSyncJob`, `SyncFlushCommand`, `SyncPullCommand`, `SyncStatusController`, dipicu oleh `SettingsRbac::simpanIntegrasi()`) agar `queue:work` langsung mengikuti perubahan `sync_api_secret`/URL tanpa restart worker.
- [x] **T-11** — Perbaiki race condition hard-delete & create+delete dalam satu transaksi DB: `BackofficeModelSyncObserver::deleting()` menangkap snapshot `_refs` sebelum cascade FK terhapus; `SyncsModelEvents::enqueueModelEvent()` melewati `$model->refresh()` pada `.delete` dan melewatkan upsert bila `$model->exists === false` / `ModelNotFoundException` pasca-commit.
- [x] **T-12** — Lengkapi observer `.delete` & FK cross-node yang terlewat: tambah `deleted()` + delete handler (`Brand`, `KategoriProduk`, `KualitasProduk`, `SatuanUnit`, `TipeHp`, `SkuVariant`, `Pelanggan`, `TiketServis`, `PayrollPeriode`, dan `Produk::forceDelete()`), remap `Produk::supplier_id`, `Pelanggan::approved_by`, `JurnalHeader::referensi_id`, serta anak `PayrollKomisiDetail` pada `payroll.upsert`.
- [x] **T-13** — Perluasan cakupan & keamanan tipe DB: tambah `rekonsiliasi_bank`, `rekonsiliasi_bank_item`, `pelanggan_kode_aktivasi` (`2026_11_28_000002_add_sync_uuid_to_additional_backoffice_tables.php`), natural matchers pada master non-unique (`supplier` nama, `shift`, `bagi_hasil_periode`, `aset_tetap`, `skema_komisi`, `komisi_skema`, `channels`, `approval_rules`), sinkronisasi `konfigurasi.upsert` ter-allowlist (`KonfigurasiService`, `PajakService`), serta normalisasi kolom `date`/`datetime`/`timestamp` untuk MySQL 8 strict mode.

---

## Cakupan Model yang Dikecualikan Secara Sengaja
- `HybridSyncInbox`, `HybridSyncOutbox` (infrastruktur sync sendiri)
- `AuditLog`, `AktivitasLog`, `PosOfflineSyncLog`, `ImportLog`, `SidImportMap` (log/jejak lokal per node)
- `User`, `CabangUserPivot`, `DeviceSession`, `PelangganDeviceSession` (kredensial password, 2FA & sesi per-node; relasi user tetap diresolusi via email)
- `NotifikasiKeluar`, `WebhookDelivery`, `WebhookEndpoint`, `SavedReport` (konfigurasi/deliverables lokal)
- `StokItem`, `StockMutationLog`, `SupplierSaldoMutasi`, `SupplierScore` (derived state — dimutasi atomik oleh `stok.mutasi` & subledger)
- `JurnalAkuntansi` (child line atomik di bawah `jurnal.upsert`)
- `ChannelOrder`, `ChannelWebhookInbound`, `Pengiriman`, `WebArticle`, `WebBanner`, `WebFaq`, `WebKonsumenSetting`, `WebReview` (khusus kanal online/marketplace)
