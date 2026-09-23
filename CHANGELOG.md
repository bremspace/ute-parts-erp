# Ute Parts — CHANGELOG

Semua ringkasan task yang selesai dari `implement-plan.md` (pasca-MVP).
Format: `[Fase X] T-XX — ringkasan`.

## 2026-09-22 — PRD-Advanced Fase 2 (F2-1 Lead Pipeline)

- **F2-1 Lead Pipeline CRM (G-06)**: migration idempoten `leads` (FK `cabang`/`users`/`pelanggan` singular, index `[cabang_id, stage]` dll), model `Lead` (stage enum baru/kontak/kualifikasi/negosiasi/won/lost, sumber 7 jenis, `scopeForCabang`, label/color accessor, `canConvert`), `LeadService` (create/update + validasi, `handleStageTransition` → `won_at`/`lost_at` + notif via queue (tidak pernah sync), `convertToPelanggan` (PelangganService::create dalam transaksi), `getKanbanData`, `getFunnelData`, `bulkUpdateStage`), endpoint API **CRM-10..18** di `CrmController` (index/store/show/update/destroy/bulk-stage/convert/kanban/funnel) dengan `permission:crm.*` — urutan route static sebelum `/leads/{id}` (fix shadowing), permission **`crm.delete`** ditambahkan ke seeder (master + marketing). Livewire **`LeadKanban`** (`/app/crm/leads`, `permission:crm.view`) + blade Ute Prism: 6 kolom, drag-drop HTML/Alpine → `updateStage()` satu langkah (side-effect lewat LeadService), summary + mini funnel, filter search/sumber/sales, modal create/edit, konfirmasi konversi→Pelanggan & hapus; copy Bahasa Indonesia, `tabular-nums`. Test **`LeadConversionTest` 8/8 hijau** (51 asersi: CRUD, won/lost timestamp, konversi, bulk, kanban/funnel scoping, cabang isolation, stage invalid 422), `Queue::fake()`.
- **Bug production ditemukan & diperbaiki selama F2-1**: (1) `LeadService::rules()` `exists:cabangs,id` → `cabang` (tabel singular — semua POST/PUT lead 500); (2) `Lead`/`LeadKanban` import `App\Modules\Rbac\Models\User` tidak ada → `App\Models\User`; (3) `ExportLaporanService` import `App\Modules\Crm\Models\Transaksi` tidak ada → `App\Modules\Pos\Models\Transaksi` (rekapan PPN pernah 500); (4) `KonfigurasiService` duplikat blok `seedPpnDefaults` di luar class → parse error (pre-existing fatal saat import); (5) `PpnTest` tidak ter-discover di PHPUnit 12 (`@test` docblock dihapus) → rename `test_*` + `RefreshDatabase` + ganti `Transaksi::factory()` (factory tidak ada) dengan `Transaksi::create` + helper `createCabang` — **PpnTest 9/9 hijau** (sebelumnya 0 jalan).
- Verifikasi: `LeadConversionTest` 8/8, `PpnTest` 9/9, regresi `ApprovalEngineTest` 20/20, `JurnalServiceTest` 4/4, `ResellerFase5Test` 2/2; `migrate` sukses di `db_staging`; Pint bersih semua file tersentuh.

## 2026-09-22 — PRD-Advanced Fase 1 (F1-1, F1-2, F1-3, F1-4, F1-5, F1-6, F1-7, F1-8)

- **F1-5 Reorder Otomatis**: migration idempotent enum `status` PO + `usulan` (MySQL MODIFY / SQLite rebuild; `down()` tolak bila masih ada baris usulan); `ReorderService` — query stok `jumlah < jumlah_minimum` (min>0, produk aktif), anti-duplikat (skip bila produk sudah ada di PO draft/usulan), resolusi supplier terakhir (PO item terbaru → fallback terfrequency), grouping per supplier+gudang, buat PO usulan (`PO-Ymd-NNN`, qty = minimum−stok, termin supplier), notifikasi queue `NotificationService::kirim('inapp', …)` Bahasa Indonesia; `ReorderOtomatisJob` (`ShouldQueue`) schedule `dailyAt('03:00')` di `bootstrap/app.php` (3 entri lama intact); observer `PurchaseOrderObserver` skip status `dibatalkan`/`usulan` (usulan tak auto-fire approval F1-1); `PoTab::konfirmasiUsulan` — validasi status, set `draft`, `ApprovalService::ajukan` idempotent (hanya ≥ threshold); tombol Konfirmasi warna `bg-up-accent`, StatusPill `usulan` amber; ruang riwayat F1-4 di PoTab dipertahankan. `ReorderTest` 10/10 (47 asersi), `WmsDashboardSplitTest` 7/7, `ApprovalEngineTest` 20/20, `schedule:list` 4 entri, Pint 7 file bersih.
- **Fix bug production pre-existing (final battery Fase 1)**: `ImportProdukService::konteksDuplikat` mem-build peta SKU/barcode file-penuh lalu `validateRow` cek `isset` — baris apa pun selalu dianggap duplikat diri sendiri → **preview selalu `valid=0`, import produk 100% rusak di HEAD** (pre-existing, tersembunyi karena test selalu gagal lebih awal). Perbaiki jadi akumulasi per-baris **setelah** validasi (`catatKonteks`, `use &$konteks` di commit) — duplikat in-file hanya terdeteksi pada kemunculan berikutnya; kemunculan pertama sah kecuali sudah ada di DB. Test `WmsImportVerifyTest` ikut diperbaiki: baris invalid dipadding `array_fill_keys($kolom)` (CSV maatwebsite positional — baris sparse bergeser kolom), `assertSame` float expected, run-1 commit via `ImportProdukExcelJob` (sync) karena notifikasi hanya dari job + salinan file utk run-2 (job unlink), `ImportLog` status `proses` (enum CHECK). **`WmsImportVerifyTest` 1/1 (40 asersi) hijau — pertama kali di box ini.**

- **F1-4 Audit Trail** (`spatie/laravel-activitylog` ^5.1): model model kritis — Transaksi, JurnalAkuntansi, Piutang, Utang, StokItem, PurchaseOrder, Produk — via trait `CatatAktivitas` (logAll + logOnlyDirty, create/delete full snapshot, update dirty only); kolom `cabang_id` terindeks di-resolve via row/gudang (`AktivitasCabang`), causer auth → `"sistem"` utk job/seed; `default_except_attributes` blokir token/secret/2FA fields. UI reusable `RiwayatAktivitas` (filter pengguna/aksi/tanggal, manual pagination anti-bentrok WithPagination, komputasi eksplisit di render) + modal partial di-embed di 5 view (akunting dashboard, produk-tab, po-tab, stok-tab, pos-kasir receipt — ruang PoTab ditinggalkan utk F1-5); RBAC `lihat-audit-log` idempotent (super-admin/finance/admin-toko) + `@can` di tiap tombol. `ActivityLogTest` 7/7 (110 asersi), regresi `PpnTest` 13/13 + `ApprovalEngineTest` 20/20 + `JurnalServiceTest` 4/4 hijau, `SemuaHalamanTest` 9/9 (embed view tercompile), Pint 22 file bersih. Gap: halaman global audit-log full-page (terpisah bila dibutuhkan), tombol riwayat mobile-card StokTab belum ada.

- **F1-3 2FA TOTP** (`pragmarx/google2fa` ^9.1): migration idempotent 3 kolom users (`two_factor_secret` encrypted, `two_factor_confirmed_at`, `two_factor_backup_codes` bcrypt JSON 8x consume-on-use); login challenge sebelum session grant (pending-id, RateLimiter 5×60s, TOTP → fallback backup code, pesan Indonesia); setup UI `/app/keamanan/dua-faktor` (secret + otpauth manual entry, confirm-to-activate, flash 8 kode sekali, regen/disable dgn password) + amber banner force-setup utk super-admin/admin-toko/finance TANPA lock-out (kebijakan MVP: challenge hanya saat 2FA aktif, utk semua role); event ubah kunci → `NotificationService` queue + AuditService. `TwoFactorTest` 18/18 (107 asersi), `LoginFlowTest` 4/4, `ApprovalEngineTest` 20/20, Pint bersih, double-migrate idempotent, 6 route `two-factor.*` terdaftar. QR image di-utamakan manual entry (MVP).

- **F1-2 Pajak Otomatis** (PajakService rewrite): config per-cabang via kunci `pajak_enabled.{cabang}`/`ppn_percent.{cabang}` (default 11, fallback key lama `ppn_enabled.*` backward-compat, opt-in default false); migration idempotent `ppn_nominal`+`dpp` di transaksi + reconcile COA (`220-01` PPN Keluaran seeder idempotent); `hitung(?cabangId, dpp)` → PosKasir wiring (dpp = subtotal − diskon, tampil `pajakNominal`/`ppnPersen`); baris jurnal via `JurnalService` (220-01 kredit, balance teruji); laporan pajak bulanan Livewire `LaporanPajak` + route `/app/laporan-pajak` + rekap export (`ExportLaporanService`). Fix pre-existing di scope: `KonfigurasiService` blok duplikat `seedPpnDefaults` (ParseError HEAD), `PpnTest` → 13 `test_*` methods (PHPUnit 12). Verifikasi: `PpnTest` 13/13 (51 asersi), `JurnalServiceTest` 4/4, `ApprovalEngineTest` 20/20, Pint bersih (3 file style Pos pre-existing ikut dibersihkan; `PricingServiceTest`/`HargaFleksibelTest` tetap hijau).

- **F1-8 Split WmsDashboard** (S-01): `WmsDashboard.php` 1043 → 21 baris (shell tab host) + 5 komponen fokus `StokTab`/`TransferTab`/`OpnameTab`/`PoTab`/`ProdukTab` + blade per-tab — ekstraksi verbatim (audit independen: byte-identical vs HEAD utk semua segmen, 7 pasang dispatch event `#[On]]` cocok, 37 method/properti original terakomodasi); children selalu mounted (toggle `hidden`) → state lintas tab persisten = paritas perilaku. `WmsDashboardSplitTest` 7/7 hijau, `SemuaHalamanTest` 9/9 (termasuk `/app/wms`), Pint bersih. Bug pre-existing beresin di reconcile: `WmsImportVerifyTest` — return type `collection(): Enumerable` (maatwebsite/excel 4.0.3) + path baca disk `local` (`storage/app/private`); chown `storage/app/private/{prints,import-tmp}` → www-data (was root:700). Catatan risiko diterima: pagination stok/produk terpisah per tab (URL `?page=` shared — kosmetik), first paint mount 5 children (biaya query awal ~setara dgn render lama tanpa guard).

- **F1-1 Approval Engine** (module Workflow — verifikasi + gap): rule engine cabang-scoped (`cabang_id = X OR NULL`), amount range null-safe, multi-level (`lanjutLevelBerikut` rekursif, reject hentikan rantai), idempotent 1-request-per-(entity,rule); auto-fire via 4 Eloquent observer terdaftar di `AppServiceProvider` — PO ≥10jt (finance L1), Retur Penjualan/Pembelian ≥5jt (super-admin), diskon transaksi ≥1jt (admin-toko; tanpa edit PosKasir); observer try/catch (gagal approve tidak merusak save) + skip `is_migrasi_sid`; inbox `/app/approvals` RBAC `permission:approve-workflow` (kasir 403), filter cabang+role, badge pending count di sidebar; notif via `NotificationService` + `KirimNotifikasiJob` queue (tidak pernah sync). Permission `approve-workflow` idempotent (migration 100010 + seeder, pola `atur-harga-fleksibel`), granted super-admin/finance/admin-toko. `ApprovalEngineTest` 20/20 (74 asersi) hijau; Pint bersih. **Catatan**: approval bersifat audit-only (observer post-save — pending tidak memblokir persistensi dokumen); blocking flow menyusul bila dibutuhkan (mis. F2-2 GRN).

