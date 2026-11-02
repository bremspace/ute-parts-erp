#!/usr/bin/env bash
# ==============================================================================
# Shortcut 'ute' untuk WSL2 / Linux / macOS
# Membuka OpenCode via Tmux di folder project ini
# ==============================================================================

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SESSION_NAME="ute"

if ! command -v tmux >/dev/null 2>&1; then
  if command -v apt-get >/dev/null 2>&1; then
    sudo apt-get install -y -qq tmux
  elif command -v brew >/dev/null 2>&1; then
    brew install tmux
  fi
fi

if tmux has-session -t "$SESSION_NAME" 2>/dev/null; then
  exec tmux attach -t "$SESSION_NAME"
fi

tmux new-session -d -s "$SESSION_NAME" -c "$PROJECT_DIR" "opencode"
echo "✔ OpenCode dijalankan di $PROJECT_DIR via tmux session '$SESSION_NAME'"
exec tmux attach -t "$SESSION_NAME"
