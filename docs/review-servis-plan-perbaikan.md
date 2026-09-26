# Review Modul Servis — Alur, Integrasi & Plan Perbaikan

> Tanggal: 2026-09-26 · Sumber: 3 lane review paralel (akunting/stok, sisi konsumen, backoffice), semua temuan terverifikasi dengan `path:line`.
> Status: **review selesai, eksekusi belum dimulai.** Checkbox dicentang hanya setelah verified per aturan AGENTS.md.

---

## 1. Ringkasan Alur (kondisi aktual)

### 1.1 Checkin → kerja → checkout (backoffice)
```
[diajukan_online] → diterima (terimaUnit: form, foto, kondisi fisik, no tiket harian)
→ diagnosa → setEstimasi() → menunggu_approval (token_approval dibuat + link)
→ disetujui (approveByToken / staf) → dikerjakan (inputPekerjaan: part potong stok + SN, jasa)
→ qc → selesai (onSelesai: garansi auto + JURNAL 4–5 baris, fail-closed) → diambil (terminal)
```
- State machine tegas: `app/Modules/Servis/Services/ServisStateMachine.php:20-30`, tidak boleh mundur kecuali `servis.override-status` + log alasan (`ServisService.php:121-135`).
- Setiap transisi dicatat `ServisStatusLog` (timeline ada, tidak pernah dipakai di UI pelanggan).

### 1.2 Integrasi pembukuan — **sudah ada, tapi 3 cacat serius**
- Jurnal onSelesai (`ServisService.php:448-553`): Kas 110-01 D / Pendapatan jasa 420-01 K / Pendapatan part 410-01 K / HPP 510-02 D / Persediaan 130-01 K. Balance divalidasi (`JurnalService.php:120-132`), akun wajib aktif (`JurnalService.php:90-97`), fail-closed: jurnal gagal → status tidak berubah (teruji `JurnalFailClosedTest.php:73-112`).
- `generateNoJurnal('servis', cabangId)` sudah memuat cabangId + `lockForUpdate` (`JurnalService.php:323-348`) ✅.
- Masuk Laba Rugi / Neraca / Jurnal Umum & export ✅.
- **Tetapi:** (a) kas di-'selesai' **sebelum pelanggan membayar** — tidak ada mekanisme bayar/piutang servis; (b) **tidak idempoten** — bisa dobel post; (c) COA hanya di **seeder**, `composer setup` tanpa `--seed`.

### 1.3 Stok part toko — **deduksi benar, pengembalian tidak ada**
- Potong via `StokDeductionService::kurangi()`: `lockForUpdate`, tolak stok negatif, StokLog + StockMutationLog, referensi `TiketServis` (`StokDeductionService.php:42-126`) ✅.
- SN produk `sn=true` diklaim `servis` saat input pekerjaan, dilepas saat selesai/ditolak (`NomorSeriService.php:145-175`) ✅.
- **Tetapi:** tidak ada restore saat tiket ditolak; gudang tidak di-scope cabang; dua jalur input (legacy `ServisSparepart` vs `TiketServisItem`) bisa tumpang tindih.

### 1.4 Sisi konsumen — **paling lemah**
- Halaman publik `GET /tracking/{token}` (`routes/web.php:46-67`): stepper 8 langkah + estimasi + garansi saja. Tanpa timeline, item, tagihan.
- Akun pelanggan (`customer-account.blade.php:84-109`): kartu status + link tracking. Tanpa stepper/estimasi/approve.
- Booking online: endpoint ada (`routes/api.php:34`), **UI nol**, dan tiket `diajukan_online` **tidak tampil di Kanban**.
- Notifikasi: semua `inapp` + `tujuan=null`; job `inapp` = no-op, `wa` = placeholder tanpa HTTP call → **pelanggan tidak pernah menerima apa pun**.

---

## 2. Temuan per Prioritas

