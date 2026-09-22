# STAGING-HANDOFF — Konteks Pekerjaan Ute Parts

> Baca file ini saat lanjut kerja di workspace ini (staging). Berisi status terkini, keputusan, dan JANGAN lupakan gotcha.
> Dibuat: 2026-09-20. Sumber asli: sesi opencode yang membangun staging ini.

## Cara melanjutkan sesi (reminder)
1. File ini = anchor konteks. Buka dulu, lalu baca: `PRD-Frontend-UteParts.md`, `PRD-Backend-UteParts.md`, `implement-plan.md`, `CONTEXT.md`, `docs/adr/0006-0012.md`, `CHANGELOG.md`.
2. Untuk melanjutkan dari sesi opencode lama: `cd /var/www/test.uteparts.id-staging && opencode` lalu resume sesi yang berjudul seputar Fase 10, atau pakai file handoff ini sebagai prompt pembuka "lanjutkan pekerjaan dari STAGING-HANDOFF.md".

## Lingkungan
- **Production (live)**: `/var/www/test.uteparts.id` — `https://66.42.48.27` (self-signed cert, HTTP→HTTPS redirect utk IP), domain `test.uteparts.id` masih via Cloudflare (SSL pending).
- **Staging (ini)**: `/var/www/test.uteparts.id-staging` — `http://66.42.48.27:8080`
- **DB prod**: `test_uteparts` / **DB staging**: `db_staging` — user `test_uteparts`/`test123` (localhost + 127.0.0.1). 61 tabel, data identik.
- **PHP CLI**: installasi pakai `/usr/bin/php8.5` (atau php8.4). JANGAN pakai php8.3 (vendor butuh ≥8.4.1). FPM: `php8.5-fpm`, socket `/run/php/php8.5-fpm.sock`.
- **MySQL**: akses root via `sudo mysql` (socket auth). Bukan `mysql -uroot -p`.
- **Auto-deploy prod: DIMATIKAN** (2026-09-20, permintaan user) — `ute-parts-deploy.timer` stop+disable. Rilis prod sekarang MANUAL: `systemctl start ute-parts-deploy.service` JANGAN — service punya bug (lihat gotcha #8). Prosedur manual: `cd /var/www/test.uteparts.id && git pull origin main`, lalu `export DEPLOY_PATH=... PHP_BIN=/usr/bin/php8.5 QUEUE_NAME=ute-parts-queue HOME=/root && ./deploy.sh`, lalu `php artisan migrate --force` + `npm run build` + cache + `supervisorctl restart ute-parts-queue`. Staging TIDAK ter-deploy otomatis (snapshot manual via rsync).

## ISOLASI STAGING (jangan diubah)
- `.env` staging: `APP_ENV=staging`, `APP_DEBUG=true`, `APP_URL/ASSET_URL=http://66.42.48.27:8080`, `DB_DATABASE=db_staging`, `FORCE_HTTPS=false`, `SESSION_SECURE_COOKIE=false`, `SESSION_SAME_SITE=lax`, `SESSION_COOKIE=ute-parts-staging`, `SANCTUM_STATEFUL_DOMAINS=66.42.48.27:8080`, APP_KEY sendiri (sudah regenerate).
- Cookie sesi staging beda nama → tidak bentrok dgn prod (host sama, port beda, cookie tetap dishare browser).
- `public/storage` symlink → `/var/www/test.uteparts.id-staging/storage/app/public` (SUDAH diperbaiki; sebelumnya nunjuk ke prod).

## STATUS FASE 10 (implement-plan.md, T-28..T-37)
- T-28 Security ✅ (SecurityHeaders, ForceHttps, throttle global `/api/*`, .env hardening) — branch `fase-10-t-28`
- T-29 Ganti Cabang ✅ — branch `fase-10-t-29`
- T-30 Responsive ✅ — branch `fase-10-t-30`
- T-31 PWA ✅ (sebagian — file statis manifest/offline/icons baru di-commit ke git prod hari ini) — branch `fase-10-t-31`
- T-32 Theme toggle ✅ — migration `users.theme_preference` + `POST /api/user/theme` (AUTH-03) + token-remap theming (`html[data-theme=light]` invert `--color-ink-*`), Alpine `themeManager`, toggle di header backoffice+marketplace. **PENTING**: fix Sanctum SPA auth di `/api/*` (lihat gotcha #7).
- T-33 Kas-Akunting sync ✅ — `kas_sesi.sumber` (manual/legacy/carryover), jurnal buka kas `110-01/310-01`, selisih → `520-07` (bukan 520-06 lama yang salah), multi-kasir per-kasir, carryover prefill di PosKasir, `GET /api/akunting/kas-sesi` + tabel "Riwayat Sesi Kas" di laporan.
- T-34 Ribuan separator ✅ — direktif Alpine `x-format-number` (ADR 0011), 10 input nominal (POS, Akunting, CRM), model tetap angka bersih.
- T-35 Thermal print ✅ — client Web Bluetooth ESC/POS (58mm struk + 80mm faktur, fallback window.print) + backend queue `PrintThermalJob` (ESC/POS raw via `EscPosWriter`, artifact ke `storage/app/private/prints/` bila `THERMAL_PRINTER` kosong) + `POST /api/pos/transaksi/{id}/print` (POS-10).
- T-36 Customer create POS ✅ — `PelangganService` single source of truth (dipakai CRM-06 + Livewire POS + Livewire CRM).
- T-37 Birthday + tahan fix ✅ — `pelanggan.tanggal_lahir` (date, ADR 0012), input date di form CRM+POS, segment kampanye `birthday_month`/`birthday_day`, shortcut F6 → tahan, panel auto-open, bug `where('is_active')` di BroadcastService dihapus.
- Root cabang saat ini: `fase-10-t-31`. CAKUPAN KERJA ANTAR CABANG BELUM DI-PUSH; ada **6 git stash** di prod berisi pekerjaan uncommitted T-28..T-31 (`git stash list` di `/var/www/test.uteparts.id`). JANGAN `git stash clear`.

## ADR (docs/adr/)
0006 security=automated+manual review | 0007 PWA basic install | 0008 theme localStorage+DB (`theme_preference`) | 0009 thermal hybrid | 0010 breakpoints xxs 320/xs 375 | 0011 ribuan via Alpine x-format-number | 0012 birthday = date input

## TESTING
- `composer test` GAGAL di env ini (SQLite driver hilang). Pakai: `php8.5 artisan test --filter="PricingServiceTest|ServisStateMachineTest"` (9 lulus). Feature tests butuh SQLite driver — abaikan.

## GOTCHA TERBARU (2026-09-20)
1. **Login 419 di prod (HTTPS) SUDAH DIPERBAIKI**: `Auth::attempt()` regenerasi sesi → token CSRF baru. Fix: simpan token sesi sebelum attempt, pulihkan sesudahnya (`routes/web.php` route `login.post`). JANGAN hapus preserve CSRF ini.
2. **deploy.sh root cause "file hilang"**: baris `git stash --include-untracked` menghapus file untracked tiap deploy (manifest.json, offline.html hilang dari prod). **Baris itu SUDAH DIHAPUS**. PWA statis (manifest/offline/icons) SUDAH di-commit ke git prod (commit `5c1a762`). Kalau file hilang lagi → cek deploy.sh jangan ada stash ulang.
3. **ttyd dipindah 8080→8081** (terminal web, Basic auth, unit `/etc/systemd/system/ttyd.service` ExecStart `-p 8081`; cloudflared quick tunnel `--url http://localhost:8081`). 8080 = staging.
4. **SetAssetUrl pakai `getHttpHost()`** (termasuk port non-standar) di app/Http/Middleware/SetAssetUrl.php — kedua env. Jangan revert ke getHost() (asset staging pecah).
5. **ForceHttps skip IP**: middleware `ForceHttps.php` biarkan — IP prod tetap HTTP→HTTPS via nginx, staging FORCE_HTTPS=false.
6. Livewire computed property: WAIBS pass eksplisit di `render()` (`['propName' => $this->computedProp]`). Bare `$prop` di blade GAGAL — sudah dipraktikkan di PosKasir.
7. `@if` Blade di dalam `<script>` Alpine = syntax error → pakai `x-show` (sudah diperbaiki di backoffice layout branch switcher).
7. **Sanctum SPA auth di /api/\* JANGAN dihapus** (fix T-32): `bootstrap/app.php` → `$middleware->statefulApi()` + `$middleware->api(prepend: [EncryptCookies, AddQueuedCookiesToResponse, StartSession])`. Tanpa ini SEMUA `/api/*` `auth:sanctum` (select-branch, user/theme, pos, wms, dll) → 401 "Unauthenticated." walau sesi web valid. **SUDAH TERVERIFIKASI LIVE DI PROD (2026-09-20)**: login 302→/app/pos, theme POST 200 (DB=dark), select-branch 200.
8. **Logout via POST `/api/logout`** (auth:sanctum, sesi SPA) — butuh CSRF header + session cookie.
9. **deploy.sh 2 bug SUDAH DIPERBAIKI (commit `c47ef10`)**: (a) `mv public/build` gagal Permission denied saat `/tmp/ute-build-backup` sudah ada → sekarang `rm -rf` dulu; (b) early-exit "Tidak ada perubahan" TIDAK memulihkan build yang sudah dipindah → build bisa hilang dari prod → sekarang di-restore. Uji: jalankan ulang deploy.sh (HEAD==origin) → "Pulihkan public/build dari backup" → build tetap live.
10. **Auto-deploy DIMATIKAN (2026-09-20, permintaan user)**: `ute-parts-deploy.timer` stop+disable. Rilis prod MANUAL: `export DEPLOY_PATH=/var/www/test.uteparts.id PHP_BIN=/usr/bin/php8.5 QUEUE_NAME=ute-parts-queue HOME=/root && ./deploy.sh` — PASTIKAN `HOME=/root` (tanpa itu git "dubious ownership" → deploy mati senyap di step 1). Setelah deploy: migrate --force + npm run build + config/route/view:cache + supervisorctl restart ute-parts-queue:* (nama program pakai `:*`, `restart ute-parts-queue` saja → "no such process").

## PENGGUNA PROD (login ke /app).
`admin@uteparts.com` / `password` (super admin, role via seeder RoleUserSeeder). Yang lain: super-admin@uteparts.test, admin-toko@uteparts.test, kasir@uteparts.test, dsb (all demo, password `password`).

## NEXT ACTION (lanjut di branch baru)
```
cd /var/www/test.uteparts.id-staging   # atau prod bila mau rilis
git checkout -b fase-10-t-32           # (staging tanpa .git → copy manual jika deploy)
```
Kerjakan T-32 → tes → update `.scratch/fase-10/issues/T-32.md` (status done) + CHANGELOG.md → commit. Lanjut T-33..T-37 identik.