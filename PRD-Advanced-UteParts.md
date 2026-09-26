# PRD-Advanced-UteParts — Optimasi ERP Menuju Standar Industri Dunia

> **Status**: DRAFT v1.0 — menunggu review owner
> **Tanggal**: 2026-09-22
> **Basis**: Gap analysis vs Odoo 17 / ERPNext / SAP B1 / Dynamics 365 (konteks: retail sparepart HP + servis + reseller + marketplace, multi-cabang, server 1GB RAM, tim kecil)
> **Sumber analisis**: audit penuh 11 modul (66 services, 47 models, 24 Livewire) + matriks gap domain ERP + review arsitektur

---

## 0. DAFTAR BUG FIX — BACKLOG AKTIF

> Alur kerja: centang hanya setelah **verified** → item lama yang sudah `[x]` dihapus dari daftar ini.
> Urutan: kerjakan item pertama yang belum dicentang → review → konfirmasi owner → lanjut item berikutnya.
>
> **Aturan subagent (WAJIB)**: semua subagent memakai **model yang sama dengan orkestrator saat ini** —
> `opencode/mimo-v2.6-flash-free` (dicek ulang tiap sesi via `opencode models`; ikuti bila orkestrator berubah).
> **Fallback otomatis** bila error/limit kuota: ganti ke model OpenCode gratis lain, urutan:
> `opencode/big-pickle` → `opencode/space-bunny-free` → `opencode/muse-spark-1.3-contributor-free`
> → `opencode/nemotron-3.5-lightning-free` → `opencode/ling-3.0-flash-fin-free`.