### P0 — memblokir integritas data / uang / alur inti
| # | Temuan | Bukti |
|---|---|---|
| P0-1 | **Jurnal onSelesai tidak idempoten.** `post()` tanpa `$idempotencyKey`; skenario `selesai → override mundur ke qc → selesai` = jurnal dobel (pendapatan/kas dobel). Unique index tak melindungi (NULL distinct). Tidak ada guard `tanggal_selesai`. | `ServisService.php:505-515`, `ServisService.php:423-425`, `ServisBoard.php:438-442`, migrasi `2026_09_25_030000:53-54` |
| P0-2 | **Tidak ada pembayaran servis.** Kas 110-01 dijurnal di 'selesai' sebelum bayar; tidak ada kolom bayar/lunas, endpoint bayar, `Piutang::create` servis, atau cek lunas di 'diambil'. Saldo kas digelembungkan; laba ≠ kas. | `ServisService.php:493`, `TiketServis.php:14-20`, `routes/api.php:111-118`, banding POS kasbon `PosController.php:350-396` |
| P0-3 | **Stok tidak direstore saat tiket ditolak.** Hanya SN dilepas; `StokItem`/`TiketServisItem`/`ServisSparepart` tetap. → HPP 510-02/Persediaan 130-01 bisa dobel-credit saat re-estimasi (`ditolak→diagnosa` valid forward). | `ServisService.php:150-157,399-407,466-471`, `ServisStateMachine.php:29`, `SerialNumberTest.php:618-669` (hanya assert SN) |
| P0-4 | **COA hanya di seeder, `composer setup` = `migrate --force` tanpa `--seed`.** Instalasi fresh: akun tak ada → `post()` throw → **semua** tiket gagal `selesai` (fail-closed). | `database/seeders/AkunCoaSeeder.php:21-48`, `composer.json:49-56` |
| P0-5 | **Notifikasi pelanggan tidak pernah sampai.** Semua call servis `tipe='inapp', tujuan=null`; job `inapp` = no-op, `wa` = placeholder (tanpa HTTP call). Tidak ada satu pun milestone yang sampai ke pelanggan. | `ServisService.php:99,161,192`, `NotificationService.php:19-41`, `KirimNotifikasiJob.php:41-114` |
| P0-6 | **Link approve dari notifikasi = endpoint POST.** `url("/api/servis/public/approve/{token}")` dibuka dari WA = **405 JSON**, bukan halaman. Tidak ada GET route approve; halaman tracking tanpa tombol approve/reject → alur `menunggu_approval` buntu dari sisi pelanggan. | `ServisService.php:191`, `routes/api.php:33`, `tracking-publik.blade.php` (115 baris, tanpa form) |
| P0-7 | **`setEstimasi` mencatat `status_dari` salah** (`menunggu_approval → menunggu_approval`; nilai asli hilang permanen) + **bypass state machine** (tanpa `DB::transaction`, tanpa cek transisi). | `ServisService.php:180-187` (konfirmasi baca langsung) vs pola benar `ServisService.php:123,137-139` |
| P0-8 | **Potong stok sebelum approval pelanggan + tanpa SN.** `inputSparepart` mengizinkan status `diagnosa` (sebelum estimasi disetujui), dan jalur ini **tidak pernah klaim SN** (unit `sn=true` bisa terjual lagi). | `ServisService.php:225`, `ServisService.php:266-274` (tanpa `klaimServis`) |
| P0-9 | **Guard anti-dobel hanya satu arah.** `inputSparepart` tolak bila ada item T-17, tapi `inputPekerjaan` tidak cek `ServisSparepart` → dobel potong stok. Dampak jurnal: bila legacy terisi lalu pekerjaan hanya baris `jasa`, `onSelesai` pakai cabang `items` → **410-01/510-02/130-01 hilang dari jurnal** walau stok sudah berkurang. | `ServisService.php:231-233` vs `:289-352`, `:464-485` |
| P0-10 | **Kanban tanpa scope `cabang_id`.** `ServisBoard::getGroupedTiketsProperty` query tanpa filter cabang (melanggar constraint "semua query wajib `cabang_id`"). `ServisController::show` juga tanpa scope (bocor silang-cabang). | `ServisBoard.php:156-205`, `ServisController.php:99-113` |

