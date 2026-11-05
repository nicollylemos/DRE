#!/usr/bin/env bash
# Restore a partir do S3:
#   sudo scripts/restore.sh db                       → último dump (mysql/LATEST)
#   sudo scripts/restore.sh db mysql/2026/11/05/x.sql.gz
#   sudo scripts/restore.sh files                    → devolve uploads/storage do S3
# Para desastre maior (perdeu o RDS inteiro) use o Point-in-Time Restore do RDS — ver README.
DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib.sh
source "$DIR/lib.sh"
: "${BACKUP_BUCKET:?defina BACKUP_BUCKET em /opt/dre/deploy.env}"

case "${1:-}" in
  db)
    KEY="${2:-$(aws s3 cp "s3://$BACKUP_BUCKET/mysql/LATEST" - --region "$AWS_REGION")}"
    SECRET="$(db_secret)"
    HOST=$(jq -r .host <<<"$SECRET"); USER=$(jq -r .username <<<"$SECRET")
    export MYSQL_PWD; MYSQL_PWD=$(jq -r .password <<<"$SECRET"); unset SECRET
    DB="${DB_NAME:-dre_${ENVIRONMENT}}"
    if [ "${FORCE:-0}" != 1 ]; then
      read -r -p "Isso SOBRESCREVE o banco $DB com $KEY. Digite SIM: " ok
      [ "$ok" = SIM ] || { log "cancelado"; exit 1; }
    fi
    log "restaurando $KEY em $DB"
    mysql -h "$HOST" -u "$USER" -e "CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    aws s3 cp "s3://$BACKUP_BUCKET/$KEY" - --region "$AWS_REGION" | gunzip | mysql -h "$HOST" -u "$USER" "$DB"
    wait_health 15 && log "restore do banco OK — /health UP"
    ;;
  files)
    log "restaurando arquivos de s3://$BACKUP_BUCKET/files/"
    aws s3 sync "s3://$BACKUP_BUCKET/files/" "$DATA_DIR" --region "$AWS_REGION" --only-show-errors
    chown -R 33:33 "$DATA_DIR"
    log "restore de arquivos OK"
    ;;
  *) echo "uso: $0 db [chave-s3] | files"; exit 1 ;;
esac
