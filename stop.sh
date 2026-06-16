#!/bin/bash
# InterlinkedLog - Stop everything

DIR="$(cd "$(dirname "$0")" && pwd)"
PIDDIR="$DIR/.pids"

echo "=== Parando InterlinkedLog ==="

# Kill by PID files
if [ -f "$PIDDIR/backend.pid" ]; then
  kill $(cat "$PIDDIR/backend.pid") 2>/dev/null && echo "[backend] Parado"
fi

if [ -f "$PIDDIR/frontend.pid" ]; then
  kill $(cat "$PIDDIR/frontend.pid") 2>/dev/null && echo "[frontend] Parado"
fi

# Fallback: kill anything still on the ports
kill $(lsof -ti:3000) 2>/dev/null
kill $(lsof -ti:8000) 2>/dev/null

rm -f "$PIDDIR"/*.pid
echo "Parado."