### P1 — alur/consumer experience rusak atau bocor
| # | Temuan | Bukti |
|---|---|---|
| P1-1 | Booking online: endpoint ada, **UI/form nol** (PRD §6.6 menjanjikan form pilih cabang); tidak ada `pelanggan_id` saat user login. | `routes/api.php:34`, `ServisController.php:218-243`, `layouts/marketplace.blade.php:65-97` |
| P1-2 | Tiket `diajukan_online` **tidak muncul di Kanban/filter** — menguap antara booking dan `diterima`. | `ServisStateMachine.php:72-84`, `ServisBoard.php:198-204`, blade:11-16 |
| P1-3 | Tracking publik minim: tanpa timeline `ServisStatusLog`, item part/jasa, total tagihan; stepper **rusak** utk `diajukan_online` & `ditolak` (`$currentIdx=-1`). | `tracking-publik.blade.php:41-44`, `routes/web.php:46-67` |
| P1-4 | Link tracking di akun pelanggan dirender walau `token_approval` masih `null` → 404; token baru dibuat saat estimasi (bukan saat checkin). | `customer-account.blade.php:100`, `ServisService.php:32-68` (terimaUnit tanpa token) |
| P1-5 | `GET /api/servis/tracking/{token}` terkunci `auth:sanctum` padahal dikomentari "publik". | `routes/api.php:219-230` |
| P1-6 | `gudang_id` tidak di-scope ke cabang tiket (validasi hanya `exists:gudang,id`; `StokDeductionService::kurangi` tanpa filter cabang) → stok cabang B terpotong, jurnal masuk cabang A. | `ServisController.php:167,201`, `StokDeductionService.php:42-58` vs `:211` |
| P1-7 | `updateStatus` tidak validasi `$statusBaru ∈ STATUS_VALID` → status ngawur dari override. | `ServisStateMachine.php:32-35`, `ServisService.php:121-135` |
| P1-8 | Export servis: periode di-hardcode MTD, filter `created_at` (bukan `tanggal_selesai`), fail-open saat `session('cabang_id')` null, `->get()` tanpa chunk (RAM 1GB). | `ServisBoard.php:127-149`, `ExportLaporanService.php:347-361` |
| P1-9 | Tidak ada test utk: dobel jurnal override, restore stok ditolak, HPP dobel re-estimasi, campur legacy+T-17, cross-cabang gudang, reject approval token, booking online, halaman tracking. | daftar `tests/` (exp-1 §5) |

### P2 — kualitas/keamanan lapis kedua
- `servis.approve-estimasi` permission **mati** — approval aktual jalan via `servis.update-status` → teknisi bisa approve estimasi sendiri (`RolesAndPermissionsSeeder.php:27,85,113`, `ServisBoard.php:426`).
- `token_approval` **di-regenerate** tiap simpan estimasi → link yang sudah dibagikan mati (`ServisService.php:183` vs `:409-414`).
- Harga item dari request dipakai mentah utk tagihan/jurnal tanpa pembanding `estimasi_biaya` yang disetujui; `inputPekerjaan` boleh jalan di `qc` (nilai bisa berubah detik terakhir sebelum jurnal) (`ServisService.php:291,302`).
- Override tanpa alasan tetap lolos (`alasan` default `''`) (`ServisService.php:121-135`).
- Notifikasi memakai slug status mentah (`"…ke: menunggu_approval"`) dan tanpa link tracking (`ServisService.php:163`).
- `accountServis` `select *` tanpa paginasi → tarik `foto_unit` base64 semua riwayat (`ShopController.php:202-215`).
- Export laporan servis hanya menampilkan `estimasi_biaya`, bukan realisasi tagihan → tidak bisa direkonsiliasi dengan jurnal (`ExportLaporanService.php:347-361`).
- `jurnal_akuntansi.sumber` tanpa allow-list (`JurnalService.php`).

---

## 3. Plan Perbaikan (todo)