- [x] **B-01 — Dashboard `/app/dashboard`** ✅ — verified 7/7 test DashboardSinkronisasiTest + 9/9 SemuaHalamanTest + 4/4 TreasuryWidgetTest, pint clean (2026-09-25): STATUS_OMZET selesai+lunas di 4 query omzet; piutang widget scoped cabang + migrasi backfill `cabang_id` NULL idempotent + seeder fix; alias `total_sisa` (fix Total Piutang selalu Rp 0 karena accessor override); `render()` berbasis `hasRole()` (super-admin kini dapat treasury, multi-role aman); `wire:poll.60s` (realtime 60 dtk) + guard redeclare `colorHsl`; stok kritis selaras ReorderService; scoping transaksi terbaru & omzet shift; label "semua cabang" utk komisi/jumlah trx; `@can('laporan.cabang')` 7 link drill-down; PO pending + status `usulan`; chart servis 1 GROUP BY + `diajukan_online`; `serverStatus` hanya super-admin.
- [x] **B-02 — POS `/app/pos`** ✅ — verified (2026-09-25): root cause = `addToCart` cek stok varian-eksak padahal grid kirim variant-null (0/216 baris cocok → hard-block) + kriteria gauge ≠ add-to-cart + `take(16)`. Fix: `hitungStokTersedia()` satu kriteria (agregat produk+gudang utk klik kartu), **produk stok kosong TIDAK BISA DIPILIH — hard-block (kebijakan owner final, backorder dibatalkan)**: `addToCart()` menolak dgn pesan Indonesia "Stok {nama} habis di gudang ini — pilih gudang lain…" (atau "Pilih gudang terlebih dahulu…" bila gudang belum ada), `updateQty()` dibatasi stok riil yang dihitung ulang dari DB & `stok_max <= 0` membuang item, `STOK_TANPA_GUDANG` 999→0 (tidak ada bypass "gudang belum dipilih"), `validasiStokKeranjang()` menolak stok 0/kurang sebelum transaksi dibuat, `processTransaction()` TIDAK lagi mengirim `izinkanNegatif: true` ke `StokDeductionService` (default `false` = tolak stok kurang), kartu katalog stok 0 tampil terkunci (`aria-disabled="true"` + `title` Indonesia + ikon kunci) bukan hanya dicek server, banner backorder di keranjang dihapus. Tetap berlaku: delegasi potong stok ke `StokDeductionService` (lockForUpdate + StockMutationLog → sinkron channel), gudang discoping & divalidasi cabang (anti-tamper), load-more katalog, RBAC route `permission:pos.view|pos.view-own`, `StokTab` scoping cabang, `ChannelSyncService` fallback `StokItem`, seeder idempoten. Test 13/13 PosStokSinkronTest + 9/9 SemuaHalamanTest + 17/17 SerialNumberTest + 4/4 StockMutationUserIdTest + 12/12 StockMutationActorTest, pint clean. *Catatan (di luar cakupan, perlu keputusan owner):* `PosController` API POS-01 masih mengirim `izinkanNegatif: true` → endpoint API masih bisa membuat stok negatif.
- [x] **B-03 — WMS `/app/wms`** ✅ (kode) — diagnosis: hipotesis "master data kosong → POS gagal" **dibantah** (live: 6 produk aktif tampil, 0 orphan); inkonsistensi nyata = inventori 216 baris vs 6 master karena seeder varian non-idempoten (SKU random ×36 run) + kehilangan katalog SID. Fix kode: `StokDeductionService` satu kriteria resolusi baris (varian-null → baris kanonik), `StokTab` scoping cabang, seeder idempotent, sinkronisasi POS/Servis/Marketplace/Channel. **Fase B (data live) menunggu keputusan owner**: dedup 216 varian, re-import katalog SID (`sid:import-raw`), backfill `StockMutationLog`.
- [x] **B-04 — Import master produk** ✅ — verified (2026-09-25): 2 root cause di `ImportProdukTemplateExport` — (1) class hanya `implements WithMultipleSheets` tanpa marker `Export` → `Excel::download()` TypeError (route di `routes/api.php` kena `shouldRenderJsonWhen` → exception jadi JSON = "baris data" yang tampil); (2) anonymous sheet `collection()` tanpa return type `: Enumerable` → E_COMPILE_ERROR tersembunyi. Fix: `implements Export, WithMultipleSheets` + return type `: Enumerable`; controller/route/blade tidak diubah. Test 2/2 WmsImportTemplateDownloadTest (content-type xlsx, magic bytes `PK`, header = `templateColumns()`) + 1/1 WmsImportVerifyTest regresi, pint clean.
- [x] **B-05 — Cycle count `/app/wms/cycle-count`** ✅ — verified (2026-09-25): root cause = `CycleCountPage::render()` (`:227-229`) mengakses `$this->schedulesProperty` (nama literal, tidak ada) padahal getter legacy `getSchedulesProperty()`/`getTasksProperty()`/`getRaksProperty()` diekspos Livewire v4 sebagai magic `$this->schedules`/`$this->tasks`/`$this->raks` → `Livewire\Exceptions\PropertyNotFoundException` (`Component.php:127`). View sudah sinkron; hanya akses di `render()` yang salah. Fix wiring saja (logika F3-7 tak disentuh) + baseline phpstan 3 entri disesuaikan. Test baru `CycleCountPageTest` 4/4 (2xx route, viewHas schedules/tasks/raks, create jadwal, form count preview); bukti regresi: fix di-stash → 4 failed di `CycleCountPage.php:227`. pint clean. Bug serupa di `SupplierScoreTable` & `SessionManagementPage` di luar scope.
- [x] **B-06 — Servis `/app/servis` terima unit** ✅ — verified (2026-09-25): (a) kunci gadget kini tampil utk SEMUA role ber-`servis.view` (blokir "hanya teknisi-ditugaskan" di `ServisController::show()` dihapus) + kartu "Kunci Gadget" ter-mask `••••••••` dgn tombol Tampilkan/Sembunyikan (plaintext tak ada di DOM sebelum interaksi); (b) alur checklist kondisi fisik didokumentasikan (6 chip Layar/Body/Baterai/Kamera/Speaker/Watermark → `toggleKondisiFisik` → JSON `kondisi_fisik`, opsional, tak menyentuh state machine) + label/hint diperbaiki + empty-state di modal detail; (c) foto terima unit jadi OPSIONAL (min-2 dihapus di Livewire `simpanTerima` + API `store` `nullable|array`). Test 8/8 ServisTerimaUnitTest + 6/6 ServisStateMachineTest (regresi) + 9/9 SemuaHalamanTest, pint clean. **Keputusan owner B-14 selesai (2026-09-26)**: role `kasir` + `marketing` diberi `servis.view` (read-only) di `RolesAndPermissionsSeeder`, guard server-side di `ServisBoard` (`simpanTerima`/`updateStatus`/`simpanPekerjaan` butuh permission create/update/input), `finance` + `staff-gudang` tetap 403. Test 10/10 ServisViewRoleTest.
- [x] **B-07 — Servis terima unit (error SQL)** ✅ — verified (2026-09-25): root cause BUKAN "sort buffer terlalu kecil" murni, tapi **row yang di-sort terlalu lebar** — MySQL 8.0.46 (sort_buffer_size 256KB) mem-packing seluruh baris ke sort buffer, sedangkan `tiket_servis.foto_unit` menyimpan base64 foto (**431.406 byte/baris terukur** di `db_staging`) → 1 baris > buffer → `1038 Out of sort memory`; EXPLAIN live = `type: ALL` + `Using filesort`, tanpa index `created_at`. Fix aplikasi = **query 2 tahap** (ORDER BY + LIMIT hanya di kolom sempit `id`, lalu hydrate + `withCount('spareparts')` via `whereIn` tanpa ORDER BY, urutan `latest()` dikembalikan di PHP) di `ServisBoard::getGroupedTiketsProperty()` (query `limit 100` pada error) dan `ServisController::index()` [SERVICE-02]; plus migrasi idempotent index `tiket_servis(created_at)` + `tiket_servis(cabang_id, created_at)`. Bukti: query lama direproduksi 1038 (live & scratch DB), query baru jalan di live (`100 rows`); index saja **tidak cukup** (optimizer tetap pilih filesort krn kolom lebar → 1038 utk `limit 100` & filter `cabang+status`). Test 5/5 ServisListQueryTest (index ada, kanban render dgn foto lebar + spareparts_count + urutan, gardu regresi ORDER BY hanya kolom `id`, scoping cabang SERVICE-02, filter status/search) + 8/8 ServisTerimaUnitTest + 10/10 ExportLaporanTest + 9/9 SemuaHalamanTest, pint PASS 386 file. **Tertunda (keputusan owner):** `php artisan migrate` di `db_staging` (juga menjalankan migrasi backfill milik task lain); papan kanban **tidak scoped `cabang_id`** (pre-existing, sengaja tidak diubah); pola `order by created_at` serupa di modul lain (WMS/CRM/POS/Omnichannel) belum disentuh.
- [x] **B-08 — Servis (error blade)** ✅ — verified (2026-09-25): root cause = akses `$loop->index` di dalam blok `@for` (`servis-board.blade.php:249`) — directive `@for` **tidak** menyediakan variabel `$loop` → `$loop` null → "Attempt to read property 'index' on null", terpicu saat modal Terima Unit memilih `tipe_kunci = 'pola'`. Fix: indeks pola dihitung eksplisit `{{ $i - 1 }}` (indeks 0–8 tetap valid); grep `->index` di file tersebut kini nihil. State machine/token/jurnal/terima-unit tidak diubah. Test 9/9 ServisTerimaUnitTest (termasuk regresi buka modal + pilih pola) + 5/5 ServisListQueryTest, pint PASS 386 files.
- [x] **B-09 — Broadcast promo CRM** ✅ — verified (2026-09-25): **Alur** = buat campaign (API CRM-08 / UI CRM, `permission:crm.broadcast`) → simpan `KampanyeBroadcast` → kirim-now atau `terjadwal` (cron `crm:broadcast-terjadwal`) → resolve audiens (tier/reseller/belum belanja N hari/birthday) → personalisasi `{nama}`/`{tier}` → outbox `notifikasi_keluar` + queue `KirimNotifikasiJob` → WA/email terkirim. **Fix sinkronisasi**: (1) `kampanye_broadcast_id` masuk fillable + relasi dua arah (sebelumnya praktis selalu NULL → log campaign kosong, dashboard 0); (2) guard RBAC server-side di `CrmDashboard::kirimBroadcast()` + tombol disembunyikan tanpa permission (UI sebelumnya bypass RBAC API); (3) UI CRM & API legacy CRM-05 kini sama-sama membuat record `KampanyeBroadcast` via `BroadcastService` (sebelumnya bypass campaign, tanpa log/jadwal/channel); (4) schedule dobel terdaftar (≈7.100 invoke) → satu registrasi + `withoutOverlapping()`/`onOneServer()`; (5) typo kontrak `jadiwalkan_at` → `dijadwalkan_at` (+ alias backward-compat, validasi segmen/jadwal lampau/pasangan birthday); (6) WA tanpa kredensial & email tanpa tujuan → `gagal` + error Indonesia (bukan `terkirim` palsu; config stub `services.wa` ditambahkan, provider nyata belum dipilih); (7) filter audiens dipindah ke SQL + `chunkById(100)` (hemat RAM 1GB) + eager-load tier anti N+1; (8) `broadcastLog` dipaginasi 100 terbaru; (9) metrik dashboard broadcast改为 basis status lifecycle `kampanye_broadcast` (sebelumnya satuan campur campaign vs notifikasi) + label view disesuaikan. Test 8/8 BroadcastPromoTest + 9/9 SemuaHalamanTest, pint PASS 387 files. **Tertunda (keputusan owner)**: pilih provider WA (Fonnte/Wablas/WA Business); semantik final `terkirim` (queued vs accepted vs delivered) & rekap status campaign setelah job async; scope cabang (global vs `cabang_id`); queue worker Supervisor produksi (path config sistem salah → 53 job pending); `inapp` jadi inbox pelanggan atau audit-only.
- [x] **B-10 — Akunting** ✅ — audit 4 lapis + 9 lane perbaikan, verified (2026-09-25). **Audit (live `db_staging`):** 167 baris/71 jurnal, 0 tidak balance, 0 akun COA hilang, 9/9 transaksi punya jurnal, 0 piutang/utang mismatch — double-entry-nya benar, tapi kontrol bocor. **P0 diperbaiki:** (1) `PembayaranSubledgerService` (baru) — Livewire `bayarPiutang/Utang` yang tadinya **tak pernah post jurnal** kini satu transaksi (`lockForUpdate` + subledger + jurnal + `referensi_tipe/id` + idempotency key, 404 lintas cabang); controller ikut sama, exception tak lagi ditelan. (2) `/app/akunting` → `permission:akunting.view` + guard server-side di 6 method mutasi + seluruh query AR/AP/laporan/buku-besar scoped `cabang_id` (sebelumnya `bukuBesar` tanpa filter cabang sama sekali). (3) `jurnal_header` (baru) unique `(cabang_key, no_jurnal)` + counter `lockForUpdate` → `post()` idempoten atomic, signature 24 call site tetap kompatibel. (4) fail-closed: Servis/Payroll/Kas hanya finalisasi status setelah jurnal commit; 2 migrasi lama dibungkus `ActivityLogger::withoutLogging()`. (5) webhook Duitku: 1 `DB::transaction` + `lockForUpdate` + validasi nominal (toleransi Rp1) + idempotent. (6) `ute:rekonsiliasi` (baru, READ-ONLY) — 9 kategori anomali + usulan, exit code untuk CI. **P1/P2 diperbaiki:** audit trail lengkap (LogsActivity utk `AkunCOA`/`StokLog`/`ApprovalRequest`/`ServisStatusLog`/`KampanyeBroadcast`, field sensitif dikecualikan berlapis; event `journal.posted` 1× per posting) + `audit_logs.cabang_id`; `StockMutationLog.user_id` terisi di 12 call site (NULL = proses sistem, dikunci test); `DepresiasiAsetJob` terjadwal bulanan + notifikasi gagal; **view AsetRegister + route `/app/akunting/aset` dibuat** (F3-3 sebelumnya tak bisa diakses) + form harga difix (input `"1.000.000"` ditolak rule `numeric`); Neraca berbasis **saldo kumulatif** (`neracaSaldo()` = aset = kewajiban + ekuitas, tanpa hardcode, dipakai dashboard+export+API); validasi COA disatukan ke `ValidasiBarisJurnal` (paritas pesan dashboard↔API dikunci test); import produk ditolak lintas cabang + jurnal per-chunk dalam transaksi + savepoint; `paid_at`/`payment_reference`/`payment_url` masuk fillable. **Test:** 20 file test baru, semua hijau; `IntegrasiModulTest` yang tadinya gagal kini 2/2; pint PASS 409 file. **Migrasi menunggu owner:** `2026_09_25_000100` (backfill piutang/utang), `..._020000` (index `tiket_servis`), `..._030000` (`jurnal_header`), `..._030100` (`stock_mutation_log.user_id`), `..._040000` (`audit_logs.cabang_id`). **Tertunda (keputusan owner):** jalankan `ute:rekonsiliasi` lalu rekonsiliasi data live (2 PO `diterima` tanpa jurnal, payroll `2026-09` `selesai` Rp54,5jt tanpa jurnal, 45 jurnal menumpuk di opname, 3 `Utang` orphan, `piutang`/`utang` `cabang_id` NULL); PPN marketplace belum dipisah ke 220-01 seperti POS; Supervisor worker mati (path config salah → 53 job `pending`); provider WA (Fonnte/Wablas/WA Business); `StockOpname`/`StokTransfer` masih fallback `auth()->id() ?? 1`; `AuditLog` lama (`AuditService`) belum disatukan dengan Spatie; `AbsensiShiftTest` gagal pre-existing (test pakai `Carbon::create` vs `now()`).
- [x] **B-11 — Jurnal manual** ✅ — verified (2026-09-25): form jurnal manual di `AkuntingDashboard` + `akunting-dashboard.blade.php` dirombak — baris entry kini punya **dropdown akun COA** (kode + nama, hanya akun aktif, difilter sesuai `saldo_normal` per sisi debit/kredit; akun pendapatan/utang/ekuitas tidak bisa dipilih di sisi debit), **toggle sisi debit/kredit** (warna semantik amber/mint + `StatusPill`), nominal menerima format ribuan Indonesia lalu dinormalisasi, baris tambah/hapus (min. 2), dan **verifikasi otomatis real-time**: panel Total Debit / Total Kredit / Selisih + status (seimbang/kurang debit/kurang kredit) + error per baris (akun kosong/nonaktif/nominal 0/sisi salah) → tombol posting nonaktif sampai valid, dengan validasi identik di server (anti manipulasi state Livewire) + `ConfirmDialog` sebelum posting. Posting tetap lewat `JurnalService::post()` & `generateNoJurnal()` scoped cabang (tidak diubah). Ute Prism dipakai (`GlassCard`, `PrismButton`, `StatusPill`, `ConfirmDialog`, Toast); tanpa dependency baru. Test 4/4 JurnalManualTest (tak balance ditolak, balance tersimpan, pembatasan akun, dropdown akun aktif) + 4/4 JurnalServiceTest regresi, pint PASS 388 files.
- [x] **B-12 — Bug computed-property serupa B-05** ✅ — verified (2026-09-25): root cause = `SupplierScoreTable::render()` (`:65-66`) akses `$this->scoresProperty`/`$this->suppliersProperty` & `SessionManagementPage::render()` (`:72`) akses `$this->devicesProperty` — nama literal tidak ada; getter legacy `getScoresProperty()`/`getSuppliersProperty()`/`getDevicesProperty()` diekspos Livewire v4 sebagai magic `$this->scores`/`$this->suppliers`/`$this->devices` → `Livewire\Exceptions\PropertyNotFoundException`. Fix wiring saja (pola identik `CycleCountPage.php:227-231`) + baseline phpstan 3 entri `property.notFound` disesuaikan (`$scores`/`$suppliers`/`$devices`) → `phpstan analyse` 2 file = No errors. **Temuan baru:** view keduanya tidak pernah dibuat (`view()->exists()` false di seluruh repo, tanpa route & tanpa `<livewire:…>`, dead code F3-2/F3-4) → setelah fix render berhenti di `View […] not found`, jadi tidak ada view yang bisa disinkronkan. Test baru `ComputedPropertyFixTest` 4/4 passed (5 assertions): computed teresolusi + render tanpa `PropertyNotFoundException` dgn guard `assertViewHas` yg aktif ketika view dibuat nanti; bukti regresi: fix di-revert → **2 failed** (`Property [$scoresProperty] not found`, `Property [$devicesProperty] not found`). pint PASS. **Tindak lanjut terpisah:** buat 2 view + route (`@designer`/task baru).

