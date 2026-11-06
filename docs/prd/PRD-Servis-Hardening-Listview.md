# PRD — Servis Module Hardening & List View (Post-Audit)

**Status:** ACTIVE — Phase 1 (P1) in progress
**Tanggal:** 2026-11-06
**Sumber audit:** Full end-to-end code review of `app/Modules/Servis/` (ServisService, ServisStateMachine, ServisBoard, ServisController, CekBatasWaktuServisCommand, migrations, tests) + ISO 9001:2015 clause mapping.
**Branch kerja:** `brem` (push harian `origin/brem`; merge ke `main` hanya via `AUDIT_SYNC` + `BREM_RELEASE_CONFIRMED`).

---

## 1. Background

Audit end-to-end menghasilkan temuan yang sudah sebagian dieksekusi (P0, commit `ac3a3ca`):

| ID | Temuan | Status |
|----|--------|--------|
| P0/BUG-1 | Guard `diambil` memaksa lunas untuk tiket `batal` yang belum pernah jurnal (`bayar()` menolak status `batal`) → deadlock: unit tak bisa diserahkan tanpa "bayar" | ✅ FIXED (diambil guard dibagi 2 skenario) |
| P0/BUG-2 | Tiket yang jurnal `onSelesai`-nya sudah terposting lalu di-override ke `ditolak`/`batal` bisa diserahkan gratis — kehilangan piutang | ✅ FIXED (terkunci kecuali override + alasan) |
| P1-1 | Nomor tiket `SRV-...` dibuat dari `count()+1` **tanpa lock** → dua intake paralel menghasilkan nomor sama; DB unique index menolak → exception mentah untuk kasir | ⬜ PRD ini |
| P1-2 | Auto-cancel SLA bertindak atas nama `User::role('super-admin')->first() ?? teknisi ?? User::first()` → audit trail salah atribusi (pelanggaran ISO 9001 §7.5.3 traceability) | ⬜ PRD ini |
| P1-3 | Subledger AR (`piutang`) dicocokkan lewat `WHERE keterangan LIKE '%{no_tiket}%'` → row LAIN yang kebetulan menyebut no_tiket bisa tertagih-marks lunas (kewarasan pembukuan) | ⬜ PRD ini |
| P2-1 | `prosesBayar()` di-red-gate permission `servis.update-status` — pembayaran butuh permission sendiri `servis.payment` | ⬜ Fase 2 |
| P2-2 | `bayar()` memakai `estimasi_biaya` sbg jasa saat semua item jasa dibatalkan → overbill | ⬜ Fase 2 |
| P2-3 | `terimaUnit()` menerima `cabang_id` dari request tanpa validasi akses cabang user | ⬜ Fase 2 |
| P2-4 | Notifikasi `batal`, UX copy, dsb | ⬜ Fase 2 |
| UI-1 | Kanban grid adalah satu-satunya tampilan papan servis; butuh **List view** responsif | ⬜ Fase 3 |

---

## 2. Arsitektur Hasil Inspeksi (yang WAJIB diikuti implementasi)

Pola yang sudah mapan di codebase ini — implementasi baru HARUS meniru:

1. **Number generation** (`JurnalService::generateNoJurnal`): `DB::transaction` → `lockForUpdate()` → pluck nomor → `max($suffix) + 1`. LIKE pattern **WAJIB menyertakan `$cabangId`** (SESSION-STATE: tanpa cabangId → konflik lintas cabang). DB unique index sebagai jaring pengaman terakhir. Format: `SRV-C{cabang:02d}-{Ymd}-{seq:04d}` (walk-in/walk-out) dan `SRV-ONL-{Ymd}-{seq:04d}` (booking, global).
2. **Referensi polimorfik on AR/AP**: tabel `utang` sudah punya `referensi_tipe` (string) + `referensi_id` (bigint). Tabel `piutang` BELUM — kini ditambahkan (kolom baru, nullable, non-destructive, + index komposit).
3. **Audit trail**: `servis_status_log.user_id` adalah FK **nullable** ke `users` — aktor sistem direpresentasikan `user_id = NULL` + `aksi` baru `auto_batal_sistem`. `AuditService::catat()` dipanggil dengan deskripsi eksplisit "Sistem (scheduler)". **Tidak membuat user palsu.**
4. **Response/UX contract**: semua pesan error **Bahasa Indonesia**; API codes `[API: SERVICE-XX]` tidak boleh dinama-kan ulang; respons `{success, data, message}`.
5. **Scoping**: semua query tiket WAJIB scope `cabang_id` (session). `updateStatus` memakai `lockForUpdate` + validasi ulang di dalam transaksi.
6. **Migration rules**: non-destructive, SQLite `:memory:` compatible (tests), ada `down()`.
7. **Livewire**: computed properties WAJIB pass eksplisit di `render()`; permission gate via `boleh()`; board memakai `tiketScoped()`.
8. **Queue**: semua notifikasi via `NotificationService->kirim()` (database queue) — tidak pernah sinkron di request lifecycle.

---

## 3. Fase 1 (P1) — Scope Eksekusi Saat Ini

### T-01 — Nomor tiket race-safe (align `JurnalService` pattern)
- Ekstraksi helper `buatNoTiket(int $cabangId): string` di `ServisService`:
  transaksi + `lockForUpdate` + LIKE `SRV-C{cc}-{Ymd}-%` scoped cabang → `max($seq)+1`.
- Booking online: helper `buatNoTiketOnline(): string` — LIKE `SRV-ONL-{Ymd}-%` (global, tanpa cabang di pattern tapi tetap lock).
- `terimaUnit()` / `bookingOnline()` memakai helper tersebut; creation dibungkus retry (≤3) menangkap `QueryException` duplicate-key ( Defens-in-depth vs unique index `no_tiket`).
- **Non-goal**: tidak mengubah format prefix (konsumen/merge/job lain bergantung padanya).

- [x] T-01a — helper no-tiket walk-in + booking, lock + max-sequence + retry duplicate-key
- [x] T-01b — unit test/feature: dua tiket berurutan di cabang sama dapat nomor unik & berurutan; format tetap `SRV-C{cc}-{Ymd}-{seq}`

### T-02 — Aktor sistem untuk auto-cancel SLA (ISO 9001 §7.5.3)
- `ServisService::updateStatus()` menerima parameter opsional `string $sumber = 'manual'` (`manual|sistem`).
- `$sumber === 'sistem'`: user dianggap NULL (tidak boleh dikunci ke user STAFF), transisi tetap WAJIB valid oleh `ServisStateMachine::dapatTransisi()` — DENGAN pengecualian eksplisit hanya untuk target `ditolak` (nonconforming-output control, §8.7) dari status zona kerja (`dikerjakan|qc`), plus `alasan` WAJIB, log `aksi = 'auto_batal_sistem'`, `user_id = NULL`, `AuditService::catat` dengan deskripsi "Sistem (scheduler SLA)".
- Semua jalur lain TIDAK berubah (publik token approval tetap `manual` + user null + transisi valid saja).
- `CekBatasWaktuServisCommand` → panggil `updateStatus(..., null, $alasanBatal, 'sistem')`, HAPUS masquerade `super-admin/teknisi/User::first()`.
- **Non-goal**: tidak menambah status baru, tidak mengubah PRD §4.3 state machine diagram (transisi automatic tetap dieja di log).

- [x] T-02a — parameter `sumber` + validasi (alasan wajib, target hanya `ditolak`)
- [x] T-02b — command refactor: tanpa user palsu
- [x] T-02c — test: sistem-cancel dari `dikerjakan` menghasilkan log `auto_batal_sistem`, `user_id` NULL; jalur manual user-null TIDAK bisa override (tetap ditolak)

### T-03 — Subledger AR referensi eksplisit (hapus matching `LIKE`)
- Migration `2026_11_06_010000_add_referensi_to_piutang_table.php`: nullable `referensi_tipe` (string), nullable `referensi_id` (bigint), index komposit `['referensi_tipe', 'referensi_id']`; non-destructive; down() drops kolom.
- Backfill di migration: baris piutang yang `keterangan`-nya mengandung `SRV-...` valid (regex ke format nomor tiket yang ada) → isi referensi TiketServis yang cocok (dengan Produk/keterangan guard: hanya bila UNIQUE match, tidak ambi praktik menantang).
- `ServisService::onSelesai()` → `Piutang::create` menulis `referensi_tipe = TiketServis::class`, `referensi_id = $tiket->id`.
- Matching piutang untuk pelunasan/cek eksisting difokus lewat helper private `piutangServis(TiketServis $tiket): ?Piutang` → urutan: (1) `referensi_tipe+referensi_id` EXAKT, (2) fallback `keterangan LIKE` HANYA utk rows legacy yang belum ter-backfill.
- **Non-goal**: tidak mengubah skema/semantik utang; tidak merilis SPK/piutang baru di luar servis.

- [x] T-03a — migration + backfill + down()
- [x] T-03b — helper `piutangServis()` + pengganti seluruh LIKE di `onSelesai()` & `bayar()`
- [x] T-03c — test: dua piutang (referensi-exact vs decoy keterangan-LIKE) — hanya row referensi-exact yang tertagih on bayar(); onSelesai menulis referensi

---

## 4. Fase 2 (P2) — setelah konfirmasi user (AKTIF)
Permission `servis.payment` terpisah (SettingsRbac + Livewire gate + middleware):
- Seeder: permission `servis.payment` baru; diberikan ke super-admin/owner (all), admin-toko, teknisi (kompatibilitas — dulu bayar via `servis.update-status`), dan `kasir` (jalur pelunasan POS adalah tugas meja kasir; dulu tanpa gate sama sekali). Owner dapat mengetatkan (mis. cabut dari teknisi) tanpa code change.
- Gate: `ServisBoard::prosesBayar()` + `PosKasir::prosesBayarServis()` (dulu TANPA gate sama sekali) + route API `/{id}/bayar` middleware `permission:servis.payment` (dulu `servis.update-status`).
- Katalog permission di `SettingsRbac` untuk UI settings.

- [x] T-04a — permission `servis.payment` di catalogue + seeder (5 role) + route middleware
- [x] T-04b — Livewire gates: ServisBoard::prosesBayar + PosKasir::prosesBayarServis
- [x] T-04c — test: user dengan `servis.update-status` TANPA `servis.payment` ditolak 403 di API bayar (decoupling terbukti); user dengan payment lolos

`bayar()` tidak memakai `estimasi_biaya` bila baris jasa eksplisit pernah ada dan semuanya dibatalkan:
- Helper `TiketServis::punyaRiwayatJasaEksplisit()` (ada baris `TiketServisItem` tipe jasa, termasuk yang dibatalkan).
- Fallback estimasi hanya dipakai bila tiket TIDAK PERNAH punya item jasa (tiket legacy); jurnal `onSelesai` + pelunasan `bayar` + tampilan `getRincianBiayaLengkap` konsisten.

- [x] T-05a — helper + guard di `onSelesai`, `bayar`, `getRincianBiayaLengkap`
- [x] T-05b — test: semua item jasa dibatalkan → jasa = 0 (bukan estimasi); tiket legacy tanpa item jasa masih ditagih estimasi

Validasi `cabang_id` request vs akses cabang user pada `terimaUnit` (align `resolveCabangId`):

- [x] T-06a — guard cabang di `ServisService::terimaUnit` (bisaAksesCabang + role HQ)
- [x] T-06b — test: staff cabang A intake ke cabang B → exception; super-admin lintas cabang → lolos

## 5. Fase 3 (UI-1) — List View papan servis (designer-driven)
- Toggle `view-mode: kanban | list` (persist ke localStorage + preferensi user, sama pola ADR 0008 theme).
- List view: tabel desktop (DataTable pattern `PRD-Frontend §3`) / kartu aksi di mobile; kolom sama dgn kartu Kanban; swipe/akses kolom drilldown; dark-mode default backoffice; mobile-first list.
- Desain diserahkan maupun flow keminter ke @designer; dilarang shadcn/tailwind-ui default tanpa custom (PRD-Frontend §3).

---

## 6. Verification Plan (setiap fase)
1. `./vendor/bin/pint` — PSR-12.
2. `php artisan migrate` (SQLite :memory: ) + `composer test` — amin no regressions; P1 tests green.
3. Independensi node desain: PRD tidak menimpa file PRD lain.
4. CHANGELOG entry per fase selesai.
5. Push ke `origin/brem` — TIDAK PERNAH ke `main` tanpa token `BREM_RELEASE_CONFIRMED`.

## 7. Risk & Mitigation
- **T-01** retry loop bisa menutupi bug lain: catch hanya `SQLSTATE 23000/1062` — exception lain di-thrown ulang.
- **T-02** membuka loop trust: mode `sistem` TAUTKUNCI target `ditolak`, alasan wajib, hanya kode internal (command) yang memakainya — tidak ter-expose di HTTP.
- **T-03** backfill bisa salah link bila keterangan ambigu: hanya backfill bila EXACTLY satu tiket match; sisanya biarkan NULL (fallback LIKE tetap dipakai kanal legacy).
