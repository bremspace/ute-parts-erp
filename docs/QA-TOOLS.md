# Panduan Tooling QA (Gratis, Terminal-Only)

Semua tools di bawah **gratis (MIT/BSD)**, dijalankan via terminal, output teks untuk dikonsumsi AI/human. Tidak ada biaya token AI untuk review — AI hanya membaca output tool.

> **Catatan**: PHPInsights v2 memiliki bug circular-dependency di container (infinite loop) pada stack Laravel 11 ini. Sudah dicoba v2.15.0 dan v2.14.2 — keduanya crash. v1.x butuh PHP ≤7.4. **Dilewati sementara**; gunakan 3 tools inti: **Pint, PHPStan (Larastan), Deptrac, Composer Audit**.

---

## Ringkasan Perintah

| Perintah | Berlaku di | Deskripsi |
|----------|-----------|-----------|
| `composer qa-auto` | Windows, Linux, macOS | **Perintah utama** — cek env, deteksi RAM, install deps, pipeline QA, tulis log |
| `php scripts/qa-auto.php` | Windows, Linux, macOS | Sama seperti di atas, tanpa composer |
| `composer qa` | Windows, Linux, macOS | Pipeline QA saja (format → stan → deptrac → audit) |
| `composer qa:format` | Windows, Linux, macOS | Cek style code dengan Laravel Pint (--test, tidak menulis file) |
| `composer qa:stan` | Windows, Linux, macOS | Analisis statis PHPStan level 6 + baseline |
| `composer qa:stan:lowram` | Windows, Linux, macOS | PHPStan hemat RAM (256M, serial) |
| `composer qa:stan:full` | Windows, Linux, macOS | PHPStan penuh (1G, paralel) untuk mesin kuat |
| `composer qa:deptrac` | Windows, Linux, macOS | Cek arsitektur dependensi antar modul |
| `composer qa:audit` | Windows, Linux, macOS | Scan kerentanan keamanan dependency (Composer Audit) |
| `./scripts/qa-runner.sh [perintah] [profil]` | Linux, macOS | Wrapper fleksibel RAM (padanan `.php` ada di bawah) |

> Untuk **Windows**, gunakan `composer qa-auto` atau `php scripts/qa-auto.php`. Script `.sh` hanya untuk Linux/macOS.

---

## Detail Tiap Tool

### 1. Laravel Pint — Code Style (PSR-12 + Laravel preset)

```bash
# Cek saja (exit code ≠ 0 kalau ada yang tidak rapi)
vendor/bin/pint --test

# Perbaiki otomatis
vendor/bin/pint
```

- **Output**: Daftar file yang diformat / sudah rapi.
- **RAM**: Ringan (<50MB).
- **Gunakan saat**: Sebelum commit, di CI.

---

### 2. PHPStan (Larastan) — Static Analysis Level 6

```bash
# Standar (pakai baseline, memory 512M, 2 proses paralel)
vendor/bin/phpstan analyse --no-progress --memory-limit=512M

# RAM <1GB (serial, 256M)
vendor/bin/phpstan analyse --no-progress --memory-limit=256M --processes=1

# RAM >2GB (cepat, 1G, 4 proses)
vendor/bin/phpstan analyse --no-progress --memory-limit=1G --processes=4
```

- **Konfigurasi**: `phpstan.neon` (level 6, path `app/`, baseline `phpstan-baseline.neon`).
- **Baseline**: 1122 error existing terecord di `phpstan-baseline.neon` — **hanya error BARU** yang dilaporkan.
- **Error non-baseline** (3 error covariance anonymous class) → perlu perbaikan manual.
- **Output**: Tabel error dengan file:line, kode error, saran perbaikan.
- **RAM**: 256M–1GB tergantung flag.
- **AI Consumption**: Copy-paste tabel error ke AI → minta perbaikan per file.

---

### 3. Deptrac — Dependency Architecture

```bash
# Laporan console
vendor/bin/deptrac analyse --no-progress

# Visualisasi graph (Mermaid → paste ke mermaid.live)
vendor/bin/deptrac analyse --formatter=mermaid

# Visualisasi Graphviz (butuh `dot`)
vendor/bin/deptrac analyse --formatter=graphviz --output=deptrac.dot
dot -Tpng deptrac.dot -o deptrac.png
```

