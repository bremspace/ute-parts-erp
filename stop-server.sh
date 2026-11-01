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

if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE" 2>/dev/null || true)
    if [ -n "$PID" ] && kill -0 "$PID" 2>/dev/null; then
        kill "$PID" 2>/dev/null || true
        echo "✔ [OK] Server (PID: $PID) berhasil dihentikan."
        STOPPED=1
    fi
    rm -f "$PID_FILE"
fi

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