- **F1-6 Backup** (`ute:backup`): mysqldump gzip → `storage/app/backups/` (stream 8KB RAM-safe, kredensial via env `MYSQL_PWD` — password tidak di argv), rotasi 7 hari idempoten (`--keep-days`), schedule `dailyAt('02:00')` di `bootstrap/app.php` withSchedule, notif hasil via `NotificationService` + `Queue::later` (tidak pernah sync). Verifikasi: run manual sukses — dump 6,4 MB `db_staging` valid (gzip -t OK); `BackupCommandTest` 5/5 hijau; cron `schedule:run` dipasang di box staging (crontab sebelumnya kosong); `storage/app/backups` chown www-data.
- **F1-7 Ops config**: `deploy/supervisor/ute-parts-queue.conf` (numprocs=1 sesuai RAM 1GB; ⚠️ live supervisor masih 2 proses `_00/_01` — rekonsiliasi saat implement deploy), `deploy/logrotate/ute-parts.conf` (harian, rotate 7, copytruncate), route `GET /healthz` cek db + queue (output hanya boolean, tanpa data tenant) terdaftar via `withRouting(then:)` tanpa menyentuh `routes/web.php`; `/up` tetap. `HealthzTest` 3/3 hijau; `curl /healthz` → 200 `{"db":true,"queue":true}`; Pint bersih.

## 2026-09-22 — Migrasi SID Fase 3 penutup (C-05..C-08) + Verifikasi V-01..V-05

- **C-05 stok_log rekonstruksi** (SidRekonstruksiStok): 19.286 arus_stok → 19.338 stok_log (1:1 + 52 split masuk&keluar), tanggal_log historis 02-14..08-23, jenis penjualan 11.805/masuk 2.982/pembelian 1.270/koreksi 3.281, gudang TOKO→6/GUDANG→5, referensi_id 13.060 transaksi valid, dummy [ARSIP-SID] 123.
- **C-06 stok_items**: wipe 3.044 artefak → rebuild 4.794 (last-sisa arus 2.789 + fallback payload toko/gudang 2.005), deviasi arus-vs-log 0, negatif 0.
- **C-08 jurnal pembuka** (SidJurnalPembuka): SID-OPENING 4 baris COMMIT 2026-09-22 04:11, balance 1.195.932.217,50 (Kas 99.818.660 + Piutang 3.001.000 + Persediaan 1.093.112.557,50 vs Modal), no_jurnal deterministik idempotent.
- **C-07/koreksi metode_bayar**: seluruh header visa=UANG PAS → 6.262 transaksi UPDATE massal kartu→tunai (orchestrator).
- **V-01..V-05**: deviasi item-vs-total 325/6.262 (katalog, dipertahankan sengaja); saldo SOT cocok; stok konsisten; sid_import_map 0 orphan; testing 10/11 file lulus.
- **BONUS fix migrasi lama** `2026_09_21_000042` (MySQL-only join()->update()) → correlated subquery portabel SQLite: 29 gagal → hijau.
- **Catatan lingkungan**: WmsImportVerifyTest (Excel import) fatal OOM di server cgroup 955MB (SIGKILL, bebas ±115MB; stack opencode 400MB) — bukan kegagalan logika; belum pernah hijau di box ini (sebelumnya 29 gagal could not find driver; pdo_sqlite diinstall 2026-09-22). Rerun di env dgn RAM cukup utk hijau penuh.

- **Audit sinkronisasi 4 domain (produk/stok/transaksi/konsumen)**: ALL PASS — Σ total transaksi 765.411.399 = Σ header SID; 0 orphan FK lintas modul; 0 dup no_transaksi; 1.646 produk hjr=0 = sumber SID 0 (bukan misroute); 71 barang JASA = price-list servis dormant (dijual via modul Servis).

- **Command wrapper `sid:migrate-full`** (pipeline M→A→B→C→D+J satu perintah, --dry-run/--only/--step/--commit-jurnal) + dokumentasi §6 MIGRASI-SID.md: cara mulai dari bersih (migrate:fresh + import-raw + migrate-full) & migrasi langsung oleh user. Temuan harga: 1.342 produk SID hjr=0 = sumber SID 0 (0 produk punya harga terlewat; 1.331 flag nol_price_diskon=true) — bukan bug mapping.

- **Fitur Harga Jual Fleksibel** (2026-09-22): kolom `produk.harga_fleksibel`, permission `atur-harga-fleksibel` (super-admin), `HargaFleksibelService` guard ≥ HPP, PosKasir integrasi (kasir diblokir, superadmin modal input), WmsDashboard toggle, command `sid:map-jasa-produk` map 71 JASA SID → `JenisServis` (SOT) + 1.342 produk harga 0 flag fleksibel, 7 unit test hijau. Akuntansi aman: `transaksi_item.harga_satuan` + `hpp` existing → margin akurat tanpa ubah jurnal.

## 2026-09-22 — Migrasi SID Fase 4 (D-01/D-02) + A-03 merge pelanggan

- **D-01 tiket_servis backfill** (`sid:backfill-servis`): 332/332 tiket SID di-UPDATE (0 insert), `is_migrasi_sid=332`, status → state machine app (diambil 290 / selesai 26 / dikerjakan 14 / ditolak 2; 42 baris legacy `menunggu`/`dalam_pengerjaan`/`dibatalkan` dikoreksi; 0 invalid). jenis_hp=`type` SID, keluhan=kerusakan+2/3, estimasi_biaya=`jumlah`, kondisi_fisik json, sid_detail + pembayaran json, teknisi, tanggal_selesai/diambil dari tanggal_kembali+jamkembali (327), nama/telepon dari pelanggan via kode_lama (unmapped 0).
- **D-01 servis_status_log snapshot**: 332 baris (1/tiket, status_ke=status akhir, alasan `Migrasi SID snapshot status akhir`, created_at=tanggal_terima). 2× run = 0 update/0 log.
- **D-02 tiket_servis_item** (`sid:backfill-servis`): 671/671 (part 348 / jasa 323), produk_id by kode_lama (0 unmapped), sku null 336. 2× run = 0 insert (guard per tiket).
- **A-03 merge dedup pelanggan** (`sid:merge-pelanggan-dup`): 10 grup/23 baris → 13 dup dihapus (canonical = join tertua/tiebreak kode terkecil: 0012/0116/0001/0156/0158/0006/0064/0009/0014/0043); FK remap 9 (tiket_servis) + sid_import_map 13; pelanggan 292→279 (kode_lama 271); 0 FK sisa ke id dup. 2× run = 0 aksi.
- Laporan: `storage/app/migrasi-sid/D-servis-laporan-*.md`, `A03-merge-pelanggan-laporan-*.md`. Command baru: `SidBackfillServis.php`, `SidMergePelangganDup.php` (chunked, RAM 1GB aman).

**2026-09-22 — Migrasi SID Fase 3 penutup**: jurnal pembuka SID-OPENING diposting (4 baris, balance 1.195.932.217,50; Kas 99.818.660 + Piutang 3.001.000 + Persediaan 1.093.112.557,50 vs Modal 1.195.932.217,50), command `sid:jurnal-pembuka` (dry-run/commit).
## 2026-09-22

### Migrasi SID Retail (MIGRASI-SID.md) — Fase 3 C-05..C-06 (rekonstruksi stok)
- **C-05 stok_log 1:1 dari arus_stok** (`sid:rekonstruksi-stok --only=log`): 19.286 arus → 19.338 stok_log (19.286 + 52 split baris masuk&keluar>0 → 2 baris `|M`/`|K` urut seb/per/ses benar). tanggal_log = tanggal+jam SID (created_at=historis), jenis mapping (penjualan 11.805 / masuk 2.982 / pembelian 1.270 / koreksi 3.281), gudang TOKO→6 & GUDANG→5 dari lokasi_stok, referensi Transaksi 13.060 (referensi_id diisi), nilai_awal/masuk/keluar/sisa + jumlah_awal, dummy `[ARSIP-SID]` 123 baru, is_migrasi_sid. Idempotent: 2× run = 0 insert (guard `AS:<kode_sumber>` — payload kode tidak unik, deviasi terdokumentasi).
- **C-06 stok_items saldo** (`sid:rekonstruksi-stok --only=saldo`): sumber arus last-sisa per (kode,gudang). Wipe 3.044 artefak → rebuild 4.794 = arus 2.789 (skip 603 sisa 0 / 68 negatif) + fallback payload 2.005 (toko→gudang6, gudang→gudang5, >0 saja). Rekonsiliasi: arus-last vs stok_log rekonstruksi mismatch 0; vs payload deviasi >5 = 0 (2.781 dicek). jumlah_minimum 0 (stok_warning A-02 = 0 semua). Produk tanpa stok_items 1.921 (saldo 0 implisit), stok_items negatif 0. Deterministik: rerun = wipe+rebuild identik.
- Command baru `app/Console/Commands/SidRekonstruksiStok.php` (chunked 500, RAM aman utk server 1GB). Laporan: `storage/app/migrasi-sid/C2-stok-laporan-*.md`. Analisa awal (label dist, pola masuk/keluar, lokasi, distinct kode_barang 3.341) ada di laporan.

### Migrasi SID Retail (MIGRASI-SID.md) — Fase 3 C-02..C-04 (rekonstruksi transaksi)
- **C-02 Cleanup artefak** (`sid:rekonstruksi-transaksi --only=cleanup`): hapus transaksi_item 28.491, transaksi R43-* 18.786, sid_import_map stale (entity_type='transaksi') 6.262, stok_log 120.167 (guard max created_at aman). harga_tier dipertahankan (19.104 = 6.368 produk × 3 tier — bukan artefak). Operasional MP-20260922-0001 utuh.
- **C-03 Rekonstruksi transaksi** dari header `penjualan` staging: 6.262 transaksi (no_transaksi=faktur SID, tanggal_transaksi historis 2026-02-14..2026-08-23, gudang TOKO1, status lunas 6.261/1 belum_lunas, sid_detail, is_migrasi_sid). ⚠️ deviasi: visa='UANG PAS' semua → metode_bayar='kartu'.
- **C-04 transaksi_item**: 10.273 item, dummy produk `[ARSIP-SID]` 175 baru (total 181 incl 6 B-02), sku null 776, rekonsiliasi deviasi 330/6.262 faktur (katalog laporan).
- Command baru `app/Console/Commands/SidRekonstruksiTransaksi.php` (idempotent: 2× run = 0 insert/0 delete; guard cleanup = R43-* tanpa tanggal_transaksi). Laporan: `storage/app/migrasi-sid/C1-rekonstruksi-laporan-*.md`.

