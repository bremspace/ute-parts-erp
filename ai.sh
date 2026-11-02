#!/usr/bin/env bash
# ==============================================================================
# Ute Parts ERP — OpenCode & AI Assistant Launcher (9router + OMO-Slim + MCP)
# Menjalankan 9router di background jika belum aktif, lalu membuka OpenCode
# dengan plugin oh-my-opencode-slim, skills, dan codebase-memory-mcp siap pakai.
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# 1. Periksa & Nyalakan 9router di background (Port 20128)
if ! curl -s --max-time 1 http://127.0.0.1:20128/v1/models >/dev/null 2>&1; then
    echo "⟳ Menyalakan service 9router di background (port 20128)..."
    if command -v systemctl >/dev/null 2>&1 && systemctl is-enabled 9router >/dev/null 2>&1; then
        sudo systemctl start 9router >/dev/null 2>&1 || true
    fi
    if ! curl -s --max-time 1 http://127.0.0.1:20128/v1/models >/dev/null 2>&1; then
        if command -v 9router >/dev/null 2>&1; then
            nohup 9router -p 20128 --tray --skip-update >/dev/null 2>&1 &
        fi
    fi
    sleep 1
fi

# 2. Periksa apakah OpenCode & AI stack sudah terkonfigurasi
if ! command -v opencode >/dev/null 2>&1 && [ ! -f "$HOME/.opencode/bin/opencode" ] && [ ! -f "/root/.opencode/bin/opencode" ]; then
    echo "⟳ Menyiapkan stack AI mandiri (OpenCode + OMO-Slim + MCP)..."
    bash "${SCRIPT_DIR}/deploy/setup-ai-dev-stack.sh" >/dev/null 2>&1 || true
fi

# 3. Temukan binary OpenCode
OPENCODE_BIN=""
if command -v opencode >/dev/null 2>&1; then
    OPENCODE_BIN="$(which opencode)"
elif [ -x "$HOME/.opencode/bin/opencode" ]; then
    OPENCODE_BIN="$HOME/.opencode/bin/opencode"
elif [ -x "/root/.opencode/bin/opencode" ]; then
    OPENCODE_BIN="/root/.opencode/bin/opencode"
elif [ -x "/usr/local/bin/opencode" ]; then
    OPENCODE_BIN="/usr/local/bin/opencode"
else
    echo "❌ Binary opencode belum ditemukan. Jalankan: bash deploy/setup-ai-dev-stack.sh"
    exit 1
fi

# 4. Sinkronisasi AGENTS.md ke konfigurasi OpenCode
CONFIG_DIR="$HOME/.config/opencode"
mkdir -p "$CONFIG_DIR"
if [ -f "${SCRIPT_DIR}/AGENTS.md" ]; then
    cp "${SCRIPT_DIR}/AGENTS.md" "${CONFIG_DIR}/AGENTS.md" 2>/dev/null || true
fi

# 5. Jalankan OpenCode di direktori project saat ini
exec "$OPENCODE_BIN" "$@"
