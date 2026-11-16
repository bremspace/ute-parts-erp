#!/usr/bin/env bash
set -e

PROJECT_DIR="/var/www/ute-parts-erp"

if [ -d "$PROJECT_DIR" ]; then
    cd "$PROJECT_DIR"
else
    echo "❌ Error: Direktori project $PROJECT_DIR tidak ditemukan!" >&2
    exit 1
fi

echo "📥 Menjalankan git pull di $PROJECT_DIR..."
git pull

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
