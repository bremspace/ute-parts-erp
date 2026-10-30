#!/usr/bin/env bash
# ==============================================================================
# Ute Parts ERP — AI Development & Maintenance Stack Setup
# Installs & Configures:
#   1. OpenCode CLI (opencode)
#   2. 9router (Local AI Model Proxy & Router on port 20128)
#   3. Oh-My-OpenCode-Slim (OMOSlim)
#   4. Codebase Memory Knowledge Graph MCP (codebase-memory-mcp)
#   5. Custom Agents, Skills & AGENTS.md
# ==============================================================================
set -euo pipefail

TARGET_USER="${SUDO_USER:-$(whoami)}"
USER_HOME=$(eval echo "~$TARGET_USER")
CONFIG_DIR="${USER_HOME}/.config/opencode"

echo "======================================================================"
echo "🤖 Menyiapkan AI Development & Maintenance Stack untuk: $TARGET_USER"
echo "======================================================================"

# 1. Pastikan Node.js & npm terpasang
if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
  echo "⟳ Menginstal Node.js 20 LTS & npm..."
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
fi

# 2. Install OpenCode CLI
if [ ! -f "${USER_HOME}/.opencode/bin/opencode" ] && ! command -v opencode >/dev/null 2>&1; then
  echo "⟳ Menginstal OpenCode CLI..."
  curl -fsSL https://opencode.ai/install.sh | bash || true
  # Link ke /usr/local/bin agar dapat dipanggil langsung
  if [ -f "${USER_HOME}/.opencode/bin/opencode" ]; then
    ln -sf "${USER_HOME}/.opencode/bin/opencode" /usr/local/bin/opencode || true
  fi
fi

# 3. Install Global NPM Packages (9router, codebase-memory-mcp, oh-my-opencode)
echo "⟳ Menginstal paket AI global (9router, codebase-memory-mcp, oh-my-opencode)..."
npm install -g 9router codebase-memory-mcp oh-my-opencode --no-fund --no-audit

# 4. Buat Direktori Konfigurasi OpenCode & MCP
mkdir -p "${CONFIG_DIR}/agents"
mkdir -p "${CONFIG_DIR}/skills"
mkdir -p "${CONFIG_DIR}/plugins"
mkdir -p "${USER_HOME}/.config"

# Salin konfigurasi opencode.json jika ada template di repo
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

# 5. Konfigurasi opencode.json dasar
cat > "${CONFIG_DIR}/opencode.json" <<'EOF'
{
  "$schema": "https://opencode.ai/config.json",
  "plugin": [
    "oh-my-opencode-slim"
  ],
  "provider": {
    "9router": {
      "npm": "@ai-sdk/openai-compatible",
      "options": {
        "baseURL": "http://127.0.0.1:20128/v1",
        "apiKey": "sk-1cea182039e3469b-3fpetn-d4040c26"
      },
      "models": {
        "kombo": {
          "name": "kombo",
          "modalities": {
            "input": ["text", "image"],
            "output": ["text"]
          }
        },
        "antig": {
          "name": "antig",
          "modalities": {
            "input": ["text", "image"],
            "output": ["text"]
          }
        }
      }
    }
  }
}
EOF

# 6. Konfigurasi MCP Settings (~/.config/mcp-settings.json)
cat > "${USER_HOME}/.config/mcp-settings.json" <<'EOF'
{
  "mcp": [
    {
      "id": "codebase-memory",
      "command": "codebase-memory-mcp",
      "args": [],
      "env": {
        "ENV": "production"
      }
    }
  ]
}
EOF

# 7. Salin AGENTS.md bawaan project
if [ -f "${REPO_ROOT}/AGENTS.md" ]; then
  cp "${REPO_ROOT}/AGENTS.md" "${CONFIG_DIR}/AGENTS.md"
fi

# 8. Konfigurasi Systemd Service untuk 9router (Auto-start on boot)
SYSTEMD_SERVICE="/etc/systemd/system/9router.service"
if [ "$(id -u)" -eq 0 ]; then
  NODE_BIN="$(which node)"
  NINE_ROUTER_CLI="$(which 9router || echo /usr/lib/node_modules/9router/cli.js)"

  cat > "$SYSTEMD_SERVICE" <<EOF
[Unit]
Description=9Router Local AI Proxy Service
After=network.target

[Service]
Type=simple
User=root
ExecStart=${NODE_BIN} ${NINE_ROUTER_CLI} --tray --skip-update -p 20128
Restart=always
RestartSec=5
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
EOF

  systemctl daemon-reload
  systemctl enable 9router
  systemctl restart 9router || true
  echo "✔ Service 9router berhasil dikonfigurasi & diaktifkan di port 20128."
fi

# 9. Atur Ownership
chown -R "${TARGET_USER}:${TARGET_USER}" "${CONFIG_DIR}" 2>/dev/null || true
chown -R "${TARGET_USER}:${TARGET_USER}" "${USER_HOME}/.config" 2>/dev/null || true

echo "======================================================================"
echo "✔ AI Development Stack (OpenCode + 9router + MCP) Siap Digunakan!"
echo "======================================================================"
