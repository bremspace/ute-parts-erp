#!/usr/bin/env bash
# ==============================================================================
# ERP & POS System — Stop Background Server (Port 1741)
# ==============================================================================
PORT=1741
PID_FILE="storage/framework/server.pid"

echo "======================================================================"
echo "   ERP & POS System — Penghenti Server (Port $PORT)"
echo "======================================================================"
echo ""

STOPPED=0

# 0. Hentikan kontainer Docker jika Docker Compose aktif
if [ -f "compose.yaml" ] && (command -v docker >/dev/null 2>&1 || command -v sudo >/dev/null 2>&1); then
    echo "ℹ Menghentikan kontainer Docker Bremspace..."
    docker compose down 2>/dev/null || sudo docker compose down 2>/dev/null || true
    STOPPED=1
fi

# Hentikan Web Server
if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE" 2>/dev/null || true)
    if [ -n "$PID" ] && kill -0 "$PID" 2>/dev/null; then
        kill "$PID" 2>/dev/null || true
        echo "✔ [OK] Web Server (PID: $PID) berhasil dihentikan."
        STOPPED=1
    fi
    rm -f "$PID_FILE"
fi

# Hentikan Background Queue Worker
QUEUE_PID_FILE="storage/framework/queue.pid"
if [ -f "$QUEUE_PID_FILE" ]; then
    QPID=$(cat "$QUEUE_PID_FILE" 2>/dev/null || true)
    if [ -n "$QPID" ] && kill -0 "$QPID" 2>/dev/null; then
        kill "$QPID" 2>/dev/null || true
        echo "✔ [OK] Queue Worker (PID: $QPID) berhasil dihentikan."
        STOPPED=1
    fi
    rm -f "$QUEUE_PID_FILE"
fi

# Pastikan proses queue:work lain dihentikan
pkill -f "artisan queue:work" 2>/dev/null || true

if command -v fuser >/dev/null 2>&1; then
    if fuser "${PORT}/tcp" >/dev/null 2>&1; then
        fuser -k "${PORT}/tcp" >/dev/null 2>&1 || true
        STOPPED=1
    fi
fi

if command -v lsof >/dev/null 2>&1; then
    PIDS=$(lsof -ti :"$PORT" 2>/dev/null || true)
    if [ -n "$PIDS" ]; then
        kill -9 $PIDS 2>/dev/null || true
        STOPPED=1
    fi
fi

if [ "$STOPPED" = "1" ]; then
    echo "✔ [OK] Seluruh proses di port $PORT telah dihentikan."
else
    echo "ℹ [INFO] Tidak ada server yang berjalan di port $PORT."
fi
echo ""
