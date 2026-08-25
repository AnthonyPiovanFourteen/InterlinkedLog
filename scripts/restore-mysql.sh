#!/bin/bash
# Restauração do backup MySQL no container do compose.
#
# Uso:
#   ./scripts/restore-mysql.sh backups/interlinkedlog-20260824-020000.sql.gz
#
# A restauração recria as tabelas do banco de destino (o mysqldump inclui
# DROP TABLE IF EXISTS antes de cada CREATE). Para restaurar em um banco
# separado de validação:
#
#   docker compose exec -T mysql mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e \
#     'CREATE DATABASE IF NOT EXISTS interlinkedlog_restore'
#   gunzip -c backups/interlinkedlog-xxx.sql.gz | docker compose exec -T mysql sh -c \
#     'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" interlinkedlog_restore'

set -euo pipefail

FILE="${1:?Uso: ./scripts/restore-mysql.sh backups/interlinkedlog-YYYYMMDD-HHMMSS.sql.gz}"

if [ ! -f "$FILE" ]; then
    echo "ERRO: arquivo não encontrado: $FILE" >&2
    exit 1
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Restaurando $FILE em $MYSQL_DATABASE..."
gunzip -c "$FILE" | docker compose exec -T mysql sh -c \
    'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Restauração concluída."