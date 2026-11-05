#!/usr/bin/env bash
# Rollback:
#   sudo scripts/rollback.sh            → volta pra imagem anterior (instantâneo, sem build)
#   sudo scripts/rollback.sh v3.2.0     → redeploy de uma tag/commit específico
DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib.sh
source "$DIR/lib.sh"

if [ -n "${1:-}" ]; then
  exec "$DIR/deploy.sh" "$1"
fi

PREV="$(cat "$STATE_DIR/previous_tag" 2>/dev/null || true)"
CUR="$(cat "$STATE_DIR/current_tag" 2>/dev/null || true)"
[ -z "$PREV" ] && { log "não existe versão anterior registrada" ERROR; exit 1; }
docker image inspect "dre-app:$PREV" >/dev/null 2>&1 || { log "imagem dre-app:$PREV não existe mais; use rollback.sh <tag>" ERROR; exit 1; }

log "rollback $CUR → $PREV" WARN
run_tag "$PREV"
if wait_health 30; then
  echo "$PREV" > "$STATE_DIR/current_tag"
  echo "$CUR" > "$STATE_DIR/previous_tag"
  echo "$(date -Is) rollback $PREV" >> "$STATE_DIR/history.log"
  log "rollback OK: dre-app:$PREV no ar"
else
  log "versão $PREV também não ficou saudável" ERROR; exit 1
fi
