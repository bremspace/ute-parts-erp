#!/usr/bin/env bash
# ==============================================================================
# Script Pembersih OMO Biasa untuk WSL2 / Linux / macOS
# ==============================================================================

set -e

CONFIG_DIR="$HOME/.config/opencode"
LOCAL_DIR="$HOME/.local/share"
CACHE_DIR="$HOME/.cache"

echo "Menghapus cache OMO biasa..."
rm -rf "$LOCAL_DIR/oh-my-opencode" \
       "$CACHE_DIR/oh-my-opencode" \
       "$CACHE_DIR/opencode/packages/oh-my-openagent@latest" \
       "$LOCAL_DIR/opencode/storage/oh-my-openagent"

echo "Memperbarui konfigurasi ke OMO-Slim murni..."
echo '["oh-my-opencode-slim"]' > "$CONFIG_DIR/tui.json"

cat << 'EOF' > "$CONFIG_DIR/cli.json"
{
  "$schema": "https://opencode.ai/v2/cli.json",
  "plugins": [
    "oh-my-opencode-slim"
  ]
}
EOF

if [ -f "$CONFIG_DIR/opencode.jsonc" ]; then
  sed -i '/oh-my-openagent/d' "$CONFIG_DIR/opencode.jsonc" || true
fi

if [ -f "$CONFIG_DIR/opencode.json" ]; then
  sed -i '/oh-my-openagent/d' "$CONFIG_DIR/opencode.json" || true
fi

echo "✔ Selesai! OMO biasa telah dihapus, OMO-Slim aktif murni."
echo "Jalankan 'opencode' kembali."