---

## 1. RINGKASAN EKSEKUTIF

Ute Parts ERP saat ini **kompetitif untuk bisnis lokal** — 11 modul lengkap, double-entry accounting, multi-cabang WMS, kanban servis, omnichannel Shopee. Diferensiasi vs Odoo/ERPNext: ticketing servis + integrasi marketplace lebih fokus.

**Gap utama bersifat OPERASIONAL (bukan strategis):** pajak otomatis, reorder point, approval engine, 2FA, audit trail lengkap, drill-down reporting, serial tracking, GRN. **Tidak relevan** (jangan dibangun): multi-currency, manufacturing/MRP, HR payroll penuh — dikecualikan demi RAM & tim kecil.

---

## 2. PRINSIP EKSEKUSI

1. **Server 1GB RAM**: tiap fitur baru wajib cek memory footprint; laporan berat via queue + cache 15 menit; tanpa sync dispatch di request
2. **Semua query scope `cabang_id`** — tanpa kecuali
3. **Test wajib** per fitur baru (SQLite :memory:); jalankan `php artisan test --filter=XTest` per-file (suite penuh butuh RAM >1GB — di box ini OOM)
4. **Checklist checkbox** per task — centang HANYA setelah verified (aturan implement-plan)
5. **Idempotent** semua command/sedeeder baru
6. **Bahasa error/pesan**: Indonesia