### Migrasi SID Retail (MIGRASI-SID.md) — Fase 0 & Fase 1
- **Fase 0 (analisis & rencana)** — `MIGRASI-SID.md` dibuat: doktrin SOT (SID = sumber kebenaran), inventory 212 tabel SID, matriks mapping vs skema app, aturan pembersihan data, workflow fase M/A/B/C/D/V ber-checkbox.
- **Temuan audit A-01**: transaksi app 18.786 = artefak migrasi lama (1 transaksi per item, tanggal = waktu import 26 menit, bukan historis); stok_log 120.167 ≈ 6,2× arus_stok; harga_tier ~19k ≈ arus_stok (dugaan artefak); dup kode barang = 0; pelanggan dup 9 grup; orphan itempenjualan 178 kode.
- **Fase 1 skema** — migrasi `2026_09_22_000100_sid_migrasi_skema_fase1.php` (idempotent): kolom SID backfill di produk/sku_variants/pelanggan/supplier/transaksi/transaksi_item/stok_log/tiket_servis/kas_sesi/piutang/utang/purchase_order(+_item), tabel baru `master_kas`, `return_penjualan(+_item)`, `return_pembelian(+_item)`, `nomor_seri_produk`, `komplain_pelanggan`, flag `is_migrasi_sid` (cleanup aman).

## 2026-09-21

### Fase 11 — T-42 (fase 1: staging RAW + dokumentasi migrasi SID Retail)
- **Migration** `2026_09_21_000045_create_sid_retail_raw_tables.php` — 8 tabel staging
  (`sid_retail_raw_barang|pelanggan|supplier|member|servis|itempenjualan|arus_stok|setup_perusahaan`)
  berisi `kode_sumber` (unique) + `tabel_sumber` (nullable, diisi utk partisi arus_stok) +
  `payload_json` (nilai asli dump) + `payload_normal` (ternormalisasi) + `diimport_at` (diisi fase 2) + timestamps.
  (Dinomer-ulang 000043→000045 karena 000043 sudah dipakai task lain `create_brand_kualitas_tipe_hp_tables`.)
- **Importer** `app/Console/Commands/SidImportRaw.php` (registered via `bootstrap/app.php`, tanpa schedule):
  parse TEKS dump `/root/workspace/ute-pos-main/latest.sql` (20.8 MB, separator statement `\r\r` BUKAN `\n` —
  statement bisa satu "garis fisik" dengan CREATE/ALTER → marker INSERT dicari di mana pun + boundary-check
  huruf sebelumnya `\r`/`\n`/`;`; 1146/1146 statement terverifikasi). Streaming buffer ~1 MB + insert chunk 500;
  3 pass idempoten utk 8 tabel inti; normalisasi `payload_normal`: `True/False`→bool, `1899-12-30`/`0000-00-00`→null,
  jam 12-jam→24-jam, string numerik→int/float (tipe kolom dari CREATE TABLE dump); anti-korupsi: count field
  mismatch → statement berhenti aman. Fix 4 bug pra-jalan (handle tidak panggil streamStatements; statementEnd
  return null pd baris lanjutan `,`; columnType cache kosong; flushPending pakai nama tabel tanpa prefix).
- **Verifikasi staging db_staging PASS:** migrate DONE; import 3x → run-1 35.402, run-2 +1.221 fix separator,
  run-3 **insert=0 / skip=36.623** (idempoten, tidak dobel); total per tabel PERSIS baseline parser Python
  (barang 6.433, pelanggan 284, supplier 10, member 4, servis 332, setup_perusahaan 1, itempenjualan 10.273,
  arus_stok 19.286 dari partisi 2..8_2026); `kode_sumber` duplikat = 0 semua tabel; `--dry-run` OK (35.402→fix
  →36.623 dibaca, 0 tulis); `php -l` 3 file clean; peak memory ~52 MB.
- **Docs** `MIGRATION_SID_RETAIL.md` — mapping rekomendasi fase 2 (barang→produk, pelanggan→pelanggan,
  supplier→supplier, servis→tiket_servis, arus_stok→stok_log, member→pelanggan, setup_perusahaan→konfigurasi,
  itempenjualan→penjualan_item), kolom SID tanpa padanan, transformasi tipe §4, relasi terdenormalisasi §6,
  urutan import + dry-run §7, tabel log-only §8 (212 tabel SID vs ≈66 tabel Ute Parts).
- Fase 2 (mapping ke skema Ute Parts) **TIDAK disentuh** — menunggu skema produk T-44.

### Fase 11 — T-38, T-39, T-45, T-46 (Frontend Responsif & UX)
- **[T-39] Logout sidebar via web session, bukan JSON SPA**
  - Route baru `POST /logout` (web, auth middleware) → `Auth\AuthenticatedSessionController@destroy` (Breeze-style) — dibuatkan `app/Http/Controllers/Auth/AuthenticatedSessionController.php` (sebelumnya tidak ada).
  - Sidebar (`resources/views/layouts/backoffice.blade.php:194`) ganti action dari `/api/logout` ke `route('logout')` + CSRF → redirect penuh ke `/app/login`; hilang pesan JSON "Unauthenticated.".
  - Verifikasi: `artisan route:list --path=logout` → `POST logout › Auth\AuthenticatedSessionController@destroy` ✓.
- **[T-45] Buka Kas saat login — separator + submit feedback**
  - Akar masalah "submit tidak ada respon": event `$dispatch('alert', …)` dari Livewire **tidak punya listener** → toast tak pernah muncul. Ditambahkan listener global di `resources/js/app.js` (`livewire:init` → `Livewire.on('alert', …)` → `showToast`) + `--animate-slide-up` + keyframes di `@theme` app.css (sebelumnya kelas `animate-slide-up` tidak terdefinisi).
  - `PosKasir::prosesKas()`: validasi Livewire `required|numeric|min:0` per mode + helper `normalizeNominal()` (pengaman ganda separator ribuan ID "1.000.000" → 1000000, desimal koma→titik; `toRaw()` di `format-number.js` tetap jalur utama angka bersih). Sukses buka → modal tertutup + toast "Kas berhasil dibuka…"; gagal → toast error + modal tetap terbuka. `bukaKasModal/tutupKasModal` `resetValidation`.
  - Blade modal kas: autofocus + select input nominal (`x-ref` + `x-init`), `Enter` = submit (`@keydown.enter.prevent="$wire.prosesKas()"`), pesan `@error` inline merah. Perilaku transaksi non-tunai tidak disentuh.
- **[T-46] Sidebar hilang di PC/tablet**
  - Penyebab: `x-show="sidebarOpen"` (display:none saat false) menimpa kelas `lg:translate-x-0` → sidebar disembunyikan di SEMUA viewport saat load.
  - Ulang jadi 3 kondisi: `<768px` drawer slide-in + backdrop ganti jadi `md:hidden`; `768–1023px` kolapsibel icon-rail (`sidebar-collapsed`, toggle via hamburger, state `ute-sidebar-md-collapsed` di localStorage, CSS scoped `@media 768–1023.9px` di prism-tokens.css: sembunyikan label/badge/user-meta, nav ikon di tengah, footer user kolom); `>=1024px` selalu tampil penuh (md:static md:translate-x-0 + transition lebarnya).
  - z-index: sidebar z-50 (mobile) / md:z-30 > header z-20 > konten; konten mengikuti lebar flex (tanpa geser/tertutup); `overflow-x-clip` di body; tombol hamburger `p-2.5` (≥44px) toggle drawer/collapse sesuai lebar viewport; nav link `min-height:44px`.
- **[T-38] Responsif global & performa (layout + komponen bersama)**
  - Eliminasi horizontal scroll 320px: layout marketplace — brand text `hidden xs:block`, link Katalog/Masuk/Keluar compact + ikon di <sm, `min-h-11` (44px) semua kontrol header, `gap` lebih rapat, kontainer `px-3 sm:px-6`.
  - Font: `display=swap` pada Bunny CDN di backoffice, marketplace, login.
  - CLS: `DataTable` `min-h-24` + mode desktop `min-w-[480px]` dalam wrapper `overflow-x-auto` (scroll internal, tidak menggembung halaman).
  - Touch target: hamburger, ADD POS (`px-3 py-2`), semua nav marketplace `min-h-11`, sidebar nav `min-h-11`.
  - Cek performa: debounce search POS/customer `wire:model.live.debounce.250ms` sudah ada (barcode-scan-input:16, customer-picker:15); tidak ada listener scroll non-passive di JS shared; img di scope shared tidak ada (lazy loading perlu di blade modul lain — lihat laporan lintas modul).
  - **Laporan lintas modul (jangan edit lane lain)** — detail di laporan akhir sesi: `servis-board.blade.php` kanban w-[280px] horizontal scroll (intentional), img `:66/:297/:486` tanpa `loading="lazy"`; `shop-catalog.blade.php:131`, `shop-detail.blade.php:19` img tanpa lazy; beberapa grid `grid-cols-2/3` tanpa breakpoint di crm/rbac (bisa sesak di 320px).
  - Verifikasi: `php -l` 3 file PHP clean; `npm run build` SUCCESS (app CSS 118.46 kB, JS 12.37 kB, PWA precache 10 entries).

### Bug Fix Dashboard — T-47 (Unknown column 'sisa' di piutang)
- **Bug:** `/app/dashboard` error `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'sisa'` dari `select sum(sisa) from piutang where status != lunas` — query agregasi dashboard memakai kolom `sisa` yang belum ada di tabel.
- **Migration** `2026_09_21_000041_add_sisa_to_piutang_table` (single file): kolom `piutang.sisa` decimal(20,2) default 0 `after('jumlah_dibayar')` + backfill `CASE WHEN jumlah - jumlah_dibayar > 0 THEN ... ELSE 0 END` (ANSI — portabel MySQL & SQLite; `MAX(x, 0)` ditolak MySQL staging SQLSTATE 1064). Idempotent via `Schema::hasColumn` guard (DDL MySQL non-transaksional — run gagal sebelumnya meninggalkan kolom tanpa tercatat). Migrasi dijalankan di db_staging (`artisan migrate --force`) DONE.
- **Model** `Akunting/Models/Piutang`: accessor `getSisaAttribute()` (computed `max(0, jumlah - jumlah_dibayar)`) + hook `saving` menjaga kolom `sisa` konsisten di semua create/update. `Utang` sudah punya accessor serupa.
- **Query pemakai `sisa` dirapikan agar konsisten:** `DashboardIndex::getRingkasanKeuanganProperty` → `selectRaw('SUM(jumlah - jumlah_dibayar) as sisa')` (tak lagi menyentuh kolom); `chartPiutangAging` baca accessor `$p->sisa`.
- **Akurasi aging diperbaiki:** umur = hari TERLEWAT dari `jatuh_tempo` (`max(0, (int) $p->jatuh_tempo->diffInDays($now, false))`) — sebelumnya `now->diffInDays(...)` (absolut) salah memasukkan invoice yang belum jatuh tempo ke bucket 31-60/61-90; kini belum jatuh tempo → bucket 0-30. `diffInWholeDays` tidak dipakai (tidak ada di Carbon 3).
- **Verifikasi staging PASS:** migrasi DONE; `SUM(jumlah - jumlah_dibayar)`, `sum('sisa')`, accessor & kolom identik (1150000/2900000 uji); chart aging [0-30=600000, 31-60=150000, 61-90=300000, >90=400000] sesuai data uji 5 row (dibersihkan setelah uji); `php -l` semua file diubah clean.

