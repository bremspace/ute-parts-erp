#!/usr/bin/env bash
set -e

PROJECT_DIR="/var/www/ute-parts-erp"

if [ -d "$PROJECT_DIR" ]; then
    cd "$PROJECT_DIR"
else
    echo "❌ Error: Direktori project $PROJECT_DIR tidak ditemukan!" >&2
    exit 1
fi

echo "📥 Sinkronisasi Git di $PROJECT_DIR..."
CURRENT_BRANCH="$(git branch --show-current 2>/dev/null || echo "")"

if git fetch origin; then
    case "$CURRENT_BRANCH" in
        brem)
            PULL_BRANCHES=("main" "Unas" "brem")
            ;;
        Unas)
            PULL_BRANCHES=("main" "brem" "Unas")
            ;;
        *)
            PULL_BRANCHES=("main")
            [ -n "$CURRENT_BRANCH" ] && PULL_BRANCHES+=("$CURRENT_BRANCH")
            ;;
    esac

    for br in "${PULL_BRANCHES[@]}"; do
        if git show-ref --verify --quiet "refs/remotes/origin/$br"; then
            echo "  ↻ Pull origin/$br -> $CURRENT_BRANCH..."
            if ! git pull --no-rebase --no-edit --autostash origin "$br"; then
                echo "⚠️  Conflict/gagal saat pull origin/$br. Lanjut buka Antigravity untuk rekonsiliasi." >&2
                break
            fi
        fi
    done
else
    echo "⚠️  git fetch origin gagal (offline?), lanjut buka Antigravity." >&2
fi

echo "🚀 Menyalakan Antigravity..."
export PATH="/root/.local/bin:/root/.gemini/antigravity-cli/bin:/usr/local/bin:$PATH"

if command -v agy >/dev/null 2>&1; then
    exec agy "$@"
elif command -v antigravity >/dev/null 2>&1; then
    exec antigravity "$@"
elif [ -x /root/.local/bin/agy ]; then
    exec /root/.local/bin/agy "$@"
else
    echo "❌ Error: Antigravity CLI binary tidak ditemukan!" >&2
    exit 1
fi