---

## 3. MATRIKS GAP FINAL ( hasil sintesis dua audit )

### 3.1 Domain Gap — Prioritas Eksekusi

| # | Fitur | Domain | Level | Effort | Fase |
|---|-------|--------|-------|--------|------|
| G-01 | **Approval Engine** (rule-based, multi-level, matrix by amount) | Workflow | CRITICAL | L | F1 |
| G-02 | **Pajak otomatis** (PPN 11% default per-outlet, PPh 22 opsional, e-invoice data siap DJP) | Finance | CRITICAL | M | F1 |
| G-03 | **2FA TOTP** (wajib admin/superadmin/finance, opsional lainnya + backup codes) | Security | CRITICAL | M | F1 |
| G-04 | **Audit Trail lengkap** (activity log seluruh modul: create/update/delete, before/after JSON) | Workflow | CRITICAL | M | F1 |
| G-05 | **Reorder Point otomatis** (alert stok < minimum, usulan PO draft otomatis via queue) | Inventory | CRITICAL | M | F1 |
| G-06 | **Lead Pipeline CRM** (kanban lead → qualified → konversi jadi pelanggan, scoring sederhana) | Sales | CRITICAL | M | F2 |
| G-07 | **GRN (Goods Receipt Note)** validasi vs PO (qty, harga, selisih approval) | Procurement | CRITICAL | M | F2 |
| G-08 | **Serial Number tracking** (nomor_seri per produk sn=true, trace di transaksi/servis) | Inventory | CRITICAL | L | F2 |
| G-09 | **BI Drill-down + Custom Report Builder** (dashboard → transaksi → item; export Excel semua laporan) | BI | CRITICAL | L | F2 |
| G-10 | **Field-level security** (visibility kolom harga_beli/margin by role) | Security | HIGH | M | F3 |
| G-11 | **Supplier scoring** (准时 on-time %, quality return %, harga rata-rata; tampil di form PO) | Procurement | HIGH | S | F3 |
| G-12 | **Aset tetap** (daftar aset, depresiasi garis lurus bulanan, jurnal otomatis) | Finance | HIGH | M | F3 |
| G-13 | **Backup otomatis** (command `db:backup` + dump storage + notifikasi; cron harian) | Ops | HIGH | S | F1 |
| G-14 | **Supervisor + monitoring config** (queue worker, logrotate, healthcheck `/healthz`) | Ops | HIGH | S | F1 |
| G-15 | **Session management** (device list, force logout per device, timeout config) | Security | HIGH | S | F3 |
| G-16 | **Webhook terstandar** (outbound: transaksi selesai/stok berubah — signature HMAC, retry) | Integration | MEDIUM | M | F3 |
| G-17 | **Treasury sederhana** (proyeksi arus kas 30 hari: piutang jatuh tempo + utang + saldo kas) | Finance | MEDIUM | S | F3 |
| G-18 | **Cycle count otomatis** (jadwal opname per rak/kategori, count minor vs major) | Inventory | MEDIUM | S | F3 |