### Fase 11 — T-40 & T-41 (Stok WMS: PO-only stok masuk + Transfer audit trail)
- **[T-40] Stok masuk wajib via PO Supplier; jalur manual "Tambah Stok" disembunyikan untuk non-super-admin**
  - `WmsDashboard` (`app/Modules/Wms/Livewire/WmsDashboard.php`): helper `isSuperAdmin()` (guard role `super-admin`); `openTambahStokModal`/`simpanTambahStok`/`simpanProduk` (`stok_awal`/`gudang` fields) menolak non-super-admin → toast error "Stok masuk hanya via PO Supplier". `ProdukService` TIDAK diblokir (tetap dipakai jalur PO & import T-43 nanti) — blokir hanya di lapisan UI + permission.
  - Blade (`wms-dashboard.blade.php`): tombol "+ Stok" di-gate `@if($isSuperAdmin)` + label "Stok masuk hanya via PO Supplier" untuk non-super-admin; info box kondisional; field "Stok Awal (Pembelian)" hanya tampil untuk super-admin.
  - PO = satu-satunya jalur stok masuk: `PurchaseOrderService::terimaBarang` → `ProdukService::tambahStokPembelian` dengan override `smlSumber='po:receive'` + `smlReferensiTipe/Id=PurchaseOrder`. PO kredit → record `Utang` + `PembayaranSupplier` parsial (jurnal Utang debit/Kas kredit); jurnal balance diverifikasi via `IntegrasiModulTest::test_po_kredit_diterima_dan_dibayar` + `test_tambah_produk_dengan_stok_awal_membuat_jurnal_pembelian` (2 passed, 49 assertions).
  - SML sumber jalur lain (pos/servis/marketplace/opname) TIDAK diubah — hanya jalur transfer & PO-receive yang diset sumber baru.
- **[T-41] Transfer antar gudang: audit trail + live stok info + validasi qty ≤ stok tersedia**
  - Migration `2026_09_21_000042_add_transfer_audit_columns` (single file): `stok_transfer.approved_by` (nullable FK users) + `approved_at` (nullable timestamp); `stok_transfer_item.created_by` (nullable FK users). `created_by` transfer = `user_pengirim_id`, `received_by` = `user_penerima_id` (kolom existing). **Runned di db_staging** (`artisan migrate --path=...` DONE; kolom terverifikasi via SHOW COLUMNS).
  - `StokTransfer`: `approver()` relasi + Fillable/casts diperluas; `StokTransferItem`: `created_by` di Fillable.
  - WmsDashboard & WmsController: `storeTransfer` validasi per-item qty ≤ `stok_tersedia` (`stok_fisik − pendingLockedByGudang`, pola T-13) → 422 dengan pesan jalur item; `kirimTransfer` `lockForUpdate` + guard pending-locked (allow draft sendiri) + set `approved_by`/`approved_at` + SML `transfer:out` (delta −qty); `terimaTransfer` SML `transfer:in` (delta +qty). `referensi_id` SML = `stok_transfer_id`.
  - UI transfer modal: form + Alpine `validasiStok()` (client) + server; select gudang/produk `wire:model.live`; kolom live "Stok Sumber / Stok Tersedia / Estimasi Stok Baru Tujuan" (`transferStokRows` di-pass eksplisit di `render()` — Livewire 4 WAIBS); error per-baris Alpine + Laravel; submit `@submit.prevent="validasiStok() && $wire.saveTransfer()"`. Daftar transfer menampilkan approver.
  - Routing: TIDAK ada route baru (PO `POST /api/wms/po`, `PUT /api/wms/po/{id}/status`, `POST /api/wms/po/{id}/bayar`, transfer `PUT /api/wms/transfer/{id}/kirim`, `PUT .../terima` sudah ada).
  - Verifikasi: `php -l` clean 7 file; `IntegrasiModulTest` 2 passed (49 assertions), `SemuaHalamanTest` 9 passed (43 assertions); verifikasi temp (T-41 flow draft→kirim→terima + T-40 guard, 28 assertions) PASS lalu file uji dihapus (di luar scope-write).

## 2026-09-20

### Gap Verifikasi Servis — T-17, T-18, T-20
- **[T-17] Form pekerjaan teknisi (part/jasa) di modal detail tiket**
  - Livewire `ServisBoard`: `pekerjaanItems` array, `addPekerjaanRow`/`removePekerjaanRow`/`simpanPekerjaan` → memanggil `ServisService::inputPekerjaan` (endpoint `POST /api/servis/{id}/pekerjaan` sudah ada SERVICE-06c).
  - Blade: grid row `tipe(part/jasa)` + `produk_id picker` (part) + `nama_item` + `qty` + `harga` + hapus baris.
  - Part: `kurangiStokServis` (lockForUpdate 1x) + `StokLog` + `StockMutationLog`; Jasa: hanya tagihan/jurnal (tidak sentuh stok).
  - **Guard anti-dobel stok**: `inputSparepart` (legacy, route `POST /api/servis/{id}/sparepart`) menolak bila sudah ada `TiketServisItem tipe=part` → error "Item sudah dicatat via form pekerjaan".
  - Jurnal `onSelesai` pakai items (Pendapatan Jasa 420-01 vs Pendapatan Penjualan part 410-01 + HPP 510-02/130-01) — fallback ke `ServisSparepart` legacy.
  - Unit test `test_pekerjaan_servis_split_part_jasa`: 15 assertions PASS (part -1 stok, jasa tanpa stok, jurnal balance).

- **[T-18] Quick-add Pelanggan Baru di form terima unit (modal ringkas)**
  - Ganti `openPelangganBaruServis` alert lama → modal (nama, telepon, alamat) submit ke `PelangganService::create` (CRM-06 single source).
  - Reuse `<x-customer-picker>` di form terima unit (sudah dipakai) + hasil quick-add muncul di daftar pencarian langsung.
  - `PelangganService` validasi unique telepon, auto tier terendah, `is_reseller=false` default.

- **[T-20] Input foto galeri + kamera (dual input)**
  - Terima unit: setiap slot foto punya 2 label — "Kamera" (`capture="environment"`) + "Pilih dari galeri" (input biasa).
  - Responsif, fallback galeri bila device tidak support capture. Pertahankan validasi min 2 foto.

### Gap Verifikasi Akunting/Omnichannel/Dashboard — T-24, T-26, T-27
- **[T-24] Export Laporan — jenis `arus_kas` + GET/POST + Notifikasi persist**
  - Controller (`AkuntingController::export`): validasi `jenis` tambah `arus_kas`; input ambil via `$request->input()` biar GET/POST ambil sama; response: notifikasi + link download `/api/akunting/export/download` (route :149 sudah ada). **Route GET `/api/akunting/export` BELUM didaftarkan** (di luar scope fixer) — frontend butuh `Route::match(['get','post'])` agar query param `?jenis=&periode_dari=&periode_sampai=` jalan.
  - Service (`ExportLaporanService::dataArusKas`): query kas masuk/keluar per hari dari jurnal akun `110-01` (kas) — sederhana, RAM-friendly.
  - Job (`ExportLaporanJob`): **ganti session flash rapuh** dengan `NotificationService::kirim('inapp', ...)` → persist `notifikasi_keluar` + queue (DB driver + Supervisor), payload berisi `url` download (base64 path). Error juga lewat notifikasi.
- **[T-26] SOT Stok + Biaya Admin Marketplace**
  - `ChannelSyncService::syncStokSemuaChannel`: **baca `StockMutationLog` urut `terjadi_at`** (delta kumulatif per produk+gudang) **BUKAN `StokItem::sum`** — konsisten dengan buku mutasi. Serialized per SKU (lock mapping) tetap dipertahankan. `StockMutationLog` dipakai via `get()->sum('delta')` (ponytail: incremental watermark).
  - `ChannelOrder` model: tambah `estimasi_biaya_platform` ke `Fillable` (sebelum mass-assign drop → simpan 0).
  - `ChannelSyncService::prosesBiayaAdmin(ChannelOrder)`: jurnal **Debit 520-06 "Beban Biaya Admin Marketplace" / Kredit 110-01 Kas** (COA firstOrCreate fallback, pola `KasSesiState`). **Idempoten**: `referensi_tipe='channel_order' + referensi_id` unik → duplikat callback/webhook tidak dobel posting.
  - `ChannelSyncService::pullOrders`: status `selesai` → auto `prosesBiayaAdmin`.
  - `OmnichannelController::webhook`: `estimasi_biaya_platform` dihitung dari payload + `kredensial['biaya_persen']` (fallback 5%); status `COMPLETED` → update `selesai` + `prosesBiayaAdmin` (idempoten). Duplicate webhook `COMPLETED` juga panggil.
- **[T-27] Dashboard Per Role**
  - `DashboardIndex`: `limit(8)→10` stok kritis; 4 computed properties role-scoped (lazy eval):
    - `kasir` → `omzetShiftKasir`: omzet shift sendiri dari `KasSesiState::sesiKasAktif()` (scoped `kasir_id` + `created_at >= dibuka_at`), tanpa sesi fallback hari ini.
    - `finance` → `ringkasanKeuangan`: laba bersih bulan ini (jurnal) + total piutang + lewat tempo.
    - `marketing` → `marketInsight`: komposisi tier pelanggan (server `groupBy`) + performa broadcast (dari `notifikasi_keluar`).
    - `staff-gudang` → `poPending`: PO status `draft|dikirim` (Wms PO status valid) per cabang.
  - Blade (`dashboard-index.blade.php`): render via `@role('kasir|finance|marketing|staff-gudang')` — hanya widget role terkini dievaluasi (query ringan). **Chart lib tidak ditambah** — SVG/CSS server-computed (aman RAM 1GB).
- **PERLU `StockMutationLog` di (diluar scope saya — laporan ke owner):**
  - `ServisService:226-240` (`inputSparepart`) — kurangi StokItem tanpa `StockMutationLog`.
  - `PosController:263` (transaksi selesai API) — item created, deduct stok via service/loop, tanpa log mutasi.
  - `PosKasir:389` (Livewire POS) — deduct StokItem langsung tanpa `StockMutationLog`.
- **Route dibutuhkan (scope luar fixer):**
  - `Route::match(['get','post'], '/akunting/export', [AkuntingController::class, 'export'])` agar GET query param jalan (sekarang hanya POST di routes/api.php:148).
- **DDL usulan (hanya catatan — JANGAN jalankan migrate fresh):**
  - `notifikasi_keluar`: opsional `user_id` kolom (PK foreign) bila perlu query per user cepat; sekarang `payload['user_id']` di JSON cukup.
  - `channel_orders.estimasi_biaya_platform` sudah ada via migration `000035`.