- **Konfigurasi**: `deptrac.php` — layer per modul + ruleset baseline edge nyata.
- **Baseline**: 0 violation pada edge yang sudah ada (matrix 52 pasangan source→target). Edge BARU yang tidak terdaftar → violation.
- **Known debt (edge sah tapi smell, di-allow baseline tapi perlu refactor bertahap)**:
  - `Workflow → Pos/Wms` : Observer + ApprovalService memanggil domain service langsung (shotgun surgery). Ideal: event/interface.
  - `Akunting → Pos` : Pakai `KasSesiState` di controller (feature envy).
  - `Marketplace → Pos` : OrderService/PaymentController memakai model Pos.
- **Output**: Daftar violation (kalau ada) + summary uncovered/allowed.
- **RAM**: Ringan (<100MB).
- **AI Consumption**: Copy violation list → minta refactor edge spesifik.

---

### 4. Composer Audit — Vulnerability Scan

```bash
composer audit --no-interaction
```

- **Output**: Daftar advisory (CVE) + versi terkena + rekomendasi upgrade.
- **RAM**: Sangat ringan.
- **Frekuensi**: Setiap `composer update` / mingguan di CI.

---

## Perintah Utama (Universal): `scripts/qa-auto.php`

Runner ini berbasis PHP, bukan bash, sehingga **perintah yang sama** jalan di Windows, Linux, dan macOS. Windows tidak memerlukan bash, Git Bash, maupun WSL.

### Cara menjalankan

Selalu jalankan dari **folder root project** (tempat `composer.json` berada).

| OS | Perintah |
|----|----------|
| Windows (PowerShell / CMD) | `composer qa-auto` |
| Windows (PowerShell / CMD) | `php scripts/qa-auto.php` |
| Linux | `composer qa-auto` |
| Linux | `php scripts/qa-auto.php` |
| macOS | `composer qa-auto` |
| macOS | `php scripts/qa-auto.php` |

Kedua perintah itu identik — `composer qa-auto` hanyalah alias yang memanggil `php scripts/qa-auto.php`.

### Yang dilakukan otomatis

1. Cek versi PHP (minimal 8.3, mengikuti `composer.json`) — berhenti dengan pesan jelas bila tidak sesuai
2. Deteksi total RAM → pilih profil `lowram` / `normal` / `highram`
3. Cek Composer dan `vendor/autoload.php` → jalankan `composer install` bila dependency belum ada
4. Verifikasi binary `vendor/bin/pint`, `phpstan`, `deptrac`
5. Clear cache Laravel (config, route, view)
6. Jalankan pipeline: **Pint** → **PHPStan** → **Deptrac** → **Composer Audit**
7. Tulis 3 file log di `qa-results/`

Pipeline tidak berhenti di tengah bila satu step gagal. Semua step tetap dijalankan, lalu exit code menyatakan ada atau tidaknya kegagalan.

### Profil RAM otomatis

| RAM | Profil | PHPStan memory | PHPStan paralel |
|-----|--------|----------------|-----------------|
| < 1 GB | `lowram` | 256M | tidak (serial) |
| 1–2 GB | `normal` | 512M | ya (2 proses) |
| > 2 GB | `highram` | 1G | ya (4 proses) |

Deteksi RAM membaca `/proc/meminfo` langsung di Linux tanpa memanggil program eksternal. Bila RAM tidak terdeteksi, runner memakai profil paling aman (`lowram`) dan memberi tahu. Untuk memaksa nilai tertentu, set environment variable:

```powershell
# Windows PowerShell
$env:SYSTEM_TOTAL_MEMORY_MB="2048"; php scripts/qa-auto.php
```

```bash
# Linux / macOS
SYSTEM_TOTAL_MEMORY_MB=2048 php scripts/qa-auto.php
```

### Output log

```
qa-results/
├── qa-<timestamp>.log           # output lengkap, human-readable
├── qa-<timestamp>.json          # terstruktur, siap di-parse AI
└── qa-<timestamp>.summary.txt   # ringkasan status + Next Actions for AI
```

Ketiganya memakai newline `\n` dan encoding UTF-8, sehingga konsisten di semua OS (tidak ada `\r\n` yang mengganggu diff atau parsing).

Struktur JSON:

