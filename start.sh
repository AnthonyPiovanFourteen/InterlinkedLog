#!/bin/bash
# InterlinkedLog - Start backend + frontend

DIR="$(cd "$(dirname "$0")" && pwd)"
PIDDIR="$DIR/.pids"

echo "=== InterlinkedLog ==="

mkdir -p "$PIDDIR"
rm -f "$PIDDIR"/*.pid

# Kill anything on our ports first
kill $(lsof -ti:8000) 2>/dev/null || true
kill $(lsof -ti:3000) 2>/dev/null || true
sleep 1

# Seed + start backend
echo "[backend] Seeding + starting on :8000"
cd "$DIR/backend"
/home/anthonypiovan/.php-ext/php-sqlite artisan app:seed 2>&1
setsid /home/anthonypiovan/.php-ext/php-sqlite -S 127.0.0.1:8000 -t public > "$DIR/logs/backend.log" 2>&1 &
BACKEND_PID=$!
echo $BACKEND_PID > "$PIDDIR/backend.pid"
echo "  Backend PID $BACKEND_PID"

# Start frontend
echo "[frontend] Starting on :3000"
cd "$DIR"

BUN_CMD=""
for p in "$HOME/.bun/bin/bun" "/home/$USER/.bun/bin/bun" /usr/local/bin/bun; do
  [ -x "$p" ] && { BUN_CMD="$p"; break; }
done
[ -z "$BUN_CMD" ] && BUN_CMD="$(command -v bun 2>/dev/null)"
[ -z "$BUN_CMD" ] && BUN_CMD="$(command -v npm 2>/dev/null)"

if [ -n "$BUN_CMD" ]; then
  setsid "$BUN_CMD" run dev > "$DIR/logs/frontend.log" 2>&1 &
  FRONTEND_PID=$!
  echo $FRONTEND_PID > "$PIDDIR/frontend.pid"
  echo "  Frontend PID $FRONTEND_PID"
else
  echo "  ERRO: bun/npm não encontrado"
  exit 1
fi

sleep 5
echo ""
echo "  Frontend: http://localhost:3000"
echo "  Backend:  http://localhost:8000/api/v1"
echo "  Login:    admin@interlinked.io / admin123"
echo ""
echo "  ./stop.sh para parar tudo."
