#!/usr/bin/env bash
# Backup lógico (complementa o backup automático do RDS):
#   - dump do MySQL compactado → s3://<bucket>/mysql/AAAA/MM/DD/
#   - uploads + storage (comprovantes, notas, contratos) → s3://<bucket>/files/ (bucket versionado)
# Roda todo dia pelo cron (/etc/cron.d/dre-backup) e pode ser chamado na mão.
DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib.sh
source "$DIR/lib.sh"
: "${BACKUP_BUCKET:?defina BACKUP_BUCKET em /opt/dre/deploy.env}"

SECRET="$(db_secret)"
HOST=$(jq -r .host <<<"$SECRET"); USER=$(jq -r .username <<<"$SECRET")
export MYSQL_PWD; MYSQL_PWD=$(jq -r .password <<<"$SECRET")   # env, não aparece no ps
unset SECRET
DB="${DB_NAME:-dre_${ENVIRONMENT}}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
KEY="mysql/$(date -u +%Y/%m/%d)/${DB}-${STAMP}.sql.gz"

log "dump de $DB → s3://$BACKUP_BUCKET/$KEY"
mysqldump -h "$HOST" -u "$USER" --single-transaction --routines --triggers "$DB" \
  | gzip -9 | aws s3 cp - "s3://$BACKUP_BUCKET/$KEY" --region "$AWS_REGION" --only-show-errors
echo "$KEY" | aws s3 cp - "s3://$BACKUP_BUCKET/mysql/LATEST" --region "$AWS_REGION" --only-show-errors

log "sync de arquivos → s3://$BACKUP_BUCKET/files/"
aws s3 sync "$DATA_DIR" "s3://$BACKUP_BUCKET/files/" --region "$AWS_REGION" --only-show-errors \
  --exclude 'storage/sessions/*'

log "backup OK ($KEY)"