```json
{
  "timestamp": "20260924-153908",
  "hostname": "New-indra",
  "os": "Linux",
  "php_version": "8.4.25",
  "ram_mb": 955,
  "profile": "lowram",
  "phpstan_memory": "256M",
  "phpstan_parallel": false,
  "overall_status": "failed",
  "steps": [
    {"name": "cache-clear", "status": "success", "duration_ms": 2291, "output": "..."},
    {"name": "pint-format-check", "status": "success", "duration_ms": 2623, "output": "..."},
    {"name": "phpstan", "status": "failed", "duration_ms": 16546, "output": "3 non-baseline errors..."},
    {"name": "deptrac", "status": "success", "duration_ms": 3167, "output": "0 violations..."},
    {"name": "composer-audit", "status": "success", "duration_ms": 1533, "output": "No vulnerabilities"}
  ]
}
```

`steps[].output` dibatasi 200 baris pertama agar file tidak membengkak. Output lengkap tersedia di file `.log`.

### Cara dibantu AI

```bash
# 1. Jalankan auto-runner
php scripts/qa-auto.php

# 2. Baca ringkasan — berisi status tiap step + Next Actions for AI
cat qa-results/qa-*.summary.txt
```

Contoh isi bagian Next Actions:

```
Next Actions for AI:
  - FIX: phpstan — [ERROR] Found 3 errors
  - RETRY: composer-audit — step tidak tervalidasi (kemungkinan masalah jaringan, bukan bug)
```

### Arti status tiap step

| Status | Arti |
|--------|------|
| `success` | Step berjalan dan hasilnya bersih |
| `failed` | Step berjalan dan menemukan masalah nyata — perlu diperbaiki |
| `inconclusive` | Step tidak bisa tervalidasi, biasanya karena jaringan (contoh: `composer audit` gagal menghubungi packagist.org). Bukan tanda bug — cukup ulangi nanti |

Untuk parsing terstruktur (punya `jq`):

```bash
jq '.steps[] | select(.status=="failed")' qa-results/qa-*.json
```

Untuk user Windows tanpa `jq`, cukup kirimkan isi `qa-*.summary.txt` atau `qa-*.json` ke AI secara langsung.

---

## Runner khusus Linux/macOS (opsional)

Dua script shell tetap disediakan untuk user Unix/macOS yang lebih terbiasa dengan shell script. Keduanya **tidak** jalan di Windows tanpa Git Bash atau WSL —padanannya sudah tercakup oleh `qa-auto.php` di atas.

### `scripts/qa-auto.sh`

Padanan `qa-auto.php`:

```bash
# Jalankan lengkap
./scripts/qa-auto.sh

# Alias yang sama
composer qa-auto
```

### `scripts/qa-runner.sh`

Wrapper fleksibel per tool dengan profil RAM yang dipilih manual:

```bash
# Default: profil lowram (<1GB), jalankan semua
./scripts/qa-runner.sh

# Hanya PHPStan dengan profil normal (1-2GB)
./scripts/qa-runner.sh stan normal

# Semua tool dengan profil highram (>2GB)
./scripts/qa-runner.sh all highram

# Perintah tersedia: all | stan | deptrac | insights | audit | format
# Profil tersedia: lowram | normal | highram
```

ENV override (opsional):

```bash
export PHPSTAN_MEMORY_LIMIT=256M
export PHPSTAN_PROCESSES=1
export INSIGHTS_THREADS=1
./scripts/qa-runner.sh stan lowram
```

Untuk menjalankan satu tool saja tanpa wrapper, perintah `composer qa:stan`, `composer qa:deptrac`, dan sejenisnya tetap dapat dipakai langsung di semua OS.

---

## Alur Kerja Rekomendasi

### Development (local)
```bash
# Cara tercepat — cek semuanya sekaligus, semua OS
php scripts/qa-auto.php

# Atau jalankan per tool (semua OS)
composer qa:format       # cek style
composer qa:stan         # cek tipe (pakai baseline)
composer qa:deptrac      # cek arsitektur
# jika semua hijau → commit
```

### CI Pipeline (GitHub Actions / GitLab CI)
```yaml
# .github/workflows/qa.yml
jobs:
  qa:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: mbstring, xml, pdo, pdo_mysql
          coverage: none
      - run: composer install --prefer-dist --no-progress
      - run: composer qa-auto
      - name: Upload QA logs
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: qa-results
          path: qa-results/
```

### AI-Assisted Fix Loop
```bash
# 1. Jalankan auto-runner (semua OS) — log otomatis tersimpan di qa-results/
php scripts/qa-auto.php

# 2. Berikan output ke AI
# "Berikut output PHPStan dari qa-auto, perbaiki error di file X: ..."
# Carrier: qa-results/qa-<timestamp>.summary.txt atau .json

# 3. Setelah fix, jalankan ulang
php scripts/qa-auto.php
```

