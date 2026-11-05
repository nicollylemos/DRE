#!/usr/bin/env bash
# Deploy na EC2:  sudo /opt/dre/app/scripts/deploy.sh <branch|tag|commit>
# 1. baixa o código  2. gera o .env a partir do Secrets Manager  3. builda a imagem
# 4. troca o container  5. só confirma se o /health ficar UP — senão volta sozinho pra versão anterior
DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib.sh
source "$DIR/lib.sh"

REF="${1:-main}"
cd "$APP_DIR" || exit 1
log "deploy de '$REF' no ambiente $ENVIRONMENT"

git fetch --all --tags --prune --quiet
if git show-ref --verify --quiet "refs/remotes/origin/$REF"; then
  git checkout --force --quiet -B "$REF" "origin/$REF"
else
  git checkout --force --quiet "$REF"
fi
REV="$(git rev-parse --short HEAD)"

# .env gerado aqui, permissão 600, nunca versionado
SECRET="$(db_secret)"
umask 077
cat > "$APP_DIR/.env" <<ENV
DB_HOST=$(jq -r .host <<<"$SECRET")
DB_PORT=$(jq -r '.port // 3306' <<<"$SECRET")
DB_USER=$(jq -r .username <<<"$SECRET")
DB_PASS=$(jq -r .password <<<"$SECRET")
DB_NAME=${DB_NAME:-dre_${ENVIRONMENT}}
APP_ENV=production
ENVIRONMENT=$ENVIRONMENT
AWS_REGION=$AWS_REGION
APP_TZ=America/Sao_Paulo
TRUST_PROXY=1
ENV
umask 022
ORIGIN_SECRET="$(aws secretsmanager get-secret-value --region "$AWS_REGION" \
  --secret-id "$ORIGIN_SECRET_ARN" --query SecretString --output text)"
echo "ORIGIN_SECRET=$ORIGIN_SECRET" >> "$APP_DIR/.env"
unset SECRET ORIGIN_SECRET

# pastas persistentes (fora do container) com dono www-data (uid 33)
mkdir -p "$DATA_DIR"/uploads/{documentos,fornecedores,parceiros,perfil} \
         "$DATA_DIR"/storage/{sessions,imports,notas,comprovantes}
chown -R 33:33 "$DATA_DIR"

log "build da imagem dre-app:$REV"
docker build --quiet -t "dre-app:$REV" --build-arg APP_REVISION="$REV" "$APP_DIR" >/dev/null

PREV="$(cat "$STATE_DIR/current_tag" 2>/dev/null || true)"
run_tag "$REV"

if wait_health 30; then
  [ -n "$PREV" ] && [ "$PREV" != "$REV" ] && echo "$PREV" > "$STATE_DIR/previous_tag"
  echo "$REV" > "$STATE_DIR/current_tag"
  echo "$(date -Is) $REF $REV" >> "$STATE_DIR/history.log"
  log "deploy OK: dre-app:$REV no ar"
  # guarda as 5 imagens mais recentes pra rollback rápido
  docker images dre-app --format '{{.CreatedAt}}\t{{.Tag}}' | sort -r | tail -n +6 | cut -f2 \
    | xargs -r -I{} docker rmi "dre-app:{}" >/dev/null 2>&1 || true
else
  log "health check falhou na versão $REV" ERROR
  if [ -n "$PREV" ]; then
    log "rollback automático para $PREV" WARN
    run_tag "$PREV"
    wait_health 30 && log "versão $PREV restaurada" WARN
  fi
  exit 1
fi