### 3.2 Dikecualikan (bukan konteks bisnis — JANGAN bangun)
- Multi-currency consolidation (IDR-only)
- MRP/Manufacturing BOM (retail + servis, bukan pabrik)
- HR absensi/KPI penuh (payroll + komisi teknisi ADA via F3-8; absensi/KPI defer sampai butuh)
- Inter-company elimination (single company)
- SOX compliance formal (log internal cukup)

### 3.3 Gap Struktural (refactor, jalan beriringan)

| # | Isu | Bukti | Aksi | Fase |
|---|-----|-------|------|------|
| S-01 | Fat Livewire: `WmsDashboard.php` 1.033 baris | path file | Split → `StokTab`, `TransferTab`, `OpnameTab`, `PoTab`, `ProdukTab` komponen fokus | F1 |
| S-02 | Test coverage tipis: hanya Pricing + ServisStateMachine + HargaFleksibel + Jurnal | `tests/` | Test per fitur baru (mandatory), tambah feature test integrasi modul | semua |
| S-03 | Tidak ada base pattern repo/DTO | app/ | Katalis: cukup service eksplisit — JANGAN tambah layer repository abstrak (YAGNI; services sudah oke) | — |
| S-04 | Pagination blade tidak konsisten | views | Standardisasi saat sentuh modul terkait | F2 |