### Fase A — Integritas jurnal & stok (P0, urut dependency) ✅ SELESAI & HARDENED (2026-09-26)
- [x] **T-01** — Idempotensi jurnal servis: guard `tanggal_selesai` + `idempotencyKey 'servis:{id}'` di `onSelesai`; komisi & jurnal tidak dobel pada `selesai → override → selesai`. Hardened [P0-2]: blokir penambahan item setelah selesai (`inputPekerjaan`/`inputSparepart`) + fail-closed guard di `onSelesai` jika ada item setelah `tanggal_selesai`. Test: `ServisIntegritasFaseATest`.
- [x] **T-02** — COA: `composer setup` kini jalankan `db:seed --class=AkunCoaSeeder --force` setelah migrate; seeder idempoten + 5 akun wajib servis dipaksa `is_active`. Test: `AkunCoaSeederTest` (5 test, termasuk bukti migrasi saja tidak membuat akun).
- [x] **T-03** — Restore stok saat `ditolak`: `onDitolak()` → `restoreStokPartDitolak()` membalik dari StokLog net outflow per gudang+varian + method baru `StokDeductionService::kembalikan()` + kolom `dibatalkan_at` (migrasi `2026_09_26_010000`) di `tiket_servis_item` & `servis_sparepart`. Hardened [P0-1]: net sum (tanpa filter negatif) mencegah stok hantu pada siklus penolakan berulang (10→8→10→9→10); Hardened [P1-1]: `lockForUpdate` atomik pada tiket dan baris part saat transisi/restore.
- [x] **T-04** — Anti HPP dobel: perhitungan `onSelesai` menyaring `whereNull('dibatalkan_at')` pada items & legacy. Test: re-estimasi pasca-ditolak → HPP 1x.
- [x] **T-05** — Guard simetris: `onSelesai` merge legacy+items (bukan if/else) → baris 410-01/510-02/130-01 tetap ada walau items hanya jasa. Hardened [P1-3]: guard `inputPekerjaan` granular hanya tolak jika payload memuat baris part; baris tipe jasa murni tetap diizinkan walau ada part legacy.
- [x] **T-06** — Scope `cabang_id`: helper `scopeTiket()`/`tiketScoped()` di `ServisBoard` (grouped, selected, semua mutasi) & `ServisController::show` (+ `updateStatus/setEstimasi/inputSparepart/inputPekerjaan` → 404 silang-cabang); validasi gudang wajib 1 cabang dgn tiket di `inputSparepart`/`inputPekerjaan`. Test: `ServisCabangScopeTest` (10 test).
- [x] **T-07** — `setEstimasi`: `$statusLama` ditangkap sebelum update (log `dari` benar), `DB::transaction(...,3)`, validasi `dapatTransisi` (+ sandbox dua-hop `ditolak→diagnosa→menunggu_approval`), token hanya dibuat bila null (link lama tetap hidup).
- [x] **T-08** — `inputSparepart`: guard `['disetujui','dikerjakan']` (buang `diagnosa`); produk `sn=true` ditolak → wajib form pekerjaan (SN tidak pernah lolos tanpa klaim).
- [x] **T-09** — `updateStatus` validasi `$statusBaru ∈ allStatuses()` sebelum transisi/override (`"Status tidak valid"`).

