#!/usr/bin/env bash
# QA Tool Runner — fleksibel untuk RAM <1GB atau >1GB
# Pakai: ./scripts/qa-runner.sh [perintah] [profil]
#   perintah: all | stan | deptrac | insights | audit | format
#   profil: lowram (default, <1GB) | normal (1-2GB) | highram (>2GB)
#
# ENV override:
#   PHPSTAN_MEMORY_LIMIT=512M  # atau 256M/1G
#   PHPSTAN_PROCESSES=1        # 1=serial, >1=paralel
#   INSIGHTS_THREADS=1         # 1=serial

set -euo pipefail

CMD="${1:-all}"
PROFIL="${2:-lowram}"

case "$PROFIL" in
    lowram)
        export PHPSTAN_MEMORY_LIMIT="${PHPSTAN_MEMORY_LIMIT:-256M}"
        export PHPSTAN_PROCESSES="${PHPSTAN_PROCESSES:-1}"
        INSIGHTS_THREADS="${INSIGHTS_THREADS:-1}"
        ;;
    normal)
        export PHPSTAN_MEMORY_LIMIT="${PHPSTAN_MEMORY_LIMIT:-512M}"
        export PHPSTAN_PROCESSES="${PHPSTAN_PROCESSES:-2}"
        INSIGHTS_THREADS="${INSIGHTS_THREADS:-2}"
        ;;
    highram)
        export PHPSTAN_MEMORY_LIMIT="${PHPSTAN_MEMORY_LIMIT:-1G}"
        export PHPSTAN_PROCESSES="${PHPSTAN_PROCESSES:-4}"
        INSIGHTS_THREADS="${INSIGHTS_THREADS:-4}"
        ;;
    *)
        echo "Profil tidak dikenal: $PROFIL (lowram|normal|highram)"
        exit 1
        ;;
esac

cd "$(dirname "$0")/.." || exit 1

run_stan() {
    echo "=== PHPStan (memory=$PHPSTAN_MEMORY_LIMIT, processes=$PHPSTAN_PROCESSES) ==="
    vendor/bin/phpstan analyse --no-progress \
        --memory-limit="$PHPSTAN_MEMORY_LIMIT" \
        --processes="$PHPSTAN_PROCESSES"
}

run_deptrac() {
    echo "=== Deptrac ==="
    vendor/bin/deptrac analyse --no-progress
}

run_insights() {
    echo "=== PHPInsights (threads=$INSIGHTS_THREADS) ==="
    vendor/bin/phpinsights analyse app \
        --no-interaction \
        --min-quality=0 --min-complexity=0 --min-architecture=0 --min-style=0 \
        --threads="$INSIGHTS_THREADS"
}

run_audit() {
    echo "=== Composer Audit ==="
    composer audit --no-interaction
}

run_format() {
    echo "=== Pint (format check) ==="
    vendor/bin/pint --test
}

case "$CMD" in
    all)
        run_format
        run_stan
        run_deptrac
        run_insights
        run_audit
        ;;
    stan)
        run_stan
        ;;
    deptrac)
        run_deptrac
        ;;
    insights)
        run_insights
        ;;
    audit)
        run_audit
        ;;
    format)
        run_format
        ;;
    *)
        echo "Perintah: all | stan | deptrac | insights | audit | format"
        exit 1
        ;;
esac