> **Keputusan arsitektur**: TIDAK menambah repository layer / interface generik — services terpusat sudah memadai untuk ukuran tim; prioritas ke fitur bernilai bisnis. (Alasan: tim kecil, tambahan layer = biaya maintenance tanpa nilai).

---

## 4. FASE EKSEKUSI

### FASE 1–3 — SELESAI ✅ (entri `[x]` dihapus dari daftar)

> F1-1..F1-8, F2-1..F2-5, F3-1..F3-8c **semua selesai & verified** — checklist lama dihapus sesuai aturan owner.
> Riwayat pengerjaan: `CHANGELOG.md` + git log. Desain detail HR/Komisi/Payroll tetap di §4.1–§4.3.

### 4.1 HR SEDERHANA — DETAIL DESAIN (F3-8, revisi: diminta owner)

**Prinsip**: fokus payroll + komisi teknisi SAJA (bukan absensi/KPI dulu). Struktur tabel di-desain sejak awal agar mudah extend tanpa migrasi rombak.

**Tabel**:
- `karyawan` — user_id (FK nullable, 1:1 dgn `users`), nik, nama, jabatan (enum: teknisi/kasir/admin/other), cabang_id, tgl_masuk, gaji_pokok, status_aktif, rekening bank. **SOT karyawan = user bila linked** (nama/email dari user).
- `karyawan_komponen_gaji` — karyawan_id, tipe (enum: `tunjangan`/`potongan`/`bonus`), nama (mis. "Tunjangan Transport"), nominal_bulanan atau rumus, is_aktif. Fleksibel: nilai tetap atau persentase dari gaji pokok.
- `komisi_teknisi` (rule) — cabang_id nullable (global default), jabatan target `teknisi`, jenis (per_tiket / persen_nilai_servis), nominal atau persen, min status tiket `selesai`, is_aktif. **Komisi reseller (existing `KomisiService`) TIDAK disentuh** — ini terpisah utk karyawan teknisi.
- `payroll_periode` — periode (YYYY-MM), tanggal mulai/selesai, status (draft/diproses/selesai/dibayar), catatan. Idempotent per periode.
- `payroll_slip` — payroll_periode_id, karyawan_id, gaji_pokok, total_tunjangan, total_potongan, total_komisi, total_gaji, rincian JSON (snapshot komponen + detail komisi per tiket), jurnal_id FK.
- `payroll_komisi_detail` — slip_id, tiket_servis_id FK, teknisi_id, jenis, nominal (traceable per tiket servis!).

