#!/bin/bash
# Backup do MySQL (container do compose) com retenção.
#
# Uso:
#   ./scripts/backup-mysql.sh [dias_de_retencao]      (padrão: 7)
#
# Variáveis opcionais:
#   BACKUP_DIR  diretório de destino (padrão: ./backups)
#
# Agendamento (cron):
#   30 2 * * *  cd /caminho/do/repositorio && ./scripts/backup-mysql.sh 7 >> logs/backup.log 2>&1

set -euo pipefail

DIR="${BACKUP_DIR:-$(cd "$(dirname "$0")/.." && pwd)/backups}"
RETENTION="${1:-7}"
STAMP=$(date +%Y%m%d-%H%M%S)
FILE="$DIR/interlinkedlog-$STAMP.sql.gz"

mkdir -p "$DIR"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Gerando dump..."
docker compose exec -T mysql sh -c \
    'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE"' \
    | gzip > "$FILE"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Backup criado: $FILE ($(du -h "$FILE" | cut -f1))"

DELETED=$(find "$DIR" -name 'interlinkedlog-*.sql.gz' -mtime +"$RETENTION" -print -delete)
if [ -n "$DELETED" ]; then
    echo "Removidos por retenção ($RETENTION dias):"
    echo "$DELETED"
fi