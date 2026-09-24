# Panduan Tooling QA (Gratis, Terminal-Only)

Semua tools di bawah **gratis (MIT/BSD)**, dijalankan via terminal, output teks untuk dikonsumsi AI/human. Tidak ada biaya token AI untuk review — AI hanya membaca output tool.

> **Catatan**: PHPInsights v2 memiliki bug circular-dependency di container (infinite loop) pada stack Laravel 11 ini. Sudah dicoba v2.15.0 dan v2.14.2 — keduanya crash. v1.x butuh PHP ≤7.4. **Dilewati sementara**; gunakan 3 tools inti: **Pint, PHPStan (Larastan), Deptrac, Composer Audit**.

---

## Ringkasan Perintah

| Perintah | Deskripsi |
|----------|-----------|
| `composer qa` | Jalankan seluruh pipeline QA (format → stan → deptrac → audit) |
| `composer qa:format` | Cek style code dengan Laravel Pint (--test, tidak menulis file) |
| `composer qa:stan` | Analisis statis PHPStan level 6 + baseline |
| `composer qa:stan:lowram` | PHPStan hemat RAM (256M, 1 proses) |
| `composer qa:stan:full` | PHPStan penuh (1G, 4 proses) untuk mesin kuat |
| `composer qa:deptrac` | Cek arsitektur dependensi antar modul |
| `composer qa:audit` | Scan kerentanan keamanan dependency (Composer Audit) |
| `./scripts/qa-runner.sh [perintah] [profil]` | Wrapper fleksibel RAM (lowram/normal/highram) |

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

## Wrapper Fleksibel RAM: `scripts/qa-runner.sh`

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

**ENV Override** (opsional, sebelum menjalankan):
```bash
export PHPSTAN_MEMORY_LIMIT=256M
export PHPSTAN_PROCESSES=1
export INSIGHTS_THREADS=1
./scripts/qa-runner.sh stan lowram
```

---

## One-Command Auto Runner: `scripts/qa-auto.sh` 🎯

**Satu perintah untuk semua lingkungan** — cek PHP, RAM, install deps, jalankan full pipeline, output log terstruktur untuk AI.

```bash
# Jalankan lengkap (auto-detect RAM, install deps jika perlu, clear cache, run all)
./scripts/qa-auto.sh
```

**Yang dilakukan otomatis:**
1. ✅ Cek PHP version (min 8.3, dari `composer.json`)
2. ✅ Deteksi total RAM → pilih profil `lowram` (<1GB) / `normal` (1-2GB) / `highram` (>2GB)
3. ✅ Cek Composer & `vendor/` → `composer install` jika belum ada
4. ✅ Clear Laravel caches (config, route, view)
5. ✅ **Pint** — format check
6. ✅ **PHPStan** — static analysis (level 6 + baseline, memory/proses sesuai profil)
7. ✅ **Deptrac** — architecture check
8. ✅ **Composer Audit** — vulnerability scan
9. ✅ Generate 3 file log di `qa-results/`:
   - `qa-YYYYMMDD-HHMMSS.log` — human-readable full output
   - `qa-YYYYMMDD-HHMMSS.json` — struktur data untuk AI parsing
   - `qa-YYYYMMDD-HHMMSS.summary.txt` — ringkasan + next actions untuk AI

**Profil RAM otomatis:**

| RAM | Profil | PHPStan Memory | PHPStan Parallel |
|-----|--------|----------------|------------------|
| < 1 GB | lowram | 256M | 1 (serial) |
| 1-2 GB | normal | 512M | 2 |
| > 2 GB | highram | 1G | 4 |

**Output JSON untuk AI:**
```json
{
  "timestamp": "20260924-132208",
  "hostname": "New-indra",
  "php_version": "8.4.25",
  "ram_mb": 955,
  "steps": [
    {"name": "pint-format-check", "status": "success", "duration_ms": 1698, "output": "..."},
    {"name": "phpstan", "status": "failed", "duration_ms": 10374, "output": "3 non-baseline errors..."},
    {"name": "deptrac", "status": "success", "duration_ms": 2968, "output": "0 violations..."},
    {"name": "composer-audit", "status": "success", "duration_ms": 1008, "output": "OK: No vulnerabilities"}
  ]
}
```

**AI Consumption:**
```bash
# 1. Jalankan auto-runner
./scripts/qa-auto.sh

# 2. Baca summary untuk next actions
cat qa-results/qa-*.summary.txt

# 3. Parse JSON untuk detail error
jq '.steps[] | select(.status=="failed")' qa-results/qa-*.json

# 4. Minta AI perbaiki error spesifik
# "Berikut output PHPStan dari qa-auto.sh, perbaiki 3 error method.childReturnType..."
```

---

## Alur Kerja Rekomendasi

### Development (local)
```bash
# Sebelum commit
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
      - run: composer qa
        env:
          PHPSTAN_MEMORY_LIMIT: 512M
          PHPSTAN_PROCESSES: 2
```

### AI-Assisted Fix Loop
```bash
# 1. Jalankan tool, simpan output
composer qa:stan 2>&1 | tee phpstan-output.txt

# 2. Berikan output ke AI
# "Berikut output PHPStan, perbaiki error di file X: ..."

# 3. Setelah fix, jalankan ulang
composer qa:stan
```

---

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| PHPStan OOM (killed) | Kurangi `memory-limit`, set `--processes=1`, gunakan `qa:stan:lowram` |
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
| `scripts/qa-runner.sh` | Wrapper CLI fleksibel RAM |
| `composer.json` → `scripts.qa*` | Entry point `composer qa` |

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
- [ ] Semua file config baru (`phpstan.neon`, `phpstan-baseline.neon`, `deptrac.php`, `scripts/qa-runner.sh`, `docs/QA-TOOLS.md`) di-commit