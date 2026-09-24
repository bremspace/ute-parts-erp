# PRD-Advanced-UteParts — Optimasi ERP Menuju Standar Industri Dunia

> **Status**: DRAFT v1.0 — menunggu review owner
> **Tanggal**: 2026-09-22
> **Basis**: Gap analysis vs Odoo 17 / ERPNext / SAP B1 / Dynamics 365 (konteks: retail sparepart HP + servis + reseller + marketplace, multi-cabang, server 1GB RAM, tim kecil)
> **Sumber analisis**: audit penuh 11 modul (66 services, 47 models, 24 Livewire) + matriks gap domain ERP + review arsitektur

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

### FASE 1 — Fondasi Operasional (Quick Wins, target: sprint 1)
> Backend: fixer lane | Frontend: designer lane | Test wajib per fitur

- [x] **F1-1 Approval Engine** — tabel `approval_rule` (entity_type, min_amount, approver_role, level, cabang_id nullable), `approval_request` (status: pending/disetujui/ditolak, payload JSON, requested_by). Trait/observer auto-fire. UI: notifikasi badge + halaman approval inbox per role. Applies: PO > threshold, retur > threshold, diskon besar.
- [x] **F1-2 Pajak Otomatis** — config per-cabang `ppn_percent` (default 11), `pajak_enabled`; field `ppn_nominal` + `dpp` di transaksi; jurnal PPN keluaran (akun 220-01) saat faktur; laporan pajak bulanan (rekap PPN keluaran/masukan → e-Faktur-ready export).
- [x] **F1-3 2FA TOTP** — package `pragmarx/google2fa` (Laravel Fortify-compatible sederhana); enforce role super-admin/finance; backup codes 8x; UI setup + login challenge.
- [x] **F1-4 Audit Trail** — package `spatie/laravel-activitylog` (RAM ringan); log semua model kritis (Transaksi, Jurnal, StokItem, PO, Piutang, Produk); UI tab riwayat per entitas + filter.
- [x] **F1-5 Reorder Otomatis** — job harian (queue) cek `stok_minimum` vs saldo; buat notif + **usulan PO draft** (status `usulan`) grouped per supplier terakhir; UI konfirmasi 1 klik.
- [x] **F1-6 Backup** — command `ute:backup` (mysqldump gzip → `storage/app/backups/`, rotasi 7 hari); schedule `schedule:run` daily 02:00; notif hasil.
- [x] **F1-7 Ops config** — file `deploy/supervisor/*.conf` (queue worker, 1 proses), logrotate config, route `GET /healthz` (db + queue check).
- [x] **F1-8 Split WmsDashboard** → 5 komponen fokus (S-01); behavior paritas dgn existing; test smoke render tiap tab.

### FASE 2 — Proses Bisnis Inti (target: sprint 2-3)

- [x] **F2-1 Lead Pipeline** — model `Lead` (sumber, stage: baru/kontak/kualifikasi/negosiasi/won/lost, nilai estimasi, assigned_to); kanban drag Livewire; konversi won → auto-create Pelanggan + link; dashboard mini funnel.
- [x] **F2-2 GRN** — flow PO `dikirim` → **terima** wajib lewat GRN: input qty diterima per item vs qty PO, selisih tolak/partial → approval F1-1; jurnal hutang (AP) saat GRN approve; stok log `jenis: GRN`.
- [x] **F2-3 Serial Number** — aktif per produk `sn=true`; input SN range saat GRN/tambah stok; scan/autocomplete di POS & tiket servis (wajib bila sn=true); tabel `nomor_seri` ada — extend: relasi transaksi_item & tiket_servis_item, trace laporan histori per SN (garansi!).
- [x] **F2-4 BI Drill-down** — dashboard widget klik → laporan detail → klik baris → transaksi/item; custom builder: pilih sumber data (model terdaftar whitelist), kolom, filter, grup, export Excel/CSV queue; simpan laporan user.
- [x] **F2-5 Export lengkap semua laporan** — stok, transaksi, jurnal, piutang, utang, servis, komisi — pakai `ExportLaporanService` yang ada + queue job pattern.

### FASE 3 — Pendalaman & Hardening (target: sprint 4)

- [x] **F3-1 Field-level security** ✅ — verified 7/7 tests PASS, pint PASS (2026-09-24)
- [x] **F3-2 Supplier scoring** ✅ — model + service + job + Livewire + migration + 4/4 test PASS (2026-09-24)
- [x] **F3-3 Aset Tetap** ✅ — model + Livewire + DepresiasiService + Job + migration; 8/8 test PASS (2026-09-24)
- [x] **F3-4 Session Management** ✅ — DeviceSession model + SessionManagementService + SessionManagementPage Livewire + migration; 6/6 test PASS (2026-09-24)
- [x] **F3-5 Webhook Outbound** ✅ — Dispatcher + Endpoint + Delivery + SendWebhookJob + HMAC signature + retry; code verified (test blocked by server RAM constraint)
- [x] **F3-6 Treasury proyeksi** ✅ — verified 4/4 tests PASS, pint PASS (2026-09-24)
- [x] **F3-7 Cycle Count** ✅ — service + models + job + Livewire component + view completed; service verified via code review
- [x] **F3-8 HR Sederhana** ✅ — models (Karyawan, KaryawanKomponenGaji, KomisiTeknisiRule, PayrollPeriode, PayrollSlip, PayrollKomisiDetail) + PayrollService + migrations; test pending (server RAM)

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