---

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Script `.sh` tidak jalan di Windows | Gunakan `composer qa-auto` atau `php scripts/qa-auto.php` — keduanya universal tanpa bash/Git Bash/WSL |
| `php` tidak dikenali di Windows | PHP belum ada di PATH. Pasang PHP (contoh: [php.net](https://php.net/downloads) atau XAMPP/Laragon), lalu tambahkan folder PHP ke PATH |
| `composer` tidak dikenali di Windows | Pasang [Composer](https://getcomposer.org/Composer-Setup.exe), atau jalankan `php composer.phar qa-auto` bila memakai file lokal |
| RAM tidak terdeteksi otomatis | Set env `SYSTEM_TOTAL_MEMORY_MB` (PowerShell: `$env:SYSTEM_TOTAL_MEMORY_MB="2048"`) |
| PHPStan OOM (killed) | Kurangi `memory-limit`, jalankan tanpa `--parallel`, atau pakai `composer qa:stan:lowram` |
| PHPStan baseline tidak terbaca | Pastikan `phpstan-baseline.neon` ada di root & di-include di `phpstan.neon` |
| Deptrac violation baru | Tambah edge ke `deptrac.php` ruleset layer ybs (`->accesses($targetLayer)`) |
| Pint gagal di file generated | Exclude di `pint.json` (`"exclude": ["bootstrap/cache/*", "storage/*"]`) |
| Composer audit deprecation noise | Normal di PHP 8.4, abaikan; fokus ke "No security vulnerability advisories found" |
| PHPInsights crash | Known bug v2 di stack ini — gunakan tool lain, cek ulang nanti |

---

## File Konfigurasi Penting

| File | Fungsi |
|------|--------|
| `phpstan.neon` | Config PHPStan level 6 + baseline include |
| `phpstan-baseline.neon` | 1122 error existing (auto-generated) — **commit file ini** |
| `deptrac.php` | Layer & ruleset arsitektur modul |
| `scripts/qa-auto.php` | Runner QA universal (Windows/Linux/macOS) |
| `scripts/qa-auto.sh` | Padanan `.php` khusus Linux/macOS |
| `scripts/qa-runner.sh` | Wrapper CLI fleksibel RAM (Linux/macOS) |
| `composer.json` → `scripts.qa*` | Entry point `composer qa` dan `composer qa-auto` |

---

## Maintenance Berkala

- **Mingguan**: `composer qa:audit` + `composer outdated --direct`
- **Bulanan**: Naikkan PHPStan level (6→7→8) bertahap setelah baseline bersih
- **Saat refactor modul**: Update `deptrac.php` ruleset (tambah/hapus edge sadar)
- **Baseline PHPStan**: Regenerate kalau error non-baseline sudah diperbaiki banyak:
  ```bash
  vendor/bin/phpstan analyse --generate-baseline --memory-limit=512M
  ```

---

## Status Tool Saat Ini (2026-09-24)

| Tool | Versi | Status | Catatan |
|------|-------|--------|---------|
| Laravel Pint | ^1.27 | ✅ Stabil | Sudah dipakai di project |
| PHPStan | 2.2.15 | ✅ Stabil | Baseline 1122 error, 3 non-baseline |
| Larastan | v3.12.2 | ✅ Stabil | Extension Laravel untuk PHPStan |
| Deptrac | ^4.7 | ✅ Stabil | Baseline 0 violation (52 edge diizinkan) |
| PHPInsights | ^2.15 → ^2.14.2 | ❌ Broken | Circular container bug, skip sementara |
| Composer Audit | built-in | ✅ Stabil | No vulnerabilities |

---

## Commit & Push Checklist

Sebelum push:
- [ ] `composer qa:format` → PASS
- [ ] `composer qa:stan` → PASS (hanya baseline error)
- [ ] `composer qa:deptrac` → 0 violation
- [ ] `composer qa:audit` → No vulnerabilities
- [ ] `php artisan view:cache` → OK
- [ ] Test fitur terkait (`vendor/bin/phpunit --filter=NamaTest`) → PASS
- [ ] Semua file config baru (`phpstan.neon`, `phpstan-baseline.neon`, `deptrac.php`, `scripts/qa-auto.php`, `docs/QA-TOOLS.md`) di-commit