**Alur**:
1. Admin/finance buka periode payroll bulanan → sistem auto-hitung draft: gaji pokok + komponen + **komisi teknisi otomatis dari tiket servis status `selesai` dalam periode** (link teknisi via `tiket_servis.teknisi_id` — cek kolom existing; bila belum ada teknisi_id di tiket, tambah migration nullable FK).
2. Review rincian per karyawan (slide-over: breakdown komisi per tiket) → approve via **F1-1 Approval Engine** (threshold: total payroll periode > X).
3. Approve → post jurnal via `JurnalService` existing: debit Beban Gaji (520-01) + debit Beban Komisi (520-02) / kredit Hutang Gaji (210-02) — **cek COA existing, buat akun baru bila belum ada** (migrasi seeder COA idempotent).
4. Tandai `dibayar` → jurnal pembayaran (debit Hutang Gaji / kredit Kas) — reuses `master_kas`.
5. Slip PDF/thermal-friendly print.

**Kesiapan bertumbuh (scalable, tanpa rework)**: `karyawan_komponen_gaji` tipe enum extendable; `payroll_slip.rincian` JSON snapshot; `komisi_teknisi` rule configurable (bukan hardcoded).

### 4.2 ABSENSI, SHIFT & KPI KARYAWAN (F3-8b, revisi owner)

**Prinsip**: absensi + shift = sumber data KPI + payah disiplin; terhubung payroll (potongan/tunjangan via komponen §4.1) dan open/close kas kasir.

**Tabel**:
- `shift` — cabang_id, nama (mis. "Pagi", "Siang"), jam_mulai, jam_selesai, is_aktif. Bisa lintas hari (jam selesai < mulai = overnight).
- `shift_jadwal` (roster) — cabang_id, karyawan_id, shift_id, tanggal. Assignment kasir ↔ gudang kas default.
- `absensi_log` — karyawan_id, tanggal, jam_masuk nullable, jam_keluar nullable, shift_id nullable, status enum (`hadir/terlambat/absen/izin/cuti`), catatan, self_photo nullable (bisa tanpa foto MVP), lokasi nullable. Idempotent per karyawan+tanggal.
- `kpi_metric` — kode, nama, rumus (whitelist terdefinisi kode, BUKAN eval bebas), target, satuan, periode (bulanan), is_aktif.
- `kpi hasil` — karyawan_id, kpi_metric_id, periode, nilai aktual, persen_capaian. Dihitung via service dari data real (bukan input manual): contoh metric teknisi = tiket selesai/periode; kasir = total transaksi/shift + selisih kas; marketing = penjualan dari lead won.

**Alur**:
1. Kasir/karyawan clock-in/out di POS/backoffice (bisa dari halaman POS — tombol kecil) → absensi_log otomatis terhubung **sesi kas** (KasSesiState existing): open kas wajib clock-in aktif; tutup kas → sarankan clock-out; selisih kas sesi masuk KPI kasir.
2. Shift kasir menentukan sesi kas (satu shift = satu sesi kas); rotasi via roster mingguan (input admin: grid jadwal).
3. KPI dihitung job bulanan (queue) per periode → tampil dashboard karyawan & admin; persen capaian bisa jadi komponen insentif payroll §4.1 (komponen `bonus` rumus KPI).
4. Absen tanpa izin > X hari → potongan otomatis via komponen `potongan` (config threshold, opt-in per cabang).

**RBAC**: `kelola-hr` (admin+superadmin) utk roster/KPI semua; karyawan lihat absensi+KPI sendiri.

**Test**: AbsensiShiftTest (idempotent clock-in, overnight shift, gate open kas), KpiHitungTest (metric dari data real), RosterTest.

### 4.3 KOMISI/INSENTIF MARKETING INTERNAL & EKSTERNAL (F3-8c, revisi owner)

**Prinsip**: generalisasi `KomisiService` (reseller) → engine komisi multi-aktor (karyawan internal marketing + reseller/agen eksternal) berbasis rule configurable, tidak hardcoded.