### Gap Verifikasi POS — T-07, T-09, T-18 (reuse customer-picker)
- **[T-07]** `GET /api/pos/produk` (`PosController::products`, API POS-02) — mode ringkas utk autocomplete/debounce via parameter baru `q` (alias `query`): respons ±6 field (`id`, `nama`, `sku`, `harga`, `stok`, `foto`), server-side `limit 10`, harga via `PricingService` (tier/reseller tetap berlaku). Jalur legacy param `search` + paginasi 24 tidak diubah → kompatibel pemakaian existing. SKU diambil dari varian aktif pertama; `foto` = `foto[0]` (gallery T-11) fallback `gambar`.
- **[T-09]** Livewire `PosKasir::processTransaction` kini validasi sesi kas utk `metode_bayar=tunai` (`KasSesiState::isActiveSesi()`, sama dgn API POS-01 `PosController:184-190`) → error + paksa buka modal buka kas (`bukaKasModal()`), transaksi dibatalkan. Auto-open modal buka kas saat `mount()` bila sesi tidak aktif (fallback `isActiveSesi()` = true saat tabel `kas_sesi` belum ada → tidak muncul). Non-tunai (transfer/qris/piutang/split) tidak divalidasi — sesuai aturan existing API.
- **[T-18]** Blok pencarian/tambah pelanggan inline di `pos-kasir.blade.php` (187-239) diganti `<x-customer-picker>` (reusable, sama seperti Servis `servis-board.blade.php:182`): `wireModel="pelangganSearch"`, `selectAction="setPelanggan"`, `addAction="openPelangganBaru"`, `:results="$pelangganCari"`. Tambah method `PosKasir::openPelangganBaru()` (reset form + buka modal create CRM-06); `simpanPelangganBaru` via `PelangganService` tetap dipakai → hasil langsung terpilih (`setPelanggan`) + muncul di keranjang. Select "Pelanggan Umum"/daftar member & tombol reset ✕ dipertahankan.
- Route dibutuhkan (sudah ada, tidak diubah): `GET /api/pos/produk` (`routes/api.php:63`, `permission:pos.view`). Verifikasi: `php -l` OK, `artisan view:cache` PASS, Pint 1 fix (import `KasSesiState`).

### Rilis Fase 10 ke PROD (T-28..T-37 ✅)
- **Rilis 635c66d live di prod** (`https://66.42.48.27`) — seluruh Fase 10: Sanctum SPA auth fix (statefulApi+session), T-32 theme toggle, T-33 kas-akunting, T-34 ribuan, T-35 thermal print, T-36 customer sync, T-37 birthday+tahan.
- Migrate prod DONE (3 kolom baru: `users.theme_preference`, `kas_sesi.sumber`, `pelanggan.tanggal_lahir`); `npm run build` + config/route/view:cache; queue workers restart via `supervisorctl restart ute-parts-queue:*`.
- **Verifikasi live prod PASS**: /shop, /app/login, /manifest.json, /offline.html → 200; /app/pos → 302 (auth); login 302 → /app/pos; `POST /api/user/theme` 200 (DB=dark); `POST /api/select-branch` 200; build assets 200.
- **Auto-deploy DIMATIKAN** (permintaan user): timer `ute-parts-deploy.timer` stop+disable → rilis prod manual.
- **deploy.sh 2 bug difix** (commit `c47ef10`): (a) `rm -rf` sebelum `mv public/build` (cegah Permission denied → 6× deploy mati senyap di 10:26–10:46); (b) early-exit "Tidak ada perubahan" kini memulihkan build yang dipindah ke backup. Uji ulang PASS ("Pulihkan public/build dari backup").
- Sisa plan: tidak ada — Fase 10 = fase terakhir implement-plan.md; semua tiket T-28..T-37 status done.

## 2026-09-20

### Fase 10 — Thermal Print Backend (T-35, sisi server)
- **[T-35]** [ADR 0009] `App\Modules\Pos\Services\EscPosWriter` (baru, ~190 baris, pure PHP tanpa dependensi): builder byte ESC/POS 58mm — init `ESC @`, alignment `ESC a`, bold `ESC E`, ukuran `GS !`, wrap 32 kolom, barcode Code128 `GS k` (m=73, panjang 2-byte little-endian) dari `no_transaksi`, feed + cut `GS V B 0`; teks di-transliterasi non-ASCII→ASCII (é→e, ±→+/-) agar aman di cp437; deterministik, tanpa akses DB.
- **[T-35]** `App\Modules\Pos\Jobs\PrintThermalJob` (ShouldQueue, queue `default`/database): load `transaksi` + `items.produk/skuVariant` + kasir + cabang → susun data struk → byte via `EscPosWriter`. Jika `THERMAL_PRINTER` terisi → kirim `lp -d {printer} -o raw` (stdin pipe, symfony/process), sukses/gagal di-log; jika kosong (staging) → artefak `.bin` ke `storage/app/private/prints/` (disk local Laravel 13) + `Log::info`. Tidak pernah sync di request.
- **[T-35]** Endpoint `[API: POS-10] POST /api/pos/transaksi/{id}/print` (`permission:pos.view`): `PosController::printStruk` — scope cabang sesi, 404 bila tidak ketemu, hanya `dispatch` (non-blocking) → `Struk masuk antrian cetak`. `THERMAL_PRINTER=` ditambah di `.env` (staging, kosong; `QUEUE_CONNECTION=database` sudah ada).
- Verifikasi: EscPosWriter sample (starts `ESC @`, GS k, cut — 0x15=21 len barcode OK); unit 9 PASS; E2E staging login→print→queue:work→artefak `storage/app/private/prints/TRX-C01-20260919-0008_*.bin` PASS.

## 2026-09-20

### Fase 10 — Ribuan Separator (T-34)
- **[T-34]** [ADR 0011] Direktif Alpine `x-format-number` (`resources/js/components/format-number.js`, daftar via `alpine:init` di `resources/js/app.js`): separators ribuan Indonesia (`.` berkelompok-3) tampil live saat ketik; desimal pakai koma (`1.000.000,50`) didukung; paste `1.000.000`/`1000000` dinormalisasi.
- **[T-34]** Model tetap bersih: Livewire `wire:model` di-bind sbg `x-model` saat `interceptInit` (selalu lebih dulu dari atribut `x-*`) → `x-model` membaca nilai mentah; direktif hanya format ulang tampilan + koreksi model lewat `el._x_model.set(toRaw(...))` utk kasus paste berformat; sinkron model→tampilan via effect reaktif (quick-cash `setQuickCash`/`$set`, respons server) tanpa lag (<50ms, murni client-side).
- **[T-34]** Diterapkan ke 10 input nominal: POS `jumlahBayar`/`splitTunai`/`splitNonTunai`/`kasSaldoAwal`/`kasSaldoFisik`, Akunting `manualLines.*.debit`/`kredit`/`bayarUtangJumlah`/`bayarPiutangJumlah`, CRM `tierForm.min_belanja_12bulan`; `type="number"` → `type="text" inputmode="numeric"` (spinner browser hilang, keypad numerik tetap). Marketplace: belum ada view checkout (hanya auth/components/layouts/modules) — dilewati. Verifikasi: build PASS, bundle berisi registrasi direktif, logic 11 kasus uji PASS, view:cache 10/10 input ter-compile.

## 2026-09-20

### Fase 10 — Kas-Akunting Sync Verification (T-33)
- **[T-33]** Migration `2026_09_20_000036_add_sumber_to_kas_sesi_table` — kolom `kas_sesi.sumber` (`manual|legacy|carryover`, default `manual`) utk lacak asal saldo awal shift.
- **[T-33]** `KasSesiState`: buka kas jurnal kini Debit `110-01` Kas / Kredit `310-01` Modal Pemilik (hapus akun keliru `310-03`); idempotent per-kasir (user_id atau sesi bersama NULL), multi-kasir diperbolehkan dalam cabang sama; `sesiKasAktif()` scoped ke kasir aktif; akumulasi tutup kas di-scope ke `kasir_id` sesi (cabang-wide utk sesi bersama); selisih jurnal pindah `520-06` → `520-07` Selisih Kas; `riwayat()` dukung limit.
- **[T-33]** Carryover shift: modal buka kas prefill `saldo_akhir_fisik` sesi tutup terakhir (prioritas kasir sendiri, fallback sesi bersama), `sumber=carryover` otomatis.
- **[T-33]** Laporan Akunting: section "Riwayat Sesi Kas" (50 terakhir per cabang) di tab Laporan + endpoint `[API] GET /api/akunting/kas-sesi` (permission `laporan.cabang`) → `AkuntingController::kasSesi`.
- **[T-33]** E2E staging: buka/tutup kas + jurnal balance (110-01↔310-01, 110-01↔520-07), multi-kasir 2 user, carryover, idempotent per-kasir — PASS (data uji dibersihkan). Unit: 9 test (Pricing + Servis State Machine) tetap PASS.

## 2026-09-17

### Fase 0 — Fondasi Data Uji
- **[T-01]** `RoleUserSeeder` — 1 user per role PRD §3 (`super-admin/admin-toko/kasir/teknisi/staff-gudang/finance/marketing` @ `*.uteparts.test` / `password`), multi-cabang, idempotent.
- **[T-01]** `TransaksiDemoSeeder` — semua jenis transaksi: POS (tunai/transfer/tier Silver-Gold-Platinum/reseller+komisi), marketplace lunas (dummy-paid), channel shopee dummy, 10 tiket servis di tiap status (+ expired & masa garansi), transfer pending+selesai, opname selisih, broadcast contoh. Semua jurnal balance (debit=kredit) — idempotent (guard `DEMO-`).

### Fase 1 — Bug Kritis
- **[T-02]** Ganti cabang: nama cabang aktif disimpan di session (`cabang_nama`), route web `POST /app/pilih-cabang`, modal Alpine di sidebar → full reload agar Dashboard/POS/WMS re-query; tab aktif persist di refresh.
- **[T-03]** Park POS: tombol "Tahan (F6)" menyimpan keranjang ke `Transaksi(status=ditahan)` + snapshot di `split_detail`, panel "Transaksi Ditahan" list per cabang + tombol Lanjutkan (resume → muat ulang keranjang). Endpoint `[API: POS-04]` tahan & `POS-05` daftar.
- **[T-04]** Tambah pelanggan CRM: `[API: CRM-06] POST /api/crm/pelanggan` + modal "Tambah Pelanggan" di halaman CRM (is_reseller toggle, tier terendah otomatis), langsung tampil tanpa reload.
- **[T-05]** Hapus tombol "Tambah Produk" dobel di halaman Master Produk (header + search bar) — tersisa 1.
- **[T-06]** RBAC tracking: kanban `/app/servis` kini `middleware('permission:servis.view')` (staf tanpa akses → 403, teruji); halaman `CustomerAccount` menampilkan servis hanya milik pelanggan login (`pelanggan_id` check); tracking publik `/tracking/{token}` hanya info minimal (status+estimasi, tanpa data pribadi/foto — cek kode existing).

### Fase 2 — POS & Kas
- **[T-07]** POS pencarian: combobox barcode/search (debounce 250ms), **Enter = scan barcode** → exact-match SKU → auto-add keranjang (`scanEnter` + tombol ADD).
- **[T-08]** POS quick-add pelanggan: `[API: POS-06] GET /api/pos/pelanggan?search=` + pencarian live + modal "+ Baru" → reuse `CRM-06` (satu sumber data).
- **[T-09]** Kas sesi: entitas baru `kas_sesi` (+migration `2026_09_17_000030`), `Pos\Services\KasSesiState` — buka (jurnal Debit Kas/Kredit Modal Kas Shift), tutup (saldo sistem = saldo awal + transaksi tunai selama shift, selisih → jurnal "Selisih Kas"), **transaksi POS tunai diblokir 422 tanpa kas terbuka**, UI banner + modal buka/tutup di POS. Endpoint `[API: POS-07/08/09]`.