### Fase A.2 — Estimasi Terperinci & Manajemen Kunci HP (Fondasi Transaksi) ✅ SELESAI (2026-09-26)
- [x] **T-09a** — Skema & Migrasi: tambah `biaya_jasa` (`decimal(12,2) default 0`) pada tabel `jenis_servis`; buat tabel `tiket_servis_estimasi_item` (`id`, `tiket_servis_id`, `tipe ['part','jasa']`, `jenis_servis_id`, `produk_id`, `sku_variant_id`, `nama_item`, `qty`, `harga`, `subtotal`). Buat model Eloquent `TiketServisEstimasiItem` & relasi `estimasiItems()` di `TiketServis`.
- [x] **T-09b** — UX/UI Kunci HP di Terima Unit & Modal Detail: toggle intip (`👁️`) pada input PIN/Password; visual grid 3x3 titik interaktif + tombol reset pola di modal terima unit; visualisasi grid 3x3 urutan nomor node menyala di modal detail untuk teknisi.
- [x] **T-09c** — Service & Backend Estimasi Terperinci: perbarui `ServisService::setEstimasi()` agar menerima array `$items` (part/jasa), menghitung total otomatis, menyimpan ke `tiket_servis_estimasi_item`, mengupdate `$tiket->estimasi_biaya = sum(subtotal)` tanpa memotong stok/klaim SN. Integrasikan resolusi harga part dengan `PricingService::resolve($produk, $tiket->pelanggan, $variant)` dan template jasa `jenis_servis.biaya_jasa`.
- [x] **T-09d** — Livewire Estimasi Multi-Baris & Prefill Pekerjaan: form modal estimasi di `ServisBoard` dengan baris dinamis part (katalog cabang + info stok + harga tier otomatis) dan jasa (pilihan jenis servis / custom); saat status `disetujui`/`dikerjakan`, tombol "Input Pekerjaan" otomatis mem-prefill baris pekerjaan dari item estimasi. Test suite komprehensif (`ServisEstimasiTerperinciTest` & `ServisBoardEstimasiKunciTest`).

### Fase B — Pembayaran & buku besar (P0-2) ✅ SELESAI (2026-09-26)
- [x] **T-10** — Tambah kolom `status_pembayaran` enum('belum_bayar','lunas') default 'belum_bayar', `tanggal_bayar`, `metode_pembayaran`, `no_jurnal_bayar` di `tiket_servis` (migrasi `2026_09_26_030000_add_pembayaran_to_tiket_servis_table.php`).
- [x] **T-11** — Jurnal `onSelesai`: Debit **120-01 Piutang Usaha** (bukan 110-01 Kas) / Kredit Pendapatan (420-01 & 410-01) + HPP (510-02 D / 130-01 K); status pembayaran tetap 'belum_bayar'. Ditambahkan `120-01` ke `AKUN_WAJIB_SERVIS` di `AkunCoaSeeder`.
- [x] **T-12** — Endpoint & Method bayar servis (`ServisService::bayar()` / `POST /api/servis/{id}/bayar`, permission `servis.update-status`):
  - Validasi belum lunas dan totalTagihan > 0.
  - Posting Jurnal pelunasan: Debit **110-01 Kas** / Kredit **120-01 Piutang Usaha** (idempotency key `servis-bayar:{tiket_id}`).
  - Catat `tanggal_bayar`, `metode_pembayaran` (tunai/transfer/qris/kartu), `no_jurnal_bayar`, update `status_pembayaran = 'lunas'`.
- [x] **T-13** — Guard transisi `diambil`: status `diambil` hanya boleh bila `status_pembayaran === 'lunas'`. Jika belum lunas, lempar exception "Unit tidak dapat diserahkan/diambil sebelum pembayaran lunas".
- [x] **T-14** — UI Pembayaran di `ServisBoard` & Export Laporan: modal kasir pembayaran di Livewire, badge status pembayaran (Belum Bayar / Lunas) di kartu kanban dan modal detail, ekspor servis menampilkan realisasi tagihan dan status/metode pembayaran. Test: `ServisPembayaranFaseBTest` & `ServisBoardBayarTest`.

