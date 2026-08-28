#!/usr/bin/env bash
# kh-start — Lanza la GUI web del harness Kleo en una máquina (con anti-suspensión)
#
# Uso: ./kh-start <host|ssh-alias> [--port PUERTO] [--foreground]
#   host          máquina donde está el harness (ej: carolinaramos@172.16.0.18)
#   --port N      puerto de la Web UI (default 3091)
#   --foreground  ejecutar en primer plano (default: background + log en ~/kh-web.log)
#
# Requisitos en la máquina: node ≥22, npm config con @itfinden registry + token,
# y acceso SSH (preferiblemente con la llave Kleo-World).
set -euo pipefail

HOST="${1:-}"
PORT="3091"
FG=0
KEY="$(cd "$(dirname "${BASH_SOURCE[0]}")/../kleo-keys" 2>/dev/null && pwd)/kleo_world"
[ -f "$KEY" ] || KEY="$HOME/.ssh/kleo_world"
shift || true
while [ $# -gt 0 ]; do
  case "$1" in
    --port) PORT="$2"; shift 2;;
    --foreground) FG=1; shift;;
    *) echo "Arg desconocido: $1"; exit 1;;
  esac
done
[ -z "$HOST" ] && { echo "Uso: kh-start <host> [--port N] [--foreground]"; exit 1; }

SSH_BASE=(-o BatchMode=yes -o ConnectTimeout=8 -o StrictHostKeyChecking=accept-new)
if [ -f "$KEY" ]; then
  SSH_BASE+=(-i "$KEY" -o IdentitiesOnly=yes)
fi

echo "════════════════════════════════════════"
echo "  kh-start — GUI del harness en $HOST (puerto $PORT)"
echo "════════════════════════════════════════"

# Verificar acceso
if ! ssh "${SSH_BASE[@]}" "$HOST" 'true' 2>/dev/null; then
  echo "❌ Sin acceso SSH a $HOST (¿llave Kleo-World autorizada? ¿máquina encendida?)"
  exit 1
fi

# CAFFEINATE: anti-suspensión (solo macOS; en Linux no existe y no hace falta)
CAFF=""
command -v caffeinate >/dev/null 2>&1 && CAFF="caffeinate -d -s"

if [ "$FG" = "1" ]; then
  ssh "${SSH_BASE[@]}" -o ConnectTimeout=10 "$HOST" "
    export PATH=/usr/local/bin:/opt/homebrew/bin:\$PATH
    export NODE_OPTIONS=\"\${NODE_OPTIONS:---max-old-space-size=6144}\"
    export DSH_HOME=\"\$HOME/.kleo\"
    $CAFF npx --yes @itfinden/kh web --port $PORT
  " 2>&1 | grep -vE "WARNING|post-quantum|store now|upgraded|openssh.com/pq"
else
  ssh "${SSH_BASE[@]}" -o ConnectTimeout=10 "$HOST" "
    export PATH=/usr/local/bin:/opt/homebrew/bin:\$PATH
    export NODE_OPTIONS=\"\${NODE_OPTIONS:---max-old-space-size=6144}\"
    export DSH_HOME=\"\$HOME/.kleo\"
    nohup $CAFF npx --yes @itfinden/kh web --port $PORT > ~/kh-web.log 2>&1 &
    echo \"lanzado en background, log: ~/kh-web.log\"
  " 2>&1 | grep -vE "WARNING|post-quantum|store now|upgraded|openssh.com/pq"
  echo "⏳ Esperando a que la Web UI arranque (primera vez descarga paquetes)..."
  sleep 25
  ssh "${SSH_BASE[@]}" -o ConnectTimeout=10 "$HOST" "tail -15 ~/kh-web.log 2>/dev/null; echo '---'; lsof -iTCP:$PORT -sTCP:LISTEN -P 2>/dev/null | head -2 || echo 'aún no escucha'" 2>&1 | grep -vE "WARNING|post-quantum|store now|upgraded|openssh.com/pq"
  echo ""
  echo "📎 URL: http://$HOST:$PORT  (o http://IP:$PORT desde tu navegador)"
  echo "   Para ver logs: ssh $HOST 'tail -f ~/kh-web.log'"
fi
