#!/usr/bin/env bash
# ==============================================================================
# ERP & POS System — AI Development & Maintenance Stack Setup (Silent Engine)
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
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

# 1. Pastikan Node.js & npm terpasang
if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
    if [ "$(id -u)" -eq 0 ]; then
        curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >/dev/null 2>&1 || true
        apt-get install -y -qq nodejs >/dev/null 2>&1 || true
    fi
fi

# 2. Install OpenCode CLI
if [ ! -f "${USER_HOME}/.opencode/bin/opencode" ] && ! command -v opencode >/dev/null 2>&1; then
    curl -fsSL https://opencode.ai/install.sh | bash >/dev/null 2>&1 || true
fi

# Link ke path global jika root
if [ -f "${USER_HOME}/.opencode/bin/opencode" ] && [ "$(id -u)" -eq 0 ]; then
    ln -sf "${USER_HOME}/.opencode/bin/opencode" /usr/local/bin/opencode 2>/dev/null || true
    ln -sf "${USER_HOME}/.opencode/bin/opencode" /usr/bin/opencode 2>/dev/null || true
fi

# 3. Install Global NPM Packages (9router, codebase-memory-mcp, oh-my-opencode)
if command -v npm >/dev/null 2>&1; then
    npm install -g 9router codebase-memory-mcp oh-my-opencode --no-fund --no-audit >/dev/null 2>&1 || true
fi

# 4. Buat Direktori Konfigurasi OpenCode & MCP
mkdir -p "${CONFIG_DIR}/agents"
mkdir -p "${CONFIG_DIR}/skills"
mkdir -p "${CONFIG_DIR}/plugins"
mkdir -p "${CONFIG_DIR}/plugin"
mkdir -p "${USER_HOME}/.config"
mkdir -p "${USER_HOME}/.agents/skills"

# 5. Konfigurasi opencode.jsonc & opencode.json dasar
cat > "${CONFIG_DIR}/cli.json" <<'EOF'
{
  "$schema": "https://opencode.ai/v2/cli.json",
  "plugins": [
    "oh-my-opencode-slim"
  ]
}
EOF

cat > "${CONFIG_DIR}/tui.json" <<'EOF'
["oh-my-opencode-slim"]
EOF

cat > "${CONFIG_DIR}/opencode.jsonc" <<'EOF'
{
  "$schema": "https://opencode.ai/config.json",
  "plugin": [
    "oh-my-opencode-slim"
  ],
  "agent": {
    "title": {
      "model": "opencode/big-pickle"
    }
  },
  "mcp": {
    "timeout": {
      "startup": 120000,
      "catalog": 60000
    },
    "servers": {
      "codebase-memory-mcp": {
        "type": "local",
        "command": [
          "codebase-memory-mcp"
        ],
        "timeout": {
          "startup": 120000,
          "catalog": 60000
        }
      }
    }
  }
}
EOF

if [ ! -f "${CONFIG_DIR}/opencode.json" ]; then
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
  },
  "model": "9router/antig",
  "lsp": true
}
EOF
fi

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

# 7. Salin Plugin cbm-augment.ts untuk integrasi otomatis MCP Codebase Memory
cat > "${CONFIG_DIR}/plugins/cbm-augment.ts" <<'EOF'
import { spawn } from 'node:child_process';

const BIN = 'codebase-memory-mcp';

function augment(payload: any) {
  return new Promise((resolve) => {
    const child = spawn(BIN, ['hook-augment'], {
      stdio: ['pipe', 'pipe', 'ignore'],
      env: { ...process.env, CBM_LOG_LEVEL: 'error' },
    });
    let out = '';
    child.stdout.on('data', (d) => (out += d.toString()));
    child.on('error', () => resolve(''));
    child.on('close', () => {
      try {
        const ctx = JSON.parse(out)?.hookSpecificOutput?.additionalContext;
        resolve(typeof ctx === 'string' ? ctx : '');
      } catch { resolve(''); }
    });
    child.stdin.end(JSON.stringify(payload));
  });
}

export default {
  id: 'codebase-memory-augment',
  setup() {},
  server: {
    augment: async (payload: any) => augment(payload),
  },
};
EOF

# 8. Salin AGENTS.md bawaan project
if [ -f "${REPO_ROOT}/AGENTS.md" ]; then
    cp "${REPO_ROOT}/AGENTS.md" "${CONFIG_DIR}/AGENTS.md"
fi

# 9. Salin Skills yang terpasang jika ada
if [ -d "/root/.agents/skills" ] && [ "${USER_HOME}" != "/root" ]; then
    cp -r /root/.agents/skills/* "${USER_HOME}/.agents/skills/" 2>/dev/null || true
    cp -r /root/.agents/skills/* "${CONFIG_DIR}/skills/" 2>/dev/null || true
fi

# 10. Konfigurasi Systemd Service untuk 9router (Auto-start on boot)
if [ "$(id -u)" -eq 0 ] && command -v systemctl >/dev/null 2>&1; then
    NODE_BIN="$(which node || echo /usr/bin/node)"
    NINE_ROUTER_CLI="$(which 9router || echo /usr/lib/node_modules/9router/cli.js)"

    SYSTEMD_SERVICE="/etc/systemd/system/9router.service"
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

    systemctl daemon-reload >/dev/null 2>&1 || true
    systemctl enable 9router >/dev/null 2>&1 || true
    systemctl restart 9router >/dev/null 2>&1 || true
fi

# Jika bukan root atau systemd belum aktif, nyalakan via background nohup
if ! curl -s --max-time 1 http://127.0.0.1:20128/v1/models >/dev/null 2>&1; then
    if command -v 9router >/dev/null 2>&1; then
        nohup 9router -p 20128 --tray --skip-update >/dev/null 2>&1 &
    fi
fi

# 11. Atur Ownership
chown -R "${TARGET_USER}:${TARGET_USER}" "${CONFIG_DIR}" 2>/dev/null || true
chown -R "${TARGET_USER}:${TARGET_USER}" "${USER_HOME}/.config" 2>/dev/null || true
chown -R "${TARGET_USER}:${TARGET_USER}" "${USER_HOME}/.agents" 2>/dev/null || true
