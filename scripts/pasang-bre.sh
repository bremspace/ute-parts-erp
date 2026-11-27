#!/usr/bin/env bash
# ==============================================================================
# PASANG BRE — Universal AI Agent Harness Environment Setup
# ==============================================================================
# Mendukung SEMUA AI Agent Harness:
# 1. Antigravity CLI (agy / anti)
# 2. Claude Code (claude)
# 3. OpenCode (opencode)
# 4. Hermes Agent (hermes)
# 5. Codex CLI (codex)
# 6. Cursor IDE / Cursor Agent (cursor)
# 7. Windsurf Cascade (windsurf)
# 8. Cline / Roo Code (cline)
# 9. GitHub Copilot CLI / Chat
# ==============================================================================
set -e

# Detect directories (robust symlink resolution)
SOURCE="${BASH_SOURCE[0]}"
while [ -h "$SOURCE" ]; do
  DIR="$( cd -P "$( dirname "$SOURCE" )" >/dev/null 2>&1 && pwd )"
  SOURCE="$(readlink "$SOURCE")"
  [[ $SOURCE != /* ]] && SOURCE="$DIR/$SOURCE"
done
SCRIPT_DIR="$( cd -P "$( dirname "$SOURCE" )" >/dev/null 2>&1 && pwd )"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
TARGET_HOME="${HOME:-/root}"
BIN_DIR="/usr/local/bin"

if [ ! -w "$BIN_DIR" ]; then
    BIN_DIR="$TARGET_HOME/.local/bin"
    mkdir -p "$BIN_DIR"
fi

echo "======================================================================"
echo "⚡ MEMULAI 'PASANG BRE' — Setup Universal untuk SEMUA AI AGENT HARNESS"
echo "======================================================================"
echo "📁 Project Directory: $REPO_ROOT"
echo "👤 User / Home     : $(whoami) ($TARGET_HOME)"
echo "⚙️  Target Bin Dir  : $BIN_DIR"
echo ""

# ------------------------------------------------------------------------------
# 1. CEK AKUN GITHUB & ATURAN HAK AKSES PUSH
# ------------------------------------------------------------------------------
echo "🔐 [1/9] Memeriksa Akun GitHub Terhubung..."
CURRENT_GH_USER=""

# 1. Cek via gh cli jika login
if command -v gh >/dev/null 2>&1 && gh auth status >/dev/null 2>&1; then
    CURRENT_GH_USER=$(gh api user --jq .login 2>/dev/null || true)
fi

# 2. Cek via SSH GitHub jika gh tidak ada/belum login
if [ -z "$CURRENT_GH_USER" ]; then
    SSH_CHECK=$(ssh -T -o BatchMode=yes -o StrictHostKeyChecking=accept-new git@github.com 2>&1 || true)
    if echo "$SSH_CHECK" | grep -q "Hi "; then
        CURRENT_GH_USER=$(echo "$SSH_CHECK" | sed -n 's/.*Hi \([^!]*\)!.*/\1/p' | xargs)
    fi
fi

if [ -z "$CURRENT_GH_USER" ]; then
    echo "   ⚠️  PERINGATAN: Akun GitHub belum terhubung di environment ini!"
    echo "      Silakan hubungkan akun GitHub Anda:"
    echo "      1) Jalankan: gh auth login"
    echo "      2) Atau tambahkan SSH Key ke GitHub (ssh-keygen && gh ssh-key add)"
    echo "      Tanpa akun GitHub, Anda tidak akan bisa melakukan git push."
else
    echo "   ✔ Akun GitHub terdeteksi: @$CURRENT_GH_USER"
    if [ "$CURRENT_GH_USER" = "bremspace" ]; then
        echo "   👑 Hak Akses: OWNER (bremspace). Diizinkan push ke branch brem & main."
    else
        echo "   👤 Hak Akses: KOLABORATOR ($CURRENT_GH_USER)."
        echo "   ⚠️  ATURAN: Anda HANYA diizinkan push ke branch 'Unas' (atau branch fitur)."
        echo "      DILARANG KERAS push langsung ke branch 'main' atau 'brem'."
    fi
fi

# Pasang git pre-push hook untuk proteksi otomatis jika bukan bremspace
HOOK_FILE="$REPO_ROOT/.git/hooks/pre-push"
if [ -d "$REPO_ROOT/.git/hooks" ]; then
    cat <<'HOOK_EOF' > "$HOOK_FILE"
#!/usr/bin/env bash
# Git Pre-Push Protection Hook
CURRENT_USER=""
if command -v gh >/dev/null 2>&1 && gh auth status >/dev/null 2>&1; then
    CURRENT_USER=$(gh api user --jq .login 2>/dev/null || true)
fi
if [ -z "$CURRENT_USER" ]; then
    SSH_CHECK=$(ssh -T -o BatchMode=yes -o StrictHostKeyChecking=accept-new git@github.com 2>&1 || true)
    if echo "$SSH_CHECK" | grep -q "Hi "; then
        CURRENT_USER=$(echo "$SSH_CHECK" | sed -n 's/.*Hi \([^!]*\)!.*/\1/p' | xargs)
    fi
fi

while read local_ref local_sha remote_ref remote_sha; do
    if [[ "$remote_ref" =~ refs/heads/(main|brem)$ ]]; then
        if [ "$CURRENT_USER" != "bremspace" ]; then
            echo "❌ [GIT GUARD] PUSH DITOLAK!" >&2
            echo "   Akun GitHub Anda (@${CURRENT_USER:-unknown}) bukan 'bremspace'." >&2
            echo "   Kolaborator hanya diizinkan push ke branch 'Unas' atau branch fitur." >&2
            echo "   Silakan checkout branch Unas dan buat Pull Request via GitHub." >&2
            exit 1
        fi
    fi
done
exit 0
HOOK_EOF
    chmod +x "$HOOK_FILE"
    echo "   🛡️  Git pre-push hook terpasang (proteksi branch main & brem)"
fi

# ------------------------------------------------------------------------------
# 2. SINKRONISASI SKILLS KE SELURUH AGENT HARNESS
# ------------------------------------------------------------------------------
echo "📦 [2/9] Sinkronisasi Skills ke Semua Agent Directory..."

# Global standard
mkdir -p "$TARGET_HOME/.agents/skills"
if [ -d "$REPO_ROOT/.agents/skills" ]; then
    cp -rn "$REPO_ROOT/.agents/skills/"* "$TARGET_HOME/.agents/skills/" 2>/dev/null || true
fi

# Antigravity CLI
mkdir -p "$TARGET_HOME/.gemini/config"
cat <<JSON > "$TARGET_HOME/.gemini/config/skills.json"
{
  "entries": [
    { "path": "$TARGET_HOME/.agents/skills" },
    { "path": "$REPO_ROOT/.agents/skills" }
  ]
}
JSON

# Claude Code skills
mkdir -p "$TARGET_HOME/.claude/skills"
cp -rn "$REPO_ROOT/.agents/skills/"* "$TARGET_HOME/.claude/skills/" 2>/dev/null || true

# OpenCode skills
mkdir -p "$TARGET_HOME/.config/opencode/skills"
cp -rn "$REPO_ROOT/.agents/skills/"* "$TARGET_HOME/.config/opencode/skills/" 2>/dev/null || true

# Hermes Agent skills
mkdir -p "$TARGET_HOME/.hermes/skills/productivity"
for s in caveman ponytail; do
    if [ -d "$REPO_ROOT/.agents/skills/$s" ]; then
        cp -rn "$REPO_ROOT/.agents/skills/$s" "$TARGET_HOME/.hermes/skills/productivity/" 2>/dev/null || true
    fi
done

# Codex CLI skills
mkdir -p "$TARGET_HOME/.codex/skills"
cp -rn "$REPO_ROOT/.agents/skills/"* "$TARGET_HOME/.codex/skills/" 2>/dev/null || true

# Cursor skills
mkdir -p "$TARGET_HOME/.cursor/skills"
cp -rn "$REPO_ROOT/.agents/skills/"* "$TARGET_HOME/.cursor/skills/" 2>/dev/null || true

echo "   ✔ 175+ Skills tersambung ke Antigravity, Claude, OpenCode, Hermes, Codex, Cursor"

# ------------------------------------------------------------------------------
# 3. PASANG PLUGIN & RULES ANTIGRAVITY
# ------------------------------------------------------------------------------
echo "🔌 [3/9] Memasang Plugins untuk Antigravity..."
mkdir -p "$TARGET_HOME/.gemini/config/plugins"
for plugin in caveman ponytail rtk; do
    if [ -d "$REPO_ROOT/.agents/plugins/$plugin" ]; then
        mkdir -p "$TARGET_HOME/.gemini/config/plugins/$plugin"
        cp -rn "$REPO_ROOT/.agents/plugins/$plugin/"* "$TARGET_HOME/.gemini/config/plugins/$plugin/" 2>/dev/null || true
    fi
done
# Ensure rules/AGENTS.md
mkdir -p "$TARGET_HOME/.gemini/config/plugins/ponytail/rules"
[ -f "$REPO_ROOT/.agents/rules/ponytail.md" ] && cp "$REPO_ROOT/.agents/rules/ponytail.md" "$TARGET_HOME/.gemini/config/plugins/ponytail/rules/AGENTS.md"
[ -f "$REPO_ROOT/.agents/rules/caveman.md" ] && mkdir -p "$TARGET_HOME/.gemini/config/plugins/caveman/rules" && cp "$REPO_ROOT/.agents/rules/caveman.md" "$TARGET_HOME/.gemini/config/plugins/caveman/rules/AGENTS.md"
[ -f "$REPO_ROOT/.agents/rules/rtk.md" ] && mkdir -p "$TARGET_HOME/.gemini/config/plugins/rtk/rules" && cp "$REPO_ROOT/.agents/rules/rtk.md" "$TARGET_HOME/.gemini/config/plugins/rtk/rules/AGENTS.md"
echo "   ✔ Antigravity Plugins (Caveman, Ponytail, RTK) terpasang"

# ------------------------------------------------------------------------------
# 4. PASANG PLUGIN & RULES CLAUDE CODE
# ------------------------------------------------------------------------------
echo "🤖 [4/9] Konfigurasi Claude Code..."
mkdir -p "$TARGET_HOME/.claude"
if [ ! -f "$TARGET_HOME/.claude/CLAUDE.md" ]; then
    echo "@RTK.md" > "$TARGET_HOME/.claude/CLAUDE.md"
fi
# Configure RTK hook in settings.json if rtk binary exists
if command -v rtk >/dev/null 2>&1; then
    rtk init -g --agent claude --auto-patch 2>/dev/null || true
fi
echo "   ✔ Claude Code terkonfigurasi"

# ------------------------------------------------------------------------------
# 5. PASANG PLUGIN & RULES OPENCODE
# ------------------------------------------------------------------------------
echo "🧠 [5/9] Konfigurasi OpenCode..."
mkdir -p "$TARGET_HOME/.config/opencode/plugins"
if [ -d "$REPO_ROOT/.agents/plugins/caveman" ]; then
    cp -rn "$REPO_ROOT/.agents/plugins/caveman" "$TARGET_HOME/.config/opencode/plugins/" 2>/dev/null || true
fi
if [ -d "$REPO_ROOT/.agents/opencode/commands" ]; then
    mkdir -p "$TARGET_HOME/.config/opencode/commands"
    cp -rn "$REPO_ROOT/.agents/opencode/commands/"* "$TARGET_HOME/.config/opencode/commands/" 2>/dev/null || true
fi
if [ -d "$REPO_ROOT/.agents/opencode/agents" ]; then
    mkdir -p "$TARGET_HOME/.config/opencode/agents"
    cp -rn "$REPO_ROOT/.agents/opencode/agents/"* "$TARGET_HOME/.config/opencode/agents/" 2>/dev/null || true
fi
if command -v rtk >/dev/null 2>&1; then
    rtk init -g --opencode --auto-patch 2>/dev/null || true
fi
echo "   ✔ OpenCode terkonfigurasi"

# ------------------------------------------------------------------------------
# 6. PASANG PLUGIN & RULES HERMES AGENT
# ------------------------------------------------------------------------------
echo "🕊️  [6/9] Konfigurasi Hermes Agent..."
mkdir -p "$TARGET_HOME/.hermes/plugins"
if command -v rtk >/dev/null 2>&1; then
    rtk init -g --agent hermes --auto-patch 2>/dev/null || true
fi
echo "   ✔ Hermes Agent terkonfigurasi"

# ------------------------------------------------------------------------------
# 7. PASANG RULES CODEX, CURSOR, WINDSURF, CLINE
# ------------------------------------------------------------------------------
echo "🎯 [7/9] Konfigurasi Codex, Cursor, Windsurf, Cline..."
mkdir -p "$TARGET_HOME/.codex"
if command -v rtk >/dev/null 2>&1; then
    rtk init -g --codex --auto-patch 2>/dev/null || true
fi
echo "   ✔ Codex, Cursor, Windsurf, Cline siap"

# ------------------------------------------------------------------------------
# 8. WORKSPACE CROSS-AGENT ENTRYPOINTS
# ------------------------------------------------------------------------------
echo "🌐 [8/9] Menyiapkan Workspace Entrypoints..."
if [ ! -f "$REPO_ROOT/CLAUDE.md" ]; then
    echo -e "# Claude Code Instructions\nSee @AGENTS.md for complete workspace instructions, rules, architecture, and 'pasang bre'." > "$REPO_ROOT/CLAUDE.md"
fi
if [ ! -f "$REPO_ROOT/GEMINI.md" ]; then
    ln -sf AGENTS.md "$REPO_ROOT/GEMINI.md" 2>/dev/null || true
fi
if [ ! -f "$REPO_ROOT/.cursorrules" ]; then
    echo -e "# Cursor Rules\nSee AGENTS.md for project specifications, rules, design system, and agent procedures." > "$REPO_ROOT/.cursorrules"
fi
if [ ! -f "$REPO_ROOT/.windsurfrules" ]; then
    echo -e "# Windsurf Cascade Rules\nSee AGENTS.md for project specifications, rules, design system, and agent procedures." > "$REPO_ROOT/.windsurfrules"
fi
if [ ! -f "$REPO_ROOT/.clinerules" ]; then
    echo -e "# Cline / Roo Code Rules\nSee AGENTS.md for project specifications, rules, design system, and agent procedures." > "$REPO_ROOT/.clinerules"
fi
echo "   ✔ Workspace file lintas-agent terverifikasi"

# ------------------------------------------------------------------------------
# 9. SHORTCUT 'anti' & CONTROLLER 'agent-skills'
# ------------------------------------------------------------------------------
echo "🚀 [9/9] Mengaktifkan Shortcut & Trio Switcher..."
# Copy controller
if [ -f "$REPO_ROOT/scripts/agent-skills.py" ]; then
    cp "$REPO_ROOT/scripts/agent-skills.py" "$BIN_DIR/agent-skills"
    chmod +x "$BIN_DIR/agent-skills"
    ln -sf "$BIN_DIR/agent-skills" "$BIN_DIR/agent-mode" 2>/dev/null || true
    ln -sf "$BIN_DIR/agent-skills" "$BIN_DIR/skills-mode" 2>/dev/null || true
fi

# Shortcut anti
if [ -f "$REPO_ROOT/scripts/anti.sh" ]; then
    cp "$REPO_ROOT/scripts/anti.sh" "$BIN_DIR/anti"
    chmod +x "$BIN_DIR/anti"
fi

# Bash aliases (delegasikan ke /usr/local/bin/anti agar tidak duplikat logika)
cat <<'SHELLRC' > "$TARGET_HOME/.bash_aliases"
# Shortcut Antigravity ERP
anti() {
    if [ -x /usr/local/bin/anti ]; then
        /usr/local/bin/anti "$@"
    else
        cd /var/www/ute-parts-erp 2>/dev/null || return 1
        bash scripts/anti.sh "$@"
    fi
}
SHELLRC

if [ -w "/etc/profile.d" ]; then
    cp "$TARGET_HOME/.bash_aliases" /etc/profile.d/anti.sh 2>/dev/null || true
    chmod 644 /etc/profile.d/anti.sh 2>/dev/null || true
fi

# AKTIFKAN TRIO DI SEMUA AGENT SEKARANG
if command -v agent-skills >/dev/null 2>&1; then
    agent-skills on
elif [ -x "$BIN_DIR/agent-skills" ]; then
    "$BIN_DIR/agent-skills" on
fi

echo ""
echo "======================================================================"
echo "🎉 'PASANG BRE' SUKSES! SELURUH AI AGENT HARNESS TELAH TERPASANG & AKTIF"
echo "======================================================================"
if command -v agent-skills >/dev/null 2>&1; then
    agent-skills status
fi
echo ""
echo "💡 Kontrol Global:"
echo "  • 'agent-skills on'     -> Aktifkan Caveman + Ponytail + RTK di semua agent"
echo "  • 'agent-skills off'    -> Matikan di semua agent (Normal Mode)"
echo "  • 'agent-skills status' -> Cek status di semua agent"
echo "======================================================================"
