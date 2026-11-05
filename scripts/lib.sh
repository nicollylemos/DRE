#!/usr/bin/env bash
# Funções comuns dos scripts de operação (rodam na EC2 como root).
set -euo pipefail

APP_DIR=/opt/dre/app
export DATA_DIR=/opt/dre/data
STATE_DIR=/opt/dre/state
# shellcheck disable=SC1091
[ -f /opt/dre/deploy.env ] && source /opt/dre/deploy.env
: "${ENVIRONMENT:?defina ENVIRONMENT em /opt/dre/deploy.env}"
: "${AWS_REGION:=us-east-1}"
mkdir -p "$STATE_DIR"

log() { printf '%s [%s] %s\n' "$(date -Is)" "${2:-INFO}" "$1"; }

# Lê usuário/senha/host do RDS no Secrets Manager (via LabRole/instance profile — sem Access Key)
db_secret() {
  aws secretsmanager get-secret-value --region "$AWS_REGION" \
    --secret-id "$DB_SECRET_ARN" --query SecretString --output text
}

# Espera o /health responder UP (dentro da própria EC2)
wait_health() {
  local tries=${1:-30}
  for _ in $(seq "$tries"); do
    if curl -fsS http://localhost/health 2>/dev/null | grep -q '"status":"UP"'; then return 0; fi
    sleep 2
  done
  return 1
}

# Sobe uma versão da imagem (tag) com o compose de produção
run_tag() {
  cd "$APP_DIR"
  APP_TAG="$1" HOSTNAME_TAG="$(hostname -s)" docker compose -f docker-compose.aws.yml up -d --remove-orphans
}