**Tabel**:
- `komisi_skema` (generalisasi, extends/replaces skema reseller existing — MIGRASI DATA: skema komisi reseller existing dipertahankan sebagai seed rule baru, jangan putus alur lama sampai paritas teruji) — aktor_tipe enum (`karyawan`/`reseller`/`agen`), aktor_id nullable (null = rule umum tipe aktor), trigger_tipe enum (`penjualan`/`lead_won`/`tiket_servis`/`target_kpi`), nominal atau persen, min_amount, cabang_id nullable, is_aktif.
- `komisi_transaksi` — extends existing pattern (cek `KomisiService` + model `Komisi` existing — tambah kolom aktor_tipe/aktor_id bila belum, jaga kompatibilitas lama via default `reseller`).

**Alur**:
1. Transaksi POS selesai / lead won (§F2-1) / tiket servis selesai → job hitung komisi match semua rule aktif (karyawan marketing dari lead source, reseller dari pelanggan is_reseller, agen via referral code di pelanggan) → insert `komisi_transaksi` (idempotent per trigger+rule).
2. Komisi karyawan internal masuk payroll §4.1 (komponen komisi, bisa KPI-linked §4.2); komisi reseller/agen masuk halaman saldo komisi existing + payout (existing flow, extend aktor).
3. Rule builder UI sederhana (admin): tipe aktor, trigger, nominal/persen, periode aktif.

**Test**: KomisiMultiAktorTest (rule match multi aktor, idempotent, migrasi paritas reseller lama → baru).

**RBAC**: permission `kelola-payroll` (super-admin + finance); teknisi boleh lihat slip sendiri (pembatasan cabang + user_id).

**Test**: PayrollPeriodeTest (hitung komisi teknisi dari tiket selesai), PayrollJurnalTest (balance + posting idempotent per periode), PayrollPermissionTest (teknisi lihat slip sendiri saja).

**UI**: modul Backoffice baru "HR & Payroll" (desktop-first): daftar karyawan, form komponen gaji, rule komisi, halaman periode (wizard: hitung draft → review → approve → bayar), slip detail + print. Marketplace/Servis teknisi dashboard widget: komisi saya bulan ini.

---

## 5. SKEMA INTEGRASI & SINKRONISASI (WAJIB tiap fitur)

1. **Akunting**: setiap fitur yg menyentuh uang/stok wajib posting jurnal via `JurnalService::post()` existing — tidak ada jurnal manual baru; balance debit=kredit dijamin test
2. **Notifikasi**: semua approval/alert/reorder → `NotificationService` via queue (database driver) — dilarang sync
3. **RBAC**: permission baru masuk migration seeder (pola `atur-harga-fleksibel`); assign role default: super-admin all, admin-toko subset
4. **Audit**: model kritis baru wajib di-log activity (F1-4)
5. **Dashboard**: widget baru pakai pola `DashboardIndex` role-aware existing
6. **Frontend**: komponen Ute Prism (GlassCard, StatusPill, DataTable, Toast, ConfirmDialog) — tidak ada template Tailwind mentah; angka `tabular-nums`; format ribuan titik (ADR 0011)
7. **Mobile-first marketplace / desktop-first backoffice** (ADR 0010)

---

## 6. TEST PLAN PER FASE

| Fase | Test minimal |
|------|-------------|
| F1 | ApprovalRuleTest (matrix match), PpnTest (hitung+jurnal balance), TwoFactorTest (enforce role), ReorderTest (trigger usulan), BackupCommandTest |
| F2 | LeadConversionTest (won→pelanggan), GrnTest (selisih+approval+AP jurnal), SerialNumberTest (trace), DrillDownTest (akses data) |
| F3 | FieldSecurityTest (visibility per role), AsetDepresiasiTest (jurnal bulanan), WebhookTest (signature+retry) |

Semua test SQLite :memory:; regression: `PricingServiceTest`, `ServisStateMachineTest`, `HargaFleksibelTest`, `JurnalServiceTest` tetap hijau.

---

## 7. DEFINITION OF DONE PER FITUR

1. Kode + migration idempotent + seeder permission
2. UI Ute Prism (jika berhadapan user) — mobile+desktop
3. Test lulus + regression tetap hijau
4. `./vendor/bin/pint` clean
5. Jurnal terintegrasi (bila finansial) — balance teruji
6. Notifikasi via queue
7. CHANGELOG.md update
8. Checkbox PRD ini dicentang HANYA setelah verified

---

## 8. URUTAN EKSEKUSI & PARALELISASI

```
F1 (backend fixer: F1-1..F1-7 berurutan; F1-8 designer paralel setelah kontrak UI)
  └── F2 (fixer + designer paralel per fitur, scope file terpisah)
        └── F3
```

Estimasi effort: F1 = 5-7 hari, F2 = 7-10 hari, F3 = 5-7 hari (dengan subagent paralel).

---

*Review owner: centang/approve PRD ini → eksekusi otomatis mulai F1.*
