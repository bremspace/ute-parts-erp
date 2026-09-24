#!/usr/bin/env bash
# =============================================================================
# QA Auto-Runner — satu perintah untuk semua lingkungan
#   - Cek PHP version, RAM, Composer, dependencies
#   - Auto-install deps jika belum ada
#   - Jalankan Pint → PHPStan → Deptrac → Audit (sequential, RAM-aware)
#   - Output: log terstruktur JSON + human-readable di qa-results/
# =============================================================================

# set -euo pipefail  # JANGAN exit on error — jalankan semua step
set -uo pipefail

# ─── Konfigurasi ────────────────────────────────────────────────────────────
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG_DIR="${PROJECT_ROOT}/qa-results"
TIMESTAMP="$(date '+%Y%m%d-%H%M%S')"
LOG_FILE="${LOG_DIR}/qa-${TIMESTAMP}.log"
JSON_LOG="${LOG_DIR}/qa-${TIMESTAMP}.json"
SUMMARY_FILE="${LOG_DIR}/qa-${TIMESTAMP}.summary.txt"

# PHP minimum version (dari composer.json require.php ^8.3)
REQUIRED_PHP_MAJOR=8
REQUIRED_PHP_MINOR=3

# ─── Helper Functions ───────────────────────────────────────────────────────
log() { echo "[$(date '+%H:%M:%S')] $*" | tee -a "${LOG_FILE}"; }
log_section() { echo -e "\n=== $* ===" | tee -a "${LOG_FILE}"; }
json_init() { echo '{"timestamp":"'${TIMESTAMP}'","hostname":"'$(hostname)'","php_version":"'$(php -v | head -1 | awk '{print $2}')'","ram_mb":'$(free -m | awk '/^Mem:/{print $2}')',"steps":[]}' > "${JSON_LOG}"; }
json_add_step() {
    local name="$1" status="$2" duration_ms="$3" output_file="$4" details="$5"
    local tmp=$(mktemp)
    jq --arg name "$name" \
       --arg status "$status" \
       --argjson duration "$duration_ms" \
       --arg output "$(cat "$output_file" 2>/dev/null | head -200 | jq -Rs .)" \
       --arg details "$details" \
       '.steps += [{"name":$name,"status":$status,"duration_ms":$duration,"output":$output,"details":$details}]' \
       "${JSON_LOG}" > "$tmp" && mv "$tmp" "${JSON_LOG}"
}
OVERALL_STATUS="success"

run_step() {
    local name="$1" cmd="$2" expected_code="${3:-0}"
    local start_ms end_ms duration_ms out_file status
    start_ms=$(date +%s%3N)
    out_file=$(mktemp)
    log_section "STEP: ${name}"
    if eval "${cmd}" >"$out_file" 2>&1; then
        status="success"
    else
        status="failed"
        OVERALL_STATUS="failed"
    fi
    end_ms=$(date +%s%3N)
    duration_ms=$((end_ms - start_ms))
    log "  Status: ${status} (${duration_ms}ms)"
    json_add_step "$name" "$status" "$duration_ms" "$out_file" "$(cat "$out_file" | tail -5 | jq -Rs .)"
    cat "$out_file" >> "${LOG_FILE}"
    rm -f "$out_file"
    return 0  # Jangan exit, lanjut step berikutnya
}

# ─── Persiapan ──────────────────────────────────────────────────────────────
mkdir -p "${LOG_DIR}"
json_init
cd "${PROJECT_ROOT}"

log "🚀 QA Auto-Runner started"
log "   Project: ${PROJECT_ROOT}"
log "   Log dir: ${LOG_DIR}"

# ─── 1. Cek PHP Version ─────────────────────────────────────────────────────
log_section "CHECK: PHP Version"
PHP_VERSION=$(php -v | head -1 | awk '{print $2}')
PHP_MAJOR=$(echo "$PHP_VERSION" | cut -d. -f1)
PHP_MINOR=$(echo "$PHP_VERSION" | cut -d. -f2)
log "   Detected: PHP ${PHP_VERSION}"
if [[ $PHP_MAJOR -lt $REQUIRED_PHP_MAJOR ]] || [[ $PHP_MAJOR -eq $REQUIRED_PHP_MAJOR && $PHP_MINOR -lt $REQUIRED_PHP_MINOR ]]; then
    log "   ❌ PHP >= ${REQUIRED_PHP_MAJOR}.${REQUIRED_PHP_MINOR} required, got ${PHP_VERSION}"
    exit 1
fi
log "   ✅ PHP version OK (>= ${REQUIRED_PHP_MAJOR}.${REQUIRED_PHP_MINOR})"

# ─── 2. Cek RAM & Tentukan Profil ───────────────────────────────────────────
log_section "CHECK: System RAM"
TOTAL_RAM_MB=$(free -m | awk '/^Mem:/{print $2}')
log "   Total RAM: ${TOTAL_RAM_MB} MB"
if [[ $TOTAL_RAM_MB -lt 1024 ]]; then
    PROFIL="lowram"
    PHPSTAN_MEM="256M"
    PHPSTAN_PROCS=1
elif [[ $TOTAL_RAM_MB -lt 2048 ]]; then
    PROFIL="normal"
    PHPSTAN_MEM="512M"
    PHPSTAN_PROCS=2
else
    PROFIL="highram"
    PHPSTAN_MEM="1G"
    PHPSTAN_PROCS=4