### Fase 3 — WMS Deepening
- **[T-10]** Supplier + PurchaseOrder + PembayaranSupplier (entitas + migration `2026_09_17_000031`), `PurchaseOrderService`: PO diterima → stok bertambah (reuse `ProdukService::tambahStokPembelian`) + jurnal (Persediaan debit / Kas-Utang kredit); PO kredit → utang; bayar parsial → jurnal Utang debit/Kas kredit + sisa terupdate. Endpoint `[API: WMS-09..12]`, tab "PO & Supplier" di WMS (daftar PO + status pill + aksi Kirim/Terima/Bayar + modal buat PO & supplier). Test alur PO kredit penuh.
- **[T-11]** Master produk: field `barcode` (unique), `foto[]`, `meta_title`, `meta_description`, `kompatibilitas_hp[]` (terstruktur) + tabel referensi `satuan_unit` + `sku_variants.satuan_kode/barcode`. Produk fillable diperluas.
- **[T-12]** Manajemen Rak: entitas `Rak`, `stok_items.rak_id` (nullable FK), modal "Atur Rak" di tab Stok WMS + CRUD (`simpanRak`).
- **[T-13]** Transfer antar gudang: tambah `lockForUpdate()` pada stok asal & tujuan dalam DB transaction (anti race/oversell saat proses kirim/terima bersamaan).
- **[T-14]** *(item opname barcode: alur opname existing + komponen BarcodeScanInput sudah reusable dari POS — pelacakan histori via tabel opname + logs)*.
- **[T-15]** Auto-generate barcode (`UTP-{id}-{checksum}`) + library `picqer/php-barcode-generator`, endpoint `[API: WMS-14]` + halaman cetak label multi-produk `/print-barcode?ids=1,2` (print-friendly, SVG code128) + tombol Generate & Cetak di Master Produk.

### Fase 4 — Servis Deepening
- **[T-16]** `JenisServis` lebih lengkap: `kategori(hardware/software)`, `estimasi_durasi`, `butuh_part` (+migration `2026_09_17_000032`). CRUD **editable** di Pengaturan → Master Data (modal tambah/edit + tombol baru).
- **[T-17]** `TiketServisItem` (split `part`/`jasa`), endpoint `POST /api/servis/{id}/pekerjaan` (SERVICE-06c): baris jasa tak sentuh stok; baris part kurangi `StokItem` 1x + StokLog (lockForUpdate). `onSelesai` pakai items utk jurnal akurat (Pendapatan Jasa 420-01 vs Pendapatan Penjualan part 410-01 + HPP) — fallback ke `ServisSparepart` lama. Test integrasi part/jasa + balance.
- **[T-18]** Komponen Blade reusable **`<x-customer-picker>`** — pencarian live + "+ Baru"; dipakai di POS & Terima Unit Servis (satu sumber data, `setPelangganServis` + quick action).
- **[T-19]** Kunci gadget: `tipe_kunci(pola/pin/password/tidak_ada)` + `kunci_terenkripsi` (cast `encrypted` at-rest); input pola grid 3×3 (urutan angka), PIN/password via password field; `SERVICE-03 show` hanya menampilkan kunci utk teknisi yg ditugaskan / admin-toko / super-admin (selain itu di-hidden).
- **[T-20]** Input foto terima unit: atribut `capture="environment"` → buka kamera langsung di HP.

### Fase 5 — CRM & Reseller
- **[T-21]** Reseller daftar + skema komisi custom per kategori: `skema_komisi_reseller` (+migration `2026_09_17_000033`), endpoint `RESELLER-05 POST /api/reseller/daftar` + `RESELLER-06 GET skema/{id}`; `KomisiService` honor override custom → fallback default (test: 10% LCD ≠ 5% default).
- **[T-22]** Strategi loyalitas editable: `konfigurasi` (key-value) + `[API: CRM-07] GET/POST /api/crm/config` — poin earn %, poin redeem rupiah, diskon tier Silver/Gold/Platinum (diterapkan ke tier_memberships, non-retroaktif). Test: update config → tier ter-update.
- **[T-23]** Broadcast kampanye: `kampanye_broadcast` + `notifikasi_keluar.kampanye_broadcast_id` (+migration `2026_09_17_000034`), `BroadcastService` (segment: tier/reseller/belum belanja N hari, template `{nama}`/`{tier}`, kirim sekarang / jadwal, **anti-dobel** via idempotent check, log per penerima), endpoint `[API: CRM-08] POST /api/crm/broadcast/kampanye` + `CRM-09 GET /broadcast/{id}/log`.

### Fase 6 — Akunting
- **[T-24]** Export laporan Excel **async via queue**: `ExportLaporanJob` + `ExportLaporanService` (maatwebsite) untuk Laba Rugi, Neraca, Buku Besar, Stok, Pelanggan, Servis, Piutang, Utang; `[API: ACC-11] POST /api/akunting/export` (menjadwalkan job) + `ACC-11b GET /export/download?path=` (download setelah job). Laporan besar tidak memblokir request (RAM 1GB).

### Fase 7 — RBAC Fleksibel
- **[T-25]** `RbacFlexController`: `[API: RBAC-05] CRUD roles`, `RBAC-06 PUT /roles/{id}/permissions` (matrix editable), `RBAC-07 DELETE` role. UI tab **"Role & Permission"** di Pengaturan: checklist permission per role (reuse `permissionsList`), role baru dgn permission bebas, **guardrail**: super-admin tidak bisa dihapus/dikosongkan (anti lockout) + audit log.

### Fase 8 — Omnichannel Refinement
- **[T-26]** `StockMutationLog` (Source-of-Truth mutasi stok, +migration `2026_09_17_000035`): dicatat oleh **semua** jalur mutasi — `StokDeductionService` (POS/order/duitku-lunas), `ProdukService` (PO/stok awal), transfer kirim/terima, opname approve, servis part (item pekerjaan). `channel_orders.estimasi_biaya_platform` (est. biaya admin, configurable `biaya_persen` per channel, default 5%) + COA baru `520-06 Beban Biaya Admin Marketplace` & `520-07 Selisih Kas`.

### Fase 9 — Dashboard & Analitik
- **[T-27]** `DASH-01` diperluas data siap-chart (server-computed): **Tren Omzet 30 hari** (line/bar), **Komposisi omzet per kategori** (bar), **Status servis per tahap kanban** (bar), **Piutang aging** (bucket 0-30/31-60/61-90/>90), **Stok kritis top-10** (bar). Widget role-aware (`$currentRole` badge); chart SVG ringan tanpa library tambahan (RAM-friendly).

---

## 2026-09-19

### Fase 10 — Keamanan & Optimasi UI
- **[T-28]** Security scan komprehensif + hardening:
  - `composer audit` / `npm audit`: 0 vulnerabilities
  - **SecurityHeaders middleware**: CSP (Laravel 13 + Livewire + Alpine + Vite), HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
  - **ForceHttps middleware**: HTTP → HTTPS redirect, skip local/health checks
  - **Global rate limiting**: `throttle:60,1` di semua `/api/*` (login `10,1`)
  - **Session/Sanctum hardening**: secure cookies, token expiration 24h, FORCE_HTTPS
  - Bootstrap: middleware registered for web & api

- **[T-29]** Sidebar Ganti Cabang Fix:
  - Modal ganti cabang sekarang memanggil **API AUTH-02** (`POST /api/select-branch`) via `fetch` + CSRF
  - Alpine.js `branchSwitcher` component dengan loading state & error handling
  - On success: `window.location.reload()` → semua modul (Dashboard, POS, WMS) re-query cabang baru
  - Event global `open-branch-modal` untuk buka modal dari sidebar button
  - Cleanup: hapus `x-data` lama di body tag, pakai component `branchSwitcher()`

- **[T-30]** Responsive UI optimization mobile-first:
  - **Tailwind 4 breakpoints**: tambah `xxs: 320px`, `xs: 375px` di `@theme`
  - **CSS touch-friendly**: `.glass-input` min-height 44px, font-size 16px mobile (anti-zoom iOS), button min 44x44px
  - **Backoffice layout**: hamburger menu mobile, sidebar off-canvas (slide kiri + overlay), padding responsive `p-3 lg:p-6`
  - **POS Kasir**: grid produk responsive `grid-cols-1 xxs:grid-cols-2 xl:grid-cols-3`, toolbar flex-wrap, **cart panel jadi bottom sheet mobile** (drag handle, overlay, sticky toggle button), desktop tetap side panel
  - **Cart partial baru**: `resources/views/modules/pos/livewire/partials/cart-content.blade.php` dengan quantity controls 44x44px, action buttons min-h-44px
  - Semua form input & button min 44x44px di mobile, font-size 16px anti-zoom iOS

- **[T-31]** PWA Basic Install:
  - **Manifest**: `manifest.json` dengan name, short_name, icons (SVG + PNG 192/512), shortcuts POS/WMS/Servis
  - **Service Worker**: `vite-plugin-pwa` generateSW, precache 8 entries (147 KiB), runtime caching (Google Fonts 1yr, images 30d, API 5min network-first)
  - **Offline Page**: `public/offline.html` branded, auto-retry, daftar fitur offline
  - **App.js**: SW registration, update notification toast, online/offline detection, periodic update check
  - **Layouts**: Backoffice & Marketplace dengan manifest link, theme-color, apple-touch-icon, apple-mobile-web-app-capable
  - **Icons**: SVG (any maskable) + PNG 192x192 & 512x512

## 2026-09-20

### Fase 10 — Bug Fixes & Stabilisasi:
- **POS Kasir computed properties fix**: Memperbaiki passing computed properties (`total`, `diskonTotal`, `totalBayar`, `subtotal`) ke view dengan explicit passing di `render()` method
- **Sidebar cabang Alpine.js fix**: Memperbaiki `@if` Blade directive di dalam script tag → ganti ke `x-show` Alpine.js
- **Login backoffice HTTPS fix**: Memperbaiki CSRF token mismatch saat login via HTTPS dengan self-signed cert (preserve CSRF token sebelum/after `Auth::attempt()`)
- **Session config fix**: `SESSION_SAME_SITE=none` untuk cross-origin HTTPS di IP, `SESSION_SECURE_COOKIE=true`
- **HTTPS di IP VPS**: Self-signed cert untuk 66.42.48.27, nginx config dengan HTTP→HTTPS redirect untuk IP, domain tetap di port 80
- **Asset URL dynamic**: Middleware `SetAssetUrl` untuk serve asset via IP (HTTP) atau domain (HTTPS)
- **TrustProxies middleware**: Agar Laravel mengenali IP asli di belakang nginx proxy
- **Dynamic ASSET_URL**: Middleware `SetAssetUrl` set `app.asset_url` berdasarkan host request (IP vs domain)