### Fase C — Sisi konsumen (P0-5, P0-6, P1-1..5) ✅ SELESAI (2026-09-26)
- [x] **T-15** — Halaman **GET approve publik** baru: `GET /tracking/{token}` tampilkan estimasi + tombol Setujui/Tolak (form → `POST /tracking/{token}/approve` dan `/reject`), rate limit + throttle; link di notifikasi (`ServisService.php`) diarahkan ke halaman web.
- [x] **T-16** — Buat `token_approval` saat checkin (`terimaUnit`) + booking, kirim link tracking saat `diterima`; hancurkan link rusak di akun pelanggan (`customer-account.blade.php`).
- [x] **T-17** — Realisasi kanal notifikasi: integrasi HTTP call gateway WA (Fonnte/Wablas) di `KirimNotifikasiJob`, dispatch notifikasi WA ke telepon pelanggan per milestone (terima unit, estimasi, status update) dengan label ramah manusia & link tracking.
- [x] **T-18** — Perkaya halaman tracking publik: timeline `ServisStatusLog`, item part/jasa, total tagihan & status pembayaran, stepper tangani `diajukan_online` & `ditolak`, tampilkan `tanggal_selesai`. (`tracking-publik.blade.php`, `routes/web.php`)
- [x] **T-19** — Form booking servis online di marketplace (`BookingServis.php` Livewire) + pilih cabang, keluhan, jenis HP, auto-fill customer login, dan redirect ke tracking publik.
- [x] **T-20** — Tambah antrean status `diajukan_online` di Kanban staf (`ServisBoard.php` & blade) dengan card alert dan tombol "Konfirmasi & Terima Unit".
- [x] **T-21** — Perbaikan akun pelanggan: select eksplisit `estimasi_biaya`, `status_pembayaran`, stepper/timeline ringkas, dan link tracking hanya jika token ada.
- [x] **T-22** — Keluarkan `GET /api/servis/tracking/{token}` dari middleware `auth:sanctum` (masuk ke grup throttle publik).

### Fase D — RBAC, lapis kualitas, test (P2 + P1-8,9) ✅ SELESAI (2026-09-26)
- [x] **T-23** — Aktifkan guard `servis.approve-estimasi` di `ServisBoard::prosesApprove`; teknisi tanpa permission ini ditolak saat menyetujui estimasi.
- [x] **T-24** — Wajibkan `alasan` saat override status (validasi required bila `aksi === 'override'`).
- [x] **T-25** — Batasi `inputPekerjaan` hanya boleh saat `disetujui` dan `dikerjakan` (status `qc` dilarang menambah item).
- [x] **T-26** — Export servis: sertakan `REALISASI`, `STATUS BAYAR`, `METODE`, `NO JURNAL BAYAR`, `TGL SELESAI`, dan `TGL BAYAR`.
- [x] **T-27** — `accountServis` select kolom aman + paginasi (sembunyikan `kunci_terenkripsi`, `pola_kunci`, `pin_kunci`, dan `foto_unit`); allow-list `SUMBER_VALID` pada `JurnalService::post`.
- [x] **T-28** — Test suite baru `ServisFaseDIntegritasTest` menguji guard approve RBAC, override wajib alasan, larangan input pekerjaan di QC, proteksi data shop account, allow-list jurnal sumber, dan HTTP WA notification.

### Urutan eksekusi yang disarankan
1. **A (T-01..T-09)** — integritas uang/stok, tidak bergantung pihak lain.
2. **B (T-10..T-14)** — keputusan desain kas/piutang lebih dulu (§4).
3. **C (T-15..T-22)** — pengalaman pelanggan; T-15/T-16/T-17 adalah inti "konsumen bisa memantau + approve".
4. **D (T-23..T-28)** — rapikan & kunci dengan test.

---

## 4. Keputusan yang perlu dipilih sebelum Fase B
1. **Kapan pendapatan servis diakui kas?**
   - **Opsi 1 (saat ini, minim perubahan):** kas di `selesai` — jujur hanya bila bayar tempat; tetap tambah kolom `lunas` + endpoint bayar untuk audit.
   - **Opsi 2 (disarankan, selaras PRD §4.6/POS kasbon):** `selesai` → Piutang 120-01; bayar → Kas 110-01; `diambil` wajib lunas. Laporan servis menampilkan piutang berjalan.
2. **Kanal notifikasi resmi:** Fonnte (WA) — kredensial mana yang sudah tersedia di `.env` staging/produksi?

## 5. Bukti verifikasi
- Baca langsung orkestrator: `ServisService.php:173-219` (bug logStatus self-loop + link POST), `ServisStateMachine.php` (transisi), `tracking-publik.blade.php` (tanpa tombol approve), `routes/web.php:45-67`.
- Lane exp-1 (akunting/stok), exp-2 (konsumen), exp-3 (backoffice) — semua `path:line` di atas berasal dari laporan mereka dan tidak saling kontradiktif.