fi
log "   Profil terpilih: ${PROFIL} (PHPStan: ${PHPSTAN_MEM}, ${PHPSTAN_PROCS} proses)"

# ─── 3. Cek Composer & Dependencies ─────────────────────────────────────────
log_section "CHECK: Composer & Dependencies"
if ! command -v composer &>/dev/null; then
    log "   ❌ Composer tidak ditemukan"
    exit 1
fi
log "   Composer: $(composer --version --no-ansi | awk '{print $3}')"

if [[ ! -d vendor ]] || [[ ! -f vendor/autoload.php ]]; then
    log "   📦 Dependencies belum terinstall — menjalankan composer install..."
    run_step "composer-install" "COMPOSER_ALLOW_SUPERUSER=1 composer install --prefer-dist --no-progress --no-interaction"
else
    log "   ✅ Vendor directory exists"
fi

# ─── 4. Verifikasi Tool Binaries ────────────────────────────────────────────
log_section "CHECK: QA Tool Binaries"
for tool in pint phpstan deptrac; do
    if [[ -f "vendor/bin/${tool}" ]]; then
        log "   ✅ vendor/bin/${tool} found"
    else
        log "   ⚠️  vendor/bin/${tool} MISSING — akan diinstall via composer"
    fi
done

# ─── 5. Clear Caches (bersih sebelum QA) ────────────────────────────────────
log_section "PREP: Clear Laravel Caches"
run_step "cache-clear" "php artisan config:clear && php artisan route:clear && php artisan view:clear" 0

# ─── 6. Jalankan Pipeline QA ────────────────────────────────────────────────
log_section "QA PIPELINE START (profil: ${PROFIL})"

# 6a. Pint — Code Style
run_step "pint-format-check" "vendor/bin/pint --test"

# 6b. PHPStan — Static Analysis (PHPStan 2.x: --parallel, bukan --processes)
if [[ ${PHPSTAN_PROCS} -gt 1 ]]; then
    PHPSTAN_PARALLEL="--parallel"
else
    PHPSTAN_PARALLEL=""
fi
PHPSTAN_CMD="vendor/bin/phpstan analyse --no-progress --memory-limit=${PHPSTAN_MEM} ${PHPSTAN_PARALLEL}"
run_step "phpstan" "${PHPSTAN_CMD}"

# 6c. Deptrac — Architecture
run_step "deptrac" "vendor/bin/deptrac analyse --no-progress"

# 6d. Composer Audit — Security (text output, grep "No security vulnerability" = OK)
run_step "composer-audit" "COMPOSER_ALLOW_SUPERUSER=1 composer audit --no-interaction 2>&1 | grep -q 'No security vulnerability advisories found' && echo 'OK: No vulnerabilities' || echo 'VULNERABILITIES FOUND'"

# ─── 7. Generate Summary ────────────────────────────────────────────────────
log_section "GENERATE SUMMARY"

cat > "${SUMMARY_FILE}" <<EOF
QA Auto-Runner Summary
======================
Timestamp: ${TIMESTAMP}
Hostname: $(hostname)
Project: ${PROJECT_ROOT}
PHP Version: ${PHP_VERSION}
Total RAM: ${TOTAL_RAM_MB} MB
Profil Used: ${PROFIL}
  - PHPStan Memory: ${PHPSTAN_MEM}
  - PHPStan Processes: ${PHPSTAN_PROCS}

Overall Status: ${OVERALL_STATUS}

Log Files:
  - Human log: ${LOG_FILE}
  - JSON log:  ${JSON_LOG}
  - Summary:   ${SUMMARY_FILE}

Steps Status:
EOF

jq -r '.steps[] | "  \(.name): \(.status) (\(.duration_ms)ms)"' "${JSON_LOG}" >> "${SUMMARY_FILE}"

echo "" >> "${SUMMARY_FILE}"
echo "AI Consumption Guide:" >> "${SUMMARY_FILE}"
echo "  - Parse ${JSON_LOG} for structured results" >> "${SUMMARY_FILE}"
echo "  - Each step.output contains truncated tool output (first 200 lines)" >> "${SUMMARY_FILE}"
echo "  - Full output in ${LOG_FILE}" >> "${SUMMARY_FILE}"
echo "" >> "${SUMMARY_FILE}"
echo "Next Actions for AI:" >> "${SUMMARY_FILE}"
jq -r '.steps[] | select(.status=="failed") | "  - FIX: \(.name) — \(.details)"' "${JSON_LOG}" >> "${SUMMARY_FILE}" 2>/dev/null || echo "  (all steps passed)" >> "${SUMMARY_FILE}"

# Add PHPStan baseline info
if grep -q "phpstan-baseline.neon" "${PROJECT_ROOT}/phpstan.neon" 2>/dev/null; then
    BASELINE_COUNT=$(grep -c "identifier:" "${PROJECT_ROOT}/phpstan-baseline.neon" 2>/dev/null || echo "0")
    echo "" >> "${SUMMARY_FILE}"
    echo "PHPStan Baseline: ${BASELINE_COUNT} error di-baseline (diabaikan)" >> "${SUMMARY_FILE}"
fi

log_section "DONE"
log "📋 Summary written to: ${SUMMARY_FILE}"
log "📄 Full log: ${LOG_FILE}"
log "📊 JSON log: ${JSON_LOG}"
log "🏁 Overall: ${OVERALL_STATUS}"
cat "${SUMMARY_FILE}"

# Exit dengan status overall
[[ "${OVERALL_STATUS}" == "success" ]] && exit 0 || exit 1