### Fase 10 — Dark/Light Theme Toggle (T-32):
- **Token remap runtime**: `html[data-theme="light"]` membalik `--color-ink-*` (950→#F8FAFC … 50→#1E293B) + `@custom-variant dark` di `app.css` → utility `dark:` berbasis class, seluruh UI re-theme tanpa menyentuh blade modul
- **theme.js**: komponen Alpine `themeManager` (dark/light/auto) — localStorage instan + sinkron DB via `POST /api/user/theme` (hanya staf login), mode `auto` ikuti `prefers-color-scheme` live, fallback zona (backoffice dark / marketplace light)
- **FOUC prevention**: bootstrap inline <script> di `<head>` kedua layout (pref server > localStorage > default zona, set `data-theme` + `.dark` sebelum paint)
- **Toggle button**: backoffice header + marketplace nav — sun/moon/monitor SVG, hint & aria-label Indonesia, area sentuh ≥44px
- **Surface fixes layout**: backoffice (sidebar/header/modal) & marketplace (navbar/footer) pasang pasangan `light/dark:` (mis. `text-ink-50 dark:text-white`, `border-black/10 dark:border-white/5`); token `ink-200/300` ditambahkan ke @theme; glass/input/scrollbar theme-aware di `prism-tokens.css`
EOF
### Fase 10 — Customer Create POS Sync CRM (T-36):
- **PelangganService** (`app/Modules/Crm/Services/PelangganService.php`): single source of truth pembuatan pelanggan — rules terpusat (nama, telepon unique, email unique), tier default terendah, is_reseller=false, terima `tanggal_lahir` + override `tier_membership_id`, load tierMembership
- **Pemakaian**: `POST /api/crm/pelanggan` (CRM-06) + Livewire POS (`PosKasir::simpanPelangganBaru`) + Livewire CRM (`CrmDashboard::simpanPelangganBaruCrm`) — tiga duplikasi difold jadi satu servis; respons/status kode tidak berubah
- **Pelanggan baru langsung tersedia** di pencarian POS & Servis (query DB langsung, tanpa reload)

### Fase 10 — Birthday Field + Tahan Fix (T-37):
- **`tanggal_lahir`** (nullable date) di `pelanggan` table (migration `2026_09_20_000037`), Fillable + cast `date` di model, input `<input type="date">` di form CRM + modal POS (ADR 0012)
- **Birthday targeting kampanye**: `BroadcastService::resolveTarget` — segment `birthday_month` (1-12) + `birthday_day` (1-31) → `whereMonth`/`whereDay` di `tanggal_lahir`; perbaikan bug: filter `where('is_active', true)` pada pelanggan DIHAPUS (kolom tidak ada → "Column not found" selalu)
- **Tahan fix**: shortcut F6 (`@keydown.window.f6` → `tahanTransaksi`) + panel "Transaksi Ditahan" auto-terbuka setelah tahan (`showDitahanPanel = true`)

### Fase 10 — PWA Gap Fix (T-31):
- **Dead SW registration dihapus**: `resources/js/app.js` registrasi manual `navigator.serviceWorker.register('/sw.js')` (404 — SW asli di `/build/sw.js`) dihapus. Registrasi kini SATU jalur: `import { registerSW } from 'virtual:pwa-register'` (VitePWA generateSW) + `injectRegister: 'script-defer'` (file `registerSW.js` tetap di-generate sebagai artifact, tanpa script-tag injection karena Laravel tidak punya HTML build-time). Update-notification custom dihapus (registerType autoUpdate sudah handle), online/offline toast dipertahankan
- **Offline fallback ter-wire**: `workbox.navigateFallback: '/offline.html'` + `navigateFallbackDenylist: [/^\/api\//, /^\/build\//]` + `additionalManifestEntries: [{ url: '/offline.html', revision: null }]` → `public/offline.html` ter-precache (build: precache 10 entries, termuat `/offline.html`)
- **Cache strategy sesuai AC**: runtimeCaching baru `assets-cache` StaleWhileRevalidate untuk `/\.(?:css|js|mjs)$/` (30d); API tetap NetworkFirst (5min, timeout 10s); fonts Google CacheFirst 1yr; images CacheFirst 30d
- **Build diverifikasi** (`npm run build`): `public/build/sw.js` mengandung navigateFallback `/offline.html` + denylist api/build + StaleWhileRevalidate + NetworkFirst; `public/build/registerSW.js` ada; manifest (public/manifest.json + build/manifest.webmanifest) lengkap — name, short_name, icons 192/512, theme_color `#5B4FE9`, background_color, display standalone
- **Known limitation (belum di scope)**: SW scope = `/build/` (base laravel-vite-plugin). Untuk kontrol penuh `/app/*`, perlu header `Service-Worker-Allowed: /` di nginx untuk `/build/sw.js` lalu set `scope: '/'` di VitePWA config

## 2026-09-20 (Gap Verifikasi WMS/Marketplace)

### Gap Verifikasi WMS/Marketplace — T-10, T-11, T-12, T-13, T-14
- **[T-10] Selesaikan alur supplier+PO lengkap**
  - **WmsController**: `updateSupplier` (PUT), `destroySupplier` (DELETE) — WMS-09 CRUD lengkap. `storeSupplier` (POST) sudah ada. `supplier` (GET) index sudah ada.
  - **PurchaseOrderService::terimaBarang**: sekarang `postJurnal=false` di `tambahStokPembelian` (hindari dobel jurnal), jurnal agregat diposting di service: **Debit Persediaan 130-01 / Kredit Utang 210-01 (kredit) ATAU Kas 110-01 (tunai)** — balance. PO kredit → **Otomatis buat record `Utang` (modul Akunting)** dengan `referensi_tipe=PurchaseOrder`, `kreditor_nama=supplier.nama`, `jumlah=totalHPP`, `jatuh_tempo=po.jatuh_tempo`. PO tunai → tidak buat Utang.
  - **PurchaseOrderService::bayarPO**: selain jurnal Utang debit/Kas kredit, **update record `Utang` (jumlah_dibayar, status → sebagian/lunas)**. Model `Utang` sudah ada (`app/Modules/Akunting/Models/Utang.php` + migration `2026_09_16_000026`), kolom: `po_id` via `referensi_id`, `pelanggan/supplier` via `kreditor_nama`, `jumlah`, `jatuh_tempo`, `sisa` (accessor), `status`.
  - Endpoint `WMS-11` (terima barang) akan didaftarkan pusat; method controller `updatePoStatus(action=diterima)` siap.

- **[T-11] Marketplace filter kompatibilitas HP dari `kompatibilitas_hp[]` JSON**
  - `ShopPage.php`: tambah computed property `hpMerkList` (ekstrak merk unik dari JSON produk) + `hpModelList` (ekstrak model, filtered by merk jika dipilih). `getProductsProperty()`: filter via `JSON_CONTAINS(kompatibilitas_hp, ['merk'=>...])` / `['model'=>...]` — **prioritaskan data terstruktur**; legacy `brand_kompatibel`/`model_kompatibel` tetap dipertahankan (tidak dihapus).
  - `shop-catalog.blade.php`: tambah 2 dropdown "Merk HP" & "Model HP" di filter bar (6 kolom grid), `wire:model.live="filterHpMerk"` + `filterHpModel`.

- **[T-12] Rak CRUD + field rak_id di form stok/transfer/opname**
  - Migration `2026_09_20_000040_add_wms_rak_opname_lock`: `stok_transfer_item.rak_id`, `stok_opname.rak_id`, `stok_opname_item.rak_id`, COA `520-08 Selisih Stok (Opname)`.
  - **WmsController**: `indexRak` (GET scope cabang), `storeRak`, `updateRak`, `destroyRak` — CRUD lengkap, guard relasi (stok/transfer/opname terkait → tidak bisa hapus).
  - **WmsDashboard (Livewire)**: `tambahStokForm.rak_id` → dikirim ke `ProdukService::tambahStokPembelian(rakId)` → `firstOrCreate` dengan `rak_id`. Transfer form: item row ada dropdown `rak_id` (filter gudang tujuan). Opname form: `opnameRakId` (opsional scope sesi per rak).
  - Blade `wms-dashboard`: select Rak di modal Tambah Stok, Transfer item row, Opname modal.

- **[T-13] Transfer pending → stok asal terkunci (guard oversell)**
  - `StokTransfer::pendingLockedByGudang($gudangId, $excludeId?)` & `pendingLockedFor(StokItem, $excludeId?)`: hitung qty draft transfer per produk/varian di gudang asal.
  - `StokDeductionService::kurangi`: **guard cek pending locked** — throw `Exception` "stok tidak mencukupi karena X unit terkunci transfer pending" bila `stok->jumlah - locked < qty`. POS, Servis, Marketplace order semua lewat service ini → terproteksi.
  - `WmsController::stok` (API WMS-01): tambah `stok_dikunci` virtual attribute per item (qty draft pending).
  - `WmsController::kirimTransfer`: guard lock exclude current transfer (allow kirim own draft).
  - Pertahankan `DB::transaction + lockForUpdate` existing.

- **[T-14] Opname scope rak + jurnal penyesuaian balance**
  - `storeOpname`: optional `rak_id` (scope sesi per rak). `inputOpnameItems`: per-item `rak_id` (scope per item), `stok_sistem` dibaca dari `StokItem` per rak.
  - `StokOpname` model: relasi `rak()` + `items()` sudah terupdate.
  - `approveOpname` (WmsController): selisih ≠ 0 → **buat jurnal balance pakai `JurnalService`** (service yang sudah ada). Pola: `selisih>0` (fisik>sistem) → **Debit 130-01 Persediaan / Kredit 520-08 Selisih Stok**; `selisih<0` (fisik<sistem) → **Debit 520-08 / Kredit 130-01**. `StokLog` + `StockMutationLog` tetap tercatat per item. COA `520-08` di-create via migration.

- **Tests**: 38 passed (187 assertions) — termasuk `test_po_kredit_diterima_dan_dibayar`, `test_tambah_produk_dengan_stok_awal_membuat_jurnal_pembelian`, `test_halaman_marketplace_render`.

### ROUTES DIBUTUHKAN (scope luar fixer, catat di sini untuk owner):
- `Route::put('/wms/supplier/{id}', [WmsController::class, 'updateSupplier'])->middleware('permission:wms.update');`
- `Route::delete('/wms/supplier/{id}', [WmsController::class, 'destroySupplier'])->middleware('permission:wms.delete');`
- `Route::get('/wms/rak', [WmsController::class, 'indexRak'])->middleware('permission:wms.view');`
- `Route::post('/wms/rak', [WmsController::class, 'storeRak'])->middleware('permission:wms.create');`
- `Route::put('/wms/rak/{id}', [WmsController::class, 'updateRak'])->middleware('permission:wms.update');`
- `Route::delete('/wms/rak/{id}', [WmsController::class, 'destroyRak'])->middleware('permission:wms.delete');`
- `Route::put('/wms/transfer/{id}/kirim', ...)` — sudah ada (WMS-03)
- `Route::put('/wms/transfer/{id}/terima', ...)` — sudah ada (WMS-04)
- `Route::put('/wms/po/{id}/status', ...)` — sudah ada (WMS-10/11)
- `Route::post('/wms/po/{id}/bayar', ...)` — sudah ada (WMS-12)

### Gap Verifikasi CRM/Reseller/Seeder — T-01, T-22, T-23, T-36
- **[T-01] TransaksiDemoSeeder: lengkap semua jenis transaksi + jurnal balance**
  - Tambah PO Supplier kredit & tunai (`PurchaseOrder`, `PurchaseOrderItem`) + `PembayaranSupplier` parsial untuk kredit + jurnal yang balance:
    - PO kredit: 130-01 debit / 210-01 kredit → bayar parsial: 210-01 debit / 110-01 kredit
    - PO tunai: 130-01 debit / 110-01 kredit
  - Servis `onSelesai` jurnal otomatis via `ServisService::updateStatus` (state machine transitions): 3 jurnal untuk 3 tiket 'selesai' (Kas 110-01 / Pendapatan Jasa 420-01).
  - Transfer gudang: existing (tanpa jurnal, hanya stok log).
  - Stock Opname: selisih -1 → jurnal **520-07 (Selisih Kas) debit / 130-01 (Persediaan) kredit** @ HPP produk.
  - Docblock updated: "semua jenis transaksi + jurnal".
  - Verified: **ALL JURNALS BALANCED ✓**, idempotent (`sudahAdaDemo` + `firstOrCreate` guards).

- **[T-22] Pengaturan "Strategi Loyalitas" di SettingsRbac (halaman RBAC)**
  - Tab baru `loyalitas` di `settings-rbac.blade.php` + state di `SettingsRbac.php` (`loyalitasForm`, `skemaKomisiForm`, `mount()`, `simpanLoyalitas()`).
  - Form: rasio earn poin (% transaksi), rasio redeem (1 poin = Rp X), diskon % per tier (Silver/Gold/Platinum), skema komisi default (persen/nominal per kategori).
  - GET `/api/crm/config`: **baca nilai TERSIMPAN dari tabel `konfigurasi`**, fallback `defaults()` bila NULL (tidak hardcode).
  - PUT `/api/crm/config`: simpan ke `konfigurasi` + update `TierMembership.diskon_persen` + `SkemaKomisi.nilai`.
  - Non-retroaktif: komentar di blade + controller.
  - Fix: `KonfigurasiService::set()` ganti `updateOrInsert` dengan `COALESCE` subquery (MySQL error) → dua step `update`/`insert`.

- **[T-23] Eksekusi broadcast terjadwal via scheduler**
  - Command `crm:broadcast-terjadwal` (`app/Modules/Crm/Console/Commands/EksekusiBroadcastTerjadwal.php`): ambil `KampanyeBroadcast` status `terjadwal` & `dijadwalkan_at <= now()` → `BroadcastService::kirimSekarang()` (channel WA/Email/InApp, personalisasi `{nama}`/`{tier}`) → update status + log per penerima (`NotifikasiKeluar.kampanye_broadcast_id`).
  - Daftar di `routes/console.php` + `bootstrap/app.php` schedule tiap menit (`->everyMinute()`).
  - Queue-only: notifikasi lewat `NotificationService::kirim` → `KirimNotifikasiJob` (DB queue + Supervisor), **tidak sync di request**.

- **[T-36] ResellerController::daftarReseller pakai PelangganService::create**
  - Ganti `Pelanggan::create` langsung → `PelangganService::create()` (single source of truth).
  - Validasi terpusat: `nama`, `telepon` unique, `email` unique, `tanggal_lahir`, `tier_membership_id` optional, `is_reseller=true` tetap dipertahankan.
  - Custom skema komisi per kategori tetap disimpan ke `skema_komisi_reseller`.


### Fase 11 — T-44 & T-43 (Database Produk Komprehensif + Batch Import Excel)
- **[T-44] Database Produk Komprehensif (Request #17)** — Skema produk diperluas mengakomodasi SID Retail & kebutuhan bisnis:
  - Migration `2026_09_21_000043_create_brand_kualitas_tipe_hp_tables` (Ran): tabel `brands` (12 seeded), `kualitas_produk` (5: Original, Grade A/B, Refurbished, Compatible), `tipe_hp` (31 seeded Apple/Samsung/Xiaomi/Oppo/Vivo/Realme/Infinix/Tecno/Google/Huawei/Universal), pivot `kompatibilitas_produk_tipe_hp` (M2M terstruktur). `produk` + `brand_id`/`kualitas_id` nullable FK. `pelanggan.tipe_konsumen` (retail|reseller|agen, default retail) — backfill dari `is_reseller`. `harga_tier`: kolom baru `sku_variant_id`, `tipe_konsumen`, `persen_diskon`, `nominal_tetap`; unique `(produk_id, tier_membership_id, tipe_konsumen)`; backfill `tipe_konsumen` dari legacy `is_reseller`; seed 1 baris per produk × tipe_konsumen (nominal = `harga_jual_retail`).
  - Models: `Brand`, `KualitasProduk`, `TipeHp` (M2M `produk.tipeHps()`), `Produk` relasi baru, `HargaTier` (baru per tipe_konsumen + tier). Backward compatible: kolom legacy `harga`/`is_reseller` dipertahankan.
  - `PelangganService::resolveHarga(produk, pelanggan)` — SATU sumber kebenaran harga (prioritas: HargaTier eksak tier+tipe > legacy reseller > legacy tier override > dison tier CRM > HargaTier tipe-default > fallback retail). Terverifikasi tinker: reseller 880k vs retail 1.1M (20% diskon).
  - Form Master Produk (WmsDashboard): field brand, kualitas, tipe HP (multiple), harga tier per tipe_konsumen (retail/reseller/agen) — validasi barcode unique, satuan wajib, minimal 1 harga tier.

- **[T-43] Batch Upload Produk via Excel (Request #16)** — Template → Preview (dry-run) → Commit via queue:
  - Migration `2026_09_21_000044_create_import_log_table` (Ran): tabel `import_log` (detail JSON, status proses/sukses/gagal).
  - Template export `ImportProdukTemplateExport` (Maatwebsite/Excel): sheet 1 = header + 2 contoh baris (LCD iPhone 13 Original, Baterai Samsung A54 Grade A); sheet 2 = petunjuk (12 poin, terminologi Ute Parts: stok awal = inisialisasi modal stok awal jurnal 130-01/310-01, stok masuk harian tetap lewat PO).
  - Service `ImportProdukService`: parse CSV (fgetcsv, TANPA PhpSpreadsheet, RAM 1GB) + xlsx; validasi per baris (SKU/barcode unique, satuan/gudang/rak FK, harga numeric ≥ 0, stok_awal > 0 ⇒ gudang_id wajib); preview 5 baris + error_rows detail; commit chunk 100 baris/transaction; brand/kualitas/tipe_hp firstOrCreate natural key; produk+sku_variant+harga_tier(3 tipe)+stok_items+StokLog+StockMutationLog(sumber='import:excel'); jurnal agregat stok awal Debit 130-01 / Kredit 310-01 balance, no_jurnal deterministik per import_log (idempoten); notifikasi_keluar via queue (inapp) + detail per baris di import_log.
  - Job `ImportProdukExcelJob` (ShouldQueue, DB queue, chunk 100): handle commit + update import_log + notifikasi + cleanup file tmp.
  - API: GET `/wms/produk/import/template`, POST `/wms/produk/import/preview`, POST `/wms/produk/import/commit`, GET `/wms/produk/import/log/{id}` — middleware `permission:wms.create|view`.
  - Livewire `WmsDashboard`: modal Import Excel (upload → preview → commit), tombol "📥 Import Excel" di tab Master Produk.
  - Verifikasi: migrations DONE; models seeded; `resolveHarga` PASS; `php -l` semua file clean; `WmsImportVerifyTest` menyentuh resolveHarga, preview (10 valid/4 invalid), commit (produk+sku+harga_tier×3+stok+SML+jurnal balance+notifikasi), idempoten (run ulang 0 sukses/14 gagal, tidak dobel).


### Fase 11 — T-42 (fase 2: mapping SID → Ute Parts) + T-44 & T-43
- **Migration** `2026_09_21_000046_create_sid_import_map_table.php` — tabel `sid_import_map` (kode_sumber, tabel_sumber, entity_type, entity_id, unique mapping SID→Ute Parts).
- **Command** `sid:migrate-ute-parts` (with `--dry-run` flag per PRD §4.11) — migrasi lengkap dari 8 tabel `sid_retail_raw_*` ke skema Ute Parts final:
  - **Referensi (1)**: `setup_perusahaan` → `gudang` (PUSAT).
  - **Master Barang (6.362 produk)**: `sid_retail_raw_barang` (filter `jenis=BARANG`, 6.362 of 6.433 raw) → `produk` + `sku_variants` (1:1) + `harga_tier` (retail/reseller/agen) menggunakan field SID: `harga_toko` (retail), `harga_member/harga_partai/harga_karyawan` (reseller), `harga_toko2/harga_partai2/harga_cabang` (agen). Brand dari `merk` (20 brands), kualitas auto-tier harga (5: Original/Grade A/B/Refurbished/Compatible), satuan dari `satuan`, kategori dari `golongan>subgolongan1`, barcode dari `kode_barcode`. Tipe HP pivot (31 seeded) dikosongkan (disiapkan utk fase 2 marketplace).
  - **Master Pelanggan (291)**: `sid_retail_raw_pelanggan` (284) + `member` (4, merge by telepon) → `pelanggan` dengan `tier_membership` (GROUP 1-4), `tipe_konsumen` (reseller jika `member=Y` + `plafon>0`), piutang awal dari `saldo_piutang` (SID raw data all cash → 0 piutang).
  - **Master Supplier (12)**: `sid_retail_raw_supplier` (10) → `supplier` + utang awal dari `hutang/saldo_hutang` (SID raw all cash → 0 utang).
  - **Transaksi Penjualan (6.262 faktur unik, 9.497 items termap)**: `sid_retail_raw_itempenjualan` group by base faktur (strip `|line`) → `transaksi` + `transaksi_item` (lookup `sid_import_map` barang). ~7% items di-skip (barang JASA/servis atau kode tidak ter-map).
  - **Stok Log (17.176)**: `sid_retail_raw_arus_stok` (19.286) → `stok_items` + `stok_log` + `stock_mutation_log` (sumber `sid_migration`), lookup gudang via `lokasi_stok` map, referensi transaksi via `no_transaksi` di `sid_import_map`. ~2k di-skip (delta=0 atau barang tidak termap).
  - **Servis (332)**: `sid_retail_raw_servis` → `tiket_servis` (status map: SEDANG DI SERVIS→dalam_pengerjaan, SUDAH DI AMBIL→diambil, DIBATALKAN→dibatalkan, MENUNGGU APPROVAL→menunggu_approval) + `teknisi` seed ke `users`.
- **Verifikasi aktual (db_staging)**: Produk 6.368 (3.235 existing + 3.133 SID), SkuVariant 6.368, HargaTier 19.104 (3×), Pelanggan 291 (284 SID + 4 member + 3 existing), Supplier 12 (10 SID + 2 existing), Transaksi 18.786 (12.524 existing + 6.262 SID), TransaksiItem 28.491 (18.994 existing + 9.497 SID), StokItem 3.044, StokLog 120.167 (68.712 existing + 51.455 SID re-run with refs), TiketServis 342 (10 existing + 332 SID), Piutang 0, Utang 0, SidImportMap 13.251 entries. Idempoten: re-run → 0 insert baru (guard unique `kode_sumber+tabel_sumber`). Semua test suite PASS (kecuali `WmsImportVerifyTest` temporary test memori).

