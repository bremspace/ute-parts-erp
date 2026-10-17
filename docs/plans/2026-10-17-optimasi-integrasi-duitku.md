# Implement Plan — Optimasi Integrasi Duitku

Tanggal: 2026-10-17
Konteks: Riset @librarian (repo `duitkupg/duitku-php` + docs.duitku.com, last-modified 30 Sep 2026) + pemetaan @explorer codebase.

## Temuan utama
- Docs resmi kini pakai **HMAC-SHA256** (stringToSign tanpa apiKey, lalu `hash_hmac('sha256', ..., apiKey)`); kode kita masih **MD5**.
- Endpoint resmi: `POST /v2/inquiry` (create), `POST /transactionStatus` (cek), `POST /paymentmethod/getpaymentmethod` (daftar metode; signature = `merchantcode+paymentAmount+datetime(Y-m-d H:i:s)`).
- SDK resmi `duitku-php` tidak layak pakai (MD5, belum di-merge PR #7, no tag stabil, SSL_VERIFYPEER=false) → **implement manual** via Http client.
- Frontend belum pernah nyambung ke PAY-01: `CustomerAccount::bayarPesanan()` masih SIMULASI, checkout copy "bayar saat ambil".
- Tidak ada test `Http::fake` untuk PAY-01; tidak ada pengecekan status/ekspirasi lokal.

## Tasks

- [x] T-01 — Backend: upgrade `DuitkuService` — endpoint `/v2/inquiry`, `/paymentmethod/getpaymentmethod`, `/transactionStatus`; signature HMAC-SHA256 (create/status/methods); parsing response robust (`statusCode`/`responseCode`); config `signature_mode` (hmac|md5|dual, default dual-saat-transisi).
- [x] T-02 — Backend: webhook PAY-02 — verifikasi dual (HMAC + MD5, keduanya `hash_equals`), simpan `reference` callback ke `payment_reference`, sanitasi log (jangan log signature/raw payload penuh), seragamkan `metode_bayar='duitku'`.
- [x] T-03 — Backend: `duitku:health` (cek kredensial via getpaymentmethod live) + job/command kadaluarsa & rekonsiliasi status (`transactionStatus`) via queue untuk order tersangkut `menunggu_pembayaran`.
- [x] T-04 — Tests: `Http::fake` untuk create/payment-methods/status + webhook test HMAC; jalankan `composer test` hijau.
- [x] T-05 — Frontend (@designer): wire PAY-01 nyata — tombol "Bayar Sekarang" → `POST /api/payment/create` → redirect `paymentUrl` (tampilkan VA/QRIS bila ada), ganti simulasi; copy wajar (Indonesia). ✅ selesai 2026-10-17
- [x] T-06 — Docs/env: `.env.example` (callback URL & signature mode), `config/duitku.php`, update `docs/CHANGELOG.md`.

## Status verifikasi (2026-10-17, @fixer)
- T-01..T-04, T-06 diverifikasi hijau: `php artisan test --filter='Duitku|TransaksiPaidAt|MarketplacePpn|OnlineOrderWorkflow'` → **44/44 lulus** terhadap kode terkini.
- Suite penuh `php artisan test` (run kedua, pasca-perbaikan test): **1011 test — 1000 lulus, 10 gagal, 1 error** (5254 assertions).
- 11 kegagalan sisa **bukan milik lane ini** (tidak ada satu pun file PaymentController/DuitkuService/config/commands/Marketplace test): `BackupCommandTest`, `ExportLaporanTest`, `LivewireSnapshotReplayGuardTest`, `Nplus1P2Test`, `RekonsiliasiCommandTest`, `ServisFaseDIntegritasTest`, `ServisIntegritasFaseATest`, `WmsServisNplus1Test` ×4 (termasuk error `TransferTab::PRODUK_DROPDOWN_LIMIT` yang belum ada di source) — milik lane Notifikasi/Report/Wms/Servis/Akunting & lane designer (layout `@livewire NotificationCenter`).

## Acceptance criteria
- Signature outbound sesuai docs HMAC-SHA256; webhook menerima HMAC & MD5 (transisi aman).
- PAY-01 teruji `Http::fake`; webhook test lama tetap hijau (+ kasus HMAC).
- UI marketplace memanggil Duitku sungguhan (bukan simulasi) pada env yang terkonfigurasi.
- Tidak ada sync queue di request; semua pesan error Indonesia; idempotency webhook dipertahankan.

## Owners
- T-01..T-04, T-06 → @fixer (backend)
- T-05 → @designer (frontend/Livewire/views — DILARANG sentuh DuitkuService/PaymentController/